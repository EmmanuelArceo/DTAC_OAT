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

// prepare avatar path + attributes (use actual file if exists, otherwise default)
$avatar = '../uploads/noimg.png';
if (!empty($ojt['profile_img']) && file_exists(__DIR__ . '/../' . $ojt['profile_img'])) {
    $avatar = '../' . $ojt['profile_img'];
}
$avatar_src = htmlspecialchars($avatar . '?t=' . time());
$avatar_alt = htmlspecialchars(trim(($ojt['fname'] ?? '') . ' ' . ($ojt['lname'] ?? '')));

// Fetch DTR records using prepared statement (include policy fields and selfie_verified)
$stmt = $oat->prepare("SELECT id, date, time_in, time_out, time_in_policy, time_out_policy, ot_hours, lunch_start, lunch_end, selfie_verified FROM ojt_records WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param("i", $ojt_id);
$stmt->execute();
$dtr_query = $stmt->get_result();
$stmt->close();

function calculate_session_hours($row, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $user_id, $oat) {
    // treat explicit "00:00:00" as missing for both time_in/time_out
    if (empty($row['time_in']) || $row['time_in'] === '00:00:00' || empty($row['time_out']) || $row['time_out'] === '00:00:00') {
        return ['regular' => 0, 'ot' => 0, 'total' => 0];
    }

    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);
    // handle overnight sessions (time_out on next day)
    if ($time_out <= $time_in) $time_out += 24 * 3600;

    // Calculate policy times on the same date as time_in
    $policy_in_time = strtotime(date('Y-m-d', $time_in) . ' ' . $user_policy_time_in);
    $policy_out_time = strtotime(date('Y-m-d', $time_in) . ' ' . $user_policy_time_out);
    $policy_duration = $policy_out_time - $policy_in_time;

    // If late by 1 hour or more, start counting from next full hour; otherwise, start from actual time in
    $lateness = $time_in - $policy_in_time;
    if ($lateness >= 3600) {
        $count_start = strtotime(date('Y-m-d H:00:00', $time_in) . ' +1 hour');
    } else {
        $count_start = $time_in;
    }

    // FIX: Regular end is counted start + policy duration
    $regular_end = $count_start + $policy_duration;

    // Regular hours: up to regular end
    $reg_hours = min($time_out, $regular_end) - $count_start;
    $reg_hours = $reg_hours / 3600;

    // Deduct overlapping lunch break hours (use stored lunch if available, else current)
    $lunch_start = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_start'] ?? $user_lunch_start));
    $lunch_end = strtotime(date('Y-m-d', $count_start) . ' ' . ($row['lunch_end'] ?? $user_lunch_end));
    $overlap = max(0, min($time_out, $lunch_end) - max($count_start, $lunch_start));
    $reg_hours -= $overlap / 3600;

    // OT hours: from ojt_records
    $ot_hours = (float)($row['ot_hours'] ?? 0);

    $total_hours = max(0, max(0, $reg_hours) + $ot_hours); // Always add full OT hours
    return ['regular' => max(0, $reg_hours), 'ot' => $ot_hours, 'total' => $total_hours];
}

// unified time formatter (no leading zero, UPPER AM/PM; treats '00:00:00' as empty)
if (!function_exists('format_time')) {
    function format_time($t) {
        if (empty($t) || $t === '00:00:00') return '--';
        $ts = strtotime($t);
        return $ts ? date('g:iA', $ts) : '--';
    }
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
            <img src="<?= $avatar_src ?>" class="profile-img" alt="<?= $avatar_alt ?>">
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
                                <td>
                                    <?php if (!empty($row['id'])): ?>
                                        <a href="verifydtr.php?id=<?= (int)$row['id'] ?>" class="text-decoration-underline">
                                            <?= htmlspecialchars($row['date']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['date']) ?>
                                    <?php endif; ?>
                                    <?php if (empty($row['selfie_verified']) || $row['selfie_verified'] != 1): ?>
                                        <span title="Selfie not verified" style="color:#e63946; margin-left:4px; vertical-align:middle;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16" style="vertical-align:middle;">
                                              <path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233c-.457.778.091 1.767.982 1.767h13.707c.89 0 1.438-.99.982-1.767L8.982 1.566zm-.982 4.905a.905.905 0 1 1 1.81 0l-.35 3.507a.552.552 0 0 1-1.11 0l-.35-3.507zm.002 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= format_time($row['time_in']) ?></td>
                                <td class="text-center"><?= format_time($row['time_out']) ?></td>
                                <td class="text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $result = calculate_session_hours($row, $row['time_in_policy'], $row['time_out_policy'], $row['lunch_start'], $row['lunch_end'], $ojt_id, $oat);
                                        echo $result['ot'] . ' h';
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    if ($row['time_in'] && $row['time_out'] && $row['time_out'] !== '00:00:00') {
                                        $result = calculate_session_hours($row, $row['time_in_policy'], $row['time_out_policy'], $row['lunch_start'], $row['lunch_end'], $ojt_id, $oat);
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