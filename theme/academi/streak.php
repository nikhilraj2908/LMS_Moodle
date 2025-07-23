<?php
// File: theme/academi/streak.php
// Tracks a “streak” that increments once per calendar day (or resets if you skip a day)
// Uses mdl_user_preferences so no schema changes are required.

require_once(__DIR__ . '/../../config.php');
require_login();

global $USER;

// current timestamp
$now = time();

// 1) Load previous values (defaults to 0)
$lastTs = get_user_preferences('streak_last_ts', 0);
$count  = get_user_preferences('streak_count',   0);

// 2) Derive “day” buckets
$today    = date('Y-m-d', $now);
$lastDay  = $lastTs ? date('Y-m-d', $lastTs) : null;

if ($lastDay === $today) {
    // still the same calendar day → do nothing
}
else {
    // first hit on a new day
    if ($lastTs && ($now - $lastTs <= 86400)) {
        // previous visit was yesterday → continue streak
        $count++;
    } else {
        // gap > 1 day → reset streak
        $count = 1;
    }
    // save updated prefs
    set_user_preference('streak_last_ts', $now);
    set_user_preference('streak_count',   $count);
}

// 3) Return JSON (your front-end remains unchanged)
header('Content-Type: application/json');
echo json_encode([
    'streakCount' => $count,
    'visitDone'   => true
]);
exit;
