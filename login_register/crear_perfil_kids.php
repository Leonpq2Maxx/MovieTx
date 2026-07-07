<?php
session_start();
require_once "config.php";

/* =========================================================
   🔐 VALIDAR SESIÓN
========================================================= */

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['id'];

/* =========================================================
   👤 OBTENER USUARIO
========================================================= */

$stmt = $conn->prepare("
SELECT
    id,
    name,
    email,
    foto,
    status,
    paid_until
FROM users
WHERE id = ?
LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

/* =========================================================
   ❌ SI NO EXISTE
========================================================= */

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

/* =========================================================
   🚫 SI ESTÁ SUSPENDIDO
========================================================= */

if ($user['status'] !== "active") {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

/* =========================================================
   ⏳ SI EXPIRÓ
========================================================= */

if (
    !empty($user['paid_until']) &&
    strtotime($user['paid_until']) < time()
) {

    $stmt = $conn->prepare("
    UPDATE users
    SET status = 'suspended'
    WHERE id = ?
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?expired=1");
    exit();
}

/* =========================================================
   📦 DATOS
========================================================= */

$nombre = htmlspecialchars(
    $user['name'] ?? 'Usuario',
    ENT_QUOTES,
    'UTF-8'
);

$email = htmlspecialchars(
    $user['email'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$foto = !empty($user['foto'])
    ? $user['foto']
    : 'Logo/Logo Nuevo.png';

/* =========================================================
   ⚡ AJAX STATUS
========================================================= */

if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("
    SELECT status
    FROM users
    WHERE id = ?
    LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $data = $stmt
        ->get_result()
        ->fetch_assoc();

    if (
        !$data ||
        $data['status'] !== 'active'
    ) {

        session_unset();
        session_destroy();

        echo "logout";

    } else {

        echo "ok";
    }

    exit();
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

<title>MovieTx • Crear Perfil</title>

<link
rel="icon"
type="image/png"
href="Logo/Logo Nuevo -512x512.png"
>

<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet"
>

<link
href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.css"
rel="stylesheet"
/>

<style>

/* =========================================================
   🌌 ROOT SYSTEM
========================================================= */

:root{

--bg:#050816;

--bg2:#09101f;

--card:
rgba(14,22,38,.88);

--card-solid:#121b2e;

--line:
rgba(255,255,255,.06);

--line-strong:
rgba(255,255,255,.12);

--text:#ffffff;

--muted:#96a4ca;

--primary:#7b61ff;
--primary2:#9f7dff;

--success:#39d7a0;

--danger:#ff5d7c;

--radius:34px;

--shadow:
0 30px 80px rgba(0,0,0,.55);

--shadow-soft:
0 10px 35px rgba(0,0,0,.28);

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

radial-gradient(
circle at top left,
rgba(123,97,255,.24),
transparent 32%
),

radial-gradient(
circle at bottom right,
rgba(57,215,160,.14),
transparent 30%
),

linear-gradient(
180deg,
#050816,
#070d19 60%,
#050816
);

color:var(--text);

min-height:100vh;

overflow-x:hidden;

position:relative;
}

/* =========================================================
   ✨ FLOATING BACKGROUND
========================================================= */

body::before,
body::after{

content:"";

position:fixed;

border-radius:50%;

filter:blur(90px);

pointer-events:none;

z-index:-1;

opacity:.32;
}

body::before{

width:320px;
height:320px;

background:#7b61ff;

top:-120px;
left:-120px;
}

body::after{

width:360px;
height:360px;

background:#39d7a0;

right:-140px;
bottom:-140px;
}

/* =========================================================
   🔥 WRAPPER
========================================================= */

.wrapper{

min-height:100vh;

display:flex;
align-items:center;
justify-content:center;

padding:38px 24px;
}

/* =========================================================
   📦 MAIN CARD
========================================================= */

.crear-card{

position:relative;

width:100%;
max-width:560px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.025)
);

backdrop-filter:blur(28px);

border:1px solid var(--line);

border-radius:var(--radius);

padding:42px;

box-shadow:var(--shadow);

overflow:hidden;

animation:fadeUp .55s ease;
}

/* =========================================================
   ✨ GLOW
========================================================= */

.crear-card::before{

content:"";

position:absolute;

top:-180px;
right:-180px;

width:340px;
height:340px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(123,97,255,.24),
transparent 70%
);

pointer-events:none;
}

.crear-card::after{

content:"";

position:absolute;

bottom:-180px;
left:-180px;

width:320px;
height:320px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(57,215,160,.14),
transparent 70%
);

pointer-events:none;
}

/* =========================================================
   ✨ ANIMATION
========================================================= */

@keyframes fadeUp{

from{
opacity:0;
transform:translateY(28px);
}

to{
opacity:1;
transform:translateY(0);
}

}

/* =========================================================
   🏷 TOP BAR
========================================================= */

.top{

display:flex;
align-items:center;
justify-content:space-between;

gap:20px;

margin-bottom:34px;
}

.logo{

display:flex;
align-items:center;
gap:15px;
}

.logo img{

width:62px;
height:62px;

object-fit:cover;

border-radius:20px;

box-shadow:
0 10px 30px rgba(123,97,255,.28);
}

.logo h1{

font-size:27px;
font-weight:800;

letter-spacing:.4px;
}

.user-mini{

display:flex;
align-items:center;
gap:12px;

padding:10px 14px;

background:
rgba(255,255,255,.03);

border:1px solid
rgba(255,255,255,.05);

border-radius:20px;
}

.user-mini img{

width:50px;
height:50px;

border-radius:50%;

object-fit:cover;

border:3px solid
rgba(123,97,255,.45);
}

.user-mini div{

max-width:160px;
}

.user-mini strong{

display:block;

font-size:14px;

margin-bottom:3px;
}

.user-mini span{

font-size:12px;

color:var(--muted);

word-break:break-word;
}

/* =========================================================
   📝 TITLE
========================================================= */

.title{

text-align:center;

margin-bottom:32px;
}

.title h2{

font-size:34px;
font-weight:800;

margin-bottom:12px;

line-height:1.1;
}

.title p{

color:var(--muted);

line-height:1.8;

font-size:14px;

max-width:430px;

margin:auto;
}

/* =========================================================
   🖼 AVATAR
========================================================= */

.avatar-area{

display:flex;
justify-content:center;

margin-bottom:28px;
}

.avatar-preview{

position:relative;

width:170px;
height:170px;

border-radius:42px;

overflow:hidden;

cursor:pointer;

background:
linear-gradient(
180deg,
rgba(255,255,255,.08),
rgba(255,255,255,.03)
);

border:2px dashed
rgba(255,255,255,.12);

transition:
transform .35s ease,
border-color .35s ease,
box-shadow .35s ease;
}

.avatar-preview:hover{

transform:translateY(-6px);

border-color:
rgba(123,97,255,.72);

box-shadow:
0 20px 50px rgba(123,97,255,.22);
}

.avatar-preview img{

width:100%;
height:100%;

object-fit:cover;
}

.avatar-overlay{

position:absolute;
inset:0;

display:flex;
flex-direction:column;
align-items:center;
justify-content:center;

gap:10px;

background:
linear-gradient(
180deg,
rgba(0,0,0,.08),
rgba(0,0,0,.62)
);

transition:.25s ease;
}

.avatar-preview:hover .avatar-overlay{
opacity:.9;
}

.avatar-icon{

font-size:42px;
font-weight:700;
}

.avatar-overlay span{

font-size:13px;
font-weight:700;
}

/* =========================================================
   🧾 FORM
========================================================= */

.form{

display:flex;
flex-direction:column;

gap:20px;
}

.field{

display:flex;
flex-direction:column;

gap:10px;
}

.field label{

font-size:13px;
font-weight:700;

color:#dde5ff;
}

.input{

width:100%;

height:62px;

padding:0 20px;

border:none;
outline:none;

border-radius:20px;

background:
rgba(255,255,255,.045);

border:1px solid
rgba(255,255,255,.06);

color:#fff;

font-size:15px;

transition:
border .25s ease,
transform .25s ease,
background .25s ease,
box-shadow .25s ease;
}

.input:focus{

border-color:
rgba(123,97,255,.75);

background:
rgba(255,255,255,.07);

transform:translateY(-2px);

box-shadow:
0 0 0 4px rgba(123,97,255,.12);
}

.input::placeholder{
color:#8c9bc3;
}

/* =========================================================
   🔘 BUTTON
========================================================= */

.btn{

height:62px;

border:none;
outline:none;

border-radius:20px;

cursor:pointer;

font-size:15px;
font-weight:800;

font-family:inherit;

transition:
transform .25s ease,
box-shadow .25s ease,
opacity .25s ease;
}

.btn:hover{

transform:translateY(-4px);

box-shadow:
0 18px 40px rgba(123,97,255,.34);
}

.btn:active{
transform:scale(.985);
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

/* =========================================================
   ✨ INFO BOX
========================================================= */

.info{

margin-top:24px;

padding:18px 20px;

border-radius:24px;

background:
rgba(255,255,255,.04);

border:1px solid
rgba(255,255,255,.05);

color:var(--muted);

font-size:13px;

line-height:1.8;
}

/* =========================================================
   ✨ CROPPER MODAL
========================================================= */

#cropModal{

position:fixed;
inset:0;

display:none;
flex-direction:column;

background:#050816;

z-index:99999;
}

.crop-header{

height:82px;

padding:0 24px;

display:flex;
align-items:center;
justify-content:space-between;

background:
rgba(9,14,28,.94);

border-bottom:1px solid
rgba(255,255,255,.06);

backdrop-filter:blur(20px);
}

.crop-header h3{

font-size:16px;
font-weight:700;
}

.crop-header button{

background:none;

border:none;

color:#fff;

font-size:14px;
font-weight:700;

cursor:pointer;

transition:opacity .25s ease;
}

.crop-header button:hover{
opacity:.7;
}

.crop-container{

flex:1;

display:flex;
align-items:center;
justify-content:center;

padding:24px;
}

.crop-container img{

max-width:100%;
max-height:100%;
}

.crop-footer{

padding:22px;

display:flex;
gap:14px;

background:
rgba(9,14,28,.96);

border-top:1px solid
rgba(255,255,255,.06);
}

.crop-footer button{

flex:1;

height:56px;

border:none;

border-radius:18px;

font-weight:700;

cursor:pointer;

transition:.25s ease;
}

.crop-footer button:hover{
transform:translateY(-2px);
}

.btn-zoom{

background:#1b2742;

color:#fff;
}

.btn-save{

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

color:#fff;
}

/* =========================================================
   📱 ANDROID
========================================================= */

@media screen
and (max-width:920px)
and (pointer:coarse)
and (-webkit-min-device-pixel-ratio:1){

.wrapper{
padding:18px;
}

.crear-card{

padding:30px 24px;

border-radius:30px;
}

.top{

flex-direction:column;
align-items:flex-start;
}

.title{

text-align:left;
}

.title h2{

font-size:28px;

line-height:1.15;
}

.title p{
font-size:14px;
}

.avatar-preview{

width:150px;
height:150px;

border-radius:34px;
}

.input{

height:58px;

font-size:15px;

border-radius:18px;
}

.btn{

height:58px;

font-size:15px;

border-radius:18px;
}

.crop-footer{

flex-direction:column;
}

}

/* =========================================================
   🍎 IPHONE
========================================================= */

@media only screen
and (max-width:430px)
and (-webkit-touch-callout:none){

body{

padding-top:
env(safe-area-inset-top);

padding-bottom:
env(safe-area-inset-bottom);
}

.wrapper{
padding:14px;
}

.crear-card{

padding:24px 18px;

border-radius:28px;
}

.top{

flex-direction:column;
align-items:flex-start;

gap:18px;

margin-bottom:28px;
}

.logo img{

width:56px;
height:56px;
}

.logo h1{
font-size:23px;
}

.user-mini{

width:100%;
}

.title{

text-align:left;

margin-bottom:26px;
}

.title h2{

font-size:25px;
}

.title p{

font-size:13px;

line-height:1.7;
}

.avatar-area{
margin-bottom:24px;
}

.avatar-preview{

width:135px;
height:135px;

border-radius:30px;
}

.avatar-icon{
font-size:36px;
}

.input{

height:56px;

padding:0 16px;

border-radius:16px;

font-size:14px;
}

.btn{

height:56px;

border-radius:16px;

font-size:14px;
}

.info{

padding:16px;

font-size:12px;
}

.crop-header{

height:74px;

padding:0 18px;
}

.crop-footer{

padding:18px;

flex-direction:column;
}

.crop-footer button{

height:54px;

border-radius:16px;
}

}

/* =========================================================
   🖥 PC / DESKTOP
========================================================= */

@media screen and (min-width:1200px){

.wrapper{
padding:70px;
}

.crear-card{

max-width:660px;

padding:52px;
}

.title h2{

font-size:40px;
}

.title p{

font-size:15px;

max-width:480px;
}

.avatar-preview{

width:190px;
height:190px;

border-radius:46px;
}

.input{

height:66px;

font-size:16px;

padding:0 22px;
}

.btn{

height:66px;

font-size:16px;
}

.info{

font-size:14px;

padding:20px 22px;
}

}

/* =========================================================
   🔥 CUSTOM SCROLL
========================================================= */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{

background:#27365d;

border-radius:999px;
}

::-webkit-scrollbar-track{
background:transparent;
}

</style>

</head>

<body>

<div class="bg-blur"></div>

<div class="wrapper">

<div class="crear-card">

<!-- =====================================================
     TOP
===================================================== -->

<div class="top">

<div class="logo">

<img src="Logo/Logo Nuevo -512x512.png">

<div>
<h1>MovieTx</h1>
</div>

</div>

<div class="user-mini">

<img src="<?= htmlspecialchars($foto) ?>">

<div>
<strong><?= $nombre ?></strong>
<span><?= $email ?></span>
</div>

</div>

</div>

<!-- =====================================================
     TITLE
===================================================== -->

<div class="title">

<h2>👶 Crear Perfil KIDS</h2>

<p>

Este perfil está diseñado para niños.
Solo podrá existir un perfil KIDS por cuenta.

</p>

</div>

<!-- =====================================================
     FORM
===================================================== -->

<form
id="formPerfil"
class="form"
action="guardar_perfil.php"
method="POST"
enctype="multipart/form-data"
>

<div class="avatar-area">

<label class="avatar-preview">

<img
id="preview"
src="default.png"
alt="Preview"
>

<div class="avatar-overlay">

<div class="avatar-icon">+</div>

<span>Cambiar avatar</span>

</div>

<input
type="file"
name="foto"
id="fotoInput"
hidden
accept="image/*"
onchange="previewImage(event)"
>

</label>

</div>

<div class="field">

<label>Nombre del perfil</label>

<input
type="text"
name="nombre"
class="input"
placeholder="Nombre del niño"
maxlength="20"
required
>

<input
type="hidden"
name="tipo"
value="kids"
>

</div>

<button
type="submit"
class="btn btn-primary"
>

✨ Crear perfil

</button>

</form>

<div class="info">

Podrás editar el perfil más adelante,
cambiar avatar y administrar dispositivos.

</div>

</div>

</div>

<!-- =========================================================
     ✨ CROPPER
========================================================= -->

<div id="cropModal">

<div class="crop-header">

<button onclick="cerrarCrop()">
Cancelar
</button>

<h3>Ajustar avatar</h3>

<button onclick="recortarImagen()">
Guardar
</button>

</div>

<div class="crop-container">

<img id="imageToCrop">

</div>

<div class="crop-footer">

<button
class="btn-zoom"
onclick="zoomOut()"
>

－ Alejar

</button>

<button
class="btn-save"
onclick="zoomIn()"
>

＋ Acercar

</button>

</div>

</div>

<script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.js"></script>

<script>

/* =========================================================
   ⚡ CHECK STATUS
========================================================= */

setInterval(() => {

fetch("crear_perfil_kids.php?check_status=1")

.then(res => res.text())

.then(data => {

if(data === "logout"){

window.location.href = "index.php";

}

});

},15000);

/* =========================================================
   ✨ CROPPER
========================================================= */

let cropper;
let imagenFinalBlob = null;

function previewImage(event){

const file = event.target.files[0];

if(!file) return;

const reader = new FileReader();

reader.onload = function(){

    const image = document.getElementById("imageToCrop");

    image.onload = function(){

        document.getElementById("cropModal").style.display = "flex";

        if(cropper){
            cropper.destroy();
        }

        cropper = new Cropper(image,{
            aspectRatio:1,
            viewMode:1,
            dragMode:"move",
            autoCropArea:1,
            background:false,
            responsive:true,
            zoomable:true,
            movable:true,
            scalable:true,
            rotatable:false,
            wheelZoomRatio:0.1
        });

    };

    image.src = reader.result;

};

reader.readAsDataURL(file);

}

function zoomIn(){

if(cropper){
cropper.zoom(0.1);
}

}

function zoomOut(){

if(cropper){
cropper.zoom(-0.1);
}

}

function cerrarCrop(){

document
.getElementById("cropModal")
.style.display = "none";

}

function recortarImagen(){

if(!cropper) return;

const canvas =
cropper.getCroppedCanvas({

width:500,
height:500,

imageSmoothingEnabled:true,
imageSmoothingQuality:"high"

});

document
.getElementById("preview")
.src = canvas.toDataURL("image/png");

canvas.toBlob(blob => {

imagenFinalBlob = blob;

},"image/png");

cerrarCrop();

}

/* =========================================================
   🚀 SUBMIT
========================================================= */

document
.getElementById("formPerfil")
.addEventListener("submit", async function(e){

e.preventDefault();

const formData = new FormData(this);

if(imagenFinalBlob){

formData.set(
"foto",
imagenFinalBlob,
"perfil.png"
);

}

try{

const response = await fetch(
"guardar_perfil.php",
{
method:"POST",
body:formData
}
);

const result = await response.text();

if(result.trim() === "ok"){

window.location.href =
"perfiles.php";

}else{

alert(
"Error al crear perfil: " + result
);

}

}catch(error){

alert(
"Ocurrió un error inesperado."
);

}

});

</script>

</body>
</html>