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

// 📥 Recibir datos
$data = json_decode(file_get_contents("php://input"), true);

$movie_id = $data['movie_id'] ?? '';
$tiempo   = $data['tiempo'] ?? 0;

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie_id"]);
    exit;
}

// 🔥 INSERT o UPDATE
$sql = "INSERT INTO progreso_peliculas (email, movie_id, tiempo, updated_at)
VALUES (?, ?, ?, NOW())
ON DUPLICATE KEY UPDATE
tiempo = VALUES(tiempo),
updated_at = NOW()";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssd", $email, $movie_id, $tiempo);

if ($stmt->execute()) {
    echo json_encode(["status"=>"ok"]);
} else {
    echo json_encode(["status"=>"error","msg"=>$stmt->error]);
}
