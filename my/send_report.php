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

$admin = get_admin();

try {
    // Step 1: Read and validate input
    $input      = json_decode(file_get_contents('php://input'), true);
    $categoryid = isset($input['categoryid']) ? (int)$input['categoryid'] : 0;

    // Step 2: Build SQL where-clause for category
    $categoryWhere = '';
    $params        = [];

    if ($categoryid > 0) {
        $categoryWhere       = 'AND c.category = :categoryid';
        $params['categoryid'] = $categoryid;
    }

    // Step 3: MAIN SQL
    // This is aligned with the dashboard "userSummaries" query
    $sql = "
        SELECT 
            u.id,
            CONCAT(u.firstname, ' ', u.lastname) AS fullname,
            u.email,
            cc.name AS categoryname,

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
        LEFT JOIN {course_categories} cc ON cc.id = c.category

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
        $categoryWhere

        GROUP BY u.id, u.firstname, u.lastname, u.email, cc.name
        ORDER BY u.firstname ASC, u.lastname ASC
        LIMIT 500
    ";

    $reportRows = $DB->get_records_sql($sql, $params);

    // Step 4: Build CSV (one line per user)
    $csv = "Name,Category,Email,Total Courses,Completed,In Progress,Not Started,Points Earned / Max\n";
    foreach ($reportRows as $row) {
        $csv .= sprintf(
            "\"%s\",\"%s\",\"%s\",\"%d\",\"%d\",\"%d\",\"%d\",\"%s / %s\"\n",
            $row->fullname,
            $row->categoryname ?? 'N/A',
            $row->email,
            $row->total_courses,
            $row->completed_courses,
            $row->inprogress_courses,
            $row->notstarted_courses,
            $row->total_points_earned,
            $row->max_total_points
        );
    }

    // Step 5: Create temp file
    $tempdir  = make_temp_directory('reports');
    $filename = 'user_summary_report_' . time() . '.csv';
    $filepath = $tempdir . '/' . $filename;

    if (file_put_contents($filepath, $csv) === false) {
        throw new Exception("Failed to write CSV to temporary file.");
    }

    // Step 6: Recipient (site admin for bulk mail)
    $recipient = get_admin();

    $from        = \core_user::get_noreply_user();
    $subject     = "User Summary Report";
    $messagetext = "Hi,\n\nAttached is the User Summary Report based on your selected category filter.\n\nRegards,\nAdmin";
    $messagehtml = "<p>Hi,<br><br>Attached is the <strong>User Summary Report</strong> based on your selected category filter.<br><br>Regards,<br>Admin</p>";

    $success = email_to_user(
        $recipient,
        $from,
        $subject,
        $messagetext,
        $messagehtml,
        $filepath,   // attachment path
        $filename    // attachment name
    );

    // Step 7: Cleanup and return JSON
    @unlink($filepath);

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
