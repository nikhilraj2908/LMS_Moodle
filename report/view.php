<?php
require_once(__DIR__ . '/../config.php');

// Course id.
$courseid = required_param('courseid', PARAM_INT);

$PAGE->set_url(new moodle_url('/report/view.php', array('courseid' => $courseid)));

// Basic access checks.
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new \moodle_exception('invalidcourseid');
}
require_login($course);

// Map numeric keys to report names
$allowed_reports = [
      6 => 'Activity completion',
    2 => 'Logs',
    3 => 'Live Log',
    4 => 'Activity report',
    5 => 'Course participation',
    11 => 'Hits distribution',
    1=>'Course completion'
   
];

$PAGE->set_title(get_string('reports'));
$PAGE->set_pagelayout('incourse');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reports'));

// Check and display only the allowed reports
$hasreports = false;
if ($reportnode = $PAGE->settingsnav->find('coursereports', \navigation_node::TYPE_CONTAINER)) {
    $filtered_reports = [];
   

    foreach ($reportnode->children as $child) {
        $report_key = (int) $child->key; // Convert to integer for matching

        // Check if the report key exists in the allowed_reports array
        if (array_key_exists($report_key, $allowed_reports)) {
            $child->text = $allowed_reports[$report_key]; // Set custom display name
            $filtered_reports[] = $child;
            $hasreports = true;
        }
    }

    if ($hasreports) {
        $reportnode->children = $filtered_reports; // Replace with filtered list
        echo $OUTPUT->render_from_template('core/report_link_page', ['node' => $reportnode]);
    } else {
        echo html_writer::div($OUTPUT->notification('No accessible reports match the selected criteria.', 'warning'), 'mt-3');
    }
} else {
    echo html_writer::div($OUTPUT->notification('No report node found. Ensure reports are enabled.', 'error'), 'mt-3');
}

echo $OUTPUT->footer();
