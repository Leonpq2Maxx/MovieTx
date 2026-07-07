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
   🔥 OBTENER DATOS REALES
========================= */

// email y nombre del usuario
$userEmail = $_SESSION['email'] ?? '';
$userName  = $_SESSION['name'] ?? 'Usuario';

// 🔥 obtener nombre REAL del perfil desde BD
$perfilName = 'Perfil';

$stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? LIMIT 1");
if ($stmtPerfil) {
    $stmtPerfil->bind_param("i", $perfilId);
    $stmtPerfil->execute();
    $resultPerfil = $stmtPerfil->get_result();
    
    if ($rowPerfil = $resultPerfil->fetch_assoc()) {
        $perfilName = $rowPerfil['nombre']; // 🔥 ESTE es el nombre real (ej: mela)
    }
}

/* =========================
   RECIBIR DATOS
========================= */

$data = json_decode(file_get_contents("php://input"), true);

$movie_id  = $data['movie_id'] ?? null;
$temporada = $data['temporada'] ?? null;
$episodio  = $data['episodio'] ?? null;
$tiempo    = isset($data['tiempo']) ? (float)$data['tiempo'] : 0;

/* =========================
   DEBUG
========================= */

file_put_contents("debug_progreso_perfil.txt", json_encode($data) . PHP_EOL, FILE_APPEND);

/* =========================
   VALIDACIÓN
========================= */

if (!$movie_id) {
    echo json_encode(["status"=>"error","msg"=>"no_movie"]);
    exit;
}

/* =========================
   VALORES SEGUROS
========================= */

if ($temporada === null || $temporada === "") $temporada = "0";
if ($episodio === null || $episodio === "") $episodio = "0";

/* =========================
   GUARDAR PROGRESO
========================= */

$sql = "INSERT INTO user_progress_perfil 
(user_id, perfil_id, user_email, user_name, perfil_name, movie_id, temporada, episodio, tiempo)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
temporada = VALUES(temporada),
episodio = VALUES(episodio),
tiempo = VALUES(tiempo),
user_email = VALUES(user_email),
user_name = VALUES(user_name),
perfil_name = VALUES(perfil_name)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

/* =========================
   BIND
========================= */

$stmt->bind_param(
    "iissssssd",
    $userId,
    $perfilId,
    $userEmail,
    $userName,
    $perfilName, // 🔥 ahora es el correcto
    $movie_id,
    $temporada,
    $episodio,
    $tiempo
);

$stmt->execute();

/* =========================
   RESPUESTA
========================= */

echo json_encode([
    "status"=>"ok",
    "perfil_name"=>$perfilName // útil para debug
]);