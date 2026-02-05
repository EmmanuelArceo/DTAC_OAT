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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff; /* changed to plain white background */
            min-height: 100vh;
            color: #14532d;
        }
        .glass {
            background: #ffffff; /* keep cards solid white on white bg */
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.08);
            /* removed heavy backdrop-filter for cleaner white look */
            border-radius: 1.5rem;
        
        }
        .dashboard-header {
            font-size: 2.7rem;
            font-weight: 800;
            color: #219150;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        .dashboard-sub {
            color: #388e3c;
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            border: none;
            border-radius: 1.25rem;
            background: linear-gradient(120deg, #e8f5e9 60%, #e0f2f1 100%);
            box-shadow: 0 2px 16px 0 rgba(22,101,52,0.08);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 8px 32px 0 rgba(22,101,52,0.16);
            transform: translateY(-2px) scale(1.03);
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #219150;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            color: #14532d;
        }
        .stat-label {
            font-size: 1.1rem;
            color: #388e3c;
            font-weight: 600;
            margin-top: 0.3rem;
        }
        .action-card {
            border: none;
            border-radius: 1.25rem;
            background: #fff;
            box-shadow: 0 2px 16px 0 rgba(22,101,52,0.08);
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s, transform 0.2s;
            text-decoration: none;
            min-height: 220px;
        }
        .action-card:hover {
            box-shadow: 0 8px 32px 0 rgba(22,101,52,0.16);
            transform: translateY(-2px) scale(1.03);
            text-decoration: none;
        }
        .action-icon {
            font-size: 2.2rem;
            color: #219150;
            margin-bottom: 0.7rem;
        }
        .action-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #14532d;
            margin-bottom: 0.2rem;
        }
        .action-desc {
            color: #388e3c;
            font-size: 0.97rem;
            text-align: center;
        }
        @media (max-width: 991.98px) {
            .dashboard-header { font-size: 2rem; }
            .stat-card, .action-card { padding: 1.2rem 0.7rem; }
        }
        @media (max-width: 767.98px) {
            .dashboard-header { font-size: 1.5rem; }
            .main-content { margin-left: 0 !important; }
        }
    </style>
</head>
<body>
  
    <div class="container main-content py-5" style="max-width: 1200px;">
        <!-- Header -->
        <div class="glass p-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                <div>
                    <div class="dashboard-header">Super Admin Dashboard</div>
                    <div class="dashboard-sub">Welcome, Super Admin! Here’s an overview of your OJT system.</div>
                </div>
            </div>
        </div>
        <!-- Stat Cards -->
        <div class="row g-4 mb-5">
            <?php
            $ojt_count = $oat->query("SELECT COUNT(*) as c FROM users WHERE role='ojt'")->fetch_assoc()['c'] ?? 0;
            $admin_count = $oat->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'] ?? 0;
            $active_today = $oat->query("SELECT COUNT(DISTINCT user_id) as c FROM ojt_records WHERE date = CURDATE()")->fetch_assoc()['c'] ?? 0;
            $pending = $oat->query("SELECT COUNT(*) as c FROM ojt_records WHERE time_out IS NULL AND date = CURDATE()")->fetch_assoc()['c'] ?? 0;
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-icon"><i class="bi bi-people-fill"></i></span>
                    <span class="stat-value"><?= $ojt_count ?></span>
                    <span class="stat-label">Total OJTs</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-icon"><i class="bi bi-person-badge-fill"></i></span>
                    <span class="stat-value"><?= $admin_count ?></span>
                    <span class="stat-label">Total Admins</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-icon"><i class="bi bi-person-check-fill"></i></span>
                    <span class="stat-value"><?= $active_today ?></span>
                    <span class="stat-label">Active Today</span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <span class="stat-icon"><i class="bi bi-clock-history"></i></span>
                    <span class="stat-value"><?= $pending ?></span>
                    <span class="stat-label">Pending Time Out</span>
                </div>
            </div>
        </div>
        <!-- Main Actions -->
        <div class="row g-4 mb-5">
            <div class="col-12 col-md-4">
                <a href="manageojt.php" class="action-card h-100">
                    <span class="action-icon"><i class="bi bi-people-fill"></i></span>
                    <span class="action-title">Manage OJTs</span>
                    <span class="action-desc">View and manage all OJT users.</span>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="manage_admins.php" class="action-card h-100">
                    <span class="action-icon"><i class="bi bi-person-badge-fill"></i></span>
                    <span class="action-title">Manage Admins</span>
                    <span class="action-desc">Add, edit, or remove admin accounts.</span>
                </a>
            </div>
            <div class="col-12 col-md-4">
                <a href="site_settings.php" class="action-card h-100">
                    <span class="action-icon"><i class="bi bi-gear-fill"></i></span>
                    <span class="action-title">Site Settings</span>
                    <span class="action-desc">Configure system-wide settings.</span>
                </a>
            </div>
        </div>
        <!-- Reports & QR Generator -->
        <div class="row g-4 mb-5">
            <div class="col-12 col-md-6">
                <a href="reports.php" class="action-card h-100">
                    <span class="action-icon"><i class="bi bi-bar-chart-fill"></i></span>
                    <span class="action-title">Reports</span>
                    <span class="action-desc">View attendance and system reports.</span>
                </a>
            </div>
            <div class="col-12 col-md-6">
                <a href="qr_generator.php" class="action-card h-100">
                    <span class="action-icon"><i class="bi bi-qr-code-scan"></i></span>
                    <span class="action-title">QR Generator</span>
                    <span class="action-desc">Generate QR codes for attendance.</span>
                </a>
            </div>
        </div>

        <!-- Active OJTs Today -->
        <?php
        $active_ojts = $oat->query("
            SELECT u.id, u.username, u.fname, u.lname, u.email, u.profile_img, r.time_in, r.time_out
            FROM users u
            JOIN ojt_records r ON u.id = r.user_id
            WHERE u.role = 'ojt' AND r.date = CURDATE() AND r.time_in IS NOT NULL AND r.time_in != ''
            ORDER BY r.time_in ASC
        ");
        ?>
        <div class="glass p-4 mb-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0" style="color:#14532d;font-weight:700;">Active OJTs Today</h5>
                <small class="text-muted"><?= $active_ojts ? $active_ojts->num_rows : 0 ?> active</small>
            </div>

            <?php if ($active_ojts && $active_ojts->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr class="text-muted">
                                <th scope="col">OJT</th>
                                <th scope="col">Username</th>
                                <th scope="col">Time In</th>
                                <th scope="col">Time Out</th>
                                <th scope="col">DTR Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ojt = $active_ojts->fetch_assoc()): ?>
                                <?php
                                    $img = '../uploads/noimg.png';
                                    if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                                        $img = '../' . $ojt['profile_img'];
                                    }
                                    $time_in = $ojt['time_in'] ? date('g:i A', strtotime($ojt['time_in'])) : '<span class="text-muted">--</span>';
                                    if ($ojt['time_out'] && $ojt['time_out'] !== '00:00:00') {
                                        $time_out = date('g:i A', strtotime($ojt['time_out']));
                                    } else {
                                        $time_out = '<span class="text-muted">--</span>';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars(trim($ojt['fname'].' '.$ojt['lname'])) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #e6f4ea;">
                                            <div>
                                                <div style="font-weight:700;color:#14532d;"><?= htmlspecialchars($ojt['fname'].' '.$ojt['lname']) ?></div>
                                                <div class="text-muted" style="font-size:0.9rem;"><?= htmlspecialchars($ojt['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted">@<?= htmlspecialchars($ojt['username']) ?></td>
                                    <td style="color:#14532d;font-weight:600;"><?= $time_in ?></td>
                                    <td style="color:#14532d;font-weight:600;"><?= $time_out ?></td>
                                    <td>
                                        <a href="dtrview.php?user_id=<?= urlencode($ojt['id']) ?>" class="btn btn-sm btn-outline-success">
                                            View DTR
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-3">No OJT has clocked in today.</div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Bootstrap 5 JS and icons -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</body>
</html>