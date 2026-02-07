<?php

include '../db.php';
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

$ojt_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if (!$ojt_id) {
    echo "<div class='alert alert-danger'>No OJT selected.</div>";
    exit;
}

// Fetch OJT info
$ojt = $oat->query("SELECT fname, lname, username, profile_img FROM users WHERE id = $ojt_id")->fetch_assoc();
if (!$ojt) {
    echo "<div class='alert alert-danger'>OJT not found.</div>";
    exit;
}

// Fetch DTR records
$dtr_query = $oat->query("SELECT date, time_in, time_out FROM ojt_records WHERE user_id = $ojt_id ORDER BY date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTR Report - <?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <th class="text-center">Late</th>
                        <th class="text-center">Total Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($dtr_query && $dtr_query->num_rows > 0): ?>
                        <?php while ($row = $dtr_query->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['date']) ?></td>
                                <td class="text-center"><?= $row['time_in'] ? date("g:i A", strtotime($row['time_in'])) : '' ?></td>
                                <td class="text-center">
                                    <?= ($row['time_out'] && $row['time_out'] !== '00:00:00') ? date("g:i A", strtotime($row['time_out'])) : '' ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $late = 'No';
                                    if ($row['time_in'] && date('H:i:s', strtotime($row['time_in'])) > '08:00:00') {
                                        $late = 'Yes';
                                    }
                                    echo $late;
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $time_in = strtotime($row['time_in']);
                                        $time_out = strtotime($row['time_out']);
                                        $hours = ($time_out - $time_in) / 3600;

                                        // Deduct 1 hour ONLY if the interval overlaps with 12:00:00 to 13:00:00
                                        $lunch_start = strtotime(date('Y-m-d', $time_in) . ' 12:00:00');
                                        $lunch_end = strtotime(date('Y-m-d', $time_in) . ' 13:00:00');
                                        $overlaps_lunch = ($time_in < $lunch_end) && ($time_out > $lunch_start);
                                        if ($overlaps_lunch) {
                                            $hours -= 1;
                                        }

                                        // Deduct 1 hour ONLY if late (time_in after 8:00 AM)
                                        if (date('H:i:s', $time_in) > '08:00:00') {
                                            $hours -= 1;
                                        }

                                        echo max(0, round($hours)) . ' h';
                                    } else {
                                        echo '';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
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
</body>
</html>