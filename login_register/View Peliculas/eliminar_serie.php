<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

if(!isset($_POST['id'])){
    echo json_encode(["status"=>"error"]);
    exit;
}

$email = $_SESSION['email'];
$serie_id = $_POST['id'];

/* obtener user_id */
$stmtUser = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$stmtUser->bind_param("s",$email);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$userId = $user['id'];

/* eliminar */
$stmt = $conn->prepare("
DELETE FROM continuar_serie 
WHERE user_id=? AND serie_id=?
");

$stmt->bind_param("is",$userId,$serie_id);

if($stmt->execute()){
    echo json_encode(["status"=>"ok"]);
} else {
    echo json_encode(["status"=>"error"]);
}