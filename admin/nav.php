<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// allow admin and super_admin
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['super_admin','admin'])) {
    header("Location: ../login.php");
    exit;
}
$user_id = $_SESSION['user_id'] ?? null;
$fname = $_SESSION['fname'] ?? '';
$lname = $_SESSION['lname'] ?? '';
$role = $_SESSION['role'] ?? '';
$position = '';
$profile_img = '';


$displayName = trim(($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '')) ?: ($_SESSION['username'] ?? 'Admin');
$avatar = $_SESSION['avatar'] ?? '../assets/admin-avatar.png';
$currentPage = basename($_SERVER['PHP_SELF']);

if ($user_id) {
    $user = $oat->query("SELECT profile_img, position FROM users WHERE id = $user_id")->fetch_assoc();
    if (!empty($user['profile_img'])) {
        $profile_img = $user['profile_img'] . '?t=' . time();
    } else {
        // Fallback avatar
        $profile_img = 'https://ui-avatars.com/api/?name=' . urlencode(trim("$fname $lname")) . '&background=3CB3CC&color=fff';
    }
    $position = $user['position'] ?? '';
}
?>




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        /* Modern sidebar design using only Bootstrap utilities and minimal custom CSS */
        .sidebar-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(180deg, #1e3a5f 0%, #2c5282 100%);
            box-shadow: 4px 0 12px rgba(0,0,0,0.1);
            z-index: 1050;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-wrapper.collapsed {
            transform: translateX(-280px);
        }
        
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease-in-out;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        .sidebar-header {
            background: rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.85);
            border-radius: 0.5rem;
            margin: 0.25rem 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }
        
        .sidebar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(4px);
        }
        
        .sidebar-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
            font-weight: 600;
        }
        
        .sidebar-nav .nav-link i {
            width: 24px;
            font-size: 1.1rem;
        }
        
        .section-divider {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.5rem 1.25rem;
            margin-top: 1rem;
        }
        
        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }
        
        .toggle-sidebar {
            position: fixed;
            top: 1rem;
            left: 290px;
            z-index: 1049;
            background: #2c5282;
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .toggle-sidebar:hover {
            background: #1e3a5f;
            transform: scale(1.1);
        }
        
        .toggle-sidebar.collapsed {
            left: 10px;
        }
        
        .navbar-top {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-left: 280px;
            transition: margin-left 0.3s ease-in-out;
        }
        
        .navbar-top.expanded {
            margin-left: 0;
        }
        
        @media (max-width: 991.98px) {
            .sidebar-wrapper {
                transform: translateX(-280px);
            }
            
            .sidebar-wrapper.show {
                transform: translateX(0);
            }
            
            .main-content, .navbar-top {
                margin-left: 0 !important;
            }
            
            .toggle-sidebar {
                left: 10px;
            }
        }
        
        /* Smooth scroll */
        .sidebar-wrapper::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-wrapper::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.1);
        }
        
        .sidebar-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body class="bg-light">

<!-- Toggle Button (visible only on mobile view) -->
<button class="toggle-sidebar btn btn-primary d-md-none d-flex align-items-center justify-content-center" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar -->
<div class="sidebar-wrapper" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header p-3 d-flex align-items-center gap-3">
        <img src="../<?= htmlspecialchars($profile_img) ?>" alt="Admin Avatar" class="rounded-circle" width="56" height="56" style="object-fit: cover; border: 2px solid rgba(255,255,255,0.3);">
     
        <div class="flex-grow-1">
            <h6 class="text-white mb-0 fw-bold"><?php echo htmlspecialchars($displayName); ?></h6>
            <small class="text-white-50">Administrator</small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav py-3">
        <a href="admin.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'admin.php' ? 'active' : ''; ?>" data-page="admin.php">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="qr_generator.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'qr_generator.php' ? 'active' : ''; ?>" data-page="qr_generator.php">
            <i class="bi bi-qr-code"></i>
            <span>QR Time</span>
        </a>
        
        <a href="manageojt.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'manageojt.php' ? 'active' : ''; ?>" data-page="manageojt.php">
            <i class="bi bi-people"></i>
            <span>Manage OJT</span>
        </a>
        
        <a href="activeojt.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'activeojt.php' ? 'active' : ''; ?>" data-page="activeojt.php">
            <i class="bi bi-person-check"></i>
            <span>Active OJT</span>
        </a>
        
        <a href="otreports.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'otreports.php' ? 'active' : ''; ?>" data-page="otreports.php">
            <i class="bi bi-file-text"></i>
            <span>OT Reports</span>
        </a>

        <div class="section-divider">Reports & Settings</div>
        
       
        
        <a href="dtruserview.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'dtrview.php' ? 'active' : ''; ?>" data-page="dtrview.php">
            <i class="bi bi-calendar-event"></i>
            <span>DTR View</span>
        </a>
        
        <a href="sitesettings.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'sitesettings.php' ? 'active' : ''; ?>" data-page="sitesettings.php">
            <i class="bi bi-gear"></i>
            <span>Site Settings</span>
        </a>

        <div class="section-divider">Profile</div>
        
        <a href="adminprofileedit.php" class="nav-link d-flex align-items-center gap-3 <?php echo $currentPage === 'adminprofileedit.php' ? 'active' : ''; ?>" data-page="adminprofileedit.php">
            <i class="bi bi-pencil-square"></i>
            <span>Edit Profile</span>
        </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer p-3 mt-auto">
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-white-50">Signed in</small>
            <a href="../logout.php" class="btn btn-sm btn-danger">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>

<!-- Top Navbar (Mobile) -->


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sidebar toggle functionality
(function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const topNav = document.getElementById('topNav');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('show'); // For mobile
        mainContent.classList.toggle('expanded');
        topNav.classList.toggle('expanded');
    });
})();
</script>
