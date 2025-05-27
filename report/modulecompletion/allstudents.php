<?php
// Minimal all students completion report for modulecompletion plugin.

require_once(__DIR__ . '/../../config.php');
// require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/modulecompletion/locallib.php');
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/report/modulecompletion/locallib.php');
require_once($CFG->libdir . '/completionlib.php');  // Add this line

// ... rest of your code

use report_modulecompletion\persistents\filter;
use report_modulecompletion\output\reports;
use report_modulecompletion\event\report_viewed;

require_login();
$context = context_system::instance();
require_capability('report/modulecompletion:view', $context);

$PAGE->set_url(new moodle_url('/report/modulecompletion/allstudents.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('allstudentsreport', 'report_modulecompletion'));
$PAGE->set_heading(get_string('allstudentsreport', 'report_modulecompletion'));
$PAGE->set_pagelayout('report');

$output = $PAGE->get_renderer('report_modulecompletion');

// Step 1: Default date range (1 year ago to today)
$today = new DateTime();
$oneYearAgo = (clone $today)->modify('-1 year');
$startTimestamp = $oneYearAgo->getTimestamp();
$endTimestamp = $today->getTimestamp();

// Step 2: Create filter object with valid properties
$filterdata = (object)[
    'users' => '',
    'cohorts' => '',
    'only_cohorts_courses' => 0,
    'courses' => '',
    'starting_date' => $startTimestamp,
    'ending_date' => $endTimestamp
];
$filter = new filter(0, $filterdata);

// Step 3: Set ordering with safe defaults and validate
$order_by = optional_param('order_by', 'student', PARAM_ALPHA);
$order_dir = optional_param('order_dir', 'asc', PARAM_ALPHA);

// Allowed keys for ordering and direction
$allowed_order_bys = ['student', 'completion', 'last_completed'];
$allowed_order_dirs = ['asc', 'desc'];

// Validate keys
if (!in_array($order_by, $allowed_order_bys)) {
    $order_by = 'student';
}
if (!in_array($order_dir, $allowed_order_dirs)) {
    $order_dir = 'asc';
}

// Step 4: Handle export actions
$action = optional_param('action', '', PARAM_ALPHA);
$type = optional_param('type', 'csv', PARAM_ALPHA);

if ($action === 'export' && in_array($type, ['csv', 'xlsx'])) {
    $reports = report_modulecompletion_get_reports(
        $filter->get('users'),
        $filter->get('cohorts'),
        $filter->get('only_cohorts_courses'),
        $filter->get('courses'),
        $filter->get('starting_date'),
        $filter->get('ending_date'),
        $order_by,
        $order_dir
    );
    $reportsrenderable = new reports($filter, $reports);
    $formattedreports = $reportsrenderable->export_for_template($output)->reports;
    $exportfunction = 'report_modulecompletion_export_' . $type;
    $exportfunction($formattedreports);
    exit;
}

// Step 5: Generate report output
$reports = report_modulecompletion_get_reports(
    $filter->get('users'),
    $filter->get('cohorts'),
    $filter->get('only_cohorts_courses'),
    $filter->get('courses'),
    $filter->get('starting_date'),
    $filter->get('ending_date'),
    $order_by,
    $order_dir
);

// Step 6: Prepare language-safe keys for display
$orderbykey = 'form_order_by_student';
if (in_array($order_by, $allowed_order_bys)) {
    $orderbykey = 'form_order_by_' . $order_by;
}
$orderdirkey = 'form_order_dir_asc';
if (in_array($order_dir, $allowed_order_dirs)) {
    $orderdirkey = 'form_order_dir_' . $order_dir;
}

// Step 7: Display report info above report
echo $output->header();

echo html_writer::div(
    html_writer::tag('h2', get_string('allstudentsreport', 'report_modulecompletion')) .
    html_writer::tag('p', get_string('alluserallcourses', 'report_modulecompletion')) .
    html_writer::tag('p', get_string('form_starting_date', 'report_modulecompletion') . ': ' . date('Y-m-d', $filter->get('starting_date'))) .
    html_writer::tag('p', get_string('form_ending_date', 'report_modulecompletion') . ': ' . date('Y-m-d', $filter->get('ending_date'))) .
    html_writer::tag('p', get_string('orderedby', 'report_modulecompletion') . ': ' . get_string($orderbykey, 'report_modulecompletion')) .
    html_writer::tag('p', get_string('direction', 'report_modulecompletion') . ': ' . get_string($orderdirkey, 'report_modulecompletion')),
    'report-filters mb-4'
);

// Render the report
echo $output->render_reports($filter, $reports);

// Export buttons URLs with current order and direction
$exportcsvurl = new moodle_url('/report/modulecompletion/allstudents.php', [
    'action' => 'export',
    'type' => 'csv',
    'order_by' => $order_by,
    'order_dir' => $order_dir
]);

$exportxlsxurl = new moodle_url('/report/modulecompletion/allstudents.php', [
    'action' => 'export',
    'type' => 'xlsx',
    'order_by' => $order_by,
    'order_dir' => $order_dir
]);

echo html_writer::start_div('mb-3 mt-4');
echo html_writer::link($exportcsvurl, get_string('exportcsv', 'report_modulecompletion'), [
    'class' => 'btn btn-success mr-2'
]);
echo html_writer::link($exportxlsxurl, get_string('exportexcel', 'report_modulecompletion'), [
    'class' => 'btn btn-success'
]);
echo html_writer::end_div();

echo $output->footer();
