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
    $oat->query("UPDATE ot_reports SET approved = 1 WHERE id = $approve_id");
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
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date Submitted</th>
                            <th>Student</th>
                            <th>OT Date</th>
                            <th>Hours</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports && $reports->num_rows > 0): ?>
                            <?php while($row = $reports->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['submitted_at']))) ?></td>
                                    <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                                    <td><?= htmlspecialchars($row['ot_date']) ?></td>
                                    <td><?= htmlspecialchars($row['ot_hours']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($row['ot_reason'])) ?></td>
                                    <td>
                                        <?php if (empty($row['approved'])): ?>
                                            <button 
                                                class="btn btn-success btn-sm"
                                                onclick="openApproveModal(
                                                    <?= $row['id'] ?>, 
                                                    '<?= htmlspecialchars(addslashes($row['fname'] . ' ' . $row['lname'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_date'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_hours'])) ?>', 
                                                    '<?= htmlspecialchars(addslashes($row['ot_reason'])) ?>'
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
                                <td colspan="6" class="text-center text-muted">No OT reports found.</td>
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
function openApproveModal(id, name, date, hours, reason) {
    var modal = new bootstrap.Modal(document.getElementById('approveModal'));
    document.getElementById('approve_id_modal').value = id;
    document.getElementById('modal_body_content').innerHTML = 
        `<p>Approve OT for <strong>${name}</strong>?</p>
         <p>Date: <strong>${date}</strong></p>
         <p>Hours: <strong>${hours}</strong></p>
         <p>Reason: <strong>${reason}</strong></p>`;
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