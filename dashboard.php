<?php

  if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
include 'db.php';

// Redirect to login if not signed in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? '';
$role = $_SESSION['role'] ?? 'ojt';

// Fetch required hours
$req = $oat->query("SELECT required_hours FROM ojt_requirements WHERE user_id = $user_id")->fetch_assoc();
$required_hours = (float)($req['required_hours'] ?? 0);

// Fetch completed hours (only for valid time_out records) with policy adjustments
$total_completed = 0;
$records = $oat->query("
    SELECT time_in, time_out 
    FROM ojt_records 
    WHERE user_id = $user_id 
    AND time_out IS NOT NULL 
    AND time_out != '00:00:00' 
    AND time_out > time_in
");
while ($row = $records->fetch_assoc()) {
    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);
    $base_hours = ($time_out - $time_in) / 3600; // Base hours

    // Deduct 1 hour for lunch only if time_in <= 12:00:00 and time_out >= 13:00:00
    $lunch_start = strtotime(date('Y-m-d', $time_in) . ' 12:00:00');
    $lunch_end = strtotime(date('Y-m-d', $time_in) . ' 13:00:00');
    $adjusted_hours = $base_hours;
    if ($time_in <= $lunch_start && $time_out >= $lunch_end) {
        $adjusted_hours -= 1;
    }

    // Deduct 1 hour if late (time_in after 8:00 AM)
    if (date('H:i:s', $time_in) > '08:00:00') {
        $adjusted_hours -= 1;
    }

    // Floor to whole hours (ignore minutes)
    $adjusted_hours = floor($adjusted_hours);

    // Ensure non-negative
    $total_completed += max(0, $adjusted_hours);
}
$completed_hours = $total_completed;

$remaining_hours = max(0, $required_hours - $completed_hours);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OJT Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-4xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-green-700 mb-2">Welcome, <?= htmlspecialchars($full_name) ?>!</h1>
        <p class="text-green-800 mb-6">Role: <span class="capitalize"><?= htmlspecialchars($role) ?></span></p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-green-100 rounded-lg p-5 text-center shadow">
                <div class="text-lg font-semibold text-green-700 mb-1">Required OJT Hours</div>
                <div class="text-3xl font-bold text-green-900"><?= number_format($required_hours, 0) ?></div>
            </div>
            <div class="bg-green-100 rounded-lg p-5 text-center shadow">
                <div class="text-lg font-semibold text-green-700 mb-1">Completed Hours</div>
                <div class="text-3xl font-bold text-green-900"><?= number_format($completed_hours, 0) ?></div>
            </div>
            <div class="bg-green-100 rounded-lg p-5 text-center shadow">
                <div class="text-lg font-semibold text-green-700 mb-1">Remaining Hours</div>
                <div class="text-3xl font-bold text-green-900"><?= number_format($remaining_hours, 0) ?></div>
            </div>
        </div>
        <div class="mt-8 text-center">
            <a href="dtr.php" class="inline-block bg-green-700 text-white px-6 py-2 rounded-lg font-semibold shadow hover:bg-green-800 transition">Go to DTR</a>
        </div>
    </div>
</body>
</html>