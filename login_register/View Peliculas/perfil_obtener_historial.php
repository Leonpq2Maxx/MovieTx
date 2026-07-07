<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if (!isset($_SESSION['email'])) {
    echo json_encode([]);
    exit;
}

$email = $_SESSION['email'];

/* =========================
   VALIDAR PERFIL ACTIVO
========================= */
if (!isset($_SESSION['perfil_name'])) {
    echo json_encode([]);
    exit;
}

$perfil = $_SESSION['perfil_name'];

/* =========================
   OBTENER HISTORIAL POR PERFIL
========================= */
$stmt = $conn->prepare("
    SELECT movie_id, titulo, tipo, imagen, archivo, visto_en
    FROM perfil_historial
    WHERE user_email=? AND perfil_name=?
    ORDER BY visto_en DESC
");

$stmt->bind_param("ss", $email, $perfil);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];

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

    $historial[] = [
    "movie_id"  => $row['movie_id'],
    "titulo"    => $titulo,
    "tipo"      => $tipo,
    "imagen"    => $row['imagen'],
    "archivo"   => $row['archivo'],
    "timestamp" => strtotime($row['visto_en']) * 1000,

    // 🔥 CLAVE
    "origen"    => "perfil",
    "perfil_name" => $perfil   // usamos el nombre como ID temporal
];

}

/* =========================
   RESPUESTA
========================= */
echo json_encode($historial);
