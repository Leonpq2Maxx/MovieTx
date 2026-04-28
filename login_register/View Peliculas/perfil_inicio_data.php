<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if(!isset($_SESSION['id']) || !isset($_SESSION['perfil_id'])){
    echo json_encode(["status"=>"error","msg"=>"No session"]);
    exit;
}

$userId   = $_SESSION['id'];
$perfilId = $_SESSION['perfil_id'];

/* =========================
   VALIDAR DATOS
========================= */
if(!isset($_POST['id'], $_POST['imginicio'])){
    echo json_encode(["status"=>"error","msg"=>"Faltan datos"]);
    exit;
}

/* =========================
   OBTENER NOMBRE PERFIL
========================= */
$stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? LIMIT 1");
$stmtPerfil->bind_param("i", $perfilId);
$stmtPerfil->execute();
$perfil = $stmtPerfil->get_result()->fetch_assoc();

$nombrePerfil = $perfil['nombre'] ?? 'Perfil';

/* =========================
   DATOS
========================= */
$pelicula_id = $_POST['id'];
$titulo      = $_POST['titulo'] ?? "";
$imginicio   = $_POST['imginicio'];
$progreso    = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;

/* =========================
   INSERT / UPDATE
========================= */
$stmt = $conn->prepare("
INSERT INTO perfiles_continuar_viendo 
(user_id, nombre_perfil, perfil_id, pelicula_id, titulo, imginicio, progreso)
VALUES (?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
titulo=VALUES(titulo),
imginicio=VALUES(imginicio),
progreso=VALUES(progreso),
fecha=NOW()
");

$stmt->bind_param(
    "isisssi",
    $userId,
    $nombrePerfil,
    $perfilId,
    $pelicula_id,
    $titulo,
    $imginicio,
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
DELETE FROM perfiles_continuar_viendo 
WHERE perfil_id=?
AND idPrimaria NOT IN (
    SELECT idPrimaria FROM (
        SELECT idPrimaria FROM perfiles_continuar_viendo
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
