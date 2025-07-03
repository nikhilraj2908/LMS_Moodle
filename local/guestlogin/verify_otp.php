<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__.'/../../config.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'));
$email = isset($data->email) ? clean_param($data->email, PARAM_EMAIL) : '';
$otp   = isset($data->otp) ? clean_param($data->otp, PARAM_INT) : '';

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
    exit;
}

// match session OTP
if (
    isset($_SESSION['guestotp']) &&
    isset($_SESSION['guestemail']) &&
    $_SESSION['guestemail'] === $email &&
    $_SESSION['guestotp'] == $otp
) {
    global $DB;

    $record = new stdClass();
    $record->email = $email;
    $record->timecreated = time();
    $DB->insert_record('guestlogins', $record);

    $_SESSION['guestotp_verified'] = true;

    // clear OTP
    unset($_SESSION['guestotp']);

  require_once($CFG->dirroot.'/login/lib.php');
complete_user_login(get_complete_user_data('username', 'guest'));

require_once($CFG->dirroot.'/login/lib.php');
complete_user_login(get_complete_user_data('username', 'guest'));

echo json_encode(['success' => true, 'redirect' => (new moodle_url('/local/guestlogin/guestcourses.php'))->out(false)]);
exit;

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
}
