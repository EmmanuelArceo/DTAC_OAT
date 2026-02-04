<?php

session_start();
include 'db.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$user_id = $_SESSION['user_id'];

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
            $oat->query("INSERT INTO ojt_records (user_id, date, time_in) VALUES ($user_id, '$today', '$time_in')");
            echo "Time in successful at $time_in!";
        }
    } else {
        echo "Invalid or expired QR code.";
    }
} else {
    echo "QR code not found.";
}
?>