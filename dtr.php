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

// Fetch DTR records for this OJT using prepared statement (include policy fields and id)
$stmt = $oat->prepare("SELECT id, date, time_in, time_out, remarks, time_in_policy, time_out_policy, ot_hours, lunch_start, lunch_end FROM ojt_records WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dtr_query = $stmt->get_result();

// Function to calculate total hours (updated for per-record policies and accurate deductions, now with floor for whole hours)
function calculateTotalHours($row, $user_id, $oat) {
    if (!$row['time_in'] || !$row['time_out'] || $row['time_out'] === '00:00:00') {
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
            <button id="scan-time-in-btn" class="btn-accent me-2">
                <i class="bi bi-qr-code-scan me-2"></i>Scan Time In QR
            </button>
            <button id="scan-time-out-btn" class="btn-accent">
                <i class="bi bi-qr-code-scan me-2"></i>Scan Time Out QR
            </button>
        </div>
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
                                        if ($row['time_in']) {
                                            $time_in_ts = strtotime($row['time_in']);
                                            $policy_in = $row['time_in_policy'] ?? '08:00:00';
                                            $policy_in_time_ts = strtotime(date('Y-m-d', $time_in_ts) . ' ' . $policy_in);
                                            $is_late = $time_in_ts > $policy_in_time_ts; // Fixed: > for exactly on time
                                            $time_in_display = date('h:i A', $time_in_ts);
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
        let html5QrCode;
        let currentAction = ''; // 'time_in' or 'time_out'

        function startScan(action) {
            currentAction = action;
            qrReader.style.display = 'block';
            scanTimeInBtn.disabled = true;
            scanTimeOutBtn.disabled = false; // Allow switching
            scanTimeOutBtn.disabled = true;
            scanLabel.textContent = action === 'time_in' ? 'Scanning for Time In...' : 'Scanning for Time Out...';
            if (html5QrCode) {
                html5QrCode.stop().catch(()=>{});
                html5QrCode = null;
            }
            html5QrCode = new Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                qrCodeMessage => {
                    html5QrCode.stop().catch(()=>{});
                    qrReader.style.display = 'none';
                    scanTimeInBtn.disabled = false;
                    scanTimeOutBtn.disabled = false;
                    scanLabel.textContent = ''; // clear label
                    qrResult.innerHTML = "Processing QR...";

                    try {
                        const url = new URL(qrCodeMessage);
                        const params = new URLSearchParams(url.search);
                        const code = params.get('code');
                        const sessionId = params.get('session_id');
                        const date = params.get('date');

                        if (!code || !sessionId || !date) {
                            qrResult.innerHTML = "Invalid QR code format.";
                            return;
                        }

                        const endpoint = currentAction === 'time_out' ? 'time_out.php' : 'time_in.php';
                        fetch(`${endpoint}?code=${encodeURIComponent(code)}&session_id=${encodeURIComponent(sessionId)}&date=${encodeURIComponent(date)}`)
                            .then(response => response.text())
                            .then(data => {
                                qrResult.innerHTML = data;
                                setTimeout(() => location.reload(), 2000);
                            })
                            .catch(() => {
                                qrResult.innerHTML = "Failed to process QR.";
                            });
                    } catch (e) {
                        qrResult.innerHTML = "Invalid QR code content.";
                    }
                },
                errorMessage => {
                    // Optionally show scan errors
                }
            ).catch(() => {
                qrResult.innerHTML = "Unable to start camera.";
                scanTimeInBtn.disabled = false;
                scanTimeOutBtn.disabled = false;
                scanLabel.textContent = '';
            });
        }

        scanTimeInBtn.addEventListener('click', () => startScan('time_in'));
        scanTimeOutBtn.addEventListener('click', () => startScan('time_out'));
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>