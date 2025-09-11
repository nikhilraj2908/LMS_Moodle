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

        // --- settings ---
        $apibase   = trim((string)get_config('local_aisummary', 'apibase'));
        $apikey    = trim((string)get_config('local_aisummary', 'apikey'));
        $model     = trim((string)(get_config('local_aisummary', 'model') ?: 'meta-llama/llama-3-8b-instruct:free'));
        $maxtokens = (int)(get_config('local_aisummary', 'maxtokens') ?? 180);

        if ($apibase === '') {
            $apibase = 'https://openrouter.ai/api/v1/chat/completions';
        }

        $isopenrouter = (strpos($apibase, 'openrouter.ai') !== false);
        if ($isopenrouter && $apikey === '') {
            throw new \moodle_exception('missingconfig', 'local_aisummary', '', null,
                'Configure API Key for OpenRouter in Site administration ▶ Plugins ▶ Local plugins ▶ AI Summary');
        }

        $headers = ['Content-Type: application/json'];
        if ($apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }
        if ($isopenrouter) {
            $headers[] = 'HTTP-Referer: ' . rtrim($CFG->wwwroot, '/');
            $headers[] = 'X-Title: Moodle AI Summary';
        }

        // Updated prompts to be more directive
        $system = "You generate concise Moodle course summaries (3–5 sentences, ~120–160 words) based ONLY on the course title. Do NOT ask for more details. Use general knowledge if needed.";
        $user   = "Generate a course summary about: {$title}";

        $payloadBase = [
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.7,
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

        $json = null; $lastRaw = ''; $lastStatus = 0;
        foreach ($candidates as $m) {
            $payload = $payloadBase + ['model' => $m];

            $ch = curl_init($apibase);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 25,
            ]);

            $raw    = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            curl_close($ch);

            if ($errno) {
                throw new \moodle_exception('curlerror', 'local_aisummary', '', null, $errmsg);
            }

            if ($status >= 200 && $status < 300) {
                $json = json_decode($raw, true);
                break;
            }

            if (!($status == 404 && stripos($raw, 'No endpoints found') !== false)) {
                $lastRaw = $raw; $lastStatus = $status;
                break;
            }
            $lastRaw = $raw; $lastStatus = $status;
        }

        if (!$json) {
            throw new \moodle_exception('badresponse', 'local_aisummary', '', null, 'HTTP '.$lastStatus.' '.$lastRaw);
        }

        $text = $json['choices'][0]['message']['content'] ?? $json['choices'][0]['text'] ?? '';
        if ($text === '') {
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