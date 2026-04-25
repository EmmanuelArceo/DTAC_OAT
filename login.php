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
        $userStatus = strtolower(trim($user['status'] ?? ''));

        if ($userStatus === 'restricted') {
            $message = "<div class='alert alert-danger mb-3'>Your account is restricted and cannot log in.</div>";
        } elseif (password_verify($password, $user['password'])) {
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

        :root {
            --accent: #6b8f71;
            --accent-deep: #59705a;
            --accent-soft: #8ca987;
            --accent-contrast: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            min-height: 100vh;
            background: radial-gradient(circle at top left, rgba(107,143,113,0.18), transparent 32%),
                        radial-gradient(circle at bottom right, rgba(89,112,90,0.16), transparent 28%),
                        linear-gradient(180deg, #eef7f0 0%, #f9fcfb 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            overflow-x: hidden;
        }

        .login-brand {
            display: flex;
            justify-content: center;
            margin-bottom: 22px;
        }

        .login-brand img {
            width: 250px;
            height: auto;
            border-radius: 20px;
            object-fit: contain;   
        }

        .card-glass {
            width: min(100%, 420px);
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(107,143,113,0.18);
            border-radius: 34px;
            padding: 40px 34px 32px;
            box-shadow: 0 24px 70px rgba(15,23,42,0.12);
            backdrop-filter: blur(18px);
        }

        .login-heading {
            margin: 0 0 10px;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--accent-deep);
            letter-spacing: -0.02em;
            text-align: center;
        }

        .login-subtitle {
            margin: 0 0 28px;
            color: #45544c;
            font-size: 0.95rem;
            text-align: center;
        }

        .form-control-custom {
            width: 100%;
            border-radius: 999px;
            border: 1px solid rgba(89,112,90,0.18);
            padding: 14px 18px;
            margin-bottom: 16px;
            font-size: 0.95rem;
            color: #0f172a;
            background: #ffffff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--accent-deep);
            box-shadow: 0 0 0 0.2rem rgba(107,143,113,0.18);
        }

        .password-row {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 14px;
            top: 38%;
            right: 1%;
            transform: translateY(-50%);
            border: none;
            background: rgba(107,143,113,0.12);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-deep);
            cursor: pointer;
        }

        .password-toggle-btn:hover {
            background: rgba(107,143,113,0.18);
        }

        .btn-login-custom {
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 0;
            border-radius: 999px;
            border: none;
            background: linear-gradient(90deg, var(--accent), var(--accent-deep));
            color: var(--accent-contrast);
            font-weight: 700;
            font-size: 0.98rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 14px 30px rgba(89,112,90,0.16);
        }

        .btn-login-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(89,112,90,0.2);
        }

        .links {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.88rem;
            color: #55655a;
            flex-wrap: wrap;
        }

        .links a {
            color: var(--accent-deep);
            text-decoration: none;
            font-weight: 600;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .alert-custom {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 18px;
            font-size: 0.9rem;
            background: rgba(255, 229, 229, 0.95);
            border: 1px solid rgba(239, 87, 87, 0.2);
            color: #8f1c1c;
        }

        @media (max-width: 520px) {
            .navbar-brand-custom {
                top: 12px;
                left: 12px;
                padding: 10px 12px;
            }

            .navbar-brand-custom img {
                height: 42px;
            }

            .card-glass {
                padding: 28px 22px;
                border-radius: 28px;
            }

            .login-heading {
                font-size: 1.5rem;
            }

            .form-control-custom {
                padding: 13px 16px;
            }

            .password-toggle-btn {
                width: 38px;
                height: 38px;
                right: 12px;
            }
        }
    </style>
</head>
<body>
        <div class="card-glass">
            <div class="login-brand">
                <img src="922abd68-1446-4ad9-b263-9ff3a11938cc.png" alt="OJT Logo">
            </div>
            <h1 class="login-heading">Welcome back</h1>
            <p class="login-subtitle">Sign in to your OJT account</p>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <input id="username" name="username" type="text" class="form-control-custom" placeholder="Username" required autofocus />
            </div>

            <div class="mb-3 password-row">
                <input id="password" name="password" type="password" class="form-control-custom" placeholder="Password" required />
                <button type="button" id="togglePassword" class="password-toggle-btn" aria-label="Show password">
                    <i id="eyeIcon" class="bi bi-eye"></i>
                </button>
            </div>

            <div class="d-grid mb-3">
                <button type="submit" class="btn-login-custom">Login</button>
            </div>

            <div class="links">
                <a href="register.php">Create an account</a>
                <a href="forgot_password.php">Forgot Password?</a>
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