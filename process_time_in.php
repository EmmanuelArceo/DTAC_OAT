<?php

session_start();
include 'db.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['qr_url'])) {
    echo "Invalid request.";
    exit;
}

// Extract code from scanned QR URL
$qr_url = $data['qr_url'];
$parsed_url = parse_url($qr_url);
parse_str($parsed_url['query'] ?? '', $params);
$code = $params['code'] ?? '';

if (!$code) {
    echo "Invalid QR code.";
    exit;
}

// Check if code exists and is not expired
$res = $oat->query("SELECT code, expires_at FROM qr_codes ORDER BY id DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    if ($row['code'] === $code && strtotime($row['expires_at']) >= time()) {
        // Check if already timed in today
        $date = date('Y-m-d');
        $check = $oat->query("SELECT id FROM ojt_records WHERE user_id=$user_id AND date='$date'");
        if ($check->num_rows > 0) {
            echo "Already timed in today.";
        } else {
            $time_in = date('H:i:s');
            $oat->query("INSERT INTO ojt_records (user_id, date, time_in) VALUES ($user_id, '$date', '$time_in')");
            echo "Time in successful at $time_in!";
        }
    } else {
        echo "Invalid or expired QR code.";
    }
} else {
    echo "QR code not found.";
}
?>