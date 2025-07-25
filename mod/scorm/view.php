<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/scorm/lib.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->libdir.'/ddllib.php'); // Needed for XMLDB
$dbman = $DB->get_manager();

$table = new xmldb_table('scorm_session_time');
if (!$dbman->table_exists($table)) {
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('scormid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('counter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 1); // new field
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $dbman->create_table($table);
}

$id = optional_param('id', '', PARAM_INT);       // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);         // scorm ID
$organization = optional_param('organization', '', PARAM_INT); // organization ID.
$action = optional_param('action', '', PARAM_ALPHA);
$preventskip = optional_param('preventskip', '', PARAM_INT); // Prevent Skip view, set by javascript redirects.

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('scorm', $id, 0, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $scorm = $DB->get_record("scorm", array("id" => $cm->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else if (!empty($a)) {
    if (! $scorm = $DB->get_record("scorm", array("id" => $a))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $scorm->course))) {
        throw new \moodle_exception('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("scorm", $scorm->id, $course->id, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
} else {
    throw new \moodle_exception('missingparameter');
}

$url = new moodle_url('/mod/scorm/view.php', array('id' => $cm->id));
if ($organization !== '') {
    $url->param('organization', $organization);
}
$PAGE->set_url($url);
$forcejs = get_config('scorm', 'forcejavascript');
if (!empty($forcejs)) {
    $PAGE->add_body_class('forcejavascript');
}

require_login($course, false, $cm);

$context = context_course::instance($course->id);
$contextmodule = context_module::instance($cm->id);

$launch = false; // Does this automatically trigger a launch based on skipview.
if (!empty($scorm->popup)) {
    $scoid = 0;
    $orgidentifier = '';

    $result = scorm_get_toc($USER, $scorm, $cm->id, TOCFULLURL);
    // Set last incomplete sco to launch first.
    if (!empty($result->sco->id)) {
        $sco = $result->sco;
    } else {
        $sco = scorm_get_sco($scorm->launch, SCO_ONLY);
    }
    if (!empty($sco)) {
        $scoid = $sco->id;
        if (($sco->organization == '') && ($sco->launch == '')) {
            $orgidentifier = $sco->identifier;
        } else {
            $orgidentifier = $sco->organization;
        }
    }

    if (empty($preventskip) && $scorm->skipview >= SCORM_SKIPVIEW_FIRST &&
        has_capability('mod/scorm:skipview', $contextmodule) &&
        !has_capability('mod/scorm:viewreport', $contextmodule)) { // Don't skip users with the capability to view reports.

        // Do we launch immediately and redirect the parent back ?
        if ($scorm->skipview == SCORM_SKIPVIEW_ALWAYS || !scorm_has_tracks($scorm->id, $USER->id)) {
            $launch = true;
        }
    }
    // Redirect back to the section with one section per page ?

    $courseformat = course_get_format($course)->get_course();
    if ($courseformat->format == 'singleactivity') {
        $courseurl = $url->out(false, array('preventskip' => '1'));
    } else {
        $courseurl = course_get_url($course, $cm->sectionnum)->out(false);
    }
    $PAGE->requires->data_for_js('scormplayerdata', Array('launch' => $launch,
                                                           'currentorg' => $orgidentifier,
                                                           'sco' => $scoid,
                                                           'scorm' => $scorm->id,
                                                           'courseurl' => $courseurl,
                                                           'cwidth' => $scorm->width,
                                                           'cheight' => $scorm->height,
                                                           'popupoptions' => $scorm->options), true);
    $PAGE->requires->string_for_js('popupsblocked', 'scorm');
    $PAGE->requires->string_for_js('popuplaunched', 'scorm');
    $PAGE->requires->js('/mod/scorm/view.js', true);
}

if (isset($SESSION->scorm)) {
    unset($SESSION->scorm);
}

$strscorms = get_string("modulenameplural", "scorm");
$strscorm  = get_string("modulename", "scorm");

$shortname = format_string($course->shortname, true, array('context' => $context));
$pagetitle = strip_tags($shortname.': '.format_string($scorm->name));

// Trigger module viewed event.
scorm_view($scorm, $course, $cm, $contextmodule);


if (empty($preventskip) && empty($launch) && (has_capability('mod/scorm:skipview', $contextmodule))) {
    scorm_simple_play($scorm, $USER, $contextmodule, $cm->id);
}

// Print the page header.

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
// Let the module handle the display.
if (!empty($action) && $action == 'delete' && confirm_sesskey() && has_capability('mod/scorm:deleteownresponses', $contextmodule)) {
    $PAGE->activityheader->disable();
} else {
    $PAGE->activityheader->set_description('');
}

echo $OUTPUT->header();
if (!empty($action) && confirm_sesskey() && has_capability('mod/scorm:deleteownresponses', $contextmodule)) {
    if ($action == 'delete') {
        $confirmurl = new moodle_url($PAGE->url, array('action' => 'deleteconfirm'));
        echo $OUTPUT->confirm(get_string('deleteuserattemptcheck', 'scorm'), $confirmurl, $PAGE->url);
        echo $OUTPUT->footer();
        exit;
    } else if ($action == 'deleteconfirm') {
        // Delete this users attempts.
        scorm_delete_tracks($scorm->id, null, $USER->id);
        scorm_update_grades($scorm, $USER->id, true);
        echo $OUTPUT->notification(get_string('scormresponsedeleted', 'scorm'), 'notifysuccess');
    }
}

// Print the main part of the page.
$attemptstatus = '';
if (empty($launch) && ($scorm->displayattemptstatus == SCORM_DISPLAY_ATTEMPTSTATUS_ALL ||
         $scorm->displayattemptstatus == SCORM_DISPLAY_ATTEMPTSTATUS_ENTRY)) {
    $attemptstatus = scorm_get_attempt_status($USER, $scorm, $cm);
}
echo $OUTPUT->box(format_module_intro('scorm', $scorm, $cm->id), '', 'intro');

// Check if SCORM available. No need to display warnings because activity dates are displayed at the top of the page.
list($available, $warnings) = scorm_get_availability_status($scorm);

if ($available && empty($launch)) {
    scorm_print_launch($USER, $scorm, 'view.php?id='.$cm->id, $cm);
}

echo $OUTPUT->box($attemptstatus);
$attempt = scorm_get_last_attempt($scorm->id, $USER->id);
$trackdata = scorm_get_tracks($scorm->id, $USER->id, $attempt);

// fallback
$completion = 'incomplete';
$success = 'unknown';
$score = '0';
$time = '0s';

// check SCORM tracking
if (!empty($trackdata)) {
    if (!empty($trackdata->completion_status)) {
    $completion = $trackdata->completion_status;
} elseif (!empty($trackdata->lesson_status)) {
    $completion = $trackdata->lesson_status;
}

if (!empty($trackdata->success_status)) {
    $success = $trackdata->success_status;
} elseif (!empty($trackdata->lesson_status)) {
    if ($trackdata->lesson_status === 'passed') {
        $success = 'passed';
    } elseif ($trackdata->lesson_status === 'failed') {
        $success = 'failed';
    }
}


    if (!empty($trackdata->score_raw)) {
        $score = $trackdata->score_raw;
    }
    if (!empty($trackdata->total_time)) {
        $time = $trackdata->total_time;
    }
}

// fallback from module completion
$completioninfo = new completion_info($course);
$completionstate = $completioninfo->get_data($cm, false);
if ($completionstate->completionstate == COMPLETION_COMPLETE) {
    $completion = 'completed';
}

// fallback to Moodle gradebook for score if needed
$gradeitem = grade_get_grades($course->id, 'mod', 'scorm', $scorm->id, $USER->id);
if ($gradeitem && !empty($gradeitem->items[0]->grades)) {
    $usergrade = reset($gradeitem->items[0]->grades);
    if (isset($usergrade->grade)) {
        $score = round($usergrade->grade, 2);
    }
}

$timesql = "SELECT SUM(duration) as totaltime, MAX(counter) as attemptcount
            FROM {scorm_session_time}
            WHERE userid = :userid AND scormid = :scormid";

$params = ['userid' => $USER->id, 'scormid' => $scorm->id];
$scormdata = $DB->get_record_sql($timesql, $params);

$totalduration = $scormdata->totaltime ?? 0;
$attemptcount = $scormdata->attemptcount ?? 0;

// Convert total seconds to H:i:s format
$time = gmdate("H:i:s", $totalduration);
// show status in Bootstrap cards

echo '<div class="row mt-4">';

echo '  <div class="col-md-3">';
echo '    <div class="card p-3 text-center">';
echo '      <h5>Completion</h5>';
echo '      <span class="fw-bold text-' . ($completion === 'completed' ? 'success' : 'danger') . '">' . htmlspecialchars($completion) . '</span>';
echo '    </div>';
echo '  </div>';

echo '  <div class="col-md-3">';
echo '    <div class="card p-3 text-center">';
echo '      <h5>All Attempts</h5>';
echo '      <span class="fw-bold text-primary">' . htmlspecialchars($attemptcount) . '</span>';
echo '    </div>';
echo '  </div>';

echo '  <div class="col-md-3">';
echo '    <div class="card p-3 text-center">';
echo '      <h5>Score</h5>';
echo '      <span class="fw-bold text-primary">' . htmlspecialchars($score) . ' %</span>';
echo '    </div>';
echo '  </div>';

echo '  <div class="col-md-3">';
echo '    <div class="card p-3 text-center">';
echo '      <h5>Total Time</h5>';
echo '      <span class="fw-bold text-primary">' . htmlspecialchars($time) . '</span>';
echo '    </div>';
echo '  </div>';

echo '</div>';



if (!empty($forcejs)) {
    $message = $OUTPUT->box(get_string("forcejavascriptmessage", "scorm"), "forcejavascriptmessage");
    echo html_writer::tag('noscript', $message);
}

if (!empty($scorm->popup)) {
    $PAGE->requires->js_init_call('M.mod_scormform.init');
}

echo $OUTPUT->footer();
