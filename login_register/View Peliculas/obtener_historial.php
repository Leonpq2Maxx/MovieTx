<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['email'])){
    echo json_encode([]);
    exit;
}

$email = $_SESSION['email'];

$stmt = $conn->prepare("
SELECT movie_id, titulo, tipo, imagen, progreso, visto_en, archivo
FROM historial
WHERE user_email=?
ORDER BY visto_en DESC
");

$stmt->bind_param("s",$email);
$stmt->execute();

$result = $stmt->get_result();

$historial = [];

while($row = $result->fetch_assoc()){

    $titulo = $row['titulo'];
    $tipo = $row['tipo'];

    /* seguridad si algo viene vacío */

    if(!$titulo || $titulo == "undefined"){
        $titulo = str_replace("_"," ",$row['movie_id']);
        $titulo = ucwords($titulo);
    }

    if(!$tipo || $tipo == "undefined"){
        $tipo = "pelicula";
    }

    $historial[] = [
        "movie_id" => $row['movie_id'],
        "titulo" => $titulo,
        "tipo" => $tipo,
        "imagen" => $row['imagen'],
        "progreso" => $row['progreso'],
        "archivo" => $row['archivo'],
        "timestamp" => strtotime($row['visto_en'])*1000
    ];
}

echo json_encode($historial);
?>