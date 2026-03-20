<?php

include '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$today = date('Y-m-d');
$activities = [];

// Clock In
$res = $oat->query("
    SELECT u.fname, u.lname, r.time_in
    FROM ojt_records r
    JOIN users u ON u.id = r.user_id
    WHERE r.date = '$today' AND r.time_in IS NOT NULL AND r.time_in != ''
    ORDER BY r.time_in DESC
");
while ($row = $res->fetch_assoc()) {
    $activities[] = [
        'type' => 'clock_in',
        'user' => htmlspecialchars($row['fname'] . ' ' . $row['lname']),
        'action' => 'clocked in',
        'time' => date('h:i A', strtotime($row['time_in']))
    ];
}

// Clock Out
$res = $oat->query("
    SELECT u.fname, u.lname, r.time_out
    FROM ojt_records r
    JOIN users u ON u.id = r.user_id
    WHERE r.date = '$today' AND r.time_out IS NOT NULL AND r.time_out != ''
    ORDER BY r.time_out DESC
");
while ($row = $res->fetch_assoc()) {
    $activities[] = [
        'type' => 'clock_out',
        'user' => htmlspecialchars($row['fname'] . ' ' . $row['lname']),
        'action' => 'clocked out',
        'time' => date('h:i A', strtotime($row['time_out']))
    ];
}

// OT Requests
$res = $oat->query("
    SELECT u.fname, u.lname, o.ot_date, o.submitted_at
    FROM ot_reports o
    JOIN users u ON u.id = o.student_id
    WHERE o.ot_date = '$today'
    ORDER BY o.submitted_at DESC
");
while ($row = $res->fetch_assoc()) {
    $activities[] = [
        'type' => 'ot_request',
        'user' => htmlspecialchars($row['fname'] . ' ' . $row['lname']),
        'action' => 'requested OT',
        'time' => date('h:i A', strtotime($row['submitted_at']))
    ];
}

// Sort all activities by time descending (most recent first)
usort($activities, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

echo json_encode($activities);