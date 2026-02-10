<?php


include '../db.php';
include 'nav.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch current admin details
$stmt = $oat->prepare("SELECT fname, lname, email, profile_img FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname'] ?? '');
    $lname = trim($_POST['lname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $profile_img = $admin['profile_img'];

    // Validate inputs
    if (empty($fname) || empty($lname) || empty($email)) {
        $errors[] = 'All fields except password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if (!empty($password) || !empty($confirm_password)) {
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
    }

    // Handle profile image upload
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profile_images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['profile_img']['name']);
        $file_path = $upload_dir . $file_name;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($_FILES['profile_img']['type'], $allowed_types)) {
            $errors[] = 'Only JPEG, PNG, and GIF files are allowed.';
        } elseif ($_FILES['profile_img']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Profile image must be less than 2MB.';
        } else {
            if (move_uploaded_file($_FILES['profile_img']['tmp_name'], $file_path)) {
                $profile_img = 'uploads/profile_images/' . $file_name;
            } else {
                $errors[] = 'Failed to upload profile image.';
            }
        }
    }

    // If no errors, update the admin profile
    if (empty($errors)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $oat->prepare("UPDATE users SET fname = ?, lname = ?, email = ?, password = ?, profile_img = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $fname, $lname, $email, $hashed_password, $profile_img, $admin_id);
        } else {
            $stmt = $oat->prepare("UPDATE users SET fname = ?, lname = ?, email = ?, profile_img = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fname, $lname, $email, $profile_img, $admin_id);
        }
        if ($stmt->execute()) {
            $success = 'Profile updated successfully.';
            $admin['fname'] = $fname;
            $admin['lname'] = $lname;
            $admin['email'] = $email;
            $admin['profile_img'] = $profile_img;
            $_SESSION['fname'] = $fname;
            $_SESSION['lname'] = $lname;
            $_SESSION['avatar'] = $profile_img;
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .profile-edit-container {
            max-width: 500px;
            margin: 3rem auto;
            padding: 2rem;
            background: rgba(60, 178, 204, 0.09);
            border-radius: 18px;
            box-shadow: 0 10px 30px 0 rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .profile-header img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .form-label {
            font-weight: 600;
            color: #4c8eb1;
        }
        .btn-primary {
            background: #4c8eb1;
            border: none;
        }
        .btn-primary:hover {
            background: #3cb2cc;
        }
        .alert ul {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
<div class="profile-edit-container">
    <div class="profile-header">
        <img src="../<?= htmlspecialchars($admin['profile_img'] ?: 'uploads/noimg.png') ?>?t=<?= time() ?>" alt="Profile Image">
        <div>
            <h4 class="mb-0"><?= htmlspecialchars($admin['fname'] . ' ' . $admin['lname']) ?></h4>
            <small class="text-muted"><?= htmlspecialchars($admin['email']) ?></small>
        </div>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="fname" class="form-label">First Name</label>
            <input type="text" class="form-control" id="fname" name="fname" value="<?= htmlspecialchars($admin['fname']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="lname" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="lname" name="lname" value="<?= htmlspecialchars($admin['lname']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="profile_img" class="form-label">Profile Image</label>
            <input type="file" class="form-control" id="profile_img" name="profile_img" accept="image/*">
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Leave blank to keep current password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Update Profile</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>