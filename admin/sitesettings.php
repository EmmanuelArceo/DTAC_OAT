<?php

include '../db.php';
 include 'nav.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch current settings or set defaults
$settings = $oat->query("SELECT * FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';
$default_time_out = $settings['default_time_out'] ?? '17:00:00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_time_in = $_POST['default_time_in'] ?? '08:00:00';
    $new_time_out = $_POST['default_time_out'] ?? '17:00:00';

    if ($settings) {
        $oat->query("UPDATE site_settings SET default_time_in='$new_time_in', default_time_out='$new_time_out' LIMIT 1");
    } else {
        $oat->query("INSERT INTO site_settings (default_time_in, default_time_out) VALUES ('$new_time_in', '$new_time_out')");
    }
    $default_time_in = $new_time_in;
    $default_time_out = $new_time_out;
    $success = "Settings updated!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f7fbfb; }
        .glass {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            max-width: 500px;
            margin: 40px auto;
            padding: 32px 24px;
        }
    </style>
</head>
<body>
    <div class="glass">
        <h3 class="mb-4">Site Settings</h3>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label for="default_time_in" class="form-label">Default Time In</label>
                <input type="time" id="default_time_in" name="default_time_in" class="form-control" value="<?= htmlspecialchars(substr($default_time_in,0,5)) ?>" required>
            </div>
            <div class="mb-3">
                <label for="default_time_out" class="form-label">Default Time Out</label>
                <input type="time" id="default_time_out" name="default_time_out" class="form-control" value="<?= htmlspecialchars(substr($default_time_out,0,5)) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</body>
</html>