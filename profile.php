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
        $ext = pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION);
        $img_path = 'uploads/profile_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_img']['tmp_name'], $img_path);
        $oat->query("UPDATE users SET profile_img = '$img_path' WHERE id = $user_id");
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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-green-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-green-700 mb-6">My Profile</h1>
        <?php if (!empty($success)): ?>
            <div class="mb-4 p-2 bg-green-100 text-green-700 rounded"><?= $success ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="flex flex-col items-center">
                <img src="<?= !empty($user['profile_img']) ? $user['profile_img'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) ?>" alt="Profile Image" class="w-32 h-32 rounded-full object-cover mb-2 border-2 border-green-300" id="profilePreview">
                <input type="file" name="profile_img" accept="image/*" class="block mt-2" onchange="previewProfileImg(event)">
            </div>
            
            <div>
                <label class="block font-semibold mb-1 text-green-700">Bio</label>
                <textarea name="bio" rows="3" class="w-full px-3 py-2 border rounded" maxlength="255"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
            <button type="submit" name="update_profile" class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition w-full">Save Changes</button>
        </form>
        <button id="logoutBtn" class="mt-8 w-full bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-2 rounded-lg shadow transition">Logout</button>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-sm w-full text-center">
            <h2 class="text-xl font-bold mb-4 text-red-700">Confirm Logout</h2>
            <p class="mb-6 text-gray-700">Are you sure you want to logout? This will also time you out in DTR.</p>
            <div class="flex justify-center space-x-4">
                <button id="cancelLogout" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300 font-semibold">Cancel</button>
                <button id="confirmLogout" class="px-4 py-2 rounded bg-red-600 hover:bg-red-700 text-white font-semibold">Logout</button>
            </div>
        </div>
    </div>

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
        const logoutBtn = document.getElementById('logoutBtn');
        const logoutModal = document.getElementById('logoutModal');
        const cancelLogout = document.getElementById('cancelLogout');
        const confirmLogout = document.getElementById('confirmLogout');

        logoutBtn.onclick = () => logoutModal.classList.remove('hidden');
        cancelLogout.onclick = () => logoutModal.classList.add('hidden');
        confirmLogout.onclick = () => {
            // AJAX to set time_out in DTR, then logout
            fetch('logout.php', {method: 'POST'})
                .then(() => { window.location.href = 'login.php'; });
        };
    </script>
</body>
</html>