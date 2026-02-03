<?php

session_start();
include 'db.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['code']) || !isset($_GET['session_id']) || !isset($_GET['date'])) {
    echo "Invalid QR code.";
    exit;
}

$code = $_GET['code'];
$session_id = $_GET['session_id'];
$date = $_GET['date'];
$today = date('Y-m-d');

if ($date !== $today) {
    echo "QR code is not for today.";
    exit;
}

// Check if code, session_id, and date match and are not expired
$res = $oat->query("SELECT code, expires_at FROM qr_codes WHERE type='time_out' AND session_id='$session_id' AND date='$date' ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    if ($row['code'] === $code && strtotime($row['expires_at']) >= time()) {
        // Check if timed in today and not yet timed out
        $check = $oat->query("SELECT id FROM ojt_records WHERE user_id=$user_id AND date='$today' AND time_in IS NOT NULL AND (time_out IS NULL OR time_out = '00:00:00')");
        if ($check->num_rows > 0) {
            $time_out = date('H:i:s');
            $oat->query("UPDATE ojt_records SET time_out='$time_out' WHERE user_id=$user_id AND date='$today'");
            echo "Time out successful at $time_out!";
        } else {
            echo "No valid time in record for today or already timed out.";
        }
    } else {
        echo "Invalid or expired QR code.";
    }
} else {
    echo "QR code not found.";
}
?>