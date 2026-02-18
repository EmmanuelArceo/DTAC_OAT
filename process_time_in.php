<?php

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$code = trim($_POST['code']);
$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// validate QR code (existence + expiry)
$stmt = $oat->prepare("SELECT id, expires_at FROM qr_codes WHERE code = ? LIMIT 1");
$stmt->bind_param('s', $code);
$stmt->execute();
$qrRes = $stmt->get_result();

if (!$qrRes || $qrRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid QR code']);
    exit;
}

$qr = $qrRes->fetch_assoc();
if (!empty($qr['expires_at']) && strtotime($qr['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'QR code expired']);
    exit;
}

// insert or update today's ojt_records.time_in
$now = date('H:i:s');

$sel = $oat->prepare("SELECT id, time_in FROM ojt_records WHERE user_id = ? AND date = ? LIMIT 1");
$sel->bind_param("is", $userId, $today);
$sel->execute();
$res = $sel->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if (!empty($row['time_in']) && $row['time_in'] !== '00:00:00') {
        echo json_encode(['success' => false, 'message' => 'Already timed in at ' . date('g:i A', strtotime($row['time_in']))]);
        exit;
    }
    $upd = $oat->prepare("UPDATE ojt_records SET time_in = ? WHERE id = ?");
    $upd->bind_param("si", $now, $row['id']);
    $ok = $upd->execute();
    $recordId = $row['id'];
} else {
    $ins = $oat->prepare("INSERT INTO ojt_records (user_id, date, time_in) VALUES (?, ?, ?)");
    $ins->bind_param("iss", $userId, $today, $now);
    $ok = $ins->execute();
    $recordId = $oat->insert_id;
}

if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'Failed to record Time In']);
    exit;
}

// attach pending selfie (if present in session)
if (!empty($_SESSION['pending_selfie'])) {
    $pending = $_SESSION['pending_selfie'];
    if (file_exists(__DIR__ . '/' . $pending)) {
        $a = $oat->prepare("UPDATE ojt_records SET selfie = ? WHERE id = ?");
        $a->bind_param("si", $pending, $recordId);
        $a->execute();
    }
    unset($_SESSION['pending_selfie']);
}

echo json_encode(['success' => true, 'message' => 'Time In recorded at ' . date('g:i A'), 'time_in' => $now]);
exit;
?>