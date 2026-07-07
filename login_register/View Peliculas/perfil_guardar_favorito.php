<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR PERFIL
========================= */
if (!isset($_SESSION['email']) || !isset($_SESSION['perfil_name'])) {
    echo json_encode(["status" => "error", "msg" => "No es perfil"]);
    exit;
}

$email  = $_SESSION['email'];
$perfil = $_SESSION['perfil_name'];

/* =========================
   DATOS
========================= */
$movie_id = $_POST['movie_id'] ?? '';
$titulo   = $_POST['titulo'] ?? '';
$tipo     = $_POST['tipo'] ?? 'pelicula';
$imagen   = $_POST['imagen'] ?? '';

if (!$movie_id) {
    echo json_encode(["status" => "error", "msg" => "Datos incompletos"]);
    exit;
}

/* =========================
   VERIFICAR EXISTE
========================= */
$check = $conn->prepare("
    SELECT id FROM perfil_favorito 
    WHERE user_email=? AND perfil_name=? AND movie_id=?
");
$check->bind_param("sss", $email, $perfil, $movie_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    echo json_encode(["status" => "exists"]);
    exit;
}

/* =========================
   INSERT
========================= */
$stmt = $conn->prepare("
    INSERT INTO perfil_favorito
    (user_email, perfil_name, movie_id, titulo, imagen, tipo, creado_en)
    VALUES (?,?,?,?,?,?,NOW())
");

$stmt->bind_param(
    "ssssss",
    $email,
    $perfil,
    $movie_id,
    $titulo,
    $imagen,
    $tipo
);

$stmt->execute();

echo json_encode(["status" => "success"]);
?>
