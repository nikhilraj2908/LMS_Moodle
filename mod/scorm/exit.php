<?php
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');

$id = required_param('id', PARAM_INT);         // Course Module ID
$scormid = required_param('scormid', PARAM_INT);
$attempt = required_param('attempt', PARAM_INT);

// ✅ Detect if called via navigator.sendBeacon
$is_beacon = isset($_SERVER['HTTP_SEC_FETCH_MODE']) && $_SERVER['HTTP_SEC_FETCH_MODE'] === 'no-cors';

if (!$is_beacon) {
    // ✅ Normal page visit (not from beacon): do full user verification
    $cm = get_coursemodule_from_id('scorm', $id, 0, true);
    $course = $DB->get_record("course", ["id" => $cm->course]);
    $scorm = $DB->get_record("scorm", ["id" => $scormid]);
    require_login($course, false, $cm);
    require_sesskey();
    $userid = $USER->id;
} else {
    // ✅ Beacon call: manually load session and user
    \core\session\manager::load_session_cookie();

    if (!isloggedin() || empty($USER->id)) {
        // ❌ Beacon call failed to get session
        error_log("❌ [exit.php] Beacon call failed: Not logged in.");
        http_response_code(401); // Unauthorized
        exit;
    }

    $userid = $USER->id;
}

// ✅ Log for debug
error_log("⏱️ [exit.php] Called by user {$userid} for SCORM {$scormid}, attempt {$attempt}");

// ✅ Find active session (endtime is NULL)
$record = $DB->get_record('scorm_session_time', [
    'userid' => $userid,
    'scormid' => $scormid,
    'endtime' => null
]);

if ($record) {
    $record->endtime = time();
    $record->duration = $record->endtime - $record->starttime;

    // ✅ Update session duration
    $DB->update_record('scorm_session_time', $record);
    error_log("✅ [exit.php] Session updated: Duration = {$record->duration}s");
} else {
    error_log("❌ [exit.php] No active session found to update for user {$userid}");
}

// ✅ Final response
if (!$is_beacon) {
    // Redirect back to course section (for manual testing/debug)
    $cm = get_coursemodule_from_id('scorm', $id, 0, true);
    $course = $DB->get_record("course", ["id" => $cm->course]);

    if ($course->format == 'singleactivity') {
        redirect($CFG->wwwroot);
    } else {
        redirect(course_get_url($course, $cm->sectionnum));
    }
} else {
    // Silent success for beacon
    http_response_code(204); // No Content
    exit;
}
