<?php


include '../db.php';
include 'nav.php';

// Generate a new code every 30 seconds (global QR, not per user)
function generate_qr_data($type) {
    $now = time();
    $interval = 30;
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

// Handle AJAX request
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['type'])) {
    header('Content-Type: application/json');
    $type = $_GET['type'] === 'time_out' ? 'time_out' : 'time_in';
    echo json_encode(generate_qr_data($type));
    exit;
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen">
    <div class="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-green-700 mb-6 text-center">OJT QR Generators</h1>
        <div class="mb-4 text-center">
            <button id="toggle-qr-btn" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">
                Show Time Out QR
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Time In QR -->
            <div id="time-in-section" class="flex flex-col items-center bg-green-100 rounded-lg p-4 shadow">
                <h2 class="text-lg font-semibold text-green-700 mb-2">Time In QR Code</h2>
                <img id="qr-img-time-in" src="<?= $time_in_data['qr_img'] ?>" alt="Time In QR Code" class="mb-2 rounded shadow border border-green-200" />
                <div id="qr-expiry-time-in" class="text-xs text-gray-500">Expires at: <?= htmlspecialchars($time_in_data['expiry']) ?></div>
            </div>
            <!-- Time Out QR -->
            <div id="time-out-section" class="hidden flex flex-col items-center bg-blue-100 rounded-lg p-4 shadow">
                <h2 class="text-lg font-semibold text-blue-700 mb-2">Time Out QR Code</h2>
                <img id="qr-img-time-out" src="<?= $time_out_data['qr_img'] ?>" alt="Time Out QR Code" class="mb-2 rounded shadow border border-blue-200" />
                <div id="qr-expiry-time-out" class="text-xs text-gray-500">Expires at: <?= htmlspecialchars($time_out_data['expiry']) ?></div>
            </div>
        </div>
        <p class="mt-8 text-center text-sm text-gray-400">QR codes refresh every 30 seconds and are valid only for today.</p>
    </div>
    <script>
        let lastExpiryTimeIn = '<?= $time_in_data['expiry'] ?>';
        let lastExpiryTimeOut = '<?= $time_out_data['expiry'] ?>';
        let showingTimeOut = false;

        const toggleBtn = document.getElementById('toggle-qr-btn');
        const timeInSection = document.getElementById('time-in-section');
        const timeOutSection = document.getElementById('time-out-section');

        toggleBtn.addEventListener('click', () => {
            const confirmChange = confirm('Confirm change QR?');
            if (!confirmChange) return;

            showingTimeOut = !showingTimeOut;
            if (showingTimeOut) {
                timeInSection.classList.add('hidden');
                timeOutSection.classList.remove('hidden');
                toggleBtn.textContent = 'Show Time In QR';
            } else {
                timeOutSection.classList.add('hidden');
                timeInSection.classList.remove('hidden');
                toggleBtn.textContent = 'Show Time Out QR';
            }
        });

        function refreshQR(type) {
            fetch(`qr_generator.php?ajax=1&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    const lastExpiry = type === 'time_in' ? lastExpiryTimeIn : lastExpiryTimeOut;
                    if (data.expiry !== lastExpiry) {
                        document.getElementById(`qr-img-${type}`).src = data.qr_img;
                        document.getElementById(`qr-expiry-${type}`).textContent = "Expires at: " + data.expiry;
                        if (type === 'time_in') lastExpiryTimeIn = data.expiry;
                        else lastExpiryTimeOut = data.expiry;
                    }
                });
        }
        setInterval(() => refreshQR('time_in'), 30000);
        setInterval(() => refreshQR('time_out'), 30000);
    </script>
</body>
</html>