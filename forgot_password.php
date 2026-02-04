<?php

include 'db.php';
include 'mail.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $oat->real_escape_string($_POST['email']);
    $user = $oat->query("SELECT id FROM users WHERE email='$email'")->fetch_assoc();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 120); // 2 minutes from now
        $user_id = $user['id'];
        $oat->query("INSERT INTO password_resets (user_id, token, expires_at) VALUES ($user_id, '$token', '$expires')");
        // Get local LAN IP address (Windows only, for XAMPP)
        function getLocalIp() {
            $ips = [];
            foreach (explode("\n", shell_exec('ipconfig 2>&1')) as $line) {
                if (preg_match('/IPv4.*?:\s*([\d\.]+)/', $line, $matches)) {
                    $ip = $matches[1];
                    if (strpos($ip, '192.168.') === 0) {
                        return $ip; // Prefer 192.168.x.x
                    }
                    if (strpos($ip, '10.') === 0 || preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $ip)) {
                        $ips[] = $ip; // Save other private IPs
                    }
                }
            }
            // If no 192.168.x.x, return another private IP if found
            if (!empty($ips)) return $ips[0];
            return getHostByName(getHostName());
        }
        function getAllLanIps() {
            $ips = [];
            foreach (explode("\n", shell_exec('ipconfig 2>&1')) as $line) {
                if (preg_match('/adapter (.+):/i', $line, $adapterMatch)) {
                    $currentAdapter = trim($adapterMatch[1]);
                }
                if (preg_match('/IPv4.*?:\s*([\d\.]+)/', $line, $matches)) {
                    $ip = $matches[1];
                    if (strpos($ip, '192.168.') === 0) {
                        $ips[$currentAdapter ?? 'Unknown'] = $ip;
                    }
                }
            }
            return $ips;
        }
        function getPreferredLanIp($preferred = 'Ethernet') {
            $currentAdapter = '';
            foreach (explode("\n", shell_exec('ipconfig 2>&1')) as $line) {
                if (preg_match('/adapter (.+):/i', $line, $adapterMatch)) {
                    $currentAdapter = trim($adapterMatch[1]);
                }
                if (preg_match('/IPv4.*?:\s*([\d\.]+)/', $line, $matches)) {
                    $ip = $matches[1];
                    if (strpos($ip, '192.168.') === 0 && stripos($currentAdapter, $preferred) !== false) {
                        return $ip;
                    }
                }
            }
            // fallback: first 192.168.x.x
            foreach (explode("\n", shell_exec('ipconfig 2>&1')) as $line) {
                if (preg_match('/IPv4.*?:\s*([\d\.]+)/', $line, $matches)) {
                    $ip = $matches[1];
                    if (strpos($ip, '192.168.') === 0) {
                        return $ip;
                    }
                }
            }
            return getHostByName(getHostName());
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];

        // If running locally (localhost or 127.0.0.1 or ::1), use hostname or LAN IP
        if (
            $host === 'localhost' ||
            $host === '127.0.0.1' ||
            $host === '::1'
        ) {
            $host = getHostName(); // or use your LAN IP logic if you prefer
        }

        $reset_link = "{$protocol}{$host}/OAT/reset_password.php?token=$token";
        if (send_reset_email($email, $reset_link)) {
            $message = "<div class='alert alert-success text-center mb-3'>Reset link sent to your email.</div>";
        } else {
            $message = "<div class='alert alert-danger text-center mb-3'>Failed to send email. Please try again later.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger text-center mb-3'>Email not found.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
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
        .forgot-glass {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(60,179,204,0.10);
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius: 18px;
            max-width: 420px;
            margin: 48px auto;
            padding: 36px 28px 28px 28px;
        }
        .forgot-title {
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
        .muted-link {
            color: var(--accent-deep);
        }
    </style>
</head>
<body>
    <div class="forgot-glass">
        <div class="forgot-title mb-2">Forgot Password</div>
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" required class="form-control" />
            </div>
            <button type="submit" class="btn-accent w-100 mb-2">Send Reset Link</button>
        </form>
        <div class="text-center mt-3">
            <a href="login.php" class="muted-link">Back to Login</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>