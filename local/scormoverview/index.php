<?php
// local/scormoverview/index.php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$cmid     = required_param('cmid', PARAM_INT);
$scormid  = optional_param('scormid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Filters
$q        = optional_param('q', '', PARAM_RAW_TRIMMED);
$statusf  = optional_param('status', 'all', PARAM_ALPHA);
$sort     = optional_param('sort', 'nameaz', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

// Resolve cm / course / scorm
$cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
$courseid = $courseid ?: $cm->course;
$scormid  = $scormid  ?: $cm->instance;

$course  = get_course($courseid);
$context = context_module::instance($cmid);
$coursecontext = context_course::instance($courseid);

require_login($course, false, $cm);

// Capability
if (!has_capability('mod/scorm:viewreport', $context)
    && !has_capability('moodle/course:create', context_system::instance())) {
    throw new required_capability_exception($context, 'mod/scorm:viewreport', 'nopermissions', '');
}

$PAGE->set_url(new moodle_url('/local/scormoverview/index.php', [
    'cmid'=>$cmid,'scormid'=>$scormid,'courseid'=>$courseid
]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_scormoverview', 'SCORM Overview'));
$PAGE->set_heading(format_string($course->fullname));

// (Optional) turn on SQL debug temporarily if you still hit an error
// $DB->set_debug(true);

// -----------------------------------------------------------------------------
// Enrolled users
// -----------------------------------------------------------------------------
$enrolled = get_enrolled_users($coursecontext, '', 0,
    'u.id,u.firstname,u.lastname,u.email,u.firstnamephonetic,u.lastnamephonetic,u.middlename,u.alternatename');

$enrolled = $enrolled ? $enrolled : [];
$userids  = array_map(fn($u)=>(int)$u->id, $enrolled);
if (empty($userids)) { $userids = [0]; }
foreach ($enrolled as $u) {
    foreach (['firstnamephonetic','lastnamephonetic','middlename','alternatename'] as $nf) {
        if (!property_exists($u, $nf)) { $u->$nf = ''; }
    }
}

global $DB;
$inplaceholders = implode(',', array_fill(0, count($userids), '?'));

// -----------------------------------------------------------------------------
// Tracks (status/score/attempts/first/last/time) – safe for ONLY_FULL_GROUP_BY
// -----------------------------------------------------------------------------
$track = [];
try {
    $tracksql = "
      SELECT
        t.userid,
        MAX(t.status)   AS status,
        MAX(t.scoreraw) AS scoreraw,
        MAX(t.attempts) AS attempts,
        MIN(t.tmod)     AS firstaccess,
        MAX(t.tmod)     AS lastaccess,
        SUM(t.seconds)  AS totalsecs
      FROM (
        SELECT
          sst.userid,
          sst.timemodified AS tmod,
          CASE WHEN sst.element IN ('cmi.core.lesson_status','cmi.completion_status') THEN sst.value ELSE NULL END AS status,
          CASE WHEN sst.element IN ('cmi.core.score.raw','cmi.score.raw') THEN sst.value ELSE NULL END AS scoreraw,
          sst.attempt AS attempts,
          CASE WHEN sst.element IN ('cmi.core.total_time','cmi.total_time') THEN
              (COALESCE(NULLIF(SUBSTRING_INDEX(SUBSTRING_INDEX(sst.value,':',1),':',-1),''),'0')+0)*3600 +
              (COALESCE(NULLIF(SUBSTRING_INDEX(SUBSTRING_INDEX(sst.value,':',2),':',-1),''),'0')+0)*60 +
              (COALESCE(NULLIF(SUBSTRING_INDEX(sst.value,':',-1),''),'0')+0)
           ELSE 0 END AS seconds
        FROM {scorm_scoes_track} sst
        WHERE sst.scormid = ? AND sst.userid IN ($inplaceholders)
      ) t
      GROUP BY t.userid";
    $trackparams = array_merge([$scormid], $userids);
    $track = $DB->get_records_sql($tracksql, $trackparams); // keyed by userid
} catch (dml_exception $e) {
    debugging('Track query failed: '.$e->getMessage(), DEBUG_DEVELOPER);
    $track = [];
}

// -----------------------------------------------------------------------------
// Fallback from custom scorm_session_time (guard if table not present)
// -----------------------------------------------------------------------------
$session = [];
try {
    require_once($CFG->libdir . '/ddllib.php');
    $dbman = $DB->get_manager();
    $table = new xmldb_table('scorm_session_time');

    if ($dbman->table_exists($table)) {
        $stsql = "SELECT
                    userid,
                    MIN(starttime) AS firststart,
                    MAX(endtime)   AS lastend,
                    SUM(duration)  AS totaltime,
                    MAX(counter)   AS attemptcount
                  FROM {scorm_session_time}
                  WHERE scormid = ? AND userid IN ($inplaceholders)
                  GROUP BY userid";
        $stparams = array_merge([$scormid], $userids);
        $session  = $DB->get_records_sql($stsql, $stparams); // keyed by userid
    } else {
        $session = [];
    }
} catch (dml_exception $e) {
    debugging('Session_time query failed: '.$e->getMessage(), DEBUG_DEVELOPER);
    $session = [];
}

// -----------------------------------------------------------------------------
// Completion & gradebook
// -----------------------------------------------------------------------------
$cmc = $DB->get_records('course_modules_completion',
    ['coursemoduleid' => $cmid], 'userid', 'userid,completionstate');

$gradebyuser = [];
try {
    $gsql = "SELECT gg.userid, gg.finalgrade, gi.grademax
             FROM {grade_items} gi
             JOIN {grade_grades} gg ON gg.itemid = gi.id
             WHERE gi.itemtype='mod' AND gi.itemmodule='scorm' AND gi.iteminstance=:scormid";
    $gradebyuser = $DB->get_records_sql($gsql, ['scormid' => $scormid]); // keyed by userid
} catch (dml_exception $e) {
    debugging('Grade query failed: '.$e->getMessage(), DEBUG_DEVELOPER);
    $gradebyuser = [];
}

// -----------------------------------------------------------------------------
// Build rows
// -----------------------------------------------------------------------------
$rows = [];
foreach ($enrolled as $u) {
    $uid = (int)$u->id;
    $t = $track[$uid] ?? null;

    // Status
    $status = null;
    if ($t && !empty($t->status)) {
        $status = core_text::strtolower($t->status);
    } else if (isset($cmc[$uid])) {
        $state = (int)$cmc[$uid]->completionstate; // 0,1,2,3
        if ($state === COMPLETION_COMPLETE || $state === COMPLETION_COMPLETE_PASS) $status = 'completed';
        else if ($state === COMPLETION_COMPLETE_FAIL) $status = 'failed';
    }
    $status = $status ?? 'not attempted';

    // Score
    $score = null;
    if ($t && $t->scoreraw !== null && $t->scoreraw !== '') {
        $score = round((float)$t->scoreraw, 2);
    } else if (isset($gradebyuser[$uid]) && $gradebyuser[$uid]->finalgrade !== null) {
        $raw = (float)$gradebyuser[$uid]->finalgrade;
        $max = (float)($gradebyuser[$uid]->grademax ?? 100);
        $score = ($max > 0) ? round(($raw / $max) * 100, 2) : round($raw, 2);
    }

    // Attempts / time
    $attempts  = $t ? (int)$t->attempts : 0;
    $totalsecs = $t ? (int)$t->totalsecs : 0;

    // First/last access from tracks
    $first = $t && !empty($t->firstaccess) ? (int)$t->firstaccess : 0;
    $last  = $t && !empty($t->lastaccess)  ? (int)$t->lastaccess  : 0;

    // Fallback from session_time
    if (isset($session[$uid])) {
        $attempts  = $attempts  ?: (int)$session[$uid]->attemptcount;
        $totalsecs = $totalsecs ?: (int)$session[$uid]->totaltime;
        $first     = $first     ?: (int)($session[$uid]->firststart ?? 0);
        $last      = $last      ?: (int)($session[$uid]->lastend    ?? 0);
    }

    $rows[] = [
        'userid'    => $uid,
        'fullname'  => fullname($u),
        'email'     => $u->email,
        'status'    => $status,
        'score'     => $score,
        'attempts'  => $attempts,
        'totalsecs' => $totalsecs,
        'first'     => $first,
        'last'      => $last,
    ];
}

// Filters
if ($q !== '') {
    $needle = core_text::strtolower($q);
    $rows = array_values(array_filter($rows, function($x) use ($needle){
        return (strpos(core_text::strtolower($x['fullname']), $needle) !== false)
            || (strpos(core_text::strtolower($x['email']), $needle) !== false);
    }));
}
if ($statusf !== 'all') {
    $rows = array_values(array_filter($rows, function($x) use ($statusf){
        if ($statusf === 'notstarted') return $x['status'] === 'not attempted';
        if ($statusf === 'completed')  return in_array($x['status'], ['completed','passed']);
        if ($statusf === 'inprogress') return in_array($x['status'], ['incomplete','failed','browsed']);
        return true;
    }));
}

usort($rows, function($a,$b) use ($sort){
    switch ($sort) {
        case 'nameza': return strcasecmp($b['fullname'],$a['fullname']);
        case 'last':   return ($b['last'] <=> $a['last']);
        default:       return strcasecmp($a['fullname'],$b['fullname']);
    }
});

// KPIs
$total      = count($enrolled);
$completed  = count(array_filter($rows, fn($x)=> in_array($x['status'], ['completed','passed'])));
$inprog     = count(array_filter($rows, fn($x)=> in_array($x['status'], ['incomplete','failed','browsed'])));
$notstarted = $total - ($completed + $inprog);
$completionpct = $total ? round(($completed/$total)*100) : 0;

// CSV
if ($download === 'csv') {
    $filename = 'scorm_overview_cm'.$cmid.'_'.date('Ymd_His').'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Learner','Email','Status','Score','Attempts','Total time','First access','Last access']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['fullname'],
            $r['email'],
            $r['status'],
            $r['score'] === null ? '-' : $r['score'].'%',
            $r['attempts'],
            gmdate('H:i:s', (int)$r['totalsecs']),
            $r['first'] ? userdate($r['first']) : '-',
            $r['last']  ? userdate($r['last'])  : '-',
        ]);
    }
    fclose($out);
    exit;
}

// Render
echo $OUTPUT->header();
echo $OUTPUT->heading('SCORM Overview — '.format_string($cm->name));

echo html_writer::tag('style', "
.scorm-kpis{display:grid;grid-template-columns:repeat(5,minmax(160px,1fr));gap:16px;margin:8px 0 16px}
.scorm-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.scorm-card .num{font-size:28px;font-weight:700;margin-top:8px}
.scorm-filters{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:8px 0 16px}
.scorm-actions{display:flex;gap:12px}
@media(max-width:900px){.scorm-kpis{grid-template-columns:repeat(2,minmax(160px,1fr));}}
");

echo html_writer::start_div('scorm-kpis');
foreach ([['Enrolled',$total],['Completed',$completed],['In Progress',$inprog],['Not Started',$notstarted],['Completion',$completionpct.'%']] as $k){
    echo html_writer::start_div('scorm-card');
    echo html_writer::tag('div', $k[0]);
    echo html_writer::tag('div', $k[1], ['class'=>'num']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

$formurl = new moodle_url('/local/scormoverview/index.php', ['cmid'=>$cmid,'scormid'=>$scormid,'courseid'=>$courseid]);
echo html_writer::start_div('scorm-filters');
echo html_writer::start_tag('form', ['method'=>'get','action'=>$formurl]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'cmid','value'=>$cmid]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'scormid','value'=>$scormid]);
echo html_writer::empty_tag('input', ['type'=>'hidden','name'=>'courseid','value'=>$courseid]);

echo html_writer::start_div('row g-2 align-items-end');
echo html_writer::start_div('col-md-5');
echo html_writer::tag('label','Search user',['class'=>'form-label']);
echo html_writer::empty_tag('input', ['class'=>'form-control','type'=>'text','name'=>'q','value'=>$q,'placeholder'=>'Type name or email…']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::tag('label','Status',['class'=>'form-label']);
echo html_writer::select(['all'=>'All statuses','completed'=>'Completed/Passed','inprogress'=>'In progress/Failed/Browsed','notstarted'=>'Not started'], 'status', $statusf, null, ['class'=>'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2');
echo html_writer::tag('label','Sort by',['class'=>'form-label']);
echo html_writer::select(['nameaz'=>'Name (A–Z)','nameza'=>'Name (Z–A)','last'=>'Last access'], 'sort', $sort, null, ['class'=>'form-select']);
echo html_writer::end_div();

echo html_writer::start_div('col-md-2 scorm-actions');
echo html_writer::empty_tag('input', ['type'=>'submit','class'=>'btn btn-primary w-100','value'=>'Apply']);
$csvurl = new moodle_url($formurl, ['q'=>$q,'status'=>$statusf,'sort'=>$sort,'download'=>'csv']);
echo html_writer::link($csvurl, 'Export CSV', ['class'=>'btn btn-danger w-100']);
echo html_writer::end_div();

echo html_writer::end_div(); // row
echo html_writer::end_tag('form');
echo html_writer::end_div(); // filters

$table = new html_table();
$table->head = ['Learner','Email','Status','Score','Attempts','Total time','First access','Last access'];
foreach ($rows as $r) {
    $table->data[] = [
        html_writer::link(new moodle_url('/user/view.php', ['id'=>$r['userid'],'course'=>$courseid]), format_string($r['fullname'])),
        s($r['email']),
        s($r['status']),
        $r['score']===null ? '-' : $r['score'].' %',
        $r['attempts'],
        gmdate('H:i:s', (int)$r['totalsecs']),
        $r['first'] ? userdate($r['first']) : '-',
        $r['last']  ? userdate($r['last'])  : '-',
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
