<?php 
session_start();
require_once "../config.php";

/* =========================================
   🔒 VALIDAR SESIÓN
========================================= */
if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

$userId = (int) $_SESSION['id'];

/* =========================================
   👤 OBTENER USUARIO
========================================= */
$stmt = $conn->prepare("
    SELECT id, name, email, foto, status, paid_until
    FROM users
    WHERE id=?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

/* =========================================
   ❌ SI NO EXISTE
========================================= */
if (!$user) {

    session_unset();
    session_destroy();

    header("Location: ../index.php");
    exit();
}

/* =========================================
   🚫 SUSPENDIDO
========================================= */
if ($user['status'] !== "active") {

    session_unset();
    session_destroy();

    header("Location: ../index.php");
    exit();
}

/* =========================================
   ⏳ EXPIRADO
========================================= */
if (
    !empty($user['paid_until']) &&
    strtotime($user['paid_until']) < time()
) {

    $stmt = $conn->prepare("
        UPDATE users
        SET status='suspended'
        WHERE id=?
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: ../index.php?expired=1");
    exit();
}

/* =========================================
   👤 DETECTAR USUARIO / PERFIL
========================================= */

/* 🔥 USUARIO PRINCIPAL */
$nombre = $user['name'] ?? 'Usuario';

/* 🔥 FOTO USUARIO */
if(
    !empty($user['foto']) &&
    file_exists("../" . $user['foto'])
){
    $foto = "../" . $user['foto'];

}else{

    $foto = "../Logo/Logo Nuevo -512x512.png";
}


/* =========================================
   👤 SI HAY PERFIL ACTIVO
========================================= */

if(isset($_SESSION['perfil_id'])){

    $perfilId = (int) $_SESSION['perfil_id'];

    $stmtPerfil = $conn->prepare("
        SELECT nombre, foto
        FROM perfiles
        WHERE id=? AND user_id=?
        LIMIT 1
    ");

    $stmtPerfil->bind_param(
        "ii",
        $perfilId,
        $userId
    );

    $stmtPerfil->execute();

    $perfil =
    $stmtPerfil
    ->get_result()
    ->fetch_assoc();

    /* =========================================
       🔥 PERFIL EXISTE
    ========================================= */

    if($perfil){

        /* 🔥 CAMBIAR NOMBRE */
        $nombre = $perfil['nombre'];

        /* 🔥 FOTO PERFIL */
        if(
            !empty($perfil['foto']) &&
            file_exists(
                "../uploads/perfiles/" .
                $perfil['foto']
            )
        ){

            $foto =
            "../uploads/perfiles/" .
            $perfil['foto'];

        }else{

            $foto =
            "../Logo/Logo Nuevo -512x512.png";
        }
    }
}
/* =========================================
   ⚡ VERIFICACIÓN AJAX
========================================= */
if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("
        SELECT status
        FROM users
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || $data['status'] !== 'active') {

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

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>MovieTx • Agregado</title>

<link rel="icon"
type="image/png"
href="../Logo/Logo Nuevo -512x512.png">

<link rel="preconnect"
href="https://fonts.googleapis.com">

<link rel="preconnect"
href="https://fonts.gstatic.com"
crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
rel="stylesheet">

<style>

/* =========================================
   🌌 RESET
========================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

html{
-webkit-text-size-adjust:100%;
}

body{

font-family:'Inter',sans-serif;

background:
radial-gradient(circle at top left,
rgba(0,153,255,.10),
transparent 30%),

radial-gradient(circle at bottom right,
rgba(255,0,128,.06),
transparent 30%),

#050505;

color:#fff;

overflow-x:hidden;
overflow-y:auto;

-webkit-font-smoothing:antialiased;
text-rendering:optimizeLegibility;

-webkit-overflow-scrolling:touch;
}

/* =========================================
   🖼 IMG
========================================= */

img{
display:block;
max-width:100%;

user-select:none;
-webkit-user-drag:none;
}

/* =========================================
   ✨ SCROLL
========================================= */

::-webkit-scrollbar{
width:8px;
}

::-webkit-scrollbar-thumb{
background:#ff007f;
border-radius:20px;
}

/* =========================================
   🔥 HEADER
========================================= */

.topbar{

position:sticky;
top:0;
z-index:999;

background:rgba(5,5,5,.92);

border-bottom:
1px solid rgba(255,255,255,.05);

padding:14px 18px;
}

.topbar-inner{

display:flex;
align-items:center;
justify-content:space-between;

gap:14px;
}

.logo-area{
display:flex;
align-items:center;
gap:12px;
}

.logo-area img{

width:44px;
height:44px;

object-fit:cover;

border-radius:14px;
}

.logo-text h1{
font-size:1rem;
font-weight:800;
}

.logo-text span{
font-size:.75rem;
opacity:.65;
}

/* =========================================
   👤 PERFIL
========================================= */

.profile-box{
display:flex;
align-items:center;
gap:10px;
}

.profile-box img{

width:42px;
height:42px;

border-radius:50%;
object-fit:cover;

border:2px solid rgba(255,255,255,.10);
}

.profile-name{
font-size:.84rem;
font-weight:700;
}

/* =========================================
   🔍 SEARCH
========================================= */

.search-wrapper{
padding:18px;
}

.search-box{
position:relative;
}

.search-box input{

width:100%;
height:56px;

border:none;
outline:none;

border-radius:18px;

padding:
0 55px 0 18px;

font-size:.92rem;
font-weight:600;

background:
rgba(255,255,255,.05);

border:
1px solid rgba(255,255,255,.06);

color:#fff;

transition:
border-color .15s ease,
background .15s ease;
}

.search-box input:focus{

border-color:#ff007f;

background:
rgba(255,255,255,.07);
}

.search-box svg{

position:absolute;
right:18px;
top:50%;

transform:translateY(-50%);

width:20px;
height:20px;

opacity:.6;

stroke-width:2;
}

/* =========================================
   🎯 GÉNEROS
========================================= */

.genre-scroll{

display:flex;
gap:10px;

overflow-x:auto;

padding:
0 18px 18px;

scrollbar-width:none;

-webkit-overflow-scrolling:touch;
}

.genre-scroll::-webkit-scrollbar{
display:none;
}

.genre-btn{

border:none;
cursor:pointer;

padding:
10px 16px;

border-radius:999px;

background:
rgba(255,255,255,.05);

color:#fff;

font-size:.82rem;
font-weight:700;

white-space:nowrap;

transition:
background .15s ease,
transform .15s ease;
}

.genre-btn:active{
transform:scale(.96);
}

.genre-btn:hover,
.genre-btn.active{

background:
linear-gradient(
135deg,
#ff007f,
#7b2dff
);
}

/* =========================================
   🎬 TITULO
========================================= */

.section-title{

display:flex;
align-items:center;
justify-content:space-between;

padding:
0 18px 20px;
}

.section-title h3{

font-size:1.15rem;
font-weight:800;
}

.section-title span{

font-size:.85rem;
opacity:.65;
}

/* =========================================
   🎞 GRID
========================================= */

.movie-grid{

display:grid;

grid-template-columns:
repeat(3,minmax(0,1fr));

gap:14px;

padding:
0 14px 45px;
}

/* =========================================
   🎥 CARD
========================================= */

.movie-card{

position:relative;

overflow:hidden;

border-radius:22px;

cursor:pointer;

background:
rgba(255,255,255,.035);

border:
1px solid rgba(255,255,255,.05);

transition:
transform .16s ease,
border-color .16s ease,
background .16s ease;
}

@media (hover:hover){

.movie-card:hover{

transform:translateY(-4px);

border-color:
rgba(255,255,255,.10);

background:
rgba(255,255,255,.045);
}

}

/* =========================================
   🎬 POSTER
========================================= */

.poster{

position:relative;
overflow:hidden;

aspect-ratio:2/3;

background:#111;
}

.poster img{

width:100%;
height:100%;

object-fit:cover;
object-position:center;

transition:transform .20s ease;
}

@media (hover:hover){

.movie-card:hover .poster img{
transform:scale(1.03);
}

}

/* =========================================
   🌑 OVERLAY
========================================= */

.overlay{

position:absolute;
inset:0;

background:
linear-gradient(
to top,
rgba(0,0,0,.88),
transparent 65%
);

pointer-events:none;
}

/* =========================================
   🏷 TAGS
========================================= */

.tags{

position:absolute;

top:8px;
left:8px;

display:flex;
flex-direction:column;

gap:5px;

z-index:5;
}

.tag{

display:inline-flex;
align-items:center;
justify-content:center;

width:fit-content;

padding:
4px 8px;

border-radius:999px;

font-size:.52rem;
font-weight:800;

letter-spacing:.3px;
line-height:1;

border:
1px solid rgba(255,255,255,.08);
}

.tag.series{

background:
linear-gradient(
135deg,
#ff007f,
#ff4fa3
);

color:#fff;
}

.tag.year{

background: linear-gradient(135deg, #ff007f, #ff4fa3);

color:#fff;
}

.tag.hd{

background:
linear-gradient(
135deg,
#00c853,
#00e676
);

color:#fff;
}

/* =========================================
   🆕 NUEVO
========================================= */

.new-badge{

position:absolute;

bottom:10px;
left:10px;

padding:
5px 9px;

border-radius:999px;

font-size:.50rem;
font-weight:800;

background:
linear-gradient(
135deg,
#ff007f,
#ff5f00
);

z-index:5;
}

/* =========================================
   📄 INFO
========================================= */

.movie-info{

padding:12px 8px 14px;

display:flex;
align-items:center;
justify-content:center;

text-align:center;
}

.movie-info h4{

font-size:.82rem;
font-weight:800;

line-height:1.25;

width:100%;

display:-webkit-box;
-webkit-line-clamp:2;
-webkit-box-orient:vertical;

overflow:hidden;
}

/* =========================================
   ⚡ IMÁGENES VACÍAS
========================================= */

.poster img[src=""],
.poster img:not([src]){
opacity:0;
}

/* =========================================
   📱 MOBILE
========================================= */

@media screen and (max-width:480px){

.topbar{
padding:12px;
}

.logo-area img{
width:38px;
height:38px;
}

.logo-text h1{
font-size:.88rem;
}

.logo-text span{
font-size:.65rem;
}

.profile-box img{
width:36px;
height:36px;
}

.profile-name{
font-size:.72rem;
}

.search-wrapper{
padding:12px;
}

.search-box input{

height:48px;

font-size:.78rem;

padding:
0 45px 0 14px;
}

.genre-scroll{
padding:0 12px 14px;
gap:8px;
}

.genre-btn{

font-size:.70rem;

padding:
8px 12px;
}

.section-title{
padding:0 12px 16px;
}

.section-title h3{
font-size:.95rem;
}

.section-title span{
font-size:.70rem;
}

.movie-grid{

grid-template-columns:
repeat(3,minmax(0,1fr));

gap:9px;

padding:
0 9px 35px;
}

.movie-card{
border-radius:16px;
}

.tags{
top:6px;
left:6px;
gap:4px;
}

.tag{

font-size:.42rem;

padding:
3px 6px;

border-radius:14px;
}

.new-badge{

font-size:.42rem;

padding:
4px 7px;

bottom:6px;
left:6px;
}

.movie-info{
padding:9px 4px 12px;
}

.movie-info h4{
font-size:.64rem;
}

}

/* =========================================
   🍎 IPHONE
========================================= */

@media screen
and (min-width:390px)
and (max-width:430px){

.movie-grid{
gap:10px;
}

.movie-card{
border-radius:18px;
}

.tag{

font-size:.48rem;

padding:
4px 7px;
}

.new-badge{
font-size:.46rem;
}

.movie-info h4{
font-size:.72rem;
}

}

/* =========================================
   📱 TABLET
========================================= */

@media screen
and (min-width:768px)
and (max-width:1023px){

.movie-grid{

grid-template-columns:
repeat(4,minmax(0,1fr));

gap:18px;

padding:
0 18px 45px;
}

.movie-info h4{
font-size:.92rem;
}

.tag{

font-size:.62rem;

padding:
5px 10px;
}

}

/* =========================================
   💻 PC
========================================= */

@media screen
and (min-width:1024px){

.topbar{
padding:16px 24px;
}

.logo-area img{
width:48px;
height:48px;
}

.logo-text h1{
font-size:1.1rem;
}

.profile-name{
font-size:.90rem;
}

.search-wrapper{
padding:22px;
}

.search-box input{
height:58px;
font-size:.95rem;
}

.genre-scroll{
padding:0 22px 20px;
}

.section-title{
padding:0 22px 22px;
}

.movie-grid{

grid-template-columns:
repeat(6,minmax(0,1fr));

gap:20px;

padding:
0 20px 55px;
}

.movie-card{
border-radius:24px;
}

.tag{

font-size:.65rem;

padding:
6px 11px;
}

.new-badge{

font-size:.60rem;

padding:
6px 10px;
}

.movie-info{
padding:14px 10px 16px;
}

.movie-info h4{
font-size:.92rem;
}

}

/* =========================================
   🖥 PC GRANDES
========================================= */

@media screen
and (min-width:1440px){

.movie-grid{

grid-template-columns:
repeat(7,minmax(0,1fr));
}

.movie-info h4{
font-size:1rem;
}

}

/* =========================================
   ⚡ REDUCIR ANIMACIONES
========================================= */

@media (prefers-reduced-motion:reduce){

*{
animation:none !important;
transition:none !important;
}

}

</style>

</head>

<body>


<div id="loader-screen">

  <!-- 🌌 fondo animado -->
  <div class="loader-bg"></div>
  <div class="loader-particles"></div>
  <div class="loader-glow"></div>

  <div class="loader-content">

    <!-- 🔥 LOGO -->
    <div class="loader-circle">

      <div class="circle-ring ring1"></div>
      <div class="circle-ring ring2"></div>

      <div class="circle-core">
        <img
          src="../Logo/Logo Nuevo -512x512.png"
          alt="MovieTx"
          class="loader-logo"
          draggable="false"
        >
      </div>

    </div>

    <!-- 🎬 TITULO -->
    <h1 class="loader-title">
      Movie<span>Tx</span>
    </h1>

    <!-- ✨ SUB -->
    <p class="loader-sub">
      Streaming Experience
    </p>

    <!-- 📊 PROGRESS -->
    <div class="loader-progress">

      <div class="loader-progress-track">

        <div
          class="loader-progress-fill"
          id="loading-fill">
        </div>

        <div class="loader-shine"></div>

      </div>

      <div
        class="loader-percent"
        id="loading-percent">
        0%
      </div>

    </div>

    <!-- ⚡ STATUS -->
    <div class="loader-status">

      <span class="status-dot"></span>

      <span
        class="loader-message"
        id="loader-message">
        Inicializando sistema
      </span>

    </div>

  </div>

</div>

<style>

/* =========================================
🌌 RESET
========================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

html{
scroll-behavior:smooth;
-webkit-text-size-adjust:100%;
}

/* =========================================
🔒 BODY LOCK
========================================= */

body.loading{
overflow:hidden;
touch-action:none;
height:100vh;
}

/* =========================================
🌌 MAIN LOADER
========================================= */

#loader-screen{

position:fixed;
inset:0;

display:flex;
align-items:center;
justify-content:center;

overflow:hidden;

padding:20px;

background:
radial-gradient(circle at top,
rgba(0,180,255,.12),
transparent 30%),

radial-gradient(circle at bottom,
rgba(255,0,128,.10),
transparent 30%),

linear-gradient(
180deg,
#070b14 0%,
#020307 45%,
#000 100%
);

z-index:999999;

font-family:
'Inter',
sans-serif;

transition:
opacity .8s ease,
visibility .8s ease;

/* mejora render */
isolation:isolate;
will-change:opacity;
}

/* ocultar */

#loader-screen.hidden{
opacity:0;
visibility:hidden;
pointer-events:none;
}

/* =========================================
✨ BACKGROUND EFFECT
========================================= */

.loader-bg{

position:absolute;

/* FIX DEL HALO GIGANTE */
inset:-20%;

background:
conic-gradient(
from 180deg,
rgba(0,170,255,.05),
rgba(123,45,255,.04),
rgba(255,0,128,.05),
rgba(0,170,255,.05)
);

animation:
bgRotate 22s linear infinite;

will-change:transform;
transform:translateZ(0);
}

@keyframes bgRotate{

to{
transform:rotate(360deg);
}
}

/* =========================================
✨ PARTICLES
========================================= */

.loader-particles{

position:absolute;
inset:0;

overflow:hidden;
pointer-events:none;
}

.loader-particles::before,
.loader-particles::after{

content:"";

position:absolute;

width:220%;
height:220%;

background-image:
radial-gradient(
rgba(255,255,255,.13) 1px,
transparent 1px
);

background-size:
58px 58px;

animation:
particlesMove 22s linear infinite;

will-change:transform;
}

.loader-particles::after{
opacity:.4;
animation-duration:34s;
transform:rotate(12deg);
}

@keyframes particlesMove{

from{
transform:translate3d(0,0,0);
}

to{
transform:translate3d(-140px,-140px,0);
}
}

/* =========================================
💡 GLOW
========================================= */

.loader-glow{

position:absolute;

width:460px;
height:460px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(0,170,255,.16),
transparent 70%
);

filter:blur(55px);

animation:
pulseGlow 4s ease infinite;

pointer-events:none;
will-change:transform,opacity;
}

@keyframes pulseGlow{

0%,100%{
transform:scale(1);
opacity:.65;
}

50%{
transform:scale(1.15);
opacity:1;
}
}

/* =========================================
📦 CONTENT
========================================= */

.loader-content{

position:relative;
z-index:20;

width:min(92vw,420px);

display:flex;
flex-direction:column;
align-items:center;
justify-content:center;

text-align:center;

animation:
loaderFade .9s ease;

will-change:transform,opacity;
}

@keyframes loaderFade{

from{
opacity:0;
transform:translateY(18px);
}

to{
opacity:1;
transform:translateY(0);
}
}

/* =========================================
🪐 LOGO
========================================= */

.loader-circle{

position:relative;

width:170px;
height:170px;

margin:
0 auto 34px;

display:flex;
align-items:center;
justify-content:center;

isolation:isolate;
}

/* =========================================
🌀 RINGS
========================================= */

.circle-ring{

position:absolute;
inset:0;

border-radius:50%;

border:
1px solid rgba(255,255,255,.08);

pointer-events:none;
will-change:transform;
}

.ring1{

animation:
rotateRing 6s linear infinite;
}

.ring2{

inset:12px;

border-color:
rgba(0,180,255,.20);

animation:
rotateRingReverse 8s linear infinite;
}

@keyframes rotateRing{

to{
transform:rotate(360deg);
}
}

@keyframes rotateRingReverse{

to{
transform:rotate(-360deg);
}
}

/* =========================================
🌟 CORE
========================================= */

.circle-core{

position:absolute;
inset:20px;

display:flex;
align-items:center;
justify-content:center;

border-radius:50%;

/* FIX PRINCIPAL */
overflow:hidden;

background:
linear-gradient(
145deg,
rgba(255,255,255,.08),
rgba(255,255,255,.02)
);

backdrop-filter:blur(12px);
-webkit-backdrop-filter:blur(12px);

border:
1px solid rgba(255,255,255,.08);

box-shadow:
0 0 35px rgba(0,170,255,.18),
inset 0 0 25px rgba(255,255,255,.04);

isolation:isolate;
}

/* borde animado FIX */

.circle-core::before{

content:"";

position:absolute;

/* FIX DEL HOVER TRANSPARENTE */
inset:0;

border-radius:50%;

padding:2px;

background:
linear-gradient(
135deg,
#00e1ff,
#7b2dff,
#ff007f
);

-webkit-mask:
linear-gradient(#fff 0 0)
content-box,
linear-gradient(#fff 0 0);

-webkit-mask-composite:xor;
mask-composite:exclude;

animation:
spinBorder 4s linear infinite;

pointer-events:none;
will-change:transform;
}

@keyframes spinBorder{

to{
transform:rotate(360deg);
}
}

/* =========================================
🎬 LOGO IMAGE
========================================= */

.loader-logo{

width:88px;
height:88px;

object-fit:contain;

position:relative;
z-index:2;

pointer-events:none;
user-select:none;

filter:
drop-shadow(0 0 16px rgba(0,225,255,.45));

animation:
logoFloat 3s ease infinite;

will-change:transform;
}

@keyframes logoFloat{

0%,100%{
transform:translateY(0);
}

50%{
transform:translateY(-6px);
}
}

/* =========================================
🎬 TITLES
========================================= */

.loader-title{

font-size:2.8rem;
font-weight:900;

line-height:1;
letter-spacing:.5px;

margin-bottom:10px;

color:#fff;

text-shadow:
0 0 20px rgba(0,225,255,.08);
}

.loader-title span{

background:
linear-gradient(
135deg,
#00e1ff,
#7b2dff,
#ff007f
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.loader-sub{

font-size:.9rem;
font-weight:600;

letter-spacing:2.5px;
text-transform:uppercase;

color:
rgba(255,255,255,.58);

margin-bottom:34px;
}

/* =========================================
📊 PROGRESS
========================================= */

.loader-progress{
width:100%;
}

.loader-progress-track{

position:relative;

height:9px;

overflow:hidden;

border-radius:999px;

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.06);

backdrop-filter:blur(6px);
}

.loader-progress-fill{

width:0%;
height:100%;

border-radius:999px;

background:
linear-gradient(
90deg,
#00e1ff,
#7b2dff,
#ff007f
);

box-shadow:
0 0 18px rgba(0,225,255,.45);

transition:
width .22s ease;

will-change:width;
}

/* brillo */

.loader-shine{

position:absolute;
top:0;
left:-40%;

width:40%;
height:100%;

background:
linear-gradient(
90deg,
transparent,
rgba(255,255,255,.4),
transparent
);

animation:
shine 1.8s linear infinite;

pointer-events:none;
}

@keyframes shine{

to{
left:140%;
}
}

/* =========================================
🔢 PERCENT
========================================= */

.loader-percent{

margin-top:14px;

font-size:1rem;
font-weight:800;

color:#fff;

letter-spacing:.5px;
}

/* =========================================
⚡ STATUS
========================================= */

.loader-status{

margin-top:28px;

display:flex;
align-items:center;
justify-content:center;

gap:10px;

flex-wrap:wrap;

font-size:.84rem;

color:
rgba(255,255,255,.65);
}

.status-dot{

width:10px;
height:10px;

border-radius:50%;

background:#00e1ff;

box-shadow:
0 0 14px #00e1ff;

animation:
dotPulse 1s infinite;

flex-shrink:0;
}

@keyframes dotPulse{

0%,100%{
transform:scale(1);
opacity:1;
}

50%{
transform:scale(.65);
opacity:.45;
}
}

/* =========================================
📱 MOBILE SMALL
========================================= */

@media screen and (max-width:360px){

#loader-screen{
padding:14px;
}

.loader-content{
width:100%;
}

.loader-circle{
width:125px;
height:125px;
margin-bottom:24px;
}

.circle-core{
inset:16px;
}

.loader-logo{
width:58px;
height:58px;
}

.ring2{
inset:9px;
}

.loader-title{
font-size:1.8rem;
}

.loader-sub{
font-size:.68rem;
letter-spacing:1.4px;
margin-bottom:26px;
}

.loader-progress-track{
height:7px;
}

.loader-percent{
font-size:.82rem;
}

.loader-status{
font-size:.68rem;
margin-top:22px;
}

.status-dot{
width:8px;
height:8px;
}
}

/* =========================================
📱 MOBILE
========================================= */

@media screen and (min-width:361px)
and (max-width:600px){

.loader-content{
width:min(94vw,340px);
}

.loader-circle{
width:150px;
height:150px;
}

.circle-core{
inset:18px;
}

.loader-logo{
width:72px;
height:72px;
}

.loader-title{
font-size:2.1rem;
}

.loader-sub{
font-size:.76rem;
letter-spacing:2px;
}

.loader-progress-track{
height:8px;
}

.loader-percent{
font-size:.92rem;
}

.loader-status{
font-size:.74rem;
}
}

/* =========================================
🍎 IPHONE
========================================= */

@media screen
and (min-width:390px)
and (max-width:430px){

.loader-circle{
width:165px;
height:165px;
}

.circle-core{
inset:20px;
}

.loader-logo{
width:78px;
height:78px;
}

.loader-title{
font-size:2.3rem;
}

.loader-sub{
font-size:.8rem;
}

.loader-percent{
font-size:.95rem;
}
}

/* =========================================
📱 TABLET
========================================= */

@media screen
and (min-width:768px)
and (max-width:1023px){

.loader-content{
width:min(78vw,500px);
}

.loader-circle{
width:200px;
height:200px;
}

.circle-core{
inset:24px;
}

.loader-logo{
width:100px;
height:100px;
}

.loader-title{
font-size:3.2rem;
}

.loader-sub{
font-size:1rem;
}

.loader-progress-track{
height:11px;
}

.loader-percent{
font-size:1.15rem;
}

.loader-status{
font-size:.95rem;
}
}

/* =========================================
💻 PC
========================================= */

@media screen
and (min-width:1024px){

.loader-content{
width:min(32vw,500px);
}

.loader-circle{
width:210px;
height:210px;
margin-bottom:40px;
}

.circle-core{
inset:24px;
}

.loader-logo{
width:105px;
height:105px;
}

.loader-title{
font-size:3.5rem;
letter-spacing:1px;
}

.loader-sub{
font-size:1rem;
letter-spacing:3px;
}

.loader-progress-track{
height:11px;
}

.loader-percent{
font-size:1.15rem;
}

.loader-status{
font-size:.95rem;
margin-top:32px;
}

.loader-glow{
width:620px;
height:620px;
}
}

/* =========================================
🖥 PC GRANDES
========================================= */

@media screen
and (min-width:1440px){

.loader-content{
width:min(28vw,560px);
}

.loader-circle{
width:240px;
height:240px;
}

.circle-core{
inset:28px;
}

.loader-logo{
width:120px;
height:120px;
}

.loader-title{
font-size:4rem;
}

.loader-sub{
font-size:1.1rem;
}

.loader-progress-track{
height:12px;
}

.loader-percent{
font-size:1.3rem;
}

.loader-status{
font-size:1rem;
}

.loader-glow{
width:760px;
height:760px;
}
}

</style>
<script>

document.addEventListener("DOMContentLoaded", () => {

const loader =
document.getElementById("loader-screen");

const fill =
document.getElementById("loading-fill");

const percent =
document.getElementById("loading-percent");

const message =
document.getElementById("loader-message");

if(
!loader ||
!fill ||
!percent
) return;

/* =========================================
🔒 LOCK BODY
========================================= */

document.body.classList.add("loading");

/* =========================================
⚡ STATUS TEXTS
========================================= */

const texts = [

"Inicializando sistema",
"Cargando catálogo",
"Preparando películas",
"Optimizando experiencia",
"Finalizando carga"

];

let textIndex = 0;

const textInterval =
setInterval(() => {

textIndex =
(textIndex + 1) % texts.length;

if(message){
message.textContent =
texts[textIndex];
}

}, 900);

/* =========================================
📊 PROGRESS
========================================= */

let progress = 0;
let finished = false;

function updateProgress(value){

progress =
Math.min(100, value);

fill.style.width =
progress + "%";

percent.textContent =
Math.floor(progress) + "%";
}

/* progreso fake suave */

const progressInterval =
setInterval(() => {

if(progress < 88){

progress +=
Math.random() * 4;

updateProgress(progress);

}

}, 120);

/* =========================================
✅ FINISH
========================================= */

function finishLoader(){

if(finished) return;

finished = true;

clearInterval(progressInterval);
clearInterval(textInterval);

/* animación final */

const final =
setInterval(() => {

if(progress < 100){

progress += 2.5;

updateProgress(progress);

}else{

clearInterval(final);

setTimeout(() => {

loader.classList.add("hidden");

document.body.classList.remove("loading");

/* remover */

setTimeout(() => {

loader.remove();

}, 900);

}, 250);

}

}, 18);

}

/* =========================================
🚀 LOAD EVENT
========================================= */

window.addEventListener("load", () => {

setTimeout(() => {

finishLoader();

}, 300);

});

/* fallback */

setTimeout(() => {

finishLoader();

}, 5000);

});

</script>

<!-- =========================================
     🔥 HEADER
========================================= -->

<header class="topbar">

<div class="topbar-inner">

<div class="logo-area">

<img src="../Logo/Logo Nuevo -512x512.png">

<div class="logo-text">
<h1>MovieTx</h1>
</div>

</div>

<div class="profile-box">

<img src="<?= htmlspecialchars($foto) ?>">

<div class="profile-name">
<?= htmlspecialchars($nombre) ?>
</div>

</div>

</div>

</header>

<!-- =========================================
     🔍 SEARCH
========================================= -->

<div class="search-wrapper">

<div class="search-box">

<input
type="text"
id="searchInput"
placeholder="Buscar peliculas • acción • anime • terror..."
>

<svg fill="none"
stroke="currentColor"
viewBox="0 0 24 24">

<circle cx="11"
cy="11"
r="8"></circle>

<path d="m21 21-4.3-4.3"></path>

</svg>

</div>

</div>

<!-- =========================================
     🎯 GÉNEROS
========================================= -->

<div class="genre-scroll">

<button class="genre-btn active"
data-genre="all">
Todo
</button>

<button
class="genre-btn"
data-genre="nuevo"
id="nuevoBtn"
style="display:none;">
Nuevo
</button>

<button class="genre-btn"
data-genre="accion">
Acción
</button>

<button class="genre-btn"
data-genre="animacion">
Animación
</button>

<button class="genre-btn"
data-genre="anime">
Anime
</button>

<button class="genre-btn"
data-genre="biblico">
Biblico
</button>

<button class="genre-btn"
data-genre="comedia">
Comedia
</button>

<button class="genre-btn"
data-genre="crimen">
Crimen
</button>

<button class="genre-btn"
data-genre="drama">
Drama
</button>

<button class="genre-btn"
data-genre="documental">
Documental
</button>

<button class="genre-btn"
data-genre="disney">
Disney
</button>

<button class="genre-btn"
data-genre="marvel">
Marvel
</button>

<button class="genre-btn"
data-genre="misterio">
Misterio
</button>

<button class="genre-btn"
data-genre="musical">
Musical
</button>

<button class="genre-btn"
data-genre="suspenso">
Suspenso
</button>

<button class="genre-btn"
data-genre="romance">
Romance
</button>

<button class="genre-btn"
data-genre="peleas">
Peleas
</button>

<button class="genre-btn"
data-genre="terror">
Terror
</button>

<button class="genre-btn"
data-genre="venganza">
Venganza
</button>

</div>

<!-- =========================================
     🎞 TITLE
========================================= -->

<div class="section-title">

<h3>Agregados Hoy</h3>

<span id="contador">0</span>

</div>

<!-- =========================================
     🎥 GRID
========================================= -->

<div class="movie-grid"
id="movieGrid">

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="serie" 
  data-title="baki dou el samurai invencible" 
  data-genre="anime animacion peleas" 
  data-date="2026-06-13" 
  data-link="../View Peliculas/Reproductor Universal.php?id=baki_dou_el_samurai_invencible">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2026
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/vIbiGAJR69775GHFlYlPFG4GSpb.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Baki-Dou: El samurái invencible</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="serie" 
  data-title="en el barro" 
  data-genre="crimen drama" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=en_el_barro">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/vQANo4LO7Hi57XxQqhRWeAZkD5h.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>En el barro</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="serie" 
  data-title="it bienvenidos a derry" 
  data-genre="terror misterio" 
  data-date="2026-06-13" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=it_bienvenido_a_derry">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>IT: Bienvenidos a Derry</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="serie" 
  data-title="steven universe futuro" 
  data-genre="animacion aventura" 
  data-date="2026-06-13" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=steven_universe_futuro">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fDdIlvGhBNnljro1ON6T9Q3hRpq.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Steven Universe: Futuro</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="serie" 
  data-title="from" 
  data-genre="misterio terror" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=from">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2026
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/cjXLrg4R7FRPFafvuQ3SSznQOd9.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>FROM</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="serie" 
  data-title="baki" 
  data-genre="animacion anime peleas" 
  data-date="2026-06-13" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=baki_2018">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Baki</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2016" 
  data-tipo="serie" 
  data-title="rosario tijeras" 
  data-genre="crimen drama" 
  data-date="2026-06-13" 
  data-link="../View Peliculas/Reproductor Universal Series.php?id=rosario_tijeras">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Serie
      </span>
      <span class="tag year">
        2016
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/zY7jshpbPNs5U677HxRZUltb7gm.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Rosario tijeras</h4>
  </div>
</div>

<!-- CARD

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="pelicula" 
  data-title="titulo" 
  data-genre="genero" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2026
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Titulo</h4>
  </div>
</div>
 -->

</div>

<script>
history.scrollRestoration = "manual";

window.addEventListener("load", () => {
    window.scrollTo({
        top: 0,
        left: 0,
        behavior: "instant"
    });
});
</script>

<script>

/* =========================================
   ⚡ MOVIETX ENGINE ULTRA OPTIMIZADO
   🔥 MEZCLA INTELIGENTE DE GÉNEROS
   🔥 ORDEN POR AÑO
   🔥 SCROLL FLUIDO
   🔥 MOBILE FAST
========================================= */

(() => {

"use strict";

/* =========================================
   ⚡ ELEMENTOS
========================================= */

const grid =
document.getElementById("movieGrid");

const searchInput =
document.getElementById("searchInput");

const contador =
document.getElementById("contador");

const genreButtons =
document.querySelectorAll(".genre-btn");

/* =========================================
   ⚡ OBTENER CARDS
========================================= */

const cards =
Array.from(
grid.children
);

/* =========================================
   ⚡ CSS PERFORMANCE
========================================= */

const style =
document.createElement("style");

style.textContent = `

.movie-card[data-visible="0"]{
display:none !important;
}

.movie-grid{
transform:translateZ(0);
will-change:transform;
}

.movie-card{
transform:translateZ(0);
backface-visibility:hidden;
contain:layout paint style;
}

.poster img{
transition:
transform .25s ease,
opacity .25s ease;
}

.movie-card:hover .poster img{
transform:scale(1.03);
}

/* =========================================
   🆕 NUEVO ANIMADO
========================================= */

.new-badge{

position:absolute;

bottom:10px;
left:10px;

padding:
5px 9px;

border-radius:999px;

font-size:.50rem;
font-weight:800;

background:
linear-gradient(
135deg,
#ff007f,
#ff5f00
);

color:#fff;

z-index:5;

box-shadow:
0 0 10px rgba(255,0,127,.55),
0 0 20px rgba(255,95,0,.35);

animation:
newPulse 2s ease-in-out infinite,
newFade .45s ease;
}

/* =========================================
   ✨ PULSO
========================================= */

@keyframes newPulse{

0%{
transform:scale(1);
box-shadow:
0 0 10px rgba(255,0,127,.45),
0 0 18px rgba(255,95,0,.25);
}

50%{
transform:scale(1.08);
box-shadow:
0 0 16px rgba(255,0,127,.8),
0 0 28px rgba(255,95,0,.55);
}

100%{
transform:scale(1);
box-shadow:
0 0 10px rgba(255,0,127,.45),
0 0 18px rgba(255,95,0,.25);
}

}

/* =========================================
   ⚡ APARICIÓN
========================================= */

@keyframes newFade{

from{
opacity:0;
transform:
translateY(6px)
scale(.8);
}

to{
opacity:1;
transform:
translateY(0)
scale(1);
}

}

`;

document.head.appendChild(style);

/* =========================================
   🎯 OBTENER GÉNERO PRINCIPAL
========================================= */

function getMainGenre(card){

/* 🔥 prioridad manual */
if(card.dataset.main){

return card.dataset.main
.toLowerCase()
.trim();

}

/* 🔥 fallback automático */
const genres =
(card.dataset.genre || "")
.toLowerCase()
.trim()
.split(" ");

return genres[0] || "otros";

}

/* =========================================
   📅 ORDEN POR AÑO
   ❌ SIN RANDOM
========================================= */

function ordenarPorAnio(){

cards.sort((a,b)=>{

const anioA =
parseInt(a.dataset.anio) || 0;

const anioB =
parseInt(b.dataset.anio) || 0;

/* =========================================
   🔥 MÁS NUEVO PRIMERO
========================================= */

if(anioA !== anioB){

return anioB - anioA;

}

/* =========================================
   📅 SI TIENEN MISMO AÑO
   USAR FECHA
========================================= */

const fechaA =
a.dataset.date
? new Date(a.dataset.date).getTime()
: 0;

const fechaB =
b.dataset.date
? new Date(b.dataset.date).getTime()
: 0;

return fechaB - fechaA;

});

/* =========================================
   ⚡ REINSERTAR
========================================= */

const fragment =
document.createDocumentFragment();

for(const card of cards){

fragment.appendChild(card);

}

grid.innerHTML = "";
grid.appendChild(fragment);

}

/* =========================================
   🚀 EJECUTAR ORDEN
========================================= */

ordenarPorAnio();

/* =========================================
   ⚡ PRE-CACHE
========================================= */

for(const card of cards){

card._title =
(card.dataset.title || "")
.toLowerCase();

card._genre =
(card.dataset.genre || "")
.toLowerCase();

card._main =
getMainGenre(card);

card._tipo =
(card.dataset.tipo || "")
.toLowerCase();

card._anio =
(card.dataset.anio || "")
.toLowerCase();

card._link =
card.dataset.link || "";

card._date =
card.dataset.date
? new Date(card.dataset.date)
: null;

}

/* =========================================
   🎯 GÉNERO ACTIVO
========================================= */

let activeGenre = "all";

/* =========================================
   🆕 DETECTAR NUEVOS
========================================= */

const hoy =
Date.now();

/* =========================================
   🆕 VERIFICAR SI HAY NUEVOS
========================================= */

let hayNuevos = false;

for(const card of cards){

if(!card._date) continue;

const diferencia =
(hoy - card._date.getTime())
/ 86400000;

/* =========================================
   🆕 CONTENIDO NUEVO
========================================= */

if(diferencia <= 2){

hayNuevos = true;

/* =========================================
   🏷 CREAR BADGE
========================================= */

if(!card.querySelector(".new-badge")){

const badge =
document.createElement("div");

badge.className =
"new-badge";

badge.textContent =
"Nuevo";

const poster =
card.querySelector(".poster");

if(poster){

poster.appendChild(badge);

}

}

}

}

/* =========================================
   👀 MOSTRAR / OCULTAR BOTÓN
========================================= */

const nuevoBtn =
document.getElementById(
"nuevoBtn"
);

if(nuevoBtn){

nuevoBtn.style.display =
hayNuevos
? "inline-flex"
: "none";

}

/* =========================================
   🔍 FILTRAR
========================================= */

function filtrar(){

const text =
searchInput.value
.trim()
.toLowerCase();

let visibles = 0;

requestAnimationFrame(()=>{

for(let i=0;i<cards.length;i++){

const card =
cards[i];

const matchText =

card._title.includes(text) ||
card._genre.includes(text) ||
card._tipo.includes(text) ||
card._anio.includes(text);

/* =========================================
   🆕 DETECTAR NUEVOS
========================================= */

let isNuevo = false;

if(card._date){

const diferencia =
(hoy - card._date.getTime())
/ 86400000;

isNuevo = diferencia <= 2;

}

/* =========================================
   🎯 FILTRO GÉNERO
========================================= */

let matchGenre = false;

if(activeGenre === "all"){

matchGenre = true;

}else if(activeGenre === "nuevo"){

matchGenre = isNuevo;

}else{

matchGenre =
card._genre.includes(
activeGenre
);

}

/* =========================================
   👀 VISIBILIDAD
========================================= */

const visible =
matchText && matchGenre;

const visibleValue =
visible ? "1" : "0";

if(
card.dataset.visible !==
visibleValue
){

card.dataset.visible =
visibleValue;

}

if(visible){
visibles++;
}

}

/* =========================================
   📊 CONTADOR
========================================= */

contador.textContent =
`${visibles} peliculas`;

});

}

/* =========================================
   ⌨️ INPUT OPTIMIZADO
========================================= */

let debounce;

searchInput.addEventListener(
"input",
()=>{

clearTimeout(debounce);

debounce =
setTimeout(()=>{

filtrar();

},180);

},
{passive:true}
);

/* =========================================
   🎯 GÉNEROS
========================================= */

for(const btn of genreButtons){

btn.addEventListener(
"click",
()=>{

for(const b of genreButtons){

b.classList.remove(
"active"
);

}

btn.classList.add(
"active"
);

activeGenre =
btn.dataset.genre || "all";

filtrar();

},
{passive:true}
);

}

/* =========================================
   🎬 CLICK CARDS
========================================= */

grid.addEventListener(
"click",
e=>{

const card =
e.target.closest(
".movie-card"
);

if(!card) return;

if(card._link){

window.location.href =
card._link;

}

}
);

/* =========================================
   ⚡ IMÁGENES
========================================= */

const images =
document.querySelectorAll(
".poster img"
);

for(const img of images){

img.loading = "lazy";

img.decoding = "async";

img.fetchPriority = "low";

}

/* =========================================
   📱 SCROLL FLUIDO
========================================= */

let ticking = false;

window.addEventListener(
"scroll",
()=>{

if(!ticking){

requestAnimationFrame(()=>{

ticking = false;

});

ticking = true;

}

},
{passive:true}
);

/* =========================================
   🔒 CHECK STATUS
========================================= */

let checking = false;

setInterval(()=>{

if(checking) return;

checking = true;

fetch(
"series.php?check_status=1",
{
cache:"no-store"
}
)

.then(r=>r.text())

.then(data=>{

if(
data.trim() === "logout"
){

window.location.href =
"../index.php";

}

})

.catch(()=>{})

.finally(()=>{

checking = false;

});

},20000);

/* =========================================
   🚀 INIT
========================================= */

filtrar();

})();

</script>

</body>
</html>