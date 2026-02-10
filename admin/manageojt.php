<?php

include '../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit;
}

// Fetch all OJT users (add mname)
$ojts = $oat->query("SELECT id, username, fname, mname, lname, email, bio, profile_img, position, adviser_id FROM users WHERE role = 'ojt' ORDER BY lname, fname");

// Fetch all potential trainers (admins and advisers)
$trainers = $oat->query("SELECT id, fname, lname FROM users WHERE role IN ('admin', 'super_admin') ORDER BY lname, fname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage OJT Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4c8eb1;
            --primary-dark: #3cb2cc;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.08), 0 1px 2px -1px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background: rgba(60, 178, 204, 0.09);
            border-radius: 18px;
            box-shadow: 0 10px 30px 0 rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }
        .page-header .badge {
            background: var(--primary-dark);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
        }
        .table-responsive {
            margin-top: 1.5rem;
        }
        .ojt-table th, .ojt-table td {
            vertical-align: middle;
        }
        .ojt-table th {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            border: none;
        }
        .ojt-table tbody tr {
            transition: background 0.2s;
        }
        .ojt-table tbody tr:hover {
            background: var(--light);
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        .manage-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .manage-btn:hover {
            background: var(--primary-dark);
        }
        .modal-header {
            background: var(--primary);
            color: #fff;
        }
        .modal-title i {
            margin-right: 0.5rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--primary);
        }
        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-outline-secondary:hover {
            background: var(--primary);
            color: #fff;
        }
        @media (max-width: 767.98px) {
            .main-content {
                padding: 1rem;
            }
            .page-header h1 {
                font-size: 1.3rem;
            }
            .user-avatar {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <i class="bi bi-person-badge-fill fs-2 text-primary"></i>
            <h1>Manage OJT Users</h1>
            <span class="badge rounded-pill"><?= htmlspecialchars($ojts->num_rows) ?> OJTs</span>
        </div>
        <div class="table-responsive">
            <table class="table ojt-table align-middle">
                <thead>
                    <tr>
                        <th>Manage</th>
                        <th>Profile</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Bio</th>
                        <th>Position</th>
                        <th>Adviser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ojt = $ojts->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center">
                                <button 
                                    class="manage-btn"
                                    type="button"
                                    onclick="openManageModal(
                                        <?= (int)$ojt['id'] ?>, 
                                        '<?= htmlspecialchars(addslashes($ojt['fname'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['mname'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['lname'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['username'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['email'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['bio'])) ?>', 
                                        '<?= htmlspecialchars(addslashes($ojt['position'] ?? '')) ?>',
                                        '<?= htmlspecialchars(addslashes($ojt['adviser_id'] ?? '')) ?>'
                                    )"
                                >
                                    <i class="bi bi-pencil-square"></i> Manage
                                </button>
                            </td>
                            <td class="text-center">
                                <?php
                                    $img = '../uploads/noimg.png';
                                    if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                                       $img = '../' . $ojt['profile_img'] . '?t=' . time();
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($img) ?>"
                                     alt="Profile" class="user-avatar mx-auto">
                            </td>
                            <td>
                                <?php
                                // Show: Firstname M. Lastname (if mname exists)
                                $mname = trim($ojt['mname']);
                                $middle = $mname ? strtoupper($mname[0]) . '.' : '';
                                echo htmlspecialchars($ojt['fname'] . ' ' . ($middle ? $middle . ' ' : '') . $ojt['lname']);
                            ?>
                            </td>
                            <td>@<?= htmlspecialchars($ojt['username']) ?></td>
                            <td><?= htmlspecialchars($ojt['email']) ?></td>
                            <td><?= htmlspecialchars($ojt['bio']) ?></td>
                            <td><?= htmlspecialchars($ojt['position'] ?? '') ?></td>
                            <td>
                                <?php
                                if ($ojt['adviser_id']) {
                                    $stmt = $oat->prepare("SELECT fname, lname FROM users WHERE id = ?");
                                    $stmt->bind_param("i", $ojt['adviser_id']);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        echo htmlspecialchars($row['fname'] . ' ' . $row['lname']);
                                    } else {
                                        echo '--';
                                    }
                                } else {
                                    echo '--';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- END of .main-content -->

    <!-- Modal must be outside .main-content -->
    <div class="modal fade" id="manageModal" tabindex="-1" aria-labelledby="manageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form id="manageForm" method="post" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="manageModalLabel"><i class="bi bi-person-lines-fill"></i>Manage OJT Profile</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="ojt_id" id="ojt_id">
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" name="fname" id="modal_fname" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Middle Initial</label>
                        <input type="text" name="mname" id="modal_mname" class="form-control" maxlength="1">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="lname" id="modal_lname" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="modal_username" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="modal_email" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" id="modal_bio" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="modal_position" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Assign Adviser</label>
                        <select name="adviser_id" id="modal_adviser_id" class="form-control">
                            <option value="">Select Adviser</option>
                            <?php 
                            $trainers->data_seek(0);
                            while ($trainer = $trainers->fetch_assoc()): 
                            ?>
                                <option value="<?= (int)$trainer['id'] ?>"><?= htmlspecialchars($trainer['fname'] . ' ' . $trainer['lname']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Profile Image</label>
                    <input type="file" name="profile_img" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="delete_ojt" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this OJT?')">Delete</button>
                <button type="submit" name="update_ojt" class="btn btn-primary">Update</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function openManageModal(id, fname, mname, lname, username, email, bio, position, adviser_id) {
        document.getElementById('manageForm').reset();
        document.getElementById('ojt_id').value = id;
        document.getElementById('modal_fname').value = fname;
        document.getElementById('modal_mname').value = mname;
        document.getElementById('modal_lname').value = lname;
        document.getElementById('modal_username').value = username;
        document.getElementById('modal_email').value = email;
        document.getElementById('modal_bio').value = bio;
        document.getElementById('modal_position').value = position;
        document.getElementById('modal_adviser_id').value = adviser_id || '';
        var modal = new bootstrap.Modal(document.getElementById('manageModal'));
        modal.show();
    }
    </script>
<?php
// Handle OJT profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ojt'])) {
    $ojt_id = intval($_POST['ojt_id']);
    $fname = $oat->real_escape_string($_POST['fname']);
    $mname = $oat->real_escape_string($_POST['mname']);
    $lname = $oat->real_escape_string($_POST['lname']);
    $username = $oat->real_escape_string($_POST['username']);
    $email = $oat->real_escape_string($_POST['email']);
    $bio = $oat->real_escape_string($_POST['bio']);
    $position = $oat->real_escape_string($_POST['position']);
    $adviser_id = null;
    if (in_array($_SESSION['role'], ['admin', 'super_admin']) && !empty($_POST['adviser_id'])) {
        $adviser_id = intval($_POST['adviser_id']);
        $stmt_check = $oat->prepare("SELECT id FROM users WHERE id = ? AND role IN ('admin', 'super_admin')");
        $stmt_check->bind_param("i", $adviser_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 0) {
            $adviser_id = null;
        }
    }
    $img_path = null;
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0777, true);
        }
        $ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
        $img_path = 'uploads/profile_' . $ojt_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_img']['tmp_name'], '../' . $img_path);
        $oat->query("UPDATE users SET profile_img = '$img_path' WHERE id = $ojt_id");
    }
    $stmt = $oat->prepare("UPDATE users SET fname=?, mname=?, lname=?, username=?, email=?, bio=?, position=?, adviser_id=? WHERE id=?");
    $stmt->bind_param("sssssssii", $fname, $mname, $lname, $username, $email, $bio, $position, $adviser_id, $ojt_id);
    $stmt->execute();
    echo "<script>location.href='manageojt.php';</script>";
    exit;
}

// Handle OJT deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ojt'])) {
    $ojt_id = intval($_POST['ojt_id']);
    $oat->query("DELETE FROM users WHERE id = $ojt_id AND role = 'ojt'");
    echo "<script>location.href='manageojt.php';</script>";
    exit;
}
?>
</body>
</html>