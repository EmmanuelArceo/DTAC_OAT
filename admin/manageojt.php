<?php

include '../db.php';
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch all OJT users (assuming role 'ojt')
$ojts = $oat->query("SELECT id, username, fname, lname, email, bio, profile_img FROM users WHERE role = 'ojt' ORDER BY lname, fname");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage OJT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="aos.php" rel="stylesheet">
    <style>
        /* Modal animation */
        .modal-animate {
            opacity: 0;
            transform: translateY(40px) scale(0.98);
            transition: opacity .35s cubic-bezier(.4,0,.2,1), transform .35s cubic-bezier(.4,0,.2,1);
        }
        .modal-animate.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        /* Modal backdrop */
        #manageModal {
            background: transparent;
            pointer-events: auto; /* Allow clicks on the backdrop */
        }
        #manageModal.hidden {
            display: none;
        }
        #manageModal.flex {
            display: flex;
        }
        #modalCard {
            pointer-events: auto; } /* Allow clicks inside the modal card */
    </style>
</head>
<body class="bg-green-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-5xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg" data-aos="fade-up">
        <h1 class="text-2xl font-bold text-green-700 mb-6">Manage OJT</h1>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg">
                <thead>
                    <tr class="bg-green-100 text-green-800">
                        <th class="py-2 px-4 border-b">Manage</th>
                        <th class="py-2 px-4 border-b">Profile</th>
                        <th class="py-2 px-4 border-b">Name</th>
                        <th class="py-2 px-4 border-b">Username</th>
                        <th class="py-2 px-4 border-b">Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ojt = $ojts->fetch_assoc()): ?>
                        <tr class="hover:bg-green-50" data-aos="fade-up">
                            <td class="py-2 px-4 border-b text-center">
                                <button 
                                    class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700"
                                    onclick="openManageModal(<?= $ojt['id'] ?>, '<?= htmlspecialchars(addslashes($ojt['fname'])) ?>', '<?= htmlspecialchars(addslashes($ojt['lname'])) ?>', '<?= htmlspecialchars(addslashes($ojt['username'])) ?>', '<?= htmlspecialchars(addslashes($ojt['email'])) ?>', '<?= htmlspecialchars(addslashes($ojt['bio'])) ?>')"
                                >
                                    Manage
                                </button>
                            </td>
                            <td class="py-2 px-4 border-b text-center">
                                <?php
                                    $img = '../uploads/noimg.png';
                                    if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                                        $img = '../' . $ojt['profile_img'];
                                    }
                                ?>
                                <img src="<?= htmlspecialchars($img) ?>"
                                     alt="Profile" class="w-12 h-12 rounded-full object-cover mx-auto border border-green-200">
                            </td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($ojt['username']) ?></td>
                            <td class="py-2 px-4 border-b"><?= htmlspecialchars($ojt['email']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for managing OJT profile -->
    <div id="manageModal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
        <div id="modalCard" class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative modal-animate">
            <button onclick="closeManageModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            <h2 class="text-xl font-bold mb-4 text-green-700">Update OJT Profile</h2>
            <form id="manageForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="ojt_id" id="ojt_id">
                <div class="mb-3">
                    <label class="block font-semibold mb-1">First Name</label>
                    <input type="text" name="fname" id="modal_fname" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-3">
                    <label class="block font-semibold mb-1">Last Name</label>
                    <input type="text" name="lname" id="modal_lname" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-3">
                    <label class="block font-semibold mb-1">Username</label>
                    <input type="text" name="username" id="modal_username" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-3">
                    <label class="block font-semibold mb-1">Email</label>
                    <input type="email" name="email" id="modal_email" class="w-full border rounded px-3 py-2" required>
                </div>
                <div class="mb-3">
                    <label class="block font-semibold mb-1">Bio</label>
                    <textarea name="bio" id="modal_bio" class="w-full border rounded px-3 py-2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block font-semibold mb-1">Profile Image</label>
                    <input type="file" name="profile_img" class="w-full border rounded px-3 py-2">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeManageModal()" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300">Cancel</button>
                    <button type="submit" name="update_ojt" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
    <script>
    AOS.init();

    function openManageModal(id, fname, lname, username, email, bio) {
        const modal = document.getElementById('manageModal');
        const card = document.getElementById('modalCard');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => card.classList.add('show'), 10);

        document.getElementById('ojt_id').value = id;
        document.getElementById('modal_fname').value = fname;
        document.getElementById('modal_lname').value = lname;
        document.getElementById('modal_username').value = username;
        document.getElementById('modal_email').value = email;
        document.getElementById('modal_bio').value = bio;
    }
    function closeManageModal() {
        const modal = document.getElementById('manageModal');
        const card = document.getElementById('modalCard');
        card.classList.remove('show');
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 300);
    }

    // Hide modal when clicking outside modal card
    document.getElementById('manageModal').addEventListener('mousedown', function(e) {
        if (e.target === this) {
            closeManageModal();
        }
    });
    </script>

<?php
// Handle OJT profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ojt'])) {
    $ojt_id = intval($_POST['ojt_id']);
    $fname = $oat->real_escape_string($_POST['fname']);
    $lname = $oat->real_escape_string($_POST['lname']);
    $username = $oat->real_escape_string($_POST['username']);
    $email = $oat->real_escape_string($_POST['email']);
    $bio = $oat->real_escape_string($_POST['bio']);
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

    $oat->query("UPDATE users SET fname='$fname', lname='$lname', username='$username', email='$email', bio='$bio' WHERE id = $ojt_id");
    echo "<script>location.href='manageojt.php';</script>";
    exit;
}
?>
</html>