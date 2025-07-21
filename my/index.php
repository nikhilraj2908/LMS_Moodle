<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * My Moodle — “My home” dashboard logic (with admin‐dashboard corrections)
 *
 * @package    moodlecore
 * @subpackage my
 */

require_once(__DIR__ . '/../config.php');
if (isguestuser()) {
    // Redirect guests to a safe landing page, like the guestcourses page
    redirect(new moodle_url('/local/guestlogin/guestcourses.php'));
}
require_once($CFG->dirroot . '/my/lib.php');

redirect_if_major_upgrade_required();

$edit  = optional_param('edit',  null, PARAM_BOOL);
$reset = optional_param('reset', null, PARAM_BOOL);

require_login();

// bring in globals
global $DB, $USER, $OUTPUT, $CFG;
// In your index.php
// Add this in your index.php file// == Only allow site admins ==
// if (!is_siteadmin()) {
//     http_response_code(403);
//     header('Content-Type: application/json; charset=utf-8');
//     echo json_encode(['error' => 'Access denied']);
//     exit;
// }

// == AJAX log endpoint ==// more robust AJAX detection<?php
// … your existing require_once() and redirect_if_major_upgrade_required() …

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
redirect_if_major_upgrade_required();
require_login();

global $DB, $USER, $OUTPUT, $CFG;

// Detect XMLHttpRequest
// ================================
$ajaxLogs = optional_param('ajaxlogs', 0, PARAM_BOOL);
if ($ajaxLogs) {
    // Only allow site admins
    if (!is_siteadmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Pull the range in minutes (default to 60 if missing)
    $range   = optional_param('range', 60, PARAM_INT);
    $endtime = time();
    $start   = $endtime - ($range * 60);

    try {
        $records = $DB->get_records_sql("
            SELECT
                l.timecreated AS timestmp,
                l.eventname   AS eventname,
                COALESCE(l.other, '') AS details,
                l.ip          AS ip,
                u.firstname, u.lastname,
                u.firstnamephonetic, u.lastnamephonetic,
                u.middlename, u.alternatename
              FROM {logstore_standard_log} l
              JOIN {user} u ON u.id = l.userid
             WHERE l.timecreated BETWEEN :start AND :end
             ORDER BY l.timecreated DESC
        ", ['start' => $start, 'end' => $endtime]);

        $logData = [];
        foreach ($records as $r) {
            $details     = json_decode($r->details, true);
            $description = "The user viewed the log report.";
            if (!empty($details['modaction']) && $details['modaction'] !== '-') {
                $description = "The user performed '{$details['modaction']}' on the log report.";
            }
            $logData[] = [
                'time'        => userdate($r->timestmp, get_string('strftimedatetime', 'langconfig')),
                'user'        => fullname((object)[
                    'firstname' => $r->firstname,
                    'lastname'  => $r->lastname,
                    'firstnamephonetic' => $r->firstnamephonetic,
                    'lastnamephonetic'  => $r->lastnamephonetic,
                    'middlename'        => $r->middlename,
                    'alternatename'     => $r->alternatename
                ]),
                'event'       => $r->eventname,
                'description' => $description,
                'ip'          => $r->ip
            ];
        }

        @ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($logData);
    } catch (Exception $e) {
        @ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Failed to fetch logs: ' . $e->getMessage()]);
    }
    exit;
}

// ================================
// 2. AJAX SEARCH ENDPOINT
// ================================
$searchquery = optional_param('search', '', PARAM_TEXT);
$isxhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
         strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($searchquery && $isxhr) {
    try {
        $searchsql = "
            SELECT 
                c.id           AS course_id,
                c.fullname     AS course_name,
                c.shortname    AS course_shortname
              FROM {course} c
              JOIN {enrol} e               ON e.courseid = c.id
              JOIN {user_enrolments} ue    ON ue.enrolid = e.id
             WHERE (
                    " . $DB->sql_like('LOWER(c.fullname)', ':query1', false) . "
                 OR " . $DB->sql_like('LOWER(c.shortname)', ':query2', false) . "
                   )
               AND c.visible = 1
               AND ue.userid = :userid
             ORDER BY c.fullname ASC
             LIMIT 10
        ";
        $searchterm = strtolower($searchquery);
        $params = [
            'query1' => '%' . $DB->sql_like_escape($searchterm) . '%',
            'query2' => '%' . $DB->sql_like_escape($searchterm) . '%',
            'userid' => $USER->id
        ];
        $searchresults = $DB->get_records_sql($searchsql, $params);

        $formatted = [];
        foreach ($searchresults as $course) {
            $formatted[] = [
                'courseid'        => $course->course_id,
                'coursename'      => $course->course_name,
                'courseshortname' => $course->course_shortname,
                'courseurl'       => (new moodle_url('/course/view.php', ['id' => $course->course_id]))->out()
            ];
        }

        @ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'       => true,
            'searchResults' => $formatted,
            'searchQuery'   => $searchquery
        ]);
        exit;

    } catch (Exception $e) {
        @ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'An error occurred while searching. Please try again.'
        ]);
        exit;
    }
}


// =================================
// 2. UPGRADE / GUEST REDIRECTS
// =================================
$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$strmymoodle = get_string('myhome');

if (empty($CFG->enabledashboard)) {
    $defaultpage = get_default_home_page();
    if ($defaultpage == HOMEPAGE_MYCOURSES) {
        redirect(new moodle_url('/my/courses.php'));
    } else {
        throw new moodle_exception('error:dashboardisdisabled','my');
    }
}

if (isguestuser()) {
    if (empty($CFG->allowguestmymoodle)) {
        redirect(new moodle_url('/', ['redirect'=>0]));
    }
    $userid        = null;
    $USER->editing = $edit = 0;
    $context       = context_system::instance();
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');
    $pagetitle     = "$strmymoodle (" . get_string('guest') . ")";
} else {
    $userid        = $USER->id;
    $context       = context_user::instance($USER->id);
    $PAGE->set_blocks_editing_capability('moodle/my:manageblocks');
    $pagetitle     = $strmymoodle;
}

if (!$currentpage = my_get_page($userid, MY_PAGE_PRIVATE)) {
    throw new \moodle_exception('mymoodlesetup');
}

$PAGE->set_context($context);
$PAGE->set_url('/my/index.php');
$PAGE->set_pagelayout('mydashboard');
$PAGE->add_body_class('limitedwidth');
$PAGE->set_pagetype('my-index');
$PAGE->blocks->add_region('content');
$PAGE->set_subpage($currentpage->id);
$PAGE->set_heading('');
$PAGE->set_title('');

if (!isguestuser() && get_home_page() != HOMEPAGE_MY) {
    if (optional_param('setdefaulthome', false, PARAM_BOOL)) {
        set_user_preference('user_home_page_preference', HOMEPAGE_MY);
    } else if (!empty($CFG->defaulthomepage) && $CFG->defaulthomepage==HOMEPAGE_USER) {
        $frontpagenode = $PAGE->settingsnav->add(
            get_string('frontpagesettings'),
            null,
            navigation_node::TYPE_SETTING,
            null
        );
        $frontpagenode->force_open();
        $frontpagenode->add(
            get_string('makethismyhome'),
            new moodle_url('/my/', ['setdefaulthome'=>true]),
            navigation_node::TYPE_SETTING
        );
    }
}

if (empty($CFG->forcedefaultmymoodle) && $PAGE->user_allowed_editing()) {
    if ($edit!==null) {
        $USER->editing = $edit;
    } else {
        if ($currentpage->userid) {
            $edit = !empty($USER->editing)?1:0;
        } else {
            if (!$currentpage = my_copy_page($USER->id,MY_PAGE_PRIVATE)) {
                throw new \moodle_exception('mymoodlesetup');
            }
            $context       = context_user::instance($USER->id);
            $PAGE->set_context($context);
            $PAGE->set_subpage($currentpage->id);
            $USER->editing = $edit = 0;
        }
    }

    $params      = ['edit'=>!$edit];
    $resetbutton = '';
    $editstring  = !$currentpage->userid||empty($edit)
        ? get_string('updatemymoodleon')
        : get_string('updatemymoodleoff');
    $button = !$PAGE->theme->haseditswitch
        ? $OUTPUT->single_button(new moodle_url("$CFG->wwwroot/my/index.php",$params),$editstring)
        : '';
    $PAGE->set_button($resetbutton.$button);
} else {
    $USER->editing = $edit = 0;
}

// =================================
// 3. PREPARE DASHBOARD DATA
// =================================
$completeuser = $DB->get_record('user', [
    'id' => $USER->id
], 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');

$templatecontext = [
    'username'               => fullname($completeuser),
    'completedCourses'       => 0,
    'totalCourses'           => 0,
    'totalOverdue'           => 0,
    'totalPoints'            => 0,
    'totalPossiblePoints'    => 0,
    'learningPathPercentage' => 0,
    'overduePercentage'      => 0,
    'hoursActivity'          => json_encode([]),
    'percentageChange'       => 0,
    'isIncrease'             => true,
    'currentWeekTotal'       => 0,
    'previousWeekTotal'      => 0,
    'courses'                => [],
    'recentCourse'           => null,
    'enrolledCourses'        => [],
    // template will be set below
];

if (isloggedin() && !isguestuser()) {
    // a) basic user info
    $displayname = fullname($completeuser);
    $userpicture = $OUTPUT->user_picture($USER, ['size'=>70]);
    $templatecontext['userpicture'] = $userpicture;

    // b) last 3 enrolled courses
    // ... your original SQL + loop ...
 $enrolledCoursesSql = "
        SELECT 
          c.id                    AS course_id,
          c.fullname              AS course_name,
          c.shortname             AS course_shortname,
          c.summary               AS course_summary,
          ue.timecreated          AS enrolled_time,
          cc.name                 AS category_name,
          f.filename              AS course_image_filename,
          CONCAT(
            '/pluginfile.php/',
            ctx.id,
            '/course/overviewfiles/',
            f.filename
          ) AS course_image_url,
          (
            SELECT COUNT(*)
              FROM mdl_course_modules cm
              JOIN mdl_course_sections cs ON cm.section = cs.id
             WHERE cm.course   = c.id
               AND cm.visible  = 1
               AND cs.section >= 1
          ) AS total_modules,
          CASE
            WHEN ccomp.timecompleted IS NOT NULL 
             AND ccomp.timecompleted > 0
            THEN 1
            ELSE 0
          END AS course_completion_status,
          ROUND(
            (
              SELECT COUNT(*)
                FROM mdl_course_modules_completion cmc
                JOIN mdl_course_modules cm ON cmc.coursemoduleid = cm.id
                JOIN mdl_course_sections cs ON cm.section = cs.id
               WHERE cm.course            = c.id
                 AND cmc.userid           = ue.userid
                 AND cmc.completionstate  = 1
                 AND cm.visible           = 1
                 AND cs.section          >= 1
            ) * 100.0
            / NULLIF(
                (
                  SELECT COUNT(*)
                    FROM mdl_course_modules cm
                    JOIN mdl_course_sections cs ON cm.section = cs.id
                   WHERE cm.course   = c.id
                     AND cm.visible  = 1
                     AND cs.section >= 1
                ),
                0
            ),
            2
          ) AS progress_percentage
        FROM mdl_course c
        JOIN mdl_enrol e               ON e.courseid = c.id
        JOIN mdl_user_enrolments ue    ON ue.enrolid = e.id
        LEFT JOIN mdl_course_categories cc ON c.category = cc.id
        LEFT JOIN mdl_context ctx      ON c.id = ctx.instanceid 
                                      AND ctx.contextlevel = 50
        LEFT JOIN mdl_files f          ON ctx.id = f.contextid
                                      AND f.component = 'course'
                                      AND f.filearea  = 'overviewfiles'
                                      AND f.filename <> '.'
        LEFT JOIN mdl_course_completions ccomp 
                                      ON ccomp.course = c.id 
                                     AND ccomp.userid = ue.userid
       WHERE ue.userid = ?
         AND c.visible = 1
       ORDER BY ue.timecreated DESC
       LIMIT 3
    ";

    $default_image_url = $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg';

    try {
        $enrolledCourses = $DB->get_records_sql($enrolledCoursesSql, [$USER->id]);
    } catch (dml_exception $e) {
        error_log("Error fetching enrolled courses: " . $e->getMessage());
        $enrolledCourses = [];
    }

    $enrolledCoursesData = [];
    foreach ($enrolledCourses as $course) {
        $cleanedSummary   = format_string($course->course_summary, true);
        $course_image_url = (!empty($course->course_image_filename))
            ? $CFG->wwwroot . $course->course_image_url
            : $default_image_url;
        $percent       = (float)($course->progress_percentage ?? 0);
        $rawCompletion = (bool)$course->course_completion_status;
        $reallyCompleted = ($rawCompletion || $percent >= 100.0);

        $enrolledCoursesData[] = [
            'courseid'                  => $course->course_id,
            'coursename'                => $course->course_name,
            'coursesummary'             => $cleanedSummary,
            'courseshortname'           => $course->course_shortname,
            'last_accessed_time'        => date('Y-m-d', $course->enrolled_time),
            'course_image_url'          => $course_image_url,
            'courseurl'                 => new moodle_url('/course/view.php', ['id' => $course->course_id]),
            'category'                  => $course->category_name,
            'progressPercentage'        => $percent,
            'total_modules'             => $course->total_modules ?? 0,
            'completion_status'         => $reallyCompleted ? 'Completed' : 'In Progress',
            'course_completion_status'  => $reallyCompleted ? 1 : 0,
            'is_completed'              => $reallyCompleted,
        ];
    }
    $templatecontext['enrolledCourses'] = $enrolledCoursesData;
    // c) AJAX search results
    $templatecontext['searchQuery']   = $searchquery;
    $templatecontext['searchResults'] = $formatted ?? [];



    $recentCourseSql = "
        SELECT
          c.id                              AS course_id,
          c.fullname                        AS course_name,
          c.shortname                       AS course_shortname,
          c.summary                         AS course_summary,
          FROM_UNIXTIME(l.timecreated)      AS last_accessed_time,
          f.filename                        AS course_image_filename,
          CONCAT(
            '/pluginfile.php/',
            ctx.id,
            '/course/overviewfiles/',
            f.filename
          ) AS course_image_url,
          cc.name                           AS category_name,
          (
            SELECT COUNT(*)
              FROM mdl_course_modules cm
              JOIN mdl_course_sections cs ON cm.section = cs.id
             WHERE cm.course   = c.id
               AND cm.visible  = 1
               AND cs.section >= 1
          ) AS total_modules,
          CASE
            WHEN ccomp.timecompleted IS NOT NULL
             AND ccomp.timecompleted > 0
            THEN 1
            ELSE 0
          END AS course_completion_status,
          ROUND(
            (
              SELECT COUNT(*)
                FROM mdl_course_modules_completion cmc
                JOIN mdl_course_modules cm ON cmc.coursemoduleid = cm.id
                JOIN mdl_course_sections cs ON cm.section = cs.id
               WHERE cm.course            = c.id
                 AND cmc.userid           = l.userid
                 AND cmc.completionstate  = 1
                 AND cm.visible           = 1
                 AND cs.section          >= 1
            ) * 100.0
            / NULLIF(
                (
                  SELECT COUNT(*)
                    FROM mdl_course_modules cm
                    JOIN mdl_course_sections cs ON cm.section = cs.id
                   WHERE cm.course   = c.id
                     AND cm.visible  = 1
                     AND cs.section >= 1
                ),
                0
            ),
            2
          ) AS progress_percentage
        FROM mdl_logstore_standard_log l
        JOIN mdl_course c ON l.courseid = c.id
        LEFT JOIN mdl_context ctx ON c.id = ctx.instanceid
                               AND ctx.contextlevel = 50
        LEFT JOIN mdl_files f ON ctx.id = f.contextid
                             AND f.component = 'course'
                             AND f.filearea  = 'overviewfiles'
                             AND f.filename <> '.'
        LEFT JOIN mdl_course_categories cc ON c.category = cc.id
        LEFT JOIN mdl_course_completions ccomp 
                             ON ccomp.course = c.id
                            AND ccomp.userid = l.userid
       WHERE l.userid       = ?
         AND l.courseid IS NOT NULL
         AND c.visible       = 1
       ORDER BY l.timecreated DESC
       LIMIT 1
    ";

    try {
        $recentCourse = $DB->get_record_sql($recentCourseSql, [$USER->id]);
    } catch (dml_exception $e) {
        error_log("Error fetching recent course: " . $e->getMessage());
        $recentCourse = null;
    }

    if ($recentCourse) {
        $cleanedSummary   = format_string($recentCourse->course_summary, true);
        $course_image_url = (!empty($recentCourse->course_image_filename))
            ? $CFG->wwwroot . $recentCourse->course_image_url
            : $default_image_url;
        $percent         = (float)($recentCourse->progress_percentage ?? 0);
        $rawCompletion   = (bool)$recentCourse->course_completion_status;
        $reallyCompleted = ($rawCompletion || $percent >= 100.0);

        $templatecontext['recentCourse'] = [
            'courseid'                 => $recentCourse->course_id,
            'coursename'               => $recentCourse->course_name,
            'coursesummary'            => $cleanedSummary,
            'courseshortname'          => $recentCourse->course_shortname,
            'last_accessed_time'       => $recentCourse->last_accessed_time,
            'course_image_url'         => $course_image_url,
            'courseurl'                => new moodle_url('/course/view.php', ['id' => $recentCourse->course_id]),
            'category'                 => $recentCourse->category_name,
            'progressPercentage'       => $percent,
            'total_modules'            => $recentCourse->total_modules,
            'completion_status'        => $reallyCompleted ? 'Completed' : 'In Progress',
            'course_completion_status' => $reallyCompleted ? 1 : 0,
            'is_completed'             => $reallyCompleted,
        ];
    } else {
        $templatecontext['recentCourse'] = null;
    }

    $recentCourseSql = "
        SELECT
          c.id                              AS course_id,
          c.fullname                        AS course_name,
          c.shortname                       AS course_shortname,
          c.summary                         AS course_summary,
          FROM_UNIXTIME(l.timecreated)      AS last_accessed_time,
          f.filename                        AS course_image_filename,
          CONCAT(
            '/pluginfile.php/',
            ctx.id,
            '/course/overviewfiles/',
            f.filename
          ) AS course_image_url,
          cc.name                           AS category_name,
          (
            SELECT COUNT(*)
              FROM mdl_course_modules cm
              JOIN mdl_course_sections cs ON cm.section = cs.id
             WHERE cm.course   = c.id
               AND cm.visible  = 1
               AND cs.section >= 1
          ) AS total_modules,
          CASE
            WHEN ccomp.timecompleted IS NOT NULL
             AND ccomp.timecompleted > 0
            THEN 1
            ELSE 0
          END AS course_completion_status,
          ROUND(
            (
              SELECT COUNT(*)
                FROM mdl_course_modules_completion cmc
                JOIN mdl_course_modules cm ON cmc.coursemoduleid = cm.id
                JOIN mdl_course_sections cs ON cm.section = cs.id
               WHERE cm.course            = c.id
                 AND cmc.userid           = l.userid
                 AND cmc.completionstate  = 1
                 AND cm.visible           = 1
                 AND cs.section          >= 1
            ) * 100.0
            / NULLIF(
                (
                  SELECT COUNT(*)
                    FROM mdl_course_modules cm
                    JOIN mdl_course_sections cs ON cm.section = cs.id
                   WHERE cm.course   = c.id
                     AND cm.visible  = 1
                     AND cs.section >= 1
                ),
                0
            ),
            2
          ) AS progress_percentage
        FROM mdl_logstore_standard_log l
        JOIN mdl_course c ON l.courseid = c.id
        LEFT JOIN mdl_context ctx ON c.id = ctx.instanceid
                               AND ctx.contextlevel = 50
        LEFT JOIN mdl_files f ON ctx.id = f.contextid
                             AND f.component = 'course'
                             AND f.filearea  = 'overviewfiles'
                             AND f.filename <> '.'
        LEFT JOIN mdl_course_categories cc ON c.category = cc.id
        LEFT JOIN mdl_course_completions ccomp 
                             ON ccomp.course = c.id
                            AND ccomp.userid = l.userid
       WHERE l.userid       = ?
         AND l.courseid IS NOT NULL
         AND c.visible       = 1
       ORDER BY l.timecreated DESC
       LIMIT 1
    ";

    try {
        $recentCourse = $DB->get_record_sql($recentCourseSql, [$USER->id]);
    } catch (dml_exception $e) {
        error_log("Error fetching recent course: " . $e->getMessage());
        $recentCourse = null;
    }

    if ($recentCourse) {
        $cleanedSummary   = format_string($recentCourse->course_summary, true);
        $course_image_url = (!empty($recentCourse->course_image_filename))
            ? $CFG->wwwroot . $recentCourse->course_image_url
            : $default_image_url;
        $percent         = (float)($recentCourse->progress_percentage ?? 0);
        $rawCompletion   = (bool)$recentCourse->course_completion_status;
        $reallyCompleted = ($rawCompletion || $percent >= 100.0);

        $templatecontext['recentCourse'] = [
            'courseid'                 => $recentCourse->course_id,
            'coursename'               => $recentCourse->course_name,
            'coursesummary'            => $cleanedSummary,
            'courseshortname'          => $recentCourse->course_shortname,
            'last_accessed_time'       => $recentCourse->last_accessed_time,
            'course_image_url'         => $course_image_url,
            'courseurl'                => new moodle_url('/course/view.php', ['id' => $recentCourse->course_id]),
            'category'                 => $recentCourse->category_name,
            'progressPercentage'       => $percent,
            'total_modules'            => $recentCourse->total_modules,
            'completion_status'        => $reallyCompleted ? 'Completed' : 'In Progress',
            'course_completion_status' => $reallyCompleted ? 1 : 0,
            'is_completed'             => $reallyCompleted,
        ];
    } else {
        $templatecontext['recentCourse'] = null;
    }

    // Reset session‐tracking variables before running hour‐queries


    // Reset session‐tracking variables before running hour‐queries
    $DB->execute("SET @prev_time = NULL, @prev_user = NULL, @session_id = 0");

    // d) most recent course accessed
    // ... your original SQL + assignment ...
  $currentWeekSql = "
        WITH RECURSIVE week_days AS (
            SELECT DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 2) DAY) AS week_day
            UNION ALL
            SELECT DATE_ADD(week_day, INTERVAL 1 DAY)
            FROM week_days
            WHERE week_day < DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 8) DAY)
        ),
        sessions AS (
            SELECT 
                log.userid,
                log.timecreated,
                @session_id := IF(
                    @prev_user = log.userid
                    AND (log.timecreated > @prev_time)
                    AND (log.timecreated - @prev_time) <= 1800,
                    @session_id,
                    @session_id + 1
                ) AS session_id,
                IF(
                    @prev_user = log.userid
                    AND (log.timecreated > @prev_time)
                    AND (log.timecreated - @prev_time) <= 1800,
                    log.timecreated - @prev_time,
                    0
                ) AS time_spent,
                @prev_time := log.timecreated,
                @prev_user := log.userid
              FROM mdl_logstore_standard_log AS log,
                   (SELECT @prev_time := NULL, @prev_user := NULL, @session_id := 0) AS vars
             WHERE log.userid = ?
               AND YEARWEEK(FROM_UNIXTIME(log.timecreated), 1) = YEARWEEK(CURDATE(), 1)
             ORDER BY log.userid, log.timecreated ASC
        ),
        daily_hours AS (
            SELECT 
                DAYNAME(week_days.week_day) AS day_name,
                ROUND(COALESCE(SUM(activity.time_spent) / 3600, 0), 2) AS hours_spent
              FROM week_days
              LEFT JOIN (
                  SELECT DATE(FROM_UNIXTIME(timecreated)) AS activity_date, time_spent
                    FROM sessions
              ) AS activity
                ON activity.activity_date = week_days.week_day
             GROUP BY week_days.week_day
        )
        SELECT 
            day_name, 
            hours_spent, 
            (SELECT ROUND(SUM(hours_spent), 2) FROM daily_hours) AS total_week_hours
          FROM daily_hours
         ORDER BY FIELD(
            day_name,
            'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'
         )
    ";
    try {
        $currentWeekData = $DB->get_records_sql($currentWeekSql, [$USER->id]);
    } catch (dml_exception $e) {
        error_log("Error executing current week query: " . $e->getMessage());
        $currentWeekData = [];
    }

    $hoursActivity   = [];
    $currentWeekTotal = 0;
    foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $day) {
        if (isset($currentWeekData[$day])) {
            $hoursActivity[]   = (float)$currentWeekData[$day]->hours_spent;
            $currentWeekTotal  = (float)$currentWeekData[$day]->total_week_hours;
        } else {
            $hoursActivity[] = 0;
        }
    }

   $DB->execute("SET @prev_time = NULL, @prev_user = NULL, @session_id = 0");

    // e) current week activity
    // ... your original SQL + loop ...


    $previousWeekSql = "
        WITH RECURSIVE week_days AS (
            SELECT DATE_SUB(
                       DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 2) DAY),
                       INTERVAL 1 WEEK
                   ) AS week_day
            UNION ALL
            SELECT DATE_ADD(week_day, INTERVAL 1 DAY)
            FROM week_days
            WHERE week_day < DATE_SUB(
                                 DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 8) DAY),
                                 INTERVAL 1 WEEK
                             )
        ),
        sessions AS (
            SELECT 
                log.userid,
                log.timecreated,
                @session_id := IF(
                    @prev_user = log.userid
                    AND (log.timecreated > @prev_time)
                    AND (log.timecreated - @prev_time) <= 1800,
                    @session_id,
                    @session_id + 1
                ) AS session_id,
                IF(
                    @prev_user = log.userid
                    AND (log.timecreated > @prev_time)
                    AND (log.timecreated - @prev_time) <= 1800,
                    log.timecreated - @prev_time,
                    0
                ) AS time_spent,
                @prev_time := log.timecreated,
                @prev_user := log.userid
              FROM mdl_logstore_standard_log AS log,
                   (SELECT @prev_time := NULL, @prev_user := NULL, @session_id := 0) AS vars
             WHERE log.userid = ?
               AND YEARWEEK(FROM_UNIXTIME(log.timecreated), 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
             ORDER BY log.userid, log.timecreated ASC
        ),
        daily_hours AS (
            SELECT 
                DAYNAME(week_days.week_day) AS day_name,
                ROUND(COALESCE(SUM(activity.time_spent) / 3600, 0), 2) AS hours_spent
              FROM week_days
              LEFT JOIN (
                  SELECT DATE(FROM_UNIXTIME(timecreated)) AS activity_date, time_spent
                    FROM sessions
              ) AS activity
                ON activity.activity_date = week_days.week_day
             GROUP BY week_days.week_day
        )
        SELECT (SELECT ROUND(SUM(hours_spent), 2) FROM daily_hours) AS total_week_hours
    ";
    try {
        $previousWeekData = $DB->get_record_sql($previousWeekSql, [$USER->id]);
    } catch (dml_exception $e) {
        error_log("Error executing previous week query: " . $e->getMessage());
        $previousWeekData = (object)['total_week_hours' => 0];
    }
    $previousWeekTotal = $previousWeekData->total_week_hours ?? 0;

    $percentageChange = ($previousWeekTotal > 0)
        ? round((($currentWeekTotal - $previousWeekTotal) / $previousWeekTotal) * 100, 2)
        : 0;
    $isIncrease = ($percentageChange >= 0);

    $templatecontext['hoursActivity']     = json_encode($hoursActivity);
    $templatecontext['currentWeekTotal']  = round($currentWeekTotal, 2);
    $templatecontext['previousWeekTotal'] = round($previousWeekTotal, 2);
    $templatecontext['percentageChange']  = abs($percentageChange);
    $templatecontext['isIncrease']        = $isIncrease;


  
    // f) previous week activity
    // ... your original SQL + calculation ...

    // g) overall progress/points
    // ... your original SQL + calculation ...

    // h) points broken down by course
    // ... your original SQL + loop ...


   $sql = "
        WITH UserID AS (
            SELECT id AS userid FROM mdl_user WHERE id = ?
        ),
        TotalCourses AS (
            SELECT COUNT(c.id) AS total_assigned_courses
              FROM mdl_course c
              JOIN mdl_enrol e            ON c.id = e.courseid
              JOIN mdl_user_enrolments ue ON e.id = ue.enrolid
             WHERE ue.userid = (SELECT userid FROM UserID)
        ),
        CompletedCourses AS (
            SELECT COUNT(DISTINCT cm.course) AS total_completed_courses
              FROM mdl_course_modules_completion cmc
              JOIN mdl_course_modules cm ON cmc.coursemoduleid = cm.id
             WHERE cmc.userid = (SELECT userid FROM UserID)
               AND cmc.completionstate = 1
        ),
        TotalPoints AS (
            SELECT COALESCE(SUM(g.finalgrade), 0) AS total_points_earned
              FROM mdl_grade_grades g
             WHERE g.userid = (SELECT userid FROM UserID)
        ),
        MaxPoints AS (
            SELECT COALESCE(SUM(g.rawgrademax), 0) AS max_total_points
              FROM mdl_grade_grades g
             WHERE g.userid = (SELECT userid FROM UserID)
        )
        SELECT 
            (SELECT total_assigned_courses  FROM TotalCourses)         AS total_courses_assigned,
            (SELECT total_completed_courses FROM CompletedCourses)      AS total_courses_completed,
            ((SELECT total_assigned_courses FROM TotalCourses)
             - (SELECT total_completed_courses FROM CompletedCourses))  AS total_courses_overdue,
            (SELECT total_points_earned FROM TotalPoints)               AS total_points_earned,
            (SELECT max_total_points    FROM MaxPoints)                 AS total_possible_points
    ";
    $userData = $DB->get_record_sql($sql, [$USER->id]);

    $totalCourses        = $userData->total_courses_assigned     ?? 0;
    $completedCourses    = $userData->total_courses_completed    ?? 0;
    $totalOverdue        = $userData->total_courses_overdue      ?? 0;
    $totalPoints         = $userData->total_points_earned        ?? 0;
    $totalPossiblePoints = $userData->total_possible_points      ?? 0;

    $formattedTotalPoints         = rtrim(rtrim(number_format($totalPoints, 2, '.', ''), '0'), '.');
    $formattedTotalPossiblePoints = rtrim(rtrim(number_format($totalPossiblePoints, 2, '.', ''), '0'), '.');

    $learningPathPercentage = ($totalCourses > 0)
        ? round(($completedCourses / $totalCourses) * 100, 2)
        : 0;
    $overduePercentage = ($totalCourses > 0)
        ? round(($totalOverdue / $totalCourses) * 100, 2)
        : 0;

    $templatecontext['completedCourses']       = $completedCourses;
    $templatecontext['totalCourses']           = $totalCourses;
    $templatecontext['totalOverdue']           = $totalOverdue;
    $templatecontext['totalPoints']            = $formattedTotalPoints;
    $templatecontext['totalPossiblePoints']    = $formattedTotalPossiblePoints;
    $templatecontext['learningPathPercentage'] = $learningPathPercentage;
    $templatecontext['overduePercentage']      = $overduePercentage;

    // ------------------------------------------
    // h) Points broken down by course (bar chart)
    // ------------------------------------------
    $sql = "
        WITH UserID AS (
            SELECT id AS userid FROM mdl_user WHERE id = ?
        ),
        CompletedCourses AS (
            SELECT
                c.id               AS course_id,
                c.fullname         AS course_name,
                SUM(g.finalgrade)  AS earned_points,
                SUM(g.rawgrademax) AS total_points_assigned
              FROM mdl_course c
              JOIN mdl_enrol e            ON c.id = e.courseid
              JOIN mdl_user_enrolments ue ON e.id = ue.enrolid
              JOIN mdl_grade_items gi     ON c.id = gi.courseid
              JOIN mdl_grade_grades g     ON gi.id = g.itemid
                                     AND g.userid = ue.userid
             WHERE ue.userid = (SELECT userid FROM UserID)
             GROUP BY c.id, c.fullname
        )
        SELECT
            course_id,
            course_name,
            earned_points,
            total_points_assigned,
            ROUND(
              (earned_points / NULLIF(total_points_assigned, 0)) * 100,
              0
            ) AS percentage_points_earned
          FROM CompletedCourses
         ORDER BY earned_points DESC
    ";
    $userCourses = $DB->get_records_sql($sql, [$USER->id]);

    $courses_data = [];
    foreach ($userCourses as $course) {
        $earned_points = (int)($course->earned_points ?? 0);
        $total_points  = (int)($course->total_points_assigned ?? 1);
        $percentage    = (int)round(($earned_points / $total_points) * 100, 0);
        $bar_color     = $percentage >= 70
            ? "#204070"
            : ($percentage >= 40 ? "#3C6894" : "#808080");

        $courses_data[] = [
            'course_name'   => $course->course_name,
            'earned_points' => $earned_points,
            'total_points'  => $total_points,
            'percentage'    => $percentage,
            'bar_color'     => $bar_color,
            'points_display'=> "$earned_points/$total_points"
        ];
    }
    $templatecontext['courses'] = $courses_data;
}

// ======================================
// 4. WHO SEES WHAT? Admin / Manager / Student
// ======================================
// ======================================
// 4. WHO SEES WHAT? Admin / Manager / Student
// ======================================
$isadmin        = is_siteadmin($USER->id);
$isregionmgr    = false;
$regioncategory = null;


$regionalManagerUser = $USER; // or fetch specific user
$regionalManager = [
    'name' => fullname($regionalManagerUser),
    'profile' => new moodle_url('/user/profile.php', ['id' => $regionalManagerUser->id]),
    'avatar' => $OUTPUT->user_picture($regionalManagerUser, ['size' => 55, 'link' => false])
];
$templatecontext['regionalManager'] = $regionalManager;
// FIXED: Correct regional manager role check with proper parameters
$regionalmanagerrole = 'regionalmanager';
$sql = "
    SELECT ctx.instanceid AS categoryid
      FROM {context} ctx
      JOIN {role_assignments} ra ON ra.contextid = ctx.id
      JOIN {role} r ON r.id = ra.roleid
     WHERE r.shortname = :shortname
       AND ctx.contextlevel = :ctxlevel
       AND ra.userid = :userid
     LIMIT 1
";

// CORRECTED: All 3 parameters properly defined
$params = [
    'shortname' => $regionalmanagerrole,
    'ctxlevel' => CONTEXT_COURSECAT,
    'userid' => $USER->id
];

if ($row = $DB->get_record_sql($sql, $params)) {
    $isregionmgr = true;
    $regioncategory = (int)$row->categoryid;
}

// FIXED: Online users query for admin dashboard
if ($isadmin) {

    // Add this in the admin dashboard section (after online users data)
if ($isadmin) {
    // ... existing admin dashboard code ...

    // NEW: Get user summaries for the admin dashboard
    $userSummarySql = "
        SELECT 
            u.id,
            u.firstname,
            u.lastname,
            u.email,
            COUNT(DISTINCT c.id) AS total_courses,
            COUNT(DISTINCT CASE 
                WHEN cp.progress_percent = 100 THEN c.id 
            END) AS completed_courses,
            COUNT(DISTINCT CASE 
                WHEN cp.progress_percent > 0 AND cp.progress_percent < 100 THEN c.id 
            END) AS inprogress_courses,
            ROUND(SUM(COALESCE(g.finalgrade, 0)), 0) AS total_points_earned,
            ROUND(SUM(COALESCE(g.rawgrademax, 0)), 0) AS max_total_points
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
        GROUP BY u.id, u.firstname, u.lastname, u.email
        ORDER BY u.firstname
        LIMIT 500
    ";

    $templatecontext['userSummaries'] = array_values($DB->get_records_sql($userSummarySql, []));
}
    // ADMIN DASHBOARD
    $templatecontext['template'] = 'theme_academi/core/dashboard_admin';

    // active users
    $templatecontext['activeUserCount'] = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0]);
    
    // Online users count + details
    $fiveMinutesAgo = time() - 300;

    // FIXED: Use proper IN clause handling
    $contextlevels = [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE];
    list($insql, $inparams) = $DB->get_in_or_equal($contextlevels, SQL_PARAMS_NAMED);

    $sql = "
        SELECT
            u.id,
            u.firstname,
            u.lastname,
            u.firstnamephonetic,
            u.lastnamephonetic,
            u.middlename,
            u.alternatename,
            u.lastaccess,
            GROUP_CONCAT(DISTINCT r.shortname ORDER BY r.shortname SEPARATOR ', ') AS roles
        FROM {user} u
        JOIN {role_assignments} ra ON ra.userid = u.id
        JOIN {context} ctx ON ctx.id = ra.contextid
        JOIN {role} r ON r.id = ra.roleid
        WHERE
            u.lastaccess > :since
            AND u.deleted = 0
            AND u.suspended = 0
            AND ctx.contextlevel $insql
        GROUP BY u.id
        ORDER BY u.lastaccess DESC
        LIMIT 500
    ";

    $params = ['since' => $fiveMinutesAgo];
    $params = array_merge($params, $inparams);

    try {
        $onlineRecs = $DB->get_records_sql($sql, $params);
    } catch (dml_exception $e) {
        error_log("Online users query failed: " . $e->getMessage());
        $onlineRecs = [];
    }

    $sysctx = context_system::instance();
    $onlineUsersData = [];

    foreach ($onlineRecs as $u) {
        $fullname = fullname($u);
        $avatar = $OUTPUT->user_picture(
            $DB->get_record('user', ['id' => $u->id]),
            ['size' => 45, 'link' => false, 'class' => 'online-user-avatar']
        );

        $canmessage = (
            isloggedin() &&
            !isguestuser() &&
            !empty($CFG->messaging) &&
            has_capability('moodle/site:sendmessage', $sysctx)
        );
        
        $messageurl = (new moodle_url('/message/index.php', ['id' => $u->id]))->out();
        $profileurl = (new moodle_url('/user/profile.php', ['id' => $u->id]))->out();
        
        // Only keep the first role
        $rolesarr = explode(',', $u->roles);
        $primaryrole = trim($rolesarr[0]);

        $onlineUsersData[] = [
            'id' => $u->id,
            'avatar' => $avatar,
            'fullname' => $fullname,
            'role' => $primaryrole,
            'lastaccess' => userdate($u->lastaccess),
            'messageurl' => $messageurl,
            'profileurl' => $profileurl,
            'canmessage' => $canmessage
        ];
    }

    $templatecontext['onlineUserCount'] = count($onlineUsersData);
    $templatecontext['onlineUsersData'] = $onlineUsersData;
    $templatecontext['allUsersUrl'] = (new moodle_url('/admin/user.php'))->out();

 
// … then later echo $OUTPUT->render_from_template() as before …





    // total regions (children of parent=4)
    $templatecontext['regionCount'] = (int)$DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_categories} WHERE parent=:pid",
        ['pid'=>4]
    );

    // regional managers count/list
    $regionalmgrroleid = $DB->get_field('role','id',['shortname'=>'regionalmanager']);
    $templatecontext['regionMgrCount'] = (int)$DB->count_records('role_assignments',['roleid'=>$regionalmgrroleid]);

     $sqlRecent = "
        SELECT u.id,
               u.firstname,
               u.lastname,
               c.name AS regionname
          FROM {role_assignments} ra
          JOIN {user} u            ON u.id       = ra.userid
          JOIN {context} ctx       ON ctx.id     = ra.contextid
          JOIN {course_categories} c ON c.id      = ctx.instanceid
         WHERE ra.roleid      = :rmrole
           AND ctx.contextlevel = :ctxlevel
         ORDER BY ra.id DESC
         LIMIT 3
    ";
    $rms = $DB->get_records_sql($sqlRecent, [
        'rmrole'   => $regionalmgrroleid,
        'ctxlevel' => CONTEXT_COURSECAT
    ]);

    $recentManagers = [];
   foreach ($rms as $rm) {
    $fulluser = $DB->get_record('user', ['id'=>$rm->id], '*', MUST_EXIST);

    $avatar = $OUTPUT->user_picture($fulluser, [
        'size' => 45,
        'link' => false,
        'class'=> 'manager-avatar'
    ]);

    $recentManagers[] = [
        'id'         => $fulluser->id,
        'username'   => fullname($fulluser),
        'regionname' => $rm->regionname,
        'avatarhtml' => $avatar
    ];
}
    $templatecontext['recentRegionManagers'] = $recentManagers;















    // link roleid
$allRegionsCategory = $DB->get_record('course_categories', ['name' => 'All Regions']);
if (!$allRegionsCategory) {
    $allRegionsCategory = new stdClass();
    $allRegionsCategory->id = 0;
}

// Get all regions and subcategories under "All Regions"
$regions = $DB->get_records('course_categories', ['parent' => $allRegionsCategory->id], 'name');
$regionsArray = [];

foreach ($regions as $region) {
    // For each region, get its subcategories
    $subRegions = $DB->get_records('course_categories', ['parent' => $region->id], 'name');
    $regionData = [
        'id' => $region->id,
        'name' => $region->name,
        'subregions' => []
    ];

    // Add subregions to the region
    foreach ($subRegions as $subRegion) {
        $regionData['subregions'][] = [
            'id' => $subRegion->id,
            'name' => $subRegion->name
        ];
    }

    $regionsArray[] = $regionData;
}

$templatecontext['regions'] = $regionsArray;

    // Get selected region from URL
$selectedRegionId = optional_param('regionid', 0, PARAM_INT);
$templatecontext['selectedRegionId'] = $selectedRegionId;

// Region-based report logic
if ($selectedRegionId) {
    // Fetch report data based on selected region
    $reportData = $DB->get_records_sql("
        SELECT 
            CONCAT(u.id, '-', c.id) AS uniqueid,
            u.id AS userid,
            CONCAT(u.firstname, ' ', u.lastname) AS username,
            c.fullname AS coursename,
            cat.name AS region,
            ROUND(gg.finalgrade, 0) AS score,
            ROUND((gg.finalgrade / NULLIF(gi.grademax, 0)) * 5) AS rating,
            ROUND(
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
            ) AS progress,
            CASE
                WHEN cc.timecompleted IS NOT NULL THEN 1
                ELSE 0
            END AS is_completed,
            CASE
                WHEN cc.timecompleted IS NULL AND gg.finalgrade IS NOT NULL THEN 1
                ELSE 0
            END AS is_progress,
            CASE
                WHEN gg.finalgrade IS NULL THEN 1
                ELSE 0
            END AS is_review
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        JOIN {course_categories} cat ON c.category = cat.id
        LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
        LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
        WHERE c.visible = 1
        AND cat.id = :regionid
        ORDER BY username
        LIMIT 500
    ", ['regionid' => $selectedRegionId]);
} else {
    // Show all regions if no region selected
    $reportData = $DB->get_records_sql("
        SELECT 
            CONCAT(u.id, '-', c.id) AS uniqueid,
            u.id AS userid,
            CONCAT(u.firstname, ' ', u.lastname) AS username,
            c.fullname AS coursename,
            cat.name AS region,
            ROUND(gg.finalgrade, 0) AS score,
            ROUND((gg.finalgrade / NULLIF(gi.grademax, 0)) * 5) AS rating,
            ROUND(
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
            ) AS progress,
            CASE
                WHEN cc.timecompleted IS NOT NULL THEN 1
                ELSE 0
            END AS is_completed,
            CASE
                WHEN cc.timecompleted IS NULL AND gg.finalgrade IS NOT NULL THEN 1
                ELSE 0
            END AS is_progress,
            CASE
                WHEN gg.finalgrade IS NULL THEN 1
                ELSE 0
            END AS is_review
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        JOIN {course_categories} cat ON c.category = cat.id
        LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
        LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
        LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
        WHERE c.visible = 1
        AND cat.parent = :parentid
        ORDER BY region, username
        LIMIT 500
    ", ['parentid' => $allRegionsCategory->id]);
}

// Process results
$processedData = [];
foreach ($reportData as $record) {
    $processedData[] = [
        'username' => $record->username,
        'coursename' => $record->coursename,
        'region' => $record->region,
        'score' => $record->score ?: 0,
        'rating' => min(5, max(0, $record->rating ?: 0)),
        'progress' => $record->progress ?: 0,
        'is_completed' => (bool)($record->is_completed ?? false),
        'is_progress' => (bool)($record->is_progress ?? false),
        'is_review' => (bool)($record->is_review ?? false)
    ];
}

$templatecontext['reportData'] = $processedData;

// Add export URL with region parameter
$templatecontext['exportUrl'] = (new moodle_url('/my/export.php', ['regionid' => $selectedRegionId]))->out();

}  else if ($isregionmgr && $regioncategory !== null) {
    // REGIONAL MANAGER DASHBOARD
    $templatecontext['template'] = 'theme_academi/core/dashboard_manager';
    
    // Get region details
    $cat = $DB->get_record('course_categories', ['id' => $regioncategory]);
    $templatecontext['regionname'] = format_string($cat->name);
    $templatecontext['regionid'] = $regioncategory;
   

    // 1. Active Users in Region
    $activeUserCount = $DB->count_records_sql("
        SELECT COUNT(DISTINCT u.id)
        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e ON e.id = ue.enrolid
        JOIN {course} c ON c.id = e.courseid
        WHERE c.category = :catid
        AND u.deleted = 0
        AND u.suspended = 0
    ", ['catid' => $regioncategory]);
    $templatecontext['activeUserCount'] = $activeUserCount;

    // 2. Online Users in Region (last 5 minutes)
    // 2. Online Users in Region (last 5 minutes)

// 2a) Build the list of categories: the region itself + any immediate subcategories.
$regionids = [$regioncategory];
$subcats   = $DB->get_records('course_categories', ['parent' => $regioncategory], 'id');
foreach ($subcats as $sc) {
    $regionids[] = $sc->id;
}
// generate SQL fragment and params for IN (...).
list($catsql, $catparams) = $DB->get_in_or_equal($regionids, SQL_PARAMS_NAMED);

// 2b) Five minutes ago:
$since = time() - 300;

// 2c) Run the query:
$sql = "
    SELECT DISTINCT
    u.*
  FROM {user} u
      JOIN {logstore_standard_log} l
        ON l.userid = u.id
      JOIN {course} c
        ON c.id = l.courseid
     WHERE l.timecreated > :since
       AND c.category $catsql
       AND u.deleted   = 0
       AND u.suspended = 0
     ORDER BY l.timecreated DESC
";

$params = array_merge(['since' => $since], $catparams);
$onlineUsers = $DB->get_records_sql($sql, $params);

// 2d) Massage for the template:
$onlineUsersData = [];
foreach ($onlineUsers as $u) {
    $onlineUsersData[] = [
        'id'         => $u->id,
        'name'       => fullname($u),
        'email'      => $u->email,
        'profileurl' => (new moodle_url('/user/profile.php', ['id' => $u->id]))->out(),
        'avatar'     => $OUTPUT->user_picture($u, ['size' => 35, 'link' => false]),
    ];
}

$templatecontext['onlineUsers']      = $onlineUsersData;
$templatecontext['onlineUsersCount'] = count($onlineUsersData);
    // 3. Progress Report downlodable
    $regionId = optional_param('regionid', 0, PARAM_INT); // Region ID from the filter
  // 3. Progress Report (Downloadable) - CORRECTED QUERY
   $sql = "
        SELECT 
            CONCAT(u.id, '-', c.id) AS uniqueid,
            u.id AS userid,
            CONCAT(u.firstname, ' ', u.lastname) AS username,
            c.fullname AS coursename,
            cat.name AS region,
            ROUND(gg.finalgrade, 0) AS score,
            ROUND((gg.finalgrade / NULLIF(gi.grademax, 0)) * 5) AS rating,
            ROUND(
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
            ) AS progress,
            CASE
                WHEN cc.timecompleted IS NOT NULL THEN 'Completed'
                WHEN cc.timecompleted IS NULL AND gg.finalgrade IS NOT NULL THEN 'In Progress'
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
        AND cat.id = :regionid
        ORDER BY username, coursename
    ";
    $reportData = $DB->get_records_sql($sql, ['regionid' => $regioncategory]);
    
    // Process report data
    $processedData = [];
    foreach ($reportData as $record) {
        $processedData[] = [
            'username' => $record->username,
            'coursename' => $record->coursename,
            'region' => $record->region,
            'score' => $record->score ?: 0,
            'rating' => min(5, max(0, $record->rating ?: 0)),
            'progress' => $record->progress ?: 0,
            'status' => $record->status
        ];
    }

    // Set template context for report
    $templatecontext['reportData'] = $processedData;
    $templatecontext['exportUrl'] = (new moodle_url('/my/export.php', ['regionid' => $regioncategory]))->out();

  // 3. Student Progress Report
$userfieldssql = \core_user\fields::for_name()->get_sql('u', false, '', 'userid', false);
$studentsReport = $DB->get_recordset_sql("
    SELECT
    CONCAT(u.id, '-', c.id) AS uniquekey,
    u.id AS userid,
    u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname,
    c.fullname AS coursename,
    ROUND((
        SELECT COUNT(*)
        FROM mdl_course_modules_completion cmc
        JOIN mdl_course_modules cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate = 1
    ) * 100.0 / NULLIF((
        SELECT COUNT(*)
        FROM mdl_course_modules cm
        WHERE cm.course = c.id AND cm.completion > 0
    ), 0), 1) AS progress
FROM mdl_user u
JOIN mdl_user_enrolments ue ON ue.userid = u.id
JOIN mdl_enrol e ON e.id = ue.enrolid
JOIN mdl_course c ON c.id = e.courseid
WHERE c.category = ?
AND u.deleted = 0
AND u.suspended = 0
ORDER BY u.lastname, u.firstname, coursename

", array_merge(['catid' => $regioncategory], $userfieldssql->params));

$studentsData = [];
foreach ($studentsReport as $record) {
    $studentsData[] = [
        'student' => fullname($record),
        'course' => $record->coursename,
        'progress' => $record->progress,
        'progressClass' => $record->progress >= 80 ? 'bg-success' : 
                          ($record->progress >= 50 ? 'bg-warning' : 'bg-danger')
    ];
}
$studentsReport->close();
    $templatecontext['studentsData'] = $studentsData;

    // 4. Course Completion Summary
$coursesSummary = $DB->get_records_sql("
    SELECT 
        c.id,
        c.fullname AS coursename,
        COUNT(DISTINCT ue.userid) AS enrolled,
        COUNT(DISTINCT CASE WHEN comp.completed_activities = comp.total_activities THEN ue.userid END) AS completed,
        ROUND(COUNT(DISTINCT CASE WHEN comp.completed_activities = comp.total_activities THEN ue.userid END) * 100.0 / COUNT(DISTINCT ue.userid), 1) AS completion_rate
    FROM {course} c
    JOIN {enrol} e ON e.courseid = c.id
    JOIN {user_enrolments} ue ON ue.enrolid = e.id
    LEFT JOIN (
        SELECT 
            ec.id AS courseid,
            cmc.userid,
            COUNT(DISTINCT cmc.id) AS completed_activities,
            COUNT(DISTINCT cm.id) AS total_activities
        FROM {course} ec
        JOIN {course_modules} cm ON cm.course = ec.id AND cm.completion > 0
        LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.completionstate > 0
        WHERE ec.category = :catid
        GROUP BY ec.id, cmc.userid
    ) comp ON comp.courseid = c.id AND comp.userid = ue.userid
    WHERE c.category = :catid2
    GROUP BY c.id, c.fullname
    ORDER BY completion_rate DESC
", ['catid' => $regioncategory, 'catid2' => $regioncategory]);
    
    $coursesData = [];
    foreach ($coursesSummary as $course) {
        $coursesData[] = [
            'course' => $course->coursename,
            'enrolled' => $course->enrolled,
            'completed' => $course->completed,
            'completion_rate' => $course->completion_rate,
            'statusClass' => $course->completion_rate >= 70 ? 'bg-success' : 
                            ($course->completion_rate >= 40 ? 'bg-warning' : 'bg-danger')
        ];
    }
    $templatecontext['coursesData'] = $coursesData;

    // 5. Recent Activity
 $userfieldssql = \core_user\fields::for_name()->get_sql('u', false, '', 'userid', false);
$recentActivity = $DB->get_recordset_sql("
   SELECT
    l.id AS logid,
    u.id AS userid,
    u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, u.firstname, u.lastname,
    c.fullname AS coursename,
    l.timecreated,
    l.action
FROM mdl_logstore_standard_log l
JOIN mdl_user u ON u.id = l.userid
JOIN mdl_course c ON c.id = l.courseid
WHERE c.category = ?
ORDER BY l.timecreated DESC
LIMIT 10

", array_merge(['catid' => $regioncategory], $userfieldssql->params));

$activityData = [];
foreach ($recentActivity as $activity) {
    $activityData[] = [
        'user' => fullname($activity),
        'course' => $activity->coursename,
        'time' => userdate($activity->timecreated),
        'action' => $activity->action
    ];
}
$recentActivity->close(); // Always close recordset
    $templatecontext['recentActivity'] = $activityData;
}
else {
    // STUDENT DASHBOARD
    $templatecontext['template'] = 'core/dashboard';
    
}

// =================================
// 5. RENDER THE CHOSEN TEMPLATE
// =================================

$event = \core\event\dashboard_viewed::create(['context' => $context]);
$event->trigger();

echo $OUTPUT->header();
$templatecontext['course_award_url'] = $CFG->wwwroot . '/theme/academi/pix/award.gif';
$templatecontext['course_enrolled_url']=$CFG->wwwroot.'/theme/academi/pix/graduate.gif';

if (core_userfeedback::should_display_reminder()) {
    core_userfeedback::print_reminder_block();
}
if (has_capability('moodle/site:manageblocks', context_system::instance())) {
    echo $OUTPUT->addblockbutton('content');
}
$templatecontext['date'] = userdate(time(), get_string('strftimedate'));
echo $OUTPUT->render_from_template($templatecontext['template'], $templatecontext);

echo $OUTPUT->custom_block_region('content');
echo $OUTPUT->footer();