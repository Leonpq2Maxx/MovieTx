<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No session"]);
    exit;
}

/* =========================
   VALIDAR DATOS
========================= */
if(!isset($_POST['id'], $_POST['imgserie'])){
    echo json_encode(["status"=>"error","msg"=>"Faltan datos"]);
    exit;
}

$email = $_SESSION['email'];

/* =========================
   OBTENER USER ID
========================= */
$stmtUser = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$stmtUser->bind_param("s",$email);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();

$userId = $user['id'];

/* =========================
   DATOS
========================= */
$serie_id = $_POST['id'];
$titulo   = $_POST['titulo'] ?? "";
$imgserie = $_POST['imgserie'];
$progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;

/* =========================
   INSERT / UPDATE
========================= */
$stmt = $conn->prepare("
INSERT INTO continuar_serie (user_id, user_email, serie_id, titulo, imgserie, progreso)
VALUES (?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
titulo=VALUES(titulo),
imgserie=VALUES(imgserie),
progreso=VALUES(progreso),
fecha=NOW()
");

$stmt->bind_param("issssi",$userId,$email,$serie_id,$titulo,$imgserie,$progreso);

if(!$stmt->execute()){
    echo json_encode(["status"=>"error","msg"=>$stmt->error]);
    exit;
}

/* =========================
   LIMITE 10 REGISTROS
========================= */
$limite = 10;

$delete = $conn->prepare("
DELETE FROM continuar_serie 
WHERE user_id=?
AND idPrimaria NOT IN (
    SELECT idPrimaria FROM (
        SELECT idPrimaria FROM continuar_serie
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