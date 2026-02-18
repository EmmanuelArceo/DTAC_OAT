<?php

session_start();
include 'db.php';

header('Content-Type: text/plain');

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in.";
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/* site / policy defaults (unchanged logic) */
$settings = $oat->query("SELECT default_time_in, default_time_out FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';
$default_time_out = $settings['default_time_out'] ?? '17:00:00';

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

if (empty($_REQUEST['code']) || empty($_REQUEST['date'])) {
    echo "Invalid QR code.";
    exit;
}

$code = $_REQUEST['code'];
$date = $_REQUEST['date'];
$today = date('Y-m-d');

if ($date !== $today) {
    echo "QR code is not for today.";
    exit;
}

/* validate QR (prepared statement) */
$qrStmt = $oat->prepare("SELECT code, expires_at FROM qr_codes WHERE type = 'time_in' AND code = ? AND date = ? LIMIT 1");
$qrStmt->bind_param('ss', $code, $date);
$qrStmt->execute();
$qrRes = $qrStmt->get_result();

if (!$qrRes || $qrRes->num_rows === 0) {
    echo "QR code not found.";
    exit;
}
$qrRow = $qrRes->fetch_assoc();
if (!empty($qrRow['expires_at']) && strtotime($qrRow['expires_at']) < time()) {
    echo "Invalid or expired QR code.";
    exit;
}

/* --- handle selfie upload (POST multipart/form-data) --- */
$selfie_path = null;
define('MAX_SELFIE_BYTES', 2 * 1024 * 1024);
$allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['selfie']) && $_FILES['selfie']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['selfie']['size'] > MAX_SELFIE_BYTES) {
        echo "Selfie too large (max 2MB).";
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['selfie']['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowed_mimes[$mime])) {
        echo "Invalid selfie file type.";
        exit;
    }

    $ext = $allowed_mimes[$mime];
    $uploadDir = __DIR__ . '/dtr_uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['selfie']['tmp_name'], $dest)) {
        echo "Failed to save selfie.";
        exit;
    }

    $selfie_path = 'dtr_uploads/' . $filename;
    // if user previously had a pending session selfie, clear it in favor of this upload
    if (!empty($_SESSION['pending_selfie'])) unset($_SESSION['pending_selfie']);
} elseif (!empty($_SESSION['pending_selfie'])) {
    // attach session-pending selfie if file exists
    $pending = $_SESSION['pending_selfie'];
    if (file_exists(__DIR__ . '/' . $pending)) {
        $selfie_path = $pending;
    } else {
        unset($_SESSION['pending_selfie']);
    }
}

/* Check existing record for today */
$sel = $oat->prepare("SELECT id, time_in FROM ojt_records WHERE user_id = ? AND date = ? LIMIT 1");
$sel->bind_param("is", $user_id, $today);
$sel->execute();
$selRes = $sel->get_result();

$time_in = date('H:i:s');
$remarks = ($time_in > $default_time_in) ? 'late' : 'on time';
$user_policy_time_in = $default_time_in;
$user_policy_time_out = $default_time_out;

if ($selRes->num_rows > 0) {
    $row = $selRes->fetch_assoc();
    if (!empty($row['time_in']) && $row['time_in'] !== '00:00:00') {
        echo "Already timed in today.";
        exit;
    }

    if ($selfie_path !== null) {
        $upd = $oat->prepare("UPDATE ojt_records SET time_in = ?, selfie = ? WHERE id = ?");
        $upd->bind_param("ssi", $time_in, $selfie_path, $row['id']);
    } else {
        $upd = $oat->prepare("UPDATE ojt_records SET time_in = ? WHERE id = ?");
        $upd->bind_param("si", $time_in, $row['id']);
    }
    $ok = $upd->execute();

    if ($ok) {
        if (!empty($_SESSION['pending_selfie'])) unset($_SESSION['pending_selfie']);
        echo "Time in successful at $time_in!";
    } else {
        echo "Failed to record Time In.";
    }
    exit;
}

/* Insert new record (include selfie if available) */
if ($selfie_path !== null) {
    $ins = $oat->prepare("INSERT INTO ojt_records (user_id, date, time_in, time_in_policy, time_out_policy, lunch_start, lunch_end, remarks, selfie) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("issssssss", $user_id, $today, $time_in, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $remarks, $selfie_path);
} else {
    $ins = $oat->prepare("INSERT INTO ojt_records (user_id, date, time_in, time_in_policy, time_out_policy, lunch_start, lunch_end, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("isssssss", $user_id, $today, $time_in, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $remarks);
}

if ($ins->execute()) {
    if (!empty($_SESSION['pending_selfie'])) unset($_SESSION['pending_selfie']);
    echo "Time in successful at $time_in!";
} else {
    echo "Failed to time in.";
}
?>