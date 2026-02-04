<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_reset_email($to_email, $reset_link) {
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'petbuddy.co.ph@gmail.com';
        $mail->Password   = 'wtir hqqb vlnp ychh'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        $mail->setFrom('petbuddy.co.ph@gmail.com', 'DLSU-D TESDA');
        $mail->addAddress($to_email); // <-- This line is required!

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "Click this link to reset your password (valid for 2 minutes):<br><a href='$reset_link'>$reset_link</a>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // For debugging, you can uncomment the next line:
        // echo "Mailer Error: {$mail->ErrorInfo}";
        return false;
    }
}

// Example usage:
// send_reset_email('user@example.com', 'https://example.com/reset');
// mail('user@example.com', 'Subject', 'Content');
?>