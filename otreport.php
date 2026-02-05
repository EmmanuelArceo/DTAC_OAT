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

// Handle OT report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ot_report'])) {
    $student_id = $_SESSION['user_id'];
    $ot_hours = $_POST['ot_hours'];
    $ot_date = $_POST['ot_date'];
    $ot_reason = $_POST['ot_reason'];

    // Save OT report to database
    $stmt = $oat->prepare("INSERT INTO ot_reports (student_id, ot_hours, ot_date, ot_reason) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $student_id, $ot_hours, $ot_date, $ot_reason);
    $stmt->execute();

    echo "<div class='alert alert-success mt-3'>OT report submitted successfully!</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit OT Report</title>
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
        body{
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            margin:0;
            min-height:100vh;
            background: linear-gradient(135deg, #f6fbfb 0%, #eef9fa 50%, #f9fcfd 100%);
            color:#0f172a;
            -webkit-font-smoothing:antialiased;
        }
        .wrap{
            max-width:500px;
            margin:48px auto;
            padding:24px;
        }
        .glass{
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 30px rgba(15,23,42,0.06);
            backdrop-filter: blur(8px) saturate(120%);
            border-radius:14px;
            padding:32px 24px;
        }
        .title{
            font-size:22px;
            font-weight:800;
            color:var(--accent-deep);
            margin-bottom:8px;
        }
        .subtitle{
            font-size:14px;
            color:var(--muted);
            margin-bottom:18px;
        }
        .form-label{
            font-weight:600;
            color:var(--accent-deep);
        }
        .btn-accent{
            background:transparent;
            border:1px solid var(--accent);
            color:var(--accent-deep);
            padding:10px 14px;
            border-radius:10px;
            font-weight:700;
            transition:all .15s ease;
            width:100%;
        }
        .btn-accent:hover{
            background:var(--accent);
            color:#fff;
            transform:translateY(-3px);
            box-shadow:0 8px 20px rgba(60,179,204,0.12);
            border-color:transparent;
        }
        @media (max-width: 600px){
            .wrap{ max-width:100%; margin:0; padding:8px;}
            .glass{ padding:18px 8px;}
        }
    </style>
</head>
<body>
    <main class="wrap">
        <div class="glass">
            <div class="title">Submit OT Report</div>
            <div class="subtitle">Send your overtime report.</div>
            <form method="POST" action="otreport.php" autocomplete="off">
                <div class="mb-3">
                    <label for="ot_hours" class="form-label">OT Hours</label>
                    <input type="number" name="ot_hours" id="ot_hours" class="form-control" required min="1">
                </div>
                <div class="mb-3">
                    <label for="ot_date" class="form-label">OT Date</label>
                    <input type="date" name="ot_date" id="ot_date" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="ot_reason" class="form-label">Reason for OT</label>
                    <textarea name="ot_reason" id="ot_reason" class="form-control" rows="3" required></textarea>
                </div>
                <button type="submit" name="submit_ot_report" class="btn-accent mt-2">Submit OT Report</button>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>