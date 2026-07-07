<?php
session_start();
require_once 'config.php';

// 🔒 EVITA CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 🔐 SOLO ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminId    = (int)$_SESSION['id'];
$adminName  = $_SESSION['name'] ?? 'Administrador';
$adminEmail = $_SESSION['email'] ?? '';
$adminLevel = $_SESSION['admin_level'] ?? 'normal';

function volverAdministrador() {

    $_SESSION['msg'] =
    "Cambios guardados correctamente.";

    $_SESSION['msg_type'] =
    "success";

    header("Location: Administrador.php");
    exit();
}

/* =====================
   ELIMINAR ADMIN
===================== */

if (
    isset($_POST['delete_admin'])
    && $adminLevel === 'super'
) {

    $adminDeleteId = (int)$_POST['admin_id'];

    // Evita eliminarse a sí mismo
    if ($adminDeleteId !== $adminId) {

        $stmt = $conn->prepare("
            DELETE FROM admins
            WHERE id=?
            AND admin_level='normal'
        ");

        $stmt->bind_param("i", $adminDeleteId);
        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   AGREGAR CUPOS
===================== */

if (
    isset($_POST['add_admin_quota'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $cantidad = max(
        0,
        (int)$_POST['cantidad_cupos']
    );

    if ($cantidad > 0) {

        $stmt = $conn->prepare("
            UPDATE admins
            SET user_quota = user_quota + ?
            WHERE id = ?
            AND admin_level = 'normal'
        ");

        $stmt->bind_param(
            "ii",
            $cantidad,
            $adminTargetId
        );

        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   QUITAR CUPOS
===================== */

if (
    isset($_POST['remove_admin_quota'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $cantidad = max(
        0,
        (int)$_POST['cantidad_cupos']
    );

    if ($cantidad > 0) {

        $stmt = $conn->prepare("
            UPDATE admins
            SET user_quota =
                GREATEST(1, user_quota - ?)
            WHERE id = ?
            AND admin_level = 'normal'
        ");

        $stmt->bind_param(
            "ii",
            $cantidad,
            $adminTargetId
        );

        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   CAMBIAR CONTRASEÑA
===================== */

if (
    isset($_POST['change_admin_password'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (
        !empty($newPassword)
        && $newPassword === $confirmPassword
    ) {

        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE admins
            SET password = ?
            WHERE id = ?
            AND admin_level = 'normal'
        ");

        $stmt->bind_param(
            "si",
            $passwordHash,
            $adminTargetId
        );

        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   AGREGAR PERFILES
===================== */

if (
    isset($_POST['add_admin_profiles'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $cantidad = max(
        0,
        (int)$_POST['cantidad_perfiles']
    );

    if ($cantidad > 0) {

        $stmt = $conn->prepare("
            UPDATE admins
            SET max_perfiles = max_perfiles + ?
            WHERE id = ?
            AND admin_level = 'normal'
        ");

        $stmt->bind_param(
            "ii",
            $cantidad,
            $adminTargetId
        );

        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   QUITAR PERFILES
===================== */

if (
    isset($_POST['remove_admin_profiles'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $cantidad = max(
        0,
        (int)$_POST['cantidad_perfiles']
    );

    if ($cantidad > 0) {

        $stmt = $conn->prepare("
            UPDATE admins
            SET max_perfiles =
                GREATEST(1, max_perfiles - ?)
            WHERE id = ?
            AND admin_level = 'normal'
        ");

        $stmt->bind_param(
            "ii",
            $cantidad,
            $adminTargetId
        );

        $stmt->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   ACTIVAR / SUSPENDER ADMIN
===================== */

if (
    isset($_POST['toggle_admin_status'])
    && $adminLevel === 'super'
) {

    $adminTargetId = (int)$_POST['admin_id'];

    $stmt = $conn->prepare("
        SELECT status
        FROM admins
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param("i", $adminTargetId);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {

        $currentStatus = $result['status'];

        if (
            $currentStatus === 'pending' ||
            $currentStatus === 'suspended'
        ) {

            $newStatus = 'active';

        } else {

            $newStatus = 'suspended';
        }

        $update = $conn->prepare("
            UPDATE admins
            SET status=?
            WHERE id=?
        ");

        $update->bind_param(
            "si",
            $newStatus,
            $adminTargetId
        );

        $update->execute();
    }

    volverAdministrador();
    exit();
}

/* =====================
   FOTO ADMIN LOGUEADO
===================== */

$adminFoto = 'uploads/admin/default.png';

$stmt = $conn->prepare("
    SELECT foto
    FROM admins
    WHERE id=?
    LIMIT 1
");

$stmt->bind_param("i", $adminId);
$stmt->execute();

$res = $stmt->get_result()->fetch_assoc();

if (!empty($res['foto'])) {
    $adminFoto = $res['foto'];
}

/* =====================
   ADMINISTRADORES
===================== */

if ($adminLevel === 'super') {

    /*
      El administrador principal ve
      únicamente los administradores
      ayudantes creados por él.
    */

    $admins = $conn->query("
        SELECT *
        FROM admins
        WHERE admin_level='normal'
        ORDER BY created_at DESC
    ");

} else {

    /*
      Los ayudantes solo pueden verse
      a sí mismos.
    */

    $stmt = $conn->prepare("
        SELECT *
        FROM admins
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param(
        "i",
        $adminId
    );

    $stmt->execute();

    $admins = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Administradores | MovieTx</title>

<style>

/* =========================
MOVIETX BASE
========================= */

:root{
--primary:#ff003c;
--primary-hover:#ff295f;
--bg:#080808;
--card:rgba(255,255,255,.05);
--border:rgba(255,255,255,.08);
--text:#fff;
--text2:#bdbdbd;
--shadow:0 15px 40px rgba(0,0,0,.45);
--glass:blur(22px);
}

*{
margin:0;
padding:0;
box-sizing:border-box;
-webkit-tap-highlight-color:transparent;
}

body{
font-family:"Segoe UI",system-ui,sans-serif;
background:
radial-gradient(
circle at top,
rgba(255,0,60,.18),
transparent 35%
),
linear-gradient(
180deg,
#050505 0%,
#0d0d0d 40%,
#161616 100%
);

color:white;
min-height:100vh;

padding:
max(20px,env(safe-area-inset-top))
15px
max(20px,env(safe-area-inset-bottom));
}

/* =========================
CONTAINER
========================= */

.admin-container{
width:100%;
max-width:1200px;
margin:auto;
}

/* =========================
GRID ADMINISTRADORES (2 COLUMNAS)
========================= */

.admin-list{
display:grid;
grid-template-columns:repeat(2, 1fr);
gap:18px;
align-items:start;
}

/* =========================
CARD
========================= */

.card{
background:var(--card);

backdrop-filter:var(--glass);
-webkit-backdrop-filter:var(--glass);

border:1px solid var(--border);
border-radius:25px;

padding:25px;

box-shadow:var(--shadow);

animation:fadeIn .35s ease;
}

@keyframes fadeIn{
from{
opacity:0;
transform:translateY(15px);
}
to{
opacity:1;
transform:none;
}
}

/* =========================
HEADER
========================= */

.admin-header{
display:flex;
align-items:center;
justify-content:space-between;
gap:20px;
flex-wrap:wrap;
margin-bottom:25px;
}

.admin-info{
display:flex;
align-items:center;
gap:15px;
}

.admin-info img{
width:85px;
height:85px;
border-radius:50%;
border:3px solid var(--primary);
object-fit:cover;
}

.admin-info h3{
font-size:22px;
margin-bottom:5px;
}

.email-mask{
color:var(--text2);
font-size:14px;
}

/* =========================
TITLE
========================= */

.page-title{
font-size:28px;
font-weight:800;
margin-bottom:25px;
text-align:center;
}

/* =========================
USUARIO CARD
========================= */

.usuario-card{

background:
rgba(255,255,255,.04);

border:
1px solid rgba(255,255,255,.05);

border-radius:18px;

padding:20px;

margin-bottom:18px;

transition:.25s;
}

.usuario-card:hover{
background:
rgba(255,255,255,.06);
}

.user-info{
display:grid;
grid-template-columns:
repeat(auto-fit,minmax(250px,1fr));
gap:12px;
}

.user-info div{
background:
rgba(255,255,255,.03);

padding:12px;

border-radius:12px;

font-size:14px;
}

.user-info strong{
display:block;
margin-bottom:5px;
color:white;
}

/* =========================
BOTONES
========================= */

.user-actions{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:10px;
margin-top:18px;
align-items:stretch;
}

.user-actions form{
display:block;
width:100%;
margin:0;
}

.user-actions form .btn{
width:100%;
height:100%;
min-width:100%;
}

.btn{
flex:1;
min-width:180px;
height:50px;

border:none;
border-radius:12px;

font-weight:700;
font-size:14px;

cursor:pointer;

transition:.25s;
}

.btn:hover{
transform:translateY(-2px);
}

.btn-activate{
background:
linear-gradient(
135deg,
#00c853,
#00e676
);
color:white;
}

.btn-suspend{
background:
linear-gradient(
135deg,
#ff9800,
#ffb74d
);
color:white;
}

.btn-block{
background:
linear-gradient(
135deg,
#ff003c,
#ff295f
);
color:white;
}

.btn-update{
background:
linear-gradient(
135deg,
#2196f3,
#42a5f5
);
color:white;
}

/* =========================
ESTADOS
========================= */

.estado-activo{
color:#00ff88;
font-weight:700;
}

.estado-suspendido{
color:#ffb300;
font-weight:700;
}

.estado-bloqueado{
color:#ff003c;
font-weight:700;
}

.estado-vencido{
color:#ff4444;
font-weight:700;
}

/* =========================
LOGO MOVIETX
========================= */

.logo{
text-align:center;
font-size:clamp(2rem,4vw,3rem);
font-weight:900;
letter-spacing:2px;
color:var(--primary);

margin-bottom:25px;

text-shadow:
0 0 15px rgba(255,0,60,.4),
0 0 30px rgba(255,0,60,.25);
}

/* =========================
ADMIN HEADER
========================= */

.admin-header{
display:flex;
justify-content:center;
margin-bottom:30px;
}

.admin-info{
display:flex;
align-items:center;
gap:15px;
}

.admin-info img{
width:90px;
height:90px;
border-radius:50%;
border:3px solid var(--primary);
object-fit:cover;

box-shadow:
0 0 25px rgba(255,0,60,.3);
}

.admin-info h3{
font-size:22px;
font-weight:800;
margin-bottom:5px;
}

.email-mask{
color:#bdbdbd;
font-size:14px;
}

@media(max-width:768px){

.admin-list{
grid-template-columns:1fr;
}

.admin-info{
flex-direction:column;
text-align:center;
}

.admin-info img{
width:75px;
height:75px;
}

}

.estado-espera{
color:#ff4444;
font-weight:700;
}

.modal-overlay{
display:none;
position:fixed;
inset:0;
background:rgba(0,0,0,.75);
z-index:9999;
justify-content:center;
align-items:center;
padding:20px;
}

.modal-box{
width:100%;
max-width:500px;
background:#111;
border-radius:20px;
padding:25px;
border:1px solid rgba(255,255,255,.08);
}

.modal-box h2{
text-align:center;
margin-bottom:15px;
}

.modal-box textarea{
width:100%;
height:140px;
background:#1b1b1b;
color:#fff;
border:none;
border-radius:12px;
padding:15px;
resize:none;
margin-bottom:15px;
}

/* =========================
ANDROID + IPHONE
320px a 767px
========================= */

@media screen and (max-width:767px){

body{
padding:
max(15px,env(safe-area-inset-top))
10px
max(15px,env(safe-area-inset-bottom));
}

.card{
padding:18px;
border-radius:20px;
}

.logo{
font-size:2rem;
margin-bottom:20px;
}

.admin-header{
justify-content:center;
text-align:center;
}

.admin-info{
flex-direction:column;
gap:10px;
}

.admin-info img{
width:75px;
height:75px;
}

.admin-info h3{
font-size:20px;
}

.email-mask{
font-size:13px;
}

.page-title{
font-size:22px;
}

.user-info{
    grid-template-columns:repeat(2, 1fr);
    gap:10px;
}

.user-info div{
font-size:13px;
padding:10px;
}

/* CORREGIDO */

.user-actions{
display:grid;
grid-template-columns:repeat(2, 1fr);
gap:10px;
}

.user-actions form,
.user-actions button{
width:100%;
}

@media screen and (max-width:767px){
.user-actions{
grid-template-columns:repeat(2, minmax(0, 1fr));
}
}

.user-actions form{
width:100%;
}

.user-actions form .btn,
.user-actions > .btn{
width:100%;
min-width:100%;
height:52px;
font-size:14px;
display:flex;
align-items:center;
justify-content:center;
text-align:center;
}

.btn{
width:100%;
min-width:100%;
height:52px;
font-size:14px;
}

.modal-box{
padding:20px;
border-radius:18px;
}

.modal-box textarea{
height:120px;
font-size:14px;
}

}

/* =========================
TABLETS
768px a 1023px
========================= */

@media screen and (min-width:768px) and (max-width:1023px){

.admin-container{
max-width:95%;
}

.card{
padding:22px;
}

.page-title{
font-size:26px;
}

.user-info{
grid-template-columns:
repeat(2,1fr);
}

.btn{
min-width:160px;
}

.admin-info img{
width:85px;
height:85px;
}

}

@media screen and (max-width:380px){

.user-info{
    grid-template-columns:1fr;
}

}

/* =========================
PC / NOTEBOOK
1024px+
========================= */

@media screen and (min-width:1024px){

.admin-container{
max-width:1200px;
}

.card{
padding:30px;
}

.page-title{
font-size:30px;
}

.user-info{
grid-template-columns:
repeat(auto-fit,minmax(250px,1fr));
}

.btn{
min-width:180px;
}

.admin-info img{
width:90px;
height:90px;
}

.modal-box{
max-width:550px;
}

}

/* =========================
IPHONE NOTCH SUPPORT
========================= */

@supports (padding:max(0px)){

body{
padding-top:
max(20px,env(safe-area-inset-top));

padding-bottom:
max(20px,env(safe-area-inset-bottom));

padding-left:
max(15px,env(safe-area-inset-left));

padding-right:
max(15px,env(safe-area-inset-right));
}

}

/* =========================
PANTALLAS GRANDES
1440px+
========================= */

@media screen and (min-width:1440px){

.admin-container{
max-width:1400px;
}

.page-title{
font-size:34px;
}

.logo{
font-size:3.5rem;
}

.user-info div{
font-size:15px;
}

}

/* =========================
MODO LANDSCAPE MOVILES
========================= */

@media screen and
(max-height:500px)
and (orientation:landscape){

.admin-info{
flex-direction:row;
}

.admin-info img{
width:60px;
height:60px;
}

.page-title{
font-size:20px;
margin-bottom:15px;
}

.card{
padding:15px;
}

}





/* ==========================================
   MODAL ADMIN MOVIETX PRO 2026
========================================== */

.modal-admin{
position:fixed;
inset:0;
width:100%;
height:100%;
background:rgba(0,0,0,.85);
display:none;
align-items:center;
justify-content:center;
padding:20px;
z-index:999999;
backdrop-filter:blur(5px);
-webkit-backdrop-filter:blur(5px);
overflow-y:auto;
}

.modal-admin.show{
display:flex;
animation:fadeIn .25s ease;
}

.modal-box{
width:100%;
max-width:430px;
background:#111;
border:1px solid rgba(255,255,255,.08);
border-radius:20px;
padding:25px;
position:relative;
text-align:center;
box-shadow:
0 0 25px rgba(255,0,60,.25);
animation:modalPop .25s ease;
}

.modal-box h2{
margin:0 0 15px;
font-size:24px;
font-weight:700;
color:#fff;
}

.modal-close{
position:absolute;
top:12px;
right:12px;
width:38px;
height:38px;
border:none;
border-radius:50%;
background:#ff003c;
color:#fff;
font-size:18px;
font-weight:700;
cursor:pointer;
transition:.2s;
}

.modal-close:hover{
transform:scale(1.08);
}

.modal-avatar{
width:95px;
height:95px;
border-radius:50%;
object-fit:cover;
border:3px solid #ff003c;
display:block;
margin:10px auto;
}

.modal-name{
font-size:18px;
font-weight:700;
color:#fff;
margin-top:10px;
}

.modal-email{
font-size:14px;
color:#bdbdbd;
margin-top:5px;
margin-bottom:20px;
word-break:break-word;
line-height:1.5;
}

.modal-input{
width:100%;
height:52px;
border-radius:12px;
border:1px solid rgba(255,255,255,.08);
background:#1b1b1b;
color:#fff;
padding:0 15px;
font-size:16px;
outline:none;
margin-bottom:15px;
transition:.2s;
box-sizing:border-box;
}

.modal-input:focus{
border-color:#ff003c;
box-shadow:0 0 10px rgba(255,0,60,.25);
}

.modal-btn{
width:100%;
height:52px;
border:none;
border-radius:12px;
font-size:15px;
font-weight:700;
cursor:pointer;
background:#ff003c;
color:#fff;
transition:.2s;
}

.modal-btn:hover{
background:#ff295f;
transform:translateY(-2px);
}

@keyframes fadeIn{
from{
opacity:0;
}
to{
opacity:1;
}
}

@keyframes modalPop{
from{
opacity:0;
transform:scale(.92);
}
to{
opacity:1;
transform:scale(1);
}
}

/* ==========================================
   ANDROID PEQUEÑOS
   320px - 480px
========================================== */

@media screen and (max-width:480px){

.modal-admin{
padding:12px;
}

.modal-box{
padding:18px;
border-radius:16px;
max-width:100%;
}

.modal-box h2{
font-size:20px;
}

.modal-avatar{
width:80px;
height:80px;
}

.modal-name{
font-size:16px;
}

.modal-email{
font-size:12px;
}

.modal-input{
height:48px;
font-size:14px;
}

.modal-btn{
height:48px;
font-size:14px;
}

.modal-close{
width:34px;
height:34px;
font-size:15px;
}

}

/* ==========================================
   ANDROID GRANDES
   481px - 767px
========================================== */

@media screen and (min-width:481px) and (max-width:767px){

.modal-box{
max-width:420px;
padding:22px;
}

.modal-avatar{
width:88px;
height:88px;
}

.modal-box h2{
font-size:22px;
}

}

/* ==========================================
   IPHONE SE / 8
========================================== */

@media screen
and (device-width:375px)
and (-webkit-device-pixel-ratio:2){

.modal-box{
max-width:340px;
}

}

/* ==========================================
   IPHONE X / 11 / 12 / 13 / 14 / 15
========================================== */

@media screen
and (device-width:390px){

.modal-box{
max-width:360px;
}

}

/* ==========================================
   IPHONE PLUS / PRO MAX
========================================== */

@media screen
and (min-width:414px)
and (max-width:430px){

.modal-box{
max-width:380px;
}

}

/* ==========================================
   TABLETS
========================================== */

@media screen and (min-width:768px) and (max-width:1023px){

.modal-box{
max-width:500px;
padding:30px;
}

.modal-avatar{
width:105px;
height:105px;
}

.modal-box h2{
font-size:26px;
}

.modal-input{
height:56px;
font-size:17px;
}

.modal-btn{
height:56px;
font-size:16px;
}

}

/* ==========================================
   PC
========================================== */

@media screen and (min-width:1024px){

.modal-box{
max-width:520px;
padding:32px;
}

.modal-avatar{
width:110px;
height:110px;
}

.modal-box h2{
font-size:28px;
}

.modal-name{
font-size:20px;
}

.modal-email{
font-size:15px;
}

.modal-input{
height:56px;
font-size:17px;
}

.modal-btn{
height:56px;
font-size:16px;
}

}

/* ==========================================
   PC GRANDES
========================================== */

@media screen and (min-width:1440px){

.modal-box{
max-width:560px;
}

.modal-avatar{
width:120px;
height:120px;
}

.modal-box h2{
font-size:30px;
}

}

/* ==========================================
   ULTRAWIDE
========================================== */

@media screen and (min-width:1920px){

.modal-box{
max-width:620px;
padding:38px;
}

.modal-avatar{
width:130px;
height:130px;
}

.modal-box h2{
font-size:32px;
}

.modal-name{
font-size:22px;
}

.modal-email{
font-size:16px;
}

}

.btn-delete-confirm{
background:#ff003c;
color:#fff;
}

.btn-delete-confirm:hover{
background:#ff1f55;
}


/* =========================
ONLINE / OFFLINE
========================= */

.online-status{
color:#00ff66;
font-weight:700;
animation:blinkOnline 1s infinite;
}

.offline-status{
color:#ff3b3b;
font-weight:700;
}

@keyframes blinkOnline{

0%{
opacity:1;
}

50%{
opacity:.35;
}

100%{
opacity:1;
}

}

.admin-header-click{
display:flex;
align-items:center;
gap:12px;
cursor:pointer;
padding:12px;
border-radius:15px;
transition:.25s;
}

.admin-header-click:hover{
background:rgba(255,255,255,.05);
}

.admin-header-click img{
width:70px;
height:70px;
border-radius:50%;
object-fit:cover;
border:2px solid #ff003c;
}

.admin-header-click strong{
display:block;
color:#fff;
font-size:16px;
}

.admin-header-click .arrow{
margin-left:auto;
transition:.3s;
color:#ff003c;
font-size:18px;
}

.admin-body{
max-height:0;
overflow:hidden;
opacity:0;
transition:all .35s ease;
}

.admin-item.active .admin-body{
max-height:2000px;
opacity:1;
margin-top:15px;
}

.admin-item.active .arrow{
transform:rotate(180deg);
}
</style>
</head>
<body>

<div class="admin-container">

<div class="card">

<!-- LOGO -->
<div class="logo">
MOVIETX
</div>

<!-- ADMIN LOGUEADO -->
<div class="admin-header">

<div class="admin-info">

<img
src="<?= htmlspecialchars($adminFoto) ?>"
alt="Administrador">

<div>

<h3>
<?= htmlspecialchars($adminName) ?>
</h3>

<div class="email-mask">
<?= htmlspecialchars($adminEmail) ?>
</div>

</div>

</div>

</div>

<h1 class="page-title">
Administradores Registrados
</h1>

<?php if($admins && $admins->num_rows > 0): ?>

<div class="admin-list">

<?php while($admin = $admins->fetch_assoc()): ?>

<?php

if ($admin['status'] === 'suspended') {
    $estadoClase = "estado-suspendido";
    $estadoTexto = "Suspendido";
}
elseif ($admin['status'] === 'pending') {
    $estadoClase = "estado-espera";
    $estadoTexto = "Pendiente";
}
elseif ($admin['status'] === 'rejected') {
    $estadoClase = "estado-bloqueado";
    $estadoTexto = "Bloqueado";
}
else {
    $estadoClase = "estado-activo";
    $estadoTexto = "Activo";
}

?>

<!-- 🔥 ACCORDION ITEM -->
<div class="usuario-card admin-item">

<!-- HEADER CLICKEABLE (SIEMPRE VISIBLE) -->
<div class="admin-header-click"
     onclick="toggleAdmin(<?= (int)$admin['id'] ?>)">

    <img
        src="<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>">

    <div>
        <strong>
            <?= htmlspecialchars($admin['name']) ?>
        </strong>

        <div style="color:#bdbdbd;font-size:13px;">
            ID: <?= (int)$admin['id'] ?>
        </div>
    </div>

    <span class="arrow">▼</span>

</div>

<!-- BODY OCULTO -->
<div class="admin-body" id="admin-body-<?= (int)$admin['id'] ?>">

<!-- ========================= INFO COMPLETA ========================= -->

<div class="user-info">

<div>
<strong>Nombre</strong>
<?= htmlspecialchars($admin['name']) ?>
</div>

<div>
<strong>Email</strong>
<?= htmlspecialchars($admin['email']) ?>
</div>

<div>
<strong>Contraseña</strong>
********
</div>

<div>
<strong>Teléfono</strong>
<?= htmlspecialchars($admin['telefono'] ?: '-') ?>
</div>

<div>
<strong>Nivel</strong>

<?= $admin['admin_level'] === 'super'
? 'Administrador Principal'
: 'Administrador Ayudante' ?>

</div>

<div>
<strong>Usuarios Disponibles</strong>
<?= (int)$admin['user_quota'] ?>
</div>

<div>
<strong>Máximo Usuarios</strong>
<?= (int)$admin['max_perfiles'] ?>
</div>

<div>
<strong>Estado</strong>

<span class="<?= $estadoClase ?>">
<?= $estadoTexto ?>
</span>

</div>

<div>
<strong>Conexión</strong>

<?php if((int)$admin['is_online'] === 1): ?>
<span class="online-status">● Activo Ahora</span>
<?php else: ?>
<span class="offline-status">● Desconectado</span>
<?php endif; ?>

</div>

<div>
<strong>Creado</strong>

<?= !empty($admin['created_at'])
? date('d/m/Y H:i', strtotime($admin['created_at']))
: '-' ?>

</div>

</div>

<!-- ========================= BOTONES ========================= -->

<?php if($adminLevel === 'super'): ?>

<div class="user-actions">

<form method="POST">
<input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">

<button type="submit"
name="toggle_admin_status"
class="btn <?= $admin['status'] === 'active' ? 'btn-suspend' : 'btn-activate' ?>">
<?= $admin['status'] === 'active'
? 'Suspender Administrador'
: 'Activar Administrador' ?>
</button>

</form>

<button type="button" class="btn btn-update"
onclick="abrirModalCupos('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Restablecer Cupos
</button>

<button type="button" class="btn btn-update"
onclick="abrirModalPerfiles('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Restablecer Perfiles
</button>

<button type="button" class="btn btn-update"
onclick="abrirModalPassword('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Cambiar Contraseña
</button>

<button type="button" class="btn btn-suspend"
onclick="abrirModalQuitarCupos('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Quitar Cupos
</button>

<button type="button" class="btn btn-suspend"
onclick="abrirModalQuitarPerfiles('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Quitar Perfiles
</button>

<button type="button" class="btn btn-block"
onclick="abrirModalEliminar('<?= (int)$admin['id'] ?>','<?= htmlspecialchars(addslashes($admin['name'])) ?>','<?= htmlspecialchars(addslashes($admin['email'])) ?>','<?= !empty($admin['foto']) ? htmlspecialchars($admin['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>')">
Eliminar Administrador
</button>

</div>

<?php else: ?>

<div style="margin-top:10px;color:#bdbdbd;font-size:13px;">
Sin permisos para modificar administradores
</div>

<?php endif; ?>

</div>
</div>

<?php endwhile; ?>
</div>

<?php else: ?>

<div class="usuario-card">
<div class="user-info">
<div><strong>Información</strong>No hay administradores registrados.</div>
</div>
</div>

<?php endif; ?>

</div>
</div>

<!-- MODAL CUPOS -->

<div id="modalCupos" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalCupos()">
✕
</button>

<h2>Agregar Cupos</h2>

<img
id="cuposFoto"
class="modal-avatar"
src="">

<div id="cuposNombre" class="modal-name"></div>

<div id="cuposEmail" class="modal-email"></div>

<form method="POST">

<input
type="hidden"
id="cuposAdminId"
name="admin_id">

<input
type="number"
name="cantidad_cupos"
class="modal-input"
min="1"
required
placeholder="Cantidad de cupos">

<button
type="submit"
name="add_admin_quota"
class="btn btn-update modal-btn">

Agregar Cupos

</button>

</form>

</div>

</div>

<!-- MODAL PERFILES -->

<div id="modalPerfiles" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalPerfiles()">
✕

</button>

<h2>Agregar Perfiles</h2>

<img
id="perfilesFoto"
class="modal-avatar"
src="">

<div id="perfilesNombre" class="modal-name"></div>

<div id="perfilesEmail" class="modal-email"></div>

<form method="POST">

<input
type="hidden"
id="perfilesAdminId"
name="admin_id">

<input
type="number"
name="cantidad_perfiles"
class="modal-input"
min="1"
required
placeholder="Cantidad de perfiles">

<button
type="submit"
name="add_admin_profiles"
class="btn btn-update modal-btn">

Agregar Perfiles

</button>

</form>

</div>

</div>

<!-- MODAL CAMBIAR CONTRASEÑA -->

<div id="modalPassword" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalPassword()">
✕
</button>

<h2>Cambiar Contraseña</h2>

<img
id="passwordFoto"
class="modal-avatar"
src="">

<div id="passwordNombre" class="modal-name"></div>

<div id="passwordEmail" class="modal-email"></div>

<form method="POST">

<input
type="hidden"
id="passwordAdminId"
name="admin_id">

<input
type="password"
name="new_password"
class="modal-input"
placeholder="Nueva contraseña"
required>

<input
type="password"
name="confirm_password"
class="modal-input"
placeholder="Confirmar contraseña"
required>

<button
type="submit"
name="change_admin_password"
class="btn btn-update modal-btn">

Guardar Contraseña

</button>

</form>

</div>

</div>

<!-- MODAL ELIMINAR ADMIN -->

<div id="modalEliminar" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalEliminar()">
✕
</button>

<h2>Eliminar Administrador</h2>

<img
id="eliminarFoto"
class="modal-avatar"
src="">

<div
id="eliminarNombre"
class="modal-name">
</div>

<div
id="eliminarEmail"
class="modal-email">
</div>

<div
style="
color:#ff8f8f;
font-size:14px;
margin-bottom:20px;
line-height:1.5;
">

Esta acción eliminará permanentemente
al administrador seleccionado.

</div>

<form method="POST">

<input
type="hidden"
id="eliminarAdminId"
name="admin_id">

<button
type="submit"
name="delete_admin"
class="btn btn-block modal-btn btn-delete-confirm">

Sí, Eliminar Administrador

</button>

</form>

</div>

</div>

<!-- MODAL QUITAR CUPOS -->
<div id="modalQuitarCupos" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalQuitarCupos()">
✕
</button>

<h2>Quitar Cupos</h2>

<img
id="quitarCuposFoto"
class="modal-avatar"
src="">

<div id="quitarCuposNombre" class="modal-name"></div>

<div id="quitarCuposEmail" class="modal-email"></div>

<form method="POST">

<input
type="hidden"
id="quitarCuposAdminId"
name="admin_id">

<input
type="number"
name="cantidad_cupos"
class="modal-input"
min="1"
required
placeholder="Cantidad a quitar">

<button
type="submit"
name="remove_admin_quota"
class="btn btn-suspend modal-btn">

Quitar Cupos

</button>

</form>

</div>

</div>

<!-- MODAL QUITAR PERFILES -->
<div id="modalQuitarPerfiles" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalQuitarPerfiles()">
✕
</button>

<h2>Quitar Perfiles</h2>

<img
id="quitarPerfilesFoto"
class="modal-avatar"
src="">

<div id="quitarPerfilesNombre" class="modal-name"></div>

<div id="quitarPerfilesEmail" class="modal-email"></div>

<form method="POST">

<input
type="hidden"
id="quitarPerfilesAdminId"
name="admin_id">

<input
type="number"
name="cantidad_perfiles"
class="modal-input"
min="1"
required
placeholder="Cantidad a quitar">

<button
type="submit"
name="remove_admin_profiles"
class="btn btn-suspend modal-btn">

Quitar Perfiles

</button>

</form>

</div>

</div>

<script>
    function abrirModalQuitarCupos(
id,
nombre,
email,
foto
){

document.getElementById(
'quitarCuposAdminId'
).value=id;

document.getElementById(
'quitarCuposNombre'
).innerText=nombre;

document.getElementById(
'quitarCuposEmail'
).innerText=email;

document.getElementById(
'quitarCuposFoto'
).src=foto;

document.getElementById(
'modalQuitarCupos'
).classList.add('show');

}

function cerrarModalQuitarCupos(){

document.getElementById(
'modalQuitarCupos'
).classList.remove('show');

}

function abrirModalQuitarPerfiles(
id,
nombre,
email,
foto
){

document.getElementById(
'quitarPerfilesAdminId'
).value=id;

document.getElementById(
'quitarPerfilesNombre'
).innerText=nombre;

document.getElementById(
'quitarPerfilesEmail'
).innerText=email;

document.getElementById(
'quitarPerfilesFoto'
).src=foto;

document.getElementById(
'modalQuitarPerfiles'
).classList.add('show');

}

function cerrarModalQuitarPerfiles(){

document.getElementById(
'modalQuitarPerfiles'
).classList.remove('show');

}
</script>

<script>

function abrirModalEliminar(
id,
nombre,
email,
foto
){

document.getElementById(
'eliminarAdminId'
).value = id;

document.getElementById(
'eliminarNombre'
).innerText = nombre;

document.getElementById(
'eliminarEmail'
).innerText = email;

document.getElementById(
'eliminarFoto'
).src = foto;

document.getElementById(
'modalEliminar'
).classList.add('show');

}

function cerrarModalEliminar(){

document.getElementById(
'modalEliminar'
).classList.remove('show');

}

</script>

<script>

function abrirModalPassword(id,nombre,email,foto){

document.getElementById('passwordAdminId').value=id;
document.getElementById('passwordNombre').innerText=nombre;
document.getElementById('passwordEmail').innerText=email;
document.getElementById('passwordFoto').src=foto;

document.getElementById('modalPassword').classList.add('show');

}

function cerrarModalPassword(){

document.getElementById('modalPassword').classList.remove('show');

}

</script>

<script>

function abrirModalCupos(id,nombre,email,foto){

document.getElementById('cuposAdminId').value=id;
document.getElementById('cuposNombre').innerText=nombre;
document.getElementById('cuposEmail').innerText=email;
document.getElementById('cuposFoto').src=foto;

document.getElementById('modalCupos').classList.add('show');

}

function cerrarModalCupos(){

document.getElementById('modalCupos').classList.remove('show');

}

function abrirModalPerfiles(id,nombre,email,foto){

document.getElementById('perfilesAdminId').value=id;
document.getElementById('perfilesNombre').innerText=nombre;
document.getElementById('perfilesEmail').innerText=email;
document.getElementById('perfilesFoto').src=foto;

document.getElementById('modalPerfiles').classList.add('show');

}

function cerrarModalPerfiles(){

document.getElementById('modalPerfiles').classList.remove('show');

}

</script>

<script>
function toggleAdmin(id){

const items = document.querySelectorAll('.admin-item');
const current = document.getElementById('admin-body-' + id).parentElement;

// cerrar otros
items.forEach(el=>{
if(el !== current){
el.classList.remove('active');
}
});

// toggle actual
current.classList.toggle('active');
}
</script>

</body>
</html>