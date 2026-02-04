<?php

session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch DTR records for this OJT
$dtr_query = $oat->query("SELECT date, time_in, time_out FROM ojt_records WHERE user_id = $user_id ORDER BY date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My DTR Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body class="bg-green-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-3xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-green-700 mb-6">My DTR Report</h1>
        <div class="mb-6 text-center flex justify-center space-x-4">
            <button id="scan-time-in-btn" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
                Scan Time In QR
            </button>
            <button id="scan-time-out-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
                Scan Time Out QR
            </button>
        </div>
        <div id="qr-reader" style="width: 300px; margin: 0 auto; display: none;"></div>
        <div id="qr-result" class="text-center mt-4 font-semibold"></div>
        <div class="overflow-x-auto mt-8">
            <table class="min-w-full bg-white rounded-lg shadow">
                <thead>
                    <tr>
                        <th class="py-2 px-4 bg-green-700 text-white">Date</th>
                        <th class="py-2 px-4 bg-green-700 text-white">Time In</th>
                        <th class="py-2 px-4 bg-green-700 text-white">Time Out</th>
                        <th class="py-2 px-4 bg-green-700 text-white">Late</th>
                        <th class="py-2 px-4 bg-green-700 text-white">Total Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dtr_query->num_rows > 0): ?>
                        <?php while ($row = $dtr_query->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-green-50 transition">
                                <td class="py-2 px-4 text-center"><?= htmlspecialchars($row['date']) ?></td>
                                <td class="py-2 px-4 text-center">
                                    <?= $row['time_in'] ? date("g:i A", strtotime($row['time_in'])) : '' ?>
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <?= ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date("g:i A", strtotime($row['time_out'])) : '' ?>
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <?php
                                    $late = 'No';
                                    if ($row['time_in'] && date('H:i:s', strtotime($row['time_in'])) > '08:00:00') {
                                        $late = 'Yes';
                                    }
                                    echo $late;
                                    ?>
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $time_in = strtotime($row['time_in']);
                                        $time_out = strtotime($row['time_out']);
                                        $hours = floor(($time_out - $time_in) / 3600);

                                        // Deduct 1 hour only if time_in <= 12:00:00 and time_out >= 13:00:00
                                        $lunch_start = strtotime(date('Y-m-d', $time_in) . ' 12:00:00');
                                        $lunch_end = strtotime(date('Y-m-d', $time_in) . ' 13:00:00');
                                        if ($time_in <= $lunch_start && $time_out >= $lunch_end) {
                                            $hours -= 1;
                                        }

                                        if ($late === 'Yes') {
                                            $hours -= 1; // Deduct for late
                                        }
                                        echo max(0, $hours);
                                    } else {
                                        echo '';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500">No DTR records found.</td>
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
        let html5QrCode;
        let currentAction = ''; // 'time_in' or 'time_out'

        function startScan(action) {
            currentAction = action;
            qrReader.style.display = 'block';
            scanTimeInBtn.style.display = 'none';
            scanTimeOutBtn.style.display = 'none';
            html5QrCode = new Html5Qrcode("qr-reader");
            html5QrCode.start(
                { facingMode: "environment" },
                {
                    fps: 10,
                    qrbox: 250
                },
                qrCodeMessage => {
                    html5QrCode.stop();
                    qrReader.style.display = 'none';
                    scanTimeInBtn.style.display = 'inline-block';
                    scanTimeOutBtn.style.display = 'inline-block';
                    qrResult.innerHTML = "Processing QR...";

                    // Parse QR URL and send to appropriate endpoint
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
                },
                errorMessage => {
                    // Optionally show scan errors
                }
            );
        }

        scanTimeInBtn.addEventListener('click', () => startScan('time_in'));
        scanTimeOutBtn.addEventListener('click', () => startScan('time_out'));
    </script>
</body>
</html>