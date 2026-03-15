<?php
session_start();
require "../config.php";

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No logueado"]);
    exit;
}

$email = $_SESSION['email'];
$movie_id = $_POST['movie_id'];

$stmt = $conn->prepare("DELETE FROM historial WHERE user_email=? AND movie_id=?");
$stmt->bind_param("ss",$email,$movie_id);
$stmt->execute();

echo json_encode(["status"=>"success"]);
?>
