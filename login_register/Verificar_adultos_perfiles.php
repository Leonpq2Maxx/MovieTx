<?php
session_start();
require_once "config.php";

header("Content-Type: application/json");

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 🔥 PERFIL ACTIVO
if(!isset($_SESSION['perfil_id']) || !isset($_SESSION['id'])){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Perfil no detectado"
    ]);
    exit;
}

$perfilId = intval($_SESSION['perfil_id']);
$userId   = intval($_SESSION['id']);

// 📥 JSON
$data = json_decode(file_get_contents("php://input"), true);

$age   = intval($data['age'] ?? 0);
$dni   = trim($data['dni'] ?? '');
$clave = trim($data['clave'] ?? '');

// 🔎 VALIDACIONES
if(!$dni || !$clave){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Datos incompletos"
    ]);
    exit;
}

if($age < 18){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Debes ser mayor de edad"
    ]);
    exit;
}

// 🔥 OBTENER PERFIL
$stmt = $conn->prepare("
    SELECT nombre 
    FROM perfiles
    WHERE id=? AND user_id=?
    LIMIT 1
");

$stmt->bind_param("ii", $perfilId, $userId);
$stmt->execute();

$res = $stmt->get_result();

if($res->num_rows === 0){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Perfil inválido"
    ]);
    exit;
}

$perfil = $res->fetch_assoc();
$nombrePerfil = $perfil['nombre'];

// 🔎 VERIFICAR SI EXISTE
$stmt = $conn->prepare("
    SELECT *
    FROM adultos_perfiles
    WHERE perfil_id=?
    LIMIT 1
");

$stmt->bind_param("i", $perfilId);
$stmt->execute();

$res = $stmt->get_result();


// 🆕 PRIMER ACCESO
if($res->num_rows === 0){

    $hash = password_hash($clave, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO adultos_perfiles
        (
            perfil_id,
            user_id,
            nombre_perfil,
            dni,
            clave_adulto,
            verificado
        )
        VALUES (?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        "iisss",
        $perfilId,
        $userId,
        $nombrePerfil,
        $dni,
        $hash
    );

    if($stmt->execute()){

        $_SESSION['adulto_perfil'] = true;

        echo json_encode([
            "status"=>"ok",
            "msg"=>"Perfil registrado"
        ]);

    }else{

        echo json_encode([
            "status"=>"error",
            "msg"=>"Error al registrar perfil"
        ]);

    }

    exit;
}


// 🔁 VALIDAR EXISTENTE
$row = $res->fetch_assoc();

// 🔐 CLAVE
if(!password_verify($clave, $row['clave_adulto'])){

    echo json_encode([
        "status"=>"error",
        "msg"=>"Clave +18 incorrecta"
    ]);

    exit;
}

// 🔐 DNI
if($dni !== $row['dni']){

    echo json_encode([
        "status"=>"error",
        "msg"=>"DNI incorrecto"
    ]);

    exit;
}

// ✅ SESIÓN
$_SESSION['adulto_perfil'] = true;

// ✅ OK
echo json_encode([
    "status"=>"ok",
    "msg"=>"Acceso permitido"
]);