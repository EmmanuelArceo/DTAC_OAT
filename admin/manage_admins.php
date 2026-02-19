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

    $check = $oat->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
    if ($check->num_rows === 0) {
        $stmt = $oat->prepare("INSERT INTO users (fname, mname, lname, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssssss", $fname, $mname, $lname, $username, $email, $password, $role);
        $stmt->execute();
        echo "<script>alert('Admin/Supervisor added successfully!');location.href='manage_admins.php';</script>";
        exit;
    } else {
        echo "<script>alert('Username or email already exists!');</script>";
    }
}

// Handle update admin info (except password)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_info']) && $_SESSION['role'] === 'super_admin') {
    $edit_id = (int)$_POST['edit_id'];
    $fname = $oat->real_escape_string($_POST['fname']);
    $mname = $oat->real_escape_string($_POST['mname']);
    $lname = $oat->real_escape_string($_POST['lname']);
    $username = $oat->real_escape_string($_POST['username']);
    $email = $oat->real_escape_string($_POST['email']);
    $stmt = $oat->prepare("UPDATE users SET fname=?, mname=?, lname=?, username=?, email=? WHERE id=?");
    $stmt->bind_param("sssssi", $fname, $mname, $lname, $username, $email, $edit_id);
    $stmt->execute();
    echo "<script>alert('Admin/Supervisor info updated successfully!');location.href='manage_admins.php';</script>";
    exit;
}

// Handle edit admin/supervisor (super_admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin']) && $_SESSION['role'] === 'super_admin') {
    $edit_id = (int)$_POST['edit_id'];
    $edit_role = in_array($_POST['edit_role'], ['admin', 'super_admin']) ? $_POST['edit_role'] : 'admin';
    $edit_status = in_array($_POST['edit_status'], ['active', 'restricted']) ? $_POST['edit_status'] : 'active';
    $stmt = $oat->prepare("UPDATE users SET role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssi", $edit_role, $edit_status, $edit_id);
    $stmt->execute();
    echo "<script>alert('Admin/Supervisor updated successfully!');location.href='manage_admins.php';</script>";
    exit;
}

// Fetch all admins/super_admins
$admins = $oat->query("SELECT id, fname, mname, lname, username, email, role, status FROM users WHERE role IN ('admin', 'super_admin') ORDER BY lname, fname");

// Initialize modals variable to avoid undefined warning
$modals = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Admins/Supervisors</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .main-header {
            color: #4c8eb1;
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 24px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .admin-table-container {
            max-width: 1200px;
            margin: 40px auto 0 auto;
            padding: 2rem;
            background: rgba(60, 178, 204, 0.09);
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
        }
        .table th {
            background: #4c8eb1;
            color: #fff;
            text-align: center;
            font-size: 1rem;
            font-weight: 700;
            border: none;
        }
        .table td {
            vertical-align: middle;
            text-align: center;
            font-size: 1rem;
        }
        .badge.bg-success { background: #10b981 !important; }
        .badge.bg-danger { background: #ef4444 !important; }
        .btn-dtr, .btn-outline-primary, .btn-outline-secondary {
            font-weight: 600;
            border-radius: 8px;
            padding: 6px 18px;
            font-size: 1rem;
        }
        .btn-primary {
            background: #4c8eb1;
            border: none;
        }
        .btn-primary:hover {
            background: #3cb2cc;
        }
        @media (max-width: 991.98px) {
            .admin-table-container {
                padding: 1rem;
            }
            .main-header {
                font-size: 1.3rem;
            }
            .table th, .table td {
                font-size: 0.95rem;
            }
        }
        @media (max-width: 767.98px) {
            .admin-table-container {
                padding: 0.5rem;
            }
            .main-header {
                font-size: 1.1rem;
            }
        }
        /* OJT modal styles */
        .ojt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:.6rem; }
        .ojt-card { display:flex; gap:.6rem; align-items:center; padding:.6rem; border-radius:10px; background:#fbfbfd; box-shadow:0 6px 18px rgba(2,6,23,0.04); border:1px solid rgba(15,23,42,0.03); }
        .ojt-card img { width:44px; height:44px; object-fit:cover; border-radius:8px; flex-shrink:0; }
        .ojt-card .meta { display:flex; flex-direction:column; }
        .ojt-card .meta .name { font-weight:700; color:#0f172a; }
        .ojt-card .meta .username { color:#64748b; font-size:.86rem; }
        .ojt-card .actions { margin-left:auto; }
        @media(max-width:480px){ .ojt-grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="container admin-table-container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="main-header">
                <i class="bi bi-person-badge-fill"></i> Manage Admins / Supervisors
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="bi bi-person-plus"></i> Add Admin/Supervisor
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <th>Action</th>
                        <?php endif; ?>
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
                            <td>
                                <span class="badge <?= $admin['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                    <?= htmlspecialchars(ucfirst($admin['status'] ?? 'active')) ?>
                                </span>
                            </td>
                            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                            <td>
                                <!-- Show OJT Button -->
                                <button class="btn btn-sm btn-outline-info mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#showOjtModal<?= $admin['id'] ?>">
                                    Show OJT
                                </button>
                                <!-- Edit Info Button -->
                                <button class="btn btn-sm btn-outline-secondary mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editInfoModal<?= $admin['id'] ?>">
                                    Edit Info
                                </button>
                                <!-- Edit Role/Status Button -->
                                <button class="btn btn-sm btn-outline-primary mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editAdminModal<?= $admin['id'] ?>">
                                    Edit Role/Status
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php
                        // Collect modals for this admin
                        if ($_SESSION['role'] === 'super_admin') {
                            $modals .= '
                        <!-- Edit Info Modal -->
                        <div class="modal fade" id="editInfoModal'.$admin['id'].'" tabindex="-1" aria-labelledby="editInfoModalLabel'.$admin['id'].'" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <form method="post" class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editInfoModalLabel'.$admin['id'].'"><i class="bi bi-pencil"></i> Edit Admin/Supervisor Info</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                    <input type="hidden" name="edit_id" value="'.$admin['id'].'">
                                    <div class="mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="fname" class="form-control" value="'.htmlspecialchars($admin['fname']).'" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Middle Initial</label>
                                        <input type="text" name="mname" class="form-control" maxlength="1" value="'.htmlspecialchars($admin['mname']).'">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="lname" class="form-control" value="'.htmlspecialchars($admin['lname']).'" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" value="'.htmlspecialchars($admin['username']).'" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="'.htmlspecialchars($admin['email']).'" required>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_admin_info" class="btn btn-primary">Save Changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                        <!-- Edit Role/Status Modal -->
                        <div class="modal fade" id="editAdminModal'.$admin['id'].'" tabindex="-1" aria-labelledby="editAdminModalLabel'.$admin['id'].'" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered">
                            <form method="post" class="modal-content">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editAdminModalLabel'.$admin['id'].'"><i class="bi bi-pencil"></i> Edit Admin/Supervisor Role/Status</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                    <input type="hidden" name="edit_id" value="'.$admin['id'].'">
                                    <div class="mb-3">
                                        <label class="form-label">Role</label>
                                        <select name="edit_role" class="form-control" required>
                                            <option value="admin" '.($admin['role'] === 'admin' ? 'selected' : '').'>Admin/Supervisor</option>
                                            <option value="super_admin" '.($admin['role'] === 'super_admin' ? 'selected' : '').'>Super Admin</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="edit_status" class="form-control" required>
                                            <option value="active" '.($admin['status'] === 'active' ? 'selected' : '').'>Active</option>
                                            <option value="restricted" '.($admin['status'] === 'restricted' ? 'selected' : '').'>Restricted</option>
                                        </select>
                                        <div class="form-text">Restricted admins cannot login.</div>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="edit_admin" class="btn btn-primary">Save Changes</button>
                              </div>
                            </form>
                          </div>
                        </div>
                        ';
                            // Build OJT list and Show OJT modal
                            $stuRes = $oat->query("SELECT id, fname, mname, lname, username, profile_img FROM users WHERE adviser_id = " . (int)$admin['id'] . " AND role='ojt' ORDER BY lname, fname");
                            $studentListHtml = '';
                            if ($stuRes && $stuRes->num_rows) {
                                $cards = '';
                                while ($s = $stuRes->fetch_assoc()) {
                                    $m = trim($s['mname']);
                                    $mi = $m ? ' ' . strtoupper($m[0]) . '.' : '';
                                    $displayName = htmlspecialchars($s['fname'] . $mi . ' ' . $s['lname']);
                                    $username = htmlspecialchars($s['username']);
                                    $img = !empty($s['profile_img']) ? '../' . htmlspecialchars($s['profile_img']) : 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/1f464.png';
                                    $cards .= '<div class="ojt-card">'
                                           . '<img src="' . $img . '" alt="avatar" onerror="this.onerror=null;this.src=\'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/1f464.png\'">'
                                           . '<div class="meta">'
                                             . '<div class="name">' . $displayName . '</div>'
                                             . '<div class="username">@' . $username . '</div>'
                                           . '</div>'
                                           . '<div class="actions">'
                                             . '<a href="../profile.php?user=' . (int)$s['id'] . '" class="btn btn-sm btn-outline-secondary btn-compact me-1">Profile</a>'
                                             . '<a href="dtruserview.php?user=' . (int)$s['id'] . '" class="btn btn-sm btn-primary btn-compact">DTR</a>'
                                           . '</div>'
                                         . '</div>';
                                }
                                $studentListHtml = '<div class="ojt-grid">' . $cards . '</div>';
                            } else {
                                $studentListHtml = '<div class="text-muted">No OJT assigned.</div>';
                            }
                            $modals .= '<div class="modal fade" id="showOjtModal'.$admin['id'].'" tabindex="-1" aria-labelledby="showOjtModalLabel'.$admin['id'].'" aria-hidden="true">'
                                     . '<div class="modal-dialog modal-lg modal-dialog-centered">'
                                     . '<div class="modal-content">'
                                     . '<div class="modal-header">'
                                     . '<h5 class="modal-title" id="showOjtModalLabel'.$admin['id'].'"><i class="bi bi-people-fill"></i> OJT under '.htmlspecialchars($admin['fname'].' '.$admin['lname']).'</h5>'
                                     . '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                                     . '</div>'
                                     . '<div class="modal-body">'
                                     . $studentListHtml
                                     . '</div>'
                                     . '<div class="modal-footer">'
                                     . '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>'
                                     . '</div></div></div></div>';
                        }
                        ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
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

    <!-- Render all edit modals here -->
<?= $modals ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>