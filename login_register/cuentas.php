<?php
declare(strict_types=1);

session_start();
require_once "config.php";

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* =========================================================
   🔐 VALIDAR SESIÓN
========================================================= */

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit;
}

$email = trim((string)$_SESSION['email']);
$esPerfil = isset($_SESSION['perfil_id']);

/* =========================================================
   ⚡ HELPERS
========================================================= */

function jsonResponse(array $data): never {

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function clean(?string $value): string {

    return htmlspecialchars(
        trim((string)$value),
        ENT_QUOTES,
        'UTF-8'
    );
}

function ocultarCorreo(string $correo): string {

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return clean($correo);
    }

    [$user, $domain] = explode('@', $correo);

    $visible = substr($user, 0, 3);

    return $visible .
        str_repeat('*', max(4, strlen($user) - 3)) .
        '@' .
        $domain;
}

function safeFile(string $file): string {

    return basename(trim($file));
}

/* =========================================================
   👤 USUARIO
========================================================= */

$stmt = $conn->prepare("
SELECT
    id,
    name,
    email,
    password,
    foto,
    max_perfiles,
    plan,
    paid_until,
    auto_renew
FROM users
WHERE email = ?
LIMIT 1
");

if (!$stmt) {
    die("Error SQL");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user) {

    session_destroy();

    header("Location: index.php");

    exit;
}

$userId = (int)$user['id'];

$nombre = clean($user['name']);
$plan = $user['plan'] ?? 'basico';

$foto = !empty($user['foto'])
    ? (
        str_contains($user['foto'], 'uploads/')
            ? clean($user['foto'])
            : 'uploads/usuarios/' . safeFile($user['foto'])
    )
    : 'uploads/usuarios/default.png';

$maxPerfiles = max(
    1,
    (int)($user['max_perfiles'] ?? 1)
);

$emailOculto = ocultarCorreo($user['email']);

$hoy = date('Y-m-d');

$planActivo = (
    !empty($user['paid_until']) &&
    $user['paid_until'] >= $hoy
);

$diasRestantes = $planActivo
    ? max(
        0,
        (int)ceil(
            (
                strtotime($user['paid_until']) -
                time()
            ) / 86400
        )
    )
    : 0;

    $limites = [
    'basico'   => 2,
    'estandar' => 4,
    'premium'  => 6
];

$maxPerfiles = $limites[$plan] ?? 1;

/* =========================================================
   🧹 LIMPIAR DISPOSITIVOS
========================================================= */

$conn->query("
UPDATE dispositivos
SET is_active = 0
WHERE last_ping < NOW() - INTERVAL 2 MINUTE
");

/* =========================================================
   ⚡ API AJAX
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    $action = trim($_POST['action'] ?? '');

    /* =====================
   📸 CAMBIAR FOTO
===================== */

if($action === 'change_photo'){


if(!isset($_FILES['foto'])){

jsonResponse([
'success'=>false,
'message'=>'No se recibió imagen'
]);

}


$file = $_FILES['foto'];


/* VALIDAR */

$permitidos=[
'image/jpeg',
'image/png',
'image/webp'
];


if(!in_array($file['type'],$permitidos,true)){

jsonResponse([
'success'=>false,
'message'=>'Formato no permitido'
]);

}


if($file['size'] > 5 * 1024 * 1024){

jsonResponse([
'success'=>false,
'message'=>'La imagen supera 5MB'
]);

}



$extension = strtolower(
pathinfo(
$file['name'],
PATHINFO_EXTENSION
)
);



$nuevoNombre =
uniqid('foto_',true)
.'.'.$extension;



/* =====================
   PERFIL
===================== */

if($esPerfil){


$perfilId =
(int)$_SESSION['perfil_id'];



$stmt=$conn->prepare("
SELECT foto
FROM perfiles
WHERE id=?
AND user_id=?
");


$stmt->bind_param(
"ii",
$perfilId,
$userId
);


$stmt->execute();


$old =
$stmt->get_result()
->fetch_assoc();



if(
!empty($old['foto']) &&
$old['foto']!='default.png'
){

$oldPath =
__DIR__.
"/uploads/perfiles/".
safeFile($old['foto']);

if(file_exists($oldPath)){
unlink($oldPath);
}

}



$destino =
"uploads/perfiles/".$nuevoNombre;



move_uploaded_file(
$file['tmp_name'],
__DIR__.'/'.$destino
);



$stmt=$conn->prepare("
UPDATE perfiles
SET foto=?
WHERE id=?
AND user_id=?
");


$stmt->bind_param(
"sii",
$nuevoNombre,
$perfilId,
$userId
);



}

else{


/* =====================
   USUARIO NORMAL
===================== */


if(
!empty($user['foto']) &&
$user['foto']!='default.png'
){

$oldPath =
__DIR__.
"/uploads/usuarios/".
safeFile($user['foto']);


if(file_exists($oldPath)){
unlink($oldPath);
}

}



$destino =
"uploads/usuarios/".$nuevoNombre;


move_uploaded_file(
$file['tmp_name'],
__DIR__.'/'.$destino
);



$stmt=$conn->prepare("
UPDATE users
SET foto=?
WHERE id=?
");


$stmt->bind_param(
"si",
$destino,
$userId
);


}



$ok=$stmt->execute();



jsonResponse([

'success'=>$ok,

'message'=>$ok
?'Foto actualizada correctamente'
:'Error al guardar'

]);

}

    /* =====================
       🔒 VALIDAR PERFIL
    ===================== */

    $accionesBloqueadas = [
        'delete_profile',
        'toggle_device',
        'delete_device',
        'delete_all_devices',
        'cancel_plan',
        'edit_account',
        'logout_all',
        'delete_account'
    ];

    if (
        $esPerfil &&
        in_array($action, $accionesBloqueadas, true)
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'No autorizado'
        ]);
    }

        /* =====================
       🗑 DAR DE BAJA CUENTA
    ===================== */

    if ($action === 'delete_account') {

        $conn->begin_transaction();

        try {

            /* -----------------
               ELIMINAR FOTOS PERFILES
            ----------------- */

            $stmt = $conn->prepare("
            SELECT foto
            FROM perfiles
            WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $result = $stmt->get_result();

            while ($perfil = $result->fetch_assoc()) {

                if (
                    !empty($perfil['foto']) &&
                    $perfil['foto'] !== 'default.png'
                ) {

                    $archivo = __DIR__
                        . "/uploads/perfiles/"
                        . safeFile($perfil['foto']);

                    if (file_exists($archivo)) {
                        @unlink($archivo);
                    }
                }
            }

            /* -----------------
               ELIMINAR FOTO USUARIO
            ----------------- */

            if (
                !empty($user['foto']) &&
                $user['foto'] !== 'default.png'
            ) {

                $fotoUsuario = __DIR__
                    . "/uploads/usuarios/"
                    . safeFile($user['foto']);

                if (file_exists($fotoUsuario)) {
                    @unlink($fotoUsuario);
                }
            }

            /* -----------------
   ELIMINAR PROGRESO SERIES
----------------- */

$stmt = $conn->prepare("
DELETE FROM user_progress
WHERE email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR PROGRESO SERIES PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM user_progress_perfil
WHERE user_id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();

/* -----------------
   ELIMINAR PROGRESO PELÍCULAS
----------------- */

$stmt = $conn->prepare("
DELETE FROM progreso_peliculas
WHERE email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR PROGRESO PELÍCULAS PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM perfil_progreso_peliculas
WHERE user_id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();

/* -----------------
   ELIMINAR HISTORIAL PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM perfil_historial
WHERE user_email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR FAVORITOS PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM perfil_favorito
WHERE user_email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR CONTINUAR VIENDO PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM perfiles_continuar_viendo
WHERE user_id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();

/* -----------------
   ELIMINAR CONTINUAR SERIES PERFILES
----------------- */

$stmt = $conn->prepare("
DELETE FROM perfiles_continuar_serie
WHERE user_id = ?
");

$stmt->bind_param("i", $userId);
$stmt->execute();

/* -----------------
   ELIMINAR HISTORIAL USUARIO
----------------- */

$stmt = $conn->prepare("
DELETE FROM historial
WHERE user_email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR FAVORITOS USUARIO
----------------- */

$stmt = $conn->prepare("
DELETE FROM favoritos
WHERE user_email = ?
");

$stmt->bind_param("s", $email);
$stmt->execute();

/* -----------------
   ELIMINAR CONTINUAR VIENDO USUARIO
----------------- */

$stmt = $conn->prepare("
DELETE FROM continuar_viendo
WHERE user_id = ?
OR user_email = ?
");

$stmt->bind_param("is", $userId, $email);
$stmt->execute();

/* -----------------
   ELIMINAR CONTINUAR SERIES USUARIO
----------------- */

$stmt = $conn->prepare("
DELETE FROM continuar_serie
WHERE user_id = ?
OR user_email = ?
");

$stmt->bind_param("is", $userId, $email);
$stmt->execute();

            /* -----------------
               ELIMINAR PERFILES
            ----------------- */

            $stmt = $conn->prepare("
            DELETE FROM perfiles
            WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            /* -----------------
               ELIMINAR DISPOSITIVOS
            ----------------- */

            $stmt = $conn->prepare("
            DELETE FROM dispositivos
            WHERE user_id = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            /* -----------------
               ELIMINAR USUARIO
            ----------------- */

            $stmt = $conn->prepare("
            DELETE FROM users
            WHERE id = ?
            ");

            $stmt->bind_param("i", $userId);
            $stmt->execute();

            $conn->commit();

            session_destroy();

            jsonResponse([
                'success' => true,
                'message' => 'Cuenta eliminada correctamente'
            ]);

        } catch (Throwable $e) {

            $conn->rollback();

            jsonResponse([
                'success' => false,
                'message' => 'No se pudo eliminar la cuenta'
            ]);
        }
    }

    /* =====================
       ❌ ELIMINAR PERFIL
    ===================== */

    if ($action === 'delete_profile') {

        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {

            jsonResponse([
                'success' => false,
                'message' => 'ID inválido'
            ]);
        }

        $stmt = $conn->prepare("
        SELECT foto
        FROM perfiles
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
        ");

        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();

        $perfil = $stmt->get_result()->fetch_assoc();

        if (!$perfil) {

            jsonResponse([
                'success' => false,
                'message' => 'Perfil no encontrado'
            ]);
        }

        if (
            !empty($perfil['foto']) &&
            $perfil['foto'] !== 'default.png'
        ) {

            $path = __DIR__ .
                "/uploads/perfiles/" .
                safeFile($perfil['foto']);

            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $delete = $conn->prepare("
        DELETE FROM perfiles
        WHERE id = ?
        AND user_id = ?
        ");

        $delete->bind_param("ii", $id, $userId);

        $ok = $delete->execute();

        jsonResponse([
            'success' => $ok,
            'message' => $ok
                ? 'Perfil eliminado'
                : 'No se pudo eliminar'
        ]);
    }

    /* =====================
       ✏️ EDITAR CUENTA
    ===================== */

    if ($action === 'edit_account') {

    $passActual = trim($_POST['pass_actual'] ?? '');
    $passNueva  = trim($_POST['pass_nueva'] ?? '');

    if (
        empty($passActual) ||
        empty($passNueva)
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'Faltan datos'
        ]);
    }

    if (
        !password_verify(
            $passActual,
            $user['password']
        )
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'Contraseña actual incorrecta'
        ]);
    }

    if (strlen($passNueva) < 8) {

        jsonResponse([
            'success' => false,
            'message' => 'La nueva contraseña debe tener al menos 8 caracteres'
        ]);
    }

    if (
        password_verify(
            $passNueva,
            $user['password']
        )
    ) {

        jsonResponse([
            'success' => false,
            'message' => 'La nueva contraseña no puede ser igual a la actual'
        ]);
    }

    $nuevoHash = password_hash(
        $passNueva,
        PASSWORD_DEFAULT
    );

    $update = $conn->prepare("
    UPDATE users
    SET password = ?
    WHERE id = ?
    ");

    $update->bind_param(
        "si",
        $nuevoHash,
        $userId
    );

    $ok = $update->execute();

    if ($ok) {

        session_destroy();

        jsonResponse([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente'
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Error al actualizar la contraseña'
    ]);
}

    /* =====================
       📱 TOGGLE DEVICE
    ===================== */

    if ($action === 'toggle_device') {

        $id = (int)($_POST['id'] ?? 0);

        $estado = (int)($_POST['estado'] ?? 0);

        $estado = $estado === 1 ? 1 : 0;

        $stmt = $conn->prepare("
        UPDATE dispositivos
        SET blocked = ?
        WHERE id = ?
        AND user_id = ?
        ");

        $stmt->bind_param(
            "iii",
            $estado,
            $id,
            $userId
        );

        $ok = $stmt->execute();

        jsonResponse([
            'success' => $ok,
            'message' => $ok
                ? 'Dispositivo actualizado'
                : 'No se pudo actualizar'
        ]);
    }

    /* =====================
       🗑 ELIMINAR DEVICE
    ===================== */

    if ($action === 'delete_device') {

        $id = (int)($_POST['id'] ?? 0);

        $stmt = $conn->prepare("
        DELETE FROM dispositivos
        WHERE id = ?
        AND user_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $id,
            $userId
        );

        $ok = $stmt->execute();

        jsonResponse([
            'success' => $ok,
            'message' => $ok
                ? 'Dispositivo eliminado'
                : 'No se pudo eliminar'
        ]);
    }

    /* =====================
       🗑 TODOS LOS DEVICES
    ===================== */

    if ($action === 'delete_all_devices') {

        $stmt = $conn->prepare("
        DELETE FROM dispositivos
        WHERE user_id = ?
        ");

        $stmt->bind_param("i", $userId);

        $ok = $stmt->execute();

        jsonResponse([
            'success' => $ok,
            'message' => $ok
                ? 'Dispositivos eliminados'
                : 'Error'
        ]);
    }

    /* =====================
       ❌ CANCELAR PLAN
    ===================== */

    if ($action === 'cancel_plan') {

        $stmt = $conn->prepare("
        UPDATE users
        SET auto_renew = 0
        WHERE id = ?
        ");

        $stmt->bind_param("i", $userId);

        $ok = $stmt->execute();

        jsonResponse([
            'success' => $ok,
            'message' => $ok
                ? 'Plan cancelado'
                : 'Error'
        ]);
    }

    /* =====================
       🚪 CERRAR SESIONES
    ===================== */

    if ($action === 'logout_all') {

        $stmt = $conn->prepare("
        UPDATE dispositivos
        SET is_active = 0
        WHERE user_id = ?
        ");

        $stmt->bind_param("i", $userId);

        $ok = $stmt->execute();

        session_destroy();

        jsonResponse([
            'success' => $ok,
            'message' => 'Sesiones cerradas'
        ]);
    }

    jsonResponse([
        'success' => false,
        'message' => 'Acción inválida'
    ]);
}

/* =========================================================
   👥 PERFILES
========================================================= */

$stmt = $conn->prepare("
SELECT
    id,
    nombre,
    foto
FROM perfiles
WHERE user_id = ?
ORDER BY id ASC
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$perfiles = $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

/* =========================================================
   📱 DISPOSITIVOS
========================================================= */

$stmt = $conn->prepare("
SELECT
    id,
    device_name,
    browser,
    device_type,
    os,
    ip_address,
    country,
    city,
    login_time,
    last_active,
    blocked,
    is_active
FROM dispositivos
WHERE user_id = ?
ORDER BY last_active DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();

$dispositivos = $stmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$seccion = trim($_GET['sec'] ?? 'dashboard');

$seccionesValidas = [
    'dashboard',
    'perfiles',
    'plan',
    'dispositivos',
    'seguridad'
];

if (!in_array($seccion, $seccionesValidas, true)) {
    $seccion = 'dashboard';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0, viewport-fit=cover"
>
<link
rel="icon"
type="image/png"
href="../Logo/Logo Nuevo.png">

<title>MovieTx • Cuenta</title>

<link 
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">

<script 
src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js">
</script>

<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet"
>

<style>

/* =========================================================
   🌌 ROOT
========================================================= */

:root{

--bg:#050816;
--card:#101728;
--card2:#131d33;
--line:#1f2c49;

--text:#ffffff;
--muted:#93a0be;

--primary:#6d5cff;
--primary2:#8c7bff;

--danger:#ff4f7b;
--success:#3ad29f;

--radius:24px;

--shadow:
0 10px 40px rgba(0,0,0,.45);

--blur:18px;

}

/* =========================================================
   🌌 RESET
========================================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

html{
scroll-behavior:smooth;
-webkit-text-size-adjust:100%;
}

body{

font-family:'Inter',sans-serif;

background:
radial-gradient(circle at top left,
rgba(109,92,255,.20),
transparent 30%),

radial-gradient(circle at bottom right,
rgba(58,210,159,.12),
transparent 30%),

var(--bg);

color:var(--text);

min-height:100vh;

overflow-x:hidden;

-webkit-font-smoothing:antialiased;
text-rendering:optimizeLegibility;

}

/* =========================================================
   ✨ SCROLL
========================================================= */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{
background:#27365d;
border-radius:20px;
}

/* =========================================================
   🔥 APP LAYOUT
========================================================= */

.app{

display:grid;
grid-template-columns:290px 1fr;

min-height:100vh;
}

/* =========================================================
   📱 SIDEBAR
========================================================= */

.sidebar{

position:sticky;
top:0;

height:100vh;

padding:28px 20px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.03),
rgba(255,255,255,.01)
);

border-right:1px solid rgba(255,255,255,.06);

backdrop-filter:blur(var(--blur));

z-index:100;

overflow-y:auto;

}

/* =========================================================
   🌟 BRAND
========================================================= */

.brand{

display:flex;
align-items:center;
gap:14px;

margin-bottom:35px;
}

.brand img{

width:55px;
height:55px;

object-fit:cover;
border-radius:18px;

flex-shrink:0;
}

.brand h1{

font-size:22px;
font-weight:800;

letter-spacing:.4px;
}

/* =========================================================
   👤 USER BOX
========================================================= */

.user-box{

display:flex;
align-items:center;
gap:15px;

padding:18px;

background:rgba(255,255,255,.04);

border:1px solid rgba(255,255,255,.05);

border-radius:22px;

margin-bottom:28px;
}

.user-box img{

width:65px;
height:65px;

border-radius:50%;

object-fit:cover;

border:3px solid var(--primary);

flex-shrink:0;
}

.user-box h3{

font-size:16px;
margin-bottom:4px;
}

.user-box p{

font-size:13px;
color:var(--muted);

word-break:break-word;
line-height:1.4;
}

/* =========================================================
   📌 NAV
========================================================= */

.nav{

display:flex;
flex-direction:column;
gap:12px;
}

.nav a{

display:flex;
align-items:center;
gap:14px;

padding:16px 18px;

border-radius:18px;

text-decoration:none;

font-weight:600;
font-size:15px;

color:#d7def1;

transition:
transform .25s ease,
background .25s ease,
box-shadow .25s ease;

will-change:transform;
}

.nav a:hover{

transform:translateX(4px);

background:rgba(255,255,255,.05);
}

.nav a.active{

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

color:#fff;

box-shadow:
0 10px 30px rgba(109,92,255,.30);
}

/* =========================================================
   📦 CONTENT
========================================================= */

.content{

padding:35px;

display:flex;
flex-direction:column;
gap:25px;

min-width:0;
}

/* =========================================================
   🎯 HERO
========================================================= */

.hero{

display:flex;
justify-content:space-between;
align-items:center;

gap:20px;
flex-wrap:wrap;

padding:35px;

background:
linear-gradient(
135deg,
rgba(109,92,255,.20),
rgba(58,210,159,.08)
);

border:1px solid rgba(255,255,255,.06);

border-radius:30px;

box-shadow:var(--shadow);

overflow:hidden;
}

.hero-left{

display:flex;
align-items:center;
gap:22px;

min-width:0;
}

.hero-left img{

width:95px;
height:95px;

border-radius:28px;

object-fit:cover;

border:4px solid rgba(255,255,255,.10);

flex-shrink:0;
}

.hero h2{

font-size:32px;
margin-bottom:6px;

line-height:1.1;
}

.hero p{

color:var(--muted);

line-height:1.5;

word-break:break-word;
}

.badge{

display:inline-flex;
align-items:center;
gap:8px;

padding:10px 16px;

background:rgba(58,210,159,.15);

border:1px solid rgba(58,210,159,.35);

border-radius:999px;

color:#7ef5c9;

font-weight:700;
font-size:13px;

margin-top:12px;
}

/* =========================================================
   📊 GRID
========================================================= */

.grid{

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(260px,1fr));

gap:22px;
}

/* =========================================================
   📦 CARD
========================================================= */

.card{

background:
linear-gradient(
180deg,
rgba(255,255,255,.04),
rgba(255,255,255,.02)
);

border:1px solid rgba(255,255,255,.06);

border-radius:28px;

padding:24px;

box-shadow:var(--shadow);

backdrop-filter:blur(var(--blur));

overflow:hidden;
}

.card h3{

font-size:18px;
margin-bottom:20px;
}

/* =========================================================
   📈 STATS
========================================================= */

.stat{

display:flex;
justify-content:space-between;
align-items:center;

padding:18px;

background:rgba(255,255,255,.04);

border-radius:20px;

margin-bottom:14px;

gap:15px;
}

.stat small{

display:block;

color:var(--muted);

margin-bottom:4px;
}

.stat strong{

font-size:22px;
}

/* =========================================================
   👥 PROFILES
========================================================= */

.profile-grid{

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(180px,1fr));

gap:18px;
}

.profile{

position:relative;

padding:20px;

border-radius:24px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:1px solid rgba(255,255,255,.06);

text-align:center;

transition:
transform .30s ease,
border-color .30s ease;

overflow:hidden;
}

.profile:hover{

transform:translateY(-6px);

border-color:
rgba(109,92,255,.45);
}

.profile img{

width:95px;
height:95px;

border-radius:26px;

object-fit:cover;

margin-bottom:15px;

border:3px solid rgba(255,255,255,.08);
}

.profile p{

font-weight:700;
margin-bottom:6px;
}

.profile span{

font-size:13px;
color:var(--muted);
}

.profile button{

position:absolute;
top:12px;
right:12px;

width:34px;
height:34px;

border:none;
border-radius:50%;

background:rgba(255,79,123,.18);

color:#fff;

cursor:pointer;

font-size:15px;

transition:.25s;
}

.profile button:hover{

background:rgba(255,79,123,.35);
}

/* =========================================================
   ➕ ADD PROFILE
========================================================= */

.profile.add{

display:flex;
flex-direction:column;
align-items:center;
justify-content:center;

cursor:pointer;
}

.profile.add .plus{

width:75px;
height:75px;

display:flex;
align-items:center;
justify-content:center;

border-radius:24px;

font-size:40px;
font-weight:700;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

margin-bottom:14px;
}

/* =========================================================
   📱 DEVICE
========================================================= */

.device{

display:flex;
justify-content:space-between;
align-items:center;

gap:20px;
flex-wrap:wrap;

padding:18px;

margin-bottom:16px;

background:rgba(255,255,255,.03);

border-radius:22px;
}

.device h4{

margin-bottom:6px;

line-height:1.4;
}

.device p{

font-size:13px;
color:var(--muted);
line-height:1.6;
}

.device-actions{

display:flex;
gap:10px;
flex-wrap:wrap;
}

/* =========================================================
   🔘 BUTTONS
========================================================= */

.btn{

border:none;

padding:14px 18px;

border-radius:16px;

font-weight:700;

font-size:14px;

cursor:pointer;

transition:
transform .25s ease,
opacity .25s ease;

font-family:inherit;

min-height:50px;
}

.btn:hover{

transform:translateY(-2px);
}

.btn:active{

transform:scale(.98);
}

.btn-primary{

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

color:#fff;
}

.btn-danger{

background:
linear-gradient(
135deg,
#ff4f7b,
#ff6d57
);

color:#fff;
}

.btn-dark{

background:#202c49;
color:#fff;
}

/* =========================================================
   📊 PROGRESS
========================================================= */

.progress{

height:14px;

background:#1f2943;

border-radius:999px;

overflow:hidden;

margin:18px 0 25px;
}

.progress div{

height:100%;

border-radius:999px;

background:
linear-gradient(
90deg,
var(--success),
#72ffd0
);
}

/* =========================================================
   ✨ MODAL
========================================================= */

.modal{

position:fixed;
inset:0;

display:none;
align-items:center;
justify-content:center;

padding:20px;

background:rgba(0,0,0,.70);

backdrop-filter:blur(8px);

z-index:9999;
}

.modal.active{
display:flex;
}

.modal-box{

width:100%;
max-width:450px;

background:#111a2d;

border:1px solid rgba(255,255,255,.08);

border-radius:30px;

padding:28px;

animation:zoom .25s ease;
}

@keyframes zoom{

from{
opacity:0;
transform:scale(.9);
}

to{
opacity:1;
transform:scale(1);
}

}

.modal-box h2{

margin-bottom:20px;
}

.input{

width:100%;

height:58px;

padding:0 18px;

border:none;

outline:none;

margin-bottom:15px;

border-radius:16px;

background:#1b2742;

color:#fff;

font-size:15px;

transition:
border .25s ease,
background .25s ease;

font-family:inherit;
}

.input:focus{

border:1px solid
rgba(109,92,255,.6);

background:#22304f;
}

.input::placeholder{
color:#8390b2;
}

/* =========================================================
   📱 MOBILE HEADER
========================================================= */

.mobile-top{
display:none;
}

/* =========================================================
   💻 PC / DESKTOP
========================================================= */

@media (min-width:1200px){

.content{
padding:40px;
}

.grid{
grid-template-columns:
repeat(auto-fit,minmax(320px,1fr));
}

.hero{
padding:40px;
}

.card{
padding:28px;
}

.profile-grid{
grid-template-columns:
repeat(auto-fit,minmax(220px,1fr));
}

}

/* =========================================================
   🤖 ANDROID
========================================================= */

@media screen and (max-width:900px){

.app{
grid-template-columns:1fr;
}

.sidebar{

position:fixed;

top:0;
left:-100%;

width:280px;

height:100vh;

transition:left .35s ease;

background:#0b1120;

box-shadow:
0 0 40px rgba(0,0,0,.55);
}

.sidebar.active{
left:0;
}

.mobile-top{

position:fixed;

top:0;
left:0;
right:0;

height:68px;

display:flex;
align-items:center;
justify-content:space-between;

padding:0 18px;

background:rgba(5,8,22,.96);

backdrop-filter:blur(18px);

border-bottom:
1px solid rgba(255,255,255,.05);

z-index:300;
}

.mobile-top button{

background:none;
border:none;

color:#fff;

font-size:28px;
}

.content{
padding:88px 16px 20px;
}

.hero{

padding:24px;

border-radius:26px;
}

.hero-left{

flex-direction:column;
align-items:flex-start;

gap:16px;
}

.hero-left img{

width:78px;
height:78px;
}

.hero h2{
font-size:24px;
}

.grid{
grid-template-columns:1fr;
}

.profile-grid{
grid-template-columns:1fr 1fr;
gap:14px;
}

.card{
padding:20px;
border-radius:24px;
}

.device{

flex-direction:column;
align-items:flex-start;
}

.device-actions{

width:100%;
}

.device-actions .btn{

flex:1;
min-width:120px;
}

.btn{

min-height:52px;
font-size:15px;
}

}

/* =========================================================
   🍎 IPHONE
========================================================= */

@media screen 
and (max-width:430px)
and (-webkit-min-device-pixel-ratio:2){

body{

-webkit-overflow-scrolling:touch;
}

.content{

padding:
calc(85px + env(safe-area-inset-top))
14px
calc(20px + env(safe-area-inset-bottom));
}

.mobile-top{

height:74px;

padding-left:18px;
padding-right:18px;

padding-top:env(safe-area-inset-top);
}

.sidebar{

padding-top:
calc(25px + env(safe-area-inset-top));
}

.hero{

padding:20px;

border-radius:24px;
}

.hero-left{

align-items:center;
text-align:center;

width:100%;
}

.hero-left img{

width:74px;
height:74px;

border-radius:24px;
}

.hero h2{

font-size:22px;
}

.hero p{

font-size:14px;
}

.badge{

font-size:12px;

padding:9px 14px;
}

.card{

padding:18px;

border-radius:22px;
}

.profile-grid{

grid-template-columns:1fr 1fr;
gap:12px;
}

.profile{

padding:16px;
}

.profile img{

width:80px;
height:80px;
}

.profile button{

width:30px;
height:30px;
}

.device{

padding:15px;
}

.device p{

font-size:12px;
}

.btn{

width:100%;

min-height:50px;

font-size:14px;

border-radius:14px;
}

.device-actions{

width:100%;
flex-direction:column;
}

.modal{

padding:16px;
}

.modal-box{

padding:22px;

border-radius:24px;
}

.input{

height:54px;

font-size:14px;
}

}

/* =========================================================
   📱 SMALL DEVICES
========================================================= */

@media(max-width:340px){

.hero h2{
font-size:19px;
}

.profile-grid{
grid-template-columns:1fr;
}

.user-box{
flex-direction:column;
text-align:center;
}

.nav a{
font-size:14px;
padding:14px;
}

}

/* =========================================================
   🍎 SAFE AREA
========================================================= */

@supports(padding:max(0px)){

.mobile-top{

padding-top:max(
0px,
env(safe-area-inset-top)
);
}

.content{

padding-bottom:max(
20px,
env(safe-area-inset-bottom)
);
}

}

</style>

</head>

<body>

<!-- ======================================================
     📱 MOBILE TOP
====================================================== -->

<div class="mobile-top">

<h3>MovieTx</h3>

<button
type="button"
onclick="toggleSidebar()"
>
☰
</button>

</div>

<!-- ======================================================
     🌌 APP
====================================================== -->

<div class="app">

<!-- ======================================================
     📱 SIDEBAR
====================================================== -->

<aside class="sidebar" id="sidebar">

<div class="brand">

<img
src="Logo/Logo Nuevo.png"
alt="MovieTx"
>

<div>
<h1>MovieTx</h1>
</div>

</div>

<div class="user-box">

<img
src="<?= clean($foto) ?>"
alt="Usuario"
>

<div>
<h3><?= $nombre ?></h3>
<p><?= clean($user['email']) ?></p>
</div>

</div>

<nav class="nav">

<a
href="?sec=dashboard"
class="<?= $seccion === 'dashboard' ? 'active' : '' ?>"
>
🏠 Dashboard
</a>

<a
href="?sec=perfiles"
class="<?= $seccion === 'perfiles' ? 'active' : '' ?>"
>
👥 Perfiles
</a>

<a
href="?sec=plan"
class="<?= $seccion === 'plan' ? 'active' : '' ?>"
>
💳 Plan
</a>

<a
href="?sec=dispositivos"
class="<?= $seccion === 'dispositivos' ? 'active' : '' ?>"
>
📱 Dispositivos
</a>

<?php if (!$esPerfil): ?>

<a
href="?sec=seguridad"
class="<?= $seccion === 'seguridad' ? 'active' : '' ?>"
>
🔐 Seguridad
</a>

<?php endif; ?>

</nav>

</aside>

<!-- ======================================================
     📦 CONTENT
====================================================== -->

<main class="content">

<div class="hero">

<div class="hero-left">

<img
src="<?= clean($foto) ?>"
alt="Perfil"
>

<div>

<h2><?= $nombre ?></h2>

<p><?= clean($emailOculto) ?></p>

<div class="badge">

<?= $planActivo
? "✔ Plan activo • {$diasRestantes} días"
: "⚠ Plan vencido"
?>

</div>

<div class="badge" style="margin-top:10px;">
    🧾 <?= "Plan: " . ucfirst($plan) ?>
</div>

</div>

</div>


<button
class="btn btn-dark"
type="button"
onclick="logoutAll()"
>

🚪 Cerrar sesiones

</button>

</div>

<?php if ($seccion === 'dashboard'): ?>

<div class="grid">

<div class="card">

<h3>📊 Resumen</h3>

<div class="stat">
<div>
<small>Perfiles</small>
<strong><?= count($perfiles) + 1 ?></strong>
</div>
</div>

<div class="stat">
<div>
<small>Dispositivos</small>
<?php
$activos = array_filter(
    $dispositivos,
    fn($d) => (int)$d['is_active'] === 1
);
?>

<strong><?= count($activos) ?></strong>
</div>
</div>

<div class="stat">
<div>
<small>Días restantes</small>
<strong><?= $diasRestantes ?></strong>
</div>
</div>

</div>

<div class="card">

<h3>💎 Estado del plan</h3>

<div class="progress">
<div
style="
width:
<?= min(100, $diasRestantes * 3.3) ?>%;
"
>
</div>
</div>

<p
style="
color:var(--muted);
line-height:1.7;
"
>

<?= $planActivo
? "Tu suscripción está activa."
: "Tu suscripción se encuentra vencida."
?>

</p>

<br>

<?php if (!$esPerfil): ?>

<button
class="btn btn-primary"
type="button"
onclick="location.href='pago.php'"
>

💳 Gestionar plan

</button>

<?php endif; ?>

</div>

</div>

<?php endif; ?>

<?php if ($seccion === 'perfiles'): ?>

<div class="card">

<h3>👥 Gestión de perfiles</h3>

<div class="profile-grid">

<div class="profile">

<img
src="<?= clean($foto) ?>"
alt="Principal"
>

<p><?= $nombre ?></p>

<span>Principal</span>

</div>

<?php foreach ($perfiles as $p):

$fotoPerfil = !empty($p['foto'])
? 'uploads/usuarios/' .
safeFile($p['foto'])
: 'uploads/perfiles/default.png';

?>

<div class="profile">

<img
src="<?= clean($fotoPerfil) ?>"
alt="Perfil"
>

<p><?= clean($p['nombre']) ?></p>

<span>Perfil</span>

<?php if (!$esPerfil): ?>

<button
type="button"
onclick="deleteProfile(<?= (int)$p['id'] ?>)"
>
✕
</button>

<?php endif; ?>

</div>

<?php endforeach; ?>

<?php if (
!$esPerfil &&
(count($perfiles) + 1) < $maxPerfiles
): ?>

<div
class="profile add"
onclick="location.href='crear_perfil.php'"
>

<div class="plus">+</div>

<p>Agregar perfil</p>

</div>

<?php endif; ?>

</div>

</div>

<?php endif; ?>

<?php if ($seccion === 'plan'): ?>

<div class="card">

<h3>💳 Tu plan</h3>

<div class="progress">

<div
style="
width:
<?= min(100, $diasRestantes * 3.3) ?>%;
"
>
</div>

</div>

<div class="stat">

<div>

<small>Expira</small>

<strong>

<?= !empty($user['paid_until'])
? date(
'd/m/Y',
strtotime($user['paid_until'])
)
: 'Sin fecha'
?>

</strong>

</div>

</div>

<div class="stat">

<div>

<small>Renovación automática</small>

<strong>

<?= $user['auto_renew']
? 'Activa'
: 'Desactivada'
?>

</strong>

</div>

</div>

<div
style="
display:flex;
gap:12px;
flex-wrap:wrap;
"
>

<?php if (!$esPerfil): ?>

<button
class="btn btn-primary"
type="button"
onclick="location.href='pago.php'"
>

💎 Renovar

</button>

<?php endif; ?>

<?php if (!$esPerfil): ?>

<button
class="btn btn-danger"
type="button"
onclick="cancelPlan()"
>

❌ Cancelar plan

</button>

<?php endif; ?>

</div>

</div>

<?php endif; ?>

<?php if ($seccion === 'dispositivos'): ?>

<div class="card">

<h3>📱 Dispositivos conectados</h3>

<?php if (empty($dispositivos)): ?>

<p style="color:var(--muted);">
No hay dispositivos registrados.
</p>

<?php endif; ?>

<?php foreach ($dispositivos as $d): ?>

<div class="device">

<div>

<h4>

<?= clean($d['device_name']) ?>

<?= $d['is_active']
? '🟢'
: '⚫'
?>

</h4>

<p>

🌐 Navegador:
<?= clean($d['browser']) ?>

<br>

💻 Sistema:
<?= clean($d['os']) ?>

<br>

📱 Tipo:
<?= clean($d['device_type']) ?>

<br>

📍 Ubicación:
<?= clean($d['city']) ?>,
<?= clean($d['country']) ?>

<br>

🌍 IP:
<?= clean($d['ip_address']) ?>

<br>

🕒 Inicio sesión:
<?= date(
'd/m/Y H:i',
strtotime($d['login_time'])
) ?>

<br>

⚡ Última actividad:
<?= date(
'd/m/Y H:i',
strtotime($d['last_active'])
) ?>

</p>

</div>

<div class="device-actions">

<?php if (!$esPerfil): ?>

<button
class="btn btn-dark"
type="button"
onclick="toggleDevice(
<?= (int)$d['id'] ?>,
<?= $d['blocked'] ? 0 : 1 ?>
)"
>

<?= $d['blocked']
? 'Desbloquear'
: 'Bloquear'
?>

</button>

<button
class="btn btn-danger"
type="button"
onclick="deleteDevice(
<?= (int)$d['id'] ?>
)"
>

Eliminar

</button>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

<?php if (!$esPerfil): ?>

<button
class="btn btn-danger"
type="button"
onclick="deleteAllDevices()"
>

🗑 Eliminar todos

</button>

<?php endif; ?>

</div>

<?php endif; ?>

<?php if (
$seccion === 'seguridad' &&
!$esPerfil
): ?>

<div class="card">

<h3>🔐 Seguridad de la cuenta</h3>

<p style="
color:var(--muted);
margin-bottom:20px;
line-height:1.7;
">
Por seguridad el correo electrónico no puede modificarse desde MovieTx.
Si necesitás cambiarlo deberás contactar al soporte.
</p>

<div class="stat">
<div>
<small>Correo asociado</small>
<strong><?= clean($emailOculto) ?></strong>
</div>
</div>

<br>

<h3 style="margin-bottom:15px;">
📸 Foto de perfil
</h3>


<div style="
display:flex;
align-items:center;
gap:20px;
flex-wrap:wrap;
">

<img 
src="<?= clean($foto) ?>"
id="previewFotoSeguridad"
style="
width:90px;
height:90px;
border-radius:50%;
object-fit:cover;
border:3px solid var(--primary);
">


<div style="flex:1;">

<input
type="file"
id="fotoSeguridad"
accept="image/*"
hidden
>

<button
class="btn btn-dark"
style="width:100%;"
onclick="document.getElementById('fotoSeguridad').click()"
>
📁 Seleccionar foto
</button>


<button
class="btn btn-primary"
style="width:100%;margin-top:10px;"
onclick="cambiarFoto()"
>

📸 Cambiar foto

</button>


</div>

</div>

<br>

<label style="display:block;margin-bottom:8px;">
🔑 Contraseña actual
</label>

<input
type="password"
class="input"
id="passActual"
placeholder="Ingresá tu contraseña actual"
autocomplete="current-password"
>

<label style="display:block;margin-bottom:8px;">
🛡 Nueva contraseña
</label>

<input
type="password"
class="input"
id="passNueva"
placeholder="Mínimo 8 caracteres"
autocomplete="new-password"
>

<label style="display:block;margin-bottom:8px;">
✅ Confirmar contraseña
</label>

<input
type="password"
class="input"
id="passNueva2"
placeholder="Repetir nueva contraseña"
autocomplete="new-password"
>

<button
class="btn btn-primary"
style="width:100%;margin-top:10px;"
type="button"
onclick="saveAccount()"
>

🔒 Actualizar contraseña

</button>

<button
class="btn btn-danger"
style="width:100%;margin-top:15px;"
type="button"
onclick="deleteAccount()"
>
🗑 Dar de baja la cuenta
</button>

</div>

<?php endif; ?>

</main>

</div>

<div class="modal" id="cropModal">

<div class="modal-box">

<h2>
📸 Ajustar foto
</h2>


<div style="
width:100%;
height:320px;
overflow:hidden;
border-radius:20px;
background:#000;
">

<img
id="cropImagen"
style="
max-width:100%;
display:block;
"
>

</div>


<br>


<input
type="range"
id="zoomFoto"
min="0.5"
max="3"
step="0.1"
value="1"
style="width:100%;"
>


<button
class="btn btn-primary"
style="width:100%;margin-top:15px;"
onclick="guardarAjusteFoto()"
>
Guardar ajuste
</button>


</div>

</div>

<!-- ======================================================
     ✨ MODAL
====================================================== -->

<div class="modal" id="modal">

<div class="modal-box">

<h2 id="modalTitle">
Mensaje
</h2>

<p
id="modalText"
style="
color:var(--muted);
line-height:1.7;
margin-bottom:22px;
"
>
...
</p>

<button
class="btn btn-primary"
style="width:100%;"
type="button"
onclick="closeModal()"
>

Aceptar

</button>

</div>

</div>

<!-- ======================================================
     ⚠️ MODAL CONFIRMACIÓN
====================================================== -->

<div class="modal" id="confirmModal">

    <div class="modal-box">

        <h2 id="confirmTitle">
            Confirmar acción
        </h2>

        <p
        id="confirmText"
        style="
        color:var(--muted);
        line-height:1.7;
        margin-bottom:22px;
        ">
        ...
        </p>

        <div style="
        display:flex;
        gap:12px;
        ">

            <button
            class="btn btn-dark"
            style="flex:1;"
            onclick="closeConfirm(false)"
            >
                Cancelar
            </button>

            <button
            class="btn btn-danger"
            style="flex:1;"
            onclick="closeConfirm(true)"
            >
                Continuar
            </button>

        </div>

    </div>

</div>

<script>

/* =========================================================
   ⚡ SIDEBAR
========================================================= */

function toggleSidebar(){

document
.getElementById("sidebar")
.classList
.toggle("active");

}

/* =========================================================
   ✨ MODAL
========================================================= */

const modal =
document.getElementById("modal");

function showModal(title,text){

document.getElementById(
"modalTitle"
).innerText = title;

document.getElementById(
"modalText"
).innerText = text;

modal.classList.add("active");

}

function closeModal(){

modal.classList.remove("active");

}

/* =========================================================
   ⚠️ MODAL CONFIRMACIÓN
========================================================= */

let confirmResolve = null;

function showConfirm(title,text){

    return new Promise(resolve=>{

        confirmResolve = resolve;

        document.getElementById(
            "confirmTitle"
        ).innerText = title;

        document.getElementById(
            "confirmText"
        ).innerText = text;

        document.getElementById(
            "confirmModal"
        ).classList.add("active");

    });

}

function closeConfirm(result){

    document.getElementById(
        "confirmModal"
    ).classList.remove("active");

    if(confirmResolve){
        confirmResolve(result);
    }

}

/* =========================================================
   🔥 AJAX
========================================================= */

async function request(data){

try{

const response = await fetch(
window.location.pathname,
{
method:"POST",

headers:{
"Content-Type":
"application/x-www-form-urlencoded"
},

body:new URLSearchParams(data)
}
);

if(!response.ok){

throw new Error(
"Error del servidor"
);

}

return await response.json();

}catch(error){

return{
success:false,
message:error.message
};

}

}

/* =========================================================
   👥 ELIMINAR PERFIL
========================================================= */

async function deleteProfile(id){

if(!confirm("¿Eliminar perfil?")){
return;
}

const r = await request({

action:"delete_profile",
id

});

showModal(
r.success
? "Perfil eliminado"
: "Error",
r.message
);

if(r.success){

setTimeout(()=>{
location.reload();
},900);

}

}

/* =========================================================
   📱 DEVICE
========================================================= */

async function toggleDevice(id,estado){

const r = await request({

action:"toggle_device",
id,
estado

});

if(r.success){

location.reload();

}else{

showModal(
"Error",
r.message
);

}

}

async function deleteDevice(id){

if(!confirm(
"¿Eliminar dispositivo?"
)){
return;
}

const r = await request({

action:"delete_device",
id

});

if(r.success){

location.reload();

}else{

showModal(
"Error",
r.message
);

}

}

async function deleteAllDevices(){

if(!confirm(
"¿Eliminar TODOS los dispositivos?"
)){
return;
}

const r = await request({

action:"delete_all_devices"

});

if(r.success){

location.reload();

}else{

showModal(
"Error",
r.message
);

}

}

/* =========================================================
   ❌ PLAN
========================================================= */

async function cancelPlan(){

if(!confirm("¿Cancelar plan?")){
return;
}

const r = await request({

action:"cancel_plan"

});

showModal(
r.success
? "Plan cancelado"
: "Error",

r.success
? "Tu renovación automática fue desactivada."
: r.message
);

}

/* =========================================================
   🔐 SEGURIDAD
========================================================= */

async function saveAccount(){

const passActual =
document
.getElementById("passActual")
.value
.trim();

const passNueva =
document
.getElementById("passNueva")
.value
.trim();

if(passActual === ""){

showModal(
"Error",
"Ingresá tu contraseña actual"
);

return;
}

if(passNueva === ""){

showModal(
"Error",
"Ingresá una nueva contraseña"
);

return;
}

if(passNueva.length < 8){

showModal(
"Seguridad",
"La nueva contraseña debe tener al menos 8 caracteres"
);

return;
}

const r = await request({

action:"edit_account",

pass_actual:passActual,

pass_nueva:passNueva

});

if(r.success){

showModal(
"Contraseña actualizada",
"Por seguridad deberás iniciar sesión nuevamente."
);

setTimeout(()=>{

location.href="index.php";

},1800);

}else{

showModal(
"Error",
r.message
);

}

} // <-- ESTA LLAVE CIERRA saveAccount()


/* =====================================================
   📸 CAMBIAR FOTO SEGURIDAD
===================================================== */

async function cambiarFoto(){


const input =
document.getElementById(
"fotoSeguridad"
);


if(!input.files.length){

showModal(
"Foto",
"Seleccioná una imagen"
);

return;

}


const datos = new FormData();


datos.append(
"action",
"change_photo"
);


if(!fotoFinal){

showModal(
"Foto",
"Primero ajusta la imagen"
);

return;

}


datos.append(
"foto",
fotoFinal
);



try{


const r =
await fetch(
window.location.pathname,
{
method:"POST",
body:datos
}
);


const json =
await r.json();



showModal(
json.success
?"Foto actualizada"
:"Error",
json.message
);



if(json.success){

setTimeout(()=>{

location.reload();

},1000);

}



}catch(e){


showModal(
"Error",
"No se pudo actualizar la foto"
);


}


}



/* Preview */

/* =====================================================
   📸 CROP FOTO PERFIL
===================================================== */

let cropper = null;
let fotoFinal = null;


const inputFoto =
document.getElementById("fotoSeguridad");


const previewFoto =
document.getElementById(
"previewFotoSeguridad"
);



inputFoto.addEventListener(
"change",
function(){


const archivo = this.files[0];


if(!archivo){
    return;
}



const url =
URL.createObjectURL(
    archivo
);



const imagen =
document.getElementById(
"cropImagen"
);



imagen.src = url;



document
.getElementById("cropModal")
.classList.add("active");



imagen.onload = function(){


if(cropper){

cropper.destroy();

}



cropper =
new Cropper(
imagen,
{

aspectRatio:1,

viewMode:1,

dragMode:"move",

autoCropArea:1,

background:false,

responsive:true,

zoomable:true,

movable:true,

}

);


};


});




document
.getElementById("zoomFoto")
.addEventListener(
"input",
function(){


if(cropper){

cropper.zoomTo(
parseFloat(this.value)
);

}


});




function guardarAjusteFoto(){


if(!cropper){

return;

}



cropper
.getCroppedCanvas({

width:600,

height:600,

imageSmoothingQuality:"high"

})
.toBlob(

(blob)=>{


fotoFinal =
new File(
[
blob
],
"foto.jpg",
{
type:"image/jpeg"
}
);



previewFoto.src =
URL.createObjectURL(
fotoFinal
);



document
.getElementById("cropModal")
.classList.remove(
"active"
);



},

"image/jpeg",

0.95

);


}

/* =========================================================
   🚪 LOGOUT ALL
========================================================= */

async function logoutAll(){

if(!confirm(
"¿Cerrar todas las sesiones?"
)){
return;
}

const r = await request({

action:"logout_all"

});

if(r.success){

location.href="index.php";

}else{

showModal(
"Error",
r.message
);

}

}

/* =========================================================
   🗑 DAR DE BAJA CUENTA
========================================================= */

async function deleteAccount(){

    const ok = await showConfirm(
        "Eliminar cuenta",
        "⚠️ Esta acción eliminará permanentemente tu cuenta, perfiles y dispositivos. Esta acción no se puede deshacer."
    );

    if(!ok){
        return;
    }

    const r = await request({

        action:"delete_account"

    });

    if(r.success){

        showModal(
            "Cuenta eliminada",
            "Tu cuenta fue eliminada correctamente."
        );

        setTimeout(()=>{

            location.href="index.php";

        },1500);

    }else{

        showModal(
            "Error",
            r.message
        );

    }

}
/* =========================================================
   🔥 CERRAR SIDEBAR CLICK
========================================================= */

document.addEventListener(
"click",
(e)=>{

const sidebar =
document.getElementById(
"sidebar"
);

if(
window.innerWidth <= 1100 &&
!sidebar.contains(e.target) &&
!e.target.closest(
".mobile-top button"
)
){

sidebar.classList.remove(
"active"
);

}

}
);

/* =========================================================
   ⌨️ ESC MODAL
========================================================= */

document.addEventListener(
"keydown",
(e)=>{

if(
e.key === "Escape"
){

closeModal();

}

}
);

</script>

</body>
</html>