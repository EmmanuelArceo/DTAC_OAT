<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = trim($_POST['bio']);
    $img_path = null;

    // Handle image upload
    if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
        // Ensure uploads directory exists
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        // Get current profile image path
        $old_img = $oat->query("SELECT profile_img FROM users WHERE id = $user_id")->fetch_assoc()['profile_img'];
        
        if ($old_img && file_exists($old_img)) {
            // Replace the old image by overwriting the file
            $img_path = $old_img;
            move_uploaded_file($_FILES['profile_img']['tmp_name'], $img_path);
            // No DB update needed since path remains the same
        } else {
            // No old image, create a new one
            $ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
            $img_path = 'uploads/profile_' . $user_id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['profile_img']['tmp_name'], $img_path);
            $oat->query("UPDATE users SET profile_img = '$img_path' WHERE id = $user_id");
        }
    }

    $oat->query("UPDATE users SET bio = '" . $oat->real_escape_string($bio) . "' WHERE id = $user_id");
    $success = "Profile updated successfully!";
}

// Fetch user info
$user = $oat->query("SELECT username, bio, profile_img FROM users WHERE id = $user_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #3CB3CC;
            --accent-deep: #2aa0b3;
        }
        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            background: linear-gradient(135deg, #f7fbfb 0%, #fbfcfd 100%);
            min-height: 100vh;
            color: #0f172a;
        }
        .profile-glass {
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(60,179,204,0.10);
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius: 18px;
            max-width: 440px;
            margin: 48px auto;
            padding: 36px 28px 28px 28px;
        }
        .profile-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-deep);
            margin-bottom: 8px;
            text-align: center;
        }
        .profile-img {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent);
            box-shadow: 0 4px 18px rgba(60,179,204,0.10);
            margin-bottom: 8px;
        }
        .profile-username {
            font-weight: 700;
            color: var(--accent-deep);
            text-align: center;
            font-size: 1.1rem;
        }
        .profile-bio-label {
            font-weight: 600;
            color: var(--accent-deep);
        }
        .btn-accent {
            background: linear-gradient(90deg, var(--accent), var(--accent-deep));
            color: #fff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            box-shadow: 0 4px 16px rgba(60,179,204,0.10);
            transition: all .15s;
        }
        .btn-accent:hover {
            background: var(--accent-deep);
            color: #fff;
            transform: translateY(-2px) scale(1.03);
        }
        .logout-btn {
            background: #e74c3c;
            color: #fff;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            margin-top: 18px;
            width: 100%;
            transition: background .15s;
        }
        .logout-btn:hover {
            background: #c0392b;
        }
        .form-control:focus {
            border-color: var(--accent-deep);
            box-shadow: 0 0 0 0.2rem rgba(60,179,204,0.10);
        }
        .modal-backdrop {
            background: rgba(0,0,0,0.25);
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="profile-glass">
        <div class="profile-title">My Profile</div>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success text-center mb-3 py-2"><?= $success ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="mb-3">
            <div class="d-flex flex-column align-items-center mb-3">
                <img src="<?= !empty($user['profile_img']) ? $user['profile_img'] . '?t=' . time() : 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) ?>" alt="Profile Image" class="profile-img" id="profilePreview">
                <label class="form-label mt-2" for="profile_img" style="color:var(--accent-deep);font-weight:600;">Change Photo</label>
                <input type="file" name="profile_img" id="profile_img" accept="image/*" class="form-control" style="max-width:220px;" onchange="previewProfileImg(event)">
            </div>
            <div class="mb-3">
                <label class="profile-bio-label mb-1" for="bio">Bio</label>
                <textarea name="bio" id="bio" rows="3" class="form-control" maxlength="255"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" name="update_profile" class="btn-accent w-100 mb-2">Save Changes</button>
        </form>
        <div class="text-center">
            <button id="logoutBtn" class="logout-btn">Logout</button>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger" id="logoutModalLabel">Confirm Logout</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center">
            <p class="mb-3">Are you sure you want to logout?<br>This will also time you out in DTR for the current Day.</p>
            <button id="confirmLogout" class="btn btn-danger px-4 me-2">Logout</button>
            <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profile image preview
        function previewProfileImg(event) {
            const reader = new FileReader();
            reader.onload = function(){
                document.getElementById('profilePreview').src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }

        // Logout modal logic
        document.getElementById('logoutBtn').onclick = function() {
            var modal = new bootstrap.Modal(document.getElementById('logoutModal'));
            modal.show();
        };
        document.getElementById('confirmLogout').onclick = function() {
            fetch('logout.php', {method: 'POST'})
                .then(() => { window.location.href = 'login.php'; });
        };
    </script>
</body>
</html>