<?php

include '../db.php';
include 'nav.php';


if (session_status() === PHP_SESSION_NONE) {    
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Get today's date
$today = date('Y-m-d');

// Fetch all OJTs who have time_in for today
$ojts = $oat->query("
    SELECT u.id, u.username, u.fname, u.lname, u.profile_img, r.time_in, r.time_out, r.remarks
    FROM users u
    JOIN ojt_records r ON u.id = r.user_id
    WHERE u.role = 'ojt' AND r.date = '$today' AND r.time_in IS NOT NULL AND r.time_in != ''
    ORDER BY r.time_in ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Active OJT Today</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4c8eb1;
            --primary-dark: #3cb2cc;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #0f172a;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.08), 0 1px 2px -1px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.08);
        }
        body {
            font-family: 'Inter', sans-serif;
            /* Lighter glassy, light blue background */
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .main-header {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 1px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .ojt-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .ojt-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: var(--shadow-lg);
            padding: 1.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s, transform 0.2s;
            border: none;
            position: relative;
            min-height: 270px;
        }
        .ojt-card:hover {
            box-shadow: 0 12px 32px 0 rgba(76,142,177,0.18);
            transform: translateY(-4px) scale(1.02);
        }
        .profile-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary);
            background: #fff;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(76,142,177,0.10);
        }
        .ojt-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.1rem;
            text-align: center;
        }
        .ojt-username {
            font-size: 1rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .ojt-info-list {
            width: 100%;
            margin-top: 0.5rem;
        }
        .ojt-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .ojt-info-label {
            font-size: 0.97rem;
            color: var(--primary-dark);
            font-weight: 600;
            min-width: 80px;
        }
        .ojt-info-value {
            font-size: 1.05rem;
            color: var(--dark);
            font-weight: 500;
            flex: 1;
        }
        .ojt-timein, .ojt-timeout {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(90deg, #4c8eb1 60%, #3cb2cc 100%);
            border-radius: 0.5rem;
            padding: 0.35rem 1rem;
            display: inline-block;
        }
        .ojt-email a {
            color: var(--primary);
            text-decoration: none;
            font-size: 1.01rem;
            word-break: break-all;
        }
        .ojt-email a:hover {
            text-decoration: underline;
        }
        .ojt-status {
            margin-top: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 0.35rem 1.1rem;
            border-radius: 50px;
            background: rgba(60,178,204,0.10);
            color: var(--primary-dark);
        }
        .ojt-status.completed {
            background: rgba(16,185,129,0.10);
            color: var(--success);
        }
        .ojt-status.ongoing {
            background: rgba(76,142,177,0.10);
            color: var(--primary);
        }
        @media (max-width: 991.98px) {
            .ojt-list {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 767.98px) {
            .main-header {
                font-size: 1.4rem;
            }
            .ojt-card {
                padding: 1.1rem 0.7rem;
                min-height: 0;
            }
            .profile-img {
                width: 56px;
                height: 56px;
            }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="container py-5" style="max-width: 1100px;">
        <div class="main-header">
            <i class="bi bi-person-check-fill me-2"></i>Active OJT Today
        </div>
        <?php if ($ojts->num_rows > 0): ?>
            <div class="ojt-list">
                <?php while ($ojt = $ojts->fetch_assoc()): ?>
                    <?php
                        $img = '../uploads/noimg.png';
                        if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                            $img = '../' . $ojt['profile_img'];
                        }
                        $alt = trim($ojt['fname'] . ' ' . $ojt['lname']);
                        $has_timeout = $ojt['time_out'] && $ojt['time_out'] !== '00:00:00';
                    ?>
                    <div class="ojt-card">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($alt) ?>" class="profile-img">
                        <div class="ojt-name"><?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></div>
                        <div class="ojt-username">@<?= htmlspecialchars($ojt['username']) ?></div>
                        <div class="ojt-info-list">
                            <div class="ojt-info-row">
                                <span class="ojt-info-label"><i class="bi bi-clock"></i> Time In:</span>
                                <span class="ojt-info-value ojt-timein">
                                    <?= $ojt['time_in'] ? date('g:i A', strtotime($ojt['time_in'])) : '<span class="text-muted">--</span>' ?>
                                </span>
                            </div>
                            <div class="ojt-info-row">
                                <span class="ojt-info-label"><i class="bi bi-clock-history"></i> Time Out:</span>
                                <span class="ojt-info-value ojt-timeout">
                                    <?php
                                        if ($has_timeout) {
                                            echo date('g:i A', strtotime($ojt['time_out']));
                                        } else {
                                            echo '<span class="text-muted">--</span>';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="ojt-info-row">
                                <span class="ojt-info-label"><i class="bi bi-chat-left-text"></i> Remarks:</span>
                                <span class="ojt-info-value">
                                    <?= htmlspecialchars($ojt['remarks'] ?? '--') ?>
                                </span>
                            </div>
                        </div>
                        <div class="ojt-status <?= $has_timeout ? 'completed' : 'ongoing' ?>">
                            <i class="bi bi-<?= $has_timeout ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
                            <?= $has_timeout ? 'Completed' : 'Ongoing' ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5">
                <i class="bi bi-info-circle me-2"></i>No OJT has clocked in today.
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>