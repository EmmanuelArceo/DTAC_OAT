<?php

if (session_status() === PHP_SESSION_NONE) session_start();

// Allow only admin and super_admin
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['super_admin', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';
include 'nav.php';

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4c8eb1;
            --primary-dark: #3cb2cc;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
        }
        body {
            font-family: 'Inter', sans-serif;
            /* Lighter glassy, light blue background */
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .table-container {
            max-width: 1200px;
            margin: 40px auto 0 auto;
            padding: 2rem;
            background: rgba(60, 178, 204, 0.09); /* lighter glassy effect */
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
        }
        .table-title {
            color: var(--primary);
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 24px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .user-table th {
            background: var(--primary);
            color: #fff;
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            border: none;
        }
        .user-table td {
            vertical-align: middle;
            text-align: center;
            font-size: 1rem;
        }
        .user-profile-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-dark);
            background: #fff;
        }
        .role-badge {
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.35em 1em;
            display: inline-block;
        }
        .role-badge.super_admin { background: #6366f1; color: #fff; }
        .role-badge.admin { background: var(--primary-dark); color: #fff; }
        .role-badge.ojt { background: #10b981; color: #fff; }
        .role-badge.adviser { background: #fbbf24; color: #fff; }
        .btn-dtr {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: 6px 18px;
            transition: background 0.2s;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-dtr:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        @media (max-width: 991.98px) {
            .table-container {
                padding: 1rem;
            }
            .table-title {
                font-size: 1.3rem;
            }
            .user-profile-img {
                width: 36px;
                height: 36px;
            }
            .user-table th, .user-table td {
                font-size: 0.95rem;
            }
        }
        @media (max-width: 767.98px) {
            .table-container {
                padding: 0.5rem;
            }
            .table-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container table-container">
        <div class="table-title">
            <i class="bi bi-people-fill"></i> All Users
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover user-table align-middle">
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
                                <?php
                                    $img = '../assets/admin-avatar.png';
                                    if (!empty($user['profile_img']) && file_exists('../' . $user['profile_img'])) {
                                        $img = '../' . $user['profile_img'];
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="Profile" class="user-profile-img">
                            </td>
                            <td><?= htmlspecialchars($user['fname'] . ' ' . $user['lname']) ?></td>
                            <td>@<?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <span class="role-badge <?= htmlspecialchars($user['role']) ?>">
                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <form action="dtrview.php" method="GET" style="margin: 0;">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                    <button type="submit" class="btn-dtr">
                                        <i class="bi bi-calendar-week"></i> Show DTR
                                    </button>
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