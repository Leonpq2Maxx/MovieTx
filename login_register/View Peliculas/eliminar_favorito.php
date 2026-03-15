<?php
session_start();
require "../config.php";

if(!isset($_SESSION['email'])){
    exit;
}

$email = $_SESSION['email'];
$movie_id = $_POST['movie_id'];

$sql = "DELETE FROM favoritos WHERE user_email=? AND movie_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss",$email,$movie_id);
$stmt->execute();
?>
