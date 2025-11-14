<?php
// File: my/export_user_report.php

define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../config.php');
require_login();

$userid = required_param('id', PARAM_INT);

$context = context_system::instance();
if (!has_capability('moodle/site:config', $context)) {
    throw new moodle_exception('nopermission');
}

/* ---- CSV headers ---- */
while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="user_course_report_' . $userid . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$handle = fopen('php://output', 'w');

/* header row */
fputcsv($handle, ['Course Name', 'Category', 'Status', 'Progress %', 'Points Earned', 'Max Points']);

/* ---- SQL: match user_report.php logic exactly ---- */
$sql = "
    SELECT
        c.id AS courseid,
        c.fullname AS coursename,
        cat.name   AS categoryname,

        /* Progress % (same as course_report.php / user_report.php) */
        ROUND((
            SELECT
                SUM(CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                / NULLIF(COUNT(*), 0)
            FROM {course_modules} cm
            JOIN {course_sections} cs ON cs.id = cm.section
       LEFT JOIN {course_modules_completion} cmc
              ON cmc.coursemoduleid = cm.id
             AND cmc.userid         = u.id
            WHERE cm.course     = c.id
              AND cm.visible    = 1
              AND cm.completion > 0
              AND cs.section   >= 1
        ), 0) AS progress_percent,

        /* Completion label (same conditions as user_report.php) */
        CASE
            -- Completed if Moodle course_completions has a record
            WHEN cc.timecompleted IS NOT NULL THEN 'Completed'

            -- Or if user has finished 100% of required modules
            WHEN (
                SELECT ROUND(
                    SUM(CASE WHEN cmc2.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                    / NULLIF(COUNT(*), 0), 0
                )
                  FROM {course_modules} cm2
                  JOIN {course_sections} cs2 ON cs2.id = cm2.section
             LEFT JOIN {course_modules_completion} cmc2
                    ON cmc2.coursemoduleid = cm2.id
                   AND cmc2.userid         = u.id
                 WHERE cm2.course     = c.id
                   AND cm2.visible    = 1
                   AND cm2.completion > 0
                   AND cs2.section   >= 1
            ) >= 100 THEN 'Completed'

            -- If some modules done but < 100% → In Progress
            WHEN (
                SELECT
                    SUM(CASE WHEN cmc3.completionstate = 1 THEN 1 ELSE 0 END)
                  FROM {course_modules} cm3
                  JOIN {course_sections} cs3 ON cs3.id = cm3.section
             LEFT JOIN {course_modules_completion} cmc3
                    ON cmc3.coursemoduleid = cm3.id
                   AND cmc3.userid         = u.id
                 WHERE cm3.course     = c.id
                   AND cm3.visible    = 1
                   AND cm3.completion > 0
                   AND cs3.section   >= 1
            ) > 0 THEN 'In Progress'

            -- Otherwise → Not Started
            ELSE 'Not Started'
        END AS completion_status,

        /* Points */
        ROUND(COALESCE(g.finalgrade, 0), 0) AS points_earned,
        ROUND(COALESCE(gi.grademax,   0), 0) AS max_points

    FROM {user} u
    JOIN {user_enrolments} ue ON ue.userid = u.id
    JOIN {enrol} e            ON e.id     = ue.enrolid
    JOIN {course} c           ON c.id     = e.courseid
    JOIN {course_categories} cat ON cat.id = c.category
   LEFT JOIN {course_completions} cc
          ON cc.course = c.id AND cc.userid = u.id
   LEFT JOIN {grade_items} gi
          ON gi.courseid = c.id AND gi.itemtype = 'course'
   LEFT JOIN {grade_grades} g
          ON g.itemid    = gi.id AND g.userid = u.id

   WHERE u.id = :userid
   ORDER BY c.fullname ASC
";

$params       = ['userid' => $userid];
$coursedetails = $DB->get_records_sql($sql, $params);

/* ---- write rows ---- */
foreach ($coursedetails as $row) {
    fputcsv($handle, [
        $row->coursename,
        $row->categoryname,
        $row->completion_status,
        (int)$row->progress_percent,
        (int)$row->points_earned,
        (int)$row->max_points
    ]);
}

fclose($handle);
exit;
