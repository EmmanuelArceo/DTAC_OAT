<?php

include 'db.php';
include 'mail.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $oat->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $oat->query("SELECT * FROM users WHERE username='$username' LIMIT 1");
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Start session and redirect (customize as needed)
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            if (in_array($user['role'], ['super_admin', 'admin'])) {
                header("Location: admin/admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $message = "<div class='alert alert-danger mb-3'>Invalid username or password.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger mb-3'>Invalid username or password.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OJT Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #3CB3CC; --accent-deep: #2aa0b3; }
        body{
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            background: linear-gradient(135deg,#f6fbfb 0%, #eef9fa 50%, #f9fcfd 100%);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            color:#0f172a;
        }
        .card-glass{
            width:100%; max-width:420px;
            background: rgba(255,255,255,0.65);
            border: 1px solid rgba(60,179,204,0.10);
            backdrop-filter: blur(8px) saturate(120%);
            box-shadow: 0 12px 40px rgba(15,23,42,0.06);
            border-radius:14px; padding:28px;
        }
        .brand {
            display:flex; align-items:center; gap:10px; margin-bottom:8px;
        }
        .brand .logo {
            width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-deep));
            box-shadow:0 8px 20px rgba(15,23,42,0.06);
        }
        .brand h2{ margin:0; font-size:1.25rem; font-weight:800; color:var(--accent-deep); }
        .form-label { color: #0f172a; font-weight:600; }
        .form-control:focus { box-shadow: 0 0 0 0.2rem rgba(60,179,204,0.12); border-color: var(--accent-deep); }
        .btn-accent {
            background: linear-gradient(90deg,var(--accent) 0%, var(--accent-deep) 100%);
            border: none; color: #fff; font-weight:700; padding:10px 14px; border-radius:10px;
        }
        .toggle-btn {
            background: transparent; border:0; color:var(--accent-deep); font-size:1.05rem;
        }
        .muted-link { color: var(--accent-deep); }
    </style>
</head>
<body>
    <div class="card-glass">
        <div class="brand">
            <div class="logo" aria-hidden="true"></div>
            <h2>OJT Tracker</h2>
        </div>

        <p class="mb-3" style="font-weight:700;color:#0f172a">Sign in to your account</p>

        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input id="username" name="username" type="text" class="form-control" required autofocus />
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input id="password" name="password" type="password" class="form-control" required />
                    <button type="button" id="togglePassword" class="btn toggle-btn" aria-label="Toggle password">
                        <i id="eyeIcon" class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-accent">Login</button>
            </div>

            <div class="d-flex justify-content-between">
                <small><a href="register.php" class="muted-link">Register</a></small>
                <small><a href="forgot_password.php" class="muted-link">Forgot Password?</a></small>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        togglePassword.addEventListener('click', () => {
            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.className = 'bi bi-eye-slash';
            } else {
                password.type = 'password';
                eyeIcon.className = 'bi bi-eye';
            }
        });
    </script>
</body>
</html>