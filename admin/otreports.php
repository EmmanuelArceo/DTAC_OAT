<?php


if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once 'nav.php';
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'adviser'])) {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = '';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    if ($action === 'approve_requested') {
        $stmt = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) $success = 'Report approved with requested OT hours.';
        else $errors[] = 'Failed to approve report.';
    } elseif ($action === 'approve_actual') {
        // Fetch actual OT
        $stmt = $oat->prepare("SELECT otr.ot_date, u.id FROM ot_reports otr JOIN users u ON otr.student_id = u.id WHERE otr.id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if ($rep) {
            $date = $rep['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') {
                    $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                }
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }
            $stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy FROM ojt_records WHERE user_id = ? AND DATE(time_in) = ?");
            $stmt->bind_param("is", $rep['id'], $date);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();
            if ($record) {
                $policyIn = new DateTime($date . ' ' . $record['time_in_policy']);
                $policyOut = new DateTime($date . ' ' . $record['time_out_policy']);
                $actualIn = isValidDbTime($record['time_in']) ? new DateTime($record['time_in']) : null;
                $actualOut = isValidDbTime($record['time_out']) ? new DateTime($record['time_out']) : null;
                if (!$actualIn || !$actualOut) {
                    $errors[] = 'Cannot calculate actual OT — missing actual time-in or time-out.';
                } else {
                    $early = max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600);
                    $late = max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600);
                    $actual_ot = round($early + $late);
                    $stmt = $oat->prepare("UPDATE ot_reports SET ot_hours = ?, approved = 1 WHERE id = ?");
                    $stmt->bind_param("di", $actual_ot, $report_id);
                    if ($stmt->execute()) $success = 'Report approved with actual OT hours (' . $actual_ot . ').';
                    else $errors[] = 'Failed to approve report.';
                }
            } else {
                $errors[] = 'No actual record found.';
            }
        }
    } elseif ($action === 'reject') {
        $stmt = $oat->prepare("UPDATE ot_reports SET approved = 0 WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        if ($stmt->execute()) $success = 'Report rejected.';
        else $errors[] = 'Failed to reject report.';
    }
}

// Fetch OT reports
$stmt = $oat->prepare("SELECT otr.id, u.id as user_id, u.fname, u.lname, otr.ot_hours, otr.ot_type, otr.reported_time_in, otr.reported_time_out, otr.ot_date, otr.ot_reason, otr.approved FROM ot_reports otr JOIN users u ON otr.student_id = u.id ORDER BY otr.ot_date DESC, otr.id DESC");
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// helper: treat zero/empty timestamps as "no time"
function isValidDbTime($s) {
    if (!$s) return false;
    $s = trim($s);
    return !in_array($s, ['00:00:00', '0000-00-00 00:00:00', '1970-01-01 00:00:00'], true);
}

function getVerificationStatus($oat, $report) {
    // normalize report date
    $date = $report['ot_date'];
    if (strpos($date, '/') !== false) {
        $dt = DateTime::createFromFormat('d/m/y', $date);
        if ($dt && $dt->format('Y') == '2012') {
            $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
        }
        $date = $dt ? $dt->format('Y-m-d') : $date;
    }
    $stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy FROM ojt_records WHERE user_id = ? AND DATE(time_in) = ?");
    $stmt->bind_param("is", $report['user_id'], $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    if (!$record) {
        return [
            'status' => '<span class="badge bg-secondary">No record</span>',
            'actual_in' => null,
            'actual_out' => null,
            'explanation' => 'No attendance record found for this date.'
        ];
    }
    // helper to treat zero/empty timestamps as "no time"
    $isValidDbTime = function($s) {
        if (!$s) return false;
        $s = trim($s);
        return !in_array($s, ['00:00:00', '0000-00-00 00:00:00', '1970-01-01 00:00:00'], true);
    };
    $fmt = function($s) { return $s ? date('g:i A', strtotime($s)) : null; };
    $policyIn = new DateTime($date . ' ' . $record['time_in_policy']);
    $policyOut = new DateTime($date . ' ' . $record['time_out_policy']);
    $actualIn = $isValidDbTime($record['time_in']) ? new DateTime($record['time_in']) : null;
    $actualOut = $isValidDbTime($record['time_out']) ? new DateTime($record['time_out']) : null;
    $issues = [];
    // verify reported early arrival
    if (!empty($report['reported_time_in'])) {
        $reportedIn = new DateTime($date . ' ' . $report['reported_time_in']);
        if ($reportedIn < $policyIn) {
            if (!$actualIn) {
                $issues[] = 'No actual time-in recorded to verify reported early arrival.';
            } elseif ($actualIn > $reportedIn) {
                $issues[] = 'Reported early arrival at ' . date('g:i A', strtotime($report['reported_time_in'])) . ', but actual time in was ' . $fmt($record['time_in']) . '.';
            }
        }
    }
    // verify reported late departure
    if (!empty($report['reported_time_out'])) {
        $reportedOut = new DateTime($date . ' ' . $report['reported_time_out']);
        if ($reportedOut > $policyOut) {
            if (!$actualOut) {
                $issues[] = 'No actual time-out recorded to verify reported late departure.';
            } elseif ($actualOut < $reportedOut) {
                $issues[] = 'Reported late departure at ' . date('g:i A', strtotime($report['reported_time_out'])) . ', but actual time out was ' . $fmt($record['time_out']) . '.';
            }
        }
    }
    // fallback: compare calculated OT when no reported times provided
    if (empty($report['reported_time_in']) && empty($report['reported_time_out'])) {
        if ($actualIn && $actualOut) {
            $early = max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600);
            $late = max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600);
            $actual_ot = round($early + $late);
            if ($actual_ot != $report['ot_hours']) {
                $issues[] = 'Reported OT hours (' . $report['ot_hours'] . ') do not match calculated actual OT hours (' . $actual_ot . ').';
            }
        } else {
            $issues[] = 'Insufficient attendance data to verify OT hours.';
        }
    }
    if (empty($issues)) {
        $status = '<span class="badge bg-success">Verified</span>';
        $explanation = 'Actual attendance times match or exceed the reported OT requirements.';
    } else {
        $status = '<span class="badge bg-danger">Mismatch</span>';
        $explanation = implode(' ', $issues);
    }
    return [
        'status' => $status,
        'actual_in' => $isValidDbTime($record['time_in']) ? $fmt($record['time_in']) : null,
        'actual_out' => $isValidDbTime($record['time_out']) ? $fmt($record['time_out']) : null,
        'explanation' => $explanation
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>OT Reports Review</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Keep look consistent with other pages: centered card, clean table */
.page-wrap { max-width: 1100px; margin: 1.5rem auto; }
.header-row { gap: .75rem; }
.table-sm td, .table-sm th { vertical-align: middle; }
.ot-type-badge { min-width: 96px; display:inline-block; text-align:center; }
.actions .btn { margin-right: .25rem; }
.card-search { max-width: 420px; }
.small-muted { color: #6c757d; font-size: .9rem; }
</style>
</head>
<body>
<main class="page-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3 header-row">
        <div>
            <h2 class="m-0 text-primary">OT Reports</h2>
            <div class="small-muted">Review and verify submitted OT reports</div>
        </div>
        <div class="d-flex align-items-center">
            <div class="me-2 card-search">
                <input id="searchBox" class="form-control form-control-sm" placeholder="Search student, reason, date..." />
            </div>
            <div class="me-2">
                <select id="filterType" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <option value="early">Early OT</option>
                    <option value="late">Normal OT</option>
                </select>
            </div>
            <div>
                <select id="filterStatus" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger mb-3"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($reports)): ?>
                <div class="p-4 text-center small-muted">No OT reports found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="reportsTable" class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:240px">Student</th>
                                <th style="width:110px">Date</th>
                                <th style="width:120px">Type</th>
                                <th style="width:90px">Hours</th>
                                <th>Reason</th>
                                <th style="width:160px">Verification</th>
                                <th style="width:120px">Status</th>
                                <th style="width:120px" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reports as $r):
                            $verification = getVerificationStatus($oat, $r);
                            $typeLabel = ($r['ot_type'] === 'early') ? 'Early OT' : (($r['ot_type'] === 'late') ? 'Normal OT' : ucfirst($r['ot_type']));
                            $statusKey = ($r['approved'] == 1) ? 'approved' : ((is_null($r['approved']) || $r['approved'] === '') ? 'pending' : 'rejected');
                            // we intentionally do not display 'rejected' badge on the row/card per request
                        ?>
                            <tr data-name="<?= htmlspecialchars(strtolower($r['fname'].' '.$r['lname'])) ?>"
                                data-reason="<?= htmlspecialchars(strtolower($r['ot_reason'])) ?>"
                                data-date="<?= htmlspecialchars($r['ot_date']) ?>"
                                data-type="<?= htmlspecialchars($r['ot_type']) ?>"
                                data-status="<?= $statusKey ?>">
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></div>
                                    <div class="small-muted"><?= htmlspecialchars($r['user_id']) ?></div>
                                </td>
                                <td><?= htmlspecialchars($r['ot_date']) ?></td>
                                <td><span class="badge bg-info text-dark ot-type-badge"><?= htmlspecialchars($typeLabel) ?></span></td>
                                <td><?= htmlspecialchars($r['ot_hours']) ?></td>
                                <td class="small-muted"><?= htmlspecialchars(mb_strimwidth($r['ot_reason'], 0, 60, '...')) ?></td>
                                <td>
                                    <?= $verification['status'] ?>
                                    <div class="small-muted mt-1">In: <?= htmlspecialchars($verification['actual_in'] ?? '—') ?> &nbsp; Out: <?= htmlspecialchars($verification['actual_out'] ?? '—') ?></div>
                                </td>
                                <td>
                                    <?php if ($r['approved'] == 1): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif (is_null($r['approved']) || $r['approved'] === ''): ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php else: ?>
                                        <span class="small-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end actions">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-<?= $r['id'] ?>">Details</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals -->
    <?php foreach ($reports as $r):
        $verification = getVerificationStatus($oat, $r);
        $typeLabel = ($r['ot_type'] === 'early') ? 'Early OT' : (($r['ot_type'] === 'late') ? 'Normal OT' : ucfirst($r['ot_type']));
    ?>
    <div class="modal fade" id="modal-<?= $r['id'] ?>" tabindex="-1" aria-labelledby="modalLabel-<?= $r['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">OT Report — <?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4">OT Type</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($typeLabel) ?></dd>

                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($r['ot_date']) ?></dd>

                        <dt class="col-sm-4">Hours</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($r['ot_hours']) ?></dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($r['ot_reason'])) ?></dd>

                        <?php if (!empty($r['reported_time_in']) && !in_array($r['reported_time_in'], ['00:00:00','0000-00-00 00:00:00'], true)): ?>
                            <dt class="col-sm-4">Reported In</dt>
                            <dd class="col-sm-8"><?= date('g:i A', strtotime($r['reported_time_in'])) ?></dd>
                        <?php endif; ?>
                        <?php if (!empty($r['reported_time_out']) && !in_array($r['reported_time_out'], ['00:00:00','0000-00-00 00:00:00'], true)): ?>
                            <dt class="col-sm-4">Reported Out</dt>
                            <dd class="col-sm-8"><?= date('g:i A', strtotime($r['reported_time_out'])) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Verification</dt>
                        <dd class="col-sm-8"><?= $verification['status'] ?></dd>

                        <dt class="col-sm-4">Explanation</dt>
                        <dd class="col-sm-8 small-muted"><?= htmlspecialchars($verification['explanation']) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <form method="POST" class="d-flex gap-2 w-100">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <div class="ms-auto">
                            <button type="submit" name="action" value="approve_requested" class="btn btn-success btn-sm" <?= $r['approved'] == 1 ? 'disabled' : '' ?>>Approve Requested</button>
                            <button type="submit" name="action" value="approve_actual" class="btn btn-warning btn-sm" <?= $r['approved'] == 1 ? 'disabled' : '' ?>>Approve Actual</button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm" <?= $r['approved'] == 0 ? 'disabled' : '' ?>>Reject</button>
                        </div>
                    </form>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// simple client-side filtering to match other pages UX
(function(){
    const table = document.getElementById('reportsTable');
    if (!table) return;
    const rows = Array.from(table.tBodies[0].rows);
    const searchBox = document.getElementById('searchBox');
    const filterType = document.getElementById('filterType');
    const filterStatus = document.getElementById('filterStatus');

    function applyFilters(){
        const q = (searchBox.value || '').trim().toLowerCase();
        const type = filterType.value;
        const status = filterStatus.value;
        rows.forEach(r=>{
            const name = r.dataset.name || '';
            const reason = r.dataset.reason || '';
            const date = r.dataset.date || '';
            const rowType = r.dataset.type || '';
            const rowStatus = r.dataset.status || '';
            const matchesQuery = !q || name.includes(q) || reason.includes(q) || date.includes(q);
            const matchesType = !type || rowType === type;
            const matchesStatus = !status || rowStatus === status;
            r.style.display = (matchesQuery && matchesType && matchesStatus) ? '' : 'none';
        });
    }

    [searchBox, filterType, filterStatus].forEach(el=>el && el.addEventListener('input', applyFilters));
})();
</script>
</body>
</html>
