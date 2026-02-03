<?php
date_default_timezone_set('Asia/Manila');
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "oat";

// Create connection
$oat = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($oat->connect_error) {
    die("Connection failed: " . $oat->connect_error);
}
// Connection successful
