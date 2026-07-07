<?php
session_start();
require_once "config.php";

header("Content-Type: application/json");

// 🔥 ERRORES (solo desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 📥 JSON
$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$pass  = trim($data['pass'] ?? '');
$age   = intval($data['age'] ?? 0);
$dni   = trim($data['dni'] ?? '');
$clave = trim($data['clave'] ?? '');

// 🔎 VALIDACIONES
if(!$email || !$pass || !$dni || !$clave){
    echo json_encode(["status"=>"error","msg"=>"Datos incompletos"]);
    exit;
}

if($age < 18){
    echo json_encode(["status"=>"error","msg"=>"Debes ser mayor de edad"]);
    exit;
}

// 🔐 1. VALIDAR USUARIO (TABLA REAL: users)
$stmt = $conn->prepare("SELECT password, status, approved, paid FROM users WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows === 0){
    echo json_encode(["status"=>"error","msg"=>"Usuario no existe"]);
    exit;
}

$user = $res->fetch_assoc();

// 🔑 PASSWORD
if(!password_verify($pass, $user['password'])){
    echo json_encode(["status"=>"error","msg"=>"Contraseña incorrecta"]);
    exit;
}

// 🚫 VALIDACIONES DE TU SISTEMA (IMPORTANTE)
if($user['status'] !== 'active'){
    echo json_encode(["status"=>"error","msg"=>"Cuenta no activa"]);
    exit;
}

if(isset($user['approved']) && $user['approved'] === 'no'){
    echo json_encode(["status"=>"error","msg"=>"Cuenta no aprobada"]);
    exit;
}

if(isset($user['paid']) && $user['paid'] === 'no'){
    echo json_encode(["status"=>"error","msg"=>"Debes tener una suscripción activa"]);
    exit;
}

// 🔎 2. TABLA ADULTOS
$stmt = $conn->prepare("SELECT * FROM adultos WHERE email=? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();


// 🆕 PRIMER ACCESO → REGISTRAR
if($res->num_rows === 0){

    $claveHash = password_hash($clave, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO adultos (email, dni, clave_adulto, verificado) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("sss", $email, $dni, $claveHash);

    if($stmt->execute()){

        $_SESSION['adulto'] = true;

        echo json_encode([
            "status"=>"ok",
            "msg"=>"Registrado"
        ]);
    }else{
        echo json_encode([
            "status"=>"error",
            "msg"=>"Error al registrar"
        ]);
    }

    exit;
}


// 🔁 YA EXISTE → VALIDAR
$row = $res->fetch_assoc();

// 🔐 CLAVE ADULTO
if(!password_verify($clave, $row['clave_adulto'])){
    echo json_encode([
        "status"=>"error",
        "msg"=>"Clave +18 incorrecta"
    ]);
    exit;
}

// 🔐 VALIDAR DNI (extra seguridad)
if($dni !== $row['dni']){
    echo json_encode([
        "status"=>"error",
        "msg"=>"DNI incorrecto"
    ]);
    exit;
}

// 🔐 SESIÓN
$_SESSION['adulto'] = true;

// ✅ OK
echo json_encode([
    "status"=>"ok",
    "msg"=>"Acceso permitido"
]);