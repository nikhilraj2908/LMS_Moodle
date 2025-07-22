<?php
require_once(__DIR__.'/../config.php');
require_login();

// Get filter param
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$type = optional_param('type', '', PARAM_ALPHA); // for future types: 'summary', 'region', etc.

if ($type !== 'summary') {
    throw new moodle_exception('Invalid export type');
}

// Set headers for CSV file
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="user_summary_report.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV
$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, [
    'User Full Name',
    'Email',
    'Total Courses',
    'Completed Courses',
    'In Progress',
    'Not Started',
    'Total Points Earned',
    'Maximum Points Possible'
]);

$params = [];
$categoryWhere = '';

if ($categoryid > 0) {
    $categoryWhere = 'AND c.category = :categoryid';
    $params['categoryid'] = $categoryid;
}

$sql = "
    SELECT 
        u.id,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        u.email,
        COUNT(DISTINCT c.id) AS total_courses,
        COUNT(DISTINCT CASE 
            WHEN cp.progress_percent = 100 THEN c.id 
        END) AS completed_courses,
        COUNT(DISTINCT CASE 
            WHEN cp.progress_percent > 0 AND cp.progress_percent < 100 THEN c.id 
        END) AS inprogress_courses,
        COUNT(DISTINCT CASE 
            WHEN cp.progress_percent IS NULL OR cp.progress_percent = 0 THEN c.id 
        END) AS notstarted_courses,
        ROUND(SUM(COALESCE(g.finalgrade, 0)), 0) AS total_points_earned,
        ROUND(SUM(COALESCE(gi.grademax, 0)), 0) AS max_total_points
    FROM {user} u
    LEFT JOIN {user_enrolments} ue ON u.id = ue.userid
    LEFT JOIN {enrol} e ON ue.enrolid = e.id
    LEFT JOIN {course} c ON c.id = e.courseid
    LEFT JOIN (
        SELECT 
            cmc.userid, 
            cm.course,
            (COUNT(CASE WHEN cmc.completionstate = 1 THEN 1 END) * 100.0 / COUNT(*)) AS progress_percent
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        GROUP BY cmc.userid, cm.course
    ) cp ON cp.userid = u.id AND cp.course = c.id
    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = u.id
    WHERE u.deleted = 0 AND u.suspended = 0
    $categoryWhere
    GROUP BY u.id, u.firstname, u.lastname, u.email
    ORDER BY u.firstname ASC
    LIMIT 500
";

$records = $DB->get_records_sql($sql, $params);

// Output rows
foreach ($records as $row) {
    fputcsv($output, [
        $row->fullname,
        $row->email,
        $row->total_courses,
        $row->completed_courses,
        $row->inprogress_courses,
        $row->notstarted_courses,
        $row->total_points_earned,
        $row->max_total_points
    ]);
}

fclose($output);
exit;
