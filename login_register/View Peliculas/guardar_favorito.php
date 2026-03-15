<?php
session_start();
require "../config.php";

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No logueado"]);
    exit;
}

$email = $_SESSION['email'];

$movie_id = $_POST['movie_id'];
$imagen = $_POST['imagen']; // 🔹 recibir imagen
$titulo = $_POST['titulo'];
$tipo = $_POST['tipo'];

$check = $conn->prepare("SELECT id FROM favoritos WHERE user_email=? AND movie_id=?");
$check->bind_param("ss",$email,$movie_id);
$check->execute();
$result = $check->get_result();

if($result->num_rows > 0){
    echo json_encode(["status"=>"exists"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO favoritos (user_email,movie_id,titulo,imagen,tipo,unico_favorito) VALUES (?,?,?,?,?,CURDATE())");
$stmt->bind_param("sssss",$email,$movie_id,$titulo,$imagen,$tipo);
$stmt->execute();

echo json_encode(["status"=>"success"]);
?>
