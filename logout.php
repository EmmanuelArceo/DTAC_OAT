<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $now = date('H:i:s');
    $today = date('Y-m-d');

    // Update today's DTR record if time_out is not set
    $oat->query("UPDATE ojt_records SET time_out = '$now' WHERE user_id = $user_id AND date = '$today' AND (time_out IS NULL OR time_out = '')");
}

// Destroy session and redirect
session_destroy();
header("Location: login.php");
exit;