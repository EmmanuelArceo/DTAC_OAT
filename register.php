<?php

include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if all fields are filled
    $required_fields = ['fname', 'lname', 'age', 'mobile', 'username', 'email', 'password', 'required_hours'];
    $all_filled = true;
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $all_filled = false;
            break;
        }
    }

    // Validate PH mobile number format: 09XXXXXXXXX (11 digits, starts with 09)
    $mobile = trim($_POST['mobile'] ?? '');
    $mobile_valid = preg_match('/^09\d{9}$/', $mobile);

    if (!$all_filled) {
        $message = "<div class='alert alert-danger text-center mb-2'>All fields are required.</div>";
    } elseif (!$mobile_valid) {
        $message = "<div class='alert alert-danger text-center mb-2'>Mobile number must be 11 digits and start with 09 (e.g., 09123456789).</div>";
    } else {
        $fname = $oat->real_escape_string($_POST['fname']);
        $lname = $oat->real_escape_string($_POST['lname']);
        $age = intval($_POST['age']);
        $mobile = $oat->real_escape_string($mobile);
        $username = $oat->real_escape_string($_POST['username']);
        $email = $oat->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $required_hours = floatval($_POST['required_hours']);
        $role = 'ojt';

        // Check if username, email, or mobile already exists
        $check = $oat->query("SELECT id FROM users WHERE username='$username' OR email='$email' OR mobile='$mobile'");
        if ($check->num_rows > 0) {
            $message = "<div class='alert alert-danger text-center mb-2'>Username, email, or mobile number already taken.</div>";
        }

        if (empty($message)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, email, password, role, fname, lname, age, mobile) VALUES ('$username', '$email', '$hashed_password', '$role', '$fname', '$lname', $age, '$mobile')";
            if ($oat->query($sql)) {
                $user_id = $oat->insert_id;
                $sql2 = "INSERT INTO ojt_requirements (user_id, required_hours) VALUES ($user_id, $required_hours)";
                $oat->query($sql2);
                header("Location: login.php");
                exit;
            } else {
                $message = "<div class='alert alert-danger text-center mb-2'>Error: " . $oat->error . "</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OJT Registration</title>
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
        .register-glass {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(60,179,204,0.10);
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius: 18px;
            max-width: 480px;
            margin: 48px auto;
            padding: 36px 28px 28px 28px;
        }
        .register-title {
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
        .toggle-btn {
            background: transparent;
            border: 0;
            color: var(--accent-deep);
            font-size: 1.1rem;
        }
        .muted-link {
            color: var(--accent-deep);
        }
    </style>
</head>
<body>
    <div class="register-glass">
        <div class="register-title mb-2">OJT Registration</div>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="post" action="" autocomplete="off">
            <div class="row mb-3">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label for="fname" class="form-label">First Name</label>
                    <input type="text" name="fname" id="fname" required class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="lname" class="form-label">Last Name</label>
                    <input type="text" name="lname" id="lname" required class="form-control" />
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6 mb-2 mb-md-0">
                    <label for="age" class="form-label">Age</label>
                    <input type="number" name="age" id="age" min="16" max="99" required class="form-control" />
                </div>
                <div class="col-md-6">
                    <label for="mobile" class="form-label">Mobile No.</label>
                    <input type="text" name="mobile" id="mobile" pattern="^09\d{9}$" maxlength="11" minlength="11" required class="form-control" placeholder="09123456789" />
                </div>
            </div>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" required class="form-control" />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" required class="form-control" />
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" required class="form-control" />
                    <button type="button" id="togglePassword" class="btn toggle-btn" tabindex="-1" aria-label="Toggle password">
                        <span id="eyeIcon" class="bi bi-eye"></span>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label for="required_hours" class="form-label">Required OJT Hours</label>
                <input type="number" name="required_hours" id="required_hours" min="1" step="0.01" required class="form-control" />
            </div>
            <button type="submit" class="btn-accent w-100 mb-2">Register</button>
        </form>
        <div class="text-center mt-3">
            <span class="text-muted">Already have an account?</span>
            <a href="login.php" class="muted-link ms-1">Login</a>
        </div>
    </div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        togglePassword.addEventListener('click', function () {
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