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
$profile_img = '';

if ($user_id) {
    $user = $oat->query("SELECT profile_img FROM users WHERE id = $user_id")->fetch_assoc();
    if (!empty($user['profile_img'])) {
        $profile_img = $user['profile_img'];
    } else {
        // Fallback avatar
        $profile_img = 'https://ui-avatars.com/api/?name=' . urlencode(trim("$fname $lname")) . '&background=3CB3CC&color=fff';
    }
}
?>
<style>
    :root {
        --accent: #3CB3CC;
        --accent-dark: #2aa0b3;
        --nav-height: 64px;
    }
    .ojt-navbar {
        background: linear-gradient(90deg, rgba(60,179,204,0.12), rgba(60,179,204,0.08));
        backdrop-filter: blur(6px);
        border-bottom: 1px solid rgba(15,23,42,0.04);
        height: var(--nav-height);
    }
    .ojt-navbar .navbar-brand {
        color: #0f172a;
        font-weight: 700;
        letter-spacing: .4px;
    }
    .ojt-navbar .nav-link {
        color: #0f172a;
        font-weight:600;
    }
    .ojt-navbar .nav-link:hover {
        color: var(--accent-dark);
    }
    .ojt-profile-img {
        width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid rgba(60,179,204,0.18);
        background:#fff;
    }
    .ojt-avatar-name { font-weight:700; color:#0f172a; margin-left:8px; }
    /* Loading overlay */
    #loading-overlay {
        display:none;
        position:fixed;
        top:0; left:0; width:100vw; height:100vh;
        background: rgba(255,255,255,0.75);
        z-index:9999;
        align-items:center;
        justify-content:center;
    }
</style>

<nav class="navbar navbar-expand-md ojt-navbar shadow-sm">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" class="me-2" xmlns="http://www.w3.org/2000/svg">
                <rect width="24" height="24" rx="6" fill="url(#g)"/>
                <defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#3CB3CC"/><stop offset="1" stop-color="#2aa0b3"/></linearGradient></defs>
            </svg>
            <span>OJT Tracker</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#ojtNavbarMenu" aria-controls="ojtNavbarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" style="filter: invert(20%) sepia(40%) saturate(500%) hue-rotate(130deg)"></span>
        </button>

        <div class="collapse navbar-collapse" id="ojtNavbarMenu">
            <ul class="navbar-nav me-auto mb-2 mb-md-0 ms-3">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="dtr.php">DTR</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
            </ul>

            <div class="d-flex align-items-center">
                <?php if ($user_id): ?>
                    <img src="<?= htmlspecialchars($profile_img) ?>" alt="Profile" class="ojt-profile-img">
                    <div class="d-none d-md-flex flex-column ms-2 me-3">
                        <span class="ojt-avatar-name"><?= htmlspecialchars(trim("$fname $lname")) ?></span>
                        <small class="text-muted" style="font-size:12px"><?= htmlspecialchars($_SESSION['role'] ?? '') ?></small>
                    </div>
                <?php endif; ?>

                <a href="logout.php" class="btn btn-outline-secondary d-none d-md-inline-block">Logout</a>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" role="status" aria-hidden="true">
        <div class="text-center">
            <div class="spinner-border text-secondary" role="status" style="width:3rem;height:3rem">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2" style="color:#0f172a;font-weight:600">Loading...</div>
        </div>
    </div>
</nav>

<script>
    // Loading overlay logic
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
</script>
