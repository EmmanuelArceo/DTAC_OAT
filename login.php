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
            if ($user['role'] === 'super_admin') {
                header("Location: admin\admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $message = "<span class='text-red-600'>Invalid username or password.</span>";
        }
    } else {
        $message = "<span class='text-red-600'>Invalid username or password.</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OJT Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-auto p-6 bg-white rounded-xl shadow-lg border-t-8 border-green-700">
        <h2 class="text-2xl font-bold text-green-700 mb-6 text-center">OJT Login</h2>
        <?php if ($message): ?>
            <div class="mb-4 text-center"><?= $message ?></div>
        <?php endif; ?>
        <form class="space-y-5" method="post" action="">
            <div>
                <label for="username" class="block text-green-700 font-medium mb-1">Username</label>
                <input type="text" name="username" id="username" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition" />
            </div>
            <div>
                <label for="password" class="block text-green-700 font-medium mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition pr-10" />
                    <button type="button" id="togglePassword" tabindex="-1"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-green-600 focus:outline-none">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>
            <button type="submit"
                class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded-lg shadow transition">
                Login
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-green-700">Don't have an account? <a href="register.php" class="underline font-medium">Register</a></p>
        <p class="mt-2 text-center text-sm text-green-700">
            <a href="forgot_password.php" class="underline font-medium hover:text-green-900 transition">Forgot Password?</a>
        </p>
    </div>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        let show = false;
        togglePassword.addEventListener('click', function () {
            show = !show;
            password.type = show ? 'text' : 'password';
            eyeIcon.innerHTML = show
                ? `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m1.414-1.414A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.043 5.197M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`
                : `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
        });
    </script>
</body>
</html>