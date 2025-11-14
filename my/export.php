<?php
// Prevent any HTML/debug from being printed into the CSV
define('NO_DEBUG_DISPLAY', true);  // must be before config.php
require_once(__DIR__ . '/../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// --- Accept underscores/dashes, normalize safely ---
$typeraw = optional_param('type', 'summary', PARAM_ALPHANUMEXT);
$type    = strtolower(trim($typeraw));

// Map to a safe internal mode; never throw
$mode = ($type === 'course_summary' || $type === 'coursesummary' || $type === 'course-summary')
    ? 'course_summary'
    : 'summary';

$categoryid = optional_param('categoryid', 0, PARAM_INT); // for user summary
$coursecat  = optional_param('coursecat', 0, PARAM_INT);  // for course summary

// Clear any buffers (and DO NOT define NO_OUTPUT_BUFFERING)
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// CSV headers
$filename = 'export_' . $mode . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

/**
 * Include descendants of a category via cc.path.
 *
 * @param string $alias   Table alias for course_categories.
 * @param int    $catid   Root category id.
 * @param array  $params  Params array to be filled.
 * @return string         SQL fragment "AND (...)" or empty string.
 */
function cat_filter_sql(string $alias, int $catid, array &$params): string {
    if ($catid <= 0) {
        return '';
    }
    $params['catid']       = $catid;
    $params['catpathlike'] = '%/' . $catid . '/%';
    return " AND ({$alias}.id = :catid OR {$alias}.path LIKE :catpathlike) ";
}

global $DB;

/* ====================================================================== */
/*  USER SUMMARY EXPORT (mode = summary)                                  */
/* ====================================================================== */
if ($mode === 'summary') {

    // Header row
    fputcsv($out, [
        'User Full Name',
        'Email',
        'Total Courses',
        'Completed Courses',
        'In Progress',
        'Not Started',
        'Total Points Earned',
        'Maximum Points Possible'
    ]);

    $params   = [];
    $catjoin  = ' JOIN {course_categories} cc ON cc.id = c.category ';
    $catwhere = cat_filter_sql('cc', $categoryid, $params); // empty if 0

    // *** IMPORTANT ***
    // This SQL is now aligned 1:1 with the UI query you pasted
    // (same cp subquery, same thresholds, same points logic).
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
                WHEN cp.progress_percent > 0
                 AND cp.progress_percent < 100
                THEN c.id
            END) AS inprogress_courses,

            COUNT(DISTINCT CASE
                WHEN cp.progress_percent IS NULL
                 OR cp.progress_percent = 0
                THEN c.id
            END) AS notstarted_courses,

            -- total points (only where the user actually has a grade)
            ROUND(
                SUM(
                    CASE
                        WHEN g.finalgrade IS NOT NULL
                            THEN COALESCE(g.finalgrade, 0)
                        ELSE 0
                    END
                ), 0
            ) AS total_points_earned,

            -- max possible points for those same courses
            ROUND(
                SUM(
                    CASE
                        WHEN g.finalgrade IS NOT NULL
                            THEN COALESCE(gi.grademax, 0)
                        ELSE 0
                    END
                ), 0
            ) AS max_total_points

        FROM {user} u
        LEFT JOIN {user_enrolments} ue ON u.id = ue.userid
        LEFT JOIN {enrol} e            ON ue.enrolid = e.id
        LEFT JOIN {course} c           ON c.id = e.courseid
        $catjoin

        /* per-course progress % per user, using ALL trackable modules in the course */
        LEFT JOIN (
            SELECT
                cmc.userid,
                cm.course,
                ROUND(
                    SUM(
                        CASE
                            WHEN cmc.completionstate = 1 THEN 1
                            ELSE 0
                        END
                    ) * 100.0
                    /
                    NULLIF((
                        /* total trackable, visible modules in this course (sections >= 1) */
                        SELECT COUNT(*)
                        FROM {course_modules} cm2
                        JOIN {course_sections} cs2 ON cs2.id = cm2.section
                        WHERE cm2.course     = cm.course
                          AND cm2.completion > 0
                          AND cm2.visible    = 1
                          AND cs2.section   >= 1
                    ), 0)
                , 0) AS progress_percent
            FROM {course_modules} cm
            JOIN {course_sections} cs ON cs.id = cm.section
            LEFT JOIN {course_modules_completion} cmc
                   ON cmc.coursemoduleid = cm.id
            WHERE cm.completion > 0        -- only trackable activities
              AND cm.visible    = 1        -- only visible
              AND cs.section    >= 1       -- ignore section 0
            GROUP BY cm.course, cmc.userid
        ) cp ON cp.userid = u.id AND cp.course = c.id

        LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
        LEFT JOIN {grade_grades} g ON g.itemid   = gi.id AND g.userid = u.id

        WHERE u.deleted = 0 AND u.suspended = 0
        $catwhere

        GROUP BY u.id, u.firstname, u.lastname, u.email
        ORDER BY u.firstname ASC, u.lastname ASC
    ";

    $rows = $DB->get_records_sql($sql, $params);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r->fullname,
            $r->email,
            (int)$r->total_courses,
            (int)$r->completed_courses,
            (int)$r->inprogress_courses,
            (int)$r->notstarted_courses,
            (int)round($r->total_points_earned),
            (int)round($r->max_total_points),
        ]);
    }

/* ====================================================================== */
/*  COURSE SUMMARY EXPORT (mode = course_summary)                         */
/* ====================================================================== */
} elseif ($mode === 'course_summary') {

    fputcsv($out, [
        'Course',
        'Category',
        'Enrolled',
        'Completed',
        'In_Progress',
        'Not_Started',
        'Avg Progress %',
        'Trackable Modules'
    ]);

    $params   = [];
    $catwhere = cat_filter_sql('cc', $coursecat, $params);

    // This matches your Course Report Summary logic.
    $sql = "
        SELECT
            c.id,
            c.fullname AS coursename,
            cc.name    AS categoryname,

            COUNT(DISTINCT ue.userid) AS enrolled,

            COUNT(DISTINCT CASE
                WHEN COALESCE(up.progress_pct, 0) >= 99.9
                THEN ue.userid
            END) AS completed,

            COUNT(DISTINCT CASE
                WHEN COALESCE(up.progress_pct, 0) > 0
                 AND COALESCE(up.progress_pct, 0) < 99.9
                THEN ue.userid
            END) AS inprogress,

            COUNT(DISTINCT CASE
                WHEN COALESCE(up.progress_pct, 0) <= 0.0
                THEN ue.userid
            END) AS notstarted,

            ROUND(AVG(COALESCE(up.progress_pct, 0))) AS avgprogress,

            (
                SELECT COUNT(1)
                  FROM {course_modules} cm
                  JOIN {course_sections} cs ON cs.id = cm.section
                 WHERE cm.course = c.id
                   AND cm.visible = 1
                   AND cm.completion > 0
                   AND cs.section >= 1
            ) AS totalmodules

        FROM {course} c
        JOIN {course_categories} cc ON cc.id = c.category

        LEFT JOIN {enrol} e
               ON e.courseid = c.id AND e.status = 0
        LEFT JOIN {user_enrolments} ue
               ON ue.enrolid = e.id AND ue.status = 0

        /* Per-user progress % (only trackable & visible modules in real sections) */
        LEFT JOIN (
            SELECT
                cm.course AS courseid,
                cmc.userid,
                ROUND(
                    SUM(
                        CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END
                    ) * 100.0 / NULLIF(COUNT(*), 0)
                , 0) AS progress_pct
            FROM {course_modules} cm
            JOIN {course_sections} cs ON cs.id = cm.section
            LEFT JOIN {course_modules_completion} cmc
                   ON cmc.coursemoduleid = cm.id
            WHERE cm.completion > 0
              AND cm.visible = 1
              AND cs.section >= 1
            GROUP BY cm.course, cmc.userid
        ) up
          ON up.courseid = c.id
         AND up.userid   = ue.userid

        WHERE c.visible = 1
          AND c.id <> 1
          $catwhere

        GROUP BY c.id, c.fullname, cc.name
        ORDER BY c.fullname ASC
    ";

    try {
        $rows = $DB->get_records_sql($sql, $params);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r->coursename,
                $r->categoryname,
                (int)$r->enrolled,
                (int)$r->completed,
                (int)$r->inprogress,
                (int)$r->notstarted,
                (int)$r->avgprogress,
                (int)$r->totalmodules,
            ]);
        }
    } catch (Throwable $e) {
        fputcsv($out, ['ERROR', preg_replace('/\s+/', ' ', $e->getMessage())]);
        error_log('export.php course_summary failed: ' . $e->getMessage());
    }

} else {
    throw new moodle_exception('Invalid export type');
}

fclose($out);
exit;
