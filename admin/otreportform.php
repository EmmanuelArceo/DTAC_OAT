<?php

include '../db.php';
include 'nav.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'adviser'])) {
    header("Location: ../login.php");
    exit;
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $oat->prepare("SELECT r.*, u.fname, u.lname, u.username, tg.time_in AS adviser_time_in, tg.time_out AS adviser_time_out FROM ojt_records r JOIN users u ON r.user_id = u.id LEFT JOIN user_time_groups utg ON utg.user_id = u.id LEFT JOIN time_groups tg ON utg.group_id = tg.id WHERE r.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo "<div class='alert alert-danger'>OT record not found.</div>";
    exit;
}

// Approve logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_ot'])) {
    $ot_hours = (float)$row['ot_hours'];
    $oat->query("UPDATE ojt_records SET ot_approved = 1, ot_hours = ot_hours + $ot_hours WHERE id = $id");
    header("Location: otreports.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OT Report Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<main class="container py-4" style="max-width:700px">
    <h2>OT Report Details</h2>
    <div class="mb-3">
        <strong>Date:</strong> <?= htmlspecialchars($row['date']) ?><br>
        <strong>Student:</strong> <?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?><br>
        <strong>Username:</strong> <?= htmlspecialchars($row['username']) ?><br>
        <strong>Actual Time In:</strong> <?= htmlspecialchars($row['time_in']) ?><br>
        <strong>Adviser Policy Time In:</strong> <?= htmlspecialchars($row['adviser_time_in']) ?><br>
        <strong>Actual Time Out:</strong> <?= htmlspecialchars($row['time_out']) ?><br>
        <strong>Adviser Policy Time Out:</strong> <?= htmlspecialchars($row['adviser_time_out']) ?><br>
        <strong>OT Hours:</strong> <?= htmlspecialchars($row['ot_hours']) ?><br>
        <strong>Description:</strong> <?= htmlspecialchars($row['remarks']) ?><br>
        <?php if (!empty($row['ot_proof'])): ?>
            <strong>Proof Image:</strong><br>
            <img src="../uploads/<?= htmlspecialchars($row['ot_proof']) ?>" alt="OT Proof" style="max-width:300px;">
        <?php endif; ?>
    </div>
    <form method="post">
        <?php if (!$row['ot_approved']): ?>
            <button type="submit" name="approve_ot" class="btn btn-success">Approve</button>
        <?php else: ?>
            <span class="badge bg-success">Already Approved</span>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        <a href="otreports.php" class="btn btn-outline-primary">Back</a>
    </form>
</main>
</body>
</html>