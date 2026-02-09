<?php
// filepath: c:\xampp\htdocs\OAT\admin\dtruserview.php

if (session_status() === PHP_SESSION_NONE) session_start();

// Allow only admin and super_admin
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['super_admin', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
include 'nav.php'; // Include navigation bar

// Fetch all users from the database
$users = $oat->query("SELECT id, fname, lname, username, email, role, profile_img FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .user-table {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .user-table th {
            background: #3CB3CC;
            color: #fff;
            text-align: center;
        }
        .user-table td {
            vertical-align: middle;
            text-align: center;
        }
        .user-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #3CB3CC;
        }
        .table-container {
            margin-top: 30px;
        }
        .table-title {
            color: #3CB3CC;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .btn-dtr {
            background: #3CB3CC;
            color: #fff;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            transition: background 0.2s ease;
        }
        .btn-dtr:hover {
            background: #2a92a8;
            color: #fff;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container table-container">
        <h1 class="table-title">All Users</h1>
        <div class="table-responsive">
            <table class="table table-bordered table-hover user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Profile</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['id']) ?></td>
                            <td>
                                <img src="../<?= !empty($user['profile_img']) ? htmlspecialchars($user['profile_img']) : '../assets/admin-avatar.png' ?>" 
                                     alt="Profile" 
                                     class="user-profile-img">
                            </td>
                            <td><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                            <td>
                                <form action="dtrview.php" method="GET" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                    <button type="submit" class="btn-dtr">Show DTR</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>