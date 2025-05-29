<?php
require_once(__DIR__ . '/../../config.php');  // Adjust the relative path if needed
require_login();

global $DB, $USER;

$userid = $USER->id;
$today = date('Y-m-d');

// Check if streak is already recorded today
$existing = $DB->get_record('user_streaks', ['userid' => $userid, 'streak_date' => $today]);

if (!$existing) {
    // Insert new streak record for today
    $record = new stdClass();
    $record->userid = $userid;
    $record->streak_date = $today;
    $DB->insert_record('user_streaks', $record);
}

// Calculate total streak count (simple count of days active)
$streakCount = $DB->count_records('user_streaks', ['userid' => $userid]);

// Return JSON response with current streak count
header('Content-Type: application/json');
echo json_encode(['streakCount' => $streakCount]);
exit();
