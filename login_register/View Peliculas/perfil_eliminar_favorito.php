<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR PERFIL
========================= */
if (!isset($_SESSION['email']) || !isset($_SESSION['perfil_name'])) {
    echo json_encode(["status" => "error"]);
    exit;
}

$email  = $_SESSION['email'];
$perfil = $_SESSION['perfil_name'];
$movie_id = $_POST['movie_id'] ?? '';

if (!$movie_id) {
    echo json_encode(["status" => "error"]);
    exit;
}

/* =========================
   DELETE
========================= */
$stmt = $conn->prepare("
    DELETE FROM perfil_favorito 
    WHERE user_email=? AND perfil_name=? AND movie_id=?
");

$stmt->bind_param("sss", $email, $perfil, $movie_id);
$stmt->execute();

echo json_encode(["status" => "success"]);
?>
