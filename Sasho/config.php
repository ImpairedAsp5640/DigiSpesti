<?php
$host = "database-1.cluster-cgnbwogrvdhe.eu-central-1.rds.amazonaws.com";
$username = "impairedasp5640";
$password = ">)0qTEMVW(19#)ez|2WF8RhupP21";
$database = "database1";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
