<?php
session_start();
require_once "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id']) || !isset($_SESSION['perfil_id'])) {
    echo json_encode(["status"=>"error","msg"=>"no_session"]);
    exit;
}

$userId   = (int) $_SESSION['id'];
$perfilId = (int) $_SESSION['perfil_id'];

/* =========================
   VALIDAR MOVIE ID
========================= */

$movie_id = $_GET['movie_id'] ?? null;

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie_id"]);
    exit;
}

/* =========================
   OBTENER PROGRESO
========================= */

$sql = "SELECT 
            user_email,
            user_name,
            perfil_name,
            tiempo
        FROM perfil_progreso_peliculas
        WHERE user_id=? AND perfil_id=? AND movie_id=?
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

$stmt->bind_param("iis", $userId, $perfilId, $movie_id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

/* =========================
   RESPUESTA
========================= */

echo json_encode([
    "status" => "ok",
    "data"   => $data ? $data : null
]);