<?php
// File: my/send_user_report.php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/messagelib.php');

require_login();

$PAGE->set_context(context_system::instance());
header('Content-Type: application/json');

try {
    global $DB;

    // ----- 1. Read JSON input -----
    $input  = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['userid'])) {
        throw new Exception('Missing userid.');
    }
    $userid = clean_param($input['userid'], PARAM_INT);

    // Make sure user exists (mainly for error clarity)
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // ----- 2. Build data query (MATCHES user_report.php / export_user_report.php) -----
    $sql = "
        SELECT
            c.id        AS courseid,
            c.fullname  AS coursename,
            cat.name    AS categoryname,

            /* Progress % (only visible, trackable modules in real sections) */
            ROUND((
                SELECT
                    SUM(CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                    / NULLIF(COUNT(*), 0)
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
           LEFT JOIN {course_modules_completion} cmc
                  ON cmc.coursemoduleid = cm.id
                 AND cmc.userid         = u.id
                WHERE cm.course     = c.id
                  AND cm.visible    = 1
                  AND cm.completion > 0
                  AND cs.section   >= 1
            ), 0) AS progress_percent,

            /* Completion label – EXACTLY same logic as user_report.php */
            CASE
                -- 1) Completed if Moodle course_completions has a record
                WHEN cc.timecompleted IS NOT NULL THEN 'Completed'

                -- 2) Or if user has 100% of required modules complete
                WHEN (
                    SELECT ROUND(
                        SUM(CASE WHEN cmc2.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                        / NULLIF(COUNT(*), 0), 0
                    )
                      FROM {course_modules} cm2
                      JOIN {course_sections} cs2 ON cs2.id = cm2.section
                 LEFT JOIN {course_modules_completion} cmc2
                        ON cmc2.coursemoduleid = cm2.id
                       AND cmc2.userid         = u.id
                     WHERE cm2.course     = c.id
                       AND cm2.visible    = 1
                       AND cm2.completion > 0
                       AND cs2.section   >= 1
                ) >= 100 THEN 'Completed'

                -- 3) If some modules done but < 100% → In Progress
                WHEN (
                    SELECT
                        SUM(CASE WHEN cmc3.completionstate = 1 THEN 1 ELSE 0 END)
                      FROM {course_modules} cm3
                      JOIN {course_sections} cs3 ON cs3.id = cm3.section
                 LEFT JOIN {course_modules_completion} cmc3
                        ON cmc3.coursemoduleid = cm3.id
                       AND cmc3.userid         = u.id
                     WHERE cm3.course     = c.id
                       AND cm3.visible    = 1
                       AND cm3.completion > 0
                       AND cs3.section   >= 1
                ) > 0 THEN 'In Progress'

                -- 4) Otherwise → Not Started
                ELSE 'Not Started'
            END AS completion_status,

            /* Points */
            ROUND(COALESCE(g.finalgrade, 0), 0) AS points_earned,
            ROUND(COALESCE(gi.grademax,   0), 0) AS max_points

        FROM {user} u
        JOIN {user_enrolments} ue ON ue.userid = u.id
        JOIN {enrol} e            ON e.id     = ue.enrolid
        JOIN {course} c           ON c.id     = e.courseid
        JOIN {course_categories} cat ON cat.id = c.category
   LEFT JOIN {course_completions} cc
          ON cc.course = c.id AND cc.userid = u.id
   LEFT JOIN {grade_items} gi
          ON gi.courseid = c.id AND gi.itemtype = 'course'
   LEFT JOIN {grade_grades} g
          ON g.itemid    = gi.id AND g.userid  = u.id

        WHERE u.id = :userid
        ORDER BY c.fullname ASC
    ";

    $params        = ['userid' => $userid];
    $coursedetails = $DB->get_records_sql($sql, $params);

    // ----- 3. Build CSV in memory -----
    $csv = "Course Name,Category,Status,Progress %,Points Earned,Max Points\n";

    foreach ($coursedetails as $row) {
        $csv .= sprintf(
            "\"%s\",\"%s\",\"%s\",\"%d\",\"%d\",\"%d\"\n",
            $row->coursename,
            $row->categoryname,
            $row->completion_status,
            (int)$row->progress_percent,
            (int)$row->points_earned,
            (int)$row->max_points
        );
    }

    // ----- 4. Write temp file -----
    $tempdir  = make_temp_directory('reports');
    $filename = 'user_report_' . $userid . '_' . time() . '.csv';
    $filepath = $tempdir . '/' . $filename;

    if (file_put_contents($filepath, $csv) === false) {
        throw new Exception('Failed to write CSV file.');
    }

    // ----- 5. Choose recipient (site admin) -----
    // If later you want “mail to that user”, swap this to $user instead.
    $recipient = get_admin();
    $from      = \core_user::get_noreply_user();

    $subject  = "User Course Report for {$user->firstname} {$user->lastname}";
    $msgtext  = "Hi,\n\nAttached is the detailed course report for {$user->firstname} {$user->lastname}.\n\nThanks.";
    $msghtml  = "<p>Hi,<br><br>Attached is the <strong>course report</strong> for "
              . format_string(fullname($user))
              . ".<br><br>Thanks.</p>";

    $sent = email_to_user(
        $recipient,
        $from,
        $subject,
        $msgtext,
        $msghtml,
        $filepath,
        $filename
    );

    // Clean up temp file
    @unlink($filepath);

    if ($sent) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Mail sending failed. Please check SMTP / mail config.');
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
