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
    $ot_date = $_POST['ot_date'] ?? date('Y-m-d');
    $ot_reason = trim($_POST['ot_reason'] ?? '');
    $ot_start_time = $_POST['ot_start_time'] ?? '';
    $ot_end_time = $_POST['ot_end_time'] ?? '';

    // Validate
    if ($ot_start_time === '' || $ot_end_time === '') {
        $errors[] = 'OT start and end time are required.';
    }

    // Build DateTime objects
    $actualStartDT = DateTime::createFromFormat('Y-m-d H:i', $ot_date . ' ' . $ot_start_time);
    $actualEndDT = DateTime::createFromFormat('Y-m-d H:i', $ot_date . ' ' . $ot_end_time);

    if (!$actualStartDT || !$actualEndDT) {
        $errors[] = 'Invalid OT start/end time.';
    } else {
        // Handle midnight crossing
        if ($actualEndDT <= $actualStartDT) $actualEndDT->modify('+1 day');
        $ot_hours = round(($actualEndDT->getTimestamp() - $actualStartDT->getTimestamp()) / 3600, 2);
        if ($ot_hours <= 0) $errors[] = 'Calculated OT hours is 0. No OT to submit.';
    }

    // Handle file uploads
    $upload_dir = 'uploads/ot_proofs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $before_img_path = '';
    $after_img_path = '';
    if (!empty($_FILES['before_img']['name']) && $_FILES['before_img']['error'] === UPLOAD_ERR_OK) {
        $before_img_path = $upload_dir . uniqid('before_') . '_' . basename($_FILES['before_img']['name']);
        move_uploaded_file($_FILES['before_img']['tmp_name'], $before_img_path);
    }
    if (!empty($_FILES['after_img']['name']) && $_FILES['after_img']['error'] === UPLOAD_ERR_OK) {
        $after_img_path = $upload_dir . uniqid('after_') . '_' . basename($_FILES['after_img']['name']);
        move_uploaded_file($_FILES['after_img']['tmp_name'], $after_img_path);
    }

    if (empty($errors)) {
        $stmt = $oat->prepare("INSERT INTO ot_reports (student_id, ot_hours, ot_date, ot_reason, reported_time_in, reported_time_out, before_img, after_img) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ot_start = $actualStartDT->format('H:i:s');
        $ot_end = $actualEndDT->format('H:i:s');
        $stmt->bind_param("idssssss", $user_id, $ot_hours, $ot_date, $ot_reason, $ot_start, $ot_end, $before_img_path, $after_img_path);
        if ($stmt->execute()) $success = 'OT report submitted successfully!';
        else $errors[] = 'Database error while saving report.';
    }
}

// Admin summaries
$ot_summaries = [];
if (($_SESSION['role'] ?? '') === 'admin') {
    $stmt = $oat->prepare("SELECT u.fname, u.lname, MONTH(otr.ot_date) AS month, YEAR(otr.ot_date) AS year, SUM(otr.ot_hours) AS total_ot FROM ot_reports otr JOIN users u ON otr.student_id = u.id WHERE otr.approved = 1 GROUP BY u.id, YEAR(otr.ot_date), MONTH(otr.ot_date) ORDER BY year DESC, month DESC");
    $stmt->execute();
    $ot_summaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$currentHour = date('H');
$defaultTime = sprintf('%02d:00', $currentHour);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Submit OT Report</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<main class="container py-4" style="max-width:680px">
    <div class="card mb-3">
        <div class="card-body">
            <h4 class="card-title text-primary">Submit OT Report</h4>
            <p class="text-muted">OT includes work outside your normal schedule. Please specify the date and actual OT start/end times.</p>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">OT Date</label>
                    <input type="date" name="ot_date" class="form-control" value="<?php echo htmlspecialchars($ot_date); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">OT Start Time</label>
                    <input type="time" name="ot_start_time" class="form-control" value="17:00"  required>
                </div>
                <div class="mb-3">
                    <label class="form-label">OT End Time</label>
                    <input type="time" name="ot_end_time" class="form-control" value="18:00" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Calculated OT Hours</label>
                    <input type="number" id="ot_hours" name="ot_hours" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="ot_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Before Image (optional)</label>
                    <input type="file" name="before_img" class="form-control" accept="image/*">
                </div>
                <div class="mb-3">
                    <label class="form-label">After Image (optional)</label>
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
                        <td><?php echo htmlspecialchars($s['fname'].' '.$s['lname']); ?></td>
                        <td><?php echo htmlspecialchars($s['month'].'/'.$s['year']); ?></td>
                        <td><?php echo htmlspecialchars($s['total_ot']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>
<script>
function calculateOT(){
    const start = document.querySelector('[name="ot_start_time"]').value;
    const end = document.querySelector('[name="ot_end_time"]').value;
    if (!start || !end) { document.getElementById('ot_hours').value = ''; return; }
    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let startMinutes = sh*60 + sm;
    let endMinutes = eh*60 + em;
    if (endMinutes <= startMinutes) endMinutes += 24*60;
    let total = (endMinutes - startMinutes)/60;
    document.getElementById('ot_hours').value = Math.round(total*100)/100;
}
['ot_start_time','ot_end_time'].forEach(id=>{
    const el = document.querySelector(`[name="${id}"]`);
    if (el) el.addEventListener('input', calculateOT);
});
document.addEventListener('DOMContentLoaded', calculateOT);
</script>
</body>
</html>