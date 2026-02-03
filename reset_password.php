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
            $message = "<span class='text-green-600'>Password reset successful. <a href='login.php' class='underline'>Login</a></span>";
            $show_form = false;
        }
    } else {
        $message = "<span class='text-red-600'>Invalid or expired reset link.</span>";
    }
} else {
    $message = "<span class='text-red-600'>Invalid request.</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-auto p-6 bg-white rounded-xl shadow-lg border-t-8 border-green-700">
        <h2 class="text-2xl font-bold text-green-700 mb-6 text-center">Reset Password</h2>
        <?php if ($message): ?>
            <div class="mb-4 text-center"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($show_form): ?>
        <form class="space-y-5" method="post" action="">
            <div>
                <label for="password" class="block text-green-700 font-medium mb-1">New Password</label>
                <input type="password" name="password" id="password" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition" />
            </div>
            <button type="submit"
                class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded-lg shadow transition">
                Change Password
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>