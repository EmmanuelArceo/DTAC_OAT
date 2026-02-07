<?php

include '../db.php';
$group_id = intval($_GET['group_id'] ?? 0);

// Users in group
$users = [];
$res = $oat->query("SELECT u.id, u.username FROM user_time_groups utg JOIN users u ON utg.user_id = u.id WHERE utg.group_id = $group_id ORDER BY u.username");
while ($row = $res->fetch_assoc()) $users[] = $row;

// All users (for dropdown)
$all_users = [];
$res2 = $oat->query("SELECT id, username FROM users ORDER BY username");
while ($row = $res2->fetch_assoc()) $all_users[] = $row;

header('Content-Type: application/json');
echo json_encode(['users' => $users, 'all_users' => $all_users]);