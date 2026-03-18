<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['email'])){
    echo json_encode([]);
    exit;
}

$email = $_SESSION['email'];
$perfilId = isset($_SESSION['perfil_id']) ? intval($_SESSION['perfil_id']) : 0;

$stmt = $conn->prepare("
SELECT movie_id, titulo, tipo, imagen, progreso, visto_en, archivo
FROM historial
WHERE user_email=? AND perfil_id=?
ORDER BY visto_en DESC
");
$stmt->bind_param("si",$email,$perfilId);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];

while($row = $result->fetch_assoc()){
    $titulo = $row['titulo'];
    $tipo = $row['tipo'];

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
