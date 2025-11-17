<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $USER;

$now     = time();
$lastTs  = get_user_preferences('streak_last_ts', 0);
$count   = get_user_preferences('streak_count', 0);

$today   = date('Y-m-d', $now);
$lastDay = $lastTs ? date('Y-m-d', $lastTs) : null;

if ($lastDay !== $today) {
    // new calendar day
    if ($lastTs && ($now - $lastTs <= 86400)) {
        $count++;
    } else {
        $count = 1;
    }
    set_user_preference('streak_last_ts', $now);
    set_user_preference('streak_count',   $count);
}

// ----- tier logic: 10 / 25 / 50 / 100 -----
if     ($count < 10)  { $tier='bronze';   $target=10;  $tierTitle='Bronze'; }
elseif ($count < 25)  { $tier='silver';   $target=25;  $tierTitle='Silver'; }
elseif ($count < 50)  { $tier='gold';     $target=50;  $tierTitle='Gold'; }
else                  { $tier='platinum'; $target=100; $tierTitle='Platinum'; }

$progressPct = min(100, (int)round(($count / $target) * 100));
$visitWord   = ($count === 1) ? 'Visit' : 'Visits';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'streakCount'  => (int)$count,
    'tier'         => $tier,
    'tierTitle'    => $tierTitle,
    'target'       => (int)$target,
    'progressPct'  => $progressPct,
    'label'        => "{$count}/{$target} {$visitWord}",
    'visitDone'    => true,
]);
exit;
