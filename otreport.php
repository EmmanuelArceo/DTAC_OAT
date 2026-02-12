<?php
// Session and access control
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
require_once 'nav.php';
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Fetch user policy times
$stmt = $oat->prepare("SELECT default_time_in, default_time_out FROM site_settings LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$user_policy_time_in = $settings['default_time_in'] ?? '08:00:00';
$user_policy_time_out = $settings['default_time_out'] ?? '17:00:00';
$stmt = $oat->prepare("SELECT tg.time_in, tg.time_out FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if ($group) {
    $user_policy_time_in = $group['time_in'];
    $user_policy_time_out = $group['time_out'];
}

$errors = [];
$success = '';

// Handle OT report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ot_report'])) {
    $ot_type = $_POST['ot_type'] ?? '';
    $ot_date = $_POST['ot_date'] ?? date('Y-m-d');
    $ot_reason = trim($_POST['ot_reason'] ?? '');
    $ot_hours = 0;
    $actualInDT = $actualOutDT = null;

    // File upload (optional, not stored in DB)
    if (!empty($_FILES['ot_proof']['name']) && $_FILES['ot_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/ot_proofs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = uniqid() . '_' . basename($_FILES['ot_proof']['name']);
        move_uploaded_file($_FILES['ot_proof']['tmp_name'], $upload_dir . $file_name);
    }

    // Build DateTime objects
    try {
        $policyInDT = new DateTime("$ot_date $user_policy_time_in");
        $policyOutDT = new DateTime("$ot_date $user_policy_time_out");
    } catch (Exception $e) {
        $errors[] = 'Invalid policy time configuration.';
    }

    if ($ot_type === 'early') {
        $time_in_hour = $_POST['time_in_hour'] ?? '';
        $time_in_ampm = $_POST['time_in_ampm'] ?? 'AM';
        if ($time_in_hour === '') {
            $errors[] = 'Actual time in is required for early OT.';
        } else {
            $actualInStr = "$ot_date $time_in_hour:00 $time_in_ampm";
            $actualInDT = DateTime::createFromFormat('Y-m-d g:i A', $actualInStr);
            $actualOutDT = clone $policyOutDT;
        }
    } elseif ($ot_type === 'late') {
        $time_out_hour = $_POST['time_out_hour'] ?? '';
        $time_out_ampm = $_POST['time_out_ampm'] ?? 'PM';
        if ($time_out_hour === '') {
            $errors[] = 'Actual time out is required for normal OT.';
        } else {
            $actualOutStr = "$ot_date $time_out_hour:00 $time_out_ampm";
            $actualOutDT = DateTime::createFromFormat('Y-m-d g:i A', $actualOutStr);
            $actualInDT = clone $policyInDT;
        }
    } else {
        $errors[] = 'Invalid OT type.';
    }

    if (empty($errors) && $actualInDT && $actualOutDT) {
        // Handle midnight crossing
        if ($actualOutDT <= $actualInDT) $actualOutDT->modify('+1 day');
        $earlyDiff = max(0, ($policyInDT->getTimestamp() - $actualInDT->getTimestamp()) / 3600);
        $lateDiff  = max(0, ($actualOutDT->getTimestamp() - $policyOutDT->getTimestamp()) / 3600);
        $ot_hours = round($earlyDiff + $lateDiff);
        if ($ot_hours <= 0) $errors[] = 'Calculated OT hours is 0. No OT to submit.';
    }

    if (empty($errors)) {
        $stmt = $oat->prepare("INSERT INTO ot_reports (student_id, ot_hours, ot_date, ot_reason) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $ot_hours, $ot_date, $ot_reason);
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
            <p class="text-muted">OT includes early arrival (before <?php echo htmlspecialchars(date('g:i A', strtotime($user_policy_time_in))); ?>) or normal overtime (after <?php echo htmlspecialchars(date('g:i A', strtotime($user_policy_time_out))); ?>).</p>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="alert alert-danger"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">OT Type</label><br>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ot_type" id="ot_type_early" value="early" checked>
                        <label class="form-check-label" for="ot_type_early">Early Arrival</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="ot_type" id="ot_type_late" value="late">
                        <label class="form-check-label" for="ot_type_late">Normal Overtime</label>
                    </div>
                </div>
                <div class="mb-3" id="time_in_section">
                    <label class="form-label">Actual Time In (hour)</label>
                    <div class="d-flex gap-2">
                        <input type="number" id="time_in_hour" name="time_in_hour" class="form-control" min="1" max="12" step="1" placeholder="7">
                        <select id="time_in_ampm" name="time_in_ampm" class="form-select" style="width:110px">
                            <option>AM</option><option>PM</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 d-none" id="time_out_section">
                    <label class="form-label">Actual Time Out (hour)</label>
                    <div class="d-flex gap-2">
                        <input type="number" id="time_out_hour" name="time_out_hour" class="form-control" min="1" max="12" step="1" placeholder="6">
                        <select id="time_out_ampm" name="time_out_ampm" class="form-select" style="width:110px">
                            <option>PM</option><option>AM</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Calculated OT Hours</label>
                    <input type="number" id="ot_hours" name="ot_hours" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">OT Date</label>
                    <input type="date" name="ot_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="ot_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Proof (optional)</label>
                    <input type="file" name="ot_proof" class="form-control" accept="image/*,.pdf">
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
const policyInHour = <?php echo (int)date('g', strtotime($user_policy_time_in)); ?>;
const policyInAmpm = '<?php echo date('A', strtotime($user_policy_time_in)); ?>';
const policyOutHour = <?php echo (int)date('g', strtotime($user_policy_time_out)); ?>;
const policyOutAmpm = '<?php echo date('A', strtotime($user_policy_time_out)); ?>';

function to24(h, ampm){
    h = parseInt(h,10);
    if (isNaN(h)) return NaN;
    if (ampm==='PM' && h!==12) h+=12;
    if (ampm==='AM' && h===12) h=0;
    return h;
}

function toggleSections(){
    const type = document.querySelector('input[name="ot_type"]:checked').value;
    const inSec = document.getElementById('time_in_section');
    const outSec = document.getElementById('time_out_section');
    if (type==='early') {
        inSec.classList.remove('d-none');
        outSec.classList.add('d-none');
        // autofill out with policy out
        document.getElementById('time_out_hour').value = policyOutHour;
        document.getElementById('time_out_ampm').value = policyOutAmpm;
    } else {
        inSec.classList.add('d-none');
        outSec.classList.remove('d-none');
        // autofill in with policy in
        document.getElementById('time_in_hour').value = policyInHour;
        document.getElementById('time_in_ampm').value = policyInAmpm;
    }
    calculateOT();
}

function calculateOT(){
    const typeEl = document.querySelector('input[name="ot_type"]:checked');
    if (!typeEl) return;
    const type = typeEl.value;
    let total = 0;
    if (type==='early') {
        const ih = document.getElementById('time_in_hour').value;
        const ia = document.getElementById('time_in_ampm').value;
        if (!ih) { document.getElementById('ot_hours').value = ''; return; }
        const actualIn = to24(ih, ia);
        total = Math.max(0, policyInHour - actualIn);
    } else {
        const oh = document.getElementById('time_out_hour').value;
        const oa = document.getElementById('time_out_ampm').value;
        if (!oh) { document.getElementById('ot_hours').value = ''; return; }
        const actualOut = to24(oh, oa);
        total = Math.max(0, actualOut - policyOutHour);
    }
    document.getElementById('ot_hours').value = Math.round(total);
}

document.querySelectorAll('input[name="ot_type"]').forEach(r => r.addEventListener('change', toggleSections));
['time_in_hour','time_in_ampm','time_out_hour','time_out_ampm'].forEach(id=>{
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', calculateOT);
});
document.addEventListener('DOMContentLoaded', ()=> {
    // initialize fields & UI
    toggleSections();
});
</script>
</body>
</html>