<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $USER;

$userid = $USER->id;
$today = date('Y-m-d');

error_log("streak.php called for user $userid on $today");

$existing = $DB->get_record('user_streaks', ['userid' => $userid, 'streak_date' => $today]);

if (!$existing) {
    error_log("No record for today, inserting with visited=1");
    $record = new stdClass();
    $record->userid = $userid;
    $record->streak_date = $today;
    $record->visited = 1;
    $DB->insert_record('user_streaks', $record);
} else if (isset($existing->visited) && !$existing->visited) {
    error_log("Record found but visited=0, updating to 1");
    $existing->visited = 1;
    $DB->update_record('user_streaks', $existing);
} else {
    error_log("Record found and visited=1");
}

$streakCount = $DB->count_records('user_streaks', ['userid' => $userid]);
$visitDone = $DB->record_exists('user_streaks', ['userid' => $userid, 'streak_date' => $today, 'visited' => 1]);

header('Content-Type: application/json');
echo json_encode(['streakCount' => $streakCount, 'visitDone' => $visitDone]);
exit();
