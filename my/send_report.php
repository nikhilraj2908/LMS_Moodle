<?php
// my/send_report.php

require_once(__DIR__ . '/../config.php');
require_login(null, false, null, false, true);

// Only site admins
if (!is_siteadmin()) {
    throw new moodle_exception('Access denied');
}

try {
    // Read JSON POST body
    $raw = json_decode(file_get_contents("php://input"), true);
    $regionid = isset($raw['regionid']) ? intval($raw['regionid']) : 0;
    $email = isset($raw['email']) ? clean_param($raw['email'], PARAM_EMAIL) : '';

    if (!$email) {
        throw new moodle_exception('Invalid email address.');
    }

    global $DB, $CFG;

    // Build the query similar to dashboard
    $params = [];
    if ($regionid > 0) {
        $sql = "
            SELECT 
                CONCAT(u.id, '-', c.id) AS uniqueid,
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
                        (SELECT COUNT(*) FROM {course_modules} cm WHERE cm.course = c.id AND cm.completion > 0),
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
            JOIN {course_categories} cat ON cat.id = c.category
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
            WHERE c.visible = 1
              AND cat.id = :regionid
            ORDER BY username ASC
            LIMIT 500
        ";
        $params['regionid'] = $regionid;
    } else {
        $sql = "
            SELECT 
                CONCAT(u.id, '-', c.id) AS uniqueid,
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
                        (SELECT COUNT(*) FROM {course_modules} cm WHERE cm.course = c.id AND cm.completion > 0),
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
            JOIN {course_categories} cat ON cat.id = c.category
            LEFT JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
            LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
            LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
            WHERE c.visible = 1
              AND cat.parent = 0
            ORDER BY region, username ASC
            LIMIT 500
        ";
    }

    $reportRows = $DB->get_records_sql($sql, $params);

    // Build CSV
    $csv = "User,Course,Region,Score,Rating,Progress,Status\n";
    foreach ($reportRows as $row) {
        $csv .= sprintf(
            "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
            $row->username,
            $row->coursename,
            $row->region,
            $row->score ?? '0',
            $row->rating ?? '0',
            $row->progress ?? '0',
            $row->status
        );
    }

    // Use PHPMailer
    $mail = get_mailer();
    $mail->setFrom($CFG->noreplyaddress, 'Moodle Report');
    $mail->addAddress($email);
    $mail->Subject = "Region Progress Report";
    $mail->isHTML(true);
    $mail->Body = "<p>Please find the attached report from Moodle.</p>";
    $mail->AltBody = "Please find the attached report from Moodle.";

    // attach
    $mail->addStringAttachment($csv, 'report.csv', 'base64', 'text/csv');

    $result = $mail->send();

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Report sent successfully.' : 'Mail sending failed: ' . $mail->ErrorInfo
    ]);
    exit;

} catch (Throwable $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
