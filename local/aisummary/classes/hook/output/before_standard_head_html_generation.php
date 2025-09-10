<?php
namespace local_aisummary\hook\output;

use core\hook\output\before_standard_head_html_generation as hook;

class before_standard_head_html_generation {
    public function __invoke(hook $hook): void {
        global $PAGE;
        $url = $PAGE->url && method_exists($PAGE->url, 'out_as_local_url')
            ? $PAGE->url->out_as_local_url()
            : '';
        if (strpos($url, '/course/edit.php') !== false) {
            $PAGE->requires->js_call_amd('local_aisummary/attach', 'init', []);
        }
    }
}
