<?php
session_start();
require_once "../config.php";

header("Content-Type: application/json");

if (!isset($_SESSION['email'])) {
    echo json_encode(["status"=>"error"]);
    exit;
}

$email = $_SESSION['email'];
$movie_id = $_GET['movie_id'] ?? '';

$stmt = $conn->prepare("SELECT * FROM user_progress WHERE email=? AND movie_id=? LIMIT 1");
$stmt->bind_param("ss", $email, $movie_id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    "status"=>"ok",
    "data"=>$data
]);
