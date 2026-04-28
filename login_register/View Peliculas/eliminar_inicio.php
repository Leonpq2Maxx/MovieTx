<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['id'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

if(!isset($_POST['id'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

$userId = $_SESSION['id'];
$pelicula_id = $_POST['id'];

$stmt = $conn->prepare("
DELETE FROM continuar_viendo 
WHERE user_id=? AND pelicula_id=?
");

$stmt->bind_param("is", $userId, $pelicula_id);

if($stmt->execute()){
    echo json_encode(["status"=>"ok"]);
}else{
    echo json_encode(["status"=>"error"]);
}