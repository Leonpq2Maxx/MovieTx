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
   DATOS USUARIO
========================= */

$userEmail = $_SESSION['email'] ?? '';
$userName  = $_SESSION['name'] ?? 'Usuario';

/* =========================
   🔥 OBTENER NOMBRE REAL DEL PERFIL
========================= */

$perfilName = 'Perfil';

$stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? LIMIT 1");
if ($stmtPerfil) {
    $stmtPerfil->bind_param("i", $perfilId);
    $stmtPerfil->execute();
    $resPerfil = $stmtPerfil->get_result();

    if ($row = $resPerfil->fetch_assoc()) {
        $perfilName = $row['nombre']; // 🔥 REAL (ej: mela)
    }
}

/* =========================
   RECIBIR DATOS
========================= */

$data = json_decode(file_get_contents("php://input"), true);

$movie_id = $data['movie_id'] ?? null;
$tiempo   = isset($data['tiempo']) ? (float)$data['tiempo'] : 0;

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie_id"]);
    exit;
}

/* =========================
   DEBUG
========================= */

file_put_contents("debug_progreso_peli_perfil.txt", json_encode($data) . PHP_EOL, FILE_APPEND);

/* =========================
   INSERT / UPDATE
========================= */

$sql = "INSERT INTO perfil_progreso_peliculas
(user_id, perfil_id, user_email, user_name, perfil_name, movie_id, tiempo)
VALUES (?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
tiempo = VALUES(tiempo),
user_email = VALUES(user_email),
user_name = VALUES(user_name),
perfil_name = VALUES(perfil_name),
updated_at = CURRENT_TIMESTAMP";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

$stmt->bind_param(
    "iissssd",
    $userId,
    $perfilId,
    $userEmail,
    $userName,
    $perfilName,
    $movie_id,
    $tiempo
);

if ($stmt->execute()) {
    echo json_encode([
        "status"=>"ok",
        "perfil_name"=>$perfilName
    ]);
} else {
    echo json_encode(["status"=>"error","msg"=>$stmt->error]);
}