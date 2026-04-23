<?php
// Course Report (details for one course)
// Path: /my/course_report.php

require_once(__DIR__ . '/../config.php');
require_login();

require_once($CFG->libdir . '/csvlib.class.php');  // csv_export_writer
require_once($CFG->libdir . '/filelib.php');
$courseid = required_param('id', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

// Filters (distinct names so they never collide with other pages)
$userq  = trim(optional_param('userq', '', PARAM_TEXT));            // search in user name/email
$status = optional_param('status', 'all', PARAM_ALPHA);             // all|completed|inprogress|notstarted
$sortby = optional_param('sortby', 'name', PARAM_ALPHA);            // name|progress|points
$dirraw = strtolower(optional_param('dir', 'asc', PARAM_ALPHA));
$dir    = ($dirraw === 'desc') ? 'DESC' : 'ASC';

// Permissions (match your user_report.php)
$sysctx = context_system::instance();
if (!has_capability('moodle/site:config', $sysctx)) {
    throw new moodle_exception('nopermission');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$ctx    = context_course::instance($courseid);

// Page setup
$PAGE->set_context($ctx);
$PAGE->set_url('/my/course_report.php', ['id' => $courseid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Course Report');
$PAGE->set_heading('Course Report');

// Category name
$categoryname = $DB->get_field('course_categories', 'name', ['id' => $course->category]);

// Course image (overview/summary) or fallback to default
$courseimageurl = $CFG->wwwroot . '/theme/academi/pix/defaultcourse.jpg';

// This class knows how to find card images (overviewfiles, summary, etc.)
$cle = new core_course_list_element($course);

foreach ($cle->get_course_overviewfiles() as $f) {
    // Accept standard images + svg/webp
    $mime = $f->get_mimetype();
    if ($f->is_valid_image() || in_array($mime, ['image/svg+xml', 'image/webp'], true)) {
        // Works with or without slasharguments
        $courseimageurl = file_encode_url(
            $CFG->wwwroot . '/pluginfile.php',
            '/' . $f->get_contextid() .
                '/' . $f->get_component() .
                '/' . $f->get_filearea() .
                '/' . $f->get_itemid() .
                $f->get_filepath() .
                $f->get_filename(),
            false
        );
        break;
    }
}


// Trackable modules in this course
$totalmodules = (int)$DB->get_field_sql("
    SELECT COUNT(1)
      FROM {course_modules} cm
      JOIN {course_sections} cs ON cs.id = cm.section
     WHERE cm.course = :cid
       AND cm.visible = 1
       AND cm.completion > 0
       AND cs.section >= 1
", ['cid' => $courseid]);

// Pull user rows (enrolled + progress + status + points)
$sql = "
    SELECT
        u.id,
        u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
        u.email, u.lastaccess,

        ROUND((
            SELECT SUM(CASE WHEN cmc.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                   / NULLIF(COUNT(*), 0)
              FROM {course_modules} cm
              JOIN {course_sections} cs
                ON cs.id = cm.section
         LEFT JOIN {course_modules_completion} cmc
                ON cmc.coursemoduleid = cm.id
               AND cmc.userid = u.id
             WHERE cm.course = :courseid_progress
               AND cm.visible = 1
               AND cm.completion > 0
               AND cs.section >= 1
        ), 0) AS progress_percent,

        (
            SELECT COUNT(1)
              FROM {scorm} s
              JOIN {course_modules} cmsc
                ON cmsc.instance = s.id
              JOIN {modules} msc
                ON msc.id = cmsc.module
               AND msc.name = 'scorm'
              JOIN {course_sections} cssc
                ON cssc.id = cmsc.section
              JOIN {scorm_attempt} sa
                ON sa.scormid = s.id
               AND sa.userid = u.id
             WHERE s.course = :courseid_scormstarted
               AND cmsc.visible = 1
               AND cssc.section >= 1
        ) AS hasscormattempt,

        CASE
            WHEN cc.timecompleted IS NOT NULL THEN 'Completed'

            WHEN (
                SELECT ROUND(
                    SUM(CASE WHEN cmc2.completionstate = 1 THEN 1 ELSE 0 END) * 100.0
                    / NULLIF(COUNT(*), 0), 0
                )
                  FROM {course_modules} cm2
                  JOIN {course_sections} cs2
                    ON cs2.id = cm2.section
             LEFT JOIN {course_modules_completion} cmc2
                    ON cmc2.coursemoduleid = cm2.id
                   AND cmc2.userid = u.id
                 WHERE cm2.course = :courseid_completed
                   AND cm2.visible = 1
                   AND cm2.completion > 0
                   AND cs2.section >= 1
            ) >= 100 THEN 'Completed'

            WHEN (
                SELECT SUM(CASE WHEN cmc3.completionstate = 1 THEN 1 ELSE 0 END)
                  FROM {course_modules} cm3
                  JOIN {course_sections} cs3
                    ON cs3.id = cm3.section
             LEFT JOIN {course_modules_completion} cmc3
                    ON cmc3.coursemoduleid = cm3.id
                   AND cmc3.userid = u.id
                 WHERE cm3.course = :courseid_inprogress
                   AND cm3.visible = 1
                   AND cm3.completion > 0
                   AND cs3.section >= 1
            ) > 0 THEN 'In Progress'

            WHEN (
                SELECT COUNT(1)
                  FROM {scorm} s2
                  JOIN {course_modules} cmsc2
                    ON cmsc2.instance = s2.id
                  JOIN {modules} msc2
                    ON msc2.id = cmsc2.module
                   AND msc2.name = 'scorm'
                  JOIN {course_sections} cssc2
                    ON cssc2.id = cmsc2.section
                  JOIN {scorm_attempt} sa2
                    ON sa2.scormid = s2.id
                   AND sa2.userid = u.id
                 WHERE s2.course = :courseid_startedstatus
                   AND cmsc2.visible = 1
                   AND cssc2.section >= 1
            ) > 0 THEN 'In Progress'

            ELSE 'Not Started'
        END AS completion_status,

        ROUND(COALESCE(gg.finalgrade, 0), 0) AS points_earned,
        ROUND(COALESCE(gi.grademax, 0), 0) AS max_points

      FROM {enrol} e
      JOIN {user_enrolments} ue
        ON ue.enrolid = e.id
       AND ue.status = 0
      JOIN {user} u
        ON u.id = ue.userid
       AND u.deleted = 0
       AND u.suspended = 0
 LEFT JOIN {course_completions} cc
        ON cc.course = e.courseid
       AND cc.userid = u.id
 LEFT JOIN {grade_items} gi
        ON gi.courseid = e.courseid
       AND gi.itemtype = 'course'
 LEFT JOIN {grade_grades} gg
        ON gg.itemid = gi.id
       AND gg.userid = u.id
     WHERE e.courseid = :courseid_main
  ORDER BY u.lastname, u.firstname
";

$params = [
    'courseid_progress'      => $courseid,
    'courseid_scormstarted'  => $courseid,
    'courseid_completed'     => $courseid,
    'courseid_inprogress'    => $courseid,
    'courseid_startedstatus' => $courseid,
    'courseid_main'          => $courseid,
];

$userrows = array_values($DB->get_records_sql($sql, $params));

// Transform, filter, sort
$rows = [];
foreach ($userrows as $r) {
    $fullname = fullname($r);
    $statuslbl = (string)$r->completion_status;
    $progress  = (int)round($r->progress_percent ?? 0);

    $rows[] = [
        'id'               => (int)$r->id,
        'fullname'         => $fullname,
        'email'            => $r->email,
        'profileurl'       => (new moodle_url('/user/profile.php', ['id' => $r->id]))->out(false),
        'progress_percent' => $progress,
        'completion_status' => $statuslbl,
        'points_earned'    => (int)$r->points_earned,
        'max_points'       => (int)$r->max_points,
        'lastaccess_human' => $r->lastaccess ? userdate($r->lastaccess) : get_string('never')
    ];
}

// Apply search filter (by name/email)
if ($userq !== '') {
    $needle = core_text::strtolower($userq);
    $rows = array_values(array_filter($rows, function ($x) use ($needle) {
        return (core_text::strpos(core_text::strtolower($x['fullname']), $needle) !== false) ||
            (core_text::strpos(core_text::strtolower($x['email']), $needle) !== false);
    }));
}

// Apply status filter
if (in_array($status, ['completed', 'inprogress', 'notstarted'], true)) {
    $want = [
        'completed'  => 'Completed',
        'inprogress' => 'In Progress',
        'notstarted' => 'Not Started'
    ][$status];
    $rows = array_values(array_filter($rows, fn($x) => $x['completion_status'] === $want));
}

// Sort
$cmp = null;
switch ($sortby) {
    case 'progress':
        $cmp = fn($a, $b) => $a['progress_percent'] <=> $b['progress_percent'];
        break;
    case 'points':
        // sort by earned/max ratio then earned
        $cmp = function ($a, $b) {
            $ra = $a['max_points'] ? ($a['points_earned'] / $a['max_points']) : 0;
            $rb = $b['max_points'] ? ($b['points_earned'] / $b['max_points']) : 0;
            if ($ra === $rb) return $a['points_earned'] <=> $b['points_earned'];
            return $ra <=> $rb;
        };
        break;
    default: // name
        $cmp = fn($a, $b) => strcmp($a['fullname'], $b['fullname']);
}
usort($rows, function ($a, $b) use ($cmp, $dir) {
    $res = $cmp($a, $b);
    return ($dir === 'DESC') ? -$res : $res;
});

// Stats
$enrolled   = count($rows);
$completed  = count(array_filter($rows, fn($x) => $x['completion_status'] === 'Completed'));
$inprogress = count(array_filter($rows, fn($x) => $x['completion_status'] === 'In Progress'));
$notstarted = count(array_filter($rows, fn($x) => $x['completion_status'] === 'Not Started'));
$avgprogress = ($enrolled > 0) ? (int)round(array_sum(array_column($rows, 'progress_percent')) / $enrolled) : 0;

// CSV export (keeps current filters)
if ($download === 'csv') {
    // Close session so the download isn't blocked
    \core\session\manager::write_close();

    // Safe filename
    $filename = clean_filename('course_report_' .
        format_string($course->shortname) . '_' . date('Y-m-d'));

    $csv = new csv_export_writer();
    $csv->set_filename($filename);

    // Header row
    $csv->add_data([
        'Course',
        'Category',
        'User',
        'Email',
        'Status',
        'Progress_%',
        'Points_Earned',
        'Max_Points',
        'Last_Access'
    ]);

    // Data rows
    foreach ($rows as $r) {
        $csv->add_data([
            format_string($course->fullname),
            format_string($categoryname),
            $r['fullname'],
            $r['email'],
            $r['completion_status'],
            $r['progress_percent'],
            $r['points_earned'],
            $r['max_points'],
            $r['lastaccess_human'],
        ]);
    }

    // Sends headers + file and exits
    $csv->download_file();
    exit;
}
// Build template data
$data = [
    'date' => userdate(time(), get_string('strftimedate', 'langconfig')),
    'course' => [
        'id'            => $course->id,
        'fullname'      => format_string($course->fullname),
        'shortname'     => format_string($course->shortname),
        'categoryname'  => format_string($categoryname),
        'imageurl'      => $courseimageurl,
        'startdate'     => $course->startdate ? userdate($course->startdate) : null
    ],
    'stats' => [
        'enrolled'     => $enrolled,
        'completed'    => $completed,
        'inprogress'   => $inprogress,
        'notstarted'   => $notstarted,
        'avgprogress'  => $avgprogress,
        'totalmodules' => $totalmodules
    ],
    'filters' => [
        'userq'  => s($userq),

        // explicit booleans so mustache can mark the selected option cleanly
        'status_is_all'        => ($status === 'all'),
        'status_is_completed'  => ($status === 'completed'),
        'status_is_inprogress' => ($status === 'inprogress'),
        'status_is_notstarted' => ($status === 'notstarted'),

        'sortby' => [
            'isname'     => ($sortby === 'name'),
            'isprogress' => ($sortby === 'progress'),
            'ispoints'   => ($sortby === 'points'),
        ],
        'dir' => [
            'value'  => ($dir === 'DESC') ? 'desc' : 'asc',
            'isdesc' => ($dir === 'DESC'),
        ],
    ],

    'users' => array_map(function ($r) {
        // mustache-friendly fields
        return [
            'id'               => $r['id'],
            'fullname'         => $r['fullname'],
            'email'            => $r['email'],
            'profileurl'       => $r['profileurl'],
            'progress_percent' => $r['progress_percent'],
            'completion_status' => $r['completion_status'],
            'badgeCompleted'   => ($r['completion_status'] === 'Completed'),
            'badgeInProgress'  => ($r['completion_status'] === 'In Progress'),
            'badgeNotStarted'  => ($r['completion_status'] === 'Not Started'),
            'points_earned'    => $r['points_earned'],
            'max_points'       => $r['max_points'],
            'lastaccess_human' => $r['lastaccess_human']
        ];
    }, $rows)
];

// Export URL with current filters
$exporturl = new moodle_url('/my/course_report.php', [
    'id'     => $courseid,
    'userq'  => ($userq !== '') ? $userq : null,
    'status' => ($status !== 'all') ? $status : null,
    'sortby' => $sortby,
    'dir'    => strtolower($dir),
    'download' => 'csv'
]);
$data['exportUrl'] = $exporturl->out(false);
$data['hasusers'] = !empty($rows);

// Render
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_academi/course_report', $data);
echo $OUTPUT->footer();
