<?php
session_start();
require_once "../config.php";

header("Content-Type: application/json");

if (!isset($_SESSION['email'])) {
    echo json_encode(["status"=>"error","msg"=>"no_session"]);
    exit;
}

$email = $_SESSION['email'];

$data = json_decode(file_get_contents("php://input"), true);

// 🔥 VALIDACIÓN REAL
$movie_id = $data['movie_id'] ?? null;
$temporada = isset($data['temporada']) ? $data['temporada'] : null;
$episodio = isset($data['episodio']) ? $data['episodio'] : null;
$tiempo = isset($data['tiempo']) ? (float)$data['tiempo'] : 0;

// 🚨 DEBUG (clave para detectar el bug real)
file_put_contents("debug_progreso.txt", json_encode($data) . PHP_EOL, FILE_APPEND);

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie"]);
    exit;
}

// 🔥 FORZAR VALORES SEGUROS
if ($temporada === null || $temporada === "") $temporada = 0;
if ($episodio === null || $episodio === "") $episodio = 0;

$sql = "INSERT INTO user_progress (email, movie_id, temporada, episodio, tiempo)
VALUES (?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
temporada = VALUES(temporada),
episodio = VALUES(episodio),
tiempo = VALUES(tiempo)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

// 🔥 IMPORTANTE: todo como string excepto tiempo
$stmt->bind_param("ssssd", $email, $movie_id, $temporada, $episodio, $tiempo);

$stmt->execute();

echo json_encode(["status"=>"ok"]);