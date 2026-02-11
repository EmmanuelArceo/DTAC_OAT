<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include 'db.php';

// Get DTR record by ID (passed via GET)
$dtr_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Fetch DTR record
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
$lunch_start_ts = strtotime($lunch_start);
$lunch_end_ts = strtotime($lunch_end);

$lateness = $time_in - $policy_time_in;
if ($lateness > 0) {
    if ($lateness >= 3600) {
        $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
        $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> Late by " . floor($lateness/60) . " minutes, counted from next full hour.</span>";
    } else {
        $count_start = $time_in;
        $late_note = "<span class='text-danger'><i class='bi bi-clock'></i> On time or less than 1 hour late.</span>";
    }
} else {
    $count_start = $time_in;
    $late_note = "<span class='text-success'><i class='bi bi-check-circle'></i> On time.</span>";
}

$regular_end = $policy_time_out;
$reg_hours = min($time_out, $regular_end) - $count_start;
$reg_hours = $reg_hours / 3600;

$overlap = max(0, min($time_out, $lunch_end_ts) - max($count_start, $lunch_start_ts));
$lunch_note = $overlap > 0
    ? "<span class='text-warning'><i class='bi bi-box-arrow-in-down'></i> Lunch break deducted: " . ($overlap/3600) . " hour(s).</span>"
    : "<span class='text-success'><i class='bi bi-check-circle'></i> No lunch deduction.</span>";

$reg_hours -= $overlap / 3600;

$ot_hours = (float)($dtr['ot_hours'] ?? 0);
$total_hours = max(0, floor($reg_hours + $ot_hours));
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
                            <i class="bi bi-calculator"></i> Total Hours: <?= $total_hours ?> hour(s)
                        </span>
                    </div>
                    <h5 class="mb-2"><i class="bi bi-info-circle"></i> Calculation Breakdown</h5>
                    <ul class="breakdown-list list-unstyled">
                        <li><?= $late_note ?></li>
                        <li><?= $lunch_note ?></li>
                        <li><i class="bi bi-clock-history"></i> Regular hours counted: <strong><?= round($reg_hours, 2) ?> hour(s)</strong></li>
                        <li><i class="bi bi-plus-circle"></i> Overtime hours: <strong><?= $ot_hours ?> hour(s)</strong></li>
                        <li><i class="bi bi-check2-all"></i> <strong>Total hours: <?= $total_hours ?> hour(s)</strong></li>
                    </ul>
                </div>
            </div>
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>