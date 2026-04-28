<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No session"]);
    exit;
}

if(!isset($_POST['id'], $_POST['imginicio'])){
    echo json_encode(["status"=>"error","msg"=>"Faltan datos"]);
    exit;
}

$email = $_SESSION['email'];

$stmtUser = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$stmtUser->bind_param("s",$email);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$userId = $user['id'];

$pelicula_id = $_POST['id'];
$titulo = $_POST['titulo'] ?? "";
$imginicio = $_POST['imginicio'];
$progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;

/* INSERT */
$stmt = $conn->prepare("
INSERT INTO continuar_viendo (user_id, user_email, pelicula_id, titulo, imginicio, progreso)
VALUES (?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
titulo=VALUES(titulo),
imginicio=VALUES(imginicio),
progreso=VALUES(progreso),
fecha=NOW()
");

$stmt->bind_param("issssi",$userId,$email,$pelicula_id,$titulo,$imginicio,$progreso);

if(!$stmt->execute()){
    echo json_encode(["status"=>"error","msg"=>$stmt->error]);
    exit;
}

/* =========================
   LIMITE 10 REGISTROS
========================= */

$limite = 10;

$delete = $conn->prepare("
DELETE FROM continuar_viendo 
WHERE user_id=?
AND idPrimaria NOT IN (
    SELECT idPrimaria FROM (
        SELECT idPrimaria FROM continuar_viendo
        WHERE user_id=?
        ORDER BY fecha DESC
        LIMIT $limite
    ) AS temp
)
");

if($delete){
    $delete->bind_param("ii", $userId, $userId);
    $delete->execute();
}


echo json_encode(["status"=>"ok"]);
exit;