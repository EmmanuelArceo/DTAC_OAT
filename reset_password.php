<?php

include 'db.php';
$message = "";
$show_form = false;

if (isset($_GET['token'])) {
    $token = $oat->real_escape_string($_GET['token']);
    $now = date('Y-m-d H:i:s');
    $result = $oat->query("SELECT * FROM password_resets WHERE token='$token' AND expires_at > '$now' LIMIT 1");
    if ($result && $result->num_rows === 1) {
        $reset = $result->fetch_assoc();
        $show_form = true;
        $user_id = $reset['user_id'];
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['password'])) {
            $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $oat->query("UPDATE users SET password='$new_password' WHERE id=$user_id");
            $oat->query("DELETE FROM password_resets WHERE user_id=$user_id");
            $message = "<div class='alert alert-success text-center mb-3'>Password reset successful. <a href='login.php' class='alert-link'>Login</a></div>";
            $show_form = false;
        }
    } else {
        $message = "<div class='alert alert-danger text-center mb-3'>Invalid or expired reset link.</div>";
    }
} else {
    $message = "<div class='alert alert-danger text-center mb-3'>Invalid request.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #3CB3CC;
            --accent-deep: #2aa0b3;
        }
        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            background: linear-gradient(135deg, #f7fbfb 0%, #fbfcfd 100%);
            min-height: 100vh;
            color: #0f172a;
        }
        .reset-glass {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(60,179,204,0.10);
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius: 18px;
            max-width: 420px;
            margin: 48px auto;
            padding: 36px 28px 28px 28px;
        }
        .reset-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-deep);
            margin-bottom: 8px;
            text-align: center;
        }
        .form-label {
            color: var(--accent-deep);
            font-weight: 600;
        }
        .form-control:focus {
            border-color: var(--accent-deep);
            box-shadow: 0 0 0 0.2rem rgba(60,179,204,0.10);
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
    </style>
</head>
<body>
    <div class="reset-glass">
        <div class="reset-title mb-2">Reset Password</div>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <?php if ($show_form): ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" name="password" id="password" required class="form-control" />
            </div>
            <button type="submit" class="btn-accent w-100 mb-2">Change Password</button>
        </form>
        <?php endif; ?>
        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none" style="color:var(--accent-deep)">Back to Login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>