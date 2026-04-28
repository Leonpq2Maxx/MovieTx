<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if(!isset($_SESSION['id'])){
    echo json_encode(["status"=>"error","msg"=>"No session"]);
    exit;
}

/* =========================
   VALIDAR PERFIL
========================= */
if(!isset($_SESSION['perfil_id'])){
    echo json_encode(["status"=>"error","msg"=>"No perfil seleccionado"]);
    exit;
}

$userId   = $_SESSION['id'];
$perfilId = $_SESSION['perfil_id'];

/* =========================
   OBTENER NOMBRE PERFIL
========================= */
$stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? AND user_id=? LIMIT 1");
$stmtPerfil->bind_param("ii", $perfilId, $userId);
$stmtPerfil->execute();
$resPerfil = $stmtPerfil->get_result();

if($resPerfil->num_rows === 0){
    echo json_encode(["status"=>"error","msg"=>"Perfil inválido"]);
    exit;
}

$perfilData   = $resPerfil->fetch_assoc();
$nombrePerfil = $perfilData['nombre'];

/* =========================
   VALIDAR DATOS
========================= */
if(!isset($_POST['id'], $_POST['imgserie'])){
    echo json_encode(["status"=>"error","msg"=>"Faltan datos"]);
    exit;
}

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
INSERT INTO perfiles_continuar_serie 
(user_id, perfil_id, nombre_perfil, serie_id, titulo, imgserie, progreso)
VALUES (?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
nombre_perfil=VALUES(nombre_perfil),
titulo=VALUES(titulo),
imgserie=VALUES(imgserie),
progreso=VALUES(progreso),
fecha=NOW()
");

$stmt->bind_param(
    "iissssi",
    $userId,
    $perfilId,
    $nombrePerfil,
    $serie_id,
    $titulo,
    $imgserie,
    $progreso
);

if(!$stmt->execute()){
    echo json_encode(["status"=>"error","msg"=>$stmt->error]);
    exit;
}

/* =========================
   LIMITE 10 POR PERFIL
========================= */
$limite = 10;

$delete = $conn->prepare("
DELETE FROM perfiles_continuar_serie 
WHERE perfil_id=?
AND idPrimaria NOT IN (
    SELECT idPrimaria FROM (
        SELECT idPrimaria FROM perfiles_continuar_serie
        WHERE perfil_id=?
        ORDER BY fecha DESC
        LIMIT $limite
    ) AS temp
)
");

if($delete){
    $delete->bind_param("ii", $perfilId, $perfilId);
    $delete->execute();
}

echo json_encode(["status"=>"ok"]);
exit;
