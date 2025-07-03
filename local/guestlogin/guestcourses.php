<?php
require_once(__DIR__.'/../../config.php');

// no login forced here, because guest should see guest courses
// instead, set system context
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/guestlogin/guestcourses.php'));
$PAGE->set_title("Available Guest Courses");
$PAGE->set_heading("Available Guest Courses");

echo $OUTPUT->header();

// fetch all courses with guest access enabled + visible
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.summary
    FROM {course} c
    JOIN {enrol} e
      ON e.courseid = c.id
     AND e.enrol = 'guest'
     AND e.status = 0
    WHERE c.visible = 1
    ORDER BY c.sortorder
");


// transform to template
$templatecontext = [
    'courses' => array_values($courses),
    'wwwroot' => $CFG->wwwroot
];

echo $OUTPUT->render_from_template('local_guestlogin/guestcourses', $templatecontext);

echo $OUTPUT->footer();
