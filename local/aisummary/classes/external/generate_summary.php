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

    /**
     * Define input parameters.
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'title'   => new external_value(PARAM_TEXT, 'Topic or course title', VALUE_REQUIRED),
            'context' => new external_value(PARAM_RAW,  'Short description / hints to disambiguate the title', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Main execution logic.
     */
    public static function execute($title, $context = '') {
        global $CFG;

        // Validate input
        $params = self::validate_parameters(self::execute_parameters(), [
            'title'   => $title,
            'context' => $context,
        ]);

        $title   = trim($params['title']);
        $context = trim($params['context']);

        // Require at least some context
        if (empty($context)) {
            throw new \moodle_exception('emptytext', 'local_aisummary', '', null, 'Please enter a short description');
        }

        // Stop early if ambiguous title with too little context
        if ((preg_match('/^[A-Z0-9]{2,6}$/', $title) || mb_strlen($title) < 3) && mb_strlen($context) < 10) {
            throw new \moodle_exception('needmorecontext', 'local_aisummary', '', null, 'Add more context to clarify the topic.');
        }

        // Load API settings from Moodle config
        $apibase   = trim((string) get_config('local_aisummary', 'apibase'));
        $apikey    = trim((string) get_config('local_aisummary', 'apikey'));
        $model     = trim((string) get_config('local_aisummary', 'model'));
        $maxtokens = (int) get_config('local_aisummary', 'maxtokens');

        if ($apibase === '' || $apikey === '' || $model === '') {
            throw new \moodle_exception('apimissing', 'local_aisummary', '', null, 'API base, key or model is not configured');
        }

        // Ensure correct endpoint
        $fullApiUrl = $apibase;
        if (substr($apibase, -15) !== '/chat/completions') {
            $fullApiUrl = rtrim($apibase, '/') . '/chat/completions';
        }

        // Minimal URL check
        if (stripos($fullApiUrl, 'https://') !== 0) {
            throw new \moodle_exception('badresponse', 'local_aisummary', '', null, 'Invalid API base URL (must start with https://)');
        }

        // Build a strong prompt to ensure actual generation
        $sys = "You are an assistant that writes a clear, engaging introduction (about 4–5 lines, 150–200 words). "
             . "The response must be original, factual, and directly related to the given topic. "
             . "Avoid bullet points, headings, or markdown. If context is insufficient, reply exactly with: INSUFFICIENT_CONTEXT.";

        $usercontent = "Title: {$title}\n"
                     . "Context: " . ($context !== '' ? $context : '(none)') . "\n\n"
                     . "Write an engaging introduction of around 150–200 words on this topic.";

        $payload = [
            'model'       => $model,
            'max_tokens'  => $maxtokens ?: 600,
            'temperature' => 0.4,
            'messages'    => [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user',   'content' => $usercontent],
            ],
        ];

        // Prepare cURL
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $apikey);
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('HTTP-Referer: ' . $CFG->wwwroot);
        $curl->setHeader('X-Title: Moodle AI Summary');

        // Call API
        $resp = $curl->post($fullApiUrl, json_encode($payload));

        if ($resp === false || $curl->get_errno()) {
            $error = $curl->get_errno() ? $curl->error : 'Unknown cURL error';
            throw new \moodle_exception('curlerror', 'local_aisummary', '', null, $error);
        }

        $info = $curl->get_info();
        $code = (int)($info['http_code'] ?? 0);

        // Check API status
        if ($code < 200 || $code >= 300) {
            $snippet = trim((string)$resp);
            if (mb_strlen($snippet) > 600) {
                $snippet = mb_substr($snippet, 0, 600) . '…';
            }
            throw new \moodle_exception(
                'badresponse',
                'local_aisummary',
                '',
                null,
                'HTTP ' . $code . ' | URL: ' . $fullApiUrl . ' | Body: ' . $snippet
            );
        }

        // Decode response
        $data = json_decode($resp, true);
        $text = '';

        if (isset($data['choices'][0]['message']['content'])) {
            $text = (string)$data['choices'][0]['message']['content'];
        } else if (isset($data['choices'][0]['text'])) {
            $text = (string)$data['choices'][0]['text'];
        }

        $text = trim($text);

        // Handle insufficient context
        if ($text === '' || strtoupper($text) === 'INSUFFICIENT_CONTEXT') {
            throw new \moodle_exception('emptytext', 'local_aisummary', '', null, 'AI could not generate text. Please provide more context.');
        }

        return ['summary' => $text];
    }

    /**
     * Define return structure.
     */
    public static function execute_returns() {
        return new external_single_structure([
            'summary' => new external_value(PARAM_RAW, 'Generated summary'),
        ]);
    }
}
