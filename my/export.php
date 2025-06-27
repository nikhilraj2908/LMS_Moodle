<?php
// export.php
require_once(__DIR__ . '/../config.php');
require_login();

$regionid = required_param('regionid', PARAM_INT);

// Get category and its full path including children
$category = $DB->get_record('course_categories', ['id' => $regionid]);
$path_pattern = $category->path . '/%';  // Add slash for strict child matching

$sql = "
    SELECT 
        u.id AS userid,
        CONCAT(u.firstname, ' ', u.lastname) AS username,
        MAX(cat.name) AS region,
        ROUND(MAX(gg.finalgrade), 0) AS score,
        ROUND(MAX(gg.finalgrade / NULLIF(gi.grademax, 0) * 5), 0) AS rating,
        ROUND(AVG(
            (SELECT COUNT(*)
             FROM {course_modules_completion} cmc
             JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
             WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1) 
            / 
            NULLIF(
                (SELECT COUNT(*) 
                 FROM {course_modules} cm 
                 WHERE cm.course = c.id AND cm.completion > 0),
                0
            ) * 100
        )) AS progress,
        CASE
            WHEN MAX(cc.timecompleted) IS NOT NULL THEN 'Completed'
            WHEN COUNT(gg.finalgrade) > 0 THEN 'In Progress'
            ELSE 'Not Started'
        END AS status
    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e ON e.id = ue.enrolid
    JOIN {course} c ON c.id = e.courseid
    JOIN {course_categories} cat ON c.category = cat.id
    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
    LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
    LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
    WHERE c.visible = 1
    AND (cat.path = :path OR cat.path LIKE :path_pattern)
    GROUP BY u.id, username
    ORDER BY username
";

$params = [
    'path' => $category->path,
    'path_pattern' => $path_pattern
];

$reportData = $DB->get_records_sql($sql, $params);

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=user_progress_report_' . date('Ymd') . '.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Student Name', 'Region', 'Highest Score', 'Highest Rating (out of 5)', 'Average Progress (%)', 'Status']);
foreach ($reportData as $record) {
    fputcsv($output, [
        $record->username,
        $record->region,
        $record->score,
        $record->rating,
        $record->progress,
        $record->status
    ]);
}
fclose($output);
exit;