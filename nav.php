<?php

  if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
include_once 'db.php';

// Fetch user info for navbar
$user_id = $_SESSION['user_id'] ?? null;
$fname = $_SESSION['fname'] ?? '';
$lname = $_SESSION['lname'] ?? '';
$profile_img = '';

if ($user_id) {
    $user = $oat->query("SELECT profile_img FROM users WHERE id = $user_id")->fetch_assoc();
    if (!empty($user['profile_img'])) {
        $profile_img = $user['profile_img'];
    } else {
        // Fallback avatar
        $profile_img = 'https://ui-avatars.com/api/?name=' . urlencode(trim("$fname $lname"));
    }
}
?>
<nav class="bg-green-700 px-4 py-3 shadow-md">
    <!-- Loading Overlay -->
    <div id="loading-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(255,255,255,0.7); z-index:9999; align-items:center; justify-content:center;">
        <div class="flex flex-col items-center">
            <svg class="animate-spin h-12 w-12 text-green-700 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-green-700 font-semibold">Loading...</span>
        </div>
    </div>
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <span class="text-white font-bold text-xl tracking-wide">OJT Tracker</span>
        </div>
        <div class="hidden md:flex space-x-6">
            <a href="dashboard.php" class="text-white hover:text-green-200 font-medium transition">Dashboard</a>
            <a href="dtr.php" class="text-white hover:text-green-200 font-medium transition">DTR</a>
            <a href="profile.php" class="text-white hover:text-green-200 font-medium transition">Profile</a>
        </div>
        <div class="flex items-center space-x-3">
            <?php if ($user_id): ?>
                <img src="<?= htmlspecialchars($profile_img) ?>" alt="Profile" class="w-9 h-9 rounded-full border-2 border-green-300 object-cover bg-white">
                <span class="text-white font-semibold"><?= htmlspecialchars("$fname $lname") ?></span>
            <?php endif; ?>
            <a href="logout.php" class="bg-white text-green-700 px-4 py-1 rounded hover:bg-green-100 font-semibold transition md:inline-block hidden">Logout</a>
            <button id="menuBtn" class="md:hidden text-white focus:outline-none">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
    <!-- Mobile Menu -->
    <div id="mobileMenu" class="md:hidden hidden px-2 pt-2 pb-3 space-y-1 bg-green-700">
        <a href="dashboard.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">Dashboard</a>
        <a href="dtr.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">DTR</a>
        <a href="profile.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">Profile</a>
        <!-- Logout hidden in mobile menu -->
        <!-- <a href="logout.php" class="block text-green-700 bg-white hover:bg-green-100 rounded px-3 py-2 font-semibold">Logout</a> -->
    </div>
    <script>
        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        menuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // Loading overlay logic
        window.addEventListener('DOMContentLoaded', () => {
            const overlay = document.getElementById('loading-overlay');
            overlay.style.display = 'flex';
        });
        window.addEventListener('load', () => {
            const overlay = document.getElementById('loading-overlay');
            overlay.style.display = 'none';
        });
    </script>
</nav>
