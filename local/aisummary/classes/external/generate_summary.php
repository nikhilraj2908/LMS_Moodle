<?php
namespace local_aisummary\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class generate_summary extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'title' => new external_value(PARAM_TEXT, 'Course title'),
        ]);
    }

    public static function execute(string $title) {
        global $CFG;

        self::validate_parameters(self::execute_parameters(), ['title' => $title]);
        require_sesskey();
        require_login();

        // --- Read config with safe defaults ---
        $apibase   = trim((string)get_config('local_aisummary', 'apibase'));
        $apikey    = trim((string)get_config('local_aisummary', 'apikey'));
        $model     = trim((string)(get_config('local_aisummary', 'model') ?: 'meta-llama/llama-3-8b-instruct:free'));
        $maxtokens = (int)(get_config('local_aisummary', 'maxtokens') ?? 600);

        if ($apibase === '') {
            $apibase = 'https://openrouter.ai/api/v1/chat/completions';
        }

        $isopenrouter = (strpos($apibase, 'openrouter.ai') !== false);
        if ($isopenrouter && $apikey === '') {
            throw new \moodle_exception('missingconfig', 'local_aisummary', '', null,
                'Configure API Key in Site administration ▶ Plugins ▶ Local plugins ▶ AI Summary');
        }

        $headers = ['Content-Type: application/json'];
        if ($apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }
        // IMPORTANT: do NOT send Referer for HTTP sites or when key has no origin restriction.
        if ($isopenrouter) {
            $headers[] = 'X-Title: Moodle AI Summary';
        }

        // --- Tight prompt: bullet lines only, no meta questions ---
        $system = "You are CourseSummaryBot.
Rules:
- Produce ONLY bullet lines (one idea per line). No headings/paragraphs.
- Stay strictly on the course title topic. Do not ask for more info.
- 15–30 lines total, each ≤ 50 words.
- Neutral, practical tone. Plain text/Markdown.";

        $user = "Title: {$title}
Write the bullet lines now.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
            'max_tokens'  => $maxtokens,
        ];

        $candidates = [$model];
        if ($isopenrouter) {
            $candidates = array_unique(array_filter([
                $model,
                'meta-llama/llama-3-8b-instruct:free',
                'google/gemma-2-9b-it:free',
                'qwen/qwen2.5-7b-instruct:free',
                'mistralai/mistral-7b-instruct:free',
            ]));
        }

        $text = ''; $lastRaw=''; $lastStatus=0;

        foreach ($candidates as $m) {
            $payload['model'] = $m;

            $ch = curl_init($apibase);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 35,
            ]);

            $raw    = curl_exec($ch);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno) {
                throw new \moodle_exception('curlerror', 'local_aisummary', '', null, $errmsg);
            }
            if ($status >= 200 && $status < 300) {
                $json = json_decode($raw, true);
                $text = $json['choices'][0]['message']['content']
                     ?? $json['choices'][0]['text']
                     ?? '';
                if (trim($text) !== '') { break; }
            } else {
                // If not the OpenRouter "No endpoints found" 404, stop and surface it
                $lastRaw = $raw; $lastStatus = $status;
                if (!($status == 404 && stripos($raw, 'No endpoints found') !== false)) break;
            }
        }

        if (trim($text) === '') {
            if ($lastStatus) {
                throw new \moodle_exception('badresponse', 'local_aisummary', '', null, 'HTTP '.$lastStatus.' '.$lastRaw);
            }
            throw new \moodle_exception('emptytext', 'local_aisummary');
        }

        return ['summary' => trim($text)];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'summary' => new external_value(PARAM_RAW, 'Generated summary'),
        ]);
    }
}
