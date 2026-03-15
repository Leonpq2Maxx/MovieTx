<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

if(!isset($_POST['movie_id'], $_POST['imagen'], $_POST['progreso'], $_POST['archivo'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

$email = $_SESSION['email'];

$movie_id = $_POST['movie_id'];
$titulo = $_POST['titulo'] ?? "";
$tipo = $_POST['tipo'] ?? "pelicula";
$archivo = $_POST['archivo'];
$imagen = $_POST['imagen'];
$progreso = $_POST['progreso'];

/* evitar undefined */

if($titulo == "" || $titulo == "undefined"){
    $titulo = str_replace("_"," ",$movie_id);
}

if($tipo == "" || $tipo == "undefined"){
    $tipo = "pelicula";
}

/* verificar si existe */

$check = $conn->prepare("SELECT id FROM historial WHERE user_email=? AND movie_id=?");
$check->bind_param("ss",$email,$movie_id);
$check->execute();
$result = $check->get_result();

if($result->num_rows > 0){

    $update = $conn->prepare("
        UPDATE historial
        SET progreso=?, visto_en=NOW()
        WHERE user_email=? AND movie_id=?
    ");

    $update->bind_param("sss",$progreso,$email,$movie_id);
    $update->execute();

    echo json_encode(["status"=>"updated"]);
    exit;
}

/* insertar */

$stmt = $conn->prepare("
INSERT INTO historial 
(user_email,movie_id,titulo,tipo,imagen,progreso,archivo,visto_en)
VALUES (?,?,?,?,?,?,?,NOW())
");

$stmt->bind_param("sssssss",$email,$movie_id,$titulo,$tipo,$imagen,$progreso,$archivo);
$stmt->execute();

/* limite historial */

$limite = 15;

$delete = $conn->prepare("
DELETE FROM historial 
WHERE user_email=? 
AND id NOT IN (
    SELECT id FROM (
        SELECT id FROM historial
        WHERE user_email=?
        ORDER BY visto_en DESC
        LIMIT $limite
    ) AS temp
)
");

$delete->bind_param("ss",$email,$email);
$delete->execute();

echo json_encode(["status"=>"new"]);
?>