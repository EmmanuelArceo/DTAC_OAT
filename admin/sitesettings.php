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

// Fetch current default time group or set defaults
$stmt = $oat->prepare("SELECT time_in, time_out, lunch_start, lunch_end FROM time_groups WHERE name = 'Default' LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$default_time_in = $settings['time_in'] ?? '08:00:00';
$default_time_out = $settings['time_out'] ?? '17:00:00';
$lunch_start = $settings['lunch_start'] ?? '12:00:00';
$lunch_end = $settings['lunch_end'] ?? '14:00:00'; // Updated default to 2h

// Handle POST requests with prepared statements for security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_time_group'])) {
        $time_in = date('H:i:s', strtotime($_POST['group_time_in']));
        $time_out = date('H:i:s', strtotime($_POST['group_time_out']));
        $lunch_start_val = date('H:i:s', strtotime($_POST['group_lunch_start']));
        $lunch_end_val = date('H:i:s', strtotime($_POST['group_lunch_end']));
        
        // Validation
        $error = '';
        if (strtotime($lunch_start_val) < strtotime($time_in)) {
            $error = "Lunch start cannot be before time in.";
        } elseif (strtotime($lunch_end_val) > strtotime($time_out)) {
            $error = "Lunch end cannot be after time out.";
        } elseif (strtotime($lunch_end_val) <= strtotime($lunch_start_val)) {
            $error = "Lunch end must be after lunch start.";
        }
        
        if ($error) {
            $success = $error;
            $alert_type = 'danger';
        } else {
            $stmt = $oat->prepare("INSERT INTO time_groups (name, time_in, time_out, lunch_start, lunch_end) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $_POST['group_name'], $time_in, $time_out, $lunch_start_val, $lunch_end_val);
            $stmt->execute();
            $success = "Time group added!";
            $alert_type = 'success';
        }
    } elseif (isset($_POST['delete_time_group'])) {
        $stmt = $oat->prepare("DELETE FROM time_groups WHERE id=?");
        $stmt->bind_param("i", $_POST['group_id']);
        $stmt->execute();
        $success = "Time group deleted!";
        $alert_type = 'success';
    } elseif (isset($_POST['add_user_to_group'])) {
        // Remove user from any existing group first
        $stmt = $oat->prepare("DELETE FROM user_time_groups WHERE user_id=?");
        $stmt->bind_param("i", $_POST['user_id']);
        $stmt->execute();
        
        // Add to the new group
        $stmt = $oat->prepare("INSERT INTO user_time_groups (user_id, group_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $_POST['user_id'], $_POST['group_id']);
        $stmt->execute();
        $success = "User added to group! (Moved if previously in another group)";
        $alert_type = 'success';
    } elseif (isset($_POST['remove_user_from_group'])) {
        $stmt = $oat->prepare("DELETE FROM user_time_groups WHERE user_id=? AND group_id=?");
        $stmt->bind_param("ii", $_POST['user_id'], $_POST['group_id']);
        $stmt->execute();
        $success = "User removed from group!";
        $alert_type = 'success';
    } elseif (isset($_POST['edit_time_group'])) {
        $edit_time_in = date('H:i:s', strtotime($_POST['edit_group_time_in']));
        $edit_time_out = date('H:i:s', strtotime($_POST['edit_group_time_out']));
        $edit_lunch_start = date('H:i:s', strtotime($_POST['edit_group_lunch_start']));
        $edit_lunch_end = date('H:i:s', strtotime($_POST['edit_group_lunch_end']));
        
        // Validation
        $error = '';
        if (strtotime($edit_lunch_start) < strtotime($edit_time_in)) {
            $error = "Lunch start cannot be before time in.";
        } elseif (strtotime($edit_lunch_end) > strtotime($edit_time_out)) {
            $error = "Lunch end cannot be after time out.";
        } elseif (strtotime($edit_lunch_end) <= strtotime($edit_lunch_start)) {
            $error = "Lunch end must be after lunch start.";
        }
        
        if ($error) {
            $success = $error;
            $alert_type = 'danger';
        } else {
            $stmt = $oat->prepare("UPDATE time_groups SET name=?, time_in=?, time_out=?, lunch_start=?, lunch_end=? WHERE id=?");
            $stmt->bind_param("sssssi", $_POST['edit_group_name'], $edit_time_in, $edit_time_out, $edit_lunch_start, $edit_lunch_end, $_POST['edit_group_id']);
            $stmt->execute();
            $success = "Time group updated!";
            $alert_type = 'success';
        }
    }

    // Update default time group with validation
    $new_time_in = date('H:i:s', strtotime($_POST['default_time_in'] ?? '08:00:00'));
    $new_time_out = date('H:i:s', strtotime($_POST['default_time_out'] ?? '17:00:00'));
    $new_lunch_start = date('H:i:s', strtotime($_POST['lunch_start'] ?? '12:00:00'));
    $new_lunch_end = date('H:i:s', strtotime($_POST['lunch_end'] ?? '14:00:00'));
    
    // Validation for default group
    $error = '';
    if (strtotime($new_lunch_start) < strtotime($new_time_in)) {
        $error = "Lunch start cannot be before time in.";
    } elseif (strtotime($new_lunch_end) > strtotime($new_time_out)) {
        $error = "Lunch end cannot be after time out.";
    } elseif (strtotime($new_lunch_end) <= strtotime($new_lunch_start)) {
        $error = "Lunch end must be after lunch start.";
    }
    
    if ($error) {
        $success = $error;
        $alert_type = 'danger';
    } else {
        if ($settings) {
            $stmt = $oat->prepare("UPDATE time_groups SET time_in=?, time_out=?, lunch_start=?, lunch_end=? WHERE name='Default'");
            $stmt->bind_param("ssss", $new_time_in, $new_time_out, $new_lunch_start, $new_lunch_end);
            $stmt->execute();
        } else {
            $stmt = $oat->prepare("INSERT INTO time_groups (name, time_in, time_out, lunch_start, lunch_end) VALUES ('Default', ?, ?, ?, ?)");
            $stmt->bind_param("ssss", $new_time_in, $new_time_out, $new_lunch_start, $new_lunch_end);
            $stmt->execute();
        }
        $default_time_in = $new_time_in;
        $default_time_out = $new_time_out;
        $lunch_start = $new_lunch_start;
        $lunch_end = $new_lunch_end;
        if (!isset($success)) {
            $success = "Default time group updated!";
            $alert_type = 'success';
        }
    }
}

// Fetch data
$users = $oat->query("SELECT id, username FROM users ORDER BY username");
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
            max-width: 800px;
            margin: 40px auto;
            padding: 32px 24px;
        }
    </style>
</head>
<body>
    <div class="glass">
        <h3 class="mb-4">Site Settings</h3>
        <?php if (!empty($success)): ?>
            <div class="alert alert-<?= $alert_type ?? 'success' ?> alert-dismissible fade show" role="alert" id="success-alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <form method="post" class="mb-4">
            <h5>Default Time Group</h5>
            <div class="row">
                <div class="col-md-3">
                    <label for="default_time_in" class="form-label">Default Time In</label>
                    <input type="text" id="default_time_in" name="default_time_in" class="form-control time-picker" value="<?= htmlspecialchars(date('h:i A', strtotime($default_time_in))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="default_time_out" class="form-label">Default Time Out</label>
                    <input type="text" id="default_time_out" name="default_time_out" class="form-control time-picker" value="<?= htmlspecialchars(date('h:i A', strtotime($default_time_out))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="lunch_start" class="form-label">Lunch Break Start</label>
                    <input type="text" id="lunch_start" name="lunch_start" class="form-control time-picker" value="<?= htmlspecialchars(date('h:i A', strtotime($lunch_start))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="lunch_end" class="form-label">Lunch Break End</label>
                    <input type="text" id="lunch_end" name="lunch_end" class="form-control time-picker" value="<?= htmlspecialchars(date('h:i A', strtotime($lunch_end))) ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save Default Group</button>
        </form>

        <h5 class="mb-3">Time Groups</h5>
        <form method="post" class="mb-4">
            <div class="row g-2">
                <div class="col-md-2">
                    <input type="text" name="group_name" class="form-control" placeholder="Group Name" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="group_time_in" class="form-control time-picker" placeholder="Time In" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="group_time_out" class="form-control time-picker" placeholder="Time Out" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="group_lunch_start" class="form-control time-picker" placeholder="Lunch Start" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="group_lunch_end" class="form-control time-picker" placeholder="Lunch End" required>
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($group = $time_groups->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($group['name']) ?></td>
                            <td><?= date('h:i A', strtotime($group['time_in'])) ?></td>
                            <td><?= date('h:i A', strtotime($group['time_out'])) ?></td>
                            <td><?= date('h:i A', strtotime($group['lunch_start'])) ?></td>
                            <td><?= date('h:i A', strtotime($group['lunch_end'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="showGroupUsersModal(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>')">Manage</button>
                                <?php if ($group['name'] !== 'Default'): ?>
                                <button class="btn btn-sm btn-warning" onclick="showEditTimeGroupModal(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($group['time_in'], ENT_QUOTES) ?>', '<?= htmlspecialchars($group['time_out'], ENT_QUOTES) ?>', '<?= htmlspecialchars($group['lunch_start'], ENT_QUOTES) ?>', '<?= htmlspecialchars($group['lunch_end'], ENT_QUOTES) ?>')">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Users & Management Modal -->
    <div class="modal fade" id="groupUsersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" id="addUserToGroupForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupUsersModalTitle">Time Group Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_group_id" id="edit_group_id">
                        <div class="mb-3">
                            <label>Group Name</label>
                            <input type="text" name="edit_group_name" id="edit_group_name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Time In</label>
                                <input type="text" name="edit_group_time_in" id="edit_group_time_in" class="form-control time-picker" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Time Out</label>
                                <input type="text" name="edit_group_time_out" id="edit_group_time_out" class="form-control time-picker" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Lunch Start</label>
                                <input type="text" name="edit_group_lunch_start" id="edit_group_lunch_start" class="form-control time-picker" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Lunch End</label>
                                <input type="text" name="edit_group_lunch_end" id="edit_group_lunch_end" class="form-control time-picker" required>
                            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize Flatpickr for all time pickers with 12-hour format and AM/PM
        flatpickr('.time-picker', {
            enableTime: true,
            noCalendar: true,
            dateFormat: "h:i K",
            time_24hr: false
        });

        // Auto-dismiss success alert after 3 seconds
        const alertElement = document.getElementById('success-alert');
        if (alertElement) {
            setTimeout(() => {
                alertElement.classList.remove('show');
                setTimeout(() => alertElement.remove(), 150); // Wait for fade transition
            }, 3000);
        }

        function showGroupUsersModal(groupId, groupName) {
            document.getElementById('groupUsersModalTitle').textContent = 'Manage "' + groupName + '"';
            fetch('groupusersmodal.php?group_id=' + groupId)
                .then(response => response.json())
                .then(data => {
                    let html = '<h6>Users in Group:</h6>';
                    if (data.users.length === 0) {
                        html += '<p class="text-muted">No users in this group.</p>';
                    } else {
                        data.users.forEach(user => {
                            html += `<span class="badge bg-secondary me-2" title="${user.username}">${user.full_name} <button type="button" class="btn-close btn-close-white ms-1" onclick="removeUser(${user.id}, ${groupId})" title="Remove"></button></span>`;
                        });
                    }
                    document.getElementById('groupUsersModalBody').innerHTML = html;

                    let footer = `<select name="user_id" class="form-select d-inline-block w-auto me-2" required><option value="">-- Select User --</option>`;
                    data.all_users.forEach(user => footer += `<option value="${user.id}">${user.full_name} (${user.username})</option>`);
                    footer += `</select><button type="submit" name="add_user_to_group" class="btn btn-primary">Add User</button><input type="hidden" name="group_id" value="${groupId}">`;
                    document.getElementById('groupUsersModalFooter').innerHTML = footer;

                    let deleteFooter = `<button type="button" class="btn btn-danger w-100" onclick="deleteGroup(${groupId})">Delete This Time Group</button>`;
                    document.getElementById('groupUsersModalDeleteFooter').innerHTML = deleteFooter;
                })
                .catch(error => console.error('Error loading modal:', error));
            new bootstrap.Modal(document.getElementById('groupUsersModal')).show();
        }

        function removeUser(userId, groupId) {
            if (confirm('Remove user from group?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `<input name="user_id" value="${userId}"><input name="group_id" value="${groupId}"><input name="remove_user_from_group">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteGroup(groupId) {
            if (confirm('Are you sure you want to delete this time group? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `<input name="group_id" value="${groupId}"><input name="delete_time_group">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showEditTimeGroupModal(id, name, timeIn, timeOut, lunchStart, lunchEnd) {
            document.getElementById('edit_group_id').value = id;
            document.getElementById('edit_group_name').value = name;
            document.getElementById('edit_group_time_in').value = new Date('1970-01-01 ' + timeIn).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('edit_group_time_out').value = new Date('1970-01-01 ' + timeOut).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('edit_group_lunch_start').value = new Date('1970-01-01 ' + lunchStart).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('edit_group_lunch_end').value = new Date('1970-01-01 ' + lunchEnd).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            new bootstrap.Modal(document.getElementById('editTimeGroupModal')).show();
        }
    </script>
</body>
</html>