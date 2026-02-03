

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-green-50 to-green-100 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-5xl mx-auto mt-10 p-8 bg-white/60 rounded-2xl shadow-2xl border-t-8 border-green-700 backdrop-blur-md backdrop-saturate-150">
        <h1 class="text-3xl font-extrabold text-green-700 mb-4 text-center">Welcome, Super Admin!</h1>
        <p class="text-green-600 text-center mb-8">You have full control over the OJT system.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <a href="manage_admins.php" class="bg-green-100 hover:bg-green-200 rounded-xl p-6 flex flex-col items-center shadow transition-all duration-200">
                <svg class="w-12 h-12 text-green-700 mb-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6 5.87v-2a4 4 0 00-3-3.87m6 5.87v-2a4 4 0 013-3.87M12 12a4 4 0 100-8 4 4 0 000 8z" />
                </svg>
                <span class="font-bold text-green-800 text-lg">Manage Admins</span>
                <span class="text-green-600 text-sm mt-1 text-center">Add, edit, or remove admin accounts.</span>
            </a>
            <a href="site_settings.php" class="bg-green-100 hover:bg-green-200 rounded-xl p-6 flex flex-col items-center shadow transition-all duration-200">
                <svg class="w-12 h-12 text-green-700 mb-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-bold text-green-800 text-lg">Site Settings</span>
                <span class="text-green-600 text-sm mt-1 text-center">Configure system-wide settings.</span>
            </a>
            <a href="../logout.php" class="bg-green-100 hover:bg-green-200 rounded-xl p-6 flex flex-col items-center shadow transition-all duration-200">
                <svg class="w-12 h-12 text-green-700 mb-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h4a2 2 0 012 2v1" />
                </svg>
                <span class="font-bold text-green-800 text-lg">Logout</span>
                <span class="text-green-600 text-sm mt-1 text-center">Sign out of your account.</span>
            </a>
        </div>
    </div>
</body>
</html>