<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../db.php';
include 'nav.php';

// Redirect to login if not admin, super_admin, or adviser
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'adviser'])) {
    header("Location: ../login.php");
    exit;
}

// Handle OT approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $approve_id = intval($_POST['approve_id']);
    $approval_type = $_POST['approval_type'] ?? 'deny';
    
    // Fetch OT report details using prepared statement
    $stmt = $oat->prepare("SELECT * FROM ot_reports WHERE id = ?");
    $stmt->bind_param("i", $approve_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    
    if ($report) {
        // Fetch user's policy time out (check time group or default)
        $stmt = $oat->prepare("SELECT default_time_out FROM site_settings LIMIT 1");
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_assoc();
        $default_time_out = $settings['default_time_out'] ?? '17:00:00';
        $user_policy_time_out = $default_time_out;
        $stmt = $oat->prepare("SELECT tg.time_out FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
        $stmt->bind_param("i", $report['student_id']);
        $stmt->execute();
        $group = $stmt->get_result()->fetch_assoc();
        if ($group) {
            $user_policy_time_out = $group['time_out'];
        }
        
        // Fetch actual time out from ojt_records
        $stmt = $oat->prepare("SELECT time_out FROM ojt_records WHERE user_id = ? AND date = ?");
        $stmt->bind_param("is", $report['student_id'], $report['ot_date']);
        $stmt->execute();
        $record = $stmt->get_result()->fetch_assoc();
        
        $approved_hours = 0;
        if ($record && $record['time_out'] && $record['time_out'] !== '00:00:00') {
            $actual_time_out = strtotime($record['time_out']);
            $policy_time_out_ts = strtotime($user_policy_time_out);
            $actual_ot_hours = max(0, ($actual_time_out - $policy_time_out_ts) / 3600);
            
            if ($actual_ot_hours >= $report['ot_hours']) {
                // Completed full claimed hours
                $approved_hours = $report['ot_hours'];
                $stmt = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
                $stmt->bind_param("i", $approve_id);
                $stmt->execute();
                $_SESSION['message'] = "OT report approved successfully for " . $approved_hours . " hours.";
            } else {
                // Not completed
                if ($approval_type === 'full') {
                    $approved_hours = $report['ot_hours'];
                    $stmt = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
                    $stmt->bind_param("i", $approve_id);
                    $stmt->execute();
                    $_SESSION['message'] = "OT report approved for full claimed hours (" . $approved_hours . " hours) despite incomplete time.";
                } elseif ($approval_type === 'actual') {
                    $approved_hours = round($actual_ot_hours, 2);
                    $stmt = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
                    $stmt->bind_param("i", $approve_id);
                    $stmt->execute();
                    $_SESSION['message'] = "OT report approved for actual hours (" . $approved_hours . " hours).";
                } else {
                    // Deny: Store denial in database
                    $stmt = $oat->prepare("UPDATE ot_reports SET approved = 0, denied_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $approve_id);
                    $stmt->execute();
                    $_SESSION['message'] = "OT report denied.";
                }
            }
        } else {
            // Deny if no valid record
            $stmt = $oat->prepare("UPDATE ot_reports SET approved = 0, denied_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $approve_id);
            $stmt->execute();
            $_SESSION['message'] = "OT report denied. No valid time out record found for the OT date.";
        }
        
        // Store approved OT hours in ojt_records if approved
        if ($approved_hours > 0) {
            $stmt = $oat->prepare("UPDATE ojt_records SET ot_hours = ? WHERE user_id = ? AND date = ?");
            $stmt->bind_param("dis", $approved_hours, $report['student_id'], $report['ot_date']);
            $stmt->execute();
        }
    }
    
    header("Location: otreports.php");
    exit;
}

// Fetch OT reports
$reports = $oat->query("
    SELECT ot.*, u.fname, u.lname 
    FROM ot_reports ot
    LEFT JOIN users u ON ot.student_id = u.id
    ORDER BY ot.submitted_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OT Reports</title>
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
            max-width:900px;
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
        .table thead th{
            color:var(--muted);
            font-size:13px;
            font-weight:700;
            background:transparent;
            border-bottom:2px solid var(--accent);
        }
        .table td{
            font-size:14px;
            vertical-align:middle;
        }
        .badge{
            font-size:12px;
            padding:4px 10px;
            border-radius:8px;
        }
        .badge-admin{ background:var(--accent); color:#fff;}
        .badge-adviser{ background:#fbbf24; color:#fff;}
        .badge-super_admin{ background:#6366f1; color:#fff;}
        @media (max-width: 900px){
            .wrap{ max-width:100%; margin:0; padding:8px;}
            .glass{ padding:18px 8px;}
            .table-responsive{ font-size:13px;}
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="glass">
            <div class="title">OT Reports</div>
            <div class="subtitle">All submitted overtime reports from OJT students.</div>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-info mt-3"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Student</th>
                            <th>OT Date</th>
                            <th>Hours</th>
                            <th>Reason</th>
                            <th>Proof</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports && $reports->num_rows > 0): ?>
                            <?php while($row = $reports->fetch_assoc()): 
                                // Calculate actual OT hours for this report
                                $actual_ot_hours = 0;
                                $actual_time_out_display = 'N/A';
                                // Fetch user's policy time out
                                $stmt = $oat->prepare("SELECT default_time_out FROM site_settings LIMIT 1");
                                $stmt->execute();
                                $settings = $stmt->get_result()->fetch_assoc();
                                $default_time_out = $settings['default_time_out'] ?? '17:00:00';
                                $user_policy_time_out = $default_time_out;
                                $stmt = $oat->prepare("SELECT tg.time_out FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
                                $stmt->bind_param("i", $row['student_id']);
                                $stmt->execute();
                                $group = $stmt->get_result()->fetch_assoc();
                                if ($group) {
                                    $user_policy_time_out = $group['time_out'];
                                }
                                // Fetch actual time out
                                $stmt = $oat->prepare("SELECT time_out FROM ojt_records WHERE user_id = ? AND date = ?");
                                $stmt->bind_param("is", $row['student_id'], $row['ot_date']);
                                $stmt->execute();
                                $record = $stmt->get_result()->fetch_assoc();
                                if ($record && $record['time_out'] && $record['time_out'] !== '00:00:00') {
                                    $actual_time_out = strtotime($record['time_out']);
                                    $policy_time_out_ts = strtotime($user_policy_time_out);
                                    $actual_ot_hours = max(0, ($actual_time_out - $policy_time_out_ts) / 3600);
                                    $actual_time_out_display = date('g:i A', $actual_time_out);
                                }
                                $hours_part = floor($actual_ot_hours);
                                $minutes_part = round(($actual_ot_hours - $hours_part) * 60);
                                $actual_ot_hours_display = $hours_part . ' hours ' . $minutes_part . ' minutes';
                            ?>

                                <tr>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['submitted_at']))) ?></td>
                                    <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                                    <td><?= htmlspecialchars($row['ot_date']) ?></td>
                                    <td><?= htmlspecialchars($row['ot_hours']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($row['ot_reason'])) ?></td>
                                    <td>
                                        <?php if (!empty($row['proof_path'])): ?>
                                            <a href="<?= htmlspecialchars($row['proof_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Proof</a>
                                        <?php else: ?>
                                            No proof
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($row['approved'])): ?>
                                            <button 
                                                class="btn btn-success btn-sm"
                                                onclick="openApproveModal(
                                                    <?= $row['id'] ?>, 
                                                    '<?= htmlspecialchars(addslashes($row['fname'] . ' ' . $row['lname'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_date'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_hours'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_reason'])) ?>',
                                                    '<?= htmlspecialchars(addslashes($actual_ot_hours_display)) ?>',
                                                    '<?= htmlspecialchars(addslashes($actual_time_out_display)) ?>'
                                                )"
                                            >
                                                Approve
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No OT reports found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <!-- Bootstrap Modal for approving OT report -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="approveForm" method="post">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title" id="approveModalLabel">Approve OT Report</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="approve_id" id="approve_id_modal">
            <div class="modal-body" id="modal_body_content"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Approve</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
function openApproveModal(id, name, date, hours, reason, actual_ot_hours_display, actual_time_out_display) {
    var modal = new bootstrap.Modal(document.getElementById('approveModal'));
    document.getElementById('approve_id_modal').value = id;
    
    var content = `<p>Approve OT for <strong>${name}</strong>?</p>
                   <p>Date: <strong>${date}</strong></p>
                   <p>Hours: <strong>${hours}</strong></p>
                   <p>Reason: <strong>${reason}</strong></p>`;
    
    var actual_ot_hours_num = parseFloat('<?= $actual_ot_hours ?>'); // Assuming passed
    if (actual_ot_hours_num < parseFloat(hours)) {
        content += `
            <p><strong>They didn't actually finish the OT.</strong></p>
            <p>Actual time out: ${actual_time_out_display}. Actual OT: ${actual_ot_hours_display}.</p>
            <div>
                <label><input type="radio" name="approval_type" value="full" checked> Approve Full Claimed Hours (${hours} hours)</label><br>
                <label><input type="radio" name="approval_type" value="actual"> Approve Only Actual Hours (${actual_ot_hours_display})</label><br>
                <label><input type="radio" name="approval_type" value="deny"> Deny</label>
            </div>
        `;
    }
    
    document.getElementById('modal_body_content').innerHTML = content;
    modal.show();
}
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
    .modal-animate {
        opacity: 0;
        transform: translateY(40px) scale(0.98);
        transition: opacity .35s cubic-bezier(.4,0,.2,1), transform .35s cubic-bezier(.4,0,.2,1);
    }
    .modal-animate.show {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    #approveModal {
        background: rgba(0,0,0,0.2);
        pointer-events: auto;
    }
    #approveModal.hidden {
        display: none;
    }
    #approveModal.flex {
        display: flex;
    }
    #modalCard {
        pointer-events: auto;
    }
</style>
</body>
</html>