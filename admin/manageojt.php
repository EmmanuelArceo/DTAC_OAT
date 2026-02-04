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
</head>
<body class="bg-green-50 min-h-screen">
    <?php include 'nav.php'; ?>
    <div class="max-w-5xl mx-auto mt-10 p-6 bg-white rounded-xl shadow-lg">
        <h1 class="text-2xl font-bold text-green-700 mb-6">Manage OJT</h1>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border rounded-lg">
                <thead>
                    <tr class="bg-green-100 text-green-800">
                        <th class="py-2 px-4 border-b">Profile</th>
                        <th class="py-2 px-4 border-b">Name</th>
                        <th class="py-2 px-4 border-b">Username</th>
                        <th class="py-2 px-4 border-b">Email</th>
                     
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ojt = $ojts->fetch_assoc()): ?>
                        <tr class="hover:bg-green-50">
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
</body>
</html>