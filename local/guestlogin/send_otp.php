<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
$PAGE->set_context(context_system::instance());
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'));
$email = isset($data->email) ? clean_param($data->email, PARAM_EMAIL) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

if (!validate_email($email)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$otp = random_int(100000, 999999);
$_SESSION['guestotp'] = $otp;
$_SESSION['guestemail'] = $email;

$subject = "Your Guest OTP";
$body = "Your guest OTP is: $otp";

$user = (object)[
    'id' => -1,
    'email' => $email,
    'username' => 'guest'
];
$support = core_user::get_support_user();

if (email_to_user($user, $support, $subject, $body)) {
    echo json_encode(['success' => true, 'message' => 'OTP sent']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Error sending OTP']);
    exit;
}
