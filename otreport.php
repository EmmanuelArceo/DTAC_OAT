<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
require_once 'nav.php';
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$ot_date = $_POST['ot_date'] ?? date('Y-m-d');
$errors = [];
$success = '';

// Handle OT report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ot_report'])) {
    $ot_date       = $_POST['ot_date']       ?? date('Y-m-d');
    $ot_reason     = trim($_POST['ot_reason'] ?? '');
    $ot_start_time = $_POST['ot_start_time'] ?? '';
    $ot_end_time   = $_POST['ot_end_time']   ?? '';

    // Validate times present
    if ($ot_start_time === '' || $ot_end_time === '') {
        $errors[] = 'OT start and end time are required.';
    }

    // Build DateTime objects
    $actualStartDT = DateTime::createFromFormat('Y-m-d H:i', $ot_date . ' ' . $ot_start_time);
    $actualEndDT   = DateTime::createFromFormat('Y-m-d H:i', $ot_date . ' ' . $ot_end_time);

    if (!$actualStartDT || !$actualEndDT) {
        $errors[] = 'Invalid OT start/end time.';
    } else {
        // Handle midnight crossing
        if ($actualEndDT <= $actualStartDT) $actualEndDT->modify('+1 day');
        $ot_hours = round(($actualEndDT->getTimestamp() - $actualStartDT->getTimestamp()) / 3600, 2);
        if ($ot_hours <= 0) $errors[] = 'Calculated OT hours is 0. No OT to submit.';

        // Determine OT type: before noon = early, noon or after = late (normal OT)
        $ot_type = ((int)$actualStartDT->format('H') < 12) ? 'early' : 'late';

        // ── Duplicate check ──────────────────────────────────────────────────
        // Block if the student already has a pending or approved OT report
        // for the same date AND same type (early/late).
        if (empty($errors)) {
            $dupCheck = $oat->prepare("
                SELECT id FROM ot_reports
                WHERE student_id = ?
                  AND ot_date    = ?
                  AND ot_type    = ?
                  AND approved  != -1
                LIMIT 1
            ");
            $dupCheck->bind_param("iss", $user_id, $ot_date, $ot_type);
            $dupCheck->execute();
            $dupCheck->store_result();
            if ($dupCheck->num_rows > 0) {
                $typeLabel = $ot_type === 'early' ? 'Early OT' : 'Normal OT';
                $errors[] = "You have already submitted a {$typeLabel} report for " . date('F j, Y', strtotime($ot_date)) . ". Only one report per type per day is allowed.";
            }
            $dupCheck->close();
        }
    }

    // Handle file uploads
    $upload_dir = 'uploads/ot_proofs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $before_img_path = '';
    $after_img_path  = '';
    if (!empty($_FILES['before_img']['name']) && $_FILES['before_img']['error'] === UPLOAD_ERR_OK) {
        $before_img_path = $upload_dir . uniqid('before_') . '_' . basename($_FILES['before_img']['name']);
        move_uploaded_file($_FILES['before_img']['tmp_name'], $before_img_path);
    }
    if (!empty($_FILES['after_img']['name']) && $_FILES['after_img']['error'] === UPLOAD_ERR_OK) {
        $after_img_path = $upload_dir . uniqid('after_') . '_' . basename($_FILES['after_img']['name']);
        move_uploaded_file($_FILES['after_img']['tmp_name'], $after_img_path);
    }

    if (empty($errors)) {
        $stmt = $oat->prepare("
            INSERT INTO ot_reports
                (student_id, ot_hours, ot_type, ot_date, ot_reason, reported_time_in, reported_time_out, before_img, after_img)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ot_start = $actualStartDT->format('H:i:s');
        $ot_end   = $actualEndDT->format('H:i:s');
        $stmt->bind_param("idsssssss", $user_id, $ot_hours, $ot_type, $ot_date, $ot_reason, $ot_start, $ot_end, $before_img_path, $after_img_path);
        if ($stmt->execute()) {
            $success = 'OT report submitted successfully!';
        } else {
            $errors[] = 'Database error while saving report.';
        }
    }
}

// Admin summaries
$ot_summaries = [];
if (($_SESSION['role'] ?? '') === 'admin') {
    $stmt = $oat->prepare("
        SELECT u.fname, u.lname,
               MONTH(otr.ot_date) AS month,
               YEAR(otr.ot_date)  AS year,
               SUM(otr.ot_hours)  AS total_ot
        FROM ot_reports otr
        JOIN users u ON otr.student_id = u.id
        WHERE otr.approved = 1
        GROUP BY u.id, YEAR(otr.ot_date), MONTH(otr.ot_date)
        ORDER BY year DESC, month DESC
    ");
    $stmt->execute();
    $ot_summaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Submit OT Report</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    #ot_type_badge { font-size: .85rem; }
</style>
</head>
<body>
<main class="container py-4" style="max-width:680px">
    <div class="card mb-3">
        <div class="card-body">
        <!--    <h4 class="card-title text-primary">Submit OT Report</h4>
            <p class="text-muted">OT includes work outside your normal schedule. Please specify the date and actual OT start/end times.</p>
            <p class="text-muted small">
                <strong>Early OT</strong> = starts before 12:00 PM &nbsp;|&nbsp;
                <strong>Normal OT</strong> = starts at 12:00 PM or later.
                Only one report of each type is allowed per day.
            </p>

-->

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">OT Date</label>
                    <input type="date" name="ot_date" class="form-control"
                           value="<?= htmlspecialchars($ot_date) ?>" required>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col">
                        <label class="form-label fw-semibold">OT Start Time</label>
                        <input type="time" name="ot_start_time" class="form-control" value="17:00" required>
                    </div>
                    <div class="col">
                        <label class="form-label fw-semibold">OT End Time</label>
                        <input type="time" name="ot_end_time" class="form-control" value="18:00" required>
                    </div>
                </div>
                <div class="mb-3 d-flex align-items-center gap-3">
                    <div class="flex-grow-1">
                        <label class="form-label fw-semibold">Calculated OT Hours</label>
                        <input type="number" id="ot_hours" name="ot_hours" class="form-control" readonly>
                    </div>
                    <div>
                        <label class="form-label fw-semibold d-block">OT Type</label>
                        <span id="ot_type_badge" class="badge bg-secondary fs-6 px-3 py-2">—</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason</label>
                    <textarea name="ot_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Before Image <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="file" name="before_img" class="form-control" accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">After Image <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="file" name="after_img" class="form-control" accept="image/*">
                </div>
                <button type="submit" name="submit_ot_report" class="btn btn-primary w-100">Submit OT Report</button>
            </form>
        </div>
    </div>

    <?php if (!empty($ot_summaries)): ?>
    <div class="card">
        <div class="card-body">
            <h5>OT Summaries (Admin)</h5>
            <table class="table table-sm">
                <thead><tr><th>Student</th><th>Month/Year</th><th>Total OT Hours</th></tr></thead>
                <tbody>
                <?php foreach ($ot_summaries as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['fname'].' '.$s['lname']) ?></td>
                        <td><?= htmlspecialchars($s['month'].'/'.$s['year']) ?></td>
                        <td><?= htmlspecialchars($s['total_ot']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
function calculateOT() {
    const startVal = document.querySelector('[name="ot_start_time"]').value;
    const endVal   = document.querySelector('[name="ot_end_time"]').value;
    const hoursEl  = document.getElementById('ot_hours');
    const badgeEl  = document.getElementById('ot_type_badge');

    if (!startVal || !endVal) {
        hoursEl.value = '';
        badgeEl.textContent = '—';
        badgeEl.className = 'badge bg-secondary fs-6 px-3 py-2';
        return;
    }

    const [sh, sm] = startVal.split(':').map(Number);
    const [eh, em] = endVal.split(':').map(Number);
    let startMinutes = sh * 60 + sm;
    let endMinutes   = eh * 60 + em;
    if (endMinutes <= startMinutes) endMinutes += 24 * 60;
    const total = (endMinutes - startMinutes) / 60;
    hoursEl.value = Math.round(total * 100) / 100;

    // Show OT type badge (mirrors PHP logic: before noon = early)
    if (sh < 12) {
        badgeEl.textContent = '☀️ Early OT';
        badgeEl.className = 'badge bg-warning text-dark fs-6 px-3 py-2';
    } else {
        badgeEl.textContent = '🌙 Normal OT';
        badgeEl.className = 'badge bg-info text-dark fs-6 px-3 py-2';
    }
}

['ot_start_time', 'ot_end_time'].forEach(name => {
    const el = document.querySelector(`[name="${name}"]`);
    if (el) el.addEventListener('input', calculateOT);
});
document.addEventListener('DOMContentLoaded', calculateOT);
</script>
</body>
</html>