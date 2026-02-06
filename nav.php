<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'db.php';

// Force relogin if role is super_admin or admin
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: login.php");
    exit;
}

// Fetch user info for navbar
$user_id = $_SESSION['user_id'] ?? null;
$fname = $_SESSION['fname'] ?? '';
$lname = $_SESSION['lname'] ?? '';
$role = $_SESSION['role'] ?? '';
$position = '';
$profile_img = '';

if ($user_id) {
    $user = $oat->query("SELECT profile_img, position FROM users WHERE id = $user_id")->fetch_assoc();
    if (!empty($user['profile_img'])) {
        $profile_img = $user['profile_img'];
    } else {
        // Fallback avatar
        $profile_img = 'https://ui-avatars.com/api/?name=' . urlencode(trim("$fname $lname")) . '&background=3CB3CC&color=fff';
    }
    $position = $user['position'] ?? '';
}
?>
<style>
.ojt-navbar {
    background: linear-gradient(90deg, #2aa0b3);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.ojt-profile-img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
}
.ojt-avatar-name {
    font-weight: 600;
    color: #fff;
}
.navbar-toggler {
    border: none;
    background: transparent;
}
.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.5%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='m4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}
.dropdown-menu {
    border: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    /* Removed opacity and transition for instant show/hide */
}
</style>

<nav class="navbar navbar-expand-lg ojt-navbar shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" class="me-2" xmlns="http://www.w3.org/2000/svg">
                <rect width="24" height="24" rx="6" fill="url(#g)"/>
                <defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#3CB3CC"/><stop offset="1" stop-color="#2aa0b3"/></linearGradient></defs>
            </svg>
            <span class="text-white fw-bold">Attendance Tracker</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ojtNavbarMenu" aria-controls="ojtNavbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="ojtNavbarMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-3">
                <li class="nav-item"><a class="nav-link text-white" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="dtr.php">DTR</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="profile.php">Profile</a></li>
            </ul>

            <div class="d-flex align-items-center">
                <?php if ($user_id): ?>
                    <div class="dropdown">
                        <button class="btn btn-link p-0 text-decoration-none" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= htmlspecialchars($profile_img) ?>" alt="Profile" class="ojt-profile-img">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><h6 class="dropdown-header"><?= htmlspecialchars(trim("$fname $lname")) ?></h6></li>
                            <li><small class="dropdown-item-text text-muted"><?= htmlspecialchars($role) ?> - <?= htmlspecialchars($position) ?></small></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profile.php">View Profile</a></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" role="status" aria-hidden="true">
        <div class="text-center">
            <div class="spinner-border text-secondary" role="status" style="width:3rem;height:3rem">
                <span class="visually-hidden">Loading</span>
            </div>
            <div class="mt-2" style="color:#0f172a;font-weight:600">Loading</div>
        </div>
    </div>
</nav>

<script>
    // Loading overlay logic without animations
    window.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'flex';
    });
    window.addEventListener('load', () => {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.style.display = 'none';
    });

    // Show loading overlay if page is loading
    document.onreadystatechange = function () {
        const overlay = document.getElementById('loading-overlay');
        if (!overlay) return;
        if (document.readyState !== "complete") {
            overlay.style.display = 'flex';
        } else {
            overlay.style.display = 'none';
        }
    };

    // Close mobile menu on link click
    document.querySelectorAll('#ojtNavbarMenu .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            const navbarCollapse = document.getElementById('ojtNavbarMenu');
            if (navbarCollapse.classList.contains('show')) {
                new bootstrap.Collapse(navbarCollapse, { hide: true });
            }
        });
    });
</script>
