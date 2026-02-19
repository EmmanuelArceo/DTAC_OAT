<?php
// This endpoint returns the recent activity HTML for AJAX polling
include '../db.php';
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])) {
    exit;
}
$admin_id = (int)($_SESSION['user_id'] ?? 0);
// Show clock in, clock out, and OT request events, sorted by latest action
if ($_SESSION['role'] === 'super_admin') {
    $recent_activities = $oat->query("
        SELECT * FROM (
            SELECT r.id, u.fname, u.mname, u.lname, u.profile_img, r.time_in as action_time, r.date, 
                   CONCAT(r.date, ' ', r.time_in) as action_datetime, 'time_in' as action_type, NULL as ot_id, NULL as ot_submitted
            FROM ojt_records r
            JOIN users u ON r.user_id = u.id
            WHERE r.time_in IS NOT NULL
            UNION ALL
            SELECT r.id, u.fname, u.mname, u.lname, u.profile_img, r.time_out as action_time, r.date, 
                   CONCAT(r.date, ' ', r.time_out) as action_datetime, 'time_out' as action_type, NULL as ot_id, NULL as ot_submitted
            FROM ojt_records r
            JOIN users u ON r.user_id = u.id
            WHERE r.time_out IS NOT NULL AND r.time_out != '' AND r.time_out != '00:00:00'
            UNION ALL
            SELECT NULL as id, u.fname, u.mname, u.lname, u.profile_img, o.submitted_at as action_time, o.ot_date as date, 
                   o.submitted_at as action_datetime, 'ot_request' as action_type, o.id as ot_id, o.submitted_at as ot_submitted
            FROM ot_reports o
            JOIN users u ON o.student_id = u.id
        ) t
        ORDER BY t.action_datetime DESC
        LIMIT 5
    ");
} else {
    $recent_activities = $oat->query("
        SELECT * FROM (
            SELECT r.id, u.fname, u.mname, u.lname, u.profile_img, r.time_in as action_time, r.date, 
                   CONCAT(r.date, ' ', r.time_in) as action_datetime, 'time_in' as action_type, NULL as ot_id, NULL as ot_submitted
            FROM ojt_records r
            JOIN users u ON r.user_id = u.id
            WHERE r.time_in IS NOT NULL
              AND u.role='ojt'
              AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
            UNION ALL
            SELECT r.id, u.fname, u.mname, u.lname, u.profile_img, r.time_out as action_time, r.date, 
                   CONCAT(r.date, ' ', r.time_out) as action_datetime, 'time_out' as action_type, NULL as ot_id, NULL as ot_submitted
            FROM ojt_records r
            JOIN users u ON r.user_id = u.id
            WHERE r.time_out IS NOT NULL AND r.time_out != '' AND r.time_out != '00:00:00'
              AND u.role='ojt'
              AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
            UNION ALL
            SELECT NULL as id, u.fname, u.mname, u.lname, u.profile_img, o.submitted_at as action_time, o.ot_date as date, 
                   o.submitted_at as action_datetime, 'ot_request' as action_type, o.id as ot_id, o.submitted_at as ot_submitted
            FROM ot_reports ow
            JOIN users u ON o.student_id = u.id
            WHERE u.role='ojt'
                AND (u.adviser_id = $admin_id OR u.adviser_id IS NULL OR u.adviser_id = '')
        ) t
        ORDER BY t.action_datetime DESC
        LIMIT 5
    ");
}
if ($recent_activities && $recent_activities->num_rows > 0): ?>
    <ul class="activity-list">
        <?php while ($activity = $recent_activities->fetch_assoc()):
            $img = '../uploads/noimg.png';
            if (!empty($activity['profile_img']) && file_exists('../' . $activity['profile_img'])) {
                $img = '../' . $activity['profile_img'];
            }
            $mname = trim($activity['mname']);
            $middle = $mname ? strtoupper($mname[0]) . '.' : '';
            $fullname = htmlspecialchars($activity['fname'] . ' ' . ($middle ? $middle . ' ' : '') . $activity['lname']);
            $dtr_id = (int)($activity['id'] ?? 0);
            $date_display = date('M d', strtotime($activity['date']));
            $time_ago = date('g:i A', strtotime($activity['action_time']));
            if ($activity['action_type'] === 'time_in') {
                $time_label = 'clocked in';
                $icon = 'bi-box-arrow-in-right';
                $link = "verifydtr.php?id=$dtr_id";
                $title = 'View DTR Record';
            } elseif ($activity['action_type'] === 'time_out') {
                $time_label = 'clocked out';
                $icon = 'bi-box-arrow-right';
                $link = "verifydtr.php?id=$dtr_id";
                $title = 'View DTR Record';
            } elseif ($activity['action_type'] === 'ot_request') {
                $time_label = 'sent an OT request';
                $icon = 'bi-alarm';
                $ot_id = (int)($activity['ot_id'] ?? 0);
                $link = "otreports.php?open_ot=$ot_id";
                $title = 'View OT Request';
            } else {
                $time_label = '';
                $icon = 'bi-info-circle';
                $link = '#';
                $title = '';
            }
        ?>
        <li class="activity-item p-0">
            <a href="<?= htmlspecialchars($link) ?>" style="display:flex;align-items:center;gap:1rem;padding:1rem;text-decoration:none;color:inherit;" title="<?= htmlspecialchars($title) ?>" <?= $activity['action_type'] === 'ot_request' ? 'data-ot-id="' . $ot_id . '"' : '' ?> >
                <img src="<?= htmlspecialchars($img) ?>" alt="" class="user-avatar">
                <div class="activity-content">
                    <h6><?= $fullname ?> <?= $time_label ?></h6>
                    <small><i class="bi bi-clock me-1"></i><?= $time_ago ?> • <?= $date_display ?></small>
                </div>
                <div class="activity-icon">
                    <i class="bi <?= $icon ?>"></i>
                </div>
            </a>
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