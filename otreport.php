<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';
include 'nav.php';

// Redirect to login if not signed in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle OT report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ot_report'])) {
    $student_id = $_SESSION['user_id'];
    $ot_hours = $_POST['ot_hours'];
    $ot_date = $_POST['ot_date'];
    $ot_reason = $_POST['ot_reason'];
    $proof_path = '';

    // Handle file upload
    if (isset($_FILES['ot_proof']) && $_FILES['ot_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/ot_proofs/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['ot_proof']['name']);
        $file_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['ot_proof']['tmp_name'], $file_path)) {
            $proof_path = $file_path;
        }
    }

    // Save OT report to database
    $stmt = $oat->prepare("INSERT INTO ot_reports (student_id, ot_hours, ot_date, ot_reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $student_id, $ot_hours, $ot_date, $ot_reason);
    $stmt->execute();

    echo "<div class='alert alert-success mt-3'>OT report submitted successfully!</div>";
}

// Fetch default time in and out from site_settings
$settings = $oat->query("SELECT default_time_in, default_time_out FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';
$default_time_out = $settings['default_time_out'] ?? '17:00:00';

// Calculate standard working hours
$start = strtotime($default_time_in);
$end = strtotime($default_time_out);
$standard_hours = ($end - $start) / 3600; // hours

// Fetch OT summaries for admin
$ot_summaries = [];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $ot_summaries = $oat->query("
        SELECT u.fname, u.lname, MONTH(otr.ot_date) AS month, YEAR(otr.ot_date) AS year, SUM(otr.ot_hours) AS total_ot
        FROM ot_reports otr
        JOIN users u ON otr.student_id = u.id
        WHERE otr.status = 'approved'
        GROUP BY u.id, YEAR(otr.ot_date), MONTH(otr.ot_date)
        ORDER BY year DESC, month DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit OT Report</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --accent: #3CB3CC;
            --accent-deep: #2aa0b3;
            --glass-bg: rgba(255,255,255,0.55);
            --glass-border: rgba(60,179,204,0.12);
            --muted: #6b7280;
        }
        body{
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            margin:0;
            min-height:100vh;
            background: linear-gradient(135deg, #f6fbfb 0%, #eef9fa 50%, #f9fcfd 100%);
            color:#0f172a;
            -webkit-font-smoothing:antialiased;
        }
        .wrap{
            max-width:600px;
            margin:48px auto;
            padding:24px;
        }
        .glass{
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 30px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius:14px;
            padding:32px 24px;
            margin-bottom:24px;
        }
        .title{
            font-size:22px;
            font-weight:800;
            color:var(--accent-deep);
            margin-bottom:8px;
        }
        .subtitle{
            font-size:14px;
            color:var(--muted);
            margin-bottom:18px;
        }
        .form-label{
            font-weight:600;
            color:var(--accent-deep);
        }
        .btn-accent{
            background:transparent;
            border:1px solid var(--accent);
            color:var(--accent-deep);
            padding:10px 14px;
            border-radius:10px;
            font-weight:700;
            transition:all .15s ease;
            width:100%;
        }
        .btn-accent:hover{
            background:var(--accent);
            color:#fff;
            transform:translateY(-3px);
            box-shadow:0 8px 20px rgba(60,179,204,0.12);
            border-color:transparent;
        }
        .time-inputs{
            display:flex;
            gap:10px;
        }
        .time-inputs input{
            flex:1;
        }
        @media (max-width: 600px){
            .wrap{ max-width:100%; margin:0; padding:8px;}
            .glass{ padding:18px 8px;}
            .time-inputs{ flex-direction:column;}
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="glass">
            <div class="title">Submit OT Report</div>
            <div class="subtitle">Send your overtime report. OT starts after default time out (<?php echo htmlspecialchars($default_time_out); ?>).</div>
            <form method="POST" action="otreport.php" enctype="multipart/form-data" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Time Inputs (for auto-calculation, 12-hour format)</label>
                    <div class="time-inputs">
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label style="font-size: 12px; color: var(--muted);">OT Start</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="number" id="start_hour" class="form-control" value="5" min="1" max="12" step="1" readonly>
                                <span class="form-control" style="width: 80px; background: #e9ecef; text-align: center;">PM</span>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 5px;">
                            <label style="font-size: 12px; color: var(--muted);">OT End</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="number" id="end_hour" class="form-control" min="1" max="12" step="1">
                                <select id="end_ampm" class="form-control" style="width: 80px;">
                                    <option value="PM">PM</option>
                                    <option value="AM">AM</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="ot_hours" class="form-label">OT Hours (auto-calculated, full hours only)</label>
                    <input type="number" name="ot_hours" id="ot_hours" class="form-control" required min="0" step="1" readonly>
                </div>
                <div class="mb-3">
                    <label for="ot_date" class="form-label">OT Date</label>
                    <input type="date" name="ot_date" id="ot_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="ot_reason" class="form-label">Reason for OT</label>
                    <textarea name="ot_reason" id="ot_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="ot_proof" class="form-label">Upload Proof (optional)</label>
                    <input type="file" name="ot_proof" id="ot_proof" class="form-control" accept="image/*,.pdf">
                </div>
                <button type="submit" name="submit_ot_report" class="btn-accent mt-2">Submit OT Report</button>
            </form>
        </div>

        <?php if (!empty($ot_summaries)): ?>
        <div class="glass">
            <div class="title">OT Summaries (Admin)</div>
            <div class="subtitle">Total OT hours per student per month.</div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Month/Year</th>
                        <th>Total OT Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ot_summaries as $summary): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($summary['fname'] . ' ' . $summary['lname']); ?></td>
                        <td><?php echo htmlspecialchars($summary['month'] . '/' . $summary['year']); ?></td>
                        <td><?php echo htmlspecialchars($summary['total_ot']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-calculate OT hours: hours worked after default time out
        const defaultTimeOutHour = 17; // 5 PM
        function convertTo24(hour, ampm) {
            hour = parseInt(hour);
            if (ampm === 'PM' && hour !== 12) hour += 12;
            if (ampm === 'AM' && hour === 12) hour = 0;
            return hour;
        }
        function calculateOT() {
            const startHour = document.getElementById('start_hour').value;
            const startAmpm = 'PM'; // fixed
            const endHour = document.getElementById('end_hour').value;
            const endAmpm = document.getElementById('end_ampm').value;
            if (startHour && endHour) {
                const start24 = convertTo24(startHour, startAmpm);
                const end24 = convertTo24(endHour, endAmpm);
                // Validate end time is not below default time out
                if (end24 < defaultTimeOutHour) {
                    alert('End time cannot be before default time out (5 PM).');
                    document.getElementById('end_hour').value = '';
                    return;
                }
                // OT is hours after default time out
                const otStart = start24 > defaultTimeOutHour ? start24 : defaultTimeOutHour;
                const otHours = Math.max(0, end24 - otStart).toFixed(1);
                document.getElementById('ot_hours').value = otHours;
            }
        }
        document.getElementById('start_hour').addEventListener('input', calculateOT);
        document.getElementById('end_hour').addEventListener('input', calculateOT);
        document.getElementById('end_ampm').addEventListener('change', calculateOT);
        calculateOT(); // initial calculation
    </script>
</body>
</html>