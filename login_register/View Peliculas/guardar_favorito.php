<?php
session_start();
require "../config.php";

if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No logueado"]);
    exit;
}

$email = $_SESSION['email'];
$perfilId = isset($_SESSION['perfil_id']) ? intval($_SESSION['perfil_id']) : 0;

/* 🔹 OBTENER NOMBRE USUARIO */
$stmtUser = $conn->prepare("SELECT name FROM users WHERE email=? LIMIT 1");
$stmtUser->bind_param("s",$email);
$stmtUser->execute();
$userName = $stmtUser->get_result()->fetch_assoc()['name'] ?? "Usuario";

/* 🔹 OBTENER NOMBRE PERFIL */
$perfilName = "Principal";
if($perfilId > 0){
    $stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? LIMIT 1");
    $stmtPerfil->bind_param("i",$perfilId);
    $stmtPerfil->execute();
    $perfilName = $stmtPerfil->get_result()->fetch_assoc()['nombre'] ?? "Perfil";
}

$movie_id = $_POST['movie_id'];
$imagen = $_POST['imagen'];
$titulo = $_POST['titulo'];
$tipo = $_POST['tipo'];

// verificar si ya existe
$check = $conn->prepare("SELECT id FROM favoritos WHERE user_email=? AND perfil_id=? AND movie_id=?");
$check->bind_param("sis",$email,$perfilId,$movie_id);
$check->execute();
$result = $check->get_result();

if($result->num_rows > 0){
    echo json_encode(["status"=>"exists"]);
    exit;
}

/* insertar */
$stmt = $conn->prepare("
INSERT INTO favoritos 
(user_email, user_name, perfil_id, perfil_name, movie_id, titulo, imagen, tipo, unico_favorito)
VALUES (?,?,?,?,?,?,?,?,?)
");

$fechaHoy = date("Y-m-d");

$stmt->bind_param("ssissssss",$email,$userName,$perfilId,$perfilName,$movie_id,$titulo,$imagen,$tipo,$fechaHoy);
$stmt->execute();

echo json_encode(["status"=>"success"]);
?>
