<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');

    // Check if time_out is set for today
    $result = $oat->query("SELECT time_out FROM ojt_records WHERE user_id = $user_id AND date = '$today'");
    $row = $result->fetch_assoc();

    if ($row && (!$row['time_out'] || $row['time_out'] == '00:00:00')) {
        // Delete only if time_out is not set (incomplete day)
        $oat->query("DELETE FROM ojt_records WHERE user_id = $user_id AND date = '$today'");
        $_SESSION['logout_message'] = "Logged out successfully. Today's incomplete DTR record has been deleted.";
    } else {
        // If time_out is set, keep the record
        $_SESSION['logout_message'] = "Logged out successfully. Your DTR record for today is saved.";
    }
}

// Destroy session and redirect
session_destroy();
header("Location: login.php");
exit;