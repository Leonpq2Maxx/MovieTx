<?php
session_start();
require_once "config.php";

$userId = $_SESSION['id'];

$nombre = $_POST['nombre'];

/* 🔒 CONTAR PERFILES ACTUALES */
$stmtCheck = $conn->prepare("SELECT COUNT(*) total FROM perfiles WHERE user_id=?");
$stmtCheck->bind_param("i",$userId);
$stmtCheck->execute();
$total = $stmtCheck->get_result()->fetch_assoc()['total'];

/* 🔒 OBTENER LIMITE */
$stmtLimit = $conn->prepare("SELECT max_perfiles FROM users WHERE id=?");
$stmtLimit->bind_param("i",$userId);
$stmtLimit->execute();
$max = $stmtLimit->get_result()->fetch_assoc()['max_perfiles'];

/* 🚫 BLOQUEO */
if($total >= ($max - 1)){
    die("Límite de perfiles alcanzado");
}

/* CARPETA DONDE SE GUARDAN LAS FOTOS */

$carpeta = "uploads/perfiles/";

/* CREAR CARPETA SI NO EXISTE */

if(!is_dir($carpeta)){
mkdir($carpeta,0777,true);
}

$fotoNombre = "default.png";

/* SI EL USUARIO SUBIÓ FOTO */

if(isset($_FILES['foto']) && $_FILES['foto']['error'] == 0){

$ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);

$fotoNombre = "perfil_".$userId."_".time().".".$ext;

$ruta = $carpeta.$fotoNombre;

move_uploaded_file($_FILES['foto']['tmp_name'],$ruta);

}

/* GUARDAR PERFIL EN BD */

$stmt = $conn->prepare("INSERT INTO perfiles (user_id,nombre,foto) VALUES (?,?,?)");
$stmt->bind_param("iss",$userId,$nombre,$fotoNombre);
$stmt->execute();

header("Location: perfiles.php");
exit;
?>