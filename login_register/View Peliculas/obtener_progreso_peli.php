<?php
session_start();
require_once "../config.php";

header("Content-Type: application/json");

// 🔒 Validar sesión
if (!isset($_SESSION['email'])) {
    echo json_encode(["status"=>"error","msg"=>"no_session"]);
    exit;
}

$email = $_SESSION['email'];
$movie_id = $_GET['movie_id'] ?? '';

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie_id"]);
    exit;
}

// 🔎 Buscar progreso
$sql = "SELECT tiempo FROM progreso_peliculas 
        WHERE email=? AND movie_id=? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $email, $movie_id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    "status" => "ok",
    "data" => $data
]);
