<?php
// File: my/send_report.php

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/messagelib.php');
require_once($CFG->dirroot . '/user/lib.php');

require_login();
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json');

try {
    // Step 1: Read and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    $categoryid = isset($input['categoryid']) ? (int)$input['categoryid'] : 0;
    $email = isset($input['email']) ? clean_param($input['email'], PARAM_EMAIL) : '';

    if (!validate_email($email)) {
        throw new Exception('Invalid email address.');
    }

    // Step 2: Build SQL
    $categoryWhere = '';
    $params = [];

    if ($categoryid > 0) {
        $categoryWhere = 'AND c.category = :categoryid';
        $params['categoryid'] = $categoryid;
    }

  // Step 2: Build SQL with category join
$sql = "
    SELECT 
        u.id,
        CONCAT(u.firstname, ' ', u.lastname) AS fullname,
        u.email,
        cc.name AS categoryname,  -- ✅ Category Name
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
    LEFT JOIN {course_categories} cc ON cc.id = c.category  -- ✅ JOIN added
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
    GROUP BY u.id, u.firstname, u.lastname, u.email, cc.name
    ORDER BY u.firstname ASC
    LIMIT 500
";


    $reportRows = $DB->get_records_sql($sql, $params);

    // Step 3: Build CSV
   $csv = "Name,Category,Email,Total Courses,Completed,In Progress,Not Started,Points Earned / Max\n";
foreach ($reportRows as $row) {
    $csv .= sprintf(
        "\"%s\",\"%s\",\"%s\",\"%d\",\"%d\",\"%d\",\"%d\",\"%s / %s\"\n",
        $row->fullname,
        $row->categoryname ?? 'N/A',  // ✅ Safeguard if null
        $row->email,
        $row->total_courses,
        $row->completed_courses,
        $row->inprogress_courses,
        $row->notstarted_courses,
        $row->total_points_earned,
        $row->max_total_points
    );
}

    // Step 4: Create temp file
    $tempdir = make_temp_directory('reports');
    $filename = 'user_summary_report_' . time() . '.csv';
    $filepath = $tempdir . '/' . $filename;
    if (file_put_contents($filepath, $csv) === false) {
        throw new Exception("Failed to write CSV to temporary file.");
    }

    // Step 5: Get recipient user
    $user = \core_user::get_user_by_email($email);
    if (!$user) {
        $user = (object)[
            'email' => $email,
            'firstname' => 'User',
            'lastname' => 'Report'
        ];
    }

    // Step 6: Email the report (FIXED ATTACHMENT HANDLING)
    $from = \core_user::get_noreply_user();
    $subject = "User Summary Report";
    $messagetext = "Hi,\n\nAttached is the User Summary Report based on your selected category filter.\n\nRegards,\nAdmin";
    $messagehtml = "<p>Hi,<br><br>Attached is the <strong>User Summary Report</strong> based on your selected category filter.<br><br>Regards,<br>Admin</p>";

    $success = email_to_user(
        $user,
        $from,
        $subject,
        $messagetext,
        $messagehtml,
        $filepath,   // Pass file path directly
        $filename    // Attachment name
    );

    // Step 7: Cleanup and return JSON
    unlink($filepath);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Email sending failed. Please check SMTP settings.");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}