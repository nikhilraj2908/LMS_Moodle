<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Load our AMD on every page (the JS itself will exit unless on /course/edit.php).
 */
function local_aisummary_extend_navigation(global_navigation $nav) {
    global $PAGE;
    $PAGE->requires->js_call_amd('local_aisummary/attach', 'init', []);
}
