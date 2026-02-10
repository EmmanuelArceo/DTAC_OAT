<?php

include '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit;
}

// Handle add admin/supervisor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $fname = $oat->real_escape_string($_POST['fname']);
    $mname = $oat->real_escape_string($_POST['mname']);
    $lname = $oat->real_escape_string($_POST['lname']);
    $username = $oat->real_escape_string($_POST['username']);
    $email = $oat->real_escape_string($_POST['email']);
    $role = in_array($_POST['role'], ['admin', 'super_admin']) ? $_POST['role'] : 'admin';
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check for duplicate username/email
    $check = $oat->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
    if ($check->num_rows === 0) {
        $stmt = $oat->prepare("INSERT INTO users (fname, mname, lname, username, email, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $fname, $mname, $lname, $username, $email, $password, $role);
        $stmt->execute();
        echo "<script>alert('Admin/Supervisor added successfully!');location.href='manage_admins.php';</script>";
        exit;
    } else {
        echo "<script>alert('Username or email already exists!');</script>";
    }
}

// Fetch all admins/super_admins
$admins = $oat->query("SELECT id, fname, mname, lname, username, email, role FROM users WHERE role IN ('admin', 'super_admin') ORDER BY lname, fname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins/Supervisors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="container my-4">
        <h2>Manage Admins / Supervisors</h2>
        <button class="btn btn-primary my-3" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="bi bi-person-plus"></i> Add Admin/Supervisor
        </button>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($admin = $admins->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php
                                    $mname = trim($admin['mname']);
                                    $middle = $mname ? strtoupper($mname[0]) . '.' : '';
                                    echo htmlspecialchars($admin['fname'] . ' ' . ($middle ? $middle . ' ' : '') . $admin['lname']);
                                ?>
                            </td>
                            <td>@<?= htmlspecialchars($admin['username']) ?></td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td><?= htmlspecialchars(ucfirst($admin['role'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form method="post" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addAdminModalLabel"><i class="bi bi-person-plus"></i> Add Admin/Supervisor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="fname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Middle Initial</label>
                    <input type="text" name="mname" class="form-control" maxlength="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lname" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control" required>
                        <option value="admin">Admin/Supervisor</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_admin" class="btn btn-primary">Add</button>
          </div>
        </form>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>