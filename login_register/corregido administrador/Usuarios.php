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

/* =====================
   BORRAR USUARIO
===================== */

if (
    isset($_POST['delete_user'])
    && $adminLevel === 'super'
) {

    $userId = (int)$_POST['user_id'];

    // Solo usuarios normales
    $stmt = $conn->prepare("
        DELETE FROM users
        WHERE id=?
        AND role='user'
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    header("Location: Administrador.php");
    exit();
}

/* =====================
   TOGGLE STATUS USUARIO
===================== */

if (
    isset($_POST['toggle_status'])
    && $adminLevel === 'super'
) {

    $userId = (int)$_POST['user_id'];

    // Traer estado actual
    $stmt = $conn->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {

    $currentStatus = $result['status'];

    // ACTIVAR
    if (
        $currentStatus === 'pending' ||
        $currentStatus === 'suspended'
    ) {

        $newStatus = 'active';
        $paymentStatus = 'approved';

        $update = $conn->prepare("
            UPDATE users
            SET
                status=?,
                payment_status=?,
                paid_until = DATE_ADD(NOW(), INTERVAL 30 DAY)
            WHERE id=?
        ");

        $update->bind_param(
            "ssi",
            $newStatus,
            $paymentStatus,
            $userId
        );

        $update->execute();
    }

    // SUSPENDER
    elseif ($currentStatus === 'active') {

        $newStatus = 'suspended';
        $paymentStatus = 'pending';

        $update = $conn->prepare("
            UPDATE users
            SET
                status=?,
                payment_status=?
            WHERE id=?
        ");

        $update->bind_param(
            "ssi",
            $newStatus,
            $paymentStatus,
            $userId
        );

        $update->execute();
    }
}

    header("Location: Administrador.php");
    exit();
}


/* =====================
   FOTO ADMIN
===================== */

$adminFoto = 'uploads/admin/default.png';

$stmt = $conn->prepare("
    SELECT foto
    FROM admins
    WHERE id=?
");

$stmt->bind_param("i", $adminId);
$stmt->execute();

$res = $stmt->get_result()->fetch_assoc();

if (!empty($res['foto'])) {
    $adminFoto = $res['foto'];
}

/* =====================
   USUARIOS
===================== */

if ($adminLevel === 'super') {

    // 👑 ADMIN PRINCIPAL
    // Ve TODOS los usuarios:
    // - Registrados desde index.php
    // - Creados por administradores

    $users = $conn->query("
        SELECT
            u.*,
            a.email AS admin_email,
            a.name AS admin_name
        FROM users u
        LEFT JOIN users a
            ON a.id = u.created_by_admin
        WHERE u.role='user'
        ORDER BY u.created_at DESC
    ");

} else {

    // 👨‍💼 ADMIN AYUDANTE
    // SOLO ve usuarios creados por él.
    // NO ve usuarios registrados desde index.php

    $stmt = $conn->prepare("
        SELECT *
        FROM users
        WHERE role='user'
        AND created_by_admin = ?
        AND created_by_admin IS NOT NULL
        ORDER BY created_at DESC
    ");

    $stmt->bind_param("i", $adminId);
    $stmt->execute();

    $users = $stmt->get_result();
}


/* =====================
   ACTUALIZAR PLAN + PERFILES
===================== */

if (
    isset($_POST['update_price'])
    && $adminLevel === 'super'
){

    $userId = (int)$_POST['user_id'];

    $plan = trim($_POST['plan']);
    $precio = (int)$_POST['precio'];


    /*
       CANTIDAD TOTAL DE PERFILES SEGÚN PLAN

       1 Usuario:
       Usuario principal = 1

       Básico:
       Usuario principal
       + 1 perfil
       + 1 Kids
       = 3

       Estándar:
       Usuario principal
       + 3 perfiles
       + 1 Kids
       = 5

       Premium:
       Usuario principal
       + 5 perfiles
       + 1 Kids
       = 7
    */


    switch($plan){

        case '1':

            $maxPerfiles = 1;
            $kids = 0;

        break;


        case 'basico':

            $maxPerfiles = 3;
            $kids = 1;

        break;


        case 'estandar':

            $maxPerfiles = 5;
            $kids = 1;

        break;


        case 'premium':

            $maxPerfiles = 7;
            $kids = 1;

        break;


        default:

            $maxPerfiles = 1;
            $kids = 0;

        break;

    }



    if(
        in_array(
            $plan,
            [
                '1',
                'basico',
                'estandar',
                'premium'
            ],
            true
        )
    ){


        $stmt = $conn->prepare("
            UPDATE users
            SET
                plan=?,
                precio=?,
                max_perfiles=?,
                kids=?
            WHERE id=?
            AND role='user'
        ");


        $stmt->bind_param(
            "siiii",
            $plan,
            $precio,
            $maxPerfiles,
            $kids,
            $userId
        );


        $stmt->execute();


    }


    $_SESSION['mensaje_admin'] = "✅ El cambio de plan se realizó correctamente.";

    header("Location: Administrador.php");
    exit();

}

/* =====================
   CAMBIAR PASSWORD
===================== */

if (
    isset($_POST['change_user_password'])
    && $adminLevel === 'super'
) {

    $userId = (int)$_POST['user_id'];

    $newPassword =
    trim($_POST['new_password'] ?? '');

    $confirmPassword =
    trim($_POST['confirm_password'] ?? '');

    if (
        !empty($newPassword) &&
        $newPassword === $confirmPassword
    ) {

        $hashedPassword =
        password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
            AND role = 'user'
        ");

        $stmt->bind_param(
            "si",
            $hashedPassword,
            $userId
        );

        $stmt->execute();
    }

    header("Location: Administrador.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo -512x512.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Usuarios | MovieTx</title>

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

/*==================================
ACORDEÓN USUARIOS
==================================*/

.accordion-header{
display:flex;
align-items:center;
justify-content:space-between;
gap:15px;
cursor:pointer;
user-select:none;
}

.accordion-left{
display:flex;
align-items:center;
gap:15px;
min-width:0;
}

.accordion-avatar{
width:70px;
height:70px;
border-radius:50%;
object-fit:cover;
border:2px solid #ff003c;
flex-shrink:0;
}

.accordion-user{
display:flex;
flex-direction:column;
min-width:0;
}

.accordion-user h3{
font-size:18px;
margin:0;
color:#fff;
font-weight:700;
overflow:hidden;
text-overflow:ellipsis;
white-space:nowrap;
}

.accordion-user span{
color:#aaa;
font-size:13px;
margin-top:4px;
}

.accordion-icon{
font-size:26px;
color:#ff003c;
transition:.35s;
flex-shrink:0;
}

.usuario-card.active .accordion-icon{
transform:rotate(180deg);
}

.accordion-content{
max-height:0;
overflow:hidden;
opacity:0;
transition:
max-height .45s ease,
opacity .25s ease,
margin-top .35s ease;
}

.usuario-card.active .accordion-content{
max-height:2500px;
opacity:1;
margin-top:18px;
}

@media(max-width:600px){

.accordion-avatar{
width:60px;
height:60px;
}

.accordion-user h3{
font-size:16px;
}

.accordion-icon{
font-size:22px;
}

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

/* =========================
GRID DE USUARIOS
========================= */

.users-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:20px;
    align-items:start;
}

/* Tablets */

@media (min-width:768px) and (max-width:1023px){

    .users-grid{
        grid-template-columns:1fr;
        gap:22px;
    }

}

/* PC */

@media (min-width:1024px){

    .users-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:24px;
    }

}

.user-info strong{
display:block;
margin-bottom:5px;
color:white;
}

/* =========================
BOTONES RESPONSIVE PRO
PC / TABLET / MOVIL
========================= */

.user-actions{

    display:grid;

    grid-template-columns:
    repeat(auto-fit,minmax(180px,1fr));

    gap:12px;

    margin-top:22px;

    width:100%;

    align-items:stretch;

}



/* =========================
FORM DENTRO DEL GRID
========================= */

.user-actions form{

    width:100%;

    display:flex;

    margin:0;

}



/* =========================
BOTON BASE
========================= */

.btn{

    width:100%;

    min-height:42px;

    border:none;

    border-radius:10px;

    font-weight:700;

    font-size:11px;

    cursor:pointer;

    display:flex;

    align-items:center;

    justify-content:center;

    text-align:center;

    padding:6px 8px;

    line-height:1.15;

    white-space:normal;

    overflow-wrap:break-word;

    transition:
    transform .2s ease,
    box-shadow .2s ease;

}



/* =========================
EFECTO PC
========================= */

@media (hover:hover){

.btn:hover{

    transform:translateY(-2px);

    box-shadow:
    0 8px 18px rgba(0,0,0,.35);

}

}



/* =========================
COLORES
========================= */

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
MOVILES
320px - 767px
========================= */

@media(max-width:767px){


.user-actions{

    grid-template-columns:1fr;

    gap:12px;

}


.btn{

    min-height:48px;

    font-size:14px;

    border-radius:12px;

    padding:10px 14px;

}


}



/* =========================
TABLET
768px - 1023px
========================= */

@media(min-width:768px)
and
(max-width:1023px){


.user-actions{

    grid-template-columns:
    repeat(2,minmax(180px,1fr));

}


.btn{

    min-height:44px;

    font-size:12px;

}


}



/* =========================
PC NORMAL
1024px - 1439px
========================= */

@media(min-width:1024px)
and
(max-width:1439px){


.user-actions{

    grid-template-columns:
    repeat(2,minmax(220px,1fr));

}


.btn{

    min-height:42px;

    font-size:11px;

    padding:6px 10px;

}


}



/* =========================
PC GRANDE
1440px+
========================= */

@media(min-width:1440px){


.user-actions{

    grid-template-columns:
    repeat(3,minmax(220px,1fr));

}


.btn{

    min-height:44px;

    font-size:12px;

}


}



/* =========================
ULTRAWIDE 1920+
========================= */

@media(min-width:1920px){


.user-actions{

    grid-template-columns:
    repeat(4,minmax(220px,1fr));

}


.btn{

    min-height:46px;

    font-size:13px;

}


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
grid-template-columns:1fr;
}

.user-info div{
font-size:13px;
padding:10px;
}

.user-actions{
flex-direction:column;
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
</style>
</head>
<body>

<div class="admin-container">

<div class="card">

<!-- LOGO -->

<div class="logo">
MOVIETX
</div>

<!-- ADMIN -->

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
Usuarios Registrados
</h1>

<?php if($users && $users->num_rows > 0): ?>

<div class="users-grid">

<?php while($user = $users->fetch_assoc()): ?>

<?php

$fechaVencimiento = $user['paid_until'] ?? null;
$diasRestantes = null;

if (
    !empty($fechaVencimiento) &&
    $fechaVencimiento !== '0000-00-00' &&
    $fechaVencimiento !== '0000-00-00 00:00:00'
) {
    $hoy = new DateTime();
    $vence = new DateTime($fechaVencimiento);

    $diasRestantes = (int)$hoy->diff($vence)->format('%r%a');
}

/* =========================
   ESTADO REAL
========================= */

if ($user['status'] === 'rejected') {
    $estadoClase = "estado-bloqueado";
    $estadoTexto = "Bloqueado";
}
elseif ($user['status'] === 'suspended') {
    $estadoClase = "estado-suspendido";
    $estadoTexto = "Suspendido";
}
elseif ($user['status'] === 'pending') {
    $estadoClase = "estado-espera";
    $estadoTexto = "Esperando Activación";
}
elseif ($user['status'] === 'active') {

    $estadoClase = "estado-activo";
    $estadoTexto = "Activo";

    if ($diasRestantes !== null) {

        if ($diasRestantes < 0) {
            $estadoClase = "estado-vencido";
            $estadoTexto = "Activo (Vencido)";
        }
        elseif ($diasRestantes <= 10) {
            $estadoClase = "estado-vencido";
            $estadoTexto = "Activo (Por vencer $diasRestantes días)";
        }
    }
}

?>

<div class="usuario-card">

<!-- 🔥 FOTO AGREGADA -->
<div class="accordion-header">

    <div class="accordion-left">

        <img
        class="accordion-avatar"
        src="<?= !empty($user['foto']) ? htmlspecialchars($user['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>">

        <div class="accordion-user">

            <h3>
                <?= htmlspecialchars($user['name']) ?>
            </h3>

            <span>
                ID #<?= (int)$user['id'] ?>
            </span>

        </div>

    </div>

    <div class="accordion-icon">
        ▼
    </div>

</div>

<div class="accordion-content">

<div class="user-info">

<div>
<strong>Administrador</strong>

<?= htmlspecialchars(
$user['admin_email'] ?? 'Administrador Principal'
) ?>

</div>

<div>
<strong>Email Usuario</strong>
<?= htmlspecialchars($user['email']) ?>
</div>

<div>
<strong>Contraseña</strong>
********
</div>

<div>
<strong>Teléfono</strong>
<?= htmlspecialchars($user['telefono'] ?? '-') ?>
</div>

<div>
<strong>Perfiles</strong>

<?php

$totalPerfiles = (int)($user['max_perfiles'] ?? 1);

echo $totalPerfiles . " perfiles";

?>

</div>

<div>
<strong>Perfil Kids</strong>

<?php

if(
    isset($user['kids']) &&
    $user['kids'] == 1
){
    echo "✅ Incluido (1 perfil Kids)";
}else{
    echo "❌ No incluido";
}

?>

</div>

<div>
<strong>Plan</strong>

<?php
switch (strtolower($user['plan'] ?? '')) {

    case '1':
        echo '1 Usuario';
        break;

    case 'basico':
        echo 'Básico';
        break;

    case 'estandar':
        echo 'Estándar';
        break;

    case 'premium':
        echo 'Premium';
        break;

    default:
        echo '-';
        break;
}
?>

</div>

<div>
<strong>Precio</strong>

<?php

if (!empty($user['precio'])) {

    echo '$' . number_format(
        $user['precio'],
        0,
        ',',
        '.'
    );

} else {

    echo '-';

}

?>

</div>

<div>
<strong>Fecha Vencimiento</strong>

<?php
if (
    empty($fechaVencimiento) ||
    $fechaVencimiento === '0000-00-00' ||
    $fechaVencimiento === '0000-00-00 00:00:00'
) {
    echo 'Sin fecha';
} else {
    echo date('d/m/Y', strtotime($fechaVencimiento));
}
?>

</div>

<div>
<strong>Estado</strong>

<span class="<?= $estadoClase ?>">
<?= $estadoTexto ?>
</span>

</div>

</div>

<!-- =========================
     SOLO ADMIN PRINCIPAL
========================= -->

<?php if($adminLevel === 'super'): ?>

<div class="user-actions">

<!-- SUSPENDER -->

<form method="POST">

<input
type="hidden"
name="user_id"
value="<?= $user['id'] ?>">

<button
type="submit"
name="toggle_status"
class="btn <?= $user['status'] === 'active'
? 'btn-suspend'
: 'btn-activate' ?>">

<?= $user['status'] === 'active'
? 'Suspender Usuario'
: 'Activar Usuario' ?>

</button>

</form>

<!-- CAMBIAR PASSWORD -->

<button
type="button"
class="btn btn-update"
onclick="abrirModalPasswordUsuario(
'<?= (int)$user['id'] ?>',
'<?= htmlspecialchars(addslashes($user['email'])) ?>',
'<?= !empty($user['foto']) ? htmlspecialchars($user['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>'
)">
Cambiar Contraseña
</button>

<!-- CAMBIAR PLAN -->

<button
type="button"
class="btn btn-update"
onclick="abrirModalCambiarPlan(
'<?= (int)$user['id'] ?>',
'<?= htmlspecialchars(addslashes($user['name'])) ?>',
'<?= htmlspecialchars(addslashes($user['email'])) ?>',
'<?= !empty($user['foto']) ? htmlspecialchars($user['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>',
'<?= htmlspecialchars($user['plan'] ?? '') ?>'
)">
Cambiar Plan
</button>

<!-- ACTUALIZAR PRECIO -->

<?php if($adminLevel === 'super'): ?>

<!-- =========================
     ACTUALIZAR PRECIO
     SOLO ADMIN PRINCIPAL
========================= -->

<button
type="button"
class="btn btn-update"
onclick="abrirModalPrecioUsuario(
'<?= (int)$user['id'] ?>',
'<?= htmlspecialchars(addslashes($user['name'])) ?>',
'<?= htmlspecialchars(addslashes($user['email'])) ?>',
'<?= !empty($user['foto']) ? htmlspecialchars($user['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>',
'<?= htmlspecialchars($user['plan'] ?? '') ?>'
)">
Actualizar Precio
</button>

<?php endif; ?>

<?php if($diasRestantes <= 10 && $diasRestantes > 0): ?>

<form method="POST">
    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

    <button type="submit" name="update_account" class="btn btn-update">
        Actualizar Usuario
    </button>
</form>

<?php endif; ?>

<form method="POST"
onsubmit="return confirm('¿Eliminar este usuario definitivamente?');">

    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">

    <button
type="button"
class="btn btn-block"
onclick="abrirModalEliminarUsuario(
'<?= (int)$user['id'] ?>',
'<?= htmlspecialchars(addslashes($user['email'])) ?>',
'<?= !empty($user['foto']) ? htmlspecialchars($user['foto']) : 'https://i.imgur.com/4Z7YB7Q.png' ?>'
)">
Eliminar Usuario
</button>

</form>

</div>

<?php else: ?>

<!-- =========================
     ADMIN AYUDANTE (SOLO LECTURA)
========================= -->

<?php if($user['status'] === 'pending'): ?>
    <div class="estado-espera">
        Usuario pendiente de aprobación del administrador principal
    </div>
<?php endif; ?>

<div style="margin-top:10px;color:#bdbdbd;font-size:13px;">
    Sin permisos para modificar usuarios
</div>

<?php endif; ?>
</div>
</div>

<?php endwhile; ?>
</div>
<?php else: ?>

<div class="usuario-card">

<div class="user-info">

<div>
<strong>Información</strong>
No hay usuarios registrados.
</div>

</div>

</div>

<?php endif; ?>

</div>

</div>

<div id="modalCambiarPlan" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalCambiarPlan()">
✕
</button>


<h2>
Cambiar Plan
</h2>


<img
id="planFoto"
class="modal-avatar"
src="">


<div
id="planNombre"
class="modal-name">
</div>


<div
id="planEmail"
class="modal-email">
</div>


<div
id="planId"
class="modal-email">
</div>



<form method="POST">


<input
type="hidden"
id="planUserId"
name="user_id">


<select
id="nuevoPlan"
name="plan"
class="modal-input"
onchange="actualizarPrecioPlan()"
required>


<option value="">
Seleccionar Plan
</option>


<option value="1">
1 Usuario
</option>


<option value="basico">
Básico
</option>


<option value="estandar">
Estándar
</option>


<option value="premium">
Premium
</option>


</select>



<input
type="hidden"
id="precioPlanHidden"
name="precio">



<div
id="precioMostrar"
style="
font-size:22px;
font-weight:800;
margin-bottom:20px;
color:#ff003c;
">
$0
</div>

<button
type="submit"
name="update_price"
class="btn btn-update modal-btn">

Actualizar Plan

</button>


<!-- =========================
     INFORMACIÓN DEL PLAN
========================= -->

<div id="infoPlan"
style="
margin-top:20px;
padding:18px;
border-radius:15px;
background:rgba(255,255,255,.05);
border:1px solid rgba(255,255,255,.08);
text-align:left;
font-size:14px;
line-height:1.7;
color:#ddd;
">

Selecciona un plan para ver la información.

</div>


</form>


</div>

</div>

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

<div
id="passwordNombre"
class="modal-name">
</div>

<div
id="passwordEmail"
class="modal-email">
</div>

<form method="POST">

<input
type="hidden"
id="passwordUserId"
name="user_id">

<input
type="password"
name="new_password"
class="modal-input"
required
placeholder="Nueva contraseña">

<input
type="password"
name="confirm_password"
class="modal-input"
required
placeholder="Confirmar contraseña">

<button
type="submit"
name="change_user_password"
class="btn btn-update modal-btn">

Guardar Contraseña

</button>

</form>

</div>

</div>

<div id="modalEliminar" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalEliminar()">
✕

</button>

<h2>Eliminar Usuario</h2>

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
al usuario seleccionado.

</div>

<form method="POST">

<input
type="hidden"
id="eliminarUserId"
name="user_id">

<button
type="submit"
name="delete_user"
class="btn btn-block modal-btn">

Sí, Eliminar Usuario

</button>

</form>

</div>

</div>


<div id="modalPrecio" class="modal-admin">

<div class="modal-box">

<button
type="button"
class="modal-close"
onclick="cerrarModalPrecio()">
✕
</button>


<h2>
Actualizar Precio
</h2>


<img
id="precioFoto"
class="modal-avatar"
src="">


<div
id="precioNombre"
class="modal-name">
</div>


<div
id="precioEmail"
class="modal-email">
</div>


<form method="POST">


<input
type="hidden"
id="precioUserId"
name="user_id">



<select
name="plan"
id="precioPlan"
class="modal-input"
required>


<option value="">
Seleccionar Plan
</option>

<option value="1">
1 Solo usuario
</option>


<option value="basico">
Básico
</option>


<option value="estandar">
Estándar
</option>


<option value="premium">
Premium
</option>


</select>



<input
type="number"
name="precio"
class="modal-input"
placeholder="Ingrese precio"
min="0"
required>



<button
type="submit"
name="update_price"
class="btn btn-update modal-btn">

Guardar

</button>


</form>


</div>

</div>

<script>

/* ==========================================
   ACTUALIZAR PRECIO
========================================== */


function abrirModalPrecioUsuario(
id,
nombre,
email,
foto,
plan
){


document.getElementById(
"precioUserId"
).value=id;


document.getElementById(
"precioNombre"
).innerText=nombre;


document.getElementById(
"precioEmail"
).innerText=email;


document.getElementById(
"precioFoto"
).src=foto;



document.getElementById(
"precioPlan"
).value=plan;



document.getElementById(
"modalPrecio"
).classList.add("show");


}



function cerrarModalPrecio(){


document.getElementById(
"modalPrecio"
).classList.remove("show");


}

/* ==========================================
   ELIMINAR USUARIO
========================================== */

function abrirModalEliminarUsuario(
id,
email,
foto
){

document.getElementById(
'eliminarUserId'
).value = id;

document.getElementById(
'eliminarNombre'
).innerText = 'Usuario';

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

/* ==========================================
   CAMBIAR PASSWORD
========================================== */

function abrirModalPasswordUsuario(
id,
email,
foto
){

document.getElementById(
'passwordUserId'
).value = id;

document.getElementById(
'passwordNombre'
).innerText = 'Usuario';

document.getElementById(
'passwordEmail'
).innerText = email;

document.getElementById(
'passwordFoto'
).src = foto;

document.getElementById(
'modalPassword'
).classList.add('show');

}

function cerrarModalPassword(){

document.getElementById(
'modalPassword'
).classList.remove('show');

}

/* ==========================================
   CAMBIAR PLAN
========================================== */


const preciosPlanes = {

    "1":1200,

    "basico":2500,

    "estandar":3500,

    "premium":4800

};

const informacionPlanes = {

    "1": `
    <strong style="color:#ff003c">
    Plan: 1 Usuario
    </strong>
    <br>
    Uso Simultáneo: 1
    <br>
    Disponible todas las películas y series.
    `,


    "basico": `
    <strong style="color:#ff003c">
    Plan: Básico
    </strong>
    <br>
    1 Usuario + 1 Perfil + 1 Perfil KIDS
    <br>
    Uso Simultáneo: 3
    <br>
    Disponible todas las películas y series.
    `,


    "estandar": `
    <strong style="color:#ff003c">
    Plan: Estándar
    </strong>
    <br>
    1 Usuario + 3 Perfiles + 1 Perfil KIDS
    <br>
    Uso Simultáneo: 5
    <br>
    Disponible todas las películas y series.
    `,


    "premium": `
    <strong style="color:#ff003c">
    Plan: Premium
    </strong>
    <br>
    1 Usuario + 5 Perfiles + 1 Perfil KIDS
    <br>
    Uso Simultáneo: 7
    <br>
    Disponible todas las películas y series.
    `

};


function abrirModalCambiarPlan(
id,
nombre,
email,
foto,
plan
){


document.getElementById(
"planUserId"
).value=id;


document.getElementById(
"planNombre"
).innerText=nombre;


document.getElementById(
"planEmail"
).innerText=email;


document.getElementById(
"planId"
).innerText="ID Usuario #"+id;


document.getElementById(
"planFoto"
).src=foto;



document.getElementById(
"nuevoPlan"
).value=plan;



actualizarPrecioPlan();



document.getElementById(
"modalCambiarPlan"
).classList.add("show");


}

function actualizarPrecioPlan(){


let plan =
document.getElementById(
"nuevoPlan"
).value;



let precio =
preciosPlanes[plan] ?? 0;



document.getElementById(
"precioMostrar"
).innerHTML =
"$"+precio.toLocaleString("es-AR");



document.getElementById(
"precioPlanHidden"
).value=precio;



document.getElementById(
"infoPlan"
).innerHTML =
informacionPlanes[plan] ??
"Selecciona un plan para ver la información.";

}

function cerrarModalCambiarPlan(){


document.getElementById(
"modalCambiarPlan"
).classList.remove("show");


}

/* ==========================================
   CERRAR AL HACER CLICK AFUERA
========================================== */

window.onclick = function(e){

if(e.target === document.getElementById('modalEliminar')){
cerrarModalEliminar();
}

if(e.target === document.getElementById('modalPassword')){
cerrarModalPassword();
}

if(e.target === document.getElementById('modalPrecio')){
cerrarModalPrecio();
}

if(e.target === document.getElementById('modalCambiarPlan')){
cerrarModalCambiarPlan();
}

};

</script>

<script>
document.querySelectorAll(".accordion-header").forEach(header=>{

    header.addEventListener("click",function(e){

        if(
            e.target.closest("button") ||
            e.target.closest("form") ||
            e.target.closest("input")
        ){
            return;
        }

        const card=this.parentElement;
        const abierto=card.classList.contains("active");

        document.querySelectorAll(".usuario-card").forEach(c=>{
            c.classList.remove("active");
        });

        if(!abierto){
            card.classList.add("active");
        }

    });

});
</script>

</body>
</html>