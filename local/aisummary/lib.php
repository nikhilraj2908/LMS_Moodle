<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Load our AMD module; it will self-exit on non-course-edit pages.
 */
function local_aisummary_extend_navigation(global_navigation $nav) {
    global $PAGE;
    $PAGE->requires->js_call_amd('local_aisummary/attach', 'init', []);
}
