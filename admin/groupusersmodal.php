<?php

include '../db.php';
$group_id = intval($_GET['group_id'] ?? 0);

// Users in group
$users = [];
$res = $oat->query("SELECT u.id, u.username, u.fname, u.mname, u.lname FROM user_time_groups utg JOIN users u ON utg.user_id = u.id WHERE utg.group_id = $group_id ORDER BY u.username");
while ($row = $res->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? substr($row['mname'], 0, 1) . '. ' : '') . $row['lname']);
    $users[] = $row;
}

// All users (for dropdown) - only ojt role
$all_users = [];
$res2 = $oat->query("SELECT id, username, fname, mname, lname FROM users WHERE role = 'ojt' ORDER BY username");
while ($row = $res2->fetch_assoc()) {
    $row['full_name'] = trim($row['fname'] . ' ' . ($row['mname'] ? substr($row['mname'], 0, 1) . '. ' : '') . $row['lname']);
    $all_users[] = $row;
}

header('Content-Type: application/json');
echo json_encode(['users' => $users, 'all_users' => $all_users]);