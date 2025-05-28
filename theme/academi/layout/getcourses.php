<?php
// theme/academi/layout/getcourses.php

require_once('../../../config.php');
require_login();

global $DB, $USER, $CFG;

// Get course type from URL parameter (default to 'new')
$type = optional_param('type', 'new', PARAM_ALPHA);
$userid = $USER->id;

header('Content-Type: application/json');

$courses = [];

switch ($type) {
    case 'continue':
        // Continue Learning courses based on recent access
        $sql = "
            SELECT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                FROM_UNIXTIME(l.last_accessed) AS last_accessed_time,
                f.filename AS course_image_filename,
                f.filepath AS course_image_filepath,
                f.mimetype AS course_image_mimetype,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM (
                SELECT courseid, MAX(timecreated) AS last_accessed
                FROM {logstore_standard_log}
                WHERE userid = :userid
                AND courseid IS NOT NULL
                GROUP BY courseid
            ) l
            JOIN {course} c ON l.courseid = c.id
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
            ORDER BY l.last_accessed DESC
            LIMIT 20
        ";
        $courses = $DB->get_records_sql($sql, ['userid' => $userid]);
        break;

    case 'new':
        // New courses based on creation date (last 30 days)
        $sql = "
            SELECT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                c.timecreated,
                f.filename AS course_image_filename,
                f.filepath AS course_image_filepath,
                f.mimetype AS course_image_mimetype,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM {course} c
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
              AND c.timecreated >= :created_since
            ORDER BY c.timecreated DESC
            LIMIT 20
        ";
        $created_since = time() - (30 * 24 * 60 * 60); // last 30 days
        $courses = $DB->get_records_sql($sql, ['created_since' => $created_since]);
        break;

    case 'closing':
        // Closing Soon courses ending in next 15 days
        $sql = "
            SELECT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                c.enddate,
                f.filename AS course_image_filename,
                f.filepath AS course_image_filepath,
                f.mimetype AS course_image_mimetype,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM {course} c
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
              AND c.enddate > 0
              AND c.enddate <= :closing_date
            ORDER BY c.enddate ASC
            LIMIT 20
        ";
        $closing_date = time() + (15 * 24 * 60 * 60); // next 15 days
        $courses = $DB->get_records_sql($sql, ['closing_date' => $closing_date]);
        break;

    default:
        // Default to empty list if unknown type
        $courses = [];
        break;
}

// Prepare courses for frontend output
$courses_data = [];

foreach ($courses as $course) {
    // Compose image URL or fallback
    $course_image_url = (!empty($course->course_image_filename))
        ? $CFG->wwwroot . $course->course_image_url
        : $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg';

    $courses_data[] = array(
        'course_id' => $course->course_id,
        'course_name' => $course->course_name,
        'course_shortname' => $course->course_shortname,
        'course_summary' => strip_tags($course->course_summary),
        'course_image_url' => $course_image_url,
        'course_url' => (new moodle_url('/course/view.php', array('id' => $course->course_id)))->out(false),
    );
}

echo json_encode(array_values($courses_data));
exit;
