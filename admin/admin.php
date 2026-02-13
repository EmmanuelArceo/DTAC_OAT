<?php

include '../db.php';
include 'nav.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Allow both super_admin and admin to access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int)($_SESSION['user_id'] ?? 0);

// Get statistics
if ($_SESSION['role'] === 'super_admin') {
    $ojt_count = $oat->query("SELECT COUNT(*) as c FROM users WHERE role='ojt'")->fetch_assoc()['c'] ?? 0;
    $admin_count = $oat->query("SELECT COUNT(*) as c FROM users WHERE role='admin'")->fetch_assoc()['c'] ?? 0;
    $active_today = $oat->query("SELECT COUNT(DISTINCT user_id) as c FROM ojt_records WHERE date = CURDATE()")->fetch_assoc()['c'] ?? 0;
    $pending = $oat->query("SELECT COUNT(*) as c FROM ojt_records WHERE date = CURDATE() AND (time_out IS NULL OR time_out = '' OR time_out = '00:00:00') AND time_in IS NOT NULL AND time_in != ''")->fetch_assoc()['c'] ?? 0;
} else {
    // Only OJTs under this admin or without adviser
    $ojt_count = $oat->query("SELECT COUNT(*) as c FROM users WHERE role='ojt' AND (adviser_id = $admin_id OR adviser_id IS NULL OR adviser_id = '')")->fetch_assoc()['c'] ?? 0;
    $admin_count = 1; // Only self
    $active_today = $oat->query("
        SELECT COUNT(DISTINCT r.user_id) as c
        FROM ojt_records r
        JOIN users u ON r.user_id = u.id
        WHERE u.role='ojt'
          AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
          AND r.date = CURDATE()
    ")->fetch_assoc()['c'] ?? 0;
    $pending = $oat->query("
        SELECT COUNT(*) as c
        FROM ojt_records r
        JOIN users u ON r.user_id = u.id
        WHERE u.role='ojt'
          AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
          AND r.date = CURDATE()
          AND (r.time_out IS NULL OR r.time_out = '' OR r.time_out = '00:00:00')
          AND r.time_in IS NOT NULL AND r.time_in != ''
    ")->fetch_assoc()['c'] ?? 0;
}

// Get weekly attendance trend
if ($_SESSION['role'] === 'super_admin') {
    $weekly_data = $oat->query("
        SELECT DATE(date) as day, COUNT(DISTINCT user_id) as count 
        FROM ojt_records 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date)
        ORDER BY day ASC
    ");
} else {
    $weekly_data = $oat->query("
        SELECT DATE(r.date) as day, COUNT(DISTINCT r.user_id) as count
        FROM ojt_records r
        JOIN users u ON r.user_id = u.id
        WHERE u.role='ojt'
          AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
          AND r.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(r.date)
        ORDER BY day ASC
    ");
}
$weekly_labels = [];
$weekly_counts = [];
while($row = $weekly_data->fetch_assoc()) {
    $weekly_labels[] = date('M d', strtotime($row['day']));
    $weekly_counts[] = $row['count'];
}

// Get recent activities
if ($_SESSION['role'] === 'super_admin') {
    $recent_activities = $oat->query("
        SELECT u.fname, u.mname, u.lname, u.profile_img, r.time_in, r.date, 'time_in' as action
        FROM ojt_records r
        JOIN users u ON r.user_id = u.id
        WHERE r.time_in IS NOT NULL
        ORDER BY r.date DESC, r.time_in DESC
        LIMIT 5
    ");
} else {
    $recent_activities = $oat->query("
        SELECT u.fname, u.mname, u.lname, u.profile_img, r.time_in, r.date, 'time_in' as action
        FROM ojt_records r
        JOIN users u ON r.user_id = u.id
        WHERE r.time_in IS NOT NULL
          AND u.role='ojt'
          AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
        ORDER BY r.date DESC, r.time_in DESC
        LIMIT 5
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .welcome-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color), transparent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.primary { --card-color: var(--primary); }
        .stat-card.success { --card-color: var(--secondary); }
        .stat-card.warning { --card-color: var(--warning); }
        .stat-card.danger { --card-color: var(--danger); }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .stat-card.primary .stat-icon {
            background: rgba(76, 142, 177, 0.1);
            color: var(--primary);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(76, 142, 177, 0.05), rgba(60, 178, 204, 0.1));
            color: var(--primary);
        }

        .action-btn i {
            font-size: 2rem;
            color: var(--primary);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(76, 142, 177, 0.1);
            color: var(--primary);
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }

        .stat-change.positive {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }

        .stat-change.negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .content-grid {
            display: flex;
            flex-direction: row;
            gap: 2rem;
            /* Fix: make columns take full width */
            width: 100%;
        }
        .content-grid > div {
            flex: 1 1 0;
            min-width: 0;
        }

        /* Responsive: Stack columns on mobile */
        @media (max-width: 991.98px) {
            .dashboard-container {
                padding: 0.5rem;
            }
            .dashboard-header {
                padding: 1rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .content-grid {
                flex-direction: column;
                gap: 1.2rem;
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-header h1 {
                font-size: 1.2rem;
            }
            .stat-value {
                font-size: 1.3rem;
            }
            .card-custom {
                padding: 1rem;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
            .ojt-table {
                font-size: 0.85rem;
            }
            .user-avatar {
                width: 32px;
                height: 32px;
            }
            .activity-icon {
                width: 28px;
                height: 28px;
                font-size: 1.1rem;
            }
            .card-header-custom h3 {
                font-size: 1rem;
            }
            .dashboard-header {
                padding: 0.7rem;
            }
        }

        /* Card Component */
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

        .card-header-custom .badge {
            font-weight: 600;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }

        .ojt-table {
            width: 100%;
            margin-top: 1rem;
        }

        .ojt-table thead th {
            background: var(--light);
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            padding: 1rem;
            border: none;
        }

        .ojt-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .ojt-table tbody tr:hover {
            background: var(--light);
        }

        .ojt-table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border);
        }

        .user-details h6 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .user-details small {
            color: #64748b;
            font-size: 0.875rem;
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

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 340px; /* Show max 4, then scroll */
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: background 0.2s ease;
            min-height: 70px;
        }

        .activity-item:hover {
            background: var(--light);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h5 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>
                <i class="bi bi-speedometer2 me-2"></i>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                    Super Admin Dashboard
                <?php else: ?>
                    OJT Supervisor Dashboard
                <?php endif; ?>
            </h1>
            <p>Monitor and manage your OJT system efficiently</p>
            <span class="welcome-badge">
                <i class="bi bi-person-circle me-1"></i>
                Welcome,
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                    Super Admin!
                <?php else: ?>
                    OJT Supervisor!
                <?php endif; ?>
            </span>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-value"><?= $ojt_count ?></div>
                <div class="stat-label">Total OJTs</div>
                <span class="stat-change positive">
                    <i class="bi bi-arrow-up"></i> Active Users
                </span>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div class="stat-value"><?= $active_today ?></div>
                <div class="stat-label">Active Today</div>
                <span class="stat-change positive">
                    <i class="bi bi-clock"></i> Real-time
                </span>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-value"><?= $pending ?></div>
                <div class="stat-label">Pending Time-Out</div>
                <span class="stat-change <?= $pending > 0 ? 'negative' : 'positive' ?>">
                    <i class="bi bi-<?= $pending > 0 ? 'check-circle' : 'check-circle' ?>"></i> 
                    <?= $pending > 0 ? 'Active OJT' : 'All Clear' ?>
                </span>
            </div>

        
        <?php if ($_SESSION['role'] === 'super_admin'): ?>         <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="stat-value"><?= $admin_count ?></div>
                <div class="stat-label">Total Admins</div>   
                <span class="stat-change positive">
                    <i class="bi bi-shield-check"></i> System Users
                </span>
            </div> <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card-custom">
            <div class="card-header-custom">
                <h3><i class="bi bi-lightning-charge-fill me-2"></i>Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="manageojt.php" class="action-btn">
                    <i class="bi bi-people-fill"></i>
                    <span>Manage OJTs</span>
                </a>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="manage_admins.php" class="action-btn">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Manage Admins</span>
                </a>
                <?php endif; ?>
                <a href="otreports.php" class="action-btn">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>View Reports</span>
                </a>
                <a href="qr_generator.php" class="action-btn">
                    <i class="bi bi-qr-code-scan"></i>
                    <span>QR Generator</span>
                </a>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="site_settings.php" class="action-btn">
                    <i class="bi bi-gear-fill"></i>
                    <span>Site Settings</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column - Active OJTs -->
            <div>
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-person-check me-2"></i>Active OJTs Today</h3>
                        <span class="badge bg-success"><?= $active_today ?> Active</span>
                    </div>
                    <?php
                    // Fetch active OJTs for today
                    if ($_SESSION['role'] === 'super_admin') {
                        $active_ojts = $oat->query("
                            SELECT u.id, u.username, u.fname, u.mname, u.lname, u.email, u.profile_img, 
                               r.time_in, r.time_out
                            FROM users u
                            JOIN ojt_records r ON u.id = r.user_id
                            WHERE u.role = 'ojt' AND r.date = CURDATE() 
                            AND r.time_in IS NOT NULL AND r.time_in != ''
                            ORDER BY r.time_in ASC
                        ");
                        $active_ojt_title = "Active OJTs Today";
                        $active_ojt_empty = "No OJT has clocked in today.";
                    } else {
                        $active_ojts = $oat->query("
                            SELECT u.id, u.username, u.fname, u.mname, u.lname, u.email, u.profile_img, 
                               r.time_in, r.time_out
                            FROM users u
                            JOIN ojt_records r ON u.id = r.user_id
                            WHERE u.role = 'ojt'
                              AND u.adviser_id = $admin_id
                              AND r.date = CURDATE()
                              AND r.time_in IS NOT NULL AND r.time_in != ''
                            ORDER BY r.time_in ASC
                        ");
                        $active_ojt_title = "My Active OJTs Today";
                        $active_ojt_empty = "No OJT active under you today.";
                    }
                    ?>
                    <?php if ($active_ojts && $active_ojts->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="ojt-table">
                                <thead>
                                    <tr>
                                        <th>OJT User</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($ojt = $active_ojts->fetch_assoc()): ?>
                                        <?php
                                            $img = '../uploads/noimg.png';
                                            if (!empty($ojt['profile_img']) && file_exists('../' . $ojt['profile_img'])) {
                                                $img = '../' . $ojt['profile_img'];
                                            }
                                            $time_in = date('g:i A', strtotime($ojt['time_in']));
                                            $has_timeout = $ojt['time_out'] && $ojt['time_out'] !== '00:00:00';
                                            $time_out = $has_timeout ? date('g:i A', strtotime($ojt['time_out'])) : '--';
                                            // Show: Firstname M. Lastname (if mname exists)
                                            $mname = trim($ojt['mname']);
                                            $middle = $mname ? strtoupper($mname[0]) . '.' : '';
                                            $fullname = htmlspecialchars($ojt['fname'] . ' ' . ($middle ? $middle . ' ' : '') . $ojt['lname']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <img src="<?= htmlspecialchars($img) ?>" 
                                                         alt="<?= $fullname ?>" 
                                                         class="user-avatar">
                                                    <div class="user-details">
                                                        <h6><?= $fullname ?></h6>
                                                        <small>@<?= htmlspecialchars($ojt['username']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong><?= $time_in ?></strong></td>
                                            <td><strong><?= $time_out ?></strong></td>
                                            <td>
                                                <span class="status-badge <?= $has_timeout ? 'pending' : 'active' ?>">
                                                    <span class="status-indicator"></span>
                                                    <?= $has_timeout ? 'Completed' : 'Ongoing' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="dtrview.php?user_id=<?= $ojt['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-file-text me-1"></i> View DTR
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-person-x"></i>
                            <h5><?= $active_ojt_empty ?></h5>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Weekly Attendance Chart -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-graph-up me-2"></i>Weekly Attendance Trend</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Column - Recent Activity -->
            <div>
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-activity me-2"></i>Recent Activity</h3>
                        <span class="badge bg-primary">Live</span>
                    </div>

                    <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                        <ul class="activity-list">
                            <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                <?php
                                    $img = '../uploads/noimg.png';
                                    if (!empty($activity['profile_img']) && file_exists('../' . $activity['profile_img'])) {
                                        $img = '../' . $activity['profile_img'];
                                    }
                                    $time_ago = date('g:i A', strtotime($activity['time_in']));
                                    $date_display = date('M d', strtotime($activity['date']));
                                    // Show: Firstname M. Lastname (if mname exists)
                                    $mname = trim($activity['mname']);
                                    $middle = $mname ? strtoupper($mname[0]) . '.' : '';
                                    $fullname = htmlspecialchars($activity['fname'] . ' ' . ($middle ? $middle . ' ' : '') . $activity['lname']);
                                ?>
                                <li class="activity-item">
                                    <img src="<?= htmlspecialchars($img) ?>" alt="" class="user-avatar">
                                    <div class="activity-content">
                                        <h6><?= $fullname ?> clocked in</h6>
                                        <small><i class="bi bi-clock me-1"></i><?= $time_ago ?> • <?= $date_display ?></small>
                                    </div>
                                    <div class="activity-icon">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <div class="text-center mt-3">
                            <a href="activity.php" class="btn btn-sm btn-outline-primary">
                                View All Activities <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-activity"></i>
                            <h5>No Recent Activity</h5>
                            <p>Activity will appear here once OJTs start clocking in.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- System Info Card -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <h3><i class="bi bi-info-circle me-2"></i>System Information</h3>
                    </div>
                    <div style="line-height: 2;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted"><i class="bi bi-calendar3 me-2"></i>Today's Date:</span>
                            <strong><?= date('F d, Y') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted"><i class="bi bi-clock me-2"></i>Current Time:</span>
                            <strong id="currentTime"><?= date('g:i:s A') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted"><i class="bi bi-check-circle me-2"></i>System Status:</span>
                            <span class="badge bg-success">Operational</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-server me-2"></i>Database:</span>
                            <span class="badge bg-success">Connected</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        setInterval(updateTime, 1000);

        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($weekly_labels) ?>,
                datasets: [{
                    label: 'Daily Attendance',
                    data: <?= json_encode($weekly_counts) ?>,
                    borderColor: '#4c8eb1',
                    backgroundColor: 'rgba(76, 142, 177, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#3cb2cc',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#1e40af',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            color: '#e2e8f0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>