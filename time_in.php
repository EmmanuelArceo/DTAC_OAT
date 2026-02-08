<?php

session_start();
include 'db.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch default time in/out from site_settings
$settings = $oat->query("SELECT default_time_in, default_time_out FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';
$default_time_out = $settings['default_time_out'] ?? '17:00:00';

// Fetch user's lunch policy (from time group or default)
$stmt = $oat->prepare("SELECT time_in, time_out, lunch_start, lunch_end FROM time_groups WHERE name = 'Default' LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$default_lunch_start = $settings['lunch_start'] ?? '12:00:00';
$default_lunch_end = $settings['lunch_end'] ?? '13:00:00';

$user_lunch_start = $default_lunch_start;
$user_lunch_end = $default_lunch_end;
$stmt = $oat->prepare("SELECT tg.lunch_start, tg.lunch_end FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if ($group) {
    $user_lunch_start = $group['lunch_start'] ?: $default_lunch_start;
    $user_lunch_end = $group['lunch_end'] ?: $default_lunch_end;
}

if (!isset($_GET['code']) || !isset($_GET['date'])) {
    echo "Invalid QR code.";
    exit;
}

$code = $_GET['code'];
$date = $_GET['date'];
$today = date('Y-m-d');

if ($date !== $today) {
    echo "QR code is not for today.";
    exit;
}

// Check if code, type, and date match and are not expired
$res = $oat->query("SELECT code, expires_at FROM qr_codes WHERE type='time_in' AND code='$code' AND date='$date' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    if (strtotime($row['expires_at']) >= strtotime(gmdate('Y-m-d\TH:i:s\Z'))) {
        // Check if already timed in today
        $check = $oat->query("SELECT id FROM ojt_records WHERE user_id=$user_id AND date='$today'");
        if ($check->num_rows > 0) {
            echo "Already timed in today.";
        } else {
            $time_in = date('H:i:s');
            $remarks = 'on time';
            if (date('H:i:s', strtotime($time_in)) > $default_time_in) {
                $remarks = 'late';
            }

            $user_policy_time_in = $default_time_in;
            $user_policy_time_out = $default_time_out;

            $stmt = $oat->prepare("INSERT INTO ojt_records (user_id, date, time_in, time_in_policy, time_out_policy, lunch_start, lunch_end, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssss", $user_id, $date, $time_in, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $remarks);
            $stmt->execute();

            echo "Time in successful at $time_in!";
        }
    } else {
        echo "Invalid or expired QR code.";
    }
} else {
    echo "QR code not found.";
}
?>