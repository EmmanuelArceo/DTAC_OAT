<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

// Get DTR record by ID (passed via GET)
$dtr_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;


// Fetch DTR record (include selfie_verified)
$stmt = $oat->prepare("SELECT * FROM ojt_records WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $dtr_id, $user_id);
$stmt->execute();
$dtr = $stmt->get_result()->fetch_assoc();

if (!$dtr) {
    echo "<div class='alert alert-danger'>Record not found.</div>";
    exit;
}

// Use policy times directly from the DTR record
$policy_time_in_str = $dtr['time_in_policy'] ?? '08:00:00';
$policy_time_out_str = $dtr['time_out_policy'] ?? '17:00:00';
$lunch_start = $dtr['lunch_start'] ?? '12:00:00';
$lunch_end = $dtr['lunch_end'] ?? '13:00:00';

// Calculation breakdown
$time_in = strtotime($dtr['time_in']);
$time_out = strtotime($dtr['time_out']);
$policy_time_in = strtotime($policy_time_in_str);
$policy_time_out = strtotime($policy_time_out_str);

// Calculate policy duration
$policy_duration = $policy_time_out - $policy_time_in;

$lateness = $time_in - $policy_time_in;
if ($lateness > 0) {
    $late_hours = floor($lateness / 3600);
    $late_minutes = floor(($lateness % 3600) / 60);
    $late_str = [];
    if ($late_hours > 0) $late_str[] = $late_hours . ' hour' . ($late_hours > 1 ? 's' : '');
    if ($late_minutes > 0) $late_str[] = $late_minutes . ' minute' . ($late_minutes > 1 ? 's' : '');
    $late_str = $late_str ? implode(' and ', $late_str) : 'less than a minute';

    if ($lateness >= 3600) {
        $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
        $count_start_hour = date('g:i A', $count_start);
        $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> Late by $late_str, counted from next full hour (<b>counting starts at $count_start_hour</b>).</span>";
    } else {
        $count_start = $time_in;
        $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> Late by $late_str.</span>";
    }
} else {
    $count_start = $time_in;
    $late_note = "<span class='text-success'><i class='bi bi-check-circle'></i> On time.</span>";
}

// Only calculate regular hours if time_out is set
if (!empty($dtr['time_out']) && $dtr['time_out'] !== '00:00:00') {
    $regular_end = $count_start + $policy_duration;
    $reg_hours = min($time_out, $regular_end) - $count_start;
    $reg_hours = $reg_hours / 3600;

    $lunch_start_ts = strtotime(date('Y-m-d', $count_start) . ' ' . $lunch_start);
    $lunch_end_ts = strtotime(date('Y-m-d', $count_start) . ' ' . $lunch_end);
    // Always check for overlap if any part of work session is within lunch
    if ($count_start < $lunch_end_ts && $time_out > $lunch_start_ts) {
        $deduct_start = max($count_start, $lunch_start_ts);
        $deduct_end = min($time_out, $lunch_end_ts);
        $overlap = max(0, $deduct_end - $deduct_start);
        if ($overlap > 0) {
            $lunch_note = "<span class='text-warning'><i class='bi bi-box-arrow-in-down'></i> Lunch break deducted: ";
            $lunch_note .= date('g:i A', $deduct_start) . " – " . date('g:i A', $deduct_end);
            $lunch_note .= " (" . number_format($overlap/3600, 2) . " hour(s))</span>";
            $reg_hours -= $overlap / 3600;
        } else {
            $lunch_note = "<span class='text-success'><i class='bi bi-check-circle'></i> No lunch deduction.</span>";
        }
    } else {
        $lunch_note = "<span class='text-success'><i class='bi bi-check-circle'></i> No lunch deduction.</span>";
    }

    $ot_hours = (float)($dtr['ot_hours'] ?? 0);
    $total_hours = max(0, max(0, $reg_hours) + $ot_hours); // Always add full OT hours
} else {
    $reg_hours = 0;
    $lunch_note = "<span class='text-muted'><i class='bi bi-dash-circle'></i> Waiting for time out to compute lunch deduction.</span>";
    $ot_hours = (float)($dtr['ot_hours'] ?? 0);
    $total_hours = $ot_hours;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR Detail</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #f8fafc; }
        .card-custom { border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .table th { width: 30%; }
        .breakdown-list li { margin-bottom: 8px; font-size: 1.05em; }
        .hours-badge { font-size: 1.2em; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card card-custom mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i> DTR Detail for <?= htmlspecialchars($dtr['date']) ?></h4>
                </div>
                <?php if (empty($dtr['selfie_verified']) || $dtr['selfie_verified'] != 1): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 m-0" role="alert" style="border-radius:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Unverified">
                        <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.707c.89 0 1.438-.99.982-1.767L8.982 1.566zm-.982 4.905a.905.905 0 1 1 1.81 0l-.35 3.507a.552.552 0 0 1-1.11 0l-.35-3.507zm.002 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                    </svg>
                    <div><strong>This DTR record was flagged as Face Image did not Match.</strong> Please contact your supervisor if you believe this is a mistake.</div>
                </div>
                <?php endif; ?>
                <div class="card-body">
                    <table class="table table-bordered mb-4">
                        <tr>
                            <th><i class="bi bi-calendar-event"></i> Date</th>
                            <td><?= htmlspecialchars($dtr['date']) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-box-arrow-in-right"></i> Timed In</th>
                            <td><?= date("h:i A", strtotime($dtr['time_in'])) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-box-arrow-left"></i> Timed Out</th>
                            <td><?= date("h:i A", strtotime($dtr['time_out'])) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-hourglass-split"></i> Lunch Break</th>
                            <td><?= date("h:i A", strtotime($lunch_start)) ?> - <?= date("h:i A", strtotime($lunch_end)) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-clock"></i> Time In Policy</th>
                            <td><?= date("h:i A", strtotime($policy_time_in_str)) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-clock"></i> Time Out Policy</th>
                            <td><?= date("h:i A", strtotime($policy_time_out_str)) ?></td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-plus-circle"></i> Overtime</th>
                            <td>
                                <?= htmlspecialchars($ot_hours) ?> hour(s)
                                <?php
                                if (isset($dtr['ot_status'])) {
                                    if ($dtr['ot_status'] === 'accepted') {
                                        echo "<span class='badge bg-success ms-2'>Accepted</span>";
                                    } elseif ($dtr['ot_status'] === 'rejected') {
                                        echo "<span class='badge bg-danger ms-2'>Rejected</span>";
                                    } else {
                                        echo "<span class='badge bg-secondary ms-2'>Pending</span>";
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><i class="bi bi-chat-left-text"></i> Remarks</th>
                            <td><?= htmlspecialchars($dtr['remarks']) ?></td>
                        </tr>
                    </table>
                    <div class="mb-3">
                        <span class="badge bg-info hours-badge">
                            <i class="bi bi-calculator"></i> Total Hours: <?= number_format($total_hours, 2) ?> hour(s)
                        </span>
                    </div>
                    <h5 class="mb-2"><i class="bi bi-info-circle"></i> Calculation Breakdown</h5>
                    <ul class="breakdown-list list-unstyled">
                        <li><?= $late_note ?></li>
                        <li><?= $lunch_note ?></li>
                        <li><i class="bi bi-clock-history"></i> Regular hours counted: <strong><?= $reg_hours > 0 ? number_format($reg_hours, 2) . ' hour(s)' : '--' ?></strong></li>
                        <li><i class="bi bi-plus-circle"></i> Overtime hours: <strong><?= $ot_hours ?> hour(s)</strong></li>
                        <li><i class="bi bi-check2-all"></i> <strong>Total hours: <?= number_format($total_hours, 2) ?> hour(s)</strong></li>
                    </ul>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>