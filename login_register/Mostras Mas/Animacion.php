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

<title>MovieTx • Animación</title>

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
placeholder="Buscar peliculas • anime • animacion..."
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
data-genre="anime">
Anime
</button>

<button class="genre-btn"
data-genre="biblico">
Biblico
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
data-genre="musical">
Musical
</button>

<button class="genre-btn"
data-genre="peleas">
Peleas
</button>

</div>

<!-- =========================================
     🎞 TITLE
========================================= -->

<div class="section-title">

<h3>Animacion</h3>

<span id="contador">0</span>

</div>

<!-- =========================================
     🎥 GRID
========================================= -->

<div class="movie-grid"
id="movieGrid">

<!-- CARD -->

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="pelicula" 
  data-title="toy story 5" 
  data-genre="animacion aventura disney comedia" 
  data-date="2026-06-28" 
  data-link="../View Peliculas/Reproductor Universal.php?id=toy_story_5">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2026
      </span>
      <span class="tag hd">
        CAM
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/lYZFbWQU0wCTVOEtfHlROcJoPUp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Toy Story 5</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="pelicula" 
  data-title="intercambiados" 
  data-genre="animacion aventura fantasia familia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=intercambiados">
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
    <img src="https://image.tmdb.org/t/p/w300/cAYWsiPLcjLjvmdSrPAoDtieu8i.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Intercambiados</h4>
  </div>
</div>

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="pelicula" 
  data-title="super mario bros 2 galaxy la pelicula" 
  data-genre="animacion aventura fantasia comedia familiar" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=super_mario_bros_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2026
      </span>
      <span class="tag hd">
        CAM
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/4Js0gYWxuvTN6b8iAaSF1cSQzBs.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Super Mario Bros 2: Galaxy</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="zootopia 2" 
  data-genre="animacion aventura comedia familia infantil niños disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=zootopia_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        CAM
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/3Wg1LBCiTEXTxRrkNKOqJyyIFyF.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Zootopia 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="lilo y stitch action" 
  data-genre="animacion aventura disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=lilo_y_stitch_2025">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Etiqueta
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        CAM
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/yrZqrGVbmoYZJdncnx60JUhzsGm.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Lilo y Stitch</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="mufasa el rey leon" 
  data-genre="animacion disney aventura familia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=mufasa_el_rey_leon">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/lk4NNdeQrb6zbRSogDSdE6qmjk8.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Mufasa: El rey león</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="blancanieves y los siete enanitos" 
  data-genre="animacion fantasia familia disney princesas" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=blancanieves">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Blancanieves</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="intensamente 2" 
  data-genre="animacion drama aventura fantasia disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=intensamente_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/4HEJdpcmTGm3BWWic31G4aCnuC6.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Intensamente 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="moana 2" 
  data-genre="animacion aventura disney musical" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=moana_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Moana 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2023" 
  data-tipo="pelicula" 
  data-title="elemental" 
  data-genre="animacion disney aventura romance" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=elemental">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
         Pelicula
      </span>
      <span class="tag year">
        2023
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/8riWcADI1ekEiBguVB9vkilhiQm.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Elemental</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="pinocho" 
  data-genre="animacion fantasia musical disney aventura" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=pinocho_2022">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/h32gl4a3QxQWNiNaR4Fc1uvLBkV.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Pinocho</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="lightyear" 
  data-genre="animacion aventura familia disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=lightyear">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Lightyear</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2021" 
  data-tipo="pelicula" 
  data-title="encanto" 
  data-genre="animacion musical disney fantasia musical" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=encanto">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2021
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/lH8CLypeehddHZt172TzUGWutH8.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Encanto</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="pelicula" 
  data-title="el rey león" 
  data-genre="animacion aventura familia disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_rey_leon_2019">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/yysmQpv26DdP79XtR3zsL3nVFbN.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El rey león</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="pelicula" 
  data-title="frozen 2" 
  data-genre="animacion aventura musical disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=frozen_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/lTUrKg0vvBgjCUKyjkwxHEiLzBc.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Frozen 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="pelicula" 
  data-title="aladdin" 
  data-genre="animacion aventura disney comedia musical fantasia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=aladdin_2019">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Aladdin</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2004" 
  data-tipo="pelicula" 
  data-title="los increibles" 
  data-genre="animacion disney accion aventura" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_increibles">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2004
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/1Clex17991DCM7uRkAClq52UULM.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los increibles</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2003" 
  data-tipo="pelicula" 
  data-title="peter pan la gran aventura" 
  data-genre="animacion aventura familia disney fantasia" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=peter_pan_la_gran_aventura">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2003
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xtJoP8pppOqT4rECg3E8VkvFkCj.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Peter Pan: La gran aventura</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1994" 
  data-tipo="pelicula" 
  data-title="aladdin 2 el retorno de jafar" 
  data-genre="animacion aventura musical disney romance fantasia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=aladdin_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1994
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Aladdin 2: El retorno de Jafar</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1993" 
  data-tipo="pelicula" 
  data-title="aladdin" 
  data-genre="animacion aventura musical disney fantasia musical familia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=aladdin">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Etiqueta
      </span>
      <span class="tag year">
        1993
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/eLFfl7vS8dkeG1hKp5mwbm37V83.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Aladdín</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1989" 
  data-tipo="pelicula" 
  data-title="la sirenita" 
  data-genre="animacion disney musical aventura familia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_sirenita">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1989
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/muTcgTmuyvXQldGNnCzen9FgDfW.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La sirenita</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1961" 
  data-tipo="pelicula" 
  data-title="101 dalmatas" 
  data-genre="animacion aventura disney" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dalmatas">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1961
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/wny5QtN4D9KYRaW3jDCNMSCQ8gc.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>101 Dálmatas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1951" 
  data-tipo="pelicula" 
  data-title="la cenicienta" 
  data-genre="animacion disney musical familia aventura" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_cenicienta">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1951
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/doN9cNyfpcX1DPBNmjJW8eBgcAf.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La Cenicienta</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1940" 
  data-tipo="pelicula" 
  data-title="pinocho" 
  data-genre="animacion disney musical aventura" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=pinocho">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1940
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/nsnyd6MFznuFSaHk1iveAdWc3nI.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Pinocho</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1938" 
  data-tipo="pelicula" 
  data-title="blancanieves y los siete enanitos" 
  data-genre="animacion disney princesas musical fantasia" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=blancanieves_y_los_siete_enanitos">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1938
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/wdA4lphQwywsPcEKj5sgQ9QSR55.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Blancanieves y los siete enanitos</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="pelicula" 
  data-title="toy story 4" 
  data-genre="animacion disney familia comedia aventura" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=toy_story_4">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Toy story 4</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2003" 
  data-tipo="pelicula" 
  data-title="tierra de osos" 
  data-genre="animacion disney familia aventura" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=tierra_de_osos">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2003
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Tierra de osos</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2004" 
  data-tipo="pelicula" 
  data-title="mickey, donald y goofy los tres mosqueteros" 
  data-genre="animacion disney aventura" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=mickey_donald_y_goofy_los_tres_mosqueteros">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2004
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/gknRvWOe1vypDJfFA4jnprCoK0T.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Mickey, Donald y Goofy: Los tres mosqueteros</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1953" 
  data-tipo="pelicula" 
  data-title="peter pan" 
  data-genre="animacion aventura familia disney fantasia" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=peter_pan">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1953
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/tDvGRWSdqT31ADijJf9OhbTbQ77.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Peter pan</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="los Increibles 2" 
  data-genre="animacion disney accion aventura" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_increibles_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/bJjc0217DuipdwJ0wyi3I4j6soR.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los Increíbles 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="moana" 
  data-genre="animacion aventura disney musical" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=moana">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/a4Jj3Tk2AZvmUYWx0H92HGfktKo.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Moana</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="kung fu panda 4" 
  data-genre="animacion aventura fantasia" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=kung_fu_panda_4">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Kung fu panda 4</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="baki hanma vs kengan ashura" 
  data-genre="animacion anime fantasia peleas" 
  data-date="2026-05-23" 
  data-link="../View Peliculas/Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Baki Hanma VS Kengan Ashura</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="dragon ball z dragon ball super super hero" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_super_super_hero">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/wFYXVMKWLAoazjWTBNQ4IiQSKJg.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Super: Super hero</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="los siete pecados capitales el rencor de edimburgo" 
  data-genre="animacion aventura anime accion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_el_rencor_1">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/VWKjOfMDisBDPJy1Dj5wxYLYTp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los siete pecados capitales: El rencor de Edimburgo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="minions el origen de gru" 
  data-genre="animacion accion aventura comedia ciencia ficcion familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=minions_el_origen_de_gru">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Minions: El origen de Gru</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2022" 
  data-tipo="pelicula" 
  data-title="hotel transylvania 4 transformania" 
  data-genre="animacion comedia aventura fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hotel_transylvania_4">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2022
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xNF8AxJc966FWk4SYqXxGHaZLHZ.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hotel Transylvania 4: Transformania</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2021" 
  data-tipo="pelicula" 
  data-title="los siete pecados capitales la maldicion de la luz" 
  data-genre="animacion accion aventura anime" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_la_maldicion_de_la_luz">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2021
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/w6U2pGQokqWh2wJLRaXi0bVd3zF.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los siete pecados capitales: La maldición de la luz</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2021" 
  data-tipo="pelicula" 
  data-title="un jefe en pañales 2 negocios de familia" 
  data-genre="animacion musical familiar aventura" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=un_jefe_en_pañales_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2021
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/kv2Qk9MKFFQo4WQPaYta599HkJP.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Un jefe en pañales 2: Negocios de familia</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2021" 
  data-tipo="pelicula" 
  data-title="sing 2 cantar" 
  data-genre="animacion musical fantasia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=sing_cantar_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2021
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/aWeKITRFbbwY8txG5uCj4rMCfSP.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Sing 2: Cantar</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2020" 
  data-tipo="pelicula" 
  data-title="los croods 2 una nueva era" 
  data-genre="animacion aventura comedia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_croods_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2020
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5uMWKEmegf5aTJnp1u98JF4QerP.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los Croods 2: Una nueva era</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2020" 
  data-tipo="pelicula" 
  data-title="bob esponja 3 un heroe al rescate" 
  data-genre="animacion aventura fantasia infantil" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=bob_esponja_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2020
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Bob Esponja 3: Un héroe al rescate</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2020" 
  data-tipo="pelicula" 
  data-title="trolls 2 gira mundial" 
  data-genre="animacion musical aventura familia fantasia infantil" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=trolls_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2020
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/9GdgycCYq3vnxLHw5Ldah8JEjH4.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Trolls 2: Gira mundial</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2020" 
  data-tipo="pelicula" 
  data-title="scooby ¡scooby!" 
  data-genre="animacion aventura comedia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=scooby_2020">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2020
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/tOhuq4RYr2Rt9TM7X4dkr7A9HSd.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>¡Scooby!</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2019" 
  data-tipo="pelicula" 
  data-title="como entrenar a tu dragon 3" 
  data-genre="animacion aventura fantasia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2019
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cómo entrenar a tu dragón 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="los siete pecados capitales prisioneros del cielo" 
  data-genre="animacion accion aventura anime" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_prisioneros_del_cielo">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/gNq4Uo2KDPDTvAuixQALpsSFvPu.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los siete pecados capitales: Prisioneros del cielo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="spider man un nuevo universo" 
  data-genre="accion aventura animacion marvel ciencia ficcion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=spider_man_un_nuevo_universo">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Spider-Man: Un nuevo universo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="hotel transylvania 3" 
  data-genre="animacion comedia aventura fantasia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hotel_transylvania_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/gjAFM4xhA5vyLxxKMz38ujlUfDL.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hotel Transylvania 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="dragon ball z dragon ball super" 
  data-genre="animacion peleas accion anime" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_super_broly">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Super: Broly</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2018" 
  data-tipo="pelicula" 
  data-title="steven universe la pelicula" 
  data-genre="animacion ciencia ficcion aventura musical fantasia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=steven_universe_la_pelicula">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2018
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/bewhxwbmWTMe16dEQa8ICGe9Y1Y.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Steven Universe: La pelicula</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2017" 
  data-tipo="pelicula" 
  data-title="mi villano favorito 3" 
  data-genre="animacion aventura comedia familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2017
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Mi villano favorito 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="un show mas la pelicula" 
  data-genre="animacion aventura ciencia ficcion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=un_show_mas_la_pelicula">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/o7ii8gudODmqyQs9PgGfmozj29o.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Un show más: La pelicula</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2016" 
  data-tipo="pelicula" 
  data-title="sing cantar" 
  data-genre="animacion musical fantasia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=sing_cantar">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2016
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Sing: Cantar</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="dragon ball z la resurreccion de freezer" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_resurreccion_de_freezer">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: La resurreccion de Freezer</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="boruto uzumaki naruto shippuden" 
  data-genre="animacion anime accion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=boruto_2015">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/t9F4Yzi8rZO8Rn55ceyQPAofrI9.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Boruto: La Película</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="hotel transylvania 2" 
  data-genre="animacion comedia aventura fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hotel_transylvania_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/3nFnrivNgipSKZ8LZJJbRSlAcTR.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hotel Transylvania 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="bob Esponja 2 un heroe fuera del agua" 
  data-genre="animacion aventura comedia familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=bob_esponja_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Bob Esponja 2: Un héroe fuera del agua</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="alvin y las ardillas fiesta sobre ruedas" 
  data-genre="animacion comedia aventura musical" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_4">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Alvin y las ardillas: Fiesta sobre ruedas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2016" 
  data-tipo="pelicula" 
  data-title="trolls" 
  data-genre="animacion musical aventura familia fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=trolls">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2016
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5nDbnZ9UssqVoVRggQOb2icL9Pb.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Trolls</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2014" 
  data-tipo="pelicula" 
  data-title="como entrenar a tu dragon 2" 
  data-genre="animacion aventura fantasia familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2014
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cómo entrenar a tu dragón 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2013" 
  data-tipo="pelicula" 
  data-title="los croods" 
  data-genre="animacion aventura comedia familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=los_croods">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2013
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/3X3qtBTgKt5mCB30RJwbIjgjzdw.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los croods</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2012" 
  data-tipo="pelicula" 
  data-title="hotel transylvania" 
  data-genre="animacion comedia aventura fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hotel_transylvania">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2012
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/eJGvzGrsfe2sqTUPv5IwLWXjVuR.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hotel Transylvania</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2013" 
  data-tipo="pelicula" 
  data-title="turbo" 
  data-genre="animacion aventura" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=turbo">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2013
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ysNUm2zWPkJQKa3Op0N4EmqrZ0h.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Turbo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2013" 
  data-tipo="pelicula" 
  data-title="dragon ball z la batalla de los dioses" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_batalla_de_los_dioses">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2013
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: La batalla de los dioses</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2013" 
  data-tipo="pelicula" 
  data-title="mi villano favorito 2" 
  data-genre="animacion aventura comedia familia accion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2013
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Mi villano favorito 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2012" 
  data-tipo="pelicula" 
  data-title="madagascar 3 de marcha por europa" 
  data-genre="animacion aventura animales ciencia ficcion infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=madagascar_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2012
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/l7d5JCkwvGrqiQcppobohXYnjxt.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Madagascar 3: De marcha por Europa</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2012" 
  data-tipo="pelicula" 
  data-title="el origen de los guardianes" 
  data-genre="animacion aventura fantasia infantil" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_origen_de_los_guardianes">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2012
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/kDVXsTZhssIJeZIMBC33MqmgkrQ.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El origen de los guardianes</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2012" 
  data-tipo="pelicula" 
  data-title="el gato con botas" 
  data-genre="animacion comedia aventura fantasia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_gato_con_botas">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2012
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El gato con botas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2011" 
  data-tipo="pelicula" 
  data-title="dragon ball z episodio de bardock" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_episodio_de_bardock">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2011
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Episodio de Bardock</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2011" 
  data-tipo="pelicula" 
  data-title="alvin y las ardillas 3" 
  data-genre="animacion comedia aventura musical" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2011
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/a52ebjlDqvrjcKtFGDtQgNQLaGH.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Alvin y las ardillas 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2010" 
  data-tipo="pelicula" 
  data-title="dragon ball z plan para erradicar a los Super Saiyans" 
  data-genre="animacion anime accion ciencia ficcion fantasia"
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_plan_erradicar">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2010
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Plan para erradicar a los Super Saiyans</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2010" 
  data-tipo="pelicula" 
  data-title="como entrenar a tu dragon" 
  data-genre="animacion aventura ciencia ficcion familia"
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_1">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2010
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cómo entrenar a tu dragón</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2010" 
  data-tipo="pelicula" 
  data-title="mi villano favorito" 
  data-genre="animacion aventura comedia familia infantil" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2010
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/pgDbf2DPNWVz5D8PvgsCoI21k7j.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Mi villano favorito</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2009" 
  data-tipo="pelicula" 
  data-title="alvin y las ardillas 2" 
  data-genre="animacion comedia aventura musical"
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2009
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ye1MoMxdW6imx1BdytGxXYvj4BT.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Alvin y las ardillas 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2008" 
  data-tipo="pelicula" 
  data-title="madagascar 2"
  data-genre="animacion aventura animales ciencia ficcion" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=madagascar_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2008
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/zYbvSjajQrb2jU9rUo5Mt06stPd.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Madagascar 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2007" 
  data-tipo="pelicula" 
  data-title="alvin y las ardillas" 
  data-genre="animacion aventura comedia musical" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2007
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Alvin y las ardillas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2005" 
  data-tipo="pelicula" 
  data-title="madagascar"
  data-genre="animacion aventura familia animales" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=madagascar">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2005
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/v6bFSYpmAREGriQiMJvvO9TiapM.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Madagascar</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2005" 
  data-tipo="pelicula" 
  data-title="la novia cadaver" 
  data-genre="animacion romance fantasia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_novia_cadaver">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2005
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/3ALM0VeZjGUryAqWo6pqohzbLDh.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La novia cadáver</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2004" 
  data-tipo="pelicula" 
  data-title="bob esponja" 
  data-genre="animacion aventura niños infantil familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=bob_esponja_1">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2004
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/j4Sqs3SKNaJ4chdKXS1qqUlaWyW.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Bob Esponja: La película</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2001" 
  data-tipo="pelicula" 
  data-title="shrek" 
  data-genre="animacion aventura fantasia familia" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=shrek">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2001
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5G1RjHMSt7nYONqCqSwFlP87Ckk.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Shrek</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2021" 
  data-tipo="pelicula" 
  data-title="trollhunters el despertar de los titanes" 
  data-genre="animacion fantasia familia accion aventura" 
  data-date="2026-05-24" 
  data-link="../View Peliculas/Reproductor Universal.php?id=trollhunters_el_despertar_de_los_titanes">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2021
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fhhjAX2iDmnZksQWsJ8DdAcDBc5.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Trollhunters: El despertar de los titanes</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="los pitufos" 
  data-genre="animacion aventura fantasia" 
  data-date="2026-05-25" 
  data-link="../View Peliculas/Reproductor Universal.php?id=lospitufos_2025">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/zBdQclxQnEDOhDOjkKgKPW6jEHh.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Los Pitulos</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="Lo que le falta a esta estrella" 
  data-genre="animacion romance ciencia ficcion familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=o_que_le_falta_a_esta_estrella">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/6AmW8DglQ5VnOfW1lSMSOyfcwmW.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Lo que le falta a esta estrella</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="intensamente" 
  data-genre="animacion drama aventura fantasia disney" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=intensamente">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Intensamente</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2017" 
  data-tipo="pelicula" 
  data-title="coco" 
  data-genre="animacion disney familia aventura musical" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=coco">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2017
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/yAvisTUocxmXQZQJZ521dL9a36p.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Coco</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="plankton bob esponja" 
  data-genre="animacion aventura comedia ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=plankton">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Plankton: La pelicula</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="como entrenar a tu dragon" 
  data-genre="animacion aventura ciencia ficcion familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        CAM
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Como entrenar a tu dragón</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2017" 
  data-tipo="pelicula" 
  data-title="cars 3" 
  data-genre="animacion disney familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=cars_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2017
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cars 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2017" 
  data-tipo="pelicula" 
  data-title="un jefe en pañales" 
  data-genre="animacion infantil familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=un_jefe_en_pañales">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2017
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/dPiXM1aFbJ9XJGPyf5ZULmEjzkR.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Un jefe en pañales</h4>
  </div>
</div>

<!-- CARD 

<div class="movie-card" 
  data-anio="2015" 
  data-tipo="pelicula" 
  data-title="un gran dinosaurio" 
  data-genre="animacion aventura disney musical" 
  data-date="2026-05-22" 
  data-link="../View Peliculas/Reproductor Universal.php?id=un_gran_dinosaurio">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2015
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Un gran dinosaurio</h4>
  </div>
</div>-->

<!-- CARD -->

<div class="movie-card" 
  data-anio="1998" 
  data-tipo="pelicula" 
  data-title="pocahontas 2 vuaje a un nuevo mundo" 
  data-genre="animacion aventura disney familia romance" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=pocahontas_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1998
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ttjEx1Wo3QOxsgKDhDCB2GzHdWk.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Pocahontas 2: Viaje a un nuevo mundo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1997" 
  data-tipo="pelicula" 
  data-title="hercules" 
  data-genre="animacion aventura disney fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hercules">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1997
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/hdOS8bvta2DmDF8NHcgKWQDx0OX.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hercules</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1986" 
  data-tipo="pelicula" 
  data-title="aladdin 3 el rey de los ladrones" 
  data-genre="animacion aventura disney romance fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=aladdin_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1986
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/abWvjyJz4kcp1xDn28RwyXjoIds.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Aladdin 3: El rey de los ladrones</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1995" 
  data-tipo="pelicula" 
  data-title="toy story" 
  data-genre="animacion disney familia comedia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=toy_story">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1995
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Toy story</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1995" 
  data-tipo="pelicula" 
  data-title="pocahontas" 
  data-genre="animacion aventura disney familia romance" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=pocahontas">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1995
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ilPqjOxheKo8TVA80oMnQWKrJf4.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Pocahontas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2011" 
  data-tipo="pelicula" 
  data-title="cars 2" 
  data-genre="animacion disney familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=cars_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2011
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/okIz1HyxeVOMzYwwHUjH2pHi74I.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cars 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2010" 
  data-tipo="pelicula" 
  data-title="toy story 3" 
  data-genre="animacion disney familia comedia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=toy_story_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2010
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Toy story 3</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2008" 
  data-tipo="pelicula" 
  data-title="la sirenita 3 los comienzos de ariel" 
  data-genre="animacion aventura disney musical fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_sirenita_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2008
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/oP09KA2lP5SluKVf8AmRsf38X7q.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La sirenita 3: Los comienzos de Ariel</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2007" 
  data-tipo="pelicula" 
  data-title="la cenicienta 3 qué pasaría si…" 
  data-genre="animacion disney musical familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_cenicienta_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2007
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/hnu7CGMc1zQejwjUIEGcSikdhmV.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La Cenicienta 3: Qué pasaría si…</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2006" 
  data-tipo="pelicula" 
  data-title="leroy y stitch" 
  data-genre="animacion disney aventura familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=leroy_y_stitch">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2006
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/1RjvpZMAFZlnbLvrRYWEb2tzEyC.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Leroy y Stitch</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2006" 
  data-tipo="pelicula" 
  data-title="tierra de osos 2" 
  data-genre="animacion disney familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=tierra_de_osos_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2006
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Tierra de osos 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2006" 
  data-tipo="pelicula" 
  data-title="cars" 
  data-genre="animacion disney familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=cars">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2006
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/abW5AzHDaIK1n9C36VdAeOwORRA.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Cars</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2005" 
  data-tipo="pelicula" 
  data-title="lilo y stitch 2 el efecto del defecto" 
  data-genre="animacion aventura disney familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=lilo_y_stitch_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2005
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/dTYyAszU6NWbmWGvhqLZpZTdS5T.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Lilo y Stitch 2: El efecto del defecto</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2005" 
  data-tipo="pelicula" 
  data-title="tarzan 2" 
  data-genre="animacion aventura familia disney" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=tarzan_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2005
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5KRnGepv2b1daJ2WM8ZGnPS64nl.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Tarzan 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2002" 
  data-tipo="pelicula" 
  data-title="peter pan 2 en regreso al País de nunca jamas" 
  data-genre="animacion aventura familia disney fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=peter_pan_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2002
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/kkFeLiMeih9jgXatztoloOyGSbc.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Peter Pan 2: En Regreso al País de Nunca Jamás</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2002" 
  data-tipo="pelicula" 
  data-title="la cenicienta 2 la magia no termina a media noche" 
  data-genre="animacion disney musical familia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_cenicienta_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2002
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/2EoH5WWtDYuQLYVLHeJxfvbSRyK.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La Cenicienta 2: ¡La magia no termina a media noche!</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2002" 
  data-tipo="pelicula" 
  data-title="lilo y stitch" 
  data-genre="animacion aventura disney familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=lilo_y_stitch">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2002
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/dTYyAszU6NWbmWGvhqLZpZTdS5T.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Lilo y Stitch</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2000" 
  data-tipo="pelicula" 
  data-title="la sirenita 2 regreso al mar" 
  data-genre="animacion aventura disney musical fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_sirenita_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2000
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fresAluIWfBRwdQOaVcM4i5uGsP.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La sirenita 2: Regreso al mar</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1999" 
  data-tipo="pelicula" 
  data-title="tarzan" 
  data-genre="animacion aventura familia disney" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=tarzan">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1999
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/u9WgwjFpBWc3eQxddUFSicH2K6p.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Tarzan</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1999" 
  data-tipo="pelicula" 
  data-title="toy story 2" 
  data-genre="animacion disney familia comedia aventura" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=toy_story_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1999
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Toy story 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2023" 
  data-tipo="pelicula" 
  data-title="spider man cruzando el multiverso spider man 2" 
  data-genre="accion aventura animacion marvel" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=spiderman_man_cruzando_el_multi_verso_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2023
      </span>
      <span class="tag hd">
        MALA CALIDAD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Spider-Man: Cruzando el Multi-Verso</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="la rosa de versalles" 
  data-genre="animacion romance historia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=la_rosa_de_versalles">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/F4OILPPbBfCYkWoW5be1UZnmJq.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>La rosa de Versalles</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="sonic 3" 
  data-genre="animacion comedia ciencia ficcion familiar" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=sonic_3">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/vlAXtzNWQ3VSZtIinhHqcPXS1Oc.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Sonic 3: La pelicula</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="robot salvaje" 
  data-genre="animacion aventura animales familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=robot_salvaje">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/dE8Cwtnb31637ygPHTVDxFkg8K4.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Robot salvaje</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1989" 
  data-tipo="pelicula" 
  data-title="dragon ball z devuelveme a mi gohan" 
  data-genre="anime animacion accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_devuelveme_a_mi_gohan">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1989
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Devuelveme a mi Gohan</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2025" 
  data-tipo="pelicula" 
  data-title="depredador asesino de asesinos" 
  data-genre="animacion accion ciencia ficcion suspenso" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=depredador_asesino_de_asesinos">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2025
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/e9gpb3U9kerduyipUX31Y00vfuJ.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Depredador: Asesino de asesinos</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="liga de la justicia crisis en tierras infinitas parte 2" 
  data-genre="accion animacion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=liga_de_la_justicia_crisis_en_tierras_infinitas_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/aOT8n3YOOkInZ5VHJN4FffHrm43.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Liga de la Justicia: Crisis en Tierras Infinitas - Parte 2</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="Al rescate de fondo de Bikini bob esponja" 
  data-genre="animacion aventura comedia familia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Al rescate de fondo de Bikini: La película de Arenita Mejillas</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="garfield fuera de casa" 
  data-genre="familia comedia aventura animacion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=garfield_fuera_de_casa">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/6QR2FOCQr41gSduN70WulRIhJb7.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Garfield: Fuera de casa</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="el arca de noe" 
  data-genre="animacion musical familia niño infantil biblico" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_arca_de_noe">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/fRaBjht3S1HU6lJrz2SoFwwOZQM.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El Arca De Noé</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="el pájaro loco lío en el campamento" 
  data-genre="animacion aventura familia comedia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_pajaro_loco_se_va_de_campamento">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/x7QXH6T8oTKlUbKt8TD1rPimzCr.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El pájaro loco ¡Lío en el campamento!</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2024" 
  data-tipo="pelicula" 
  data-title="megamente 2 contra el sindicato del mal" 
  data-genre="animacion ciencia ficcion comedia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=megamente_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2024
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/jdXLCBv0oFjWbTtQTuoJFXVPsbd.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Megamente 2: Contra el sindicato del mal</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1990" 
  data-tipo="pelicula" 
  data-title="dragon ball z la super batalla" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_super_batalla">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1990
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/69dMY6CPe6mqi7nMC2bVeCcjJQI.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: La super batalla</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1991" 
  data-tipo="pelicula" 
  data-title="dragon ball z el super saiyajin son goku"
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_super_saiyayin_son_goku">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1991
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El super saiyajin Son Goku</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1992" 
  data-tipo="pelicula" 
  data-title="dragon ball z ños tres grandes Super Saiyans"
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_tres_grendes_guerreros_saiyajin">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1992
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Los tres grandes Super Saiyans</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1992" 
  data-tipo="pelicula" 
  data-title="dragon ball z el regreso de cooler" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_regreso_de_cooler">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1992
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El regreso de cooler</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1993" 
  data-tipo="pelicula" 
  data-title="dragon ball z la galaxia corre peligro" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_galaxia_corre_peligro">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1993
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: La galaxia corre peligro</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1993" 
  data-tipo="pelicula" 
  data-title="dragon ball z el poder invensible" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_poder_invencible">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1993
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El poder invensible</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1994" 
  data-tipo="pelicula" 
  data-title="dragon ball z el regreso del guerrero legendario" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_regreso_de_broly">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1994
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El regreso del guerrero legendario</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1993" 
  data-tipo="pelicula" 
  data-title="dragon ball z los dos guerreros del futuro" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_guerreros_del_futuro">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1993
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Los dos guerreros del futuro</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2023" 
  data-tipo="pelicula" 
  data-title="ninja turtles caos mutante" 
  data-genre="animacion accion comedia Ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=ninja_turtles_caos_mutante">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2023
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/mgBXgA8jHext4KRWg84Cux5Y94L.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Ninja Turtles: Caos mutante</h4>
  </div>
</div>

<!-- CARD

<div class="movie-card" 
  data-anio="2023" 
  data-tipo="pelicula" 
  data-title="super mario bros la pelicula" 
  data-genre="animacion aventura fantasia comedia familiar" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=super_mario_bros">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2023
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/k36QyeVsy851npTUQL08jO8hqip.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Super Mario Bros: La pelicula</h4>
  </div>
</div> -->

<!-- CARD -->

<div class="movie-card" 
  data-anio="1998" 
  data-tipo="pelicula" 
  data-title="dragon ball dragon ball z una gran aventura mistica" 
  data-genre="animacion anime accion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_gran_aventura_mistica">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1998
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/f2BipTKswrdpqoCc1xJDyL35rJy.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball: Gran aventura mística</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1996" 
  data-tipo="pelicula" 
  data-title="dragon ball dragon ball z el camino hacia el poder" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_el_camino_hacia_el_poder">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1996
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/wPkoqtFhDoIbzt61oOYwmLOZdAg.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball: El camino hacia el poder</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1996" 
  data-tipo="pelicula" 
  data-title="dragon ball dragon ball z gt 100 años despues" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_gt_despues_de_100_años">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1996
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball GT: Después 100 años</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1995" 
  data-tipo="pelicula" 
  data-title="dragon ball z el ataque del dragon" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_ataque_del_dragon">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1995
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El ataque del dragon</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2023" 
  data-tipo="pelicula" 
  data-title="el gato con botas 2 el ultimo deseo" 
  data-genre="animacion comedia aventura fantasia familia infantil" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=el_gato_con_botas_2">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        2023
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/ygqZ758t5oBYKP1y8LHdeflNW79.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>El gato con botas 2: El último deseo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1991" 
  data-tipo="pelicula" 
  data-title="dragon ball z los rivales mas poderosos" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_rivales_mas_poderosos">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1991
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: Los rivales mas poderosos</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1990" 
  data-tipo="pelicula" 
  data-title="dragon ball z la pelea de bardock vs freezer" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_bardock_vs_freezer">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1990
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: La pelea de Bardock vs Freezer</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1990" 
  data-tipo="pelicula" 
  data-title="dragon ball z el mas fuerte del mundo" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_mas_fuerte_del_mundo">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1990
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El mas fuerte del mundo</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1987" 
  data-tipo="pelicula" 
  data-title="dragon ball dragon ball z la princesa durmiente del castillo del mal" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_la_princesa_durmiente">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1987
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball: La princesa durmiente del castillo del mal</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1986" 
  data-tipo="pelicula" 
  data-title="dragon ball dragon ball z la leyenda del dragon shenron" 
  data-genre="animacion anime accion ciencia ficcion fantasia" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_la_leyenda_de_shenron">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1986
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball: La leyenda del dragón Shenron</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="1994" 
  data-tipo="pelicula" 
  data-title="dragon ball z el combate final" 
  data-genre="animacion anime accion ciencia ficcion" 
  data-date="2026-05-27" 
  data-link="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_combate_final">
  <div class="poster">
    <div class="tags">
      <span class="tag series">
        Pelicula
      </span>
      <span class="tag year">
        1994
      </span>
      <span class="tag hd">
        HD
      </span>

    </div>
    <img src="https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Dragon Ball Z: El combate final</h4>
  </div>
</div>

<!-- CARD -->

<div class="movie-card" 
  data-anio="2026" 
  data-tipo="pelicula" 
  data-title="hoppers" 
  data-genre="animacion aventura disney familia ciencia ficcion" 
  data-date="2026-05-31" 
  data-link="../View Peliculas/Reproductor Universal.php?id=hoppers">
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
    <img src="https://image.tmdb.org/t/p/w300/4Z0E1W7YvQ0aVtgj7KKtktb9ukd.jpg" loading="lazy" decoding="async" draggable="false">
    <div class="overlay"></div>
  </div>
  <div class="movie-info">
    <h4>Hoppers: Operación castor</h4>
  </div>
</div>

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