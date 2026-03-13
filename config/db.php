<?php
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$db   = 'eggsy_pos';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");