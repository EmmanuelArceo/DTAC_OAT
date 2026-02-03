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
        $reset_link = "http://{$_SERVER['HTTP_HOST']}/OAT/reset_password.php?token=$token";
        if (send_reset_email($email, $reset_link)) {
            $message = "<span class='text-green-600'>Reset link sent to your email.</span>";
        } else {
            $message = "<span class='text-red-600'>Failed to send email. Please try again later.</span>";
        }
    } else {
        $message = "<span class='text-red-600'>Email not found.</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-auto p-6 bg-white rounded-xl shadow-lg border-t-8 border-green-700">
        <h2 class="text-2xl font-bold text-green-700 mb-6 text-center">Forgot Password</h2>
        <?php if ($message): ?>
            <div class="mb-4 text-center"><?= $message ?></div>
        <?php endif; ?>
        <form class="space-y-5" method="post" action="">
            <div>
                <label for="email" class="block text-green-700 font-medium mb-1">Email</label>
                <input type="email" name="email" id="email" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition" />
            </div>
            <button type="submit"
                class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded-lg shadow transition">
                Send Reset Link
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-green-700"><a href="login.php" class="underline font-medium">Back to Login</a></p>
    </div>
</body>
</html>