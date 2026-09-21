<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smart_trip_budget";

$conn = new mysqli(
    $servername,
    $username,
    $password,
    $dbname,
    3307

);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

?>