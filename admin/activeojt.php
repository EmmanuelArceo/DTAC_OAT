<?php


include '../db.php';
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

// Get today's date
$today = date('Y-m-d');

// Fetch all OJTs who have time_in for today
$ojts = $oat->query("
    SELECT u.id, u.username, u.fname, u.lname, u.email, u.profile_img, r.time_in, r.time_out
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
    <style>
        body {
            background: linear-gradient(135deg, #e0f2f1 0%, #f1f8e9 100%);
            min-height: 100vh;
        }
        .main-header {
            font-size: 2.5rem;
            font-weight: 800;
            color: #219150;
            letter-spacing: 1px;
            margin-bottom: 2rem;
            text-align: center;
        }
        .ojt-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        .ojt-card {
            background: #fff;
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px 0 rgba(22,101,52,0.10);
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            transition: box-shadow 0.2s, transform 0.2s;
            border: none;
            position: relative;
            gap: 2rem;
        }
        .ojt-card:hover {
            box-shadow: 0 8px 32px 0 rgba(22,101,52,0.18);
            transform: translateY(-4px) scale(1.02);
        }
        .profile-img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #43a047;
            background: #fff;
            margin-bottom: 0;
            box-shadow: 0 2px 8px rgba(22,101,52,0.10);
            flex-shrink: 0;
        }
        .ojt-details {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 2.5rem;
            flex: 1;
            width: 100%;
        }
        .ojt-main-info {
            display: flex;
            flex-direction: column;
            min-width: 180px;
            max-width: 220px;
        }
        .ojt-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: #14532d;
            margin-bottom: 0.1rem;
        }
        .ojt-username {
            font-size: 1rem;
            color: #388e3c;
            margin-bottom: 0.5rem;
        }
        .ojt-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0;
        }
        .ojt-info-label {
            font-size: 0.97rem;
            color: #388e3c;
            font-weight: 600;
            min-width: 70px;
        }
        .ojt-info-value {
            font-size: 1.05rem;
            color: #14532d;
            font-weight: 500;
            flex: 1;
        }
        .ojt-timein {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
            border-radius: 0.5rem;
            padding: 0.35rem 1rem;
            display: inline-block;
        }
        .ojt-timeout {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
            border-radius: 0.5rem;
            padding: 0.35rem 1rem;
            display: inline-block;
        }
        .ojt-email a {
            color: #219150;
            text-decoration: none;
            font-size: 1.01rem;
            word-break: break-all;
        }
        .ojt-email a:hover {
            text-decoration: underline;
        }
        @media (max-width: 991.98px) {
            .ojt-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.7rem;
            }
            .ojt-main-info {
                max-width: 100%;
            }
        }
        @media (max-width: 767.98px) {
            .main-header {
                font-size: 2rem;
            }
            .ojt-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 1.25rem 0.75rem;
                gap: 1rem;
            }
            .profile-img {
                width: 60px;
                height: 60px;
            }
            .ojt-details {
                flex-direction: column;
                gap: 0.7rem;
            }
            .ojt-main-info {
                max-width: 100%;
            }
        }
        /* Add to your <style> section if you want a bit more space */
      
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="container py-5" style="max-width: 1100px;">
        <div class="main-header">Active OJT Today</div>
        <?php if ($ojts->num_rows > 0): ?>
            <div class="ojt-list">
                <?php while ($ojt = $ojts->fetch_assoc()): ?>
                    <?php
                        $img = '../uploads/noimg.png';
                        if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                            $img = '../' . $ojt['profile_img'];
                        }
                        $alt = trim($ojt['fname'] . ' ' . $ojt['lname']);
                    ?>
                    <div class="ojt-card">
                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($alt) ?>" class="profile-img">
                        <div class="ojt-details">
                            <div class="ojt-main-info">
                                <div class="ojt-name"><?= htmlspecialchars($ojt['fname'] . ' ' . $ojt['lname']) ?></div>
                                <div class="ojt-username">@<?= htmlspecialchars($ojt['username']) ?></div>
                            </div>
                            <div class="ojt-info-row flex-column align-items-start" style="gap:0;">
                                <span class="ojt-info-label mb-1"><i class="bi bi-clock"></i> Time In:</span>
                                <span class="ojt-info-value ojt-timein">
                                    <?= $ojt['time_in'] ? date('g:i A', strtotime($ojt['time_in'])) : '<span class="text-muted">--</span>' ?>
                                </span>
                            </div>
                            <div class="ojt-info-row flex-column align-items-start" style="gap:0;">
                                <span class="ojt-info-label mb-1"><i class="bi bi-clock-history"></i> Time Out:</span>
                                <span class="ojt-info-value ojt-timein">
                                    <?php
                                        if ($ojt['time_out'] && $ojt['time_out'] !== '00:00:00') {
                                            echo date('g:i A', strtotime($ojt['time_out']));
                                        } else {
                                            echo '<span class="text-muted">--</span>';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="ojt-info-row flex-column align-items-start" style="gap:0;">
                                <span class="ojt-info-label mb-1"><i class="bi bi-envelope"></i> Email:</span>
                                <span class="ojt-info-value">
                                    <a href="mailto:<?= htmlspecialchars($ojt['email']) ?>">
                                        <?= htmlspecialchars($ojt['email']) ?>
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5">
                No OJT has clocked in today.
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>