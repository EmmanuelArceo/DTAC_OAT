<?php

include '../db.php';
include 'nav.php';

// Redirect to login if not admin, super_admin, or adviser
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'adviser'])) {
    header("Location: ../login.php");
    exit;
}

// Function to calculate actual OT hours
function calculateActualOtHours($oat, $student_id, $ot_date) {
    $stmt = $oat->prepare("SELECT default_time_out FROM site_settings LIMIT 1");
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc();
    $default_time_out = $settings['default_time_out'] ?? '17:00:00';
    $user_policy_time_out = $default_time_out;
    $stmt = $oat->prepare("SELECT tg.time_out FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    if ($group) {
        $user_policy_time_out = $group['time_out'];
    }
    $stmt = $oat->prepare("SELECT time_out FROM ojt_records WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $student_id, $ot_date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $actual_ot_hours = 0;
    $actual_time_out_display = 'N/A';
    if ($record && $record['time_out'] && $record['time_out'] !== '00:00:00') {
        $actual_time_out = strtotime($record['time_out']);
        $policy_time_out_ts = strtotime($user_policy_time_out);
        $actual_ot_hours = max(0, ($actual_time_out - $policy_time_out_ts) / 3600);
        $actual_time_out_display = date('g:i A', $actual_time_out);
    }
    return [$actual_ot_hours, $actual_time_out_display];
}

// Handle OT approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $approve_id = intval($_POST['approve_id']);
    $approval_type = $_POST['approval_type'] ?? 'deny';
    $stmt = $oat->prepare("SELECT * FROM ot_reports WHERE id = ?");
    $stmt->bind_param("i", $approve_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    if ($report) {
        list($actual_ot_hours, $actual_time_out_display) = calculateActualOtHours($oat, $report['student_id'], $report['ot_date']);
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
                $approved_hours = $report['ot_hours'];
                $stmt = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
                $stmt->bind_param("i", $approve_id);
                $stmt->execute();
                $_SESSION['message'] = "OT report approved successfully for " . $approved_hours . " hours.";
            } else {
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
                    $stmt = $oat->prepare("UPDATE ot_reports SET approved = 0 WHERE id = ?");
                    $stmt->bind_param("i", $approve_id);
                    $stmt->execute();
                    $_SESSION['message'] = "OT report denied.";
                }
            }
        } else {
            $stmt = $oat->prepare("UPDATE ot_reports SET approved = 0 WHERE id = ?");
            $stmt->bind_param("i", $approve_id);
            $stmt->execute();
            $_SESSION['message'] = "OT report denied. No valid time out record found for the OT date.";
        }
        if ($approved_hours > 0) {
            $stmt = $oat->prepare("UPDATE ojt_records SET ot_hours = ? WHERE user_id = ? AND date = ?");
            $stmt->bind_param("dis", $approved_hours, $report['student_id'], $report['ot_date']);
            $stmt->execute();
        }
    }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary: #4c8eb1;
            --primary-dark: #3cb2cc;
            --glass-bg: rgba(255,255,255,0.85);
            --glass-border: rgba(76,142,177,0.13);
            --muted: #6b7280;
        }
        body{
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            margin:0;
            min-height:100vh;
            /* Lighter glassy, light blue background */
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            color:#0f172a;
            -webkit-font-smoothing:antialiased;
        }
        .wrap{
            max-width:1100px;
            margin:48px auto;
            padding:24px;
        }
        .glass{
            background: rgba(60, 178, 204, 0.09); /* lighter glassy effect */
            border: 1px solid rgba(76,142,177,0.13);
            box-shadow: 0 8px 30px rgba(60,178,204,0.08);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius:18px;
            padding:32px 24px;
        }
        .title{
            font-size:2rem;
            font-weight:800;
            color:var(--primary);
            margin-bottom:8px;
            letter-spacing: 1px;
        }
        .subtitle{
            font-size:15px;
            color:var(--muted);
            margin-bottom:18px;
        }
        .table thead th{
            color:var(--primary-dark);
            font-size:13px;
            font-weight:700;
            background:transparent;
            border-bottom:2px solid var(--primary);
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
        .badge-admin{ background:var(--primary); color:#fff;}
        .badge-adviser{ background:#fbbf24; color:#fff;}
        .badge-super_admin{ background:#6366f1; color:#fff;}
        .btn-approve {
            background: var(--primary);
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.4rem 1.1rem;
            transition: background 0.2s;
        }
        .btn-approve:hover {
            background: var(--primary-dark);
        }
        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
        }
        .btn-outline-secondary:hover {
            background: var(--primary);
            color: #fff;
        }
        .modal-header {
            background: var(--primary);
            color: #fff;
        }
        .modal-title {
            font-weight: 700;
        }
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
            <div class="title"><i class="bi bi-clock-history me-2"></i>OT Reports</div>
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
                                list($actual_ot_hours, $actual_time_out_display) = calculateActualOtHours($oat, $row['student_id'], $row['ot_date']);
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
                                            <span class="text-muted">No proof</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (empty($row['approved'])): ?>
                                            <button 
                                                class="btn btn-approve btn-sm"
                                                onclick="openApproveModal(
                                                    <?= $row['id'] ?>, 
                                                    '<?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?>', 
                                                    '<?= htmlspecialchars($row['ot_date']) ?>', 
                                                    '<?= htmlspecialchars($row['ot_hours']) ?>', 
                                                    '<?= htmlspecialchars($row['ot_reason']) ?>',
                                                    '<?= htmlspecialchars($actual_ot_hours_display) ?>',
                                                    '<?= htmlspecialchars($actual_time_out_display) ?>',
                                                    <?= $actual_ot_hours ?>
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
            <div class="modal-header">
              <h5 class="modal-title" id="approveModalLabel"><i class="bi bi-check-circle me-2"></i>Approve OT Report</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="approve_id" id="approve_id_modal">
            <div class="modal-body" id="modal_body_content"></div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-approve">Approve</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
function openApproveModal(id, name, date, hours, reason, actual_ot_hours_display, actual_time_out_display, actual_ot_hours_num) {
    var modal = new bootstrap.Modal(document.getElementById('approveModal'));
    document.getElementById('approve_id_modal').value = id;
    var content = `<div class="mb-2"><strong>${name}</strong></div>
                   <div class="mb-2"><i class="bi bi-calendar-event"></i> <strong>Date:</strong> ${date}</div>
                   <div class="mb-2"><i class="bi bi-hourglass-split"></i> <strong>Claimed Hours:</strong> ${hours}</div>
                   <div class="mb-2"><i class="bi bi-chat-left-text"></i> <strong>Reason:</strong> ${reason}</div>`;
    if (actual_ot_hours_num < parseFloat(hours)) {
        content += `
            <div class="alert alert-warning mt-2 mb-2 p-2">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Actual OT is less than claimed.</strong><br>
                <span>Actual time out: <b>${actual_time_out_display}</b>. Actual OT: <b>${actual_ot_hours_display}</b>.</span>
            </div>
            <div class="mb-2">
                <label class="form-check">
                    <input type="radio" class="form-check-input" name="approval_type" value="full" checked>
                    <span class="form-check-label">Approve Full Claimed Hours (${hours} hours)</span>
                </label>
                <label class="form-check">
                    <input type="radio" class="form-check-input" name="approval_type" value="actual">
                    <span class="form-check-label">Approve Only Actual Hours (${actual_ot_hours_display})</span>
                </label>
                <label class="form-check">
                    <input type="radio" class="form-check-input" name="approval_type" value="deny">
                    <span class="form-check-label">Deny</span>
                </label>
            </div>
        `;
    }
    document.getElementById('modal_body_content').innerHTML = content;
    modal.show();
}
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>