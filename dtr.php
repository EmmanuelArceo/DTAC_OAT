<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* --- AJAX endpoints: save / clear pending selfie (session + attach to today's record if present) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }
    $action = $_POST['action'];

    define('MAX_SELFIE_BYTES', 2 * 1024 * 1024);
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    if ($action === 'save_selfie') {
        if (empty($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok'=>false,'msg'=>'No selfie uploaded']); exit;
        }
        if ($_FILES['selfie']['size'] > MAX_SELFIE_BYTES) {
            echo json_encode(['ok'=>false,'msg'=>'Selfie too large']); exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['selfie']['tmp_name']);
        finfo_close($finfo);
        if (!isset($allowed_mimes[$mime])) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid file type']); exit;
        }
        $contents = file_get_contents($_FILES['selfie']['tmp_name']);
        if ($contents === false) { echo json_encode(['ok'=>false,'msg'=>'Read error']); exit; }
        $selfie_db = 'data:' . $mime . ';base64,' . base64_encode($contents);

        // try attach to today's record if present
        $todayDate = date('Y-m-d');
        $sel = $oat->prepare("SELECT id FROM ojt_records WHERE user_id = ? AND date = ? LIMIT 1");
        $sel->bind_param("is", $_SESSION['user_id'], $todayDate);
        $sel->execute();
        $res = $sel->get_result();
        if ($res && $res->num_rows > 0) {
            $rid = $res->fetch_assoc()['id'];
            // persist selfie and mark verified by default
            $upd = $oat->prepare("UPDATE ojt_records SET selfie = ?, selfie_verified = 1 WHERE id = ?");
            $upd->bind_param("si", $selfie_db, $rid);
            @$upd->execute();
        }
        // keep session copy so time_in.php can consume it
        $_SESSION['pending_selfie'] = $selfie_db;
        echo json_encode(['ok'=>true]); exit;
    }

    if ($action === 'clear_pending_selfie') {
        if (!empty($_SESSION['pending_selfie'])) unset($_SESSION['pending_selfie']);
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'unknown action']); exit;
}

// fetch assigned time-group policy for this user (used for lateness display)
$stmt_group = $oat->prepare("
    SELECT tg.time_in, tg.time_out
    FROM user_time_groups utg
    JOIN time_groups tg ON utg.group_id = tg.id
    WHERE utg.user_id = ? LIMIT 1
");
$stmt_group->bind_param("i", $user_id);
$stmt_group->execute();
$group_policy = $stmt_group->get_result()->fetch_assoc();
$user_group_time_in  = $group_policy['time_in']  ?? null;
$user_group_time_out = $group_policy['time_out'] ?? null;
$stmt_group->close();

// load default time-group values from time_groups (use when user has no assigned group)
$defStmt = $oat->prepare("SELECT time_in, time_out, lunch_start, lunch_end FROM time_groups WHERE name = 'Default' LIMIT 1");
$defStmt->execute();
$defRow = $defStmt->get_result()->fetch_assoc();
$default_time_in     = $defRow['time_in']     ?? '08:00:00';
$default_time_out    = $defRow['time_out']    ?? '17:00:00';
$default_lunch_start = $defRow['lunch_start'] ?? '12:00:00';
$default_lunch_end   = $defRow['lunch_end']   ?? '13:00:00';
$defStmt->close();

// prepare display strings for assigned policy (fallback to DB default group)
$policy_in_display  = date('g:iA', strtotime($user_group_time_in  ?? $default_time_in));
$policy_out_display = date('g:iA', strtotime($user_group_time_out ?? $default_time_out));

// Fetch DTR records for this OJT using prepared statement (include selfie and selfie_verified)
$stmt = $oat->prepare("SELECT id, date, time_in, time_out, remarks, time_in_policy, time_out_policy, ot_hours, lunch_start, lunch_end, selfie, selfie_verified FROM ojt_records WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dtr_query = $stmt->get_result();

// Fetch today's selfie (if any)
$today = date('Y-m-d');
$stmt2 = $oat->prepare("SELECT id, selfie FROM ojt_records WHERE user_id = ? AND date = ? LIMIT 1");
$stmt2->bind_param("is", $user_id, $today);
$stmt2->execute();
$today_res = $stmt2->get_result();
$today_selfie = $today_res->num_rows ? $today_res->fetch_assoc()['selfie'] : null;

// prefer session-pending selfie if DB row not yet updated
if (empty($today_selfie) && !empty($_SESSION['pending_selfie'])) {
    $today_selfie = $_SESSION['pending_selfie'];
}

// check if user already timed in today (time_in not NULL and not "00:00:00")
$stmt3 = $oat->prepare("SELECT id FROM ojt_records WHERE user_id = ? AND date = ? AND time_in IS NOT NULL AND time_in != '00:00:00' LIMIT 1");
$stmt3->bind_param("is", $user_id, $today);
$stmt3->execute();
$already_timed_in_today = ($stmt3->get_result()->num_rows > 0);
$stmt3->close();

// check if user already timed out today (time_out not NULL and not "00:00:00")
$stmt4 = $oat->prepare("SELECT id FROM ojt_records WHERE user_id = ? AND date = ? AND time_out IS NOT NULL AND time_out != '00:00:00' LIMIT 1");
$stmt4->bind_param("is", $user_id, $today);
$stmt4->execute();
$already_timed_out_today = ($stmt4->get_result()->num_rows > 0);
$stmt4->close();

// Function to calculate total hours (updated to prefer per-record policy, fallback to user's time_group then DB default)
function calculateTotalHours($row, $user_id, $oat) {
    // make DB default values available inside the function
    global $default_time_in, $default_time_out, $default_lunch_start, $default_lunch_end;

    // Treat explicit "00:00:00" as missing for both time_in/time_out
    if (empty($row['time_in']) || $row['time_in'] === '00:00:00' || empty($row['time_out']) || $row['time_out'] === '00:00:00') {
        return ['total' => '', 'ot' => ''];
    }

    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);

    // determine policy times: prefer record values; fallback to user's time_group; then DB default
    $policy_time_in_str  = $row['time_in_policy']  ?? null;
    $policy_time_out_str = $row['time_out_policy'] ?? null;

    if (empty($policy_time_in_str) || empty($policy_time_out_str)) {
        static $cached_user_policies = [];
        if (!isset($cached_user_policies[$user_id])) {
            $stmt = $oat->prepare("
                SELECT tg.time_in AS time_in_policy, tg.time_out AS time_out_policy
                FROM user_time_groups utg
                JOIN time_groups tg ON utg.group_id = tg.id
                WHERE utg.user_id = ? LIMIT 1
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $cached_user_policies[$user_id] = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
        }
        $policy_time_in_str  = $policy_time_in_str  ?? ($cached_user_policies[$user_id]['time_in_policy']  ?? $default_time_in);
        $policy_time_out_str = $policy_time_out_str ?? ($cached_user_policies[$user_id]['time_out_policy'] ?? $default_time_out);
    }

    $policy_time_in = strtotime(date('Y-m-d', $time_in) . ' ' . $policy_time_in_str);
    $policy_time_out = strtotime(date('Y-m-d', $time_in) . ' ' . $policy_time_out_str);
    $policy_duration = $policy_time_out - $policy_time_in;

    // If late by 1 hour or more, start counting from next full hour; otherwise, start from actual time in
    $lateness = $time_in - $policy_time_in;
    if ($lateness >= 3600) {
        $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
    } else {
        $count_start = $time_in;
    }

    // Regular end is counted start + policy duration
    $regular_end = $count_start + $policy_duration;

    // Regular hours: up to regular end
    $reg_hours = min($time_out, $regular_end) - $count_start;
    $reg_hours = $reg_hours / 3600;

    // Deduct overlapping lunch break hours (use stored lunch if available, otherwise DB default)
    $lunch_start = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_start'] ?? $default_lunch_start));
    $lunch_end   = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_end']   ?? $default_lunch_end));
    $overlap = max(0, min($time_out, $lunch_end) - max($count_start, $lunch_start));
    $reg_hours -= $overlap / 3600;

    // OT hours: from ojt_records
    $ot_hours = (float)($row['ot_hours'] ?? 0);

    $total_hours = max(0, max(0, $reg_hours) + $ot_hours); // Always add full OT hours
    return [
        'total' => ($total_hours > 0 ? rtrim(rtrim(number_format($total_hours, 2), '0'), '.') : '0') . ' h',
        'ot' => ($ot_hours > 0 ? rtrim(rtrim(number_format($ot_hours, 2), '0'), '.') : '0') . ' h'
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My DTR Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        :root {
            --accent: #3CB3CC;
            --accent-deep: #2aa0b3;
            --muted: #6b7280;
        }
        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            background: linear-gradient(135deg, #f7fbfb 0%, #fbfcfd 100%);
            color: #0f172a;
            min-height: 100vh;
        }
        .dtr-glass {
            background: rgba(255,255,255,0.66);
            border: 1px solid rgba(60,179,204,0.10);
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius: 16px;
            max-width: 900px;
            margin: 48px auto;
            padding: 32px 24px;
        }
        /* schedule badge */
        .schedule-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(43,166,179,0.06);
            border: 1px solid rgba(43,166,179,0.08);
            box-shadow: 0 8px 20px rgba(15,23,42,0.03);
        }
        .schedule-badge .icon {
            width: 32px;
            height: 32px;
            display: inline-grid;
            place-items: center;
            border-radius: 8px;
            background: rgba(43,166,179,0.12);
            color: var(--accent-deep);
            flex: 0 0 32px;
        }
        .schedule-badge .text { line-height: 1; text-align: left; }
        .schedule-badge .label { font-size: 0.75rem; color: var(--muted); }
        .schedule-badge .times { font-weight: 700; color: var(--accent-deep); font-size: 1rem; }
        .dtr-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-deep);
            margin-bottom: 8px;
        }
        .dtr-subtitle {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 24px;
        }
        .dtr-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            justify-content: center;
        }
        .dtr-actions justify-content-center flex-row {
            gap: 10px;
        }
        .btn-accent {
            background: linear-gradient(90deg, var(--accent), var(--accent-deep));
            color: #fff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            box-shadow: 0 4px 16px rgba(60,179,204,0.10);
            transition: all .15s;
        }
        .btn-accent:hover {
            background: var(--accent-deep);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }
        /* disabled style for Time In button when already timed in */
        .btn-accent[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            filter: grayscale(10%);
        }
        .btn-accent.active {
            box-shadow: 0 6px 20px rgba(60,179,204,0.18);
            transform: translateY(-2px) scale(1.02);
        }
        #qr-reader {
            margin: 0 auto;
            display: none;
            max-width: 340px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(60,179,204,0.08);
        }
        #qr-result {
            text-align: center;
            margin-top: 18px;
            font-weight: 600;
            color: var(--muted);
            min-height: 28px;
        }
        .table thead th {
            background: linear-gradient(90deg, var(--accent-deep), var(--accent));
            color: #fff;
            border: none;
            font-weight: 700;
            font-size: 1rem;
        }
        .table tbody tr:hover {
            background: rgba(60,179,204,0.04);
        }
        .table td, .table th {
            vertical-align: middle;
        }
        @media (max-width: 900px) {
            .dtr-glass { padding: 18px 6px; }
            .dtr-title { font-size: 1.3rem; }
            .dtr-actions { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="dtr-glass">
        <div class="text-center mb-4">
            <div class="dtr-title">My DTR Report</div>
            <div class="dtr-subtitle">Your recent attendance records</div>

            <!-- show assigned schedule -->
            <div class="mt-2">
                <div class="schedule-badge" role="note" aria-label="Assigned schedule">
                    <div class="icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7v6l4 2"></path>
                        </svg>
                    </div>
                    <div class="text">
                        <div class="label">Schedule</div>
                        <div class="times"><?= htmlspecialchars($policy_in_display) ?> — <?= htmlspecialchars($policy_out_display) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="dtr-actions justify-content-center flex-row">
            <button id="scan-time-in-btn" class="btn-accent me-2" <?= $already_timed_in_today ? 'title="Already timed in today"' : '' ?>>
                 <i class="bi bi-qr-code-scan me-2"></i>Scan Time In QR
             </button>
             <button id="scan-time-out-btn" class="btn-accent">
                 <i class="bi bi-qr-code-scan me-2"></i>Scan Time Out QR
             </button>
         </div>
         <div id="scan-label" class="text-center fw-semibold mb-2" style="color:var(--accent-deep);"></div>
         <div id="qr-reader"></div>

         <!-- FACE CAPTURE (required before Time In scan) -->
         <div id="face-capture" class="text-center mt-3" style="display:none;">
             <div class="card mx-auto" style="max-width:360px; border-radius:12px; padding:12px; box-shadow:0 6px 20px rgba(15,23,42,0.06);">
                 <div id="face-video-wrap" style="position:relative;">
                     <video id="face-video" autoplay muted playsinline style="width:100%; border-radius:8px; background:#000;"></video>
                     <canvas id="face-canvas" style="display:none; width:100%; border-radius:8px;"></canvas>
                 </div>
                 <div class="mt-2 d-flex justify-content-center gap-2">
                     <button id="capture-face-btn" class="btn btn-outline-primary btn-sm">Capture</button>
                     <button id="retake-face-btn" class="btn btn-outline-secondary btn-sm" style="display:none;">Retake</button>
                     <button id="confirm-face-btn" class="btn btn-accent btn-sm" style="display:none;">Use & Scan</button>
                     <button id="cancel-face-btn" class="btn btn-light btn-sm">Cancel</button>
                 </div>
                 <div class="small text-muted mt-2">Face capture is required for Time In.</div>
             </div>
         </div>

         <div id="qr-result"></div>
         <div class="table-responsive mt-4">
             <table class="table align-middle rounded-3 overflow-hidden shadow-sm">
                 <thead>
                     <tr>
                         <th>Date</th>
                         <th class="text-center">Time In</th>
                         <th class="text-center">Time Out</th>
                         <th class="text-center">Total Hours</th>
                         <th class="text-center">OT Hours</th>
                         <th class="text-center">Remarks</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php if ($dtr_query->num_rows > 0): ?>
                         <?php while ($row = $dtr_query->fetch_assoc()): ?>
                             <?php $hours = calculateTotalHours($row, $user_id, $oat); ?>
                             <tr>
                                 <td class="text-center">
                                     <a href="dtrdetail.php?id=<?= urlencode($row['id']) ?>" style="text-decoration:underline;color:var(--accent-deep);">
                                         <?= htmlspecialchars($row['date']) ?>
                                     </a>
                                     <?php if (empty($row['selfie_verified']) || $row['selfie_verified'] != 1): ?>
                                         <span title="Selfie not verified" style="color:#e63946; margin-left:4px; vertical-align:middle;">
                                             <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16" style="vertical-align:middle;">
                                               <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.707c.89 0 1.438-.99.982-1.767L8.982 1.566zm-.982 4.905a.905.905 0 1 1 1.81 0l-.35 3.507a.552.552 0 0 1-1.11 0l-.35-3.507zm.002 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                                             </svg>
                                         </span>
                                     <?php endif; ?>
                                 </td>
                                 <td class="text-center">
                                     <?php
                                         // SHOW blank if time_in is missing or explicitly "00:00:00"
                                         if ($row['time_in'] && $row['time_in'] !== '00:00:00') {
                                             $time_in_ts = strtotime($row['time_in']);

                                             // prefer user's assigned time-group policy, then record policy, then DB default
                                             $policy_in = $user_group_time_in ?? $row['time_in_policy'] ?? $default_time_in;
                                             $policy_in_time_ts = strtotime(date('Y-m-d', $time_in_ts) . ' ' . $policy_in);
                                             $is_late = $time_in_ts > $policy_in_time_ts; // late only if strictly after policy
                                             // show without leading zero and UPPERCASE AM/PM (e.g. 2:25PM)
                                             $time_in_display = date('g:iA', $time_in_ts);
                                             if ($is_late) {
                                                 echo '<span style="color: red;">' . htmlspecialchars($time_in_display) . '</span>';
                                             } else {
                                                 echo htmlspecialchars($time_in_display);
                                             }
                                         } else {
                                             echo '--';
                                         }
                                     ?>
                                 </td>
                                 <td class="text-center">
                                     <?= ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date("g:iA", strtotime($row['time_out'])) : '--' ?>
                                 </td>
                                 <td class="text-center">
                                     <?= $hours['total'] ?: '--' ?>
                                 </td>
                                 <td class="text-center">
                                     <?= $hours['ot'] ?: '--' ?>
                                 </td>
                                 <td class="text-center">
                                     <?= htmlspecialchars($row['remarks'] ?? '--') ?>
                                 </td>
                             </tr>
                         <?php endwhile; ?>
                     <?php else: ?>
                         <tr>
                             <td colspan="6" class="py-4 text-center text-muted">No DTR records found.</td>
                         </tr>
                     <?php endif; ?>
                 </tbody>
             </table>
         </div>
     </div>

     <script>
         const scanTimeInBtn = document.getElementById('scan-time-in-btn');
         const scanTimeOutBtn = document.getElementById('scan-time-out-btn');
         const qrReader = document.getElementById('qr-reader');
         const qrResult = document.getElementById('qr-result');
         const scanLabel = document.getElementById('scan-label');

         // server-provided flags
         const alreadyTimedInToday = <?= $already_timed_in_today ? 'true' : 'false' ?>;
         const alreadyTimedOutToday = <?= $already_timed_out_today ? 'true' : 'false' ?>;

         let html5QrCode = null;
         let currentAction = ''; // 'time_in' or 'time_out'
         let processing = false;    // set while a valid QR is being processed
         let scannerStarted = false;

         // Face-capture state
         let faceStream = null;
         let selfieCaptured = false;
         let selfieDataUrl = null;

         async function startFaceCamera() {
             const video = document.getElementById('face-video');
             try {
                 faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                 video.srcObject = faceStream;
                 await video.play();
             } catch (err) {
                 qrResult.innerHTML = 'Unable to access camera for face capture.';
                 closeFaceCapture();
             }
         }

         function stopFaceCamera() {
             if (faceStream) {
                 faceStream.getTracks().forEach(t => t.stop());
                 faceStream = null;
             }
             const video = document.getElementById('face-video');
             try { video.pause(); video.srcObject = null; } catch(e){}
         }

         function showFaceCapture() {
             // stop QR scanner if running (avoid camera conflict)
             if (html5QrCode && scannerStarted) {
                 html5QrCode.stop().catch(()=>{});
                 scannerStarted = false;
                 try { html5QrCode.clear(); } catch(e){}
                 html5QrCode = null;
             }

             document.getElementById('face-capture').style.display = 'block';
             document.getElementById('qr-reader').style.display = 'none';
             document.getElementById('face-video').style.display = 'block';
             document.getElementById('face-canvas').style.display = 'none';
             document.getElementById('capture-face-btn').style.display = 'inline-block';
             document.getElementById('retake-face-btn').style.display = 'none';
             document.getElementById('confirm-face-btn').style.display = 'none';
             selfieCaptured = false;
             selfieDataUrl = null;
             scanLabel.textContent = 'Please capture your face before Time In.';
             startFaceCamera();
         }

         function closeFaceCapture() {
             stopFaceCamera();
             document.getElementById('face-capture').style.display = 'none';
             scanLabel.textContent = '';
             scanTimeInBtn.disabled = false;
             scanTimeInBtn.classList.remove('active');
         }

         function captureFace() {
             const video = document.getElementById('face-video');
             const canvas = document.getElementById('face-canvas');
             canvas.width = video.videoWidth || 320;
             canvas.height = video.videoHeight || 240;
             const ctx = canvas.getContext('2d');
             ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
             selfieDataUrl = canvas.toDataURL('image/jpeg', 0.85);
             selfieCaptured = true;
             document.getElementById('face-video').style.display = 'none';
             canvas.style.display = 'block';
             document.getElementById('capture-face-btn').style.display = 'none';
             document.getElementById('retake-face-btn').style.display = 'inline-block';
             document.getElementById('confirm-face-btn').style.display = 'inline-block';
             stopFaceCamera();
         }

         function retakeFace() {
             selfieCaptured = false;
             selfieDataUrl = null;
             document.getElementById('face-canvas').style.display = 'none';
             document.getElementById('retake-face-btn').style.display = 'none';
             document.getElementById('confirm-face-btn').style.display = 'none';
             document.getElementById('capture-face-btn').style.display = 'inline-block';
             document.getElementById('face-video').style.display = 'block';
             startFaceCamera();
         }

         async function confirmFaceAndScan() {
             // persist pending selfie (session + attach to today's row if exists)
             try {
                 const blob = dataURLToBlob(selfieDataUrl);
                 const file = new File([blob], 'selfie.jpg', { type: blob.type });
                 const form = new FormData();
                 form.append('action', 'save_selfie');
                 form.append('selfie', file);
                 await fetch('dtr.php', { method: 'POST', body: form });
             } catch (e) {
                 console.warn('Failed to persist pending selfie', e);
             }

             // close capture UI and begin QR scan for Time In
             document.getElementById('face-capture').style.display = 'none';
             qrResult.innerHTML = '<div style="display:flex;align-items:center;gap:8px"><img src="'+selfieDataUrl+'" style="width:46px;height:46px;border-radius:6px;object-fit:cover;border:1px solid #eee" alt="selfie"><div class="small text-muted">Face captured — ready to scan.</div></div>';
             startScan('time_in');
         }

         function dataURLToBlob(dataURL) {
             const parts = dataURL.split(',');
             const meta = parts[0].match(/:(.*?);/);
             const mime = meta ? meta[1] : 'image/jpeg';
             const binary = atob(parts[1]);
             const len = binary.length;
             const u8 = new Uint8Array(len);
             for (let i = 0; i < len; i++) u8[i] = binary.charCodeAt(i);
             return new Blob([u8], { type: mime });
         }

         function startScan(action) {
             currentAction = action;

             // For Time In ensure face was captured first
             if (action === 'time_in' && !selfieCaptured && !selfieDataUrl) {
                 qrResult.innerHTML = 'Please capture your face before scanning Time In.';
                 return;
             }

             qrReader.style.display = 'block';
             // disable only the active button so user can switch action while camera runs
             scanTimeInBtn.disabled = (action === 'time_in');
             scanTimeOutBtn.disabled = (action === 'time_out');
             scanTimeInBtn.classList.toggle('active', action === 'time_in');
             scanTimeOutBtn.classList.toggle('active', action === 'time_out');
             scanLabel.textContent = action === 'time_in' ? 'Scanning for Time In...' : 'Scanning for Time Out...';
             qrResult.innerHTML = '';

             if (!html5QrCode) html5QrCode = new Html5Qrcode('qr-reader');

             if (!scannerStarted) {
                 html5QrCode.start(
                     { facingMode: 'environment' },
                     { fps: 10, qrbox: 250 },
                     qrCodeMessage => onQrScanned(qrCodeMessage),
                     errorMessage => { /* per-frame decode errors ignored */ }
                 ).then(() => {
                     scannerStarted = true;
                 }).catch(err => {
                     qrResult.innerHTML = 'Unable to start camera.';
                     scanTimeInBtn.disabled = false;
                     scanTimeOutBtn.disabled = false;
                     scanTimeInBtn.classList.remove('active');
                     scanTimeOutBtn.classList.remove('active');
                     qrReader.style.display = 'none';
                     scanLabel.textContent = '';
                 });
             }
         }

         function handleServerResponse(text) {
             const lower = String(text).toLowerCase();
             qrResult.innerHTML = text;
             if (lower.includes('successful')) {
                 // reset selfie state after successful time in
                 selfieCaptured = false;
                 selfieDataUrl = null;

                 if (html5QrCode && scannerStarted) {
                     html5QrCode.stop().catch(()=>{}).then(() => {
                         try { html5QrCode.clear(); } catch(e) {}
                         html5QrCode = null;
                         scannerStarted = false;
                         qrReader.style.display = 'none';
                         scanLabel.textContent = '';
                         scanTimeInBtn.disabled = false;
                         scanTimeOutBtn.disabled = false;
                         scanTimeInBtn.classList.remove('active');
                         scanTimeOutBtn.classList.remove('active');
                         setTimeout(() => location.reload(), 900);
                     });
                 } else {
                     setTimeout(() => location.reload(), 900);
                 }
                 return true;
             }

             if (lower.includes('already') && (lower.includes('time in') || lower.includes('timed in'))) {
                 // stop scanner and reset UI
                 if (html5QrCode && scannerStarted) {
                     html5QrCode.stop().catch(()=>{}).then(() => {
                         try { html5QrCode.clear(); } catch(e) {}
                         html5QrCode = null;
                         scannerStarted = false;
                         qrReader.style.display = 'none';
                     });
                 }
                 processing = false;
                 scanLabel.textContent = '';
                 scanTimeInBtn.disabled = false;
                 scanTimeOutBtn.disabled = false;
                 scanTimeInBtn.classList.remove('active');
                 scanTimeOutBtn.classList.remove('active');
                 return true;
             }

             return false;
         }

         function onQrScanned(qrCodeMessage) {
             if (processing) return;

             try {
                 const url = new URL(qrCodeMessage);
                 const params = new URLSearchParams(url.search);
                 const code = params.get('code');
                 const sessionId = params.get('session_id');
                 const date = params.get('date');

                 if (!code || !date || (currentAction === 'time_out' && !sessionId)) {
                     qrResult.innerHTML = 'Invalid QR code format.';
                     return;
                 }

                 processing = true;

                 // If Time In and we have a captured selfie, POST the file
                 if (currentAction === 'time_in' && selfieCaptured && selfieDataUrl) {
                     const blob = dataURLToBlob(selfieDataUrl);
                     const file = new File([blob], 'selfie.jpg', { type: blob.type });

                     const form = new FormData();
                     form.append('selfie', file);
                     form.append('code', code);
                     form.append('date', date);
                     if (sessionId) form.append('session_id', sessionId);

                     fetch('time_in.php', { method: 'POST', body: form })
                         .then(r => r.text())
                         .then(text => {
                             if (!handleServerResponse(text)) {
                                 processing = false;
                             }
                         })
                         .catch(() => {
                             qrResult.innerHTML = 'Failed to process QR.';
                             processing = false;
                         });

                     return;
                 }

                 // fallback: GET request (time_out or time_in without file)
                 const endpoint = currentAction === 'time_out' ? 'time_out.php' : 'time_in.php';
                 const urlParams = `${endpoint}?code=${encodeURIComponent(code)}${sessionId ? '&session_id=' + encodeURIComponent(sessionId) : ''}&date=${encodeURIComponent(date)}`;

                 fetch(urlParams)
                     .then(response => response.text())
                     .then(text => {
                         if (!handleServerResponse(text)) {
                             processing = false;
                         }
                     })
                     .catch(() => {
                         qrResult.innerHTML = 'Failed to process QR.';
                         processing = false;
                     });

             } catch (e) {
                 qrResult.innerHTML = 'Invalid QR code content.';
             }
         }

         // Ensure cameras are stopped when leaving page
         window.addEventListener('beforeunload', () => {
             if (html5QrCode && scannerStarted) { html5QrCode.stop().catch(()=>{}); }
             stopFaceCamera();
         });

         // Wire up buttons: Time In opens face-capture first (guard if already timed in)
         scanTimeInBtn.addEventListener('click', () => {
             if (alreadyTimedInToday) {
                 qrResult.innerHTML = '<span style="color:var(--muted);font-weight:700">Already timed in today.</span>';
                 return;
             }
             scanTimeInBtn.disabled = true;
             scanTimeInBtn.classList.add('active');
             showFaceCapture();
         });
         scanTimeOutBtn.addEventListener('click', () => {
             if (alreadyTimedOutToday) {
                 qrResult.innerHTML = '<span style="color:var(--muted);font-weight:700">Already timed out today.</span>';
                 return;
             }
             startScan('time_out');
         });

         // face-capture controls
         document.getElementById('capture-face-btn').addEventListener('click', captureFace);
         document.getElementById('retake-face-btn').addEventListener('click', retakeFace);
         document.getElementById('confirm-face-btn').addEventListener('click', confirmFaceAndScan);
         document.getElementById('cancel-face-btn').addEventListener('click', async () => {
             selfieCaptured = false;
             selfieDataUrl = null;
             try {
                 await fetch('dtr.php', { method: 'POST', body: new URLSearchParams({ action: 'clear_pending_selfie' }) });
             } catch (e) { /* ignore */ }
             closeFaceCapture();
         });
     </script>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>