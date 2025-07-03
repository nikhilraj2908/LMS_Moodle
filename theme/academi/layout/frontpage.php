<?php


// If guest user, redirect to guestcourses page
if (isguestuser()) {
    redirect(new moodle_url('/local/guestlogin/guestcourses.php'));
}
defined('MOODLE_INTERNAL') || die();

// Load necessary files and Moodle's global context
require_once(dirname(__FILE__) . '/includes/layoutdata.php');
require_once(dirname(__FILE__) . '/includes/homeslider.php');

$PAGE->requires->css(new moodle_url('/theme/academi/style/slick.css'));
$PAGE->requires->js_call_amd('theme_academi/frontpage', 'init');

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

// Jumbotron class.
$jumbotronclass = (!empty(theme_academi_get_setting('jumbotronstatus'))) ? 'jumbotron-element' : '';

// Default Course Image
$default_image_url = $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg';

// User Dashboard Data
require_login();
global $DB, $USER, $OUTPUT;

// ── 1. Fetch the top 5 users by points ─────────────────────────────────────
$top5 = $DB->get_records_sql("
    SELECT 
        g.userid,
        u.firstname,
        u.lastname,
        u.picture,
        u.imagealt,
        u.email,
        COALESCE(SUM(g.finalgrade), 0) AS total_points
    FROM {grade_grades} g
    JOIN {user} u ON u.id = g.userid
    WHERE u.deleted = 0
    GROUP BY g.userid, u.firstname, u.lastname, u.picture, u.imagealt, u.email
    ORDER BY total_points DESC
    LIMIT 5
");

// ── 2. Massage them into Mustache context ─────────────────────────────────
$templatecontext['topperformers'] = [];
foreach ($top5 as $tp) {
    $user = core_user::get_user($tp->userid);
    if ($user) {
        $pictureHtml = $OUTPUT->user_picture($user, ['size'=>100, 'link'=>false, 'alttext'=>true]);
        $templatecontext['topperformers'][] = [
            'fullname' => fullname($user),
            'points' => (int)$tp->total_points,
            'picture' => $pictureHtml, 
            'profileurl' => (new moodle_url('/user/profile.php', ['id'=>$tp->userid]))->out(),
        ];
    }
}
// In your frontpage.php, modify the forum subscription section:
    if (isloggedin() && !isguestuser()) {
    require_once($CFG->dirroot . '/mod/forum/lib.php');
    
    $course = get_site();
    
    if ($forums = get_all_instances_in_course('forum', $course, $USER->id)) {
        foreach ($forums as $forum) {
            if ($cm = get_coursemodule_from_instance('forum', $forum->id, $course->id)) {
                $context = context_module::instance($cm->id);
                
                if (has_capability('mod/forum:viewdiscussion', $context)) {
                    if (\mod_forum\subscriptions::is_forcesubscribed($forum) === false) {
                        $subscription = \mod_forum\subscriptions::is_subscribed($USER->id, $forum);
                        
                        $templatecontext['forumsubscription'] = [
                            'forumid' => $forum->id,
                            'cmid' => $cm->id,
                            'courseid' => $course->id,
                            'subscribed' => $subscription,
                            'sesskey' => sesskey(),
                            'returnurl' => $CFG->wwwroot // Ensure proper return URL
                        ];
                        break;
                    }
                }
            }
        }
    }
}
if (isloggedin() && !isguestuser()) {
    $userid = $USER->id;
    $username = $USER->username; 
    $displayname = fullname($USER);
    $userpicture = $OUTPUT->user_picture($USER, ['size' => 100]);

    // -- NEW QUERY WITH CTE -----------------------------------------------
    $userData = $DB->get_record_sql("
        WITH UserID AS (
            SELECT id AS userid FROM {user} WHERE username = ?
        ),
        TotalCourses AS (
            SELECT COUNT(c.id) AS total_assigned_courses
            FROM {course} c
            JOIN {enrol} e ON c.id = e.courseid
            JOIN {user_enrolments} ue ON e.id = ue.enrolid
            WHERE ue.userid = (SELECT userid FROM UserID)
        ),
        CompletedCourses AS (
            SELECT COUNT(DISTINCT cm.course) AS total_completed_courses
            FROM {course_modules_completion} cmc
            JOIN {course_modules} cm ON cmc.coursemoduleid = cm.id
            WHERE cmc.userid = (SELECT userid FROM UserID) 
              AND cmc.completionstate = 1
        ),
        TotalPoints AS (
            SELECT COALESCE(SUM(g.finalgrade), 0) AS total_points_earned
            FROM {grade_grades} g
            WHERE g.userid = (SELECT userid FROM UserID)
        ),
        MaxPoints AS (
            SELECT COALESCE(SUM(g.rawgrademax), 0) AS max_total_points
            FROM {grade_grades} g
            WHERE g.userid = (SELECT userid FROM UserID)
        )
        SELECT 
            (SELECT total_assigned_courses FROM TotalCourses) AS total_courses_assigned,
            (SELECT total_completed_courses FROM CompletedCourses) AS total_courses_completed,
            ((SELECT total_assigned_courses FROM TotalCourses) 
                - (SELECT total_completed_courses FROM CompletedCourses)) AS total_courses_overdue,
            (SELECT total_points_earned FROM TotalPoints) AS total_points_earned,
            (SELECT max_total_points FROM MaxPoints) AS total_possible_points
        FROM dual
    ", [$username]);

    $totalCourses = $userData->total_courses_assigned ?? 0;
    $completedCourses = $userData->total_courses_completed ?? 0;
    $totalOverdue = $userData->total_courses_overdue ?? 0;
    $totalPoints = $userData->total_points_earned ?? 0;
    $totalPossiblePoints = $userData->total_possible_points ?? 0;
 
    $formattedTotalPoints = rtrim(rtrim(number_format($totalPoints, 2, '.', ''), '0'), '.');
    $formattedTotalPossiblePoints = rtrim(rtrim(number_format($totalPossiblePoints, 2, '.', ''), '0'), '.');
 
    $learningPathPercentage = ($totalCourses > 0)
        ? round(($completedCourses / $totalCourses) * 100, 2)
        : 0;
 
    $formattedUsername = ucfirst(strtolower($displayname));
    $templatecontext += [
        'isloggedin' => true,
        'username' => $formattedUsername,
        'userpicture' => $userpicture,
        'completedCourses' => $completedCourses,
        'totalCourses' => $totalCourses,
        'totalPoints' => $formattedTotalPoints,
        'learningPathPercentage' => $learningPathPercentage,
        'curriculumPercentage' => $learningPathPercentage,
        'totalPossiblePoints' => $formattedTotalPossiblePoints,
        'totalOverdue' => $totalOverdue,
    ];
     
    $courses = $DB->get_records_sql("
        SELECT 
            c.id AS course_id,
            c.fullname AS course_name,
            c.shortname AS course_shortname,
            c.summary AS course_summary,
            c.startdate AS course_startdate,
            c.enddate AS course_enddate,
            c.visible AS course_visible,
            cc.name AS category_name,
            f.filename AS course_image_filename,
            f.filepath AS course_image_filepath,
            f.mimetype AS course_image_mimetype,
            CONCAT('/pluginfile.php/', ctx.id, '/course/overviewfiles/', f.filename) AS course_image_url
        FROM {course} c
        JOIN {enrol} e ON e.courseid = c.id
        JOIN {user_enrolments} ue ON ue.enrolid = e.id
        LEFT JOIN {course_categories} cc ON c.category = cc.id
        LEFT JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50
        LEFT JOIN {files} f ON ctx.id = f.contextid 
                   AND f.component = 'course' 
                   AND f.filearea = 'overviewfiles' 
                   AND f.filename <> '.'
        WHERE ue.userid = ?
        AND c.visible = 1
        ORDER BY c.fullname ASC
    ", [$userid]);

    $courses_data = [];
    foreach ($courses as $course) {
        $course_image_url = (!empty($course->course_image_filename))
            ? $CFG->wwwroot . $course->course_image_url
            : $default_image_url;

        $startdate = ($course->course_startdate) 
            ? date('Y-m-d', $course->course_startdate) 
            : 'N/A';
        $enddate = ($course->course_enddate) 
            ? date('Y-m-d', $course->course_enddate) 
            : 'N/A';

        $courses_data[] = [
            'course_id' => $course->course_id,
            'course_name' => $course->course_name,
            'course_shortname' => $course->course_shortname,
            'course_summary' => strip_tags($course->course_summary),
            'course_image_url' => $course_image_url,
            'course_url' => new moodle_url('/course/view.php', ['id' => $course->course_id]),
            'course_startdate' => $startdate,
            'course_enddate' => $enddate,
            'category_name' => $course->category_name,
        ];
    }
    $templatecontext['courses'] = $courses_data;
    
    $templatecontext['alert_gif'] = $OUTPUT->image_url('alert', 'theme_academi')->out(false);

    $is_admin = is_siteadmin($USER->id);
    $showpopup = false;
    $popupmessage = "";
    
    if ($is_admin) {
        $last_course = $DB->get_record_sql("SELECT id, fullname, timecreated FROM {course} ORDER BY timecreated DESC LIMIT 1");
    
        if ($last_course) {
            $last_upload_time = $last_course->timecreated;
            $current_time = time();
            $one_week = 7 * 24 * 60 * 60;
    
            if (($current_time - $last_upload_time) >= $one_week) {
                $showpopup = true;
                $popupmessage = "It's been more than a week since a course was last uploaded! Please upload a new course.";
            }
        }
    }
    
    $templatecontext['bodyattributes'] = $bodyattributes;
    $templatecontext['jumbotronclass'] = $jumbotronclass;
    $templatecontext['showpopup'] = $showpopup;
    $templatecontext['popupmessage'] = $popupmessage;
} else {
    $templatecontext['isloggedin'] = false;
}

$templatecontext['sitefeatures'] = (new \theme_academi\academi_blocks())->sitefeatures();

$templatecontext['banners'] = [
    [
        'image_url' => $OUTPUT->image_url('banner1', 'theme_academi')->out(),
        'title' => 'Transform your Learning Journey',
        'subtitle' => 'With ALOGICDATA',
        'description' => 'Bringing Education to Your Fingertips',
        'cta_text' => 'Learn More',
        'cta_link' => '#',
        'is_active' => true
    ],
    [
        'image_url' => $OUTPUT->image_url('banner2', 'theme_academi')->out(),
        'title' => 'Boost Your Skills, Anytime',
        'subtitle' => 'Anywhere',
        'description' => 'Courses tailored for your success.',
        'cta_text' => 'Explore Now',
        'cta_link' => '#',
        'is_active' => false
    ],
    [
        'image_url' => $OUTPUT->image_url('banner3', 'theme_academi')->out(),
        'title' => 'Your Learning Partner Built',
        'subtitle' => 'for You',
        'description' => 'Start your journey today.',
        'cta_text' => 'Get Started',
        'cta_link' => '#',
        'is_active' => false
    ]
];

$templatecontext += $sliderconfig;
$templatecontext += [
    'bodyattributes' => $bodyattributes,
    'jumbotronclass' => $jumbotronclass,
];
$renderer = $PAGE->get_renderer('core', 'course');
$templatecontext['newsitems_html'] = $renderer->render_news_items_html();

echo $OUTPUT->render_from_template('theme_academi/frontpage', $templatecontext);