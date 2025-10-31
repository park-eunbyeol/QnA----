<?php
session_start();

$host = "localhost";
$user = "ghjrodf";
$password = "p20040829!";
$dbname = "ghjrodf";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("DB 연결 실패: " . $conn->connect_error);
}

// 문자셋 통일
$conn->set_charset("utf8mb4");
$conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
?>
