<?php
require_once(__DIR__.'/../config.php');
require_login();

$userid = required_param('id', PARAM_INT);
$context = context_system::instance();

if (!has_capability('moodle/site:config', $context)) {
    throw new moodle_exception('nopermission');
}

$PAGE->set_context($context);
$PAGE->set_url('/my/user_report.php', array('id' => $userid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('User Report');
$PAGE->set_heading('User Report');

$user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

// Get user course details
$sql = "
    SELECT
        c.id AS courseid,
        c.fullname AS coursename,
        cat.name   AS categoryname,

        /* ---- Progress % (same logic as course_report.php) ---- */
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

        /* ---- Completion label (exactly like course_report.php) ---- */
        CASE
            -- Completed if Moodle course_completions has a record
            WHEN cc.timecompleted IS NOT NULL THEN 'Completed'

            -- Or if user has finished 100% of required modules
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

            -- If some modules done but < 100% → In Progress
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

            -- Otherwise → Not Started
            ELSE 'Not Started'
        END AS completion_status,

        /* ---- Points (same as other reports) ---- */
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
          ON g.itemid    = gi.id AND g.userid = u.id

   WHERE u.id = :userid
   ORDER BY c.fullname ASC
";


$userpicture = new user_picture($user);
$userpicture->size = 100; // Optional: 100 = large, 35 = small
$userpicturehtml = $OUTPUT->render($userpicture);


$params = array('userid' => $userid);
$coursedetails = $DB->get_records_sql($sql, $params);

// Prepare data for template
$data = array(
    'date' => date('d M Y, H:i A'),
    'user' => $user,
    'userpicture' => $userpicturehtml, // 👈 Add this line
    'coursedetails' => array_values($coursedetails),
);


echo $OUTPUT->header();
// echo $OUTPUT->render_from_template('core/user_report', $data);
echo $OUTPUT->render_from_template('theme_academi/user_report', $data);


echo $OUTPUT->footer();