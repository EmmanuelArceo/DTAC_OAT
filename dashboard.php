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

// Fetch user info using prepared statement
$user_id = $_SESSION['user_id'];
$stmt = $oat->prepare("SELECT fname, lname, mname, position, profile_img FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$full_name = ($user_info['fname'] ?? '') . ' ' .
    (isset($user_info['mname']) && $user_info['mname'] ? strtoupper(substr($user_info['mname'], 0, 1)) . '. ' : '') .
    ($user_info['lname'] ?? '');
$position = $user_info['position'] ?? '';
$role = $_SESSION['role'] ?? 'ojt';

// Fix profile image path
$img = 'uploads/noimg.png';
if (!empty($user_info['profile_img']) && file_exists($user_info['profile_img'])) {
    $img = $user_info['profile_img'] . '?t=' . time();
}

// Fetch required hours using prepared statement
$stmt = $oat->prepare("SELECT required_hours FROM ojt_requirements WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$required_hours = (float)($req['required_hours'] ?? 0);

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
$stmt->bind_param("i", $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if ($group) {
    $user_policy_time_in = $group['time_in'];
    $user_policy_time_out = $group['time_out'];
    $user_lunch_start = $group['lunch_start'] ?: $default_lunch_start;
    $user_lunch_end = $group['lunch_end'] ?: $default_lunch_end;
}

// Fetch completed hours (only for valid time_out records) with policy adjustments
$total_completed = 0;
$stmt = $oat->prepare("SELECT time_in, time_out, time_in_policy, time_out_policy, ot_hours FROM ojt_records WHERE user_id = ? AND time_out IS NOT NULL AND time_out != '00:00:00' AND time_out > time_in");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$records = $stmt->get_result();
while ($row = $records->fetch_assoc()) {
    $result = calculate_session_hours($row, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $user_id, $oat);
    $total_completed += $result['regular'] + $result['ot'];
}
$completed_hours = floor($total_completed);
$remaining_hours = max(0, $required_hours - $completed_hours);
$progress = $required_hours > 0 ? min(100, round(($completed_hours / $required_hours) * 100)) : 0;

// recent sessions (last 5) using prepared statement
$stmt = $oat->prepare("SELECT date, time_in, time_out, ot_hours, time_in_policy FROM ojt_records WHERE user_id = ? ORDER BY date DESC, time_in DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent = $stmt->get_result();
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
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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
            transition:box-shadow .18s ease; /* Removed transform for smoother scrolling */
            border:1px solid rgba(15,23,42,0.03);
        }
        .stat:hover{ 
            box-shadow:0 12px 30px rgba(15,23,42,0.08); /* Kept shadow, removed translateY */
        }
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
            justify-content: center;
        }
        .btn-accent{
            background:transparent;
            border:1px solid var(--accent);
            color:var(--accent-deep);
            padding:10px 14px;
            border-radius:10px;
            font-weight:700;
            transition:all .15s ease;
            text-decoration: none;
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
        .profile-float {
            position: absolute;
            left: 50%;
            top:7%;
            transform: translate(-50%, -40%); /* 50% overlap */
            z-index: 10;
            width: 150px;
            height: 150px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .glass-frosted {
            background: rgba(255,255,255,0.55);
            border: 1px solid rgba(60,179,204,0.18);
            box-shadow: 0 16px 40px rgba(15,23,42,0.10);
            /* Removed backdrop-filter to eliminate frosted glass effect */
            border-radius: 18px;
            position: relative;
        }
        @media (max-width: 600px) {
            .profile-float { width: 90px; height: 90px; }
        }
    </style>
</head>
<body>
   
    <main class="wrap" style="position:relative;">
        <!-- Floating Profile Image, centered and overlapping glass box -->
        <div class="profile-float">
            <img src="<?= htmlspecialchars($img) ?>" alt="Profile"
                style="width:160px;height:160px;border-radius:50%;object-fit:cover;border:5px solid #3CB3CC;box-shadow:0 8px 32px rgba(60,179,204,0.18);background:rgba(255,255,255,0.7);" />
        </div>
        <div class="glass glass-frosted" style="margin-top:60px; padding-top:80px;">
            <div class="header justify-content-center" style="flex-direction:column; align-items:center; text-align:center;">
                <div class="title" style="font-size:2rem;"><?= htmlspecialchars($full_name ?: 'OJT') ?></div>
                <?php if ($position): ?>
                    <div class="subtitle" style="font-weight:600; font-size:1.1rem; margin-top:6px;"><?= htmlspecialchars($position) ?></div>
                <?php endif; ?>
              
            </div>
            <div class="actions" data-aos="fade-up" data-aos-delay="100">
                            <a href="dtr.php" class="btn-accent">View DTR</a>
                            <a href="otreport.php" class="btn-accent">OT Report</a>
                        </div>
            <div class="stats" data-aos="fade-up" data-aos-delay="200">
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

            

            <div class="recent" >
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div style="font-weight:700;color:var(--accent-deep)">Recent Sessions</div>
                    <div style="color:var(--muted);font-size:13px"><?= $recent ? $recent->num_rows : 0 ?> latest</div>
                </div>
                <?php if ($recent && $recent->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                <table class="table-borderless">
                    <thead>
                        <tr>
                            <th style="width:20%;">Date</th>
                            <th style="width:15%;">Time In</th>
                            <th style="width:15%;">Time Out</th>
                            <th style="width:15%;">Regular Hours</th>
                            <th style="width:15%;">OT Hours</th>
                            <th style="width:20%;">Total Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($r = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['date']) ?></td>
                                <td>
                                    <?php
                                        if ($r['time_in']) {
                                            $time_in_ts = strtotime($r['time_in']);
                                            $policy_in = $r['time_in_policy'] ?? $user_policy_time_in;
                                            $policy_in_time_ts = strtotime(date('Y-m-d', $time_in_ts) . ' ' . $policy_in);
                                            $is_late = $time_in_ts >= $policy_in_time_ts;
                                            $time_in_display = date('g:i A', $time_in_ts);
                                            if ($is_late) {
                                                echo '<span style="color: red;">' . htmlspecialchars($time_in_display) . '</span>';
                                            } else {
                                                echo htmlspecialchars($time_in_display);
                                            }
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>
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
                                            $result = calculate_session_hours($r, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $user_id, $oat);
                                            $reg = $result['regular'];
                                            echo floor($reg) . ' h';
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>

                                <td>
                                    <?php
                                        if ($r['time_in'] && $r['time_out'] && $r['time_out'] !== '00:00:00') {
                                            $ot_hours = (float)($r['ot_hours'] ?? 0);
                                            echo floor($ot_hours) . ' h';
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        if ($r['time_in'] && $r['time_out'] && $r['time_out'] !== '00:00:00') {
                                            $result = calculate_session_hours($r, $user_policy_time_in, $user_policy_time_out, $user_lunch_start, $user_lunch_end, $user_id, $oat);
                                            echo $result['total'] . ' h';
                                        } else {
                                            echo '<span style="color:var(--muted)">--</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                </div>
                <?php else: ?>
                    <div style="padding:18px;color:var(--muted);">No recent sessions yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>

<?php
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