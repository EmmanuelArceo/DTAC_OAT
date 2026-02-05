<?php

  if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
include 'db.php';
include 'nav.php';
// Redirect to login if not signed in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user info
$user_id = $_SESSION['user_id'];
$user_info = $oat->query("SELECT fname, lname, position FROM users WHERE id = $user_id")->fetch_assoc();
$full_name = ($user_info['fname'] ?? '') . ' ' . ($user_info['lname'] ?? '');
$position = $user_info['position'] ?? '';
$role = $_SESSION['role'] ?? 'ojt';

// Fetch required hours
$req = $oat->query("SELECT required_hours FROM ojt_requirements WHERE user_id = $user_id")->fetch_assoc();
$required_hours = (float)($req['required_hours'] ?? 0);

// Fetch default time in from site_settings
$settings = $oat->query("SELECT default_time_in FROM site_settings LIMIT 1")->fetch_assoc();
$default_time_in = $settings['default_time_in'] ?? '08:00:00';

// Fetch completed hours (only for valid time_out records) with policy adjustments
$total_completed = 0;
$records = $oat->query("
    SELECT time_in, time_out 
    FROM ojt_records 
    WHERE user_id = $user_id 
    AND time_out IS NOT NULL 
    AND time_out != '00:00:00' 
    AND time_out > time_in
");
while ($row = $records->fetch_assoc()) {
    $time_in = strtotime($row['time_in']);
    $time_out = strtotime($row['time_out']);
    $base_hours = ($time_out - $time_in) / 3600; // Base hours

    // Deduct 1 hour for lunch only if time_in <= 12:00:00 and time_out >= 13:00:00
    $lunch_start = strtotime(date('Y-m-d', $time_in) . ' 12:00:00');
    $lunch_end = strtotime(date('Y-m-d', $time_in) . ' 13:00:00');
    $adjusted_hours = $base_hours;
    if ($time_in <= $lunch_start && $time_out >= $lunch_end) {
        $adjusted_hours -= 1;
    }

    // Deduct 1 hour if late (time_in after default_time_in)
    if (date('H:i:s', $time_in) > $default_time_in) {
        $adjusted_hours -= 1;
    }

    // Round to whole hours
    $adjusted_hours = round($adjusted_hours);

    // Ensure non-negative
    $total_completed += max(0, $adjusted_hours);
}
$completed_hours = $total_completed;
$remaining_hours = max(0, $required_hours - $completed_hours);
$progress = $required_hours > 0 ? min(100, round(($completed_hours / $required_hours) * 100)) : 0;

// recent sessions (last 5)
$recent = $oat->query("
    SELECT date, time_in, time_out 
    FROM ojt_records 
    WHERE user_id = $user_id 
    ORDER BY date DESC, time_in DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OJT Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --accent: #3CB3CC;
            --accent-deep: #2aa0b3;
            --glass-bg: rgba(255,255,255,0.55);
            --glass-border: rgba(60,179,204,0.12);
            --muted: #6b7280;
        }
        *{box-sizing:border-box}
        body{
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            margin:0;
            min-height:100vh;
            background: linear-gradient(135deg, #f6fbfb 0%, #eef9fa 50%, #f9fcfd 100%);
            color:#0f172a;
            -webkit-font-smoothing:antialiased;
        }
        .wrap{
            max-width:1100px;
            margin:48px auto;
            padding:24px;
        }
        .glass{
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 30px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius:14px;
        }
        .header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            padding:20px;
        }
        .title{
            font-size:20px;
            font-weight:700;
            color:var(--accent-deep);
        }
        .subtitle{
            font-size:13px;
            color:var(--muted);
            margin-top:4px;
        }
        .stats{
            display:grid;
            grid-template-columns: repeat(3,1fr);
            gap:14px;
            padding:18px;
        }
        .stat{
            padding:18px;
            border-radius:12px;
            background:linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0.45));
            transition:transform .18s ease, box-shadow .18s ease;
            border:1px solid rgba(15,23,42,0.03);
        }
        .stat:hover{ transform:translateY(-6px); box-shadow:0 12px 30px rgba(15,23,42,0.08); }
        .stat .label{ font-size:12px; color:var(--muted); font-weight:600; }
        .stat .value{ font-size:28px; font-weight:800; color:var(--accent-deep); margin-top:6px;}
        .progress-wrap{
            margin-top:14px;
        }
        .progress {
            height:10px;
            background:rgba(15,23,42,0.06);
            border-radius:999px;
            overflow:hidden;
        }
        .progress-bar{
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent-deep) 100%);
            transition:width .6s cubic-bezier(.2,.9,.3,1);
        }
        .actions{
            display:flex;
            gap:12px;
            padding:18px;
            align-items:center;
        }
        .btn-accent{
            background:transparent;
            border:1px solid var(--accent);
            color:var(--accent-deep);
            padding:10px 14px;
            border-radius:10px;
            font-weight:700;
            transition:all .15s ease;
        }
        .btn-accent:hover{
            background:var(--accent);
            color:#fff;
            transform:translateY(-3px);
            box-shadow:0 8px 20px rgba(60,179,204,0.12);
            border-color:transparent;
        }
        .recent{
            margin-top:18px;
            padding:18px;
        }
        .recent table{ width:100%; border-collapse:collapse; }
        .recent th{ text-align:left; font-size:13px; color:var(--muted); padding:8px 10px; font-weight:700; }
        .recent td{ padding:10px; font-size:14px; color:#0f172a; border-top:1px solid rgba(15,23,42,0.04); }
        .avatar{
            width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent-deep));display:inline-block;
            box-shadow:0 6px 18px rgba(15,23,42,0.06);
        }
        @media (max-width: 900px){
            .stats{ grid-template-columns: repeat(1,1fr); }
            .header{ flex-direction:column; align-items:flex-start; gap:8px; padding:16px;}
            .actions{ padding:14px; width:100%; }
        }
    </style>
</head>
<body>
   
    <main class="wrap">
        <div class="glass">
            <div class="header">
                <div class="d-flex align-items-center gap-3">
                    <?php
                        // Fetch profile image for dashboard
                        $img = 'uploads/noimg.png';
                        $user_img = $oat->query("SELECT profile_img FROM users WHERE id = $user_id")->fetch_assoc();
                        if (!empty($user_img['profile_img']) && file_exists('' . $user_img['profile_img'])) {
                            $img = '' . $user_img['profile_img'];
                        }
                    ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="Profile" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);" />
                    <div class="d-flex flex-column align-items-start">
                        <div class="title"><?= htmlspecialchars($full_name ?: 'OJT') ?></div>
                        <div class="subtitle"><?= htmlspecialchars($position ?: $role) ?></div>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn-accent" onclick="location.href='dtr.php'">View DTR</button>
                    <button class="btn-accent" onclick="location.href='records.php'">OT Report</button>
                </div>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="label">Required Hours</div>
                    <div class="value"><?= number_format($required_hours,0) ?></div>
                    <div class="progress-wrap">
                        <div class="progress" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:<?= $required_hours>0 ? 100 : 0 ?>%"></div>
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:8px;">Total hours you must complete</div>
                    </div>
                </div>

                <div class="stat">
                    <div class="label">Completed</div>
                    <div class="value"><?= number_format($completed_hours,0) ?></div>
                    <div class="progress-wrap">
                        <div class="progress" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:<?= $progress ?>%"></div>
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:8px;"><?= $progress ?>% completed</div>
                    </div>
                </div>

                <div class="stat">
                    <div class="label">Remaining</div>
                    <div class="value"><?= number_format($remaining_hours,0) ?></div>
                    <div class="progress-wrap">
                        <div class="progress" aria-hidden="true">
                            <div class="progress-bar" role="progressbar" style="width:<?= max(0,100-$progress) ?>%; background: linear-gradient(90deg,#f3a683,#f7d794);"></div>
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:8px;">Keep going — you're getting there</div>
                    </div>
                </div>
            </div>

            <div class="recent">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-weight:700;color:var(--accent-deep)">Recent Sessions</div>
                    <div style="color:var(--muted);font-size:13px"><?= $recent ? $recent->num_rows : 0 ?> latest</div>
                </div>
                <?php if ($recent && $recent->num_rows > 0): ?>
                <table class="table-borderless">
                    <thead>
                        <tr>
                            <th style="width:30%;">Date</th>
                            <th style="width:23%;">Time In</th>
                            <th style="width:23%;">Time Out</th>
                            <th style="width:24%;">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($r = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['date']) ?></td>
                                <td><?= $r['time_in'] ? date('g:i A', strtotime($r['time_in'])) : '<span style="color:var(--muted)">--</span>' ?></td>
                                <td>
                                    <?php
                                        if ($r['time_out'] && $r['time_out'] !== '00:00:00') {
                                            echo date('g:i A', strtotime($r['time_out']));
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        if ($r['time_in'] && $r['time_out'] && $r['time_out'] !== '00:00:00') {
                                            $time_in = strtotime($r['time_in']);
                                            $time_out = strtotime($r['time_out']);
                                            $hours = ($time_out - $time_in) / 3600;

                                            // Deduct 1 hour ONLY if the interval overlaps with 12:00:00 to 13:00:00
                                            $lunch_start = strtotime(date('Y-m-d', $time_in) . ' 12:00:00');
                                            $lunch_end = strtotime(date('Y-m-d', $time_in) . ' 13:00:00');
                                            $overlaps_lunch = ($time_in < $lunch_end) && ($time_out > $lunch_start);
                                            if ($overlaps_lunch) {
                                                $hours -= 1;
                                            }

                                            // Deduct 1 hour ONLY if late (time_in after default_time_in)
                                            if (date('H:i:s', $time_in) > $default_time_in) {
                                                $hours -= 1;
                                            }

                                            echo max(0, round($hours)) . ' h';
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="padding:18px;color:var(--muted);">No recent sessions yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>