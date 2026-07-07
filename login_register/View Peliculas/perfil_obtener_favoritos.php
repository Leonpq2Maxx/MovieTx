<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR PERFIL
========================= */
if (!isset($_SESSION['email']) || !isset($_SESSION['perfil_name'])) {
    echo json_encode([]);
    exit;
}

$email  = $_SESSION['email'];
$perfil = $_SESSION['perfil_name'];

/* =========================
   OBTENER FAVORITOS POR PERFIL
========================= */
$stmt = $conn->prepare("
    SELECT movie_id, titulo, imagen, tipo, creado_en
    FROM perfil_favorito
    WHERE user_email=? AND perfil_name=?
    ORDER BY creado_en DESC
");

$stmt->bind_param("ss", $email, $perfil);
$stmt->execute();
$result = $stmt->get_result();

$favoritos = [];

while ($row = $result->fetch_assoc()) {

    $titulo = $row['titulo'];
    $tipo   = $row['tipo'];

    /* =========================
       NORMALIZAR
    ========================= */
    if (!$titulo || $titulo === "undefined") {
        $titulo = str_replace("_", " ", $row['movie_id']);
        $titulo = ucwords($titulo);
    }

    if (!$tipo || $tipo === "undefined") {
        $tipo = "pelicula";
    }

    $favoritos[] = [
        "id"     => $row['movie_id'],
        "titulo" => $titulo,
        "imagen" => $row['imagen'],
        "tipo"   => $tipo,
        "fecha"  => strtotime($row['creado_en']) * 1000
    ];
}

echo json_encode($favoritos);
?>
