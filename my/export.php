<?php
require_once(__DIR__ . '/../config.php');
require_login();

if (!is_siteadmin()) {
    die('Access denied');
}

$regionid = optional_param('regionid', 0, PARAM_INT);

// Get region name for filename
$regionname = 'All-Regions';
if ($regionid) {
    $region = $DB->get_record('course_categories', ['id' => $regionid]);
    $regionname = $region ? format_string($region->name) : 'Region-'.$regionid;
}

// Fetch report data (same query as in index.php)
// ... [Insert the SQL query from above here] ...

// Generate CSV content
$csv = "User,Course,Region,Score,Rating,Progress,Status\n";
foreach ($reportData as $record) {
    $status = 'In Progress';
    if ($record->is_completed) $status = 'Completed';
    if ($record->is_review) $status = 'Needs Review';
    
    $csv .= sprintf('"%s","%s","%s",%d,%d,%d,"%s"'."\n",
        $record->username,
        $record->coursename,
        $record->region,
        $record->score,
        $record->rating,
        $record->progress,
        $status
    );
}

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="region-report-'.$regionname.'-'.date('Ymd').'.csv"');
echo $csv;
exit;