<?php
session_start();
require_once "config.php";

/* 🔒 VALIDAR SESIÓN */
if(!isset($_SESSION['id'])){
    die("No autorizado");
}

$userId = $_SESSION['id'];

/* 🔒 VALIDAR NOMBRE */
if(!isset($_POST['nombre']) || empty(trim($_POST['nombre']))){
    die("Nombre vacío");
}

$nombre = trim($_POST['nombre']);

/* =========================================================
   👶 TIPO DE PERFIL
========================================================= */

$tipo = "normal";

if(
    isset($_POST['tipo']) &&
    $_POST['tipo'] === "kids"
){
    $tipo = "kids";
}

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
if($total >= $max){
    die("Límite de perfiles alcanzado");
}

/* =========================================================
   👶 SOLO UN PERFIL KIDS
========================================================= */

if($tipo === "kids"){

    $stmtKids = $conn->prepare("
    SELECT id
    FROM perfiles
    WHERE user_id = ?
    AND tipo = 'kids'
    LIMIT 1
    ");

    $stmtKids->bind_param("i",$userId);
    $stmtKids->execute();

    if($stmtKids->get_result()->num_rows > 0){
        die("Ya existe un perfil KIDS");
    }

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

    // 🔥 NORMALIZAR EXTENSION
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

    // 🔥 SI NO TIENE EXTENSION
    if(empty($ext)){
        $ext = "png";
    }

    $fotoNombre = "perfil_".$userId."_".time().".".$ext;

    $ruta = $carpeta.$fotoNombre;

    // 🔥 MOVER ARCHIVO (SIN is_uploaded_file para evitar error con fetch)
    if(!move_uploaded_file($_FILES['foto']['tmp_name'],$ruta)){
        die("Error al guardar la imagen en carpeta");
    }
}

/* GUARDAR PERFIL EN BD */
$stmt = $conn->prepare("
INSERT INTO perfiles
(
user_id,
nombre,
foto,
tipo
)
VALUES
(
?,
?,
?,
?
)
");

$stmt->bind_param(
"isss",
$userId,
$nombre,
$fotoNombre,
$tipo
);

if(!$stmt->execute()){
    die("Error BD: " . $stmt->error);
}

/* ✅ RESPUESTA PARA FETCH (IMPORTANTE) */
echo "ok";
exit;
?>