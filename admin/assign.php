<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../db.php'; // Assumes $oat is the mysqli connection
include 'nav.php';

// 1. Session & Access Control
if (!isset($_SESSION['user_id'])) {
    echo '<script>location.href="../login.php";</script>';
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? '';

// 2. Flash Message Logic
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// 3. Helper Functions
function fmt_name($f, $l) {
    $n = trim((string)($f ?? '') . ' ' . (string)($l ?? ''));
    return $n === '' ? 'All OJT' : $n;
}

// 4. Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to_raw = $_POST['assigned_to'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $due_date = $_POST['due_date'] ?? '';

        if ($title !== '' && $assigned_to_raw !== '') {
            $assigned_to_val = ($assigned_to_raw === 'all' && $current_role === 'super_admin') ? 0 : (int)$assigned_to_raw;
            $created_count = 0;
            
            $stmt = $oat->prepare("INSERT INTO assignments (title, description, assigned_to, assigned_by, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
            
            if ($stmt) {
                // Determine if we are doing a range or single date
                if (!empty($start_date) && !empty($end_date)) {
                    try {
                        $startDT = new DateTime($start_date);
                        $endDT = new DateTime($end_date);
                        if ($endDT >= $startDT) {
                            $interval = new DateInterval('P1D');
                            $period = new DatePeriod($startDT, $interval, $endDT->modify('+1 day'));
                            foreach ($period as $dt) {
                                if ($dt->format('w') === '0') continue; // Skip Sundays
                                $d_str = $dt->format('Y-m-d');
                                $stmt->bind_param('ssiis', $title, $description, $assigned_to_val, $current_user_id, $d_str);
                                if ($stmt->execute()) $created_count++;
                            }
                        }
                    } catch (Exception $e) { /* Fallback to single if error */ }
                } else {
                    $final_date = !empty($start_date) ? $start_date : (!empty($end_date) ? $end_date : (!empty($due_date) ? $due_date : null));
                    $stmt->bind_param('ssiis', $title, $description, $assigned_to_val, $current_user_id, $final_date);
                    if ($stmt->execute()) $created_count = 1;
                }
                $stmt->close();
            }

            $_SESSION['flash_message'] = $created_count > 0 ? "$created_count task(s) assigned." : "Failed to create task.";
            $_SESSION['flash_type'] = $created_count > 0 ? "success" : "danger";
        }
    }

    if ($action === 'update' && $id > 0) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to_raw = $_POST['assigned_to'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $assigned_to_val = ($assigned_to_raw === 'all' && $current_role === 'super_admin') ? 0 : (int)$assigned_to_raw;

        $stmt = $oat->prepare("UPDATE assignments SET title=?, description=?, assigned_to=?, due_date=? WHERE id=?");
        $stmt->bind_param('ssisi', $title, $description, $assigned_to_val, $due_date, $id);
        $_SESSION['flash_message'] = $stmt->execute() ? "Task updated." : "Update failed.";
        $_SESSION['flash_type'] = "info";
        $stmt->close();
    }

    if ($action === 'archive' && $id > 0) {
        $stmt = $oat->prepare("UPDATE assignments SET status='archived', archived_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $id);
        $_SESSION['flash_message'] = $stmt->execute() ? "Task archived." : "Archive failed.";
        $_SESSION['flash_type'] = "warning";
        $stmt->close();
    }

    echo '<script>window.location.replace(window.location.pathname);</script>';
    exit;
}

// 5. Fetch Data (Optimized)
$ojts = [];
$query = ($current_role === 'super_admin') 
    ? "SELECT id, fname, lname, profile_img FROM users WHERE role='ojt' ORDER BY fname ASC"
    : "SELECT id, fname, lname, profile_img FROM users WHERE role='ojt' AND adviser_id=$current_user_id ORDER BY fname ASC";

$resOjt = $oat->query($query);
while ($r = $resOjt->fetch_assoc()) {
    $ojts[] = ['id' => $r['id'], 'name' => fmt_name($r['fname'], $r['lname']), 'img' => $r['profile_img']];
}

$today = date('Y-m-d');
$today_tasks = [];
$upcoming_tasks = [];
$past_tasks = [];
$upcoming_tracker = []; // To ensure only one card per OJT in the summary view

$resAss = $oat->query("
    SELECT a.*, ua.fname as to_f, ua.lname as to_l, ub.fname as by_f, ub.lname as by_l 
    FROM assignments a 
    LEFT JOIN users ua ON a.assigned_to = ua.id 
    LEFT JOIN users ub ON a.assigned_by = ub.id 
    WHERE a.status != 'archived' 
    ORDER BY a.due_date ASC, a.created_at DESC
");

while ($row = $resAss->fetch_assoc()) {
    $d = $row['due_date'];
    if (!$d || $d === '0000-00-00') {
        $upcoming_tasks[] = $row;
    } elseif ($d === $today) {
        $today_tasks[] = $row;
    } elseif ($d > $today) {
        $upcoming_tasks[] = $row;
    } else {
        $past_tasks[] = $row;
    }
}

// Logic for "Upcoming" summary (One per OJT)
$upcoming_display_list = [];
foreach ($upcoming_tasks as $t) {
    $uid = (int)$t['assigned_to'];
    if (!isset($upcoming_tracker[$uid])) {
        $upcoming_display_list[] = $t;
        $upcoming_tracker[$uid] = true;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assign Task — Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{ --accent:#3CB3CC; --muted:#6b7280; }
        body{font-family:Inter, system-ui, sans-serif; background:#f7fafc; color:#0f172a}
        .page-wrapper{max-width:1200px;margin:28px auto;padding:18px}
        .card{border:0;border-radius:12px;box-shadow:0 8px 30px rgba(15,23,42,0.04); margin-bottom:20px}
        .card-header{background:transparent;border-bottom:0;padding:12px 18px;font-weight:700;color:var(--accent)}
        .btn-pill{border-radius:999px}
        .flash-msg{position:fixed;left:50%;top:18%;transform:translateX(-50%);background:#fff;padding:16px 20px;border-radius:10px;box-shadow:0 14px 40px rgba(2,6,23,0.08);z-index:2000;text-align:center}
        .assignment-card{background:#fff;border:1px solid rgba(15,23,42,0.04);border-radius:10px;padding:14px;margin-bottom:12px; transition: 0.2s;}
        .assignment-card:hover{ border-color: var(--accent); }
        .small-muted{color:var(--muted);font-size:13px}
        .assignee-link{cursor:pointer;text-decoration:none; color: var(--accent); font-weight: 500;}
        .section-divider{display:flex;align-items:center;gap:12px;margin:18px 0;}
        .section-divider hr{flex:1;border:0;height:1px;background:linear-gradient(90deg,#e6eef2,#f7fafc);}
        .section-divider .label{padding:6px 12px;border-radius:999px;background:#f1f7fc;color:#0f172a;font-weight:600;font-size:13px;}
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="d-flex align-items-center mb-4">
        <h3 class="me-auto mb-0">Assign Task</h3>
        <button onclick="window.location.reload()" class="btn btn-sm btn-outline-secondary">Refresh</button>
    </div>

    <?php if ($flash_message): ?>
        <div id="flashMsg" class="flash-msg">
            <div class="fw-bold mb-1 text-<?php echo $flash_type; ?>"><?php echo htmlspecialchars(ucfirst($flash_type)); ?></div>
            <div class="mb-2"><?php echo htmlspecialchars($flash_message); ?></div>
            <button onclick="document.getElementById('flashMsg').remove()" class="btn btn-sm btn-primary">OK</button>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Create New Task</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input name="title" type="text" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign to</label>
                            <select name="assigned_to" class="form-select" required>
                                <option value="">-- select OJT --</option>
                                <?php if ($current_role === 'super_admin'): ?><option value="all">All OJT</option><?php endif; ?>
                                <?php foreach ($ojts as $o): ?>
                                    <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6 mb-2">
                                <label class="form-label small">Start Date</label>
                                <input name="start_date" type="date" class="form-control">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label small">End Date</label>
                                <input name="end_date" type="date" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Or Single Date</label>
                            <input name="due_date" type="date" class="form-control">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-pill">Assign Task</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Assignments</span>
                    <input id="searchBox" class="form-control form-control-sm w-50" placeholder="Search tasks or names...">
                </div>
                <div class="card-body">
                    
                    <div class="mb-4 section-group">
                        <div class="fw-bold mb-2">Today's Tasks (<?php echo count($today_tasks); ?>)</div>
                        <?php foreach ($today_tasks as $t) renderTask($t); ?>
                        <?php if(!$today_tasks) echo '<p class="text-muted small">No tasks for today.</p>'; ?>
                    </div>

                    <div class="mb-4 section-group">
                        <div class="section-divider">
                            <hr><div class="label">Upcoming (<?php echo count($upcoming_display_list); ?> unique)</div><hr>
                        </div>
                        <?php foreach ($upcoming_display_list as $t) renderTask($t); ?>
                        <?php if(!$upcoming_display_list) echo '<p class="text-muted small">No upcoming tasks.</p>'; ?>
                    </div>

                    <div class="mb-2">
                        <button id="toggleHistory" class="btn btn-outline-secondary btn-sm">Toggle History (<?php echo count($past_tasks); ?>)</button>
                    </div>
                    <div id="historyList" style="display:none;" class="section-group">
                        <?php foreach ($past_tasks as $t) renderTask($t, true); ?>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php 
function renderTask($t, $isHistory = false) { 
    global $ojts;
    $name = fmt_name($t['to_f'] ?? '', $t['to_l'] ?? '');
    $by = fmt_name($t['by_f'] ?? '', $t['by_l'] ?? '');
    // weekday + formatted date
    $day = !empty($t['due_date']) && $t['due_date'] !== '0000-00-00' ? date('l', strtotime($t['due_date'])) : '';
    $dueDisplay = !empty($t['due_date']) && $t['due_date'] !== '0000-00-00' ? ($t['due_date'] . ($day ? ' · ' . $day : '')) : 'No date';
    
    // determine assignee thumbnail (prefer record, then ojts list, fallback placeholder)
    $assignee_thumb = 'https://via.placeholder.com/36?text=O';
    $assigned_id = (int)($t['assigned_to'] ?? 0);
    if ($assigned_id === 0) {
        $assignee_thumb = 'https://via.placeholder.com/36?text=ALL';
    } else {
        if (!empty($t['to_profile_img'])) {
            $assignee_thumb = $t['to_profile_img'];
        } else {
            foreach ($ojts as $o) {
                if ((int)$o['id'] === $assigned_id && !empty($o['img'])) {
                    $assignee_thumb = $o['img'];
                    break;
                }
            }
        }
    }
?>
    <div class="assignment-card task-item" 
         data-assigned-to="<?php echo (int)$t['assigned_to']; ?>" 
         data-search="<?php echo strtolower(htmlspecialchars($t['title'].' '.$name)); ?>">
        <div class="d-flex justify-content-between">
            <div>
                <div class="fw-bold"><?php echo htmlspecialchars($t['title']); ?></div>
                <div class="small-muted"><?php echo htmlspecialchars(mb_strimwidth($t['description'], 0, 100, '...')); ?></div>
                <div class="small-muted mt-1 d-flex align-items-center">
                    <img src="../<?php echo htmlspecialchars($assignee_thumb); ?>" alt="" class="rounded-circle me-2" width="36" height="36" style="object-fit:cover">
                    Assignee:
                    <a href="#" class="assignee-link ms-2" data-assigned-to="<?php echo (int)$t['assigned_to']; ?>">
                        <?php echo htmlspecialchars($name); ?>
                    </a>
                </div>
            </div>
            <div class="text-end">
                <div class="small fw-bold"><?php echo htmlspecialchars($dueDisplay); ?></div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-outline-primary btn-details" 
                        data-id="<?php echo (int)$t['id']; ?>"
                        data-title="<?php echo htmlspecialchars($t['title']); ?>"
                        data-desc="<?php echo htmlspecialchars($t['description']); ?>"
                        data-uid="<?php echo (int)$t['assigned_to']; ?>"
                        data-uname="<?php echo htmlspecialchars($name); ?>"
                        data-date="<?php echo htmlspecialchars($t['due_date']); ?>"
                        data-by="<?php echo htmlspecialchars($by); ?>">Details</button>
                    <?php if($isHistory): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Archive permanently?');">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger">Del</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post">
            <div class="modal-header"><h5 class="modal-title">Edit Task</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3"><label class="form-label">Title</label><input id="edit_title" name="title" type="text" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea id="edit_desc" name="description" rows="3" class="form-control"></textarea></div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label">Assignee</label>
                        <select id="edit_uid" name="assigned_to" class="form-select">
                            <?php if ($current_role === 'super_admin'): ?><option value="all">All OJT</option><?php endif; ?>
                            <?php foreach ($ojts as $o): ?><option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Date</label><input id="edit_date" name="due_date" type="date" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary btn-pill">Save Changes</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Task Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detailsBody"></div>
            <div class="modal-footer">
                <button id="btnTriggerEdit" class="btn btn-outline-primary btn-pill">Edit</button>
                <form method="post" onsubmit="return confirm('Archive?');">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" id="details_id">
                    <button class="btn btn-danger btn-pill">Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editModal = new bootstrap.Modal('#editModal');
    const detailsModal = new bootstrap.Modal('#detailsModal');

    // Details Modal Logic (show date + weekday)
    document.querySelectorAll('.btn-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const d = this.dataset;
            const dayName = d.date ? new Date(d.date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long' }) : '';
            document.getElementById('details_id').value = d.id;
            document.getElementById('detailsBody').innerHTML = `
                <h6>${d.title}</h6>
                <p class="text-muted">${d.desc}</p>
                <hr>
                <div class="small">
                    <strong>Assignee:</strong> ${d.uname}<br>
                    <strong>Date:</strong> ${d.date ? d.date + (dayName ? ' · ' + dayName : '') : 'No date'}<br>
                    <strong>Created By:</strong> ${d.by}
                </div>
            `;

            // Map data to Edit Modal for the "Edit" button
            document.getElementById('btnTriggerEdit').onclick = () => {
                document.getElementById('edit_id').value = d.id;
                document.getElementById('edit_title').value = d.title;
                document.getElementById('edit_desc').value = d.desc;
                document.getElementById('edit_uid').value = d.uid;
                document.getElementById('edit_date').value = d.date;
                detailsModal.hide();
                editModal.show();
            };

            detailsModal.show();
        });
    });
});
</script>
</body>
</html>