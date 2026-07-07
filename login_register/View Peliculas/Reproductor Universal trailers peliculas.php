<?php
session_start();
require_once "../config.php";

/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

$userId = (int) $_SESSION['id'];

/* =========================
   OBTENER USUARIO
========================= */

$stmt = $conn->prepare("SELECT id, name, email, foto, status, paid_until FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* =========================
   SI NO EXISTE → LOGOUT
========================= */

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

/* =========================
   SI ADMIN SUSPENDIÓ
========================= */

if ($user['status'] !== "active") {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit();
}

/* =========================
   SI CUENTA EXPIRÓ
========================= */

if (!empty($user['paid_until']) && strtotime($user['paid_until']) < time()) {

    $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?expired=1");
    exit();
}
 

/* =========================
   DATOS DEL USUARIO
========================= */

$nombre = $user['name'] ?? 'Usuario';
$email  = $user['email'] ?? '';
$foto   = !empty($user['foto']) ? $user['foto'] : 'Logo Poster MovieTx PNG/Logo MovieTx.png';


/* ======================================
   🔥 ACTIVO DEL USUARIO
====================================== */
if(isset($_SESSION['id']) && isset($_COOKIE['device_token'])){

    $stmt = $conn->prepare("
        UPDATE dispositivos 
        SET last_ping = NOW(), is_active = 1 
        WHERE user_id = ? AND token = ?
    ");
    $stmt->bind_param("is", $_SESSION['id'], $_COOKIE['device_token']);
    $stmt->execute();
}

/* ======================================
   🚫 VERIFICAR SI EL DISPOSITIVO ESTÁ BLOQUEADO
====================================== */
if(isset($_SESSION['id']) && isset($_COOKIE['device_token'])){

    $stmt = $conn->prepare("
        SELECT blocked
        FROM dispositivos
        WHERE user_id = ?
        AND token = ?
        LIMIT 1
    ");

    $stmt->bind_param("is", $_SESSION['id'], $_COOKIE['device_token']);
    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();

    // SI ESTÁ BLOQUEADO
    if($res && intval($res['blocked']) === 1){

        // DESTRUIR SESIÓN
        $_SESSION = [];
        session_destroy();

        // ELIMINAR COOKIE
        setcookie("device_token", "", time() - 3600, "/");

        // REDIRIGIR
        header("Location: index.php");
        exit;
    }
}

/* ======================================
   ⚫ LIMPIAR INACTIVOS (GLOBAL)
====================================== */
$conn->query("
    UPDATE dispositivos
    SET is_active = 0
    WHERE is_active = 1
    AND last_ping < NOW() - INTERVAL 2 MINUTE
");
 

/* =========================
   VERIFICACIÓN AJAX
   (para detectar suspensión en vivo)
========================= */

if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
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

<?php require_once "../auth.php"; ?>



<!--COLORES DE BARRA-->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>MovieTx - Reproductor</title>
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans&amp;family=Roboto&amp;display=swap" rel="stylesheet"/>
  <link rel="icon" type="image/png" href="../Logo/Logo Nuevo -512x512.png">
  <link href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" rel="stylesheet"/>
  <style>
    :root {
      --plyr-color-main: #ff0000;
      --plyr-video-control-color:rgb(255, 255, 255);
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      outline: none;
      -webkit-tap-highlight-color: transparent;
      user-select: none;
    }
    body {
      background-color: #000;
      font-family: 'PT Sans', sans-serif;
      color: #fff;
    }

    .info {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  justify-content: center;
  padding: 10px;
  border-radius: 12px;
  margin: 10px;
  font-size: 14px;

  position: relative;
  overflow: hidden;
  z-index: 1;
}

/* 🌈 BORDE ARCOIRIS CONTENEDOR */
.info::before {
  content: "";
  position: absolute;
  inset: -2px;
  border-radius: 12px;

  background: conic-gradient(
    #ff0000,
    #ff7300,
    #fffb00,
    #48ff00,
    #00f7ff,
    #0066ff,
    #a200ff,
    #ff0000
  );

  animation: giroInfo 8s linear infinite;
  z-index: 0;
}

/* FONDO INTERNO */
.info::after {
  content: "";
  position: absolute;
  inset: 2px;
  background: #000; /* mantiene tu fondo */
  border-radius: 10px;
  z-index: 1;
}

/* CONTENIDO ENCIMA */
.info * {
  position: relative;
  z-index: 2;
}

    .info i {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .hd-tag {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .hd-tag svg {
      border-radius: 4px;
    }
    .titulo, .sinopsis {
      padding: 10px;
    }
    .recomendaciones {
      padding: 10px;
    }
    .recomendaciones h4 {
  position: relative;
  display: inline-block;
  padding: 6px 14px;
  border-radius: 12px;
  color: #fff;
  overflow: hidden;
  z-index: 1;
}

.recomendaciones h4::before {
  content: "";
  position: absolute;
  inset: -2px;
  border-radius: 12px;

  background: conic-gradient(
    #ff0000,
    #ff7300,
    #fffb00,
    #48ff00,
    #00f7ff,
    #0066ff,
    #a200ff,
    #ff0000
  );

  animation: giroInfo 8s linear infinite;
  z-index: 0;
}

.recomendaciones h4::after {
  content: "";
  position: absolute;
  inset: 2px;
  background: #000;
  border-radius: 10px;
  z-index: 1;
}

.recomendaciones h4 span {
  position: relative;
  z-index: 2;
}

@keyframes giroInfo {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

    .series-grid{
  display: grid;
  grid-template-columns: repeat(3, 1fr); /* SIEMPRE 3 columnas */
  gap: 10px;
  text-align: center;
  width: 100%;
}

.serie{
  display: flex;
  flex-direction: column;
  align-items: center;
  text-decoration: none;
  color: white;
  min-width: 0;
}

.serie img{
  width: 100%;
  aspect-ratio: 2 / 3;
  object-fit: cover;
  border-radius: 10px;
}

/* 📱 móviles pequeños */
.serie p{
  margin-top: 6px;
  font-size: 11px;
  line-height: 1.3;
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;

  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;

  white-space: normal;
  text-align: center;
}

/* 📱 móviles medianos */
@media (min-width: 480px){
  .series-grid{
    gap: 12px;
  }

  .serie p{
    font-size: 12px;
  }
}

/* 📱 móviles grandes */
@media (min-width: 768px){
  .series-grid{
    gap: 15px;
  }

  .serie p{
    font-size: 14px;
  }
}

/* 💻 PC */
@media (min-width: 1024px){

  .series-grid{
    grid-template-columns: repeat(6, 1fr); /* imágenes una al lado de la otra */
    gap: 20px;
  }

}


    html { font-size: 100%; }
    body { font-size: 1rem; }
    h1 { font-size: 2rem; }
    h2 { font-size: 1.5rem; }
    h3, h4 { font-size: 1.2rem; }

    @media screen and (max-width: 768px) {
      body { font-size: 1.05rem; }
      h1 { font-size: 1.5rem; }
      h2 { font-size: 1.3rem; }
      h3, h4 { font-size: 1.1rem; }
      .info, .sinopsis p, .serie p {
        font-size: 0.80rem !important; /*estaba en 1rem*/
      }
    }

    @media screen and (max-width: 480px) {
      body { font-size: 1.1rem; }
      h1 { font-size: 1.4rem; }
      h2 { font-size: 1.2rem; }
      h3, h4 { font-size: 0.80rem; } /*estaba en 1rem*/
    }

    body, p, h1, h2, h3, h4, h5, h6, span, a, .titulo, .sinopsis, .recomendaciones, .serie {
      font-style: italic;
    }

    .info, .info *, .info span, .info i {
      font-style: normal !important;
    }

    #resume-msg {
      display: none;
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0, 0, 0, 0.85);
      padding: 10px 20px;
      color: #0f0;
      border-radius: 8px;
      z-index: 9999;
      font-weight: bold;
      max-width: 90%;
      font-size: 1rem;
      text-align: center;
      box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
      opacity: 0;
      transition: opacity 0.8s ease;
    }

    #resume-msg.visible {
      display: block;
      opacity: 1;
    }

    @media (max-width: 480px) {
      #resume-msg {
        font-size: 0.9rem;
        padding: 8px 16px;
      }
    }

    #genero-texto.colapsado {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* ACA ES LA ANIMACION DE FAVORITOS */
    @keyframes pop {
      0%   { transform: scale(1); }
      50%  { transform: scale(1.3); }
      100% { transform: scale(1); }
    }
     
    /* --- Mejor experiencia deslizable en móvil --- */
    @media (max-width: 880px) {
  
      /* Lista de temporadas horizontal deslizable */
  
      .seasons-list {
        display: flex;    
        overflow-x: auto;   
        gap: 12px;   
        padding: 8px;   
        -webkit-overflow-scrolling: touch; /* suavidad en iOS */   
        scroll-snap-type: x mandatory;
      }

  
      .seasons-list ul {
        flex-direction: row;
        gap: 12px;
        width: max-content;
      }

      .seasons-list li {
        flex: 0 0 auto;
        scroll-snap-align: start;
        min-width: 160px;
      }

  
      .seasons-list::-webkit-scrollbar {
        display: none; /* oculta scrollbar en móviles */
      }
    }


    @media (max-width: 768px) {
      /* Episodios también deslizable si hay muchos */
      .episodes-grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        padding: 8px !important;
        overflow-x: visible !important;
        overflow-y: auto !important;
      }

      .episodes-grid::-webkit-scrollbar {
        display: none;
      }
  
      .episode-card {
        flex: none !important;
        width: 100% !important;
        align-items: center !important;
      }
    }

    /* Estilo para episodio en reproducción */

    .episode-card.viendo {
  
      border: 2px solid var(--accent);
  
      background: rgba(255, 45, 91, 0.08);
 
      box-shadow: 0 0 12px rgba(255,45,91,0.6);
  
      position: relative;

    }

    .episode-card.viendo::after {
      content: "▶ Viendo ahora";
      position: absolute;
      top: 8px;
      right: 8px;
      background: var(--accent);
      color: #fff;
      font-size: 0.7rem;
      font-weight: 600;
      padding: 3px 6px;
      border-radius: 6px;
    }

    @keyframes shine {
      0% { transform: translateX(-100%) rotate(25deg); }
      50% { transform: translateX(100%) rotate(25deg); }
      100% { transform: translateX(100%) rotate(25deg); }
    }
    .info-pelicula {
      text-align: center;
      margin-top: 20px;
      color: #fff;
    }

    .sinopsis {
      font-size: 1rem;
      color: #bbb;
      max-width: 600px;
      margin: 10px auto 20px;
    }

    .acciones {
      display: flex;
      justify-content: center;
      gap: 15px;
      flex-wrap: wrap;
    }

    .play-btn {
      background: linear-gradient(135deg, #1db954, #1ed760);
      color: white;
      padding: 12px 22px;
      border: none;
      border-radius: 999px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.2s ease, box-shadow 0.3s ease;
      box-shadow: 0 8px 20px rgba(29, 185, 84, 0.35);
    }

    .play-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(29, 185, 84, 0.55);
    }

    @keyframes shine {
      0% { transform: translateX(-100%) rotate(25deg); } 
      50% { transform: translateX(100%) rotate(25deg); }
      100% { transform: translateX(100%) rotate(25deg); }
    }
  
    .season-episode-tag {
      display: inline-block;
      margin-top: 8px;
      margin-bottom: 14px;
      padding: 6px 14px;
      border-radius: 999px;
      background: linear-gradient(90deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
      border: 1px solid rgba(255,255,255,0.15);
      color: #9ad0ff;
      font-weight: 600;
      font-size: 0.9rem;
      letter-spacing: 0.3px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.35);
    }

    /* 💎 Barra de progreso estilo "Neón líquido celeste" */

    @keyframes neonFlow {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

  </style>
</head>

<body ondragstart="return false;" ondrop="return false;">
  
<script>
// 🔹 Evita volver a la página anterior
// y manda directo a index.html al presionar atrás
window.history.pushState(null, null, window.location.href);
window.addEventListener('popstate', function () {
  // Redirige directamente al index y reemplaza el historial
  window.location.replace("../inicio.php");
});

// 🔹 Limpia el historial para que no se pueda regresar desde index
if (window.performance && window.performance.navigation.type === 2) {
  // Si se intenta volver con cache, redirige igual
  window.location.replace("../inicio.php");
}
</script>

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

<!-- 🔴 Fin pantalla de carga neón -->
  
 <style>

/* PROGRESSBAR */


/* ===============================
   🌈 PROGRESS BAR ARCOÍRIS ANIMADA
================================ */

#progressBar {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: #222;
  cursor: pointer;
  overflow: hidden;
  position: relative;
}

/* =====================================
   🌈 PROGRESS BAR PREMIUM ESTABLE
===================================== */

#progressBar{

  --track-height: 6px;
  --thumb-size: 14px;

  -webkit-appearance:none;
  appearance:none;

  position:relative;

  width:100%;
  height:var(--track-height);

  border-radius:999px;

  background:#1f1f1f;

  cursor:pointer;

  overflow:visible;

  z-index:1;

  transform:translateZ(0);

  will-change:auto;

  backface-visibility:hidden;
}


/* =====================================
   🔥 CAPA ARCOÍRIS
===================================== */

#progressBar::before{

  content:"";

  position:absolute;

  top:0;
  left:0;

  width:var(--progress,0%);
  height:100%;

  border-radius:999px;

  background:
    linear-gradient(
      270deg,
      #ff0000,
      #ff9900,
      #ffff00,
      #00ff00,
      #00ccff,
      #0066ff,
      #cc00ff,
      #ff0000
    );

  background-size:400% 100%;

  animation:rainbowMove 6s linear infinite;

  box-shadow:
    0 0 6px rgba(255,255,255,.18),
    0 0 14px rgba(0,255,255,.18);

  will-change:background-position,width;

  transform:translateZ(0);

  pointer-events:none;

  z-index:0;
}


/* =====================================
   🎞️ ANIMACIÓN SUAVE
===================================== */

@keyframes rainbowMove{

  0%{
    background-position:0% 50%;
  }

  100%{
    background-position:200% 50%;
  }
}


/* =====================================
   🎯 THUMB WEBKIT
===================================== */

#progressBar::-webkit-slider-thumb{

  -webkit-appearance:none;
  appearance:none;

  width:var(--thumb-size);
  height:var(--thumb-size);

  border-radius:50%;

  background:#fff;

  border:3px solid #00d9ff;

  box-shadow:
    0 0 6px rgba(0,255,255,.95),
    0 0 14px rgba(0,255,255,.65),
    0 0 24px rgba(0,255,255,.35);

  margin-top:calc(
    (var(--track-height) - var(--thumb-size)) / 2
  );

  position:relative;

  z-index:3;

  transition:
    transform .15s ease,
    box-shadow .2s ease;

  transform:translateZ(0);
}


/* =====================================
   🖱️ HOVER PC
===================================== */

@media (hover:hover){

  #progressBar:hover::-webkit-slider-thumb{

    transform:scale(1.08);

    box-shadow:
      0 0 10px rgba(0,255,255,1),
      0 0 20px rgba(0,255,255,.7),
      0 0 30px rgba(0,255,255,.45);
  }

}


/* =====================================
   🔥 ACTIVE
===================================== */

#progressBar:active::-webkit-slider-thumb{
  transform:scale(1.15);
}


/* =====================================
   🦊 FIREFOX
===================================== */

#progressBar::-moz-range-thumb{

  width:var(--thumb-size);
  height:var(--thumb-size);

  border:none;
  border-radius:50%;

  background:#fff;

  border:3px solid #00d9ff;

  box-shadow:
    0 0 6px rgba(0,255,255,.95),
    0 0 14px rgba(0,255,255,.65),
    0 0 24px rgba(0,255,255,.35);

  transition:
    transform .15s ease,
    box-shadow .2s ease;
}


/* =====================================
   📱 MOBILE
===================================== */

@media (max-width:768px){

  #progressBar{

    --track-height:5px;
    --thumb-size:13px;
  }

}


/* =====================================
   📱 IPHONE PEQUEÑOS
===================================== */

@media (max-width:480px){

  #progressBar{

    --track-height:4px;
    --thumb-size:12px;
  }

}

#video-container {
  position: relative;
  overflow: hidden;
}

.dots::after {
  content: '';
  animation: dotPulse 1.5s infinite steps(4);
}

@keyframes dotPulse {
  0% { content: ''; }
  25% { content: '.'; }
  50% { content: '..'; }
  75% { content: '...'; }
  100% { content: ''; }
}

.age-tag {
  border: 1px solid #fff;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 12px;
}


/* =========================================================
   🎬 REPRODUCTOR RESPONSIVE
   PC + NOTEBOOK + ANDROID + IPHONE
========================================================= */
.mobile-player video{
  display:block;
}
/* ===== PC POR DEFECTO ===== */
.mobile-player {

  position: relative;

  width: 100%;
  max-width: 1500px;

  margin: 20px auto;

  aspect-ratio: 16 / 9;

  background: black;
  overflow: hidden;

  border-radius: 18px;

  box-shadow:
    0 0 20px rgba(0,0,0,.35),
    0 12px 40px rgba(0,0,0,.55);

  touch-action: manipulation;

  transition:
    width .3s ease,
    border-radius .3s ease,
    box-shadow .3s ease;
}


/* =========================================================
   🖥️ MONITORES GRANDES
========================================================= */

@media screen and (min-width: 1600px){

  .mobile-player{
    width: 75%;
    max-width: 1650px;
  }

}


/* =========================================================
   💻 NOTEBOOK / LAPTOP
========================================================= */

@media screen and (min-width: 992px) and (max-width: 1599px){

  .mobile-player{
    width: 90%;
    max-width: 1450px;
  }

}


/* =========================================================
   📱 TABLETS
========================================================= */

@media screen and (min-width: 768px) and (max-width: 991px){

  .mobile-player{

    width: 100%;
    max-width: 100%;

    margin: 0;

    border-radius: 0;

    box-shadow: none;
  }

}


/* =========================================================
   📱 ANDROID + IPHONE
========================================================= */

@media screen and (max-width: 767px){

  .mobile-player{

    position: sticky;
    top: 0;
    z-index: 1000;

    width: 100%;
    max-width: 100%;

    margin: 0;

    aspect-ratio: 16 / 9;

    border-radius: 0;

    box-shadow: none;
  }

}


/* =========================================================
   📱 MÓVILES PEQUEÑOS
========================================================= */

@media screen and (max-width: 480px){

  .mobile-player{
    aspect-ratio: 16 / 9;
  }

}


.mobile-player video {
  width: 100%;
  height: 100%;
  object-fit: contain;
  background: black;
  pointer-events: none;
}


/* OVERLAY */
.player-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;

  z-index: 5;

  display: flex;
  flex-direction: column;
  justify-content: space-between;

  background: linear-gradient(
    to bottom,
    rgba(0,0,0,.55),
    rgba(0,0,0,.15),
    rgba(0,0,0,.55)
  );

  pointer-events: auto;
  transition: opacity .3s;
}


/* CENTER */
.overlay-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 26px;

  pointer-events: none;
}

.overlay-center button {
  pointer-events: auto;
}


/* BOTTOM */


.time {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #ccc;
}


/* HIDE */
.player-overlay.hide {
  opacity: 0;
  pointer-events: none;
}


.seek-btn,
.play-btn {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1 / 1;
}


/* PLAY */
.play-btn {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(255,255,255,.2);
  border: none;
  font-size: 28px;
}

.seek-btn {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  border: 2px solid rgba(255,255,255,.7);
  background: rgba(255, 255, 255, 0.45);
  font-size: 18px;
  font-weight: bold;
}


/* 📱 BOTONES MÁS CÓMODOS EN MÓVIL */
@media screen and (max-width: 767px){

  .overlay-center{
    gap: 18px;
  }

  .play-btn{
    width: 62px;
    height: 62px;
    font-size: 24px;
  }

  .seek-btn{
    width: 56px;
    height: 56px;
    font-size: 16px;
  }

}


/* 💻 CONTROLES MÁS ELEGANTES EN PC */
@media screen and (min-width: 1200px){

  .overlay-center{
    gap: 22px;
  }

  .play-btn{
    width: 68px;
    height: 68px;
  }

  .seek-btn{
    width: 58px;
    height: 58px;
  }

}

/* =========================
   BOTTOM CONTROLS
========================= */

.overlay-bottom{
  position:absolute;
  left:0;
  bottom:0;

  width:100%;
  z-index:6;

  padding: clamp(10px, 2vw, 18px);

  display:flex;
  flex-direction:column;

  gap:10px;
}


/* CONTROLES */
/* =========================
   OVERLAY BOTTOM
========================= */

.overlay-bottom{
  position:absolute;
  left:0;
  bottom:0;

  width:100%;
  z-index:6;

  display:flex;
  flex-direction:column;

  gap:10px;

  padding:
    clamp(10px, 2vw, 18px)
    clamp(10px, 2vw, 18px)
    clamp(12px, 2vw, 20px);
}


/* =========================
   FILA INFERIOR
========================= */

.overlay-bottom-controls{

  display:flex;
  align-items:center;
  justify-content:space-between;

  gap:14px;

  width:100%;
}


/* =========================
   TIME
========================= */

.time{

  display:flex;
  align-items:center;

  gap:5px;

  min-width:0;

  color:#d8d8d8;

  font-size:clamp(11px, 1vw, 14px);

  font-weight:500;

  line-height:1;

  white-space:nowrap;

  font-variant-numeric: tabular-nums;

  user-select:none;
}


.time-separator{
  opacity:.7;
}


/* =========================
   PROGRESS
========================= */

#progressBar{
  width:100%;
}


/* =========================
   FULLSCREEN
========================= */

.fullscreen-btn{

  flex-shrink:0;

  width:42px;
  height:42px;

  border:none;
  border-radius:10px;

  background:rgba(255,255,255,.15);

  color:white;

  display:flex;
  align-items:center;
  justify-content:center;

  font-size:18px;

  padding:0;
}


/* =========================
   TABLETS
========================= */

@media (max-width: 991px){

  .overlay-bottom{
    gap:8px;
    padding:14px;
  }

  .fullscreen-btn{
    width:40px;
    height:40px;
  }

}


/* =========================
   ANDROID + IPHONE
========================= */

@media (max-width: 767px){

  .overlay-bottom{

    gap:7px;

    padding:
      10px
      max(10px, env(safe-area-inset-right))
      max(14px, env(safe-area-inset-bottom))
      max(10px, env(safe-area-inset-left));
  }

  .overlay-bottom-controls{
    gap:10px;
  }

  .time{
    font-size:11px;
  }

  #progressBar{
    height:5px;
  }

  .fullscreen-btn{
    width:38px;
    height:38px;
    font-size:16px;
    border-radius:9px;
  }

}


/* =========================
   IPHONE PEQUEÑOS
========================= */

@media (max-width: 480px){

  .time{
    font-size:10px;
  }

  .fullscreen-btn{
    width:34px;
    height:34px;
    font-size:15px;
    border-radius:8px;
  }

  #progressBar{
    height:4px;
  }

}


/* TIEMPO */
.overlay-bottom .time{
  display:flex;
  justify-content:space-between;
  align-items:center;

  width:100%;

  font-size: clamp(11px, 1.3vw, 14px);

  color:#ccc;

  line-height:1;
}


/* PROGRESS */
.overlay-bottom-controls #progressBar{
  width:100%;
  min-width:0;
}


/* FULLSCREEN */
.fullscreen-btn{
  flex-shrink:0;

  width:42px;
  height:42px;

  border:none;
  border-radius:10px;

  background:rgba(255, 255, 255, 0.2);

  color:white;

  font-size:20px;

  display:flex;
  align-items:center;
  justify-content:center;

  padding:0;
}


/* =========================
   TABLETS
========================= */

@media (max-width: 991px){

  .overlay-bottom{
    padding:14px;
    gap:8px;
  }

  .fullscreen-btn{
    width:40px;
    height:40px;
    font-size:18px;
  }

}


/* =========================
   ANDROID + IPHONE
========================= */

@media (max-width: 767px){

  .overlay-bottom{
    padding:
      10px
      max(10px, env(safe-area-inset-right))
      max(14px, env(safe-area-inset-bottom))
      max(10px, env(safe-area-inset-left));

    gap:7px;
  }

  .overlay-bottom-controls{
    gap:10px;
  }

  .overlay-bottom .time{
    font-size:12px;
  }

  #progressBar{
    height:5px;
  }

  .fullscreen-btn{
    width:38px;
    height:38px;
    border-radius:9px;
    font-size:17px;
  }

}


/* =========================
   IPHONE PEQUEÑOS
========================= */

@media (max-width: 480px){

  .overlay-bottom{
    padding:
      8px
      max(8px, env(safe-area-inset-right))
      max(12px, env(safe-area-inset-bottom))
      max(8px, env(safe-area-inset-left));
  }

  .overlay-bottom-controls{
    gap:8px;
  }

  .overlay-bottom .time{
    font-size:11px;
  }

  #progressBar{
    height:4px;
  }

  .fullscreen-btn{
    width:34px;
    height:34px;
    font-size:15px;
  }

}


/* =========================
   FULLSCREEN
========================= */

.mobile-player:fullscreen .overlay-bottom,
.mobile-player.fake-fullscreen .overlay-bottom{

  padding:
    18px
    max(18px, env(safe-area-inset-right))
    max(20px, env(safe-area-inset-bottom))
    max(18px, env(safe-area-inset-left));
}

.mobile-player:fullscreen .overlay-bottom {
  padding: 18px;
}


/* FULLSCREEN FIX */
.mobile-player:fullscreen {
  width: 100vw;
  height: 100vh;
  border-radius: 0;
  background: black;
}

.mobile-player:fullscreen .player-overlay {
  height: 100%;
}

.mobile-player:fullscreen video {
  object-fit: contain;
}

.mobile-player:fullscreen {
  width: 100vw;
  height: 100vh;
}


.mobile-player.fake-fullscreen video {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.mobile-player.fake-fullscreen {
  position: fixed;
  inset: 0;
  z-index: 9999;
  width: 100vw;
  height: 100vh;
  background: black;
  border-radius: 0;
}

.mobile-player.fake-fullscreen video {
  width: 100%;
  height: 100%;
  object-fit: contain;
}


/* MODAL */
.modal-temp {
  position: fixed;
  inset: 0;
  display: none;
  z-index: 99999;
}

.modal-temp.active {
  display: flex;
}

.modal-temp-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(8px);
}


/* CAJA */
.modal-temp-box {
  position: relative;
  margin: auto;
  width: 95%;
  max-width: 500px;
  background: #111;
  border-radius: 16px;
  padding: 15px;
  z-index: 2;
  animation: aparecer .3s ease;
}

@keyframes aparecer {
  from {
    transform: scale(.9);
    opacity:0;
  }

  to {
    transform: scale(1);
    opacity:1;
  }
}


/* HEADER */
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h2 {
  font-size: 1.2rem;
}

#cerrarModalTemp {
  cursor: pointer;
  font-size: 20px;
}


/* TEMPORADAS */
.temp-list {
  display: flex;
  gap: 10px;
  margin: 15px 0;
  overflow-x: auto;
}

.temp-btn {
  background: #222;
  padding: 8px 14px;
  border-radius: 999px;
  cursor: pointer;
  white-space: nowrap;
}

.temp-btn.active {
  background: #ff2d5b;
}


/* EPISODIOS HORIZONTAL */
.episodios-list {
  display: flex;
  gap: 10px;
  overflow-x: auto;
  padding-bottom: 10px;
}

.ep-card:hover {
  background: #ff2d5b;
}

</style>



<script>
const PERFIL_ACTIVO = <?php echo isset($_SESSION['perfil_id']) ? 'true' : 'false'; ?>;
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {

  const video = document.getElementById('videoPlayer');
  if (!video) return;

  const movieId = movie.id;
  const titulo = movie.titulo || "";
  const imginicio = movie.imginicio || "";

  if (!imginicio) {
    console.log("No hay imginicio");
  }

  // 🔥 DETECTAR PERFIL O USUARIO
  const URL_GUARDADO = (typeof PERFIL_ACTIVO !== "undefined" && PERFIL_ACTIVO)
    ? "perfil_inicio_data.php"
    : "inicio_data.php";

  function guardarProgreso() {

    const progreso = Math.floor(video.currentTime);

    fetch(URL_GUARDADO, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "id=" + encodeURIComponent(movieId) +
        "&titulo=" + encodeURIComponent(titulo) +
        "&imginicio=" + encodeURIComponent(imginicio) +
        "&progreso=" + encodeURIComponent(progreso)
    })
    .then(r => r.text())
    .then(res => console.log("Guardado:", res))
    .catch(err => console.log("Error fetch:", err));
  }

  // 🔥 guardar cada 10 segundos
  setInterval(() => {
    if (!video.paused && !video.ended) {
      guardarProgreso();
    }
  }, 10000);

});
</script>


<!-- FIN -->

 <!-- ==========================
    ESTILO DE CARGA DE VIDEO
    ===========================
-->

<style>
 /* ==========================
   LOADER VIDEO RESPONSIVE
========================== */

#player-loader{
  position:absolute;

  top:0;
  left:0;

  width:100%;
  height:100%;

  display:flex;
  align-items:center;
  justify-content:center;

  background:rgba(0,0,0,.45);

  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);

  z-index:20;

  opacity:0;
  visibility:hidden;
  transition:opacity .25s ease;

  overflow:hidden;
}

/* ACTIVO */
#player-loader.active{
  opacity:1;
  visibility:visible;
}

/* 🔥 IMPORTANTE */
.mobile-player{
  position:relative;
}

/* CONTENIDO */
.player-loader-content{
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;

  padding:12px;
  text-align:center;

  width:100%;
}

/* SPINNER */
/* =========================
   SPINNER LIMPIO
========================= */

.player-spinner{

  width:48px;
  height:48px;

  border-radius:50%;

  background:
    conic-gradient(
      #ff007f,
      #ffae00,
      #00ffcc,
      #00aaff,
      #9d00ff,
      #ff007f
    );

  animation:playerSpin 1s linear infinite;

  margin-bottom:12px;

  /* 🔥 AGUJERO TRANSPARENTE */
  -webkit-mask:
    radial-gradient(
      farthest-side,
      transparent calc(100% - 5px),
      #000 calc(100% - 4px)
    );

  mask:
    radial-gradient(
      farthest-side,
      transparent calc(100% - 5px),
      #000 calc(100% - 4px)
    );

  filter:drop-shadow(0 0 10px rgba(255,0,120,.35));
}

/* ROTACIÓN */
@keyframes playerSpin{
  to{
    transform:rotate(360deg);
  }
}

.player-spinner::before{
  content:"";
  position:absolute;
  inset:5px;
  border-radius:50%;
  background:#111;
}

@keyframes playerSpin{
  to{
    transform:rotate(360deg);
  }
}

/* TEXTO */
.player-loading-text{
  font-size:.9rem;
  font-weight:600;
  color:#fff;
}

/* 📱 MOBILE */
@media (max-width:768px){

  #player-loader{
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
  }

  .player-spinner{
    width:40px;
    height:40px;
  }

  .player-loading-text{
    font-size:.75rem;
  }

}

/* 📱 MÓVILES MUY PEQUEÑOS */
@media (max-width:480px){

  .player-spinner{
    width:34px;
    height:34px;
  }

  .player-loading-text{
    font-size:.70rem;
  }

}

#player-loader.active {
  opacity: 1;
  pointer-events: all;
}

/* Centro del spinner */
.player-spinner::before {
  content: "";
  position: absolute;
  inset: 5px;
  background: #111;
  border-radius: 50%;
}


/* Animación de puntos */
.dots::after {
  content: "";
  animation: dots 1.4s infinite steps(3);
}

@keyframes dots {
  0% { content: ""; }
  33% { content: "."; }
  66% { content: ".."; }
  100% { content: "..."; }
}

/* 📱 Responsive */
@media (max-width: 600px) {
  .player-spinner {
    width: 40px;
    height: 40px;
  }

  .player-loading-text {
    font-size: 0.75rem;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const video = document.getElementById('videoPlayer');
  const loader = document.getElementById('player-loader');

  if (!video || !loader) return;

  let loadingTimeout;

  const showLoader = () => {
    clearTimeout(loadingTimeout);

    // ⏱ pequeño delay para evitar parpadeo
    loadingTimeout = setTimeout(() => {
      loader.classList.add('active');
    }, 180);
  };

  const hideLoader = () => {
    clearTimeout(loadingTimeout);
    loader.classList.remove('active');
  };

  // 🔄 Estados reales de carga
  video.addEventListener('loadstart', showLoader);
  video.addEventListener('waiting', showLoader);
  video.addEventListener('stalled', showLoader);
  video.addEventListener('seeking', showLoader);

  // ▶️ Cuando ya puede reproducir
  video.addEventListener('canplay', hideLoader);
  video.addEventListener('playing', hideLoader);

  // ⏸️ No mostrar loader
  video.addEventListener('pause', hideLoader);
  video.addEventListener('ended', hideLoader);

  // 🧠 Extra: si buffer se recupera solo
  video.addEventListener('timeupdate', () => {
    if (!video.paused && !video.seeking && video.readyState >= 3) {
      hideLoader();
    }
  });
});
</script>

<!-- ==========================
    FIN ESTILO DE CARGA DE VIDEO
    ===========================
-->

<div class="mobile-player" id="mobilePlayer">
  <!-- 🔄 Loader del reproductor -->
  <div id="player-loader">
  <div class="player-loader-content">
    <div class="player-spinner"></div>
    <div class="player-loading-text">
      Cargando<span class="dots"></span>
    </div>
  </div>
</div>
  <video
    id="videoPlayer"
    playsinline
    webkit-playsinline
    preload="metadata"
    poster="https://image.tmdb.org/t/p/w780/"
  >
  </video>

  <div class="player-overlay" id="playerOverlay">

    <div class="overlay-center">
      <button id="btnRewind" class="seek-btn">10</button>
      <button id="btnPlay" class="play-btn">▶</button>
      <button id="btnForward" class="seek-btn">10</button>
    </div>

    <div class="overlay-bottom">

  <input type="range" id="progressBar" value="0">

  <div class="overlay-bottom-controls">

    <div class="time">
      <span id="currentTime">0:00</span>

      <span class="time-separator">/</span>

      <span id="duration">0:00</span>
    </div>

    <button id="btnFullscreen" class="fullscreen-btn">
      ⛶
    </button>

  </div>

</div>
  </div>
</div>

  <div class="info">
    <i class="fal fa-calendar-alt"></i><span>0000</span> <!--Aca va el año de estreno de pelicula-->
    <i class="fal fa-clock"></i><span>0h 00min</span> <!--aca va cuanto dura-->
    <i class="fal fa-thumbs-up"></i><span>00%</span> <!--aca va la calificacion-->
    <div class="hd-tag"></div>

  </div>
  
  <div class="info-pelicula">
    <h1><!--Nombre de pelicula--></h1>
    <span class="genero-badge">
  <span id="genero-texto"></span>
</span>
  
    <p class="sinopsis">
      <!--Sinopsis-->
    </p>

    <div class="ficha-tecnica" style="text-align:center;margin-top:20px;font-size:0.9rem;color:#ccc;">
      <p><strong>Director:</strong> </p>
      <p><strong>Reparto:</strong> </p>
      <p><strong>Estreno:</strong>  | <strong>Idioma:</strong> </p>
    </div>

    <br>

    <div class="acciones">

  <button id="btn-favorito">

    <span class="fav-bg"></span>

    <span class="fav-icon">
      ❤
    </span>

    <span class="fav-text">
      Agregar a Favoritos
    </span>

    <span class="fav-glow"></span>

  </button>

</div>
  </div>

  <!--FAVORITOS-->
<style>

/* =========================================
🔥 RESET BOTÓN
========================================= */

#btn-favorito,
#btn-favorito *{
box-sizing:border-box;
}

/* =========================================
🔥 CONTENEDOR
========================================= */

.acciones{

width:100%;

display:flex;
align-items:center;
justify-content:center;

padding:
20px 16px;
}

/* =========================================
💖 FAVORITOS PREMIUM
========================================= */

#btn-favorito{

position:relative;

display:flex;
align-items:center;
justify-content:center;
gap:12px;

width:auto;
max-width:100%;

border:none;
outline:none;
cursor:pointer;

overflow:hidden;
isolation:isolate;

padding:
16px 30px;

border-radius:999px;

background:
linear-gradient(
135deg,
#ff0055 0%,
#ff2d75 35%,
#7b2dff 100%
);

color:#fff;

font-family:
'Inter',
sans-serif;

font-size:1rem;
font-weight:800;

letter-spacing:.3px;
line-height:1;

white-space:nowrap;

transition:
transform .25s ease,
box-shadow .35s ease,
filter .35s ease,
background .35s ease;

box-shadow:
0 10px 30px rgba(255,0,102,.30),
0 0 25px rgba(123,45,255,.18);

backdrop-filter:
blur(10px);

-webkit-backdrop-filter:
blur(10px);
}

/* =========================================
✨ CAPA INTERNA
========================================= */

.fav-bg{

position:absolute;
inset:1px;

border-radius:999px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.08),
rgba(255,255,255,.02)
);

z-index:-1;
}

/* =========================================
💡 GLOW
========================================= */

.fav-glow{

position:absolute;

top:50%;
left:-35%;

width:130px;
height:130px;

transform:
translateY(-50%);

border-radius:50%;

background:
radial-gradient(
circle,
rgba(255,255,255,.35),
transparent 70%
);

opacity:.7;

filter:blur(10px);

animation:
glowMove 4s linear infinite;
}

@keyframes glowMove{

0%{
left:-40%;
opacity:0;
}

15%{
opacity:.8;
}

100%{
left:130%;
opacity:0;
}
}

/* =========================================
❤️ ICONO
========================================= */

.fav-icon{

position:relative;
z-index:2;

display:flex;
align-items:center;
justify-content:center;

font-size:1.15rem;
line-height:1;

transition:
transform .25s ease;
}

/* =========================================
📝 TEXTO
========================================= */

.fav-text{

position:relative;
z-index:2;

display:block;

white-space:nowrap;
}

/* =========================================
✨ SHINE
========================================= */

#btn-favorito::before{

content:"";

position:absolute;
top:0;
left:-120%;

width:60%;
height:100%;

background:
linear-gradient(
90deg,
transparent,
rgba(255,255,255,.28),
transparent
);

transform:skewX(-25deg);

animation:
shine 3.5s linear infinite;
}

@keyframes shine{

100%{
left:150%;
}
}

/* =========================================
🔥 HOVER
========================================= */

#btn-favorito:hover{

transform:
translateY(-4px)
scale(1.02);

box-shadow:
0 16px 40px rgba(255,0,102,.42),
0 0 30px rgba(123,45,255,.28);

filter:
brightness(1.05);
}

#btn-favorito:hover .fav-icon{

transform:
scale(1.18)
rotate(-8deg);
}

/* =========================================
⚡ ACTIVE
========================================= */

#btn-favorito:active{

transform:
scale(.96);
}

/* =========================================
📱 MOBILE PEQUEÑOS
========================================= */

@media screen and (max-width:360px){

.acciones{
padding:
16px 12px;
}

#btn-favorito{

width:100%;

min-height:50px;

padding:
14px 16px;

gap:8px;

font-size:.78rem;

border-radius:18px;

box-shadow:
0 8px 22px rgba(255,0,102,.26);
}

.fav-icon{
font-size:.90rem;
}

.fav-text{

max-width:100%;

overflow:hidden;
text-overflow:ellipsis;
}

.fav-glow{
width:75px;
height:75px;
}

#btn-favorito::before{
animation-duration:4.5s;
}
}

/* =========================================
📱 MOBILE GENERAL
========================================= */

@media screen
and (min-width:361px)
and (max-width:600px){

.acciones{
padding:
18px 14px;
}

#btn-favorito{

width:100%;

min-height:54px;

padding:
15px 20px;

gap:10px;

font-size:.90rem;

border-radius:20px;
}

.fav-icon{
font-size:1rem;
}

.fav-text{
font-weight:800;
}

.fav-glow{
width:90px;
height:90px;
}

#btn-favorito:hover{

transform:
translateY(-2px)
scale(1.01);
}
}

/* =========================================
🍎 IPHONE
========================================= */

@media screen
and (min-width:390px)
and (max-width:430px){

.acciones{
padding:
20px 16px;
}

#btn-favorito{

min-height:58px;

padding:
16px 24px;

gap:12px;

font-size:.97rem;

border-radius:22px;
}

.fav-icon{
font-size:1.08rem;
}

.fav-glow{
width:100px;
height:100px;
}

.fav-text{
letter-spacing:.2px;
}
}

/* =========================================
📱 TABLET
========================================= */

@media screen
and (min-width:768px)
and (max-width:1023px){

#btn-favorito{

padding:
17px 28px;

font-size:1rem;

gap:12px;

border-radius:999px;
}

.fav-icon{
font-size:1.15rem;
}

.fav-glow{
width:120px;
height:120px;
}
}

/* =========================================
💻 PC
========================================= */

@media screen
and (min-width:1024px){

.acciones{
padding:
24px 20px;
}

#btn-favorito{

min-height:62px;

padding:
18px 34px;

gap:14px;

font-size:1.05rem;

border-radius:999px;

box-shadow:
0 12px 34px rgba(255,0,102,.32),
0 0 28px rgba(123,45,255,.20);
}

.fav-icon{
font-size:1.22rem;
}

.fav-text{
font-weight:800;
}

.fav-glow{
width:140px;
height:140px;
}

#btn-favorito:hover{

transform:
translateY(-5px)
scale(1.03);
}
}

/* =========================================
🖥 PC GRANDES
========================================= */

@media screen
and (min-width:1440px){

.acciones{
padding:
28px 24px;
}

#btn-favorito{

min-height:68px;

padding:
20px 42px;

gap:16px;

font-size:1.12rem;
}

.fav-icon{
font-size:1.35rem;
}

.fav-glow{
width:170px;
height:170px;
}

#btn-favorito:hover{

transform:
translateY(-6px)
scale(1.04);
}
}

</style>

  <script>

// ENVIAR PING CADA 30 SEGUNDOS
setInterval(() => {

    fetch("ping.php")
    .catch(() => {});

}, 30000);

</script>

  <script>
    const MOVIES_DB = {
	
	trailer_spider_man_un_nuevo_dia: {
	id: "trailer_spider_man_un_nuevo_dia",
    titulo: "Trailer Spider-Man: Un nuevo día",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/iav2xfNr3Rw1iti1caERDZ1S85a.jpg",
    imginicio: "https://image.tmdb.org/t/p/w780/",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "",
    anio: "",
    duracion: "0h 008min",
    calificacion: "00%",
    genero: "",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      }
    ]
  },

  
};

  </script>

  <script>
const PERFIL_ID = <?php echo isset($_SESSION['perfil_id']) ? (int)$_SESSION['perfil_id'] : 0; ?>;
</script>

<script>
    const params = new URLSearchParams(window.location.search);
const movieId = params.get("id");

const movie = MOVIES_DB[movieId];
function cargarDatosPelicula() {

  const video = document.getElementById("videoPlayer");

  // Texto
  document.querySelector(".info-pelicula h1").textContent = movie.titulo;

  document.querySelector(".sinopsis").textContent = movie.sinopsis;
  document.getElementById("genero-texto").textContent = movie.genero;

  // Ficha técnica
  document.querySelector(".ficha-tecnica").innerHTML = `
    <p><strong>Director:</strong> ${movie.director}</p>
    <p><strong>Reparto:</strong> ${movie.reparto}</p>
    <p><strong>Estreno:</strong> ${movie.estreno} | <strong>Idioma:</strong> ${movie.idioma}</p>
  `;

  // Info superior
  document.querySelector(".info span:nth-child(2)").textContent = movie.anio;
  document.querySelector(".info span:nth-child(4)").textContent = movie.duracion;
  document.querySelector(".info span:nth-child(6)").textContent = movie.calificacion;
  const hdTag = document.querySelector(".hd-tag");
hdTag.innerHTML = ""; // limpiar etiquetas viejas

  // 🔹 Calidad
if (movie.calidad) {
  hdTag.innerHTML += `
    <div style="display:flex;align-items:center;gap:6px;">
      <!-- Ícono SVG -->
      <svg width="22" height="22" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <rect width="24" height="24" rx="4" fill="white" />
        <text x="4" y="17" fill="black" font-size="12" font-family="Arial, sans-serif" font-style="italic" font-weight="bold">HD</text>
      </svg>

      <span>${movie.calidad}</span>
    </div>
  `;
}

// 🔹 CAM
if (movie.cam) {
  hdTag.innerHTML += `
    <div style="display:flex;align-items:center;gap:6px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
        <path d="M20 5H16.83L15 3H9L7.17 5H4C2.9 5 2 5.9 2 7V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V7C22 5.9 21.1 5 20 5ZM12 17C9.79 17 8 15.21 8 13C8 10.79 9.79 9 12 9C14.21 9 16 10.79 16 13C16 15.21 14.21 17 12 17Z"/>
      </svg>
      <span>CAM</span>
    </div>
  `;
}

// 🔹 Adulto +18
if (movie.adulto) {
  hdTag.innerHTML += `<span class="age-tag">+18</span>`;
}

  // Poster
  video.poster = movie.poster;
}

if (!movie) {
  alert("Película no encontrada");
  window.location.href = "../index.html";
}

document.addEventListener("DOMContentLoaded", () => {

  /* ================= CONFIG ================= */
  const MOVIE_KEY = movie.id;
const STORAGE_KEY = `resume_${MOVIE_KEY}`;
const VIDEO_SRC = movie.video;


  /* ================= ELEMENTOS ================= */
  const player = document.getElementById("mobilePlayer");
  const video = document.getElementById("videoPlayer");
  const overlay = document.getElementById("playerOverlay");
  const btnPlay = document.getElementById("btnPlay");
  const btnRewind = document.getElementById("btnRewind");
const btnForward = document.getElementById("btnForward");
const btnFullscreen = document.getElementById("btnFullscreen");


  const progress = document.getElementById("progressBar");
  const currentTimeEl = document.getElementById("currentTime");
  const durationEl = document.getElementById("duration");

  let isSeeking = false;
  let wasPlayingBeforeSeek = false;
  let resumeApplied = false;
  let videoLoaded = false;
  let overlayTimeout = null;

  

function enterFullscreen() {
  try {
    const isFake = player.classList.contains("fake-fullscreen");

    // iOS → fullscreen falso
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
      if (isFake) {
        player.classList.remove("fake-fullscreen");
        hidePageInfo(false);
      } else {
        player.classList.add("fake-fullscreen");
        hidePageInfo(true);
      }
      return;
    }

    // Android / Desktop
    if (!document.fullscreenElement) {
      player.requestFullscreen();
    } else {
      document.exitFullscreen();
    }

  } catch (e) {
    console.warn("Fullscreen no disponible", e);
  }
}


btnFullscreen.onclick = (e) => {
  e.stopPropagation(); // evita que se oculte el overlay
  enterFullscreen();
};



function hidePageInfo(hide) {
  document.querySelector(".info")?.classList.toggle("hide", hide);
  document.querySelector(".info-pelicula")?.classList.toggle("hide", hide);
}

document.addEventListener("fullscreenchange", () => {
  hidePageInfo(!!document.fullscreenElement);
});



  /* ================= UTIL ================= */
  function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return "0:00";

  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = Math.floor(seconds % 60);

  if (h > 0) {
    return `${h}:${m.toString().padStart(2, "0")}:${s
      .toString()
      .padStart(2, "0")}`;
  }

  return `${m}:${s.toString().padStart(2, "0")}`;
}

function updateProgressUI(percent) {
  progress.value = percent;
  updateRainbowProgress(); // 🔥 NUEVO
}


function updateRainbowProgress() {
  const value = progress.value;
  progress.style.setProperty("--progress", value + "%");
}

  function hideOverlayAuto() {
    clearTimeout(overlayTimeout);
    overlayTimeout = setTimeout(() => {
      overlay.classList.add("hide");
    }, 3000);
  }
  function showOverlay() {
  overlay.classList.remove("hide");
  hideOverlayAuto();
}

  /* ================= PLAY ================= */
  btnPlay.onclick = () => {

    // 🔥 CARGA REAL DEL VIDEO SOLO AL PRESIONAR PLAY
    if (!videoLoaded) {
      video.src = VIDEO_SRC;
      video.load();
      videoLoaded = true;
    }

    if (video.paused) {
      video.play();
      btnPlay.textContent = "❚❚";
      overlay.classList.add("hide");
    } else {
      video.pause();
      btnPlay.textContent = "▶";
      overlay.classList.remove("hide");
    }
  };

  /* ================= SEEK ================= */
btnRewind.onclick = (e) => {
  e.stopPropagation();
  if (!videoLoaded || !video.duration) return;

  showOverlay(); // 👈 MOSTRAR CONTROLES
  video.currentTime = Math.max(0, video.currentTime - 10);
};

btnForward.onclick = (e) => {
  e.stopPropagation();
  if (!videoLoaded || !video.duration) return;

  showOverlay(); // 👈 MOSTRAR CONTROLES
  video.currentTime = Math.min(video.duration, video.currentTime + 10);
};


  /* ================= REANUDAR (CORRECTO) ================= */
function getProgressEndpointsPeli() {
  const isPerfil = (typeof PERFIL_ID !== "undefined" && PERFIL_ID > 0);

  return {
    guardar: isPerfil
      ? "perfil_guardar_progreso_peli.php"
      : "guardar_progreso_peli.php",

    obtener: isPerfil
      ? "perfil_obtener_progreso_peli.php"
      : "obtener_progreso_peli.php"
  };
}


/* =========================
   ▶️ CARGAR PROGRESO
========================= */

video.addEventListener("loadedmetadata", async () => {

  if (resumeApplied) return;

  try {

    const { obtener } = getProgressEndpointsPeli();

    const res = await fetch(`${obtener}?movie_id=${movie.id}`);
    const data = await res.json();

    if (data.status === "ok" && data.data && data.data.tiempo) {

      const tiempo = parseFloat(data.data.tiempo);

      // 🔥 evitar inicio o final
      if (tiempo > 5 && tiempo < video.duration - 5) {
        video.currentTime = tiempo;
      }

    }

  } catch (e) {
    console.warn("Error obteniendo progreso", e);
  }

  resumeApplied = true;
});


/* =========================
   💾 GUARDAR PROGRESO
========================= */

async function guardarProgresoBackend(time) {

  try {

    const { guardar } = getProgressEndpointsPeli();

    await fetch(guardar, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        movie_id: movie.id,
        tiempo: time
      })
    });

  } catch (e) {
    console.warn("Error guardando progreso", e);
  }
}

  /* ================= PROGRESO (SIN PAUSA) ================= */
/* ================= PROGRESO + GUARDADO ================= */
let lastSavedTime = 0;


video.addEventListener("timeupdate", () => {
  if (!video.duration || isSeeking) return;

  const percent = (video.currentTime / video.duration) * 100;
  updateProgressUI(percent);

  currentTimeEl.textContent = formatTime(video.currentTime);
  durationEl.textContent = formatTime(video.duration);

  if (!video.paused && !video.ended) {

    if (Math.abs(video.currentTime - lastSavedTime) >= 5) {

      guardarProgresoBackend(video.currentTime); // 🔥 BACKEND
      lastSavedTime = video.currentTime;

    }
  }
});

// empieza a arrastrar
progress.addEventListener("mousedown", () => {
  isSeeking = true;
  wasPlayingBeforeSeek = !video.paused;
});

progress.addEventListener("touchstart", () => {
  isSeeking = true;
  wasPlayingBeforeSeek = !video.paused;
});

// mientras arrastra (solo UI, NO corta el video)
progress.addEventListener("input", () => {
  if (!video.duration) return;

  showOverlay(); // 👈 IMPORTANTE (no se oculta)

  const percent = progress.value;
  const previewTime = (percent / 100) * video.duration;

  updateRainbowProgress(); // 🔥 clave

  currentTimeEl.textContent = formatTime(previewTime);
});

// suelta → recién ahí mover el video
progress.addEventListener("mouseup", applySeek);
progress.addEventListener("touchend", applySeek);

function applySeek() {
  if (!video.duration) return;

  const newTime = (progress.value / 100) * video.duration;
  video.currentTime = newTime;

  isSeeking = false;

  if (wasPlayingBeforeSeek) {
    video.play(); // 🔥 continúa sin corte
  }
}

  /* ================= FINAL ================= */
  video.addEventListener("ended", async () => {

  try {
    await fetch("eliminar_historial.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "movie_id=" + encodeURIComponent(movie.id)
    });
  } catch (e) {
    console.warn("Error limpiando progreso", e);
  }

  video.pause();
  video.currentTime = 0;
  video.removeAttribute("src");
  video.load();

  videoLoaded = false;
  btnPlay.textContent = "▶";
  overlay.classList.remove("hide");

});

  /* ================= TOQUE EN PANTALLA ================= */
  player.addEventListener("click", (e) => {
  if (
    e.target.tagName === "BUTTON" ||
    e.target.type === "range" // 🔥 clave
  ) return;

  overlay.classList.toggle("hide");
  hideOverlayAuto();
});


});

function cargarRecomendaciones() {

  const grid = document.querySelector(".series-grid");
  if (!movie.recomendaciones || !grid) return;

  grid.innerHTML = "";

  movie.recomendaciones.forEach(rec => {

    const a = document.createElement("a");
    a.className = "serie";

    // 🔹 CASO 1: abrir archivo externo
    if (rec.href) {
      a.href = `../${rec.href}`;
    }

    // 🔹 CASO 2: usar reproductor universal
    else if (rec.id) {

      if (rec.adulto) {
        a.setAttribute("data-href", `Reproductor Universal.php?id=${rec.id}`);
        a.setAttribute("data-adulto", "adulto");
        a.addEventListener("click", handleAdultLinkClick);
      } else {
        a.href = `Reproductor Universal.php?id=${rec.id}`;
      }
    }

    a.innerHTML = `
      <img loading="lazy" src="${rec.imagen}">
      <p>${rec.titulo}</p>
    `;

    grid.appendChild(a);
  });
}

</script>

  <br>

  <!-- 🔔 MODAL UNIFICADO DE FAVORITOS (para ambos mensajes) -->
<div id="modal-favoritos" class="modal-fav" aria-hidden="true">
  <div class="modal-fav-backdrop"></div>
  <div class="modal-fav-box">
    <h3 id="modal-fav-texto">Mensaje aquí</h3>
    <button id="modal-fav-aceptar">Aceptar</button>
  </div>
</div>

<style>
  .modal-fav {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
  }
  .modal-fav[aria-hidden="false"] {
    display: flex;
    animation: fadeIn 0.4s ease forwards;
  }
  .modal-fav-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(6px);
  }
  .modal-fav-box {
    position: relative;
    background: linear-gradient(145deg, #0d0d0d, #1a1a1a);
    border: 2px solid #ff2d5b;
    border-radius: 14px;
    padding: 25px 30px;
    text-align: center;
    color: #fff;
    box-shadow: 0 0 25px rgba(255,45,91,0.5);
    max-width: 90%;
    width: 380px;
    z-index: 10;
    animation: scaleUp 0.3s ease forwards;
  }
  .modal-fav-box h3 {
    margin-bottom: 20px;
    font-size: 1.1rem;
    color: #fff;
  }
  #modal-fav-aceptar {
    background: linear-gradient(135deg, #ff2d5b, #ff5e7e);
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 10px 25px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.3s ease;
    animation: bounceIn 0.6s ease;
  }
  #modal-fav-aceptar:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 0 15px rgba(255,45,91,0.8);
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  @keyframes scaleUp {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
  @keyframes bounceIn {
    0% { transform: scale(0.6); opacity: 0; }
    60% { transform: scale(1.1); opacity: 1; }
    100% { transform: scale(1); }
  }
</style>

<script>

// 🔹 Función general para mostrar el modal
function mostrarModalFavoritos(mensaje) {
  const modal = document.getElementById('modal-favoritos');
  const texto = document.getElementById('modal-fav-texto');
  const btnAceptar = document.getElementById('modal-fav-aceptar');

  texto.textContent = mensaje;
  modal.setAttribute('aria-hidden', 'false');

  let cerrarTimeout = setTimeout(() => cerrarModal(), 15000);

  function cerrarModal() {
    modal.setAttribute('aria-hidden', 'true');
    clearTimeout(cerrarTimeout);
  }

  btnAceptar.onclick = cerrarModal;
  modal.querySelector('.modal-fav-backdrop').onclick = cerrarModal;
}


// 🔹 Botón "Agregar a Favoritos"
document.getElementById('btn-favorito').addEventListener('click', () => {

  const movieId = movie.id.toLowerCase();
  const titulo = movie.titulo || movie.id.replace(/_/g," ");
  const imagen = movie.imagen || "";
  const tipo = movie.tipo || "trailer";

  /* =========================
     🔥 DETECTAR PERFIL
  ========================= */
  const esPerfil = <?php echo isset($_SESSION['perfil_name']) ? 'true' : 'false'; ?>;

  /* =========================
     📡 ENDPOINT DINÁMICO
  ========================= */
  const url = esPerfil
    ? "perfil_guardar_favorito.php"
    : "guardar_favorito.php";

  console.log("👤 Modo:", esPerfil ? "PERFIL" : "USUARIO");
  console.log("📡 Endpoint:", url);

  fetch(url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "movie_id=" + encodeURIComponent(movieId) +
      "&titulo=" + encodeURIComponent(titulo) +
      "&imagen=" + encodeURIComponent(imagen) +
      "&tipo=" + encodeURIComponent(tipo)
  })

  .then(res => res.json())

  .then(data => {

    console.log("⭐ Favorito:", data);

    if(data.status === "success"){
      mostrarModalFavoritos("Trailer agregada a favoritos");

      setTimeout(() => {
        window.location.href = "favoritos.php";
      }, 1500);
    }

    else if(data.status === "exists"){
      mostrarModalFavoritos("Esta trailer ya está en favoritos");
    }

    else if(data.status === "error"){
      mostrarModalFavoritos("Debes iniciar sesión");
    }

  })

  .catch(() => {
    mostrarModalFavoritos("Error al guardar favorito");
  });


  const btn = document.getElementById('btn-favorito');
  btn.classList.add('animado');
  setTimeout(() => btn.classList.remove('animado'), 300);

});

</script>


<script>
  function mostrarModalFavoritoExistente() {
    const modal = document.getElementById('modal-ya-favorito');
    const btnAceptar = document.getElementById('btn-fav-aceptar');
    modal.setAttribute('aria-hidden', 'false');

    let cerrarTimeout = setTimeout(() => cerrarModal(), 15000); // auto cierre 15s

    function cerrarModal() {
      modal.setAttribute('aria-hidden', 'true');
      clearTimeout(cerrarTimeout);
    }

    btnAceptar.onclick = cerrarModal;
    modal.querySelector('.modal-fav-backdrop').onclick = cerrarModal;
  }
</script>

  <style>
    .titulo-flex {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .genero-badge {
  position: relative;
  display: inline-block;
  padding: 6px 14px;
  border-radius: 12px;
  margin: 10px;
  overflow: hidden;

  color: white;
  font-weight: 600;

  z-index: 1;
}

.genero-badge::before {
  content: "";
  position: absolute;
  inset: -2px;
  border-radius: 12px;

  background: conic-gradient(
    red,
    orange,
    yellow,
    lime,
    cyan,
    blue,
    violet,
    red
  );

  animation: giroInfo 8s linear infinite;
  z-index: 0;
}
.genero-badge::after {
  content: "";
  position: absolute;
  inset: 2px;
  background: #000;
  border-radius: 10px;
  z-index: 0; /* 🔥 BAJAMOS */
}

.genero-badge span {
  position: relative;
  z-index: 1;
  color: white;

  font-size: 12px;      /* 🔥 más chico */
  letter-spacing: 0.5px; /* 🔥 más fino */
}


.genero-badge {
  position: relative;
  z-index: 2;
}


    .titulo {
      font-size: 0.7rem;
      font-style: normal;
      max-width: 200px;          /* Limita el ancho máximo */
      white-space: nowrap;       /* No saltar de línea */
      overflow: hidden;          /* Oculta el exceso */
      text-overflow: ellipsis;   /* Agrega los ... */
    }

  </style>

  <style>
    .titulo-flex {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      gap: 10px;
      flex-wrap: wrap;
    }
    
  </style>

  <div class="recomendaciones">
    <h4><span>Podría interesarte:</span></h4>
    <br><br>
    <div class="series-grid">
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>Nombre de pelicula</p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>Nombre de pelicula</p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>Nombre de pelicula</p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>Nombre de pelicula</p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>Nombre de pelicula</p>
      </a>
      <a class="serie" data-href=".html" data-adulto="adulto"> <!--data-href=".html" data-adulto="adulto" si es para adulto debes conservar estas etiquetas-->
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p>SI PARA MAYORES DE EDAD UTILICE ESTE</p>
      </a>
    </div>
  </div>

  <script>
function esperarFinLoader(callback) {
  const loader = document.getElementById("loader-screen");

  const check = setInterval(() => {
    if (loader.classList.contains("hidden")) {
      clearInterval(check);
      callback();
    }
  }, 100);
}
esperarFinLoader(() => {
  cargarDatosPelicula();
  cargarRecomendaciones();
});

</script>


<script>
function esperarFinLoader(callback) {
  const loader = document.getElementById("loader-screen");

  const check = setInterval(() => {
    if (loader.classList.contains("hidden")) {
      clearInterval(check);
      callback();
    }
  }, 100);
}

esperarFinLoader(() => {
  cargarDatosPelicula();
  cargarRecomendaciones();
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {

  const videoElement = document.getElementById('videoPlayer');
  if (!videoElement) return;

  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");

  if (!movieId) {
    console.log("❌ ID no encontrado");
    return;
  }

  const movie = (typeof MOVIES_DB !== "undefined") ? MOVIES_DB[movieId] : null;

  if (!movie) {
    console.log("❌ Movie no encontrada");
    return;
  }

  /* =========================
     DATOS LIMPIOS
  ========================= */
  const titulo = movie.titulo ? movie.titulo.trim() : "";
  const tipo   = movie.type ? movie.type.trim() : "trailer";
  const imagen = movie.imagen || "";
  const archivo = "Reproductor Universal.php?id=" + movieId;

  /* =========================
     🔥 DETECTAR PERFIL REAL
  ========================= */
  const esPerfil = <?php echo isset($_SESSION['perfil_name']) ? 'true' : 'false'; ?>;

  /* =========================
     📡 ENDPOINT DINÁMICO
  ========================= */
  const url = esPerfil
    ? "perfil_guardar_historial.php"
    : "guardar_historial.php";

  console.log("👤 Modo:", esPerfil ? "PERFIL" : "USUARIO");
  console.log("📡 Endpoint:", url);

  /* =========================
     🔒 EVITAR DUPLICADOS FRONT
  ========================= */
  let yaGuardado = false;

  function guardarHistorial() {

    if (yaGuardado) return;
    yaGuardado = true;

    fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "movie_id=" + encodeURIComponent(movieId) +
        "&titulo=" + encodeURIComponent(titulo) +
        "&tipo=" + encodeURIComponent(tipo) +
        "&imagen=" + encodeURIComponent(imagen) +
        "&archivo=" + encodeURIComponent(archivo)
    })
    .then(res => res.json())
    .then(data => {

      console.log("📺 Historial:", data);

      if (!data || data.status === "error") {
        console.log("❌ Error backend:", data);
        return;
      }

      // 🔥 Ya no se muestra ningún cartel

    })
    .catch(err => {
      console.log("❌ Error historial:", err);
    });
  }

  /* =========================
     🚀 EJECUTAR SOLO UNA VEZ
  ========================= */
  guardarHistorial();

});
</script>



  <!-- ¡¡¡NO BORRAR ESTE SCRIPT ES DE FAVORITOS Y SI LO BORRAN AFECTARAN EL FUNCIONAMIENTO DE REPRODUCTOR Y FAVORITOS!!! -->

  <!-- Modal flotante de edad + clave -->
<div id="ageModal" class="age-modal hidden">
  <div class="age-modal-content">
    <span class="close-button" onclick="closeModal()">×</span>

    <h2>Verificación de Edad</h2>

    <label for="birthyear">Año de nacimiento:</label>
<input type="number" id="birthyear">

<label for="age">Edad:</label>
<input type="number" id="age">

<label for="claveInput">Clave:</label>
<input type="password" id="claveInput">

<button id="resetClaveBtn" style="background:#444;margin-top:10px;">
      Olvidé mi clave
    </button>

<button id="confirmAgeBtn">Validar</button>
<p id="result-message"></p>

  </div>
</div>

<!-- MODAL RESET CLAVE -->
<div id="resetModal" class="modal">
  <div class="modal-content">
    <h2>Restablecer clave</h2>
    <p>¿Deseás borrar tu clave y crear una nueva?</p>
    <div class="modal-buttons">
      <button id="cancelReset">Cancelar</button>
      <button id="confirmReset">Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL ALERTA -->
<div id="alertModal" class="modal">
  <div class="modal-content">
    <p id="alertTexto"></p>
    <br>
    <button id="closeAlert">Aceptar</button>
  </div>
</div>

<style>

/*MODAL DE VLAVE*/

.modal {
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  justify-content:center;
  align-items:center;
  z-index:9999;
}

.modal-content {
  background:#121212;
  color:#fff;
  padding:25px;
  border-radius:12px;
  width:90%;
  max-width:350px;
  text-align:center;
  box-shadow:0 0 15px rgba(0,0,0,.5);
}

.modal-buttons {
  margin-top:15px;
  display:flex;
  justify-content:space-between;
}

.modal-buttons button,
#closeAlert {
  padding:8px 14px;
  border:none;
  border-radius:6px;
  cursor:pointer;
  background:#333;
  color:#fff;
}

#confirmReset {
  background:#d63030;
}

.modal-buttons button:hover,
#closeAlert:hover {
  opacity:.85;
}


/*FIN*/

  .age-modal {
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(6px);
    display: flex;
    justify-content: center;
    align-items: center;
    animation: fadeInBg 0.4s ease;
  }

  .age-modal-content {
    width: 320px;
    background: #141414;
    padding: 25px;
    border-radius: 14px;
    text-align: center;
    box-shadow: 0 0 25px rgba(255,0,0,0.25);
    color: white;
    position: relative;
    animation: popup 0.35s ease;
  }

  @keyframes popup {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
  @keyframes fadeInBg {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .age-modal-content h2 {
    margin-bottom: 15px;
    font-size: 22px;
    color: #ff3c3c;
  }

  .age-modal-content label {
    text-align: left;
    display: block;
    margin: 10px 0 5px;
    font-size: 14px;
    opacity: 0.9;
  }

  .age-modal-content input {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    background: #1f1f1f;
    border: 1px solid #333;
    color: white;
    outline: none;
    font-size: 15px;
    transition: 0.2s;
  }

  .age-modal-content input:focus {
    border-color: #ff3c3c;
    box-shadow: 0 0 5px rgba(255,60,60,0.6);
  }

  .age-modal-content button {
    width: 100%;
    margin-top: 15px;
    padding: 12px;
    background: #ff3c3c;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    transition: 0.2s ease;
  }

  .age-modal-content button:hover {
    background: #ff5555;
    transform: scale(1.03);
  }

  .close-button {
    position: absolute;
    right: 14px;
    top: 10px;
    font-size: 22px;
    cursor: pointer;
    color: #bbb;
  }

  .close-button:hover {
    color: white;
  }

  #result-message {
    margin-top: 12px;
    font-size: 14px;
    min-height: 20px;
  }

  .hidden {
    display: none;
  }
</style>
  
  <script>
let pendingRedirect = null;
let claveGuardada = localStorage.getItem("claveAdultos");

function handleAdultLinkClick(e){
  e.preventDefault();
  pendingRedirect = e.currentTarget.getAttribute("data-href");
  abrirModalEdad();
}

function abrirModalEdad(){
  ageModal.classList.remove("hidden");
  resultMessage.textContent = "";
  birthyear.value = "";
  age.value = "";
  claveInput.value = "";
}

function closeModal(){
  ageModal.classList.add("hidden");
  pendingRedirect = null;
}

const ageModal = document.getElementById("ageModal");
const birthyear = document.getElementById("birthyear");
const age = document.getElementById("age");
const claveInput = document.getElementById("claveInput");
const resultMessage = document.getElementById("result-message");

document.getElementById("confirmAgeBtn").addEventListener("click", () => {

  let birth = parseInt(birthyear.value);
  let edad = parseInt(age.value);
  let clave = claveInput.value;
  let actual = new Date().getFullYear();
  let calculada = actual - birth;

  if(!birth || !edad || !clave){
    resultMessage.textContent = "Completa todos los campos.";
    return;
  }

  if(edad !== calculada){
    resultMessage.textContent = "Edad no coincide.";
    return;
  }

  if(edad < 18){
    resultMessage.textContent = "Debes ser mayor de edad.";
    return;
  }

  // ✅ Crear clave si no existe
  if(!claveGuardada){
    localStorage.setItem("claveAdultos", clave);
    claveGuardada = clave;
    resultMessage.style.color="lime";
    resultMessage.textContent = "Clave creada. Acceso autorizado.";
    setTimeout(()=>location.href=pendingRedirect,1200);
    return;
  }

  // ✅ Validar clave existente
  if(clave !== claveGuardada){
    resultMessage.textContent="Clave incorrecta.";
    return;
  }

  // ✅ Acceso permitido
  resultMessage.style.color="lime";
  resultMessage.textContent="Acceso autorizado.";
  setTimeout(()=>location.href=pendingRedirect,1200);
});

document.querySelectorAll('[data-adulto="adulto"]').forEach(link=>{
  link.addEventListener("click", handleAdultLinkClick);
});
</script>

<script>
const resetModal = document.getElementById("resetModal");
const alertModal = document.getElementById("alertModal");
const alertTexto = document.getElementById("alertTexto");

document.getElementById("resetClaveBtn").addEventListener("click", () => {
  resetModal.style.display = "flex";
});

document.getElementById("cancelReset").addEventListener("click", () => {
  resetModal.style.display = "none";
});

document.getElementById("confirmReset").addEventListener("click", () => {
  localStorage.removeItem("claveAdultos");
  claveGuardada = null;

  resetModal.style.display = "none";
  showAlert("Clave eliminada. Ahora puedes crear una nueva.");
  abrirModalEdad();
});

document.getElementById("closeAlert").addEventListener("click", () => {
  alertModal.style.display = "none";
});

function showAlert(msg){
  alertTexto.textContent = msg;
  alertModal.style.display = "flex";
}
</script>

  <!--FIN DE LA VERIFICACION PARA ADULTOS.-->


  <script>
    function mostrarMensaje(texto) {
      const mensaje = document.getElementById('mensaje-confirmacion');
      mensaje.textContent = texto;
      mensaje.style.display = 'block';
      mensaje.style.opacity = '1';
      setTimeout(() => {
        mensaje.style.opacity = '0';
        setTimeout(() => mensaje.style.display = 'none', 500);
      }, 3000);
    }
  </script>

</body>
</html>