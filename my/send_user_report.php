<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__.'/../config.php');
require_once($CFG->libdir.'/moodlelib.php');
require_once($CFG->libdir.'/messagelib.php');
require_login();
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $userid = clean_param($input['userid'], PARAM_INT);
    $email = clean_param($input['email'], PARAM_EMAIL);

    if (!validate_email($email)) {
        throw new Exception('Invalid email address.');
    }

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    $sql = "      WITH course_progress AS (
        SELECT 
            cmc.userid, 
            cm.course,
            
            (COUNT(CASE WHEN cmc.completionstate = 1 THEN 1 END) * 100.0 / COUNT(*)) AS progress_percent
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cmc.completionstate IN (0,1)
        GROUP BY cmc.userid, cm.course
    ),
    completion_count AS (
        SELECT 
            cmc.userid, 
            cm.course,
            COUNT(CASE WHEN cmc.completionstate = 1 THEN 1 END) AS completed_modules,
            COUNT(*) AS total_modules
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        GROUP BY cmc.userid, cm.course
    ),
    course_summary AS (
    SELECT 
        u.id AS userid,
        c.id AS courseid,
        c.fullname AS coursename,
        ccg.name AS categoryname,
        ROUND(COALESCE(cp.progress_percent, 0), 0) AS progress_percent,
        CASE 
            WHEN cc.completed_modules = cc.total_modules THEN 'Completed'
            WHEN cc.completed_modules > 0 THEN 'In Progress'
            ELSE 'Not Started'
        END AS completion_status,
        ROUND(COALESCE(g.finalgrade, 0), 0) AS points_earned,
        ROUND(COALESCE(gi.grademax, 0), 0) AS max_points
    FROM {user} u
    JOIN {user_enrolments} ue ON u.id = ue.userid
    JOIN {enrol} e ON ue.enrolid = e.id
    JOIN {course} c ON c.id = e.courseid
    JOIN {course_categories} ccg ON c.category = ccg.id  -- moved here ✅
    LEFT JOIN course_progress cp ON cp.userid = u.id AND cp.course = c.id
    LEFT JOIN completion_count cc ON cc.userid = u.id AND cc.course = c.id
    LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
    LEFT JOIN {grade_grades} g ON g.itemid = gi.id AND g.userid = u.id
    WHERE u.id = :userid
),
    totals AS (
        SELECT 
            COUNT(*) AS total_courses,
            COUNT(CASE WHEN progress_percent = 100 THEN 1 END) AS completed_courses,
            SUM(points_earned) AS total_earned_points,
            SUM(max_points) AS total_possible_points
        FROM course_summary
    )
  SELECT
    cs.courseid, 
    cs.coursename,
    cs.categoryname,  -- ✅ Add this
    cs.completion_status,
    cs.progress_percent,
    cs.points_earned,
    cs.max_points,
    t.total_courses,
    t.completed_courses,
    t.total_earned_points,
    t.total_possible_points
FROM course_summary cs, totals t";
    $params = ['userid' => $userid];
    $coursedetails = $DB->get_records_sql($sql, $params);

   $csv = "Course Name,Category,Status,Progress %,Points Earned,Max Points,\n";
foreach ($coursedetails as $row) {
    $csv .= sprintf(
        "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
        $row->coursename,
        $row->categoryname,
        $row->completion_status,
        $row->progress_percent,
        $row->points_earned,
        $row->max_points,
    );
}


    $tempdir = make_temp_directory('reports');
    $filename = 'user_report_' . $userid . '_' . time() . '.csv';
    $filepath = $tempdir . '/' . $filename;
    file_put_contents($filepath, $csv);

    $recipient = \core_user::get_user_by_email($email);
    if (!$recipient) {
        $recipient = (object)[ 'email' => $email, 'firstname' => 'User', 'lastname' => 'Report' ];
    }

    $from = \core_user::get_noreply_user();
    $subject = "User Course Report";
    $msgtext = "Hi,\n\nAttached is your detailed course report.\n\nThanks.";
    $msghtml = "<p>Hi,<br><br>Attached is your <strong>course report</strong>.<br><br>Thanks.</p>";

    $sent = email_to_user($recipient, $from, $subject, $msgtext, $msghtml, $filepath, $filename);
    unlink($filepath);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Mail sending failed.');
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
