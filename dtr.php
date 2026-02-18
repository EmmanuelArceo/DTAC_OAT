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

// Fetch DTR records for this OJT using prepared statement (include selfie)
$stmt = $oat->prepare("SELECT id, date, time_in, time_out, remarks, time_in_policy, time_out_policy, ot_hours, lunch_start, lunch_end, selfie FROM ojt_records WHERE user_id = ? ORDER BY date DESC");
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

// Function to calculate total hours (updated for per-record policies and accurate deductions, now with floor for whole hours)
function calculateTotalHours($row, $user_id, $oat) {
    // treat '00:00:00' as missing for both time_in and time_out
    if (!$row['time_in'] || $row['time_in'] === '00:00:00' || !$row['time_out'] || $row['time_out'] === '00:00:00') {
        return ['total' => '', 'ot' => ''];
    }

    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);

    // Use policy times from the record
    $policy_time_in_str = $row['time_in_policy'] ?? '08:00:00';
    $policy_time_out_str = $row['time_out_policy'] ?? '17:00:00';
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

    // FIX: Regular end is counted start + policy duration
    $regular_end = $count_start + $policy_duration;

    // Regular hours: up to regular end
    $reg_hours = min($time_out, $regular_end) - $count_start;
    $reg_hours = $reg_hours / 3600;

    // Deduct overlapping lunch break hours (use stored lunch if available)
    $lunch_start = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_start'] ?? '12:00:00'));
    $lunch_end = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_end'] ?? '13:00:00'));
    $overlap = max(0, min($time_out, $lunch_end) - max($count_start, $lunch_start));
    $reg_hours -= $overlap / 3600;

    // OT hours: from ojt_records
    $ot_hours = (float)($row['ot_hours'] ?? 0);

    $total_hours = max(0, floor($reg_hours + $ot_hours));
    return ['total' => number_format($total_hours, 0) . ' h', 'ot' => number_format($ot_hours, 0) . ' h'];
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
            <!-- Note: Schedule display removed as it's now per-record -->
        </div>
        <div class="dtr-actions justify-content-center flex-row">
            <?php if (!$today_selfie): ?>
                <div id="selfie-capture" class="text-center mb-3">
                    <video id="selfie-video" width="240" height="180" autoplay style="border-radius:8px;border:1px solid #ccc;"></video>
                    <br>
                    <button id="capture-btn" class="btn-accent mt-2">Take Selfie</button>
                    <canvas id="selfie-canvas" width="240" height="180" style="display:none;"></canvas>
                    <form id="selfie-form" method="post" style="display:none;">
                        <input type="hidden" name="selfie_data" id="selfie-data">
                        <button type="submit" class="btn-accent mt-2">Submit Selfie</button>
                    </form>
                </div>
                <button id="scan-time-in-btn" class="btn-accent me-2" disabled>
                    <i class="bi bi-qr-code-scan me-2"></i>Scan Time In QR
                </button>
            <?php else: ?>
                <button id="scan-time-in-btn" class="btn-accent me-2">
                    <i class="bi bi-qr-code-scan me-2"></i>Scan Time In QR
                </button>
            <?php endif; ?>
            <button id="scan-time-out-btn" class="btn-accent">
                <i class="bi bi-qr-code-scan me-2"></i>Scan Time Out QR
            </button>
        </div>
        <?php
        // Handle real-time selfie capture and save to ojt_records
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selfie_data'])) {
            $data = $_POST['selfie_data'];
            $filename = 'selfie_' . $user_id . '_' . $today . '.png';
            $target = 'uploads/' . $filename;
            $img = str_replace('data:image/png;base64,', '', $data);
            $img = str_replace(' ', '+', $img);
            file_put_contents($target, base64_decode($img));
            // Save selfie path to DB
            $stmt = $oat->prepare("UPDATE ojt_records SET selfie=? WHERE user_id=? AND date=?");
            $stmt->bind_param("sis", $target, $user_id, $today);
            $stmt->execute();
            echo "<script>location.reload();</script>";
        }
        ?>
        <div id="scan-label" class="text-center fw-semibold mb-2" style="color:var(--accent-deep);"></div>
        <div id="qr-reader"></div>
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
                                </td>
                                <td class="text-center">
                                    <?php
                                        // treat database '00:00:00' as empty (same as time_out)
                                        if ($row['time_in'] && $row['time_in'] !== '00:00:00') {
                                            $time_in_ts = strtotime($row['time_in']);
                                            $policy_in = $row['time_in_policy'] ?? '08:00:00';
                                            $policy_in_time_ts = strtotime(date('Y-m-d', $time_in_ts) . ' ' . $policy_in);
                                            $is_late = $time_in_ts > $policy_in_time_ts; // Fixed: > for exactly on time
                                            $time_in_display = date('g:i A', $time_in_ts);
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
                                    <?= ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date("g:i A", strtotime($row['time_out'])) : '--' ?>
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

        let html5QrCode = null;
        let currentAction = ''; // 'time_in' or 'time_out'
        let processing = false;    // set while a valid QR is being processed
        let scannerStarted = false;

        function startScan(action) {
            currentAction = action;
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

        function onQrScanned(qrCodeMessage) {
            // If already processing a valid QR, ignore further detections
            if (processing) return;

            try {
                const url = new URL(qrCodeMessage);
                const params = new URLSearchParams(url.search);
                const code = params.get('code');
                const sessionId = params.get('session_id');
                const date = params.get('date');

                // Validate required params for the selected action
                if (!code || !date || (currentAction === 'time_out' && !sessionId)) {
                    qrResult.innerHTML = 'Invalid QR code format.'; // keep scanning
                    return;
                }

                // Mark processing so we don't send duplicates; keep camera running
                processing = true;
               

                const endpoint = currentAction === 'time_out' ? 'time_out.php' : 'time_in.php';
                const urlParams = `${endpoint}?code=${encodeURIComponent(code)}${sessionId ? '&session_id=' + encodeURIComponent(sessionId) : ''}&date=${encodeURIComponent(date)}`;

                fetch(urlParams)
                    .then(response => response.text())
                    .then(data => {
                        qrResult.innerHTML = data;
                        const text = String(data).toLowerCase();

                        // SUCCESS -> stop + reload
                        if (text.includes('successful')) {
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
                                    setTimeout(() => location.reload(), 1200);
                                });
                            } else {
                                setTimeout(() => location.reload(), 1200);
                            }
                            return;
                        }

                        // ALREADY TIMED IN (terminal) -> stop camera and keep server message
                        if (currentAction === 'time_in' && text.includes('already') && (text.includes('time in') || text.includes('timed in'))) {
                            // lock while stopping to avoid further detections
                            processing = true;
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
                                    processing = false;
                                });
                            } else {
                                scanTimeInBtn.disabled = false;
                                scanTimeOutBtn.disabled = false;
                                scanTimeInBtn.classList.remove('active');
                                scanTimeOutBtn.classList.remove('active');
                                processing = false;
                            }
                            return;
                        }

                        // not successful — continue scanning automatically
                        processing = false;
                        scanLabel.textContent = currentAction === 'time_in' ? 'Scanning for Time In...' : 'Scanning for Time Out...';
                        scanTimeInBtn.disabled = (currentAction === 'time_in');
                        scanTimeOutBtn.disabled = (currentAction === 'time_out');
                        scanTimeInBtn.classList.toggle('active', currentAction === 'time_in');
                        scanTimeOutBtn.classList.toggle('active', currentAction === 'time_out');
                    })
                    .catch(() => {
                        // preserve an "already timed in" server message — don't overwrite it
                        const cur = (qrResult && qrResult.textContent) ? qrResult.textContent.toLowerCase() : '';
                        if (cur.includes('already') && (cur.includes('time in') || cur.includes('timed in'))) {
                            // ensure camera is stopped if it wasn't already
                            if (html5QrCode && scannerStarted) { html5QrCode.stop().catch(()=>{}); scannerStarted = false; qrReader.style.display = 'none'; }
                            processing = false;
                            return;
                        }

                        qrResult.innerHTML = 'Failed to process QR.';
                        processing = false; // allow further scans
                    });
            } catch (e) {
                qrResult.innerHTML = 'Invalid QR code content.'; // keep scanning
            }
        }

        // Ensure camera is stopped when leaving page
        window.addEventListener('beforeunload', () => {
            if (html5QrCode && scannerStarted) { html5QrCode.stop().catch(()=>{}); }
        });

        scanTimeInBtn.addEventListener('click', () => startScan('time_in'));
        scanTimeOutBtn.addEventListener('click', () => startScan('time_out'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>