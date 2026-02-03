<?php

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}
?>
<nav class="bg-green-700 px-4 py-3 shadow-md">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <span class="text-white font-bold text-xl tracking-wide">OJT Admin Panel</span>
        </div>
        <div class="hidden md:flex space-x-6">
            <a href="admin.php" class="text-white hover:text-green-200 font-medium transition">admin</a>
            <a href="qr_generator.php" class="text-white hover:text-green-200 font-medium transition">QR Generator</a>
          
            <a href="manage_ojt.php" class="text-white hover:text-green-200 font-medium transition">Manage OJT</a>
            <a href="reports.php" class="text-white hover:text-green-200 font-medium transition">Reports</a>
        </div>
        <div class="flex items-center space-x-2">
            <a href="../logout.php" class="bg-white text-green-700 px-4 py-1 rounded hover:bg-green-100 font-semibold transition">Logout</a>
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
        <a href="admin.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">admin</a>
        <a href="qr_generator.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">QR Generator</a>
      
        <a href="manage_ojt.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">Manage OJT</a>
        <a href="reports.php" class="block text-white hover:bg-green-600 rounded px-3 py-2">Reports</a>
        <a href="../logout.php" class="block text-green-700 bg-white hover:bg-green-100 rounded px-3 py-2 font-semibold">Logout</a>
    </div>
    <script>
        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        menuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</nav>