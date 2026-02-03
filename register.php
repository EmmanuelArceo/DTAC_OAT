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
        $message = "<span class='text-red-600'>All fields are required.</span>";
    } elseif (!$mobile_valid) {
        $message = "<span class='text-red-600'>Mobile number must be 11 digits and start with 09 (e.g., 09123456789).</span>";
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
            $message = "<span class='text-red-600'>Username, email, or mobile number already taken.</span>";
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
                $message = "<span class='text-red-600'>Error: " . $oat->error . "</span>";
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
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in-up {
            opacity: 0;
            transform: translateY(40px);
            animation: fadeInUp 0.7s cubic-bezier(.4,0,.2,1) forwards;
        }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-green-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md mx-auto p-8 rounded-2xl shadow-2xl border-t-8 border-green-700 fade-in-up
        bg-white/60 backdrop-blur-md backdrop-saturate-150"
        style="box-shadow: 0 8px 32px 0 rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.15);">
        <h2 class="text-3xl font-extrabold text-green-700 mb-2 text-center tracking-tight">OJT Registration</h2>
        <p class="text-green-500 text-center mb-6 text-sm font-medium">Minimalist, Secure, and Fast</p>
        <?php if ($message): ?>
            <div class="mb-4 text-center animate-pulse"><?= $message ?></div>
        <?php endif; ?>
        <form class="space-y-5" method="post" action="">
            <div class="flex gap-3">
                <div class="w-1/2">
                    <label for="fname" class="block text-green-700 font-medium mb-1">First Name</label>
                    <input type="text" name="fname" id="fname" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
                </div>
                <div class="w-1/2">
                    <label for="lname" class="block text-green-700 font-medium mb-1">Last Name</label>
                    <input type="text" name="lname" id="lname" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-1/2">
                    <label for="age" class="block text-green-700 font-medium mb-1">Age</label>
                    <input type="number" name="age" id="age" min="16" max="99" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
                </div>
                <div class="w-1/2">
                    <label for="mobile" class="block text-green-700 font-medium mb-1">Mobile No.</label>
                    <input type="text" name="mobile" id="mobile" pattern="^09\d{9}$" maxlength="11" minlength="11" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50"
                        placeholder="09123456789" />
                </div>
            </div>
            <div>
                <label for="username" class="block text-green-700 font-medium mb-1">Username</label>
                <input type="text" name="username" id="username" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
            </div>
            <div>
                <label for="email" class="block text-green-700 font-medium mb-1">Email</label>
                <input type="email" name="email" id="email" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
            </div>
            <div>
                <label for="password" class="block text-green-700 font-medium mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required
                        class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition pr-10 bg-green-50" />
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
            <div>
                <label for="required_hours" class="block text-green-700 font-medium mb-1">Required OJT Hours</label>
                <input type="number" name="required_hours" id="required_hours" min="1" step="0.01" required
                    class="w-full px-4 py-2 border border-green-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition bg-green-50" />
            </div>
            <button type="submit"
                class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold py-2 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105">
                Register
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-green-700">Already have an account? <a href="login.php" class="underline font-medium hover:text-green-900 transition">Login</a></p>
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

        // Animate form on load
        window.addEventListener('DOMContentLoaded', () => {
            document.querySelector('.fade-in-up').style.opacity = 1;
        });
    </script>
</body>
</html>