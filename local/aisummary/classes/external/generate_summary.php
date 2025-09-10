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

        // Settings (safe defaults)
        $apibase   = trim((string)get_config('local_aisummary', 'apibase'));
        $apikey    = trim((string)get_config('local_aisummary', 'apikey'));
        $model     = trim((string)(get_config('local_aisummary', 'model') ?: 'google/gemma-2-9b-it:free'));
        $maxtokens = (int)(get_config('local_aisummary', 'maxtokens') ?? 600);

        if ($apibase === '') {
            $apibase = 'https://openrouter.ai/api/v1/chat/completions';
        }

        $isopenrouter = (strpos($apibase, 'openrouter.ai') !== false);

        $headers = ['Content-Type: application/json'];
        if ($apikey !== '') { $headers[] = 'Authorization: Bearer ' . $apikey; }
        if ($isopenrouter) {
            $headers[] = 'HTTP-Referer: ' . rtrim($CFG->wwwroot, '/');
            $headers[] = 'X-Title: Moodle AI Summary';
        }

        // Constraints
        $MINLINES = 15;
        $MAXLINES = 30;

        // Title -> keywords (anchor relevance)
        $stop = ['the','and','for','with','from','into','your','about','course','module','learn','learning',
                 'introduction','intro','of','in','to','on','by','a','an'];
        $words = preg_split('/[^a-z0-9]+/i', strtolower($title));
        $kw = [];
        foreach ($words as $w) {
            if ($w !== '' && strlen($w) >= 3 && !in_array($w, $stop)) { $kw[$w] = true; }
        }
        $keywords = implode(', ', array_slice(array_keys($kw), 0, 6));

        // Strict prompts (no questions/meta)
        $system = "You are CourseSummaryBot.
Rules:
- ONLY bullet lines (one idea per line). No headings/paragraphs.
- Stay exactly on the title topic.
- Never ask questions or request more info.
- Do NOT mention Moodle/LMS/course formats.
- {$MINLINES}–{$MAXLINES} lines total; each line <= 20 words.
- Tone: clear, neutral, practical.";

        $user = "Title: {$title}
Keywords (use naturally): {$keywords}
Write the bullet lines now.";

        $basePayload = [
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.2,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'max_tokens' => $maxtokens,
        ];

        $candidates = [$model];
        if ($isopenrouter) {
            $candidates = array_unique(array_filter([
                $model,
                'google/gemma-2-9b-it:free',
                'qwen/qwen2.5-7b-instruct:free',
                'mistralai/mistral-7b-instruct:free',
                'meta-llama/llama-3-8b-instruct:free',
            ]));
        }

        $call = function(array $payload) use ($apibase, $headers) {
            $ch = curl_init($apibase);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_TIMEOUT        => 35,
            ]);
            $raw    = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            curl_close($ch);
            return [$status,$raw,$errno,$errmsg];
        };

        $extract = function($raw) {
            $json = json_decode($raw, true);
            if (!is_array($json)) return '';
            $t = $json['choices'][0]['message']['content'] ?? null;
            if (is_string($t) && trim($t) !== '') return $t;
            if (isset($json['choices'][0]['message']['content']) && is_array($json['choices'][0]['message']['content'])) {
                $pieces = [];
                foreach ($json['choices'][0]['message']['content'] as $seg) {
                    if (is_array($seg) && isset($seg['text'])) $pieces[] = $seg['text'];
                    elseif (is_string($seg)) $pieces[] = $seg;
                }
                $joined = trim(implode("\n", $pieces));
                if ($joined !== '') return $joined;
            }
            $t = $json['choices'][0]['content'] ?? $json['choices'][0]['text'] ?? null;
            if (is_string($t) && trim($t) !== '') return $t;
            if (is_array($t)) {
                $joined = trim(implode("\n", array_map(function($x){ return is_string($x)?$x:''; }, $t)));
                if ($joined !== '') return $joined;
            }
            foreach (['output_text','response'] as $alt) {
                if (!empty($json[$alt]) && is_string($json[$alt])) return $json[$alt];
            }
            return '';
        };

        $text = ''; $lastRaw=''; $lastStatus=0;
        foreach ($candidates as $m) {
            $payload = $basePayload + ['model' => $m];
            [$status,$raw,$errno,$errmsg] = $call($payload);

            if ($errno) throw new \moodle_exception('curlerror', 'local_aisummary', '', null, $errmsg);

            if ($status >= 200 && $status < 300) {
                $text = $extract($raw);
                if ($text !== '') break;
            } else {
                if (!($status == 404 && stripos($raw, 'No endpoints found') !== false)) {
                    $lastRaw = $raw; $lastStatus = $status; break;
                }
            }
            $lastRaw = $raw; $lastStatus = $status;
        }

        $norm = function($s){ $s = preg_replace('/\r\n?/', "\n", trim($s)); return strip_tags($s); };
        $text = $norm($text);

        $looksmeta = (
            preg_match('/\b(please provide|more context|what kind of|tone do you want|target audience|keywords)\b/i', $text) ||
            substr_count($text, '?') >= 2
        );
        $offtopic = (stripos($text, 'moodle') !== false && stripos($title, 'moodle') === false);

        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $text))));
        $badcount = (count($lines) < $MINLINES || count($lines) > $MAXLINES);

        if ($text === '' || $text === '.' || $looksmeta || $offtopic || $badcount) {
            $retryUser = "Your previous output broke the rules. Try again for title '{$title}'.\n".
                         "Rules (must follow exactly):\n".
                         "- Output bullet lines ONLY (start each line with '- '), no headings or paragraphs.\n".
                         "- Total lines between {$MINLINES} and {$MAXLINES}.\n".
                         "- Each line <= 20 words.\n".
                         "- Do NOT ask questions or mention Moodle/LMS/course formats.\n".
                         "Keywords: {$keywords}\n".
                         "Write the bullet lines now.";

            $retryPayload = $basePayload;
            $retryPayload['messages'] = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $retryUser],
            ];
            $retryPayload['model'] = $candidates[0];

            [$status2,$raw2,$errno2,$err2] = $call($retryPayload);
            if ($errno2) throw new \moodle_exception('curlerror', 'local_aisummary', '', null, $err2);
            if ($status2 >= 200 && $status2 < 300) {
                $text2 = $extract($raw2);
                if ($text2 !== '') {
                    $text = $norm($text2);
                    $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $text))));
                }
            }
        }

        if ($text === '' || $text === '.') {
            if (debugging('', DEBUG_DEVELOPER)) {
                debugging('AI raw response (emptytext): '.($lastRaw ?: '[none]'), DEBUG_DEVELOPER);
            }
            throw new \moodle_exception('emptytext', 'local_aisummary');
        }

        $out = [];
        for ($i=0; $i<count($lines); $i++) {
            $ln = $lines[$i];
            if ($ln === '') continue;
            $out[] = preg_match('/^[-*•]\s/', $ln) ? $ln : ('- '.$ln);
        }
        if (count($out) > $MAXLINES) $out = array_slice($out, 0, $MAXLINES);

        return ['summary' => implode("\n", $out)];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'summary' => new external_value(PARAM_RAW, 'Generated summary'),
        ]);
    }
}
