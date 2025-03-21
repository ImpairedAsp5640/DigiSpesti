<?php
// Set default character set
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = "database-1.cluster-cgnbwogrvdhe.eu-central-1.rds.amazonaws.com";
$username = "impairedasp5640";
$password = ">)0qTEMVW(19#)ez|2WF8RhupP21";
$database = "database1";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set UTF-8 character set for all database operations
$conn->set_charset("utf8mb4");

// Remove mb_internal_encoding that might not be available
// mb_internal_encoding('UTF-8');
?>

