<?php
// start output buffering so later header('Location: ...') (PRG) still works
if (!ob_get_level()) ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../db.php';
require_once 'nav.php';
if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'adviser'])) {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$success = '';

// restore flash messages (Post/Redirect/Get)
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (isset($_SESSION['flash_errors'])) {
    $errors = $_SESSION['flash_errors'];
    unset($_SESSION['flash_errors']);
}

// Handle approve/reject actions (unchanged)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['report_id'])) {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    if ($action === 'approve_requested') {
        // fetch report so we can update ojt_records safely and avoid double-apply
        $stmt = $oat->prepare("SELECT student_id, ot_hours, ot_date, approved FROM ot_reports WHERE id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if (!$rep) {
            $errors[] = 'Report not found.';
        } elseif ($rep['approved'] == 1) {
            $errors[] = 'Report is already approved.';
        } else {
            $student_id = (int)$rep['student_id'];
            $approved_hours = (float)$rep['ot_hours'];
            $date = $rep['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }

            // transaction: mark report approved and add hours to ojt_records.ot_hours (if record exists)
            $oat->begin_transaction();
            try {
                $u = $oat->prepare("UPDATE ot_reports SET approved = 1 WHERE id = ?");
                $u->bind_param("i", $report_id);
                $u->execute();

                $v = $oat->prepare("UPDATE ojt_records SET ot_hours = COALESCE(ot_hours,0) + ? WHERE user_id = ? AND DATE(time_in) = ?");
                $v->bind_param("dis", $approved_hours, $student_id, $date);
                $v->execute();

                // check if any row was updated in ojt_records; if not, don't treat as error but inform admin
                if ($v->affected_rows === 0) {
                    $success = 'Report approved. Note: no attendance record found for that date — OT hours were not added to ojt_records.';
                } else {
                    $success = 'Report approved and OT hours added to attendance record.';
                }

                $oat->commit();
            } catch (Exception $ex) {
                $oat->rollback();
                $errors[] = 'Failed to approve report and update records.';
            }
        }
    } elseif ($action === 'approve_actual') {
        // include current approved state to prevent re-approval
        $stmt = $oat->prepare("SELECT otr.ot_date, u.id, otr.approved FROM ot_reports otr JOIN users u ON otr.student_id = u.id WHERE otr.id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $rep = $stmt->get_result()->fetch_assoc();
        if (!$rep) {
            $errors[] = 'Report not found.';
        } elseif ($rep['approved'] == 1) {
            $errors[] = 'Report is already approved and cannot be changed.';
        } else {
            $date = $rep['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') {
                    $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                }
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }
            $stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy, ot_hours FROM ojt_records WHERE user_id = ? AND DATE(time_in) = ?");
            $stmt->bind_param("is", $rep['id'], $date);
            $stmt->execute();
            $record = $stmt->get_result()->fetch_assoc();
            if (!$record) {
                return [
                    'status' => '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="No record">No Record</span>',
                    'actual_in' => null,
                    'actual_out' => null,
                    'policy_in' => null,
                    'policy_out' => null,
                    'calculated_ot' => null,
                    'ojt_hours' => null,
                    'ojt_hours_after' => null,
                    'explanation' => 'No attendance record found for this date.'
                ];
            }
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
            if (empty($report['reported_time_in']) && empty($report['reported_time_out'])) {
                if ($actualIn && $actualOut) {
                    $early = max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600);
                    $late = max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600);
                    $actual_ot = round($early + $late);
                    if ($actual_ot != $report['ot_hours']) {
                        $issues[] = 'Reported OT hours (' . $report['ot_hours'] . ') do not match calculated actual OT hours (' . $actual_ot . ').';
                    }
                    $calculated_ot = $actual_ot;
                } else {
                    $issues[] = 'Insufficient attendance data to verify OT hours.';
                    $calculated_ot = null;
                }
            } else {
                // we can still compute calculated OT when actual times exist
                $calculated_ot = ($actualIn && $actualOut)
                    ? round(max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600) + max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600))
                    : null;
            }

            $existing_ojt_hours = isset($record['ot_hours']) ? (float)$record['ot_hours'] : 0.0;
            $ojt_hours_after = is_numeric($calculated_ot) ? $existing_ojt_hours + $calculated_ot : null;

            if (empty($issues)) {
                $status = '<span class="badge bg-success" data-bs-toggle="tooltip" title="Verified">Verified</span>';
                $explanation = 'Actual attendance times match or exceed the reported OT requirements.';
            } else {
                $status = '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Mismatch">Mismatch</span>';
                $explanation = implode(' ', $issues);
            }
            return [
                'status' => $status,
                'actual_in' => $isValidDbTime($record['time_in']) ? $fmt($record['time_in']) : null,
                'actual_out' => $isValidDbTime($record['time_out']) ? $fmt($record['time_out']) : null,
                'policy_in' => $record['time_in_policy'] ? $fmt($record['time_in_policy']) : null,
                'policy_out' => $record['time_out_policy'] ? $fmt($record['time_out_policy']) : null,
                'calculated_ot' => $calculated_ot,
                'ojt_hours' => $existing_ojt_hours,
                'ojt_hours_after' => $ojt_hours_after,
                'explanation' => $explanation
            ];
        }
    } elseif ($action === 'reject') {
        // allow rejecting even if previously approved — if approved, subtract added OT hours from ojt_records
        $chk = $oat->prepare("SELECT student_id, ot_hours, ot_date, approved FROM ot_reports WHERE id = ?");
        $chk->bind_param("i", $report_id);
        $chk->execute();
        $cur = $chk->get_result()->fetch_assoc();
        if (!$cur) {
            $errors[] = 'Report not found.';
        } else {
            $student_id = (int)$cur['student_id'];
            $hours = (float)$cur['ot_hours'];
            $date = $cur['ot_date'];
            if (strpos($date, '/') !== false) {
                $dt = DateTime::createFromFormat('d/m/y', $date);
                if ($dt && $dt->format('Y') == '2012') $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
                $date = $dt ? $dt->format('Y-m-d') : $date;
            }

            $oat->begin_transaction();
            try {
                // if it was previously approved, remove the previously-added OT hours (safe non-negative)
                if ((int)$cur['approved'] === 1 && $hours > 0) {
                    $u = $oat->prepare("UPDATE ojt_records SET ot_hours = GREATEST(0, COALESCE(ot_hours,0) - ?) WHERE user_id = ? AND DATE(time_in) = ?");
                    $u->bind_param("dis", $hours, $student_id, $date);
                    $u->execute();
                }

                $s = $oat->prepare("UPDATE ot_reports SET approved = -1 WHERE id = ?");
                $s->bind_param("i", $report_id);
                $s->execute();

                $oat->commit();
                $success = 'Report rejected.';
            } catch (Exception $ex) {
                $oat->rollback();
                $errors[] = 'Failed to reject report (rollback).';
            }
        }
    }} // <-- CLOSE the main POST handler (was missing / unbalanced)
    
// Prevent form re-submission (Post/Redirect/Get):
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // save messages to session
    $_SESSION['flash_success'] = $success;
    $_SESSION['flash_errors'] = $errors;

    // Remove open_ot from URL for redirect
    $loc = strtok($_SERVER['REQUEST_URI'], '?');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting…</title>'
       . "<script>window.location.href = " . json_encode($loc) . ";</script>"
       . '<noscript><meta http-equiv="refresh" content="0;url=' . $loc . '"></noscript>'
       . '</head><body>If you are not redirected, <a href="' . $loc . '">click here</a>.</body></html>';
    exit;
}

// Fetch OT reports only for OJTs managed by the current admin/adviser using adviser_id field in users
$current_admin_id = $_SESSION['user_id'];
$stmt = $oat->prepare("
    SELECT otr.id, u.id as user_id, u.fname, u.lname, otr.ot_hours, otr.ot_type, otr.reported_time_in, otr.reported_time_out, otr.ot_date, otr.ot_reason, otr.approved
    FROM ot_reports otr
    JOIN users u ON otr.student_id = u.id
    WHERE u.adviser_id = ?
    ORDER BY otr.ot_date DESC, otr.id DESC
");
$stmt->bind_param("i", $current_admin_id);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper functions (unchanged)
function isValidDbTime($s) {
    if (!$s) return false;
    $s = trim($s);
    return !in_array($s, ['00:00:00', '0000-00-00 00:00:00', '1970-01-01 00:00:00'], true);
}

function getVerificationStatus($oat, $report) {
    $date = $report['ot_date'];
    if (strpos($date, '/') !== false) {
        $dt = DateTime::createFromFormat('d/m/y', $date);
        if ($dt && $dt->format('Y') == '2012') {
            $dt->setDate(2026, $dt->format('m'), $dt->format('d'));
        }
        $date = $dt ? $dt->format('Y-m-d') : $date;
    }
    $stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy, ot_hours FROM ojt_records WHERE user_id = ? AND DATE(time_in) = ?");
    $stmt->bind_param("is", $report['user_id'], $date);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    if (!$record) {
        return [
            'status' => '<span class="badge bg-secondary" data-bs-toggle="tooltip" title="No record">No Record</span>',
            'actual_in' => null,
            'actual_out' => null,
            'policy_in' => null,
            'policy_out' => null,
            'calculated_ot' => null,
            'ojt_hours' => null,
            'ojt_hours_after' => null,
            'explanation' => 'No attendance record found for this date.'
        ];
    }
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
    if (empty($report['reported_time_in']) && empty($report['reported_time_out'])) {
        if ($actualIn && $actualOut) {
            $early = max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600);
            $late = max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600);
            $actual_ot = round($early + $late);
            if ($actual_ot != $report['ot_hours']) {
                $issues[] = 'Reported OT hours (' . $report['ot_hours'] . ') do not match calculated actual OT hours (' . $actual_ot . ').';
            }
            $calculated_ot = $actual_ot;
        } else {
            $issues[] = 'Insufficient attendance data to verify OT hours.';
            $calculated_ot = null;
        }
    } else {
        // we can still compute calculated OT when actual times exist
        $calculated_ot = ($actualIn && $actualOut)
            ? round(max(0, ($policyIn->getTimestamp() - $actualIn->getTimestamp()) / 3600) + max(0, ($actualOut->getTimestamp() - $policyOut->getTimestamp()) / 3600))
            : null;
    }

    $existing_ojt_hours = isset($record['ot_hours']) ? (float)$record['ot_hours'] : 0.0;
    $ojt_hours_after = is_numeric($calculated_ot) ? $existing_ojt_hours + $calculated_ot : null;

    if (empty($issues)) {
        $status = '<span class="badge bg-success" data-bs-toggle="tooltip" title="Verified">Verified</span>';
        $explanation = 'Actual attendance times match or exceed the reported OT requirements.';
    } else {
        $status = '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Mismatch">Mismatch</span>';
        $explanation = implode(' ', $issues);
    }
    return [
        'status' => $status,
        'actual_in' => $isValidDbTime($record['time_in']) ? $fmt($record['time_in']) : null,
        'actual_out' => $isValidDbTime($record['time_out']) ? $fmt($record['time_out']) : null,
        'policy_in' => $record['time_in_policy'] ? $fmt($record['time_in_policy']) : null,
        'policy_out' => $record['time_out_policy'] ? $fmt($record['time_out_policy']) : null,
        'calculated_ot' => $calculated_ot,
        'ojt_hours' => $existing_ojt_hours,
        'ojt_hours_after' => $ojt_hours_after,
        'explanation' => $explanation
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>OT Reports — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
    :root {
        --primary: #4c8eb1;
        --primary-dark: #3cb2cc;
        --secondary: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --light: #f8fafc;
        --dark: #0f172a;
        --border: #e2e8f0;
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
        min-height: 100vh;
        color: var(--dark);
        padding-bottom: 2rem;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.5rem;
        background: rgba(60, 178, 204, 0.09);
        border-radius: 22px;
        box-shadow: 0 8px 32px 0 rgba(60,178,204,0.08);
        backdrop-filter: blur(6px) saturate(120%);
    }

    .dashboard-header {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        border-left: 5px solid var(--primary);
    }

    .dashboard-header h1 {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .dashboard-header p {
        color: #64748b;
        font-size: 1rem;
        margin: 0;
    }

    .card-custom {
        background: white;
        border-radius: 16px;
        padding: 1.75rem;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border);
    }

    .card-header-custom h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--dark);
        margin: 0;
    }

    .filter-sidebar {
        width: 250px;
        background: white;
        padding: 1rem;
        border-radius: 16px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
        margin-right: 1rem;
    }

    .table-responsive .table {
        margin: 0;
    }

    .table thead th {
        background: var(--light);
        color: #64748b;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        padding: 1rem;
        border: none;
    }

    .table tbody tr:hover {
        background: var(--light);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-badge.verified {
        background: rgba(16, 185, 129, 0.1);
        color: var(--secondary);
    }

    .status-badge.mismatch {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
    }

    .pagination-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
    }

    .btn-outline-primary {
        border-color: var(--primary);
        color: var(--primary);
    }

    .btn-outline-primary:hover {
        background: var(--primary);
        color: white;
    }

    /* remove modal backdrop completely for this page */
    .modal-backdrop { display: none !important; }
    .modal { z-index: 2100 !important; }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 0.5rem;
        }
        .dashboard-header {
            padding: 1rem;
        }
        .filter-sidebar {
            width: 100%;
            margin-bottom: 1rem;
        }
        .content-grid {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="bi bi-clock-history"></i> OT Reports</h1>
            <p>Review and verify submitted overtime reports</p>
        </div>

        <div class="d-flex">
            <div class="filter-sidebar">
                <h6><i class="bi bi-funnel"></i> Filters</h6>
                <div class="mb-3">
                    <label for="filterType" class="form-label small">Type</label>
                    <select id="filterType" class="form-select form-select-sm">
                        <option value="">All types</option>
                        <option value="early">Early OT</option>
                        <option value="late">Normal OT</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="filterStatus" class="form-label small">Status</label>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
            </div>
            <div class="flex-grow-1">
                <?php if ($success): ?>
                    <div class="alert alert-success mb-3"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle"></i> <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
                <?php endif; ?>

                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-table"></i> Reports List</h3>
                        <div class="d-flex align-items-center flex-wrap">
                            <div class="me-2" style="max-width:420px;">
                                <input id="searchBox" class="form-control form-control-sm" placeholder="Search student, reason, date..." />
                            </div>
                            <button id="clearFilters" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle"></i> Clear</button>
                        </div>
                    </div>
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-info-circle" style="font-size: 3rem; color: #64748b;"></i>
                            <h5 class="mt-3 text-muted">No OT reports found.</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="reportsTable" class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:240px"><i class="bi bi-person"></i> Student</th>
                                        <th style="width:110px"><i class="bi bi-calendar"></i> Date</th>
                                        <th style="width:120px"><i class="bi bi-tag"></i> Type</th>
                                        <th style="width:90px"><i class="bi bi-hourglass"></i> Hours</th>
                                        <th><i class="bi bi-chat-text"></i> Reason</th>
                                        <th style="width:160px"><i class="bi bi-check-circle"></i> Verification</th>
                                        <th style="width:120px"><i class="bi bi-flag"></i> Status</th>
                                        <th style="width:120px" class="text-end"><i class="bi bi-gear"></i> Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($reports as $r):
                                    $verification = getVerificationStatus($oat, $r);
                                    $typeLabel = ($r['ot_type'] === 'early') ? 'Early OT' : (($r['ot_type'] === 'late') ? 'Normal OT' : ucfirst($r['ot_type']));
                                    // treat 0 / NULL / '' as pending; 1 = approved; -1 (or any other) = rejected
                                    if ($r['approved'] == 1) {
                                        $statusKey = 'approved';
                                    } elseif (is_null($r['approved']) || $r['approved'] === '' || $r['approved'] == 0) {
                                        $statusKey = 'pending';
                                    } else {
                                        $statusKey = 'rejected';
                                    }
                                ?>
                                    <tr data-name="<?= htmlspecialchars(strtolower($r['fname'].' '.$r['lname'])) ?>"
                                        data-reason="<?= htmlspecialchars(strtolower($r['ot_reason'])) ?>"
                                        data-date="<?= htmlspecialchars($r['ot_date']) ?>"
                                        data-type="<?= htmlspecialchars($r['ot_type']) ?>"
                                        data-status="<?= $statusKey ?>">
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></div>
                                            <div class="small text-muted">ID: <?= htmlspecialchars($r['user_id']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($r['ot_date']) ?></td>
                                        <td><span class="badge bg-info text-dark" style="min-width:96px; display:inline-block; text-align:center;"><?= htmlspecialchars($typeLabel) ?></span></td>
                                        <td><strong><?= htmlspecialchars($r['ot_hours']) ?>h</strong></td>
                                        <td class="small text-muted" title="<?= htmlspecialchars($r['ot_reason']) ?>"><?= htmlspecialchars(mb_strimwidth($r['ot_reason'], 0, 60, '...')) ?></td>
                                        <td>
                                            <?= $verification['status'] ?>
                                            <div class="small text-muted mt-1">
                                                <?php if (!empty($r['reported_time_in']) && !in_array($r['reported_time_in'], ['00:00:00','0000-00-00 00:00:00'], true)): ?>
                                                    Reported: <?= htmlspecialchars(date('g:i A', strtotime($r['reported_time_in']))) ?> &nbsp;·&nbsp;
                                                <?php endif; ?>
                                                Actual: <?= htmlspecialchars($verification['actual_in'] ?? '—') ?> &nbsp;·&nbsp;
                                                Call Time: <?= htmlspecialchars($verification['policy_in'] ?? '—') ?>
                                                <?php if ($r['ot_type'] !== 'early'): ?>
                                                    &nbsp;·&nbsp; Out: <?= htmlspecialchars($verification['policy_out'] ?? '—') ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($r['approved'] == 1): ?>
                                                <span class="badge bg-success"><i class="bi bi-check"></i> Approved</span>
                                            <?php elseif (is_null($r['approved']) || $r['approved'] === '' || $r['approved'] == 0): ?>
                                                <span class="badge bg-secondary"><i class="bi bi-clock"></i> Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="bi bi-x"></i> Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-<?= $r['id'] ?>" aria-label="View details"><i class="bi bi-eye"></i> Details</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-controls">
                            <div id="pageInfo" class="small text-muted">Showing 1-10 of <?= count($reports) ?> reports</div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item disabled" id="prevPage"><a class="page-link" href="#" aria-label="Previous"><i class="bi bi-chevron-left"></i></a></li>
                                    <li class="page-item active" id="page1"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item disabled" id="nextPage"><a class="page-link" href="#" aria-label="Next"><i class="bi bi-chevron-right"></i></a></li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- modals moved outside .dashboard-container to avoid stacking/context clipping -->
    </div> <!-- End of dashboard-container -->

    <!-- Detail modals (moved here so Bootstrap modal is centered and above everything) -->
    <?php foreach ($reports as $r):
        $verification = getVerificationStatus($oat, $r);
        $typeLabel = ($r['ot_type'] === 'early') ? 'Early OT' : (($r['ot_type'] === 'late') ? 'Normal OT' : ucfirst($r['ot_type']));
    ?>
    <div class="modal fade" id="modal-<?= $r['id'] ?>" data-bs-backdrop="false" tabindex="-1" aria-labelledby="modalLabel-<?= $r['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-earmark-text"></i> OT Report — <?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-4"><i class="bi bi-tag"></i> OT Type</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($typeLabel) ?></dd>
                        <dt class="col-sm-4"><i class="bi bi-calendar"></i> Date</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($r['ot_date']) ?></dd>
                        <dt class="col-sm-4"><i class="bi bi-hourglass"></i> Hours</dt>
                        <dd class="col-sm-8"><strong><?= htmlspecialchars($r['ot_hours']) ?>h</strong></dd>
                        <dt class="col-sm-4"><i class="bi bi-chat-text"></i> Reason</dt>
                        <dd class="col-sm-8"><?= nl2br(htmlspecialchars($r['ot_reason'])) ?></dd>
                        <?php if (!empty($r['reported_time_in']) && !in_array($r['reported_time_in'], ['00:00:00','0000-00-00 00:00:00'], true)): ?>
                            <dt class="col-sm-4"><i class="bi bi-sunrise"></i> Reported In</dt>
                            <dd class="col-sm-8"><?= date('g:i A', strtotime($r['reported_time_in'])) ?></dd>
                        <?php endif; ?>

                        <dt class="col-sm-4"><i class="bi bi-clock-history"></i> Actual Time In</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($verification['actual_in'] ?? '—') ?></dd>

                        <dt class="col-sm-4"><i class="bi bi-telephone-fill"></i> Call Time</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($verification['policy_in'] ?? '—') ?></dd>

                        <?php if ($r['ot_type'] !== 'early'): ?>
                            <?php if (!empty($r['reported_time_out']) && !in_array($r['reported_time_out'], ['00:00:00','0000-00-00 00:00:00'], true)): ?>
                                <dt class="col-sm-4"><i class="bi bi-sunset"></i> Reported Out</dt>
                                <dd class="col-sm-8"><?= date('g:i A', strtotime($r['reported_time_out'])) ?></dd>
                            <?php endif; ?>

                            <dt class="col-sm-4"><i class="bi bi-clock"></i> Actual Time Out</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($verification['actual_out'] ?? '—') ?></dd>

                            <dt class="col-sm-4"><i class="bi bi-telephone-forward-fill"></i> Call Out</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($verification['policy_out'] ?? '—') ?></dd>
                        <?php endif; ?>
                        <dt class="col-sm-4"><i class="bi bi-check-circle"></i> Verification</dt>
                        <dd class="col-sm-8"><?= $verification['status'] ?></dd>
                        <dt class="col-sm-4"><i class="bi bi-info-circle"></i> Explanation</dt>
                        <dd class="col-sm-8 small text-muted"><?= htmlspecialchars($verification['explanation']) ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <form method="POST" class="d-flex gap-2 w-100">
                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                        <div class="ms-auto">
                            <button type="submit" name="action" value="approve_requested" class="btn btn-success btn-sm" <?= $r['approved'] == 1 ? 'disabled' : '' ?>><i class="bi bi-check2"></i> Approve Requested</button>
                            <button type="submit" name="action" value="approve_actual" class="btn btn-warning btn-sm" <?= $r['approved'] == 1 ? 'disabled' : '' ?>><i class="bi bi-calculator"></i> Approve Actual</button>
                            <!-- allow rejecting approved reports; only disable when already rejected -->
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm" <?= ($r['approved'] == -1) ? 'disabled' : '' ?>><i class="bi bi-x"></i> Reject</button>
                        </div>
                    </form>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><i class="bi bi-x"></i> Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced client-side filtering and pagination (unchanged)
        (function(){
            const table = document.getElementById('reportsTable');
            if (!table) return;
            const tbody = table.tBodies[0];
            const rows = Array.from(tbody.rows);
            const searchBox = document.getElementById('searchBox');
            const filterType = document.getElementById('filterType');
            const filterStatus = document.getElementById('filterStatus');
            const clearFilters = document.getElementById('clearFilters');
            const pageInfo = document.getElementById('pageInfo');
            const prevPage = document.getElementById('prevPage');
            const nextPage = document.getElementById('nextPage');
            const page1 = document.getElementById('page1');
            let currentPage = 1;
            const itemsPerPage = 10;
            let filteredRows = rows;

            function applyFilters(){
                const q = (searchBox.value || '').trim().toLowerCase();
                const type = filterType.value;
                const status = filterStatus.value;
                filteredRows = rows.filter(r => {
                    const name = r.dataset.name || '';
                    const reason = r.dataset.reason || '';
                    const date = r.dataset.date || '';
                    const rowType = r.dataset.type || '';
                    const rowStatus = r.dataset.status || '';
                    const matchesQuery = !q || name.includes(q) || reason.includes(q) || date.includes(q);
                    const matchesType = !type || rowType === type;
                    const matchesStatus = !status || rowStatus === status;
                    return matchesQuery && matchesType && matchesStatus;
                });
                currentPage = 1;
                renderPage();
            }

            function renderPage(){
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                rows.forEach((r, i) => r.style.display = (i >= start && i < end && filteredRows.includes(r)) ? '' : 'none');
                const totalPages = Math.ceil(filteredRows.length / itemsPerPage);
                pageInfo.textContent = `Showing ${start + 1}-${Math.min(end, filteredRows.length)} of ${filteredRows.length} reports`;
                prevPage.classList.toggle('disabled', currentPage === 1);
                nextPage.classList.toggle('disabled', currentPage === totalPages);
                page1.classList.toggle('active', currentPage === 1);
            }

            [searchBox, filterType, filterStatus].forEach(el => el.addEventListener('input', applyFilters));
            clearFilters.addEventListener('click', () => {
                searchBox.value = '';
                filterType.value = '';
                filterStatus.value = '';
                applyFilters();
            });
            prevPage.addEventListener('click', (e) => { e.preventDefault(); if (currentPage > 1) { currentPage--; renderPage(); } });
            nextPage.addEventListener('click', (e) => { e.preventDefault(); if (currentPage < Math.ceil(filteredRows.length / itemsPerPage)) { currentPage++; renderPage(); } });
            page1.addEventListener('click', (e) => { e.preventDefault(); currentPage = 1; renderPage(); });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

            renderPage();
        })();

        // Auto-open OT report modal if open_ot param is present
        (function() {
            function getParam(name) {
                const url = new URL(window.location.href);
                return url.searchParams.get(name);
            }
            const openOt = getParam('open_ot');
            if (openOt) {
                const modalId = 'modal-' + openOt;
                const modalEl = document.getElementById(modalId);
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    setTimeout(() => modal.show(), 300); // slight delay to ensure DOM is ready
                }
            }
        })();
    </script>

<?php
// flush buffer (safe if buffering was started above)
if (ob_get_level()) ob_end_flush();
?>
</body>
</html>
