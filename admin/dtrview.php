<?php
 


include '../db.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['super_admin','admin'])) {
    header("Location: ../login.php");
    exit;
}

$ojt_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$ojt_id) {
    echo "<div class='alert alert-danger'>No OJT selected.</div>";
    exit;
}

// Fetch OJT info using prepared statement
$stmt = $oat->prepare("SELECT fname, lname, username, profile_img FROM users WHERE id = ?");
$stmt->bind_param("i", $ojt_id);
$stmt->execute();
$ojt = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ojt) {
    echo "<div class='alert alert-danger'>OJT not found.</div>";
    exit;
}

// Fetch default time in/out and lunch from default time group using prepared statement
$stmt = $oat->prepare("SELECT time_in, time_out, lunch_start, lunch_end FROM time_groups WHERE name = 'Default' LIMIT 1");
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();
$default_time_in = $settings['time_in'] ?? '08:00:00';
$default_time_out = $settings['time_out'] ?? '17:00:00';
$default_lunch_start = $settings['lunch_start'] ?? '12:00:00';
$default_lunch_end = $settings['lunch_end'] ?? '13:00:00'; // Updated default to 1h lunch

// Check if user is in a time group and fetch group times
$user_policy_time_in = $default_time_in;
$user_policy_time_out = $default_time_out;
$user_lunch_start = $default_lunch_start;
$user_lunch_end = $default_lunch_end;
$stmt = $oat->prepare("SELECT tg.time_in, tg.time_out, tg.lunch_start, tg.lunch_end FROM user_time_groups utg JOIN time_groups tg ON utg.group_id = tg.id WHERE utg.user_id = ? LIMIT 1");
$stmt->bind_param("i", $ojt_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if ($group) {
    $user_policy_time_in = $group['time_in'];
    $user_policy_time_out = $group['time_out'];
    $user_lunch_start = $group['lunch_start'] ?: $default_lunch_start;
    $user_lunch_end = $group['lunch_end'] ?: $default_lunch_end;
}

// Fetch DTR records using prepared statement
$stmt = $oat->prepare("SELECT date, time_in, time_out, time_in_policy, time_out_policy, ot_hours, lunch_start, lunch_end FROM ojt_records WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $ojt_id);
$stmt->execute();
$dtr_query = $stmt->get_result();
$stmt->close();

function calculate_session_hours($row, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $user_id, $oat) {
    if (!$row['time_in'] || !$row['time_out'] || $row['time_out'] === '00:00:00') {
        return ['regular' => 0, 'ot' => 0, 'total' => 0];
    }

    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);
    $policy_time_in = $user_policy_time_in; // Always use current user policy
    $policy_time_out = $user_policy_time_out; // Always use current user policy

    $regular_end = strtotime($policy_time_out);
    $policy_in_time = strtotime(date('Y-m-d', $time_in) . ' ' . $policy_time_in);

    // If late by 1 hour or more, start counting from next full hour; otherwise, start from actual time in
    $lateness = $time_in - $policy_in_time;
    if ($lateness >= 3600) {
        $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
    } else {
        $count_start = $time_in;
    }

    // Regular hours: up to official time out
    $reg_hours = min($time_out, $regular_end) - $count_start;
    $reg_hours = $reg_hours / 3600;

    // Deduct overlapping lunch break hours (use stored lunch if available, else current)
    $lunch_start = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_start'] ?? $user_lunch_start));
    $lunch_end = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_end'] ?? $user_lunch_end));
    $overlap = max(0, min($time_out, $lunch_end) - max($count_start, $lunch_start));
    $reg_hours -= $overlap / 3600;

    // OT hours: from ojt_records
    $ot_hours = (float)($row['ot_hours'] ?? 0);

    $total_hours = max(0, floor($reg_hours + $ot_hours));
    return ['regular' => max(0, $reg_hours), 'ot' => $ot_hours, 'total' => $total_hours];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title class="title">DTR Report - <?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f7fbfb 0%, #fbfcfd 100%);
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
        }
        .glass {
            background: rgba(255,255,255,0.7);
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(15,23,42,0.06);
            max-width: 900px;
            margin: 40px auto;
            padding: 32px 24px;
        }
        .profile-img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3CB3CC;
            margin-right: 18px;
        }
        .dtr-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2aa0b3;
        }
        .table thead th {
            background: linear-gradient(90deg, #2aa0b3, #3CB3CC);
            color: #fff;
            border: none;
        }
        .table tbody tr:hover {
            background: rgba(60,179,204,0.04);
        }
        @media print {
            body {
                background: #fff !important;
            }
            .btn, .mt-4, .mb-3.text-end {
                display: none !important;
            }
            .glass {
                box-shadow: none !important;
                border: none !important;
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
         
       
          
        }
    </style>
</head>
<body>
    <div class="glass">
        <div class="d-flex align-items-center mb-4">
            <img src="<?= !empty($ojt['profile_img']) ? '../' . htmlspecialchars($ojt['profile_img'] . '?t=' . time()) : '../uploads/noimg.png' ?>" class="profile-img" alt="Profile">
            <div>
                <div class="dtr-title"><?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></div>
                <div class="text-muted">@<?= htmlspecialchars($ojt['username']) ?></div>
            </div>
        </div>
        <h5 class="mb-3" style="color:#2aa0b3;">DTR Records</h5>
        <div class="mb-3 text-end">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print DTR
            </button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Time In</th>
                        <th class="text-center">Time Out</th>
                        <th class="text-center">OT Hours</th>
                        <th class="text-center">Total Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dtr_query && $dtr_query->num_rows > 0): ?>
                        <?php while ($row = $dtr_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td class="text-center"><?= $row['time_in'] ? date("g:i A", strtotime($row['time_in'])) : '--' ?></td>
                                <td class="text-center">
                                    <?= ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date("g:i A", strtotime($row['time_out'])) : '--' ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $result = calculate_session_hours($row, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $ojt_id, $oat);
                                        echo $result['ot'] . ' h';
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $result = calculate_session_hours($row, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $ojt_id, $oat);
                                        echo $result['total'] . ' h';
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No DTR records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            <a href="admin.php" class="btn btn-outline-secondary">&larr; Back to Dashboard</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>