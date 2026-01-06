<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "san_benito_health";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone to Philippine Time
mysqli_query($conn, "SET time_zone = '+08:00'");
