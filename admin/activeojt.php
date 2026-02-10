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
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .main-header {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 1px;
            margin-bottom: 2.5rem;
            text-align: center;
            text-shadow: 0 2px 4px rgba(76,142,177,0.2);
        }
        .ojt-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        .ojt-card {
            background: rgba(255,255,255,0.95);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(60,178,204,0.15);
            backdrop-filter: blur(10px) saturate(120%);
            padding: 2rem 2rem;
            display: flex;
            flex-direction: row;
            align-items: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }
        .ojt-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            border-radius: 1.5rem 1.5rem 0 0;
        }
        .ojt-card:hover {
            box-shadow: 0 16px 48px 0 rgba(76,142,177,0.25);
            transform: translateY(-8px) scale(1.02);
            background: rgba(255,255,255,0.98);
        }
        .profile-section {
            flex-shrink: 0;
            margin-right: 2rem;
            text-align: center;
            position: relative;
        }
        .profile-img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid var(--primary);
            background: #fff;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 12px rgba(76,142,177,0.2);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .ojt-card:hover .profile-img {
            border-color: var(--primary-dark);
            box-shadow: 0 6px 16px rgba(76,142,177,0.3);
        }
        .ojt-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.2rem;
        }
        .ojt-username {
            font-size: 1.1rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .info-section {
            flex: 1;
            display: flex;
            flex-direction: row;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: space-around;
        }
        .ojt-info-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            min-width: 140px;
            padding: 1rem;
            border-radius: 1rem;
            background: rgba(76,142,177,0.05);
            transition: background 0.3s, transform 0.3s;
        }
        .ojt-card:hover .ojt-info-item {
            background: rgba(76,142,177,0.1);
            transform: translateY(-2px);
        }
        .ojt-info-label {
            font-size: 0.95rem;
            color: var(--primary-dark);
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .ojt-info-value {
            font-size: 1.1rem;
            color: var(--dark);
            font-weight: 500;
            text-align: center;
        }
        .ojt-timein, .ojt-timeout {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #4c8eb1, #3cb2cc);
            border-radius: 0.75rem;
            padding: 0.4rem 1rem;
            display: inline-block;
            box-shadow: 0 2px 8px rgba(76,142,177,0.2);
            transition: box-shadow 0.3s;
        }
        .ojt-timeout {
            background: linear-gradient(135deg, #10b981, #3cb2cc);
        }
        .ojt-card:hover .ojt-timein, .ojt-card:hover .ojt-timeout {
            box-shadow: 0 4px 12px rgba(76,142,177,0.3);
        }
        .ojt-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            padding: 0.4rem 1.2rem;
            border-radius: 50px;
            background: rgba(60,178,204,0.15);
            color: var(--primary-dark);
            box-shadow: 0 2px 8px rgba(60,178,204,0.1);
            transition: background 0.3s, box-shadow 0.3s;
        }
        .ojt-status.completed {
            background: rgba(16,185,129,0.15);
            color: var(--success);
        }
        .ojt-status.ongoing {
            background: rgba(245,158,11,0.15);
            color: var(--warning);
        }
        .ojt-card:hover .ojt-status {
            box-shadow: 0 4px 12px rgba(60,178,204,0.2);
        }
        @media (max-width: 991.98px) {
            .ojt-list {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 767.98px) {
            .main-header {
                font-size: 1.8rem;
                margin-bottom: 2rem;
            }
            .ojt-card {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem 1rem;
                gap: 1rem;
            }
            .profile-section {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            .profile-img {
                width: 70px;
                height: 70px;
            }
            .info-section {
                flex-direction: column;
                gap: 1rem;
                justify-content: center;
            }
            .ojt-info-item {
                flex-direction: row;
                justify-content: space-between;
                min-width: unset;
                width: 100%;
                padding: 0.75rem 1rem;
            }
            .ojt-info-label {
                min-width: 100px;
                text-align: left;
                font-size: 0.9rem;
            }
            .ojt-info-value {
                text-align: right;
                font-size: 1rem;
            }
            .ojt-timein, .ojt-timeout {
                font-size: 1rem;
                padding: 0.3rem 0.8rem;
            }
            .ojt-status {
                font-size: 0.95rem;
                padding: 0.3rem 1rem;
            }
        }
        @media (max-width: 480px) {
            .main-header {
                font-size: 1.5rem;
            }
            .ojt-card {
                padding: 1rem 0.75rem;
            }
            .profile-img {
                width: 60px;
                height: 60px;
            }
            .ojt-name {
                font-size: 1.2rem;
            }
            .ojt-username {
                font-size: 1rem;
            }
            .info-section {
                gap: 0.75rem;
            }
            .ojt-info-item {
                padding: 0.5rem 0.75rem;
            }
            .ojt-info-label {
                font-size: 0.85rem;
            }
            .ojt-info-value {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5" style="max-width: 1200px;">
        <div class="main-header">
            <i class="bi bi-person-check-fill me-3"></i>Active OJT Today
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
                        <div class="profile-section">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($alt) ?>" class="profile-img">
                            <div class="ojt-name"><?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></div>
                            <div class="ojt-username">@<?= htmlspecialchars($ojt['username']) ?></div>
                        </div>
                        <div class="info-section">
                            <div class="ojt-info-item">
                                <span class="ojt-info-label"><i class="bi bi-clock"></i> Time In</span>
                                <span class="ojt-info-value ojt-timein">
                                    <?= $ojt['time_in'] ? date('g:i A', strtotime($ojt['time_in'])) : '<span class="text-muted">--</span>' ?>
                                </span>
                            </div>
                            <div class="ojt-info-item">
                                <span class="ojt-info-label"><i class="bi bi-clock-history"></i> Time Out</span>
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
                            <div class="ojt-info-item">
                                <span class="ojt-info-label"><i class="bi bi-chat-left-text"></i> Remarks</span>
                                <span class="ojt-info-value">
                                    <?= htmlspecialchars($ojt['remarks'] ?? '--') ?>
                                </span>
                            </div>
                            <div class="ojt-info-item">
                                <span class="ojt-info-label"><i class="bi bi-info-circle"></i> Status</span>
                                <span class="ojt-info-value">
                                    <span class="ojt-status <?= $has_timeout ? 'completed' : 'ongoing' ?>">
                                        <i class="bi bi-<?= $has_timeout ? 'check-circle-fill' : 'hourglass-split' ?>"></i>
                                        <?= $has_timeout ? 'Completed' : 'Ongoing' ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5" style="border-radius: 1rem; box-shadow: var(--shadow-lg);">
                <i class="bi bi-info-circle me-2"></i>No OJT has clocked in today.
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>