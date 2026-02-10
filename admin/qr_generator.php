<?php

include '../db.php';

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['type'])) {
    header('Content-Type: application/json');
    $type = $_GET['type'] === 'time_out' ? 'time_out' : 'time_in';
    echo json_encode(generate_qr_data($type));
    exit;
}

include 'nav.php';

// Generate a new code every 2 seconds (global QR, not per user)
function generate_qr_data($type) {
    $now = time();
    $interval = 2;
    $code_time = floor($now / $interval) * $interval;
    $salt = $type === 'time_out' ? 'your_time_out_secret_salt' : 'your_global_secret_salt';
    $unique_code = hash('sha256', $code_time . $salt . $type);
    $expiry = date('Y-m-d H:i:s', $code_time + $interval);
    $date = date('Y-m-d'); // Current date
    $session_id = uniqid('', true); // Unique identifier

    global $oat;
    // Upsert the QR code (only one active at a time per type)
    $oat->query("DELETE FROM qr_codes WHERE type='$type'");
    $oat->query("INSERT INTO qr_codes (code, expires_at, type, session_id, date) VALUES ('$unique_code', '$expiry', '$type', '$session_id', '$date')");

    $qr_url = "http://{$_SERVER['HTTP_HOST']}/OAT/{$type}.php?code=$unique_code&session_id=$session_id&date=$date";
    $qr_img = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_url);

    return [
        'qr_img' => $qr_img,
        'expiry' => $expiry
    ];
}

$time_in_data = generate_qr_data('time_in');
$time_out_data = generate_qr_data('time_out');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin QR Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4c8eb1;
            --primary-dark: #3cb2cc;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            /* Lighter glassy, light blue background */
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .qr-glass {
            background: rgba(60, 178, 204, 0.09); /* lighter glassy effect */
            border-radius: 22px;
            box-shadow: 0 8px 32px 0 rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
            max-width: 700px;
            margin: 48px auto;
            padding: 2.5rem 2rem;
        }
        .qr-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
            text-align: center;
        }
        .toggle-btn {
            min-width: 180px;
            font-weight: 600;
            font-size: 1.1rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0.6rem 1.5rem;
            margin-bottom: 1.5rem;
            transition: background 0.2s;
        }
        .toggle-btn:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        .qr-section {
            transition: box-shadow 0.2s;
            background: #fff;
            border-radius: 1.5rem;
            padding: 2rem 1.5rem;
            margin: 0 0.5rem;
            min-width: 320px;
            max-width: 100%;
            box-shadow: 0 2px 12px 0 rgba(76,142,177,0.08);
            border: 2px solid transparent;
        }
        .qr-section.active {
            box-shadow: 0 4px 24px 0 rgba(76,142,177,0.15);
            border: 2px solid var(--primary-dark);
        }
        .qr-img {
            width: 200px;
            height: 200px;
            object-fit: contain;
            background: #fff;
            border-radius: 1rem;
            border: 2px solid #e0e0e0;
            margin-bottom: 1rem;
        }
        .qr-label {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .qr-label.time-in { color: var(--success);}
        .qr-label.time-out { color: var(--primary);}
        .qr-expiry {
            font-size: 0.98rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        .qr-note {
            margin-top: 2rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.98rem;
        }
        @media (max-width: 767.98px) {
            .qr-glass { padding: 1rem 0.2rem; }
            .qr-title { font-size: 1.2rem; }
            .qr-section { padding: 1.1rem 0.5rem; min-width: 0; }
            .qr-img { width: 140px; height: 140px; }
        }
    </style>
</head>
<body>
    <div class="qr-glass">
        <div class="qr-title">
            <i class="bi bi-qr-code-scan me-2"></i>OJT QR Generators
        </div>
        <div class="text-center mb-4">
            <button id="toggle-qr-btn" class="toggle-btn">Show Time Out QR</button>
        </div>
        <div class="d-flex justify-content-center flex-wrap">
            <!-- Time In QR -->
            <div id="time-in-section" class="qr-section active d-flex flex-column align-items-center">
                <div class="qr-label time-in"><i class="bi bi-box-arrow-in-right"></i> Time In QR Code</div>
                <img id="qr-img-time_in" src="<?= $time_in_data['qr_img'] ?>" alt="Time In QR Code" class="qr-img" />
                <div id="qr-expiry-time-in" class="qr-expiry">Expires in: <span id="qr-expiry-seconds-time_in"></span>s</div>
            </div>
            <!-- Time Out QR -->
            <div id="time-out-section" class="qr-section d-none d-flex flex-column align-items-center">
                <div class="qr-label time-out"><i class="bi bi-box-arrow-right"></i> Time Out QR Code</div>
                <img id="qr-img-time_out" src="<?= $time_out_data['qr_img'] ?>" alt="Time Out QR Code" class="qr-img" />
                <div id="qr-expiry-time-out" class="qr-expiry">Expires in: <span id="qr-expiry-seconds-time_out"></span>s</div>
            </div>
        </div>
        <div class="qr-note">
            QR codes refresh every 2 seconds and are valid only for today.
        </div>
    </div>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastExpiryTimeIn = '<?= $time_in_data['expiry'] ?>';
        let lastExpiryTimeOut = '<?= $time_out_data['expiry'] ?>';
        let showingTimeOut = false;

        const toggleBtn = document.getElementById('toggle-qr-btn');
        const timeInSection = document.getElementById('time-in-section');
        const timeOutSection = document.getElementById('time-out-section');

        function parseExpiry(expiryStr) {
            return new Date(expiryStr.replace(' ', 'T'));
        }

        function updateCountdown(type, expiryStr) {
            const expiryDate = parseExpiry(expiryStr);
            const now = new Date();
            let diff = Math.floor((expiryDate - now) / 1000);
            if (diff < 0) diff = 0;
            const expirySecondsElem = document.getElementById(`qr-expiry-seconds-${type}`);
            if (expirySecondsElem) {
                expirySecondsElem.textContent = diff;
            }
            return diff;
        }

        function refreshQR(type) {
            fetch(`qr_generator.php?ajax=1&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    const imgElem = document.getElementById(`qr-img-${type}`);
                    if (imgElem) {
                        imgElem.src = data.qr_img;
                    }
                    if (type === 'time_in') {
                        lastExpiryTimeIn = data.expiry;
                    } else {
                        lastExpiryTimeOut = data.expiry;
                    }
                    // Immediately update countdown after refresh
                    updateCountdown(type, data.expiry);
                });
        }

        // Unified countdown and refresh loop for both QR codes
        setInterval(() => {
            let diffIn = updateCountdown('time_in', lastExpiryTimeIn);
            let diffOut = updateCountdown('time_out', lastExpiryTimeOut);

            if (diffIn === 0) refreshQR('time_in');
            if (diffOut === 0) refreshQR('time_out');
        }, 1000);

        toggleBtn.addEventListener('click', () => {
            const confirmChange = confirm('Confirm change QR?');
            if (!confirmChange) return;

            showingTimeOut = !showingTimeOut;
            if (showingTimeOut) {
                timeInSection.classList.add('d-none');
                timeInSection.classList.remove('active');
                timeOutSection.classList.remove('d-none');
                timeOutSection.classList.add('active');
                toggleBtn.textContent = 'Show Time In QR';
            } else {
                timeOutSection.classList.add('d-none');
                timeOutSection.classList.remove('active');
                timeInSection.classList.remove('d-none');
                timeInSection.classList.add('active');
                toggleBtn.textContent = 'Show Time Out QR';
            }
        });

        // Initial countdown display
        updateCountdown('time_in', lastExpiryTimeIn);
        updateCountdown('time_out', lastExpiryTimeOut);
    });
    </script>
</body>
</html>