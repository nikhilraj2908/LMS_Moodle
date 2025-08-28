<?php
// Prevent any HTML/debug from being printed into the CSV
define('NO_DEBUG_DISPLAY', true);  // must be before config.php
require_once(__DIR__.'/../config.php');
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
while (ob_get_level() > 0) { @ob_end_clean(); }

// CSV headers
$filename = 'export_' . $mode . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

/** Include descendants of a category via cc.path */
function cat_filter_sql(string $alias, int $catid, array &$params): string {
    if ($catid <= 0) return '';
    $params['catid'] = $catid;
    $params['catpathlike'] = '%/' . $catid . '/%';
    return " AND ({$alias}.id = :catid OR {$alias}.path LIKE :catpathlike) ";
}

global $DB;


if ($mode  === 'summary') {
    // === USER SUMMARY (filtered by ?categoryid=...) ===
    fputcsv($out, [
        'User Full Name','Email','Total Courses','Completed Courses','In Progress',
        'Not Started','Total Points Earned','Maximum Points Possible'
    ]);

    $params = [];
    $catjoin  = ' JOIN {course_categories} cc ON cc.id = c.category ';
    $catwhere = cat_filter_sql('cc', $categoryid, $params); // empty if 0

    $sql = "
      SELECT 
        u.id,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        u.email,
        COUNT(DISTINCT c.id) AS total_courses,
        COUNT(DISTINCT CASE WHEN puc.progress_percent >= 100 THEN c.id END) AS completed_courses,
        COUNT(DISTINCT CASE WHEN puc.progress_percent > 0 AND puc.progress_percent < 100 THEN c.id END) AS inprogress_courses,
        COUNT(DISTINCT CASE WHEN COALESCE(puc.progress_percent,0) = 0 THEN c.id END) AS notstarted_courses,
        ROUND(SUM(COALESCE(g.finalgrade, 0)), 0) AS total_points_earned,
        ROUND(SUM(COALESCE(gi.grademax, 0)), 0) AS max_total_points
      FROM {user} u
      JOIN {user_enrolments} ue ON ue.userid = u.id
      JOIN {enrol} e ON e.id = ue.enrolid
      JOIN {course} c ON c.id = e.courseid
      $catjoin
      LEFT JOIN (
        SELECT 
          cmc.userid,
          cm.course,
          (SUM(CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END) * 100.0) / NULLIF(SUM(1),0) AS progress_percent
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.completion <> 0
        GROUP BY cmc.userid, cm.course
      ) puc ON puc.userid = u.id AND puc.course = c.id
      LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
      LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = u.id
      WHERE u.deleted = 0 AND u.suspended = 0
      $catwhere
      GROUP BY u.id, u.firstname, u.lastname, u.email
      ORDER BY u.firstname ASC
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

} elseif ($mode === 'course_summary') {
    // --- COURSE SUMMARY (filtered by ?coursecat=...) ---
    fputcsv($out, [
        'Course','Category','Enrolled','Completed','In_Progress','Not_Started','Avg Progress %','Trackable Modules'
    ]);

    $params = [];
    // filter added only in the OUTER query
    $catwhere = cat_filter_sql('cc', $coursecat, $params);

    // No category joins/filters inside CTEs
    $sql = "
      WITH trackable AS (
        SELECT c.id AS courseid, COUNT(cm.id) AS totalmodules
        FROM {course} c
        JOIN {course_modules} cm ON cm.course = c.id
        WHERE cm.completion <> 0
        GROUP BY c.id
      ),
      enrolls AS (
        SELECT DISTINCT ue.userid, e.courseid
        FROM {user_enrolments} ue
        JOIN {enrol} e ON e.id = ue.enrolid
      ),
      progress AS (
        SELECT
          en.courseid,
          en.userid,
          ( SUM(CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END) * 100.0 ) / NULLIF(tr.totalmodules,0) AS progress_percent
        FROM enrolls en
        LEFT JOIN {course_modules} cm 
               ON cm.course = en.courseid AND cm.completion <> 0
        LEFT JOIN {course_modules_completion} cmc
               ON cmc.coursemoduleid = cm.id AND cmc.userid = en.userid
        LEFT JOIN trackable tr ON tr.courseid = en.courseid
        GROUP BY en.courseid, en.userid, tr.totalmodules
      )
      SELECT 
        c.fullname AS coursename,
        cc.name    AS categoryname,
        COUNT(DISTINCT en.userid) AS enrolled,
        COUNT(DISTINCT CASE WHEN COALESCE(pr.progress_percent,0) >= 100 THEN en.userid END) AS completed,
        COUNT(DISTINCT CASE WHEN COALESCE(pr.progress_percent,0) > 0 AND COALESCE(pr.progress_percent,0) < 100 THEN en.userid END) AS inprogress,
        COUNT(DISTINCT CASE WHEN COALESCE(pr.progress_percent,0) = 0 THEN en.userid END) AS notstarted,
        ROUND(AVG(COALESCE(pr.progress_percent,0))) AS avgprogress,
        COALESCE(tr.totalmodules,0) AS totalmodules
      FROM {course} c
      JOIN {course_categories} cc ON cc.id = c.category
      LEFT JOIN enrolls  en ON en.courseid = c.id
      LEFT JOIN progress pr ON pr.courseid = c.id AND pr.userid = en.userid
      LEFT JOIN trackable tr ON tr.courseid = c.id
      WHERE 1=1
      $catwhere
      GROUP BY c.id, c.fullname, cc.name, tr.totalmodules
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
                (int)$r->totalmodules
            ]);
        }
    } catch (Throwable $e) {
        // Write a single CSV error row instead of an HTML error page
        fputcsv($out, ['ERROR', preg_replace('/\s+/', ' ', $e->getMessage())]);
        // Optional: log full details for you
        error_log('export.php course_summary failed: '.$e->getMessage());
    }
} else {
    throw new moodle_exception('Invalid export type');
}

fclose($out);
exit;
