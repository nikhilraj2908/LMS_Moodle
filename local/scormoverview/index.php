<?php
// local/scormoverview/index.php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php'); // for COMPLETION_* constants

// -----------------------------------------------------------------------------
// Params
// -----------------------------------------------------------------------------
$cmid     = required_param('cmid', PARAM_INT);
$scormid  = optional_param('scormid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Filters
$q        = optional_param('q', '', PARAM_RAW_TRIMMED);
$statusf  = optional_param('status', 'all', PARAM_ALPHA);  // all|completed|inprogress|notstarted
$sort     = optional_param('sort', 'nameaz', PARAM_ALPHA); // nameaz|nameza|last
$download = optional_param('download', '', PARAM_ALPHA);   // csv

// Allow calling with cmid only (scormid/courseid are resolved).
$cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
$courseid = $courseid ?: $cm->course;
$scormid  = $scormid  ?: $cm->instance;

$course  = get_course($courseid);
$context = context_module::instance($cmid);
$coursecontext = context_course::instance($courseid);

require_login($course, false, $cm);

// Allow users who can view SCORM reports OR who can create courses (creator/manager-ish).
if (!has_capability('mod/scorm:viewreport', $context)
    && !has_capability('moodle/course:create', context_system::instance())) {
    throw new required_capability_exception($context, 'mod/scorm:viewreport', 'nopermissions', '');
}

$PAGE->set_url(new moodle_url('/local/scormoverview/index.php', [
    'cmid' => $cmid, 'scormid' => $scormid, 'courseid' => $courseid
]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_scormoverview', 'SCORM Overview'));
$PAGE->set_heading(format_string($course->fullname));

// -----------------------------------------------------------------------------
// Enrolled users (pull from the COURSE context so the page isn't empty)
// -----------------------------------------------------------------------------
$enrolled = get_enrolled_users($coursecontext, '', 0, 'u.id,u.firstname,u.lastname,u.email');
$enrolled = $enrolled ? $enrolled : [];
$userids  = array_map(fn($u) => (int)$u->id, $enrolled);
if (empty($userids)) { $userids = [0]; } // keep SQL safe
foreach ($enrolled as $u) { foreach (['firstnamephonetic','lastnamephonetic','middlename','alternatename'] as $nf) { if (!property_exists($u, $nf)) { $u->$nf = ''; } } }

// -----------------------------------------------------------------------------
// Native SCORM tracks (status, score, attempts, total_time, lastaccess)
// -----------------------------------------------------------------------------
global $DB;
$inplaceholders = implode(',', array_fill(0, count($userids), '?'));
$tracksql = "
    SELECT
      sst.userid,
      MAX(CASE WHEN sst.element IN ('cmi.core.lesson_status','cmi.completion_status')
               THEN sst.value END) AS status,
      MAX(CASE WHEN sst.element IN ('cmi.core.score.raw','cmi.score.raw')
               THEN sst.value END) AS scoreraw,
      MAX(sst.attempt)               AS attempts,
      MAX(sst.timemodified)          AS lastaccess,
      SUM(
        CASE WHEN sst.element IN ('cmi.core.total_time','cmi.total_time') THEN
             (COALESCE(NULLIF(SUBSTRING_INDEX(SUBSTRING_INDEX(sst.value,':',1),':',-1),''),'0')+0)*3600 +
             (COALESCE(NULLIF(SUBSTRING_INDEX(SUBSTRING_INDEX(sst.value,':',2),':',-1),''),'0')+0)*60 +
             (COALESCE(NULLIF(SUBSTRING_INDEX(sst.value,':',-1),''),'0')+0)
        ELSE 0 END
      ) AS totalsecs
    FROM {scorm_scoes_track} sst
    WHERE sst.scormid = ?
      AND sst.userid IN ($inplaceholders)
    GROUP BY sst.userid";
$trackparams = array_merge([$scormid], $userids);
/** @var array<int,stdClass> $track */
$track = $DB->get_records_sql($tracksql, $trackparams); // keyed by userid

// -----------------------------------------------------------------------------
// Fallback attempts/time from your custom table scorm_session_time
// -----------------------------------------------------------------------------
$stsql = "SELECT userid, SUM(duration) AS totaltime, MAX(counter) AS attemptcount
          FROM {scorm_session_time}
          WHERE scormid = ?
            AND userid IN ($inplaceholders)
          GROUP BY userid";
$stparams = array_merge([$scormid], $userids);
/** @var array<int,stdClass> $session */
$session = $DB->get_records_sql($stsql, $stparams); // keyed by userid

// -----------------------------------------------------------------------------
// Activity completion (status fallback)
// -----------------------------------------------------------------------------
$cmc = $DB->get_records('course_modules_completion',
    ['coursemoduleid' => $cmid], 'userid', 'userid, completionstate'); // keyed by userid

// -----------------------------------------------------------------------------
// Gradebook (score fallback)
// -----------------------------------------------------------------------------
$gsql = "SELECT gg.userid, gg.finalgrade, gi.grademax
         FROM {grade_items} gi
         JOIN {grade_grades} gg ON gg.itemid = gi.id
         WHERE gi.itemtype='mod' AND gi.itemmodule='scorm' AND gi.iteminstance=:scormid";
$gradebyuser = $DB->get_records_sql($gsql, ['scormid' => $scormid]); // keyed by userid

// -----------------------------------------------------------------------------
// Build rows (merge all sources)
// -----------------------------------------------------------------------------
$rows = [];
foreach ($enrolled as $u) {
    $uid = (int)$u->id;
    $t = $track[$uid] ?? null;

    // STATUS: prefer native; else completion; else not attempted.
    $status = null;
    if ($t && !empty($t->status)) {
        $status = core_text::strtolower($t->status);
    } else if (isset($cmc[$uid])) {
        $state = (int)$cmc[$uid]->completionstate; // 0=not, 1=complete, 2=complete pass, 3=complete fail
        if ($state === COMPLETION_COMPLETE || $state === COMPLETION_COMPLETE_PASS) {
            $status = 'completed';
        } else if ($state === COMPLETION_COMPLETE_FAIL) {
            $status = 'failed';
        }
    }
    $status = $status ?? 'not attempted';

    // SCORE: prefer native scoreraw; else gradebook percent.
    $score = null;
    if ($t && $t->scoreraw !== null && $t->scoreraw !== '') {
        $score = round((float)$t->scoreraw, 2);
    } else if (isset($gradebyuser[$uid]) && $gradebyuser[$uid]->finalgrade !== null) {
        $raw = (float)$gradebyuser[$uid]->finalgrade;
        $max = (float)($gradebyuser[$uid]->grademax ?? 100);
        $score = ($max > 0) ? round(($raw / $max) * 100, 2) : round($raw, 2);
    }

    // ATTEMPTS/TIME: prefer native; else scorm_session_time fallback.
    $attempts  = $t ? (int)$t->attempts : 0;
    $totalsecs = $t ? (int)$t->totalsecs : 0;
    if (isset($session[$uid])) {
        $attempts  = $attempts  ?: (int)$session[$uid]->attemptcount;
        $totalsecs = $totalsecs ?: (int)$session[$uid]->totaltime;
    }

    // LAST ACCESS from native.
    $last = $t && !empty($t->lastaccess) ? (int)$t->lastaccess : 0;

    $rows[] = [
        'userid'    => $uid,
        'fullname'  => fullname($u),
        'email'     => $u->email,
        'status'    => $status,
        'score'     => $score,                 // percent or null
        'attempts'  => $attempts,
        'totalsecs' => $totalsecs,
        'last'      => $last,
    ];
}

// -----------------------------------------------------------------------------
// Filters (search/status), Sort, KPIs
// -----------------------------------------------------------------------------
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
        default:       return strcasecmp($a['fullname'],$b['fullname']); // nameaz
    }
});

$total      = count($enrolled);
$completed  = count(array_filter($rows, fn($x)=> in_array($x['status'], ['completed','passed'])));
$inprog     = count(array_filter($rows, fn($x)=> in_array($x['status'], ['incomplete','failed','browsed'])));
$notstarted = $total - ($completed + $inprog);
$completionpct = $total ? round(($completed/$total)*100) : 0;

// -----------------------------------------------------------------------------
// CSV export
// -----------------------------------------------------------------------------
if ($download === 'csv') {
    $filename = 'scorm_overview_cm'.$cmid.'_'.date('Ymd_His').'.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Learner','Email','Status','Score','Attempts','Total time','Last access']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['fullname'],
            $r['email'],
            $r['status'],
            $r['score'] === null ? '-' : $r['score'].'%',
            $r['attempts'],
            gmdate('H:i:s', $r['totalsecs']),
            $r['last'] ? userdate($r['last']) : '-'
        ]);
    }
    fclose($out);
    exit;
}

// -----------------------------------------------------------------------------
// Render
// -----------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading('SCORM Overview — '.format_string($cm->name));

// Simple styles for dashboard look
echo html_writer::tag('style', "
.scorm-kpis{display:grid;grid-template-columns:repeat(5,minmax(160px,1fr));gap:16px;margin:8px 0 16px}
.scorm-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.scorm-card .num{font-size:28px;font-weight:700;margin-top:8px}
.scorm-filters{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:8px 0 16px}
.scorm-actions{display:flex;gap:12px}
@media(max-width:900px){.scorm-kpis{grid-template-columns:repeat(2,minmax(160px,1fr));}}
");

// KPI cards
echo html_writer::start_div('scorm-kpis');
foreach ([['Enrolled',$total],['Completed',$completed],['In Progress',$inprog],['Not Started',$notstarted],['Completion',$completionpct.'%']] as $k){
    echo html_writer::start_div('scorm-card');
    echo html_writer::tag('div', $k[0]);
    echo html_writer::tag('div', $k[1], ['class'=>'num']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

// Filters
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

// Table
$table = new html_table();
$table->head = ['Learner','Email','Status','Score','Attempts','Total time','Last access'];
foreach ($rows as $r) {
    $table->data[] = [
        html_writer::link(new moodle_url('/user/view.php', ['id'=>$r['userid'],'course'=>$courseid]), format_string($r['fullname'])),
        s($r['email']),
        s($r['status']),
        $r['score']===null ? '-' : $r['score'].' %',
        $r['attempts'],
        gmdate('H:i:s', (int)$r['totalsecs']),
        $r['last'] ? userdate($r['last']) : '-'
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
