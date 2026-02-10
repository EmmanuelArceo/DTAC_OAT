<?php

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: ../login.php");
    exit;
}
include '../db.php';
include 'nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live Activity Feed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f6fcfe 0%, #e3f6fa 60%, #d2f1f7 100%);
            min-height: 100vh;
        }
        .activity-glass {
            background: rgba(60, 178, 204, 0.09);
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(60,178,204,0.08);
            backdrop-filter: blur(6px) saturate(120%);
            max-width: 900px;
            margin: 48px auto;
            padding: 36px 28px;
        }
        .activity-title {
            font-size: 2rem;
            font-weight: 800;
            color: #4c8eb1;
            margin-bottom: 18px;
            letter-spacing: 1px;
        }
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 340px; /* 4 items * 85px each (approx) */
            overflow-y: auto;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
            min-height: 70px;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #fff;
        }
        .icon-in { background: #10b981; }
        .icon-out { background: #3cb2cc; }
        .icon-ot { background: #f59e0b; }
        .activity-details {
            flex: 1;
        }
        .activity-user {
            font-weight: 600;
            color: #0f172a;
        }
        .activity-time {
            color: #64748b;
            font-size: 0.95rem;
        }
        @media (max-width: 767.98px) {
            .activity-glass { padding: 1rem 0.2rem; }
            .activity-title { font-size: 1.2rem; }
            .activity-item { gap: 0.5rem; }
            .activity-icon { width: 28px; height: 28px; font-size: 1rem; }
            .activity-list { max-height: 320px; }
        }
    </style>
</head>
<body>
    <div class="activity-glass">
        <div class="activity-title"><i class="bi bi-activity me-2"></i>Live Activity Feed</div>
        <ul class="activity-list" id="activity-list">
            <!-- Activities will be loaded here via AJAX -->
        </ul>
    </div>
    <script>
    function fetchActivities() {
        fetch('activity_feed.php')
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('activity-list');
                list.innerHTML = '';
                if (data.length === 0) {
                    list.innerHTML = '<li class="text-center text-muted py-4">No activity for today yet.</li>';
                    return;
                }
                // Show only the latest 4 activities
                data.slice(0, 4).forEach(item => {
                    let iconClass = '', icon = '';
                    if (item.type === 'clock_in') {
                        iconClass = 'icon-in';
                        icon = '<i class="bi bi-box-arrow-in-right"></i>';
                    } else if (item.type === 'clock_out') {
                        iconClass = 'icon-out';
                        icon = '<i class="bi bi-box-arrow-right"></i>';
                    } else if (item.type === 'ot_request') {
                        iconClass = 'icon-ot';
                        icon = '<i class="bi bi-alarm"></i>';
                    }
                    list.innerHTML += `
                        <li class="activity-item">
                            <span class="activity-icon ${iconClass}">${icon}</span>
                            <div class="activity-details">
                                <span class="activity-user">${item.user}</span>
                                <span>${item.action}</span>
                                <div class="activity-time">${item.time}</div>
                            </div>
                        </li>
                    `;
                });
                // If more than 4, show scroll (handled by CSS)
            });
    }
    fetchActivities();
    setInterval(fetchActivities, 5000); // Update every 5 seconds
    </script>
</body>
</html>