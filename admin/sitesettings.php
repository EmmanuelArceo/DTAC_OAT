<?php

include '../db.php';
include 'nav.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch current settings or set defaults
$settings = $oat->query("SELECT * FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';
$default_time_out = $settings['default_time_out'] ?? '17:00:00';
$lunch_start = $settings['lunch_start'] ?? '12:00:00';
$lunch_end = $settings['lunch_end'] ?? '13:00:00';

// Handle time group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_time_group'])) {
        $group_name = $oat->real_escape_string($_POST['group_name']);
        $group_time_in = $_POST['group_time_in'];
        $group_time_out = $_POST['group_time_out'];
        $group_lunch_start = $_POST['group_lunch_start'];
        $group_lunch_end = $_POST['group_lunch_end'];
        $oat->query("INSERT INTO time_groups (name, time_in, time_out, lunch_start, lunch_end) VALUES ('$group_name', '$group_time_in', '$group_time_out', '$group_lunch_start', '$group_lunch_end')");
        $success = "Time group added!";
    } elseif (isset($_POST['delete_time_group'])) {
        $group_id = intval($_POST['group_id']);
        $oat->query("DELETE FROM time_groups WHERE id=$group_id");
        $success = "Time group deleted!";
    }
    // Update settings including lunch break
    $new_time_in = $_POST['default_time_in'] ?? '08:00:00';
    $new_time_out = $_POST['default_time_out'] ?? '17:00:00';
    $new_lunch_start = $_POST['lunch_start'] ?? '12:00:00';
    $new_lunch_end = $_POST['lunch_end'] ?? '13:00:00';
    if ($settings) {
        $oat->query("UPDATE site_settings SET default_time_in='$new_time_in', default_time_out='$new_time_out', lunch_start='$new_lunch_start', lunch_end='$new_lunch_end' LIMIT 1");
    } else {
        $oat->query("INSERT INTO site_settings (default_time_in, default_time_out, lunch_start, lunch_end) VALUES ('$new_time_in', '$new_time_out', '$new_lunch_start', '$new_lunch_end')");
    }
    $default_time_in = $new_time_in;
    $default_time_out = $new_time_out;
    $lunch_start = $new_lunch_start;
    $lunch_end = $new_lunch_end;
    if (!isset($success)) $success = "Settings updated!";
}

// Handle adding user to time group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user_to_group'])) {
    $user_id = intval($_POST['user_id']);
    $group_id = intval($_POST['group_id']);
    $exists = $oat->query("SELECT 1 FROM user_time_groups WHERE user_id=$user_id AND group_id=$group_id")->num_rows;
    if (!$exists) {
        $oat->query("INSERT INTO user_time_groups (user_id, group_id) VALUES ($user_id, $group_id)");
        $success = "User added to group!";
    } else {
        $success = "User already in this group!";
    }
}

// Handle removing user from time group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_user_from_group'])) {
    $user_id = intval($_POST['user_id']);
    $group_id = intval($_POST['group_id']);
    $oat->query("DELETE FROM user_time_groups WHERE user_id=$user_id AND group_id=$group_id");
    $success = "User removed from group!";
}

// Handle editing time group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_time_group'])) {
    $group_id = intval($_POST['edit_group_id']);
    $group_name = $oat->real_escape_string($_POST['edit_group_name']);
    $group_time_in = $_POST['edit_group_time_in'];
    $group_time_out = $_POST['edit_group_time_out'];
    $group_lunch_start = $_POST['edit_group_lunch_start'];
    $group_lunch_end = $_POST['edit_group_lunch_end'];
    $oat->query("UPDATE time_groups SET name='$group_name', time_in='$group_time_in', time_out='$group_time_out', lunch_start='$group_lunch_start', lunch_end='$group_lunch_end' WHERE id=$group_id");
    $success = "Time group updated!";
}

// Fetch all users for dropdown
$users = $oat->query("SELECT id, username FROM users ORDER BY username");

// Fetch time groups
$time_groups = $oat->query("SELECT * FROM time_groups ORDER BY name");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body { background: #f7fbfb; }
        .glass {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            max-width: 500px;
            margin: 40px auto;
            padding: 32px 24px;
        }
    </style>
</head>
<body>
    <div class="glass">
        <h3 class="mb-4">Site Settings</h3>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <form method="post" class="mb-4">
            <h5>Global Defaults</h5>
            <div class="mb-3">
                <label for="default_time_in" class="form-label">Default Time In</label>
                <input type="time" id="default_time_in" name="default_time_in" class="form-control" value="<?= htmlspecialchars(substr($default_time_in,0,5)) ?>" required>
            </div>
            <div class="mb-3">
                <label for="default_time_out" class="form-label">Default Time Out</label>
                <input type="time" id="default_time_out" name="default_time_out" class="form-control" value="<?= htmlspecialchars(substr($default_time_out,0,5)) ?>" required>
            </div>
            <div class="mb-3">
                <label for="lunch_start" class="form-label">Lunch Break Start</label>
                <input type="time" id="lunch_start" name="lunch_start" class="form-control" value="<?= htmlspecialchars(substr($lunch_start,0,5)) ?>" required>
            </div>
            <div class="mb-3">
                <label for="lunch_end" class="form-label">Lunch Break End</label>
                <input type="time" id="lunch_end" name="lunch_end" class="form-control" value="<?= htmlspecialchars(substr($lunch_end,0,5)) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>

        <h5 class="mb-3">Time Groups</h5>
        <form method="post" class="mb-4">
            <div class="row">
                <div class="col-md-2">
                    <input type="text" name="group_name" class="form-control" placeholder="Group Name" required>
                </div>
                <div class="col-md-2">
                    <select name="group_time_in" class="form-select" required>
                        <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                            $t24 = sprintf('%02d:%02d', $h, $m);
                            $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                            <option value="<?= $t24 ?>"><?= $ampm ?></option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="group_time_out" class="form-select" required>
                        <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                            $t24 = sprintf('%02d:%02d', $h, $m);
                            $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                            <option value="<?= $t24 ?>"><?= $ampm ?></option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="group_lunch_start" class="form-select" required>
                        <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                            $t24 = sprintf('%02d:%02d', $h, $m);
                            $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                            <option value="<?= $t24 ?>"><?= $ampm ?></option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="group_lunch_end" class="form-select" required>
                        <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                            $t24 = sprintf('%02d:%02d', $h, $m);
                            $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                            <option value="<?= $t24 ?>"><?= $ampm ?></option>
                        <?php endfor; endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_time_group" class="btn btn-success w-100">Add Group</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Lunch Start</th>
                        <th>Lunch End</th>
                        <th>Users in Group</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $time_groups->data_seek(0); // Reset pointer
                    while ($group = $time_groups->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($group['name']) ?></td>
                            <td><?= htmlspecialchars(substr($group['time_in'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(substr($group['time_out'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(substr($group['lunch_start'], 0, 5)) ?></td>
                            <td><?= htmlspecialchars(substr($group['lunch_end'], 0, 5)) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="showGroupUsersModal(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name']) ?>')">Manage</button>
                                <button class="btn btn-sm btn-warning" onclick="showEditTimeGroupModal(
                                    <?= $group['id'] ?>,
                                    '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($group['time_in'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($group['time_out'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($group['lunch_start'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($group['lunch_end'], ENT_QUOTES) ?>'
                                )">Edit</button>
                            </td>
                            <td>
                                <!-- Actions removed, only Manage/Edit buttons remain -->
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users & Management Modal -->
    <div class="modal fade" id="groupUsersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="addUserToGroupForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupUsersModalTitle">Time Group Details</h5>
                        <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body" id="groupUsersModalBody">
                        <!-- Content loaded by JS -->
                    </div>
                    <div class="modal-footer" id="groupUsersModalFooter">
                        <!-- Add user form loaded by JS -->
                    </div>
                    <div class="modal-footer" id="groupUsersModalDeleteFooter">
                        <!-- Delete button loaded by JS -->
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Time Group Modal -->
    <div class="modal fade" id="editTimeGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Time Group</h5>
                        <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_group_id" id="edit_group_id">
                        <div class="mb-3">
                            <label>Group Name</label>
                            <input type="text" name="edit_group_name" id="edit_group_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Time In</label>
                            <select name="edit_group_time_in" id="edit_group_time_in" class="form-select" required>
                                <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                                    $t24 = sprintf('%02d:%02d', $h, $m);
                                    $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                                    <option value="<?= $t24 ?>"><?= $ampm ?></option>
                                <?php endfor; endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Time Out</label>
                            <select name="edit_group_time_out" id="edit_group_time_out" class="form-select" required>
                                <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                                    $t24 = sprintf('%02d:%02d', $h, $m);
                                    $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                                    <option value="<?= $t24 ?>"><?= $ampm ?></option>
                                <?php endfor; endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Lunch Start</label>
                            <select name="edit_group_lunch_start" id="edit_group_lunch_start" class="form-select" required>
                                <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                                    $t24 = sprintf('%02d:%02d', $h, $m);
                                    $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                                    <option value="<?= $t24 ?>"><?= $ampm ?></option>
                                <?php endfor; endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Lunch End</label>
                            <select name="edit_group_lunch_end" id="edit_group_lunch_end" class="form-select" required>
                                <?php for ($h = 0; $h < 24; $h++): for ($m = 0; $m < 60; $m += 15): 
                                    $t24 = sprintf('%02d:%02d', $h, $m);
                                    $ampm = ($h == 0 ? 12 : ($h > 12 ? $h - 12 : $h)) . ':' . sprintf('%02d', $m) . ($h < 12 ? ' AM' : ' PM'); ?>
                                    <option value="<?= $t24 ?>"><?= $ampm ?></option>
                                <?php endfor; endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="edit_time_group" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showGroupUsersModal(groupId, groupName) {
            document.getElementById('groupUsersModalTitle').textContent = 'Manage "' + groupName + '"';
            fetch('groupusersmodal.php?group_id=' + groupId)
                .then(response => response.json())
                .then(data => {
                    // Users list
                    let html = '';
                    if (data.users.length === 0) {
                        html += '<span class="text-muted">No users in this group.</span>';
                    } else {
                        html += '<div>';
                        data.users.forEach(function(user) {
                            html += `<form method="post" style="display:inline;">
                                <input type="hidden" name="user_id" value="${user.id}">
                                <input type="hidden" name="group_id" value="${groupId}">
                                <span class="badge bg-secondary">${user.username}</span>
                                <button type="submit" name="remove_user_from_group" class="btn btn-sm btn-outline-danger" title="Remove" onclick="return confirm('Remove user from group?')">&times;</button>
                            </form> `;
                        });
                        html += '</div>';
                    }
                    document.getElementById('groupUsersModalBody').innerHTML = html;

                    // Add user form
                    let footer = `<input type="hidden" name="group_id" value="${groupId}">
                        <select name="user_id" class="form-select" style="width:auto;display:inline-block;" required>
                            <option value="">-- Select User --</option>`;
                    data.all_users.forEach(function(user) {
                        footer += `<option value="${user.id}">${user.username}</option>`;
                    });
                    footer += `</select>
                        <button type="submit" name="add_user_to_group" class="btn btn-primary">Add User</button>`;
                    document.getElementById('groupUsersModalFooter').innerHTML = footer;

                    // Delete group button
                    let deleteFooter = `
                        <form method="post" onsubmit="return confirm('Are you sure you want to delete this time group? This cannot be undone.');" style="width:100%;">
                            <input type="hidden" name="group_id" value="${groupId}">
                            <button type="submit" name="delete_time_group" class="btn btn-danger w-100">Delete This Time Group</button>
                        </form>
                    `;
                    document.getElementById('groupUsersModalDeleteFooter').innerHTML = deleteFooter;

                    document.getElementById('addUserToGroupForm').action = '';
                });
            new bootstrap.Modal(document.getElementById('groupUsersModal')).show();
        }

        function showEditTimeGroupModal(id, name, timeIn, timeOut, lunchStart, lunchEnd) {
            document.getElementById('edit_group_id').value = id;
            document.getElementById('edit_group_name').value = name;
            document.getElementById('edit_group_time_in').value = timeIn;
            document.getElementById('edit_group_time_out').value = timeOut;
            document.getElementById('edit_group_lunch_start').value = lunchStart;
            document.getElementById('edit_group_lunch_end').value = lunchEnd;
            new bootstrap.Modal(document.getElementById('editTimeGroupModal')).show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body>
</html>