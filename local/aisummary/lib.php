<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Load our AMD on the course edit page.
 * Using a widely-called callback so we can enqueue JS safely.
 */
function local_aisummary_extend_navigation(\global_navigation $nav) {
    global $PAGE;
    // Only on Course > Edit settings
    if ($PAGE->url && $PAGE->url->compare(new moodle_url('/course/edit.php'), URL_MATCH_BASE)) {
        $PAGE->requires->js_call_amd('local_aisummary/attach', 'init');
    }
}
