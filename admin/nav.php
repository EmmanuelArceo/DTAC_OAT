<?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
        header("Location: ../login.php");
        exit;
    }
?>
<!-- Bootstrap 5 Sidebar Navigation -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
    padding-left: 250px; /* Match sidebar width */
}
@media (max-width: 991.98px) {
    body {
        margin-left: 0 !important;
    }
}
    .sidebar-admin {
        width: 250px;
        min-height: 100vh;
        background: #166534;
        color: #fff;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1040;
        display: flex;
        flex-direction: column;
        box-shadow: 0 0 20px rgba(0,0,0,0.08);
    }
    .sidebar-admin .sidebar-header {
        padding: 2rem 1rem 1rem 1rem;
        border-bottom: 1px solid #14532d;
        text-align: center;
    }
    .sidebar-admin .nav-link {
        color: #fff;
        font-weight: 500;
        padding: 1rem 2rem;
        border-radius: 0.375rem;
        margin: 0.25rem 1rem;
        transition: background 0.2s;
    }
    .sidebar-admin .nav-link.active,
    .sidebar-admin .nav-link:hover {
        background: #14532d;
        color: #fff;
    }
    .sidebar-admin .sidebar-footer {
        border-top: 1px solid #14532d;
        padding: 1.5rem 1rem;
        margin-top: auto;
    }
    .sidebar-admin .btn-logout {
        width: 100%;
        background: #fff;
        color: #166534;
        font-weight: 600;
        border-radius: 0.375rem;
        transition: background 0.2s, color 0.2s;
    }
    .sidebar-admin .btn-logout:hover {
        background: #e6f4ea;
        color: #14532d;
    }
    @media (max-width: 991.98px) {
        .sidebar-admin {
            position: static;
            width: 100%;
            min-height: auto;
            flex-direction: row;
            height: auto;
        }
        .sidebar-admin .sidebar-header,
        .sidebar-admin .sidebar-footer {
            display: none;
        }
        .sidebar-admin .nav {
            flex-direction: row;
            justify-content: space-around;
            width: 100%;
        }
        .sidebar-admin .nav-link {
            margin: 0.25rem 0;
            padding: 1rem 0.5rem;
            text-align: center;
        }
    }
</style>
<aside class="sidebar-admin">
    <div class="sidebar-header">
        <span class="fw-bold fs-4">OJT Admin Panel</span>
    </div>
    <nav class="nav flex-column flex-grow-1 py-3">
        <a href="admin.php" class="nav-link">Dashboard</a>
        <a href="qr_generator.php" class="nav-link">Time In/Out QR</a>
        <a href="manageojt.php" class="nav-link">Manage OJT</a>
        <a href="activeojt.php" class="nav-link">Active OJT</a>
        <a href="otreports.php" class="nav-link">OT Reports</a>
        <a href="sitesettings.php" class="nav-link">Site Settings</a>
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-logout">Logout</a>
    </div>
</aside>
<!-- Add style="margin-left:250px;" or class="ms-xxl-5" to your main content wrapper for desktop -->
<!-- Add to your <style> section or nav.php -->
