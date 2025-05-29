<?php
// theme/academi/layout/getcourses.php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $DB, $USER, $CFG;

$type = optional_param('type', 'new', PARAM_ALPHA);
$userid = $USER->id;

header('Content-Type: application/json');
$courses = [];

// Common JOIN for enrollment
$enrollment_join = "
    JOIN {enrol} e ON e.courseid = c.id
    JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid
";

switch ($type) {
    case 'continue':
        $sql = "
            SELECT DISTINCT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                MAX(l.timecreated) AS last_accessed,
                f.filename AS course_image_filename,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM {logstore_standard_log} l
            JOIN {course} c ON l.courseid = c.id
            $enrollment_join
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
                AND l.userid = :loguserid
                AND l.courseid IS NOT NULL
            GROUP BY c.id, c.fullname, c.shortname, c.summary, f.filename, ctx.id
            ORDER BY MAX(l.timecreated) DESC
            LIMIT 20
        ";
        $params = [
            'userid' => $userid,
            'loguserid' => $userid
        ];
        $courses = $DB->get_records_sql($sql, $params);
        break;

    case 'new':
        $sql = "
            SELECT DISTINCT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                c.timecreated,
                f.filename AS course_image_filename,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM {course} c
            $enrollment_join
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
                AND c.timecreated >= :created_since
            ORDER BY c.timecreated DESC
            LIMIT 20
        ";
        $params = [
            'userid' => $userid,
            'created_since' => time() - (30 * 24 * 60 * 60)
        ];
        $courses = $DB->get_records_sql($sql, $params);
        break;

    case 'closing':
        $base_sql = "
            SELECT DISTINCT
                c.id AS course_id,
                c.fullname AS course_name,
                c.shortname AS course_shortname,
                c.summary AS course_summary,
                c.enddate,
                f.filename AS course_image_filename,
                CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
            FROM {course} c
            $enrollment_join
            LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
            LEFT JOIN {files} f ON ctx.id = f.contextid AND f.component = 'course'
                AND f.filearea = 'overviewfiles' AND f.filename <> '.'
            WHERE c.visible = 1
                AND c.enddate > UNIX_TIMESTAMP()
                AND c.enddate <= :closing_date
                AND c.startdate <= UNIX_TIMESTAMP()
            ORDER BY c.enddate ASC
        ";

        // First try: Courses ending in next 15 days
        $params = [
            'userid' => $userid,
            'closing_date' => time() + (15 * 24 * 60 * 60)
        ];
        $courses = $DB->get_records_sql($base_sql . " LIMIT 4", $params);
        
        // Second try: Extend to 30 days if needed
        if (count($courses) < 4) {
            $params['closing_date'] = time() + (30 * 24 * 60 * 60);
            $more_courses = $DB->get_records_sql($base_sql . " LIMIT 10", $params);
            
            foreach ($more_courses as $course) {
                if (count($courses) >= 4) break;
                if (!isset($courses[$course->course_id])) {
                    $courses[$course->course_id] = $course;
                }
            }
        }
        
        // Final sorting by enddate
        usort($courses, function($a, $b) {
            return $a->enddate <=> $b->enddate;
        });
        break;

    default:
        $courses = [];
        break;
}


$courses_data = [];

foreach ($courses as $course) {
    $course_image_url = (!empty($course->course_image_filename))
        ? $CFG->wwwroot . $course->course_image_url
        : $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg';

    // Process summary
    $rawsummary = strip_tags($course->course_summary);
    $summary = '';
    
    if (!empty(trim($rawsummary))) {
        $words = explode(' ', $rawsummary);
        $summary = (count($words) > 8) 
            ? implode(' ', array_slice($words, 0, 8)) . '...' 
            : $rawsummary;
    }
    

    $courses_data[] = [
        'course_id' => (int)$course->course_id,
        'course_name' => $course->course_name,
        'course_shortname' => $course->course_shortname,
        'course_summary' => $summary,
        'course_image_url' => $course_image_url,
        'course_url' => (new moodle_url('/course/view.php', ['id' => $course->course_id]))->out(false),
    ];
}

// Ensure at least 4 courses

$min_courses = 4;
if (count($courses_data) < $min_courses) {
    $placeholders = $min_courses - count($courses_data);
    for ($i = 0; $i < $placeholders; $i++) {
        $placeholder = [
            'course_id' => 0,
            'course_image_url' => $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg',
            'course_url' => '#'
        ];

        if ($type === 'closing') {
            $placeholder['course_name'] = 'No courses closing soon';
            $placeholder['course_summary'] = 'Check back later for upcoming deadlines';
            $placeholder['course_shortname'] = 'no-closing';
        } else {
            $placeholder['course_name'] = 'Coming Soon';
            $placeholder['course_summary'] = 'New courses coming soon';
            $placeholder['course_shortname'] = 'coming-soon';
        }

        $courses_data[] = $placeholder;
    }
}

echo json_encode(array_values($courses_data));
exit;