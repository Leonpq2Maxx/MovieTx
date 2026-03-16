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
  <link rel="icon" type="image/png" href="../Logo Poster MovieTx PNG/Logo MovieTx.png">
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
      border: 1px solid rgba(16, 235, 255, 0.461);
      border-radius: 10px;
      margin: 10px;
      font-size: 14px;
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
    .series-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 15px;
      text-align: center;
    }
    .serie {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: white;
    }

    .serie img {
      width: 120px;
      height: 180px;
      object-fit: cover;
      border-radius: 10px;
    }

    .serie p {
  margin-top: 6px;
  font-size: 14px;
  line-height: 1.4;          /* más aire */
  max-width: 120px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  white-space: normal;
  text-align: center;
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
    
    #btn-favorito.animado {
      animation: pop 0.3s ease;
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

    #btn-favorito {
      background: linear-gradient(135deg, #ff2d55, #ff5e7e);
      color: white;
      padding: 12px 22px;
      border: none;
      border-radius: 999px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.3s ease;
      box-shadow: 0 8px 20px rgba(255, 45, 91, 0.35);
    }

    #btn-favorito:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(255, 45, 91, 0.55);
    }


    #btn-favorito::after {
      content: ""; 
      position: absolute; 
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
      120deg,
      transparent,
      rgba(255, 255, 255, 0.25),
      transparent
      );
      transform: rotate(25deg);
      animation: shine 3s infinite;
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


    #btn-favorito {
      background: linear-gradient(135deg, #ff2d55, #ff5e7e);
      color: white;
      padding: 12px 22px;
      border: none;
      border-radius: 999px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: transform 0.2s ease, box-shadow 0.3s ease;
      box-shadow: 0 8px 20px rgba(255, 45, 91, 0.35);
    }


    #btn-favorito:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(255, 45, 91, 0.55);
    }


    #btn-favorito::after {
      content: "";
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(
      120deg,
      transparent,
      rgba(255, 255, 255, 0.25),
      transparent
      );
      transform: rotate(25deg);  
      animation: shine 3s infinite;
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
  <div class="loader-content">
    <div class="loader-circle">
      <img src="../Logo Poster MovieTx PNG/Logo MovieTx.png" alt="Logo MovieTx" class="loader-logo">
    </div>

    <h1 class="loader-title">MovieTx</h1>
    <p class="loader-sub">Cargando<span class="loading-dots"></span></p>
    <p class="loader-msg">Por favor, espere</p>

    <!-- 🔥 Nueva barra de carga profesional -->
    <div class="loading-bar">
      <div class="loading-fill" id="loading-fill"></div>
      <div class="loading-percent" id="loading-percent">0%</div>
    </div>

  </div>
</div>

<style>
#loader-screen {
  position: fixed;
  inset: 0;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  z-index: 10000;
  transition: opacity 1s ease, visibility 1s ease;
}
#loader-screen.hidden {
  opacity: 0;
  visibility: hidden;
}
.loader-content { text-align: center; }

.loader-circle {
  width: 180px;
  height: 180px;
  border-radius: 50%;
  border: 6px solid transparent;
  border-top: 6px solid #00aaff;
  border-bottom: 6px solid #ff007f;
  animation: spin 2s linear infinite;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  box-shadow: 0 0 30px rgba(255, 0, 128, 0.5);
}

.loader-logo { width: 100px; }

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loader-title {
  font-size: 2.5rem;
  color: #fff;
  text-shadow: 0 0 10px #ff4da6, 0 0 20px #ff1a8c, 0 0 40px #ff007f;
  font-weight: bold;
  margin-bottom: 10px;
  letter-spacing: 2px;
}

.loader-sub { font-size: 1.2rem; color: #ccc; }
.loading-dots::after { content: ''; animation: dotPulse 1.5s steps(4) infinite; }
@keyframes dotPulse {
  0% { content: ''; }
  25% { content: '.'; }
  50% { content: '..'; }
  75% { content: '...'; }
  100% { content: ''; }
}
.loader-msg { font-size: 1rem; color: #888; margin-top: 10px; }

/* 🔥 NUEVA BARRA PROFESIONAL (MISMO ESTILO DE COLOR) */
.loading-bar {
  width: 75%;
  height: 16px;
  background: rgba(255,255,255,0.12);
  border-radius: 10px;
  margin: 22px auto 0;
  position: relative;
  overflow: hidden;
}

.loading-fill {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, #00aaff, #ff007f);
  transition: width 0.3s ease;
}

.loading-percent {
  position: absolute;
  inset: 0;
  color: #fff;
  font-size: 12px;
  font-weight: bold;
  display: flex;
  justify-content: center;
  align-items: center;
  pointer-events: none;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const loader = document.getElementById('loader-screen');
  const bar = document.getElementById('loading-fill');
  const percent = document.getElementById('loading-percent');

  let totalImages = document.images.length;
  let loaded = 0;

  if (totalImages === 0) {
    totalImages = 1;
    loaded = 1;
  }

  function updateLoader() {
    loaded++;
    let p = Math.floor((loaded / totalImages) * 100);

    bar.style.width = p + "%";
    percent.textContent = p + "%";

    if (p >= 100) {
      setTimeout(() => {
        loader.classList.add("hidden");
      }, 600);
    }
  }

  for (let img of document.images) {
    if (img.complete) updateLoader();
    else {
      img.addEventListener("load", updateLoader);
      img.addEventListener("error", updateLoader);
    }
  }
});
</script>
<!-- 🔴 Fin pantalla de carga neón -->
  
 <style>
#video-container {
  position: relative;
  overflow: hidden;
}

#player-loader {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.4);
  width: 100%;
  height: 100%;
  z-index: 20;
  border-radius: 8px;
}

.player-spinner {
  width: 43px; /*circulo de carga de reproductor estaba en 60*/
  height: 43px; /*circulo de carga de reproductor estaba en 60*/
  border: 5px solid #222;
  border-top: 5px solid #ff007f;
  border-bottom: 5px solid #00aaff;
  border-radius: 50%;
  animation: spin 1.3s linear infinite;
  margin-bottom: 15px;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.player-loading-text {
  font-size: 0.80rem; /*agrandar o achicar letra de cargando en el reproductor (estaba en 1.2)*/
  color: #fff;
  text-align: center;
  font-weight: 600;
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
/*
.mobile-player {
  position: relative;
  width: 100%;
  max-width: 100%;
  aspect-ratio: 16 / 9;
  background: #000;
  border-radius: 14px;

  🔥 ESTO ES LO QUE FALTABA 
  min-height: 200px;
}



.mobile-player {
  position: sticky;
  top: 0;
  z-index: 1000;

  width: 100%;
  aspect-ratio: 16 / 9;   
  background: black;
  overflow: hidden;
}
*/

/* ===== REPRODUCTOR (PC por defecto) ===== */
.mobile-player {
  position: relative;     /* ⬅ PC: NO sticky */
  width: 100%;
  aspect-ratio: 16 / 9;
  background: black;
  overflow: hidden;
}

/* 📱 MÓVIL: reproductor fijo arriba */
@media (max-width: 768px) {
  .mobile-player {
    position: sticky;
    top: 0;
    z-index: 1000;
  }
}


.mobile-player video {
  width: 100%;
  height: 100%;
  object-fit: contain; /* 👈 respeta proporción del video */
  background: black;
  pointer-events: none; /* 🔥 evita que el video bloquee los controles */
}

.mobile-player {
  touch-action: manipulation;
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
.overlay-bottom {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;

  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;

  z-index: 6;
}

.overlay-bottom-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.overlay-bottom-controls #progressBar {
  flex: 1;
}

.overlay-bottom input[type="range"] {
  width: 100%;
}

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


/* ===============================
   PROGRESS BAR MULTICOLOR
   Azul • Celeste • Blanco • Rosa
   Compatible PC + Móvil
================================ */

/* ===== PROGRESS BAR ===== */
#progressBar {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 6px;
  border-radius: 999px;
  background: linear-gradient(90deg, #007bff, #00cfff, #ffffff, #ff4fa3);
  cursor: pointer;
}

/* Track Firefox */
#progressBar::-moz-range-track {
  height: 6px;
  border-radius: 999px;
  background: linear-gradient(90deg, #007bff, #00cfff, #ffffff, #ff4fa3);
}

/* 🎯 THUMB PC – más chico */
#progressBar::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 14px;      /* ⬅ más chico */
  height: 14px;     /* ⬅ más chico */
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #00cfff;
  box-shadow: 0 0 6px rgba(0, 200, 255, 0.8);
  margin-top: -4px; /* ⬅ centrado */
}

/* Firefox */
#progressBar::-moz-range-thumb {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: #ffffff;
  border: 2px solid #00cfff;
  box-shadow: 0 0 6px rgba(0, 200, 255, 0.8);
}

/* 📱 MÓVIL */
@media (max-width: 768px) {
  #progressBar {
    height: 8px;
  }

  #progressBar::-webkit-slider-thumb {
    width: 16px;
    height: 16px;
    margin-top: -2px; /* sigue centrado */
  }

  #progressBar::-moz-range-thumb {
    width: 16px;
    height: 16px;
  }
}



.fullscreen-btn {
  align-self: flex-end;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  border: none;
  background: rgba(0,0,0,.6);
  color: white;
  font-size: 18px;
}


.overlay-bottom .time {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: #ccc;
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

  
</style>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const video = document.getElementById('videoPlayer');
  const loader = document.getElementById('player-loader');

  if (!video || !loader) return;

  const showLoader = () => {
    loader.style.display = 'flex';
  };

  const hideLoader = () => {
    loader.style.display = 'none';
  };

  // 🔄 Cargando video
  video.addEventListener('loadstart', showLoader);
  video.addEventListener('waiting', showLoader);
  video.addEventListener('stalled', showLoader);

  // ▶️ Listo / reproduciendo
  video.addEventListener('canplay', hideLoader);
  video.addEventListener('playing', hideLoader);

  // ⏸️ Pausa o fin → no mostrar loader
  video.addEventListener('pause', hideLoader);
  video.addEventListener('ended', hideLoader);
});
</script>

<!-- SCRIPT DE VERIFICACION DE SUSPENDIDO AL USUARIO-->

<script>
  setInterval(() => {

  fetch("auth.php?check_status=1")
    .then(res => res.text())
    .then(data => {

      if (data === "logout") {
        window.location.href = "../index.php";
      }

    });

}, 15000); // cada 15 segundos

</script>

<!-- FIN -->

<div class="mobile-player" id="mobilePlayer">
  <!-- 🔄 Loader del reproductor -->
  <div id="player-loader">
    <div class="player-spinner"></div>
    <div class="player-loading-text">
      Cargando<span class="dots"></span>
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
      <div class="time">
        <span id="currentTime">0:00</span>
        <span id="duration">0:00</span>
      </div>
      <div class="overlay-bottom-controls">
        <input type="range" id="progressBar" value="0">
        <button id="btnFullscreen" class="fullscreen-btn">⛶</button>
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
    <span class="genero-badge"><!--Genero • Genero--></span>
  
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
      <button id="btn-favorito">⭐ Agregar a Favoritos</button>
    </div>
  </div>

  <script>
    const MOVIES_DB = {

    /*Numeros*/

    secenta_minutos: {
    id: "secenta_minutos",
    titulo: "60 Minutos",
    video: "https://dl.dropbox.com/scl/fi/mb198ke9qzql2nmonx917/60-Minutos.mp4?rlkey=ci5hig86v3okgw54kyazo8o6j&st=",
    poster: "https://image.tmdb.org/t/p/w780/unvtbkgxh47BewQ8pENvdOdme0r.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/cND79ZWPFINDtkA8uwmQo1gnPPE.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Desesperado por no perder la custodia de su hija, un luchador de artes marciales mixtas abandona una pelea y recorre Berlín a todo gas para verla el día de su cumpleaños. ",
    anio: "2024",
    duracion: "1h 29min",
    calificacion: "82%",
    genero: "Accion • MMA",
    director: "Oliver Kienle",
    reparto: "Emilio Sakraya, Dennis Mojen, Marie Mouroum",
    estreno: "19/01/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "rendirse_jamas",
        titulo: "Rendirse Jamas",
        imagen: "https://image.tmdb.org/t/p/w300/nas9XShlxUZrNZCyBdf4AAXpRiq.jpg"
      },
      {
        id: "boyka_invicto_4",
        titulo: "Boyka: Invicto IV",
        imagen: "https://image.tmdb.org/t/p/w300/yegOHiGUyHiUXNSFlMfFTEZboj7.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Beekeeper: Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      },
      {
        id: "dura_de_entrenar",
        titulo: "Duro de entrenar",
        imagen: "https://image.tmdb.org/t/p/w300/eA6FztxHGs43AS6v1TF7PwugEXQ.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool Y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "heroico",
        titulo: "Heroíco",
        imagen: "https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"
      }
    ]
  },

  dias_365: {
    id: "dias_365",
    titulo: "365 Días",
    video: "https://objectstorage.us-phoenix-1.oraclecloud.com/n/axa4wow3dcia/b/bucket-20201001-1658/o/pelisarregladas%2Fparte2%2FVer%20pel%C3%ADcula%20365%20d%C3%ADas%20online%20gratis%20en%20HD%20%E2%80%A2%20Gnula.mp4",
    poster: "https://image.tmdb.org/t/p/w780/e8b2F4eg6ansZhaQQN8iXfzZtz7.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Massimo es miembro de la mafia siciliana y Laura es una directora de ventas. Ella no espera que un viaje a Sicilia salve su relación, pero Massimo la secuestrará durante 365 días para que se enamore de él.",
    anio: "2020",
    duracion: "1h 54min",
    calificacion: "86%",
    genero: "Romance • Drama",
    director: "Barbara Bialowas, Tomasz Mandes",
    reparto: "Michele Morrone, Anna-Maria, Rebecca Casiraghi",
    estreno: "07/02/2020",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dias_365_2",
        titulo: "365 Días 2: Aquel dia",
        imagen: "https://image.tmdb.org/t/p/w300/k3J2GdYxhR6U2RfsHZOsmHVKW7m.jpg"
      },
      {
        id: "dias_365_3",
        titulo: "365 Días 3: Más",
        imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg"
      },
      {
        id: "la_joven_y_el_mar",
        titulo: "La joven y el mar",
        imagen: "https://image.tmdb.org/t/p/w300/3YgGeJU0eU9XKVMooKldBU7zWLK.jpg"
      },
      {
        id: "nahir",
        titulo: "Nahir",
        imagen: "https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"
      },
      {
        id: "nada_que_ver",
        titulo: "Nada que ver",
        imagen: "https://image.tmdb.org/t/p/w300/ofnOwcG9l1DuGl7vB45JHsfSlR6.jpg"
      },
      {
        id: "La_mitad_de_Ana",
        titulo: "La mitad de Ana",
        imagen: "https://image.tmdb.org/t/p/w300/c24RWnJzwAtWZ039o9u6K7c8jyw.jpg"
      }
    ]
  },

  dias_365_2: {
    id: "dias_2_365",
    titulo: "365 Días 2: Aquel día",
    video: "https://objectstorage.us-phoenix-1.oraclecloud.com/n/axa4wow3dcia/b/bucket-20201001-1658/o/2022pelicu%2Fabril%2FVer%20365%20dni-%20Ten%20dzie%C5%84%20Online%20Castellano%20Latino%20Subtitulada%20HD%20-%20HDFull.mp4",
    poster: "https://image.tmdb.org/t/p/w780/zBG5Mg29NH9xxpWMMG7BIvKwYhL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/k3J2GdYxhR6U2RfsHZOsmHVKW7m.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Laura y Massimo vuelven más fuertes que nunca, pero las ataduras familiares de Massimo y un misterioso hombre que quiere conquistar a Laura complican su relación.",
    anio: "2022",
    duracion: "1h 51min",
    calificacion: "70%",
    genero: "Romance • Drama",
    director: "Barbara Bialowas, Tomasz Mandes",
    reparto: "Michele Morrone, Natasza Urbańska, Rebecca Casiraghi",
    estreno: "27/04/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dias_365",
        titulo: "365 Días",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "dias_365_3",
        titulo: "365 Días 3: Más",
        imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg"
      },
      {
        id: "romper_el_circulo",
        titulo: "Romper el circulo",
        imagen: "https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"
      },
      {
        id: "el_es_asi",
        titulo: "El es asi",
        imagen: "https://image.tmdb.org/t/p/w300/gTboh2Tf7zKlXWJk4UdOL1G8ki7.jpg"
      },
      {
        id: "un_ladron_romantico",
        titulo: "Un ladrón romantiico",
        imagen: "https://image.tmdb.org/t/p/w300/nif2JUyqNQBBmMYrDfmpTgwleOJ.jpg"
      },
      {
        id: "lecciones_para_canella",
        titulo: "Lecciones para canalla",
        imagen: "https://image.tmdb.org/t/p/w300/AP9kVisL0Xo4GNBQVNa8qv1yLo.jpg"
      }
    ]
  },

  dias_365_3: {
    id: "dias_365_3",
    titulo: "365 Días 3: Más",
    video: "https://dl.dropbox.com/scl/fi/7i4ktk81fxyvhk66y73rr/365-Dias-3-Mas-2022.mp4?rlkey=bhnbxhi94hh6hw730kl8hqjfy&st=",
    poster: "https://image.tmdb.org/t/p/w780/6cpRpfD3isvluFwXDGSiDVyibPJ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La relación de Laura y Massimo pende de un hilo mientras intentan superar los problemas de confianza y los celos, mientras que un tenaz Nacho trabaja para separarlos.",
    anio: "2022",
    duracion: "1h 53min",
    calificacion: "64%",
    genero: "Romance • Drama",
    director: "Barbara Bialowas, Tomasz Mandes",
    reparto: "Anna-Maria Sieklucka, Michele Morrone",
    estreno: "19/08/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dias_365",
        titulo: "365 Días",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "dias_365_2",
        titulo: "365 Días 2: Aquel día",
        imagen: "https://image.tmdb.org/t/p/w300/jBpqADo9XAKaecvI3f0J4hRAEyO.jpg"
      },
      {
        id: "en_las_profundidasdes_del_sena",
        titulo: "En las profundidasdes del sena",
        imagen: "https://image.tmdb.org/t/p/w300/3Nr9KwcPMF31BGlOfHXeAJhO2dF.jpg"
      },
      {
        id: "barbie",
        titulo: "Barbie",
        imagen: "https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "corazon_delator",
        titulo: "Corazón delator",
        imagen: "https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg"
      }
    ]
  },

  argen_1978_a: {
    id: "argen_1978_a",
    titulo: "1978",
    video: "https://dl.dropbox.com/scl/fi/h3y5ye6hfqifjsqolfg0w/1978.2025.1080P-Lat.mkv?rlkey=fbvfj844uowkaro7wi6y6ifqb&st=",
    poster: "https://image.tmdb.org/t/p/w780/A81WfCAmydM880E9ZkULRjaX9QL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iyKixwGhGRas1ppAih8E7SG5QDZ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Durante la final del Mundial, en tiempos de dictadura militar, unos torturadores secuestran a un grupo de jóvenes. Lo que comienza como un interrogatorio inhumano se convierte en un verdadero tormento: han secuestrado a personas equivocadas. Eso no es todo, ya que pertenecen a una secta guiada por una fuerza desconocida y el centro clandestino de detención se convertirá en el mismísimo infierno.",
    anio: "2025",
    duracion: "1h 20min",
    calificacion: "60%",
    genero: "Terror",
    director: "Nicolás y Luciano Onetti",
    reparto: "Agustín Pardella, Carlos Portaluppi, Mario Alarcón",
    estreno: "06/03/2025",
    idioma: "Argentina 🇦🇷",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "annabelle_2014",
        titulo: "Annabelle",
        imagen: "https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg"
      },
      {
        id: "baghead_contacto_con_la_muerte",
        titulo: "Baghead: Contacto con la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/5ssaCHmqvTZDVZtcNhNZTzfb7Nj.jpg"
      },
      {
        id: "el_conjuro",
        titulo: "El conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"
      },
      {
        id: "eliminar_amigo",
        titulo: "Eliminar amigo",
        imagen: "https://image.tmdb.org/t/p/w300/pzxHNiKjHL8Sz7DZ7POXXqohxet.jpg"
      },
      {
        id: "en_las_profundidades_del_sena",
        titulo: "En las profundidades del sena",
        imagen: "https://image.tmdb.org/t/p/w300/3Nr9KwcPMF31BGlOfHXeAJhO2dF.jpg"
      }
    ]
  },
  
      
  /*A*/

  avatar_3: {
    id: "avatar_3",
    titulo: "Avatar 3: Fuego y ceniza",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/iN41Ccw4DctL8npfmYg1j5Tr1eb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vHtH4xdcTbaCVftGwaeGFHfOB3p.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras la devastadora guerra contra la RDA y la pérdida de su hijo mayor, Jake Sully y Neytiri se enfrentan a una nueva amenaza en Pandora: el Pueblo de Ceniza, una violenta tribu Na'vi ávida de poder, liderada por los despiadados Varang. La familia de Jake debe luchar por su supervivencia y el futuro de Pandora en un conflicto que los lleva al límite emocional y físico.",
    anio: "2025",
    duracion: "0h 008min",
    calificacion: "73%",
    genero: "Acción • Ciencia Ficción • Aventura • Fantasía",
    director: "James Cameron",
    reparto: "Sam Worthington, Zoe Saldaña, Sigourney Weaver",
    estreno: "18/12/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "avatar",
        titulo: "Avatar",
        imagen: "https://image.tmdb.org/t/p/w300/gKY6q7SjCkAU6FqvqWybDYgUKIF.jpg"
      },
      {
        id: "avatar_2",
        titulo: "Avatar 2: El camino del agua",
        imagen: "https://image.tmdb.org/t/p/w300/2GnRbNaUh4sRXzKgE3VuqukcaG6.jpg"
      },
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "corazones_de_acero",
        titulo: "Corazones de acero",
        imagen: "https://image.tmdb.org/t/p/w300/kbtH5G8L8REzy72LkLmKYoBVaGv.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "cato",
        titulo: "CATO",
        imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"
      }
    ]
  },

  a_ganar: {
    id: "a_ganar",
    titulo: "¡A ganar!",
    video: "https://dl.dropbox.com/scl/fi/0prsz81yb8pv74njs8d0j/The.miracle.season.2018.1080p-dual-lat-cinecalidad.to.mp4?rlkey=g97lmjjkt9ekh8stxzh6ci8g8&amp;st=",
    poster: "https://image.tmdb.org/t/p/w780/zwfec4vK1EvkQapE5wcYrB1BHov.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/6GVYL9K2IBFrfIqwwFqMPu5DdC5.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Basado en la historia real del equipo de voleibol femenino de West High School. Tras la trágica muerte de la jugadora estrella de la escuela, Caroline 'Line' Found, las jugadores restantes deberán unirse bajo la dirección de su dura entrenadora (Helen Hunt) con la esperanza de ganar el campeonato estatal.",
    anio: "2018",
    duracion: "1h 41min",
    calificacion: "74%",
    genero: "Drama",
    director: "Sean McNamara",
    reparto: "Helen Hunt, Erin Moriarty, William Hurt",
    estreno: "06/04/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "yo_tonya",
        titulo: "Yo, Tonya",
        imagen: "https://image.tmdb.org/t/p/w300/aVWX0t95Igd8kKC3ejmtHCy1vX6.jpg"
      },
      {
        id: "desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      },
      {
        id: "Asesino_serial",
        titulo: "Asesino serial",
        imagen: "https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg"
      },
      {
        id: "harta",
        titulo: "Harta",
        imagen: "https://image.tmdb.org/t/p/w300/4d2PJ6QLAVd9w66E918JSWjkgs7.jpg"
      },
      {
        id: "La_mitad_de_Ana",
        titulo: "La mitad de Ana",
        imagen: "https://image.tmdb.org/t/p/w300/c24RWnJzwAtWZ039o9u6K7c8jyw.jpg"
      },
      {
        id: "El_deseo_de_Ana",
        titulo: "El deseo de Ana",
        imagen: "https://image.tmdb.org/t/p/w300/89XUJQYBjlxayW7IBnlNoxn1bPg.jpg"
      }
    ]
  },

  abigail: {
    id: "abigail",
    titulo: "Abigail",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmay24%2FVer%20Abigail%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/uy0uipx90Su2WqOjDSazOJDryUj.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kmB9grIf2fvpwwsDmNMN0XFz1tT.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "A una banda de delincuentes se les ha encargado secuestrar a Abigail, una bailarina de doce años hija de una poderosa figura del inframundo. Su misión requiere también vigilarla durante la noche para poder cobrar un rescate de 50 millones de dólares. En una mansión aislada, los captores comienzan a desaparecer, uno por uno, y descubren, para su creciente horror, que la pequeña niña con la que están encerrados no es normal y está mostrando su verdadera naturaleza.",
    anio: "2024",
    duracion: "1h 49min",
    calificacion: "86%",
    genero: "Terror",
    director: "Matt Bettinelli-Olpin y Tyler Gillett",
    reparto: "Melissa Barrera, Alisha Weir, Dan Stevens",
    estreno: "21/04/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "los_extraños_capitulo_1",
        titulo: "Los extraños: Capitulo uno",
        imagen: "https://image.tmdb.org/t/p/w300/za4jDcPQ5IV4p27UGcC5uEgsNGG.jpg"
      },
      {
        id: "el_exorcismo_de_georgetown",
        titulo: "El exorcista de georgetown",
        imagen: "https://image.tmdb.org/t/p/w300/ioQCdjn2YPfAJMfJlgzNdXgYZrr.jpg"
      },
      {
        id: "hablame",
        titulo: "Hablame",
        imagen: "https://image.tmdb.org/t/p/w300/rS8fjd6dYcf64v3ZhAE6fKrxoaF.jpg"
      },
      {
        id: "la_niña_de_la_comunion",
        titulo: "La niña de la comunión",
        imagen: "https://image.tmdb.org/t/p/w300/oV3R0E1GOXVrybojkEDvool22Bi.jpg"
      },
      {
        id: "winnie_the_pooh_2",
        titulo: "Winnie the Pooh 2: El bosque sangriento",
        imagen: "https://image.tmdb.org/t/p/w300/2sADrLwMQof6yYmrJRSa04tFZuS.jpg"
      },
      {
        id: "mal_de_ojo",
        titulo: "Mal de ojo",
        imagen: "https://image.tmdb.org/t/p/w300/lVRoLtpn4zq97YjTrCGsb5BlrW.jpg"
      }
    ]
  },
  
  after_2019: {
    id: "after_2019",
    titulo: "After: Aquí empieza todo",
    video: "https://dl.dropbox.com/scl/fi/my2rjwlpuunyzasp3d2fa/After.we.collided.2020.1080P-Dual-Lat.mp4?rlkey=pse9hjnqtt00l0m097dxv7sm7&st=",
    poster: "https://image.tmdb.org/t/p/w780/2v7RA1nbYnaz0NPBw3fq4bOvLgN.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La joven Tessa Young cursa su primer año en la universidad. Acostumbrada a una vida estable y ordenada, su mundo cambia cuando conoce a Hardin Scott, un misterioso joven de oscuro pasado. Desde el primer momento se odian, porque pertenecen a dos mundos distintos y son completamente opuestos. Sin embargo, estos dos polos opuestos pronto se unirán y nada volverá a ser igual. Tessa y Hardin deberán enfrentarse a difíciles pruebas para estar juntos. La inocencia, el despertar a la vida, el descubrimiento sexual y las huellas de un amor tan poderoso como la fuerza del destino.",
    anio: "2019",
    duracion: "1h 45min",
    calificacion: "84%",
    genero: "Romance • Drama",
    director: "Jenny Gage",
    reparto: "Josefina Langford, Hero Fiennes Tiffin, Shane Paul McGhie",
    estreno: "01/04/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "after_3",
        titulo: "After 3: Amor infinito",
        imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg"
      },
      {
        id: "after_4",
        titulo: "After 4: Aquí acaba todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "culpa_nuestra_3",
        titulo: "Culpa nuestra 3",
        imagen: "https://image.tmdb.org/t/p/w300/6kmi6vmp6iOn4KzI7WfnVtAeJhU.jpg"
      },
      {
        id: "almas_marcadas",
        titulo: "Almas marcadas: Rule + Shaw",
        imagen: "https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg"
      },
      {
        id: "nada_que_ver",
        titulo: "Nada qué ver",
        imagen: "https://image.tmdb.org/t/p/w300/ofnOwcG9l1DuGl7vB45JHsfSlR6.jpg"
      }
    ]
  },

  after_2: {
    id: "after_2",
    titulo: "After 2: En mil pedazos",
    video: "https://dl.dropbox.com/scl/fi/9ztayaphhu5khkh464ep4/After.we.fell.2021.1080P-Dual-Lat.mp4?rlkey=ei8fb4b80wcashrezwtjgab2f&st=",
    poster: "https://image.tmdb.org/t/p/w780/6hgItrYQEG33y0I7yP2SRl2ei4w.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Ha pasado el tiempo y Hardin todavía no se sabe si es realmente el chico profundo y reflexivo del que Tessa se enamoró, o ha sido un extraño todo este tiempo. Ella quiere alejarse, pero no es tan fácil. Tessa se ha centrado en sus estudios y comienza a trabajar como becaria en Vance Publishing. Allí conoce a Trevor, un nuevo y atractivo compañero de trabajo que es exactamente el tipo de persona con la que debería estar. Pero Hardin sabe que cometió un error, posiblemente el más grande de su vida y quiere corregir sus errores y vencer a sus demonios.",
    anio: "2020",
    duracion: "1h 38min",
    calificacion: "72%",
    genero: "Drama • Romance",
    director: "Roger Kumble",
    reparto: "Josefina Langford, Hero Fiennes Tiffin, Luisa Lombard",
    estreno: "04/09/2020",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"
      },
      {
        id: "after_3",
        titulo: "After 3: Amor infinito",
        imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg"
      },
      {
        id: "after_4",
        titulo: "After 4: Aquí acaba todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "dias_365_2",
        titulo: "365 Dias 2: Aquel día",
        imagen: "https://image.tmdb.org/t/p/w300/k3J2GdYxhR6U2RfsHZOsmHVKW7m.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa Mia 2: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "un_ladron_romantico",
        titulo: "Un ladrón romántico",
        imagen: "https://image.tmdb.org/t/p/w300/nif2JUyqNQBBmMYrDfmpTgwleOJ.jpg"
      }
    ]
  },

  after_3: {
    id: "after_3",
    titulo: "After 3: Amor infinito",
    video: "https://dl.dropbox.com/scl/fi/1sxa7z40k8spgl169c9mr/After.Para.Siempre.2023.1080P-Dual-Lat.mp4?rlkey=ndpuunh4tejq42usn5dfgbaeq&st=",
    poster: "https://image.tmdb.org/t/p/w780/rwgmDkIEv8VjAsWx25ottJrFvpO.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El amor de Tessa y Hardin nunca ha sido fácil. Mientras él permanece en Londres después de la boda de su madre y se hunde cada vez más en su propia oscuridad, ella regresa a Seattle. Tessa es la única capaz de entenderle y calmarle... él la necesita, pero ella ya no es la chica buena y dulce que era cuando llegó a la universidad. Deberá plantearse si lo que debe hacer ahora es salvar a Hardin y su relación con él, o si ha llegado el momento de pensar solo en ella. Si quieren que su amor sobreviva, primero tendrán que trabajar en sí mismos. ¿Pero será su destino seguir estando juntos?",
    anio: "2022",
    duracion: "1h 33min",
    calificacion: "77%",
    genero: "Romance • Drama",
    director: "Castille Landon",
    reparto: "Josephine Langford, Hero Fiennes Tiffin, Louise Lombard",
    estreno: "25/08/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"
      },
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "after]_4",
        titulo: "After 4: Aquí acaba todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "millers_girl",
        titulo: "Miller's Girl",
        imagen: "https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"
      },
      {
        id: "Romper_el_circulo",
        titulo: "Romper el circulo",
        imagen: "https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"
      },
      {
        id: "cincuenta_sombras_más_oscuras_2",
        titulo: "Cincuenta sombras más oscuras 2",
        imagen: "https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg"
      }
    ]
  },

  after_4: {
    id: "after_4",
    titulo: "After 4: Aquí acaba todo",
    video: "https://dl.dropbox.com/scl/fi/pvf8rt6u4k9a16wz4bnd6/After.Ever.Happy.2022.1080P-Dual-Lat.mp4?rlkey=pdgunohi7i4ljm49emvvz317t&st=",
    poster: "https://image.tmdb.org/t/p/w780/fH7kYS9qEOkhFQZc0Dcoa9MFjje.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Hardin sigue luchando por seguir adelante. Agobiado por el bloqueo del escritor y la dolorosa ruptura con Tessa, Hardin viaja a Portugal en busca de una mujer a la que hizo daño en el pasado... y para encontrarse a sí mismo. Con la esperanza de recuperar a Tessa, se da cuenta de que necesita cambiar su forma de ser antes de poder comprometerse definitivamente.",
    anio: "2023",
    duracion: "1h 35min",
    calificacion: "00%",
    genero: "Romamce • Drama",
    director: "Castille Landon",
    reparto: "Hero Fiennes Tiffin, Josephine Langford, Mimi Keene",
    estreno: "07/09/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"
      },
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "after_3",
        titulo: "After 3: Amor infinito",
        imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg"
      },
      {
        id: "cincuenta_sombra_liberadas_3",
        titulo: "Cincuenta sombras liberadas 3",
        imagen: "https://image.tmdb.org/t/p/w300/sM8hwgWZlmZf0h4aOkNopb3HBIo.jpg"
      },
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "nahir",
        titulo: "Nahir",
        imagen: "https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"
      }
    ]
  },

  al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas: {
    id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
    titulo: "Al rescate de Fondo de Bikini: La película de Arenita Mejillas",
    video: "https://dl.dropbox.com/scl/fi/lm34v9ne3qvjrjwnun40r/Al-rescate-a-fondo-de-bikini-arenita-2025-Mp4.mp4?rlkey=q1ipsn1xgcv2ymf9z6vas6ch4&st=",
    poster: "https://image.tmdb.org/t/p/w780/b80Ql0yP3lushkYpv4zgS93yfdJ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Fondo de Bikini es arrastrado fuera del mar, la ardilla científica Arenita Mejillas y su amigo Bob Esponja se embarcan rumbo a Texas para salvar su ciudad.",
    anio: "2024",
    duracion: "1h 26min",
    calificacion: "70%",
    genero: "Animacio • Familia • Aventura • Comedia",
    director: "Liza Johnson",
    reparto: "Matty Cardarople, Bill Fagerbakke, Jill Talley",
    estreno: "02/08/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bob_esponja_1",
        titulo: "Bob Esponja: La película",
        imagen: "https://image.tmdb.org/t/p/w300/CtISczftMz6g7kyk5uLxBben8b.jpg"
      },
      {
        id: "bob_esponja_2",
        titulo: "Bob Esponja 2: Un héroe fuera del agua",
        imagen: "https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg"
      },
      {
        id: "bob_esponja_3",
        titulo: "Bob Esponja 3: Un héroe al rescate",
        imagen: "https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg"
      },
      {
        id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
        titulo: "Bob Esponja 4: En busca de los pantalones Cuadrados",
        imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg"
      }
    ]
  },

  aladdin: {
    id: "aladdin",
    titulo: "Aladdín",
    video: "https://dl.dropbox.com/scl/fi/vql4ijsfk0s5465s1bt6a/Aladdin.1992.1080p-dual-lat-cinecalidad.is.mp4?rlkey=tgeihruzajf0ovwpzpazjhhax&st=",
    poster: "https://image.tmdb.org/t/p/w780/5OeY4U2rzePxWq2rkqMajUx2gz7.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/eLFfl7vS8dkeG1hKp5mwbm37V83.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Aladdín es un ingenioso joven que vive en una extrema pobreza, y que sueña con casarse con la bella hija del sultán, la princesa Jasmine. El destino interviene cuando el astuto visir del Sultán, Jafar, recluta a Aladdín para que le ayude a recuperar una lámpara mágica de las profundidades de la Cueva de las Maravillas. Aladdín encuentra una lámpara maravillosa con un genio dentro, y sus deseos comienzan a hacerse realidad.",
    anio: "1993",
    duracion: "1h 30min",
    calificacion: "82%",
    genero: "Animacion • Aventura • Disney • Romance • Fantasia",
    director: "Ron Clements, John Musker",
    reparto: "Robin Williams, Scott Weinger, Linda Larkin",
    estreno: "25/11/1992",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "aladdin_2",
        titulo: "Aladdín 2: El retorno de Jafar",
        imagen: "https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg"
      },
      {
        id: "aladdin_3",
        titulo: "Aladdín 3: El rey de los ladrones",
        imagen: "https://image.tmdb.org/t/p/w300/icinYia0UBPDby1bxak4B44ntK3.jpg"
      },
      {
        id: "aladdin_2019",
        titulo: "Aladdín",
        imagen: "https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg"
      },
      {
        id: "coco",
        titulo: "Coco",
        imagen: "https://image.tmdb.org/t/p/w300/gGEsBPAijhVUFoiNpgZXqRVWJt2.jpg"
      },
      {
        id: "hercules",
        titulo: "Hercules",
        imagen: "https://image.tmdb.org/t/p/w300/dK9rNoC97tgX3xXg5zdxFisdfcp.jpg"
      },
      {
        id: "hotel_transylvania_3",
        titulo: "Hotel Transylvania 3",
        imagen: "https://image.tmdb.org/t/p/w300/gjAFM4xhA5vyLxxKMz38ujlUfDL.jpg"
      }
    ]
  },

  aladdin_2: {
    id: "aladdin_2",
    titulo: "Aladdín 2: El retorno de Jafar",
    video: "https://dl.dropbox.com/scl/fi/arba3rvk9wcjzyop87qjv/Aladdin-2.mp4?rlkey=5mrj74618l97biga8cb94u0cv&st=",
    poster: "https://image.tmdb.org/t/p/w780/mOOJm3tamy9iHg2mOEA77CM6ufZ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Aladdín y Jasmine viven felices en el palacio real, rodeados de lujos y de la magia del genio. Pero, muy lejos de allí, algo se remueve en las arenas del desierto: Iago, el loro dentudo de Jafar, ha logrado escapar de la lámpara que los mantenía presos a ambos. Tras traicionar a su amo, Iago decide vivir una nueva vida en donde él es el jefe. Pero Jafar esconde más de un as en la manga.",
    anio: "1994",
    duracion: "1h 09min",
    calificacion: "82%",
    genero: "Animacion • Aventura • Disney • Romance • Fantasia",
    director: "Guy Ritchie",
    reparto: "Robin Williams, Scott Weinger, Linda Larkin",
    estreno: "20/03/1994",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "aladdin",
        titulo: "Aladdín",
        imagen: "https://image.tmdb.org/t/p/w300/oakAd8syy7jNQ4ZoaAGCQkTqcOV.jpg"
      },
      {
        id: "aladdin_3",
        titulo: "Aladdín 3: El rey de los ladrones",
        imagen: "https://image.tmdb.org/t/p/w300/icinYia0UBPDby1bxak4B44ntK3.jpg"
      },
      {
        id: "aladdin_2019",
        titulo: "Aladdín",
        imagen: "https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg"
      },
      {
        id: "tarzan",
        titulo: "Tarzan",
        imagen: "https://image.tmdb.org/t/p/w300/1Gk8iihu4Q4BGh2n1IwNLB3zM8E.jpg"
      },
      {
        id: "toy_story_2",
        titulo: "Toy Story 2",
        imagen: "https://image.tmdb.org/t/p/w300/t1VBfUln1XwTDHYjQaijyb7m888.jpg"
      },
      {
        id: "los_increibles_2",
        titulo: "Los Increíbles 2",
        imagen: "https://image.tmdb.org/t/p/w300/x1txcDXkcM65gl7w20PwYSxAYah.jpg"
      }
    ]
  },

  aladdin_3: {
    id: "aladdin_3",
    titulo: "Aladdín 3: El rey de los ladrones",
    video: "https://dl.dropbox.com/scl/fi/hwfh35xta2br4myvu099w/Aladdin-3.mp4?rlkey=9v1n013fwd39e5xgin5e7f8ez&st=",
    poster: "https://image.tmdb.org/t/p/w780/6ywLV3O6InF1BE870a4EgSpBoja.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/abWvjyJz4kcp1xDn28RwyXjoIds.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Por fin Aladdín se va a casar con la princesa Jasmine. A pesar de la presencia y ánimo de sus amigos Genio, Alfombra y el mono Abú, está algo preocupado: tiene miedo porque no sabe qué tipo de padre va a ser, si él nunca tuvo ninguno. Pero todas sus preocupaciones quedarán de lado cuando 40 ladrones irrumpen en la boda para robar un mágico talismán.",
    anio: "1996",
    duracion: "1h 21min",
    calificacion: "75%",
    genero: "Animacion • Aventura • Disney • Romance • Fantasia",
    director: "Tad Stones",
    reparto: "Scott Weinger, Linda Larkin, Val Bettin",
    estreno: "22/01/1996",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "aladdin",
        titulo: "Aladdín",
        imagen: "https://image.tmdb.org/t/p/w300/oakAd8syy7jNQ4ZoaAGCQkTqcOV.jpg"
      },
      {
        id: "aladdin_2",
        titulo: "Aladdín 2: El retorno de Jafar",
        imagen: "https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg"
      },
      {
        id: "aladdin_2019",
        titulo: "Aladdín",
        imagen: "https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg"
      },
      {
        id: "sonic_3",
        titulo: "Sonic 3: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"
      },
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man: Cruzando el multiverso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "Intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      }
    ]
  },

  aladdin_2019: {
    id: "aladdin_2019",
    titulo: "Aladdín",
    video: "https://dl.dropbox.com/scl/fi/acgrahd2zzcqgh5zo0hx9/Aladdin.2019.1080p-dual-lat-cinecalidad.to.mp4?rlkey=qtonjfh72uy6bvmjsad1kiadh&st=",
    poster: "https://image.tmdb.org/t/p/w780/oX056O8bAInZ75jGY9MacQ2VlsM.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Aladdin es un adorable pero desafortunado ladronzuelo enamorado de la hija del Sultán, la princesa Jasmine. Para intentar conquistarla, acepta el desafío de Jafar, que consiste en entrar a una cueva en mitad del desierto para dar con una lámpara mágica que le concederá todos sus deseos. Allí es donde Aladdín conocerá al Genio, dando inicio a una aventura como nunca antes había imaginado.",
    anio: "2019",
    duracion: "2h 07min",
    calificacion: "72%",
    genero: "Aventura • Disney • Comedia • Fantasia",
    director: "Guy Ritchie, Ritchie y John August",
    reparto: "Will Smith, Mena Massoud, Naomi Scott",
    estreno: "24/03/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "aladdin",
        titulo: "AladdÍn",
        imagen: "https://image.tmdb.org/t/p/w300/oakAd8syy7jNQ4ZoaAGCQkTqcOV.jpg"
      },
      {
        id: "aladdin_2",
        titulo: "AladdÍn 2: El retorno de Jafar",
        imagen: "https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg"
      },
      {
        id: "aladdin_3",
        titulo: "Aladdín 3: El rey de los ladrones",
        imagen: "https://image.tmdb.org/t/p/w300/icinYia0UBPDby1bxak4B44ntK3.jpg"
      },
      {
        id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
        titulo: "Bob Esponja 4: En busca de los pantalones Cuadrados",
        imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg"
      },
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man: Cruzando el multiverso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "Intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      }
    ]
  },

  alarum_codigo_letal: {
    id: "alarum_codigo_letal",
    titulo: "Alarum: Código letal",
    video: "https://dl.dropbox.com/scl/fi/zgdjsgfexumfj01xfvhz0/Alarum.2025.1080p-dual-lat-cinecalidad.ro.mp4?rlkey=eyioe1rx3gjw80ra8l294i1yf&st=",
    poster: "https://image.tmdb.org/t/p/w780/9A97itZIjT5wozcRLAHusnXYsr5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un matrimonio de espías, en el punto de mira de una red de inteligencia internacional, no se detendrá ante nada para obtener un activo crítico. Joe y Lara son agentes que viven fuera de la red y cuyo tranquilo retiro en una estación invernal salta por los aires cuando miembros de la vieja guardia sospechan que ambos pueden haberse unido a un equipo de élite de espías deshonestos, conocido como Alarum.",
    anio: "2025",
    duracion: "1h 35min",
    calificacion: "70%",
    genero: "Accion • Crimen • Suspenso",
    director: "Michael Polish",
    reparto: "Willa Fitzgerald, Sylvester Stallone, Ísis Valverde",
    estreno: "17/01/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tierra_baja",
        titulo: "Tierra baja",
        imagen: "https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"
      },
      {
        id: "la_evolucion",
        titulo: "La evaluación",
        imagen: "https://image.tmdb.org/t/p/w300/rCGwGWI4a2EaNQCyTe4vDfoiMtk.jpg"
      },
      {
        id: "la_fuente_de_la_eterna_juventud",
        titulo: "La fuente de la eterna juventud",
        imagen: "https://image.tmdb.org/t/p/w300/nJ9qnZLhmj6wD3NgOe6lKoXJQMx.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "detonantes",
        titulo: "Detonantes",
        imagen: "https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg"
      },
      {
        id: "la_bala_perdida_3",
        titulo: "La bala perdida 3",
        imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"
      }
    ]
  },

  annabelle_2014: {
    id: "annabelle_2014",
    titulo: "Annabelle",
    video: "https://dl.dropbox.com/scl/fi/c4y8vmzvwttrw9y51zvz8/Annabelle.2014.1080P-Dual-Lat.mp4?rlkey=igq4az6ke0jtnu22suc5uyt51&st=",
    poster: "https://image.tmdb.org/t/p/w780/pWZ0srAfPx4XyJMlFkKBlmYfx3C.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una pareja comienza a experimentar sucesos sobrenaturales aterradores relacionados con una muñeca antigua poco después de que su casa sea invadida por miembros de una secta satánica.",
    anio: "2014",
    duracion: "1h 38min",
    calificacion: "00%",
    genero: "Terror",
    director: "Juan R. Leonetti",
    reparto: "Annabelle Wallis, Ward Horton, Tony Amendola",
    estreno: "23/10/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "annabelle_2",
        titulo: "Annabelle 2: La creación",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 3: Vuelve a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3ZZB2UHGK2iqj4XYgmivkeCgGJn.jpg"
      },
      {
        id: "el_conjuro",
        titulo: "El Conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "el_conjuro_2",
        titulo: "El Conjuro 2: El caso Enfield",
        imagen: "https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El Conjuro 3: El Diablo Me Obligo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "la_monja",
        titulo: "La Monja",
        imagen: "https://image.tmdb.org/t/p/w300/7fxjwtEvqI1BYkXEbGqJ3dQBgXD.jpg"
      }
      
    ]
  },

  annabelle_2: {
    id: "annabelle_2",
    titulo: "Annabelle 2: La creación",
    video: "https://dl.dropbox.com/scl/fi/qse92rnp9macloj3kqcmu/Annabelle.2.creation.2017.1080P-Dual-Lat.mp4?rlkey=21izro9wmv54cht86t5md0rbd&st=",
    poster: "https://image.tmdb.org/t/p/w780/o8u0NyEigCEaZHBdCYTRfXR8U4i.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Varios años después del trágico fallecimiento de su hija, un juguetero que crea muñecas y su mujer, acogen en su casa a una monja enfermera y a un grupo de niñas, tratando de convertir su casa en un acogedor orfanato. Sin embargo, las nuevos inquilinos se convertirán en el objetivo de Annabelle, una muñeca poseída por un ser demoníaco.",
    anio: "2017",
    duracion: "1h 49min",
    calificacion: "77%",
    genero: "Terror",
    director: "David F. Sandberg",
    reparto: "Stephanie Sigman, Talitha Eliana Bateman, Lulu Wilson",
    estreno: "11/08/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "annabelle_2014",
        titulo: "Annabelle",
        imagen: "https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 3: Vuelve a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3ZZB2UHGK2iqj4XYgmivkeCgGJn.jpg"
      },
      {
        id: "el_conjuro",
        titulo: "El Conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "el_conjuro_2",
        titulo: "El Conjuro 2: El caso Enfield",
        imagen: "https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El Conjuro 3: El Diablo Me Obligo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "ofrenda_al_demonio",
        titulo: "Ofrenda al demonio",
        imagen: "https://image.tmdb.org/t/p/w300/tbaTFgGIaTL1Uhd0SMob6Dhi5cK.jpg"
      }
    ]
  },

  annabelle_3: {
    id: "annabelle_3",
    titulo: "Annabelle 3: Vuelve a casa",
    video: "https://dl.dropbox.com/scl/fi/phy3ip3ioojlg7vcgd5gy/Annabelle.comes.home.2019.1080P-Dual-Lat.mp4?rlkey=geo7jbrydvuek4x8cyr2m5st4&st=",
    poster: "https://image.tmdb.org/t/p/w780/jB98SrdXAYSbiprjIwc7WfVCuCV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/3ZZB2UHGK2iqj4XYgmivkeCgGJn.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Annabelle Vuelve a Casa es la tercera entrega de la saga Annabelle de New Line Cinema, protagonizada por la infame y siniestra muñeca del universo Expediente Warren. Los demonólogos Ed y Lorraine Warren están decididos a evitar que Annabelle cause más estragos, así que llevan a la muñeca poseída a la sala de objetos bajo llave que tienen en su casa. La colocan a salvo en una vitrina sagrada bendecida por un sacerdote. Pero una terrorífica noche nada santa, Annabelle despierta a los espíritus malignos de la habitación que se fijan un nuevo objetivo: la hija de diez años de los Warren, Judy, y sus amigas.",
    anio: "2019",
    duracion: "1h 46min",
    calificacion: "80%",
    genero: "Terror",
    director: "Gary Dauberman",
    reparto: "McKenna Grace, Patrick Wilson, Vera Farmiga",
    estreno: "26/06/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "annabelle_2",
        titulo: "Annabelle",
        imagen: "https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 2: La creacion",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "argen_1978_a",
        titulo: "1978",
        imagen: "https://image.tmdb.org/t/p/w300/iyKixwGhGRas1ppAih8E7SG5QDZ.jpg"
      },
      {
        id: "presencia",
        titulo: "Presencia",
        imagen: "https://image.tmdb.org/t/p/w300/8mRO5AdZ4Rn1crgjTHaUnWWhJXB.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"
      },
      {
        id: "eliminar_amigo",
        titulo: "Eliminar amigo",
        imagen: "https://image.tmdb.org/t/p/w300/pzxHNiKjHL8Sz7DZ7POXXqohxet.jpg"
      }
    ]
  },

  anora: {
    id: "anora",
    titulo: "Anora",
    video: "https://dl.dropbox.com/scl/fi/yitxkxrgh7e3q4v81q9gn/Anora-2024.mp4?rlkey=l6ihh94ritdypkhzdtz9s7w2e&st=",
    poster: "https://image.tmdb.org/t/p/w780/kEYWal656zP5Q2Tohm91aw6orlT.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Anora, una joven prostituta de Brooklyn, tiene la oportunidad de vivir una historia de Cenicienta cuando conoce e impulsivamente se casa con el hijo de un oligarca. Cuando la noticia llega a Rusia, su cuento de hadas se ve amenazado, ya que los padres parten hacia Nueva York para intentar conseguir la anulación del matrimonio.",
    anio: "2024",
    duracion: "2h 19min",
    calificacion: "70%",
    genero: "Romance • Drama",
    director: "Sean Baker",
    reparto: "Mikey Madison, Mark Eydelshteyn, Yura Borisov",
    estreno: "18/10/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        href: "../View Series/IT Bienvenido a Derry (2025).html",
        titulo: "IT: Bienvenidos a Derry",
        imagen: "https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg"
      },
      {
        id: "desafiante_rivales",
        titulo: "Desafiante Rivales",
        imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseo prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Dias",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "cincuentas_sombras_de_grey_1",
        titulo: "Cincuenta sombras de Grey",
        imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      }
    ]
  },

  almas_marcadas: {
    id: "almas_marcadas",
    titulo: "Almas marcadas: Rule + Shaw",
    video: "https://dl.dropbox.com/scl/fi/nn8zmke4u409kc51h4d1f/Almas-marcadas-2025.mp4?rlkey=lx0qms8ss6dcpzza469tepksx&st=",
    poster: "https://image.tmdb.org/t/p/w780/uEFgYNggglIgno71h73W1oJAiQG.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Shaw Landon ha amado a Rule Archer desde el primer momento en que lo vio. Rule, un tatuador rebelde y de carácter irascible, no tiene tiempo para una buena estudiante de medicina como Shaw, aunque sea la única que lo ve como realmente es. Ella vive según las reglas de los demás; él crea las suyas. Pero una falda corta, demasiados cócteles de cumpleaños y secretos revelados conducen a una noche que ninguno de los dos podrá olvidar. Ahora, Shaw y Rule deben descubrir cómo una chica como ella y un chico como él pueden estar juntos sin destruir su amor... ni a sí mismos.",
    anio: "2025",
    duracion: "1h 33min",
    calificacion: "88%",
    genero: "Romance • Drama",
    director: "Nick Cassavetes",
    reparto: "Sydney Taylor, Chase Stokes, Alexander Ludwig",
    estreno: "30/03/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"
      },
      {
        id: "dias_365_3",
        titulo: "365 Dias 3: Mas",
        imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg"
      },
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "cincuentas_sombras_de_grey_1",
        titulo: "Cincuenta sombras de Grey",
        imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg"
      },
      {
        id: "desafiante_rivales",
        titulo: "Desafiante Rivales",
        imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      }
    ]
  },


  alvin_y_las_ardillas: {
    id: "alvin_y_las_ardillas",
    titulo: "Alvin y las ardillas",
    video: "https://dl.dropbox.com/scl/fi/w5pw3kypg73z37ug5v1xh/Alvin-y-las-ardillas.mp4?rlkey=d6x3vsrhw5tytdl1oy9a41cgo&st=",
    poster: "https://image.tmdb.org/t/p/w780/1Y0ObS013fFZiwy1kihsh3fDHJl.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La vida de Dave Seville, un compositor sin éxito, es monótona y frustrante, hasta que encuentra con tres ardillas (Alvin, Simon y Theodore) que vienen del bosque. Dave las expulsa de su casa al no encontrar natural que las ardillas hablen, pero cambia de opinión cuando las oye cantar y los invita a cantar sus letras. Dave acude a su discográfica para mostrar las ardillas a su jefe, pero este las engaña para que se queden con él.",
    anio: "2007",
    duracion: "1h 31min",
    calificacion: "73%",
    genero: "Animacion • Musical • Comedia • Familia",
    director: "Tim Hill",
    reparto: "Ross Bagdasarian Jr, David Cross, Janice Karman",
    estreno: "14/12/2007",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "alvil_y_las_ardillas_2",
        titulo: "Alvin y las ardillas 2",
        imagen: "https://image.tmdb.org/t/p/w300/1DqgIFHVJwjlaIITCcYtobrirfd.jpg"
      },
      {
        id: "alvin_y_las_ardillas_3",
        titulo: "Alvin y las ardillas 3",
        imagen: "https://image.tmdb.org/t/p/w300/tVzpyRUZ3LwEZmK7gpvmKVFBqp6.jpg"
      },
      {
        id: "alvin_y_las_ardillas_4",
        titulo: "Alvin y las ardillas 4: Fiesta sobre ruedas",
        imagen: "https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg"
      },
      {
        id: "moana",
        titulo: "Moana",
        imagen: "https://image.tmdb.org/t/p/w300/pwW2sC4ugeFaygOPu6nYCAV3JWG.jpg"
      },
      {
        id: "mi_villano_favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "super_mario_bros",
        titulo: "Super Mario Bros: La película",
        imagen: "https://image.tmdb.org/t/p/w300/qNBAXBIQlnOThrVvA6mA2B5ggV6.jpg"
      }
    ]
  },

  alvin_y_las_ardillas_2: {
    id: "alvin_y_las_ardillas_2",
    titulo: "Alvin y las ardillas 2",
    video: "https://dl.dropbox.com/scl/fi/2y6gswnb8setevnlah2ck/Alvin-y-las-ardillas-2.mp4?rlkey=i4p2hktjij1gzr0f8nkd889qz&st=",
    poster: "https://image.tmdb.org/t/p/w780/3lf6YCItKcF3Wrrs7tp1PL0eyOT.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ye1MoMxdW6imx1BdytGxXYvj4BT.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Las ardillas más famosas y divertidas del cine regresan a la gran pantalla para protagonizar una nueva aventura. En ella, Alvin y sus compañeros deben enfrentarse a la presión de la escuela, a los problemas de la celebridad y a un grupo de chicas ardilla que les está haciendo la competencia.",
    anio: "2009",
    duracion: "1h 28min",
    calificacion: "70%",
    genero: "Animacion • Aventura • Comedia • Musical",
    director: "Betty Thomas",
    reparto: "Justin Long, Zachary Levi, Anna Faris",
    estreno: "23/12/2009",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "alvin_y_las_ardillas",
        titulo: "Alvin y las ardillas",
        imagen: "https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg"
      },
      {
        id: "alvin_y_las_ardillas_3",
        titulo: "Alvin y las ardillas 3",
        imagen: "https://image.tmdb.org/t/p/w300/lg6mc7D7QFB24rKoyQIeCa6En43.jpg"
      },
      {
        id: "alvin_y_las_ardillas_4",
        titulo: "Alvin y las ardillas 4: Fiesta sobre ruedas",
        imagen: "https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "trolls_3",
        titulo: "Trolls 3: Todos juntos",
        imagen: "https://image.tmdb.org/t/p/w300/lxoPJR6eR5nd6nHSKIkEIV4FQWe.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      }
    ]
  },

  alvin_y_las_ardillas_3: {
    id: "alvin_y_las_ardillas_3",
    titulo: "Alvin y las ardillas 3",
    video: "https://dl.dropbox.com/scl/fi/rf3psyxozwy9y0p0f9lql/Alvin-y-las-ardillas-3.mp4?rlkey=0q8eim0a7lnckc25lw86l4co3&st=",
    poster: "https://image.tmdb.org/t/p/w780/6ZcAuv5GBSSLF0LDLVLjTaT3Ucd.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/a52ebjlDqvrjcKtFGDtQgNQLaGH.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tercera entrega de la saga Alvin y las ardillas. Las ardillas se embarcan en un crucero de lujo y acaban en una isla desierta, pero pronto averiguarán que no está tan desierta como parece.",
    anio: "2011",
    duracion: "1h 27min",
    calificacion: "87%",
    genero: "Animacion • Aventura • Comedia • Musical",
    director: "Mike Mitchell",
    reparto: "Jason Lee, Jenny Slate, Jesse McCartney",
    estreno: "05/01/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "alvin_y_las_ardillas",
        titulo: "Alvin y las ardillas",
        imagen: "https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg"
      },
      {
        id: "alvil_y_las_ardillas_2",
        titulo: "Alvin y las ardillas 2",
        imagen: "https://image.tmdb.org/t/p/w300/1DqgIFHVJwjlaIITCcYtobrirfd.jpg"
      },
      {
        id: "alvin_y_las_ardillas_4",
        titulo: "Alvin y las ardillas 4: Fiesta sobre ruedas",
        imagen: "https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "el_gato_con_botas",
        titulo: "El gato con botas",
        imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg"
      },
      {
        id: "garfield_fuera_de_casa",
        titulo: "Garfield: Fuera de casa",
        imagen: "https://image.tmdb.org/t/p/w300/p6AbOJvMQhBmffd0PIv0u8ghWeY.jpg"
      }
    ]
  },

  alvin_y_las_ardillas_4: {
    id: "alvin_y_las_ardillas_4",
    titulo: "Alvin y las ardillas 4: Fiesta sobre ruedas",
    video: "https://dl.dropbox.com/scl/fi/xorbckpaet1vrzcq52nqo/Alvin.and.the.chipmunks.the.road.chip.2015.1080p-dual-lat.mp4?rlkey=81042fmp66s3jd2m9730rbalg&st=",
    poster: "https://image.tmdb.org/t/p/w780/r5uE0qZt7d9lVlNar2MAD7cvxEb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras una serie de malentendidos Alvin, Simon y Theodore piensan que Dave se va a declarar a su nueva novia en Nueva York... y por tanto se olvidará de ellos. Tienen tres días para llegar e intentar romper el compromiso y salvarse así de la pérdida de Dave.",
    anio: "2015",
    duracion: "0h 008min",
    calificacion: "73%",
    genero: "Animacion • Aventura • Comedia • Musical",
    director: "Walt Becker",
    reparto: "Justin Long, Mateo Gray Gubler, Jesse McCartney",
    estreno: "21/01/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "alvin_y_las_ardillas",
        titulo: "Alvin y las ardillas",
        imagen: "https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg"
      },
      {
        id: "alvil_y_las_ardillas_2",
        titulo: "Alvin y las ardillas 2",
        imagen: "https://image.tmdb.org/t/p/w300/1DqgIFHVJwjlaIITCcYtobrirfd.jpg"
      },
      {
        id: "alvin_y_las_ardillas_3",
        titulo: "Alvin y las ardillas 3",
        imagen: "https://image.tmdb.org/t/p/w300/lg6mc7D7QFB24rKoyQIeCa6En43.jpg"
      },
      {
        id: "el_bebe_jefazo",
        titulo: "El bebé jefazo",
        imagen: "https://image.tmdb.org/t/p/w300/dPiXM1aFbJ9XJGPyf5ZULmEjzkR.jpg"
      },
      {
        id: "turbo",
        titulo: "Turbo",
        imagen: "https://image.tmdb.org/t/p/w300/rJPEcuMyjjKd9Tg3mO1K4a9iAi9.jpg"
      },
      {
        id: "madagascar_2",
        titulo: "Madagascar 2",
        imagen: "https://image.tmdb.org/t/p/w300/zYbvSjajQrb2jU9rUo5Mt06stPd.jpg"
      }
    ]
  },

  amateur: {
    id: "amateur",
    titulo: "Amateur",
    video: "https://dl.dropbox.com/scl/fi/uzcwtzbih9bpdyxcvgfvp/The.amateur.2025.1080p-dual-lat-cinecalidad.ro.mp4?rlkey=slid43o4ta4pyxigyniotnv0u&st=",
    poster: "https://image.tmdb.org/t/p/w780/2Hz53Ap0KLULE37BzwvZOBsMosW.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xzM5pMCIyp8jkGtsFBGcPlRhVBc.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras dar un vuelco a su vida al morir su esposa en un atentado terrorista en Londres, un brillante pero introvertido agente de la CIA, experto en descifrado de códigos, decide tomar cartas en el asunto cuando sus superiores se niegan a actuar.",
    anio: "2025",
    duracion: "2h 02min",
    calificacion: "60%",
    genero: "Suspenso • Accion",
    director: "James Hawes",
    reparto: "Rami Malek, McCallany muerto, Rachel Brosnahan",
    estreno: "11/04/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_ladron_de_joyas",
        titulo: "El ladrón de joyas",
        imagen: "https://image.tmdb.org/t/p/w300/hzuus3qrQct2JeoAs2AGMYzKzjZ.jpg"
      },
      {
        id: "estragos",
        titulo: "Estragos",
        imagen: "https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"
      },
      {
        id: "g20",
        titulo: "G20",
        imagen: "https://image.tmdb.org/t/p/w300/xihssRPgRDZ7xwIjx3xuPTnqPfU.jpg"
      },
      {
        id: "k.o",
        titulo: "K.O.",
        imagen: "https://image.tmdb.org/t/p/w300/C4V4XW2igocPP54wqufQKSVQuq.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "la_bala_perdida",
        titulo: "La bala perdida 3",
        imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"
      }
    ]
  },


  anna_nicole_smith: {
    id: "anna_nicole_smith",
    titulo: "Anna Nicole Smith: Tú no me conoces",
    video: "https://dl.dropbox.com/scl/fi/y9jubtkn9hxk5fs1yokrf/Anna-nicole-2023.mp4?rlkey=k8cskscbcw2n2pdfvzomontjy&st=",
    poster: "https://image.tmdb.org/t/p/w780/nIXBaPRix53sq4W2KK8FazrGzc4.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mybL2Hd3PvsY7Qyjf7W6BKsoECu.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Traza un retrato de la vida de Anna Nicole Smith, desde sus años de fama como modelo hasta su trágica muerte, a través de su círculo más cercano.",
    anio: "2023",
    duracion: "1h 57min",
    calificacion: "60%",
    genero: "Documentacion",
    director: "Úrsula Macfarlane",
    reparto: "Anna Nicole Smith, Marilyn Grabowski, Patrik Simpson",
    estreno: "16/03/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "thor",
        titulo: "Thor",
        imagen: "https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg"
      },
      {
        id: "frida",
        titulo: "Frida",
        imagen: "https://image.tmdb.org/t/p/w300/qfPjPZF2AqB2XnXPFcHlYtlQRS3.jpg"
      },
      {
        id: "nahir",
        titulo: "Nahir",
        imagen: "https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"
      },
      {
        id: "nada_que_ver",
        titulo: "Nada que ver",
        imagen: "https://image.tmdb.org/t/p/w300/ofnOwcG9l1DuGl7vB45JHsfSlR6.jpg"
      },
      {
        href: "../View Series/IT Bienvenido a Derry (2025).html",
        titulo: "IT: Bienvenidos a Derry",
        imagen: "https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg"
      },
      {
        id: "me_vuelves_loca",
        titulo: "Me vuelves loca",
        imagen: "https://image.tmdb.org/t/p/w300/pQeyfqLEDdY6x4P4Fl5r6jcstN4.jpg"
      }
    ]
  },

  asesino_serial: {
    id: "asesino_serial",
    titulo: "Asesino serial",
    video: "https://dl.dropbox.com/scl/fi/jgnezwq0w1ddidene6pju/Asesino-serial-2024.mp4?rlkey=ow3ootcly7htvefkkeeky95n1&st=",
    poster: "https://image.tmdb.org/t/p/w780/oBpgi6xMeZyYZwiAqXNE66bdzg8.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una joven tiene una aventura de una noche con un tipo inquietante, comenzando así una persecución de pesadilla.",
    anio: "2024",
    duracion: "1h 36in",
    calificacion: "76%",
    genero: "Crimen • Suspenso • Terror",
    director: "JT Mollner",
    reparto: "Willa Fitzgerald, Kyle Gallner, Madisen Beaty",
    estreno: "23/08/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "Fineskind",
        titulo: "Fineskind: Entre hermanos",
        imagen: "https://image.tmdb.org/t/p/w300/90D6sXfbXKhDpd4S1cHICdAe8VD.jpg"
      },
      {
        id: "el_asesino",
        titulo: "El asesino",
        imagen: "https://image.tmdb.org/t/p/w300/wXbAPrZTqJzlqmmRaUh95DJ5Lv1.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        id: "un_lugar_en_silencio",
        titulo: "Un lugar en silencio",
        imagen: "https://image.tmdb.org/t/p/w300/hE51vC3iZJCqFecLzIO1Q4eYXqK.jpg"
      },
      {
        id: "saw_2",
        titulo: "Saw 2",
        imagen: "https://image.tmdb.org/t/p/w300/4KC1RHtH4asWc44Lu7wguMPNwqu.jpg"
      },
      {
        id: "el_bufon",
        titulo: "El bufón",
        imagen: "https://image.tmdb.org/t/p/w300/6a6PmabZ32a0xIn2TJx4MGKN6Q6.jpg"
      }
    ]
  },

  atrapados_en_lo_profundo: {
    id: "atrapados_en_lo_profundo",
    titulo: "Atrapados en lo profundo",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmar24%2FVer%20Atrapados%20en%20lo%20Profundo%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/pTwYKKUvYeQ2kB3Nkx9Za3OKslr.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fSY6BYUZMObTIzPfRBlhuAb5lsd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Esta película sigue a personajes de orígenes muy diferentes que se juntan cuando el avión en el que viajan se estrella en el Océano Pacífico. Cuando el avión se detiene peligrosamente cerca del borde de un barranco con los pasajeros y la tripulación atrapados en una bolsa de aire, se produce una lucha de pesadilla por la supervivencia en la que el suministro de aire se agota y los peligros se acercan por todos lados.",
    anio: "2024",
    duracion: "1h 30min",
    calificacion: "70%",
    genero: "Terror • Suspenso",
    director: "Claudio Fäh",
    reparto: "Colm Meaney, Phyllis Logan, Sophie McIntosh",
    estreno: "08/11/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "en_las_rpofundidades_del_sena",
        titulo: "En las profundidades del Sena",
        imagen: "https://image.tmdb.org/t/p/w300/3Nr9KwcPMF31BGlOfHXeAJhO2dF.jpg"
      },
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      },
      {
        id: "frente_al_tornado",
        titulo: "Frente al tornado",
        imagen: "https://image.tmdb.org/t/p/w300/7e2BuOfD6jFQm4IPMJWubsFXdUo.jpg"
      },
      {
        id: "lift_el_robo_de_primera",
        titulo: "Lift: El robo de primera",
        imagen: "https://image.tmdb.org/t/p/w300/gma8o1jWa6m0K1iJ9TzHIiFyTtI.jpg"
      },
      {
        id: "dura_de_entrenar",
        titulo: "Duro de entrenar",
        imagen: "https://image.tmdb.org/t/p/w300/eA6FztxHGs43AS6v1TF7PwugEXQ.jpg"
      },
      {
        id: "crater_un_viaje_inolvidable",
        titulo: "Cráter: Un viaje inolvidable",
        imagen: "https://image.tmdb.org/t/p/w300/n8ZpMwYT02XjpQHpSxn1eJw5Zpz.jpg"
      }
    ]
  },

  azrael: {
    id: "azrael",
    titulo: "Azrael",
    video: "https://dl.dropbox.com/scl/fi/v6x1l1mfnvbbmayisj28v/Azrael-2024.mp4?rlkey=8ree1k56mr4v2nm937adbco82&st=",
    poster: "https://image.tmdb.org/t/p/w780/uLqNGzJwnj8JKkKuRM2dHWJKCtc.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/oRZZDhHrxIqvXAuDgQLalm7vlrN.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En un mundo del que nadie habla, una devota persigue a una joven que ha escapado de su encierro. Recapturada por sus despiadados líderes, Azrael debe ser sacrificada para apaciguar un antiguo mal en lo más profundo de las tierras salvajes que la rodean.",
    anio: "2024",
    duracion: "1h 25min",
    calificacion: "74%",
    genero: "Acción • Terror • Suspenso",
    director: "EL Katz",
    reparto: "Samara Weaving, Vic Carmen Sonne, Katariina Unt",
    estreno: "27/02/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cementerio_de_animales_2",
        titulo: "Cementerio de animales 2: Los orígenes",
        imagen: "https://image.tmdb.org/t/p/w300/sbzfFLgExjl7ekLeNFEZ9EwOA9V.jpg"
      },
      {
        id: "el_mono",
        titulo: "El mono",
        imagen: "https://image.tmdb.org/t/p/w300/z15wy8YqFG8aCAkDQJKR63nxSmd.jpg"
      },
      {
        id: "en_las_profundidades_del_sena",
        titulo: "En las profundidades del Sena",
        imagen: "https://image.tmdb.org/t/p/w300/3Nr9KwcPMF31BGlOfHXeAJhO2dF.jpg"
      },
      {
        id: "el_exorcista_creyente",
        titulo: "El exorcista creyentes",
        imagen: "https://image.tmdb.org/t/p/w300/aNoNB5jWIzqcBqHEYzW232B2ktx.jpg"
      },
      {
        id: "el_bosque_de_los_suicidios",
        titulo: "El bosque de los suicidios",
        imagen: "https://image.tmdb.org/t/p/w300/xrk5IwznK8x5kR2BlBYdu2H5GcI.jpg"
      },
      {
        id: "la_sustancia",
        titulo: "La Sustancia",
        imagen: "https://image.tmdb.org/t/p/w300/cQD1qEnPOKUPHAui0okOLZSgitu.jpg"
      }
    ]
  },


  /*B*/

  babygirl: {
    id: "babygirl",
    titulo: "Babygirl: Deseo prohibido",
    video: "https://dl.dropbox.com/scl/fi/6hpdz8xib7m9unzcxpnh5/Babygirl.2024.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=rqhnftmx46x7xpm0lks6l0j1n&st=",
    poster: "https://image.tmdb.org/t/p/w780/1KVV92Wns3NaNEHNcK6uZIyK0fT.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una alta ejecutiva pone en peligro su carrera y su familia cuando inicia un tórrido romance con su becario, mucho más joven que ella.",
    anio: "2024",
    duracion: "1h 54min",
    calificacion: "77%",
    genero: "Romance",
    director: "Halina Reijn",
    reparto: "Nicole Kidman, Harris Dickinson, Antonio Banderas",
    estreno: "25/12/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"
      },
      {
        id: "dias_365_2",
        titulo: "365 Dias 2: Aquel día",
        imagen: "https://image.tmdb.org/t/p/w300/jBpqADo9XAKaecvI3f0J4hRAEyO.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa Mia: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "desafiante_rivales",
        titulo: "Desafiante Rivales",
        imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "salve_maria",
        titulo: "Salve maria",
        imagen: "https://image.tmdb.org/t/p/w300/c1vxdtbIyKE31mX9znwIsrHJ30S.jpg"
      }
    ]
  },

  bad_boys_1: {
    id: "bad_boys_1",
    titulo: "Bad boys: Dos policías rebeldes",
    video: "https://dl.dropbox.com/scl/fi/m5lmqp9v3m0h2os3lojy6/Dos-policias-rebeldes-1.mp4?rlkey=k31jddh6tc1yik1hv6vyin7cv&st=",
    poster: "https://image.tmdb.org/t/p/w780/fgKEdhRw9IgI9KGjLe4w5ghIjA3.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un alijo de heroína valorada en unos 100 millones de dólares es robado del mismísimo depósito de la policía. El caso le será asignado a los agentes Burnett y Lowery, una pareja muy peculiar por los métodos que utilizan. La única pista que tienen para comenzar es la de un testigo que les ayudará a identificar a los atracadores y a la que tendrán que proteger.",
    anio: "1995",
    duracion: "1h 58min",
    calificacion: "68%",
    genero: "Accion • Comedia • Crimen",
    director: "Michael Bay",
    reparto: "Will Smith, Martín Lawrence, Téa Leoni",
    estreno: "21/07/1995",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bad_boys_2",
        titulo: "Bad boys 2: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/qyHDZB87UQF9cu6uuQzhhaKGvuo.jpg"
      },
      {
        id: "bad_boys_3",
        titulo: "Bad boys 3: Para siempre",
        imagen: "https://image.tmdb.org/t/p/w300/5XR7Pbo8qdwdpOIsFtWJOEiOJD6.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "mision_imposible_2",
        titulo: "Misión de rescate 2",
        imagen: "https://image.tmdb.org/t/p/w300/szsOY5gX0jV6PHqXgvHNJlos8h9.jpg"
      },
      {
        id: "heroico",
        titulo: "Heroico",
        imagen: "https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"
      },
      {
        id: "rapido_y_furioso_x",
        titulo: "Rapidos y furiosos X",
        imagen: "https://image.tmdb.org/t/p/w300/AcwmKWzrJ9tMPjU8jU9XlEpmsmZ.jpg"
      }
    ]
  },

  bad_boys_2: {
    id: "bad_boys_2",
    titulo: "Bad boys 2: Dos policías rebeldes",
    video: "https://dl.dropbox.com/scl/fi/5z54279hcxx8g8z6oh9ws/Bad.Boys.II.2003.1080P-Dual-Lat.mp4?rlkey=qbrdqfvz4gq9aq5e87bca8mx0&st=",
    poster: "https://image.tmdb.org/t/p/w780/jNGj5kw65X2tpx7ffRsuxunPjrC.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qyHDZB87UQF9cu6uuQzhhaKGvuo.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En esta nueva aventura, los detectives de narcóticos de Miami, Mike Lowrey (Will Smith) y Marcus Burnett (Martin Lawrence), son asignados para formar parte de un equipo de alta tecnología que trata de destapar la trama del diseño de éxtasis en Miami. Pero inconscientemente descubren una conspiración mortal que involucra a un despiadado señor de la droga, Johnny Tapia (Jordi Mollà), que está decidido a expandir su imperio y tomar el control del negocio de la venta de narcóticos en la ciudad matando a cualquiera que se interponga en su camino.",
    anio: "2003",
    duracion: "2h 26min",
    calificacion: "71%",
    genero: "Accion • Crimen • Comedia",
    director: "Michael Bay",
    reparto: "Martin Lawrence, Will Smith, Jordi Mollà",
    estreno: "02/10/2003",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bad_boys_1",
        titulo: "Bad boys: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg"
      },
      {
        id: "bad_boys_3",
        titulo: "Bad boys 3: Para siempre",
        imagen: "https://image.tmdb.org/t/p/w300/5XR7Pbo8qdwdpOIsFtWJOEiOJD6.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "alarum_codigo_letal",
        titulo: "Alarum: Código Letal",
        imagen: "https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "extragos",
        titulo: "Estragos",
        imagen: "https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"
      }
    ]
  },

  bad_boys_3: {
    id: "bad_boys_3",
    titulo: "Bad boys 3: Para siempre",
    video: "https://dl.dropbox.com/scl/fi/01jnh4tk060wehqr6zczc/Bad.boys.for.life.2020.1080P-Dual-Lat.mp4?rlkey=10kibtebmfvbijrgdst9jyv69&st=",
    poster: "https://image.tmdb.org/t/p/w780/iUspPEAjhUUrLYKntTnKupt3eqV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5XR7Pbo8qdwdpOIsFtWJOEiOJD6.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El Departamento de Policía de Miami y su equipo de élite AMMO intentan derribar a Armando Armas, jefe de un cartel de la droga. Armando es un asesino de sangre fría con una naturaleza viciosa y contaminante. Él está comprometido con el trabajo del cartel y es enviado por su madre Isabel, para matar a Mike.",
    anio: "2020",
    duracion: "1h 03min",
    calificacion: "71%",
    genero: "Accion • Crimen • Comedia",
    director: "Adil El Arbi",
    reparto: "Will Smith, Martín Lawrence, Vanessa Hudgens",
    estreno: "23/01/2020",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bad_boys_1",
        titulo: "Bad boys: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg"
      },
      {
        id: "bad_boys_2",
        titulo: "Bad boys 2: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/qyHDZB87UQF9cu6uuQzhhaKGvuo.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "duro_de_entrenar",
        titulo: "Duro de entrenar",
        imagen: "https://image.tmdb.org/t/p/w300/eA6FztxHGs43AS6v1TF7PwugEXQ.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "detonantes",
        titulo: "Detonantes",
        imagen: "https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg"
      }
    ]
  },

  bad_boys_4: {
    id: "bad_boys_4",
    titulo: "Bad boys 4: Hasta la muerte",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/tncbMvfV0V07UZozXdBEq4Wu9HH.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de que su difunto excapitán es incriminado, Lowrey y Burnett intentan limpiar su nombre, solo para terminar huyendo ellos mismos.",
    anio: "2024",
    duracion: "0h 008min",
    calificacion: "73%",
    genero: "Accion • Crimen • Comedia",
    director: "Adil El Arbi y Bilall Fallah",
    reparto: "Will Smith, Martin Lawrence, Vanessa Hudgens",
    estreno: "07/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bad_boys_1",
        titulo: "Bad boys: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg"
      },
      {
        id: "bad_boys_2",
        titulo: "Bad boys 2: Dos policías rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/qyHDZB87UQF9cu6uuQzhhaKGvuo.jpg"
      },
      {
        id: "bad_boys_3",
        titulo: "Bad boys 3: Para siempre",
        imagen: "https://image.tmdb.org/t/p/w300/5XR7Pbo8qdwdpOIsFtWJOEiOJD6.jpg"
      },
      {
        id: "secenta_minutos",
        titulo: "60 Minutos",
        imagen: "https://image.tmdb.org/t/p/w300/cND79ZWPFINDtkA8uwmQo1gnPPE.jpg"
      },
      {
        id: "frente_al_tornado",
        titulo: "Frente al tornado",
        imagen: "https://image.tmdb.org/t/p/w300/7e2BuOfD6jFQm4IPMJWubsFXdUo.jpg"
      },
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      }
    ]
  },

  baghead_contacto_con_la_muerte: {
    id: "baghead_contacto_con_la_muerte",
    titulo: "Baghead: Contacto con la muerte",
    video: "https://dl.dropbox.com/scl/fi/ur1xcyku3dvhy8yp0ujfd/Baghead.Contacto.Con.La.Muerte.2024.1080P-Dual-Lat.mp4?rlkey=gi7kxrnnupjmsye0r8ap2a789&st=",
    poster: "https://image.tmdb.org/t/p/w780/lVJVLe4EZ1hOKPUWgsZVebCnr0C.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5ssaCHmqvTZDVZtcNhNZTzfb7Nj.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El terror ataca cuando un hombre afligido busca la ayuda de una bruja que cambia de forma para comunicarse con los muertos. Largometraje basado en el corto del mismo nombre (2017).",
    anio: "2024",
    duracion: "1h 34min",
    calificacion: "77%",
    genero: "Terror • Misterio",
    director: "Alberto Corredor",
    reparto: "Freya Allan, Jeremy Irvine, Ruby Barker",
    estreno: "08/02/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "la_primera_profecia",
        titulo: "La primera profecia",
        imagen: "https://image.tmdb.org/t/p/w300/kJkrr39cjRcfz3jR6XcGa8wSkyl.jpg"
      },
      {
        id: "",
        titulo: "Evil dead: El despertar",
        imagen: "https://image.tmdb.org/t/p/w300/uwF8bBauJob5TISQ1cMHoVgIdWD.jpg"
      },
      {
        id: "annabelle_2",
        titulo: "Annabelle 2: La creación",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "hablame",
        titulo: "Hablame",
        imagen: "https://image.tmdb.org/t/p/w300/rS8fjd6dYcf64v3ZhAE6fKrxoaF.jpg"
      },
      {
        id: "la_llorona",
        titulo: "La Llorona",
        imagen: "https://image.tmdb.org/t/p/w300/kIV24gIEbbcP7Fa1cWkp1Wf1YPy.jpg"
      },
      {
        id: "tarot",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/sS9ePS66VV2HMUPk4pyA5qAyhII.jpg"
      }
    ]
  },

  baki_hanma_vs_kengan_ashura: {
    id: "baki_hanma_vs_kengan_ashura",
    titulo: "Baki Hanma VS Kengan Ashura",
    video: "https://dl.dropbox.com/scl/fi/3vsv00d68ltyugcdg29m8/Baki.hanma.vs.kengan.ashura.2024.1080p-dual-lat-cinecalidad.re.mp4?rlkey=p01e89n8nmpmzf6ktgaqvspdb&st=",
    poster: "https://image.tmdb.org/t/p/w780/zNueX4mKKQlBqHRmeSziGCHmbiz.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "¡Llega el combate definitivo! Los luchadores más fieros de ‘Baki Hanma’ y ‘Kengan Ashura’ se enfrentan en este «crossover» de artes marciales sin precedentes.",
    anio: "2024",
    duracion: "1h 02min",
    calificacion: "80%",
    genero: "Animacion • Anime • Peleas • Fantasia",
    director: "Toshiki Hirano",
    reparto: "Nobunaga Shimazaki, Tatsuhisa Suzuki",
    estreno: "06/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        href: "Baki Hanma (2021).html",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/x145FSI9xJ6UbkxfabUsY2SFbu3.jpg"
      },
      {
        href: "Baki (2018).html",
        titulo: "Baki",
        imagen: "https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg"
      },
      {
        href: "Kengan Ashura (2019).html",
        titulo: "Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/bjKEi6zstDdSdMRfTmjof7bT6TP.jpg"
      },
      {
        id: "../View Series/Baki-Dou El samurái invencible (2026).php",
        titulo: "Baki-Dou: El samurái invencible",
        imagen: "https://imgs.search.brave.com/wL_zD4jMSbWlQUzFzIXV4WnXFsX6aYf9kV_1d1xiM74/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9zdGF0/aWMud2lraWEubm9j/b29raWUubmV0L3dp/a2ktZG9ibGFqZS1l/c3BhbmEvaW1hZ2Vz/LzkvOWUvQmFraS1E/b3VfLV9FbF9TYW11/ciVDMyVBMWlfSW52/ZW5jaWJsZV8tX1Bv/c3Rlci5qcGcvcmV2/aXNpb24vbGF0ZXN0/L3NjYWxlLXRvLXdp/ZHRoLWRvd24vMjY4/P2NiPTIwMjYwMjA0/MjAyNTM3JnBhdGgt/cHJlZml4PWVz"
      },
      {
        id: "steven_universe_la_pelicula",
        titulo: "Steven Universe: La película",
        imagen: "https://image.tmdb.org/t/p/w300/TiMOBlMWgdBNcJCVbCIjkp2ShA.jpg"
      },
      {
        id: "los_siete_pecados_capitales_el_rencor_1",
        titulo: "Los siete pecados capitales: El rencor de Edimburgo",
        imagen: "https://image.tmdb.org/t/p/w300/VWKjOfMDisBDPJy1Dj5wxYLYTp.jpg"
      }
    ]
  },

  bala_perdida_1: {
    id: "bala_perdida_1",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  bala_perdida_2: {
    id: "bala_perdida_2",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  bala_perdida_3: {
    id: "bala_perdida_3",
    titulo: "La bala perdida 3",
    video: "https://dl.dropbox.com/scl/fi/3opokcax71x7t3kxp3pp5/Last.bullet.2025.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=ceamibbik4a8kamwbodf8wjfj&st=",
    poster: "https://image.tmdb.org/t/p/w780/1ikqGTVjXA9wkDsESVVzpLP8H1r.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Lino, que acaba de salir de la cárcel, solo tiene un objetivo: vengar a Charas. Acompañado de Julia, se lanza tras Areski, quien ha vuelto a Francia sin saber que el comandante Resz y su equipo también lo acechan. Lo que sigue es una persecución frenética, con alianzas que penden de un hilo y enfrentamientos sin tregua. En este nuevo capítulo, viejos enemigos deben unir fuerzas contra un adversario común para sobrevivir y descubrir la verdad.",
    anio: "2025",
    duracion: "1h 52min",
    calificacion: "66%",
    genero: "Accion • Crimen • Suspenso",
    director: "Guillaume Pierret",
    reparto: "Alban Lenoir, Stéfi Celma, Nicolás Duvauchelle",
    estreno: "07/03/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bala_perdida_1",
        titulo: "La bala perdida",
        imagen: "https://image.tmdb.org/t/p/w300/4F2lHozzpR6kzsKJluUidDsNfbY.jpg"
      },
      {
        id: "bala_perdida_2",
        titulo: "La bala perdida 2",
        imagen: "https://image.tmdb.org/t/p/w300/p6HNFpXiXIdyMRJTrfkgaPkFCK.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "mision_imposible_2",
        titulo: "Misión de rescate 2",
        imagen: "https://image.tmdb.org/t/p/w300/szsOY5gX0jV6PHqXgvHNJlos8h9.jpg"
      },
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera del mapa",
        imagen: "https://image.tmdb.org/t/p/w300/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "The beekeeper: Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      }
    ]
  },

  bambi_una_vida_en_el_bosque_2024: {
    id: "bambi_una_vida_en_el_bosque_2024",
    titulo: "Bambi: Una vida en el bosque",
    video: "https://dl.dropbox.com/scl/fi/anu1e7jfdqhdx6x1ix7y9/Bambi-una-aventura-2025.mp4?rlkey=09n31drcwgszldyjnyrt9z7ep&st=",
    poster: "https://image.tmdb.org/t/p/w780/1W1kMLnK34XRuUj1rhYHv7ewkwT.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fvtIXQH4JcifptPe0J9GfLDIOAQ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Adaptación del libro “Bambi, la historia de una vida en el bosque” de Felix Salten, la película cuenta las aventuras de un joven cervatillo, rodeado de su madre y de los animales del bosque: su amigo el cuervo, el conejo, el mapache... Descubre el mundo de los árboles y sus secretos. Cada día, su madre lo educa para que pueda crecer fuerte. Pero cuando llega el otoño, Bambi se aventura a la intemperie cuando los cazadores lo separan para siempre de su madre.",
    anio: "2024",
    duracion: "1h 18min",
    calificacion: "60%",
    genero: "Disney • Aventura • Documental",
    director: "Michel Fessler",
    reparto: "Mylène Farmer, Senta Berger, Arja Koriseva",
    estreno: "08/03/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bambi",
        titulo: "Bambi",
        imagen: "https://image.tmdb.org/t/p/w300/q9LI5Uloz1WRqaJjr8Tq2aOeSeH.jpg"
      },
      {
        id: "bambi_2",
        titulo: "Bambi 2: El príncipe del bosque",
        imagen: "https://image.tmdb.org/t/p/w300/hquwtrcEZTmupcIsds64y4ZPqMe.jpg"
      },
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      },
      {
        id: "dumbo",
        titulo: "Dumbo",
        imagen: "https://image.tmdb.org/t/p/w300/4x9FmvdJ464Fg7A9XcbYSmxfVw3.jpg"
      },
      {
        id: "encanto",
        titulo: "Encanto",
        imagen: "https://image.tmdb.org/t/p/w300/lH8CLypeehddHZt172TzUGWutH8.jpg"
      },
      {
        id: "el_rey_leon_2019",
        titulo: "El Rey León",
        imagen: "https://image.tmdb.org/t/p/w300/yysmQpv26DdP79XtR3zsL3nVFbN.jpg"
      }
    ]
  },


  banger: {
    id: "banger",
    titulo: "Banger",
    video: "https://dl.dropbox.com/scl/fi/n0q75e4dxoa854xfvx6km/Banger-2025.mp4?rlkey=ouwqvwfj1byrzvkql6tu16ntl&st=",
    poster: "https://image.tmdb.org/t/p/w780/oDvBfDVgF6uthIPdRfC6zqqbvcE.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/x2pegSby27ebOwW361GJb1aKcxa.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando la policía le contrata para desmantelar una peculiar banda de delincuentes vinculada a su rival, un DJ en horas bajas ve la ocasión de volver a lo más alto con un temazo.",
    anio: "2025",
    duracion: "1h 31min",
    calificacion: "65%",
    genero: "Musical • Comedia",
    director: "So-Me",
    reparto: "Vincent Cassel, Yvick Letexier, Laura Felpin",
    estreno: "02/04/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cato",
        titulo: "CATO",
        imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"
      },
      {
        id: "duki",
        titulo: "Rockstar: DUKI del fin del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/9CSTzX1pUrNLD7lsJ8h9hRFJtLQ.jpg"
      },
      {
        id: "la_primera_profecia",
        titulo: "La primera profecia",
        imagen: "https://image.tmdb.org/t/p/w300/kJkrr39cjRcfz3jR6XcGa8wSkyl.jpg"
      },
      {
        id: "G20",
        titulo: "G20",
        imagen: "https://image.tmdb.org/t/p/w300/xihssRPgRDZ7xwIjx3xuPTnqPfU.jpg"
      },
      {
        id: "guerra_mundial_z",
        titulo: "Guerra mundial z",
        imagen: "https://image.tmdb.org/t/p/w300/9Sd2zBbi8hlcc6p6hGV3Qfj39jl.jpg"
      },
      {
        id: "karol_g",
        titulo: "Karol G: Mañana fue muy bonito",
        imagen: "https://image.tmdb.org/t/p/w300/5aXoQYwaQ7JJVUWclHAEXJgiS2M.jpg"
      }
    ]
  },

  barbie: {
    id: "barbie",
    titulo: "Barbie",
    video: "https://dl.dropbox.com/scl/fi/fz60p7je69ecv4b067zp9/Barbie.2023.1080p-dual-lat-cinecalidad.re.mp4?rlkey=0s292tnq711cls7ecm7bua7l4&st=",
    poster: "https://image.tmdb.org/t/p/w780/nHf61UzkfFno5X1ofIhugCPus2R.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Barbie vive en Barbieland donde todo es ideal y lleno de música y color. Un buen día decide conocer el mundo real. Cuando el CEO de Mattel se entere, tratará de evitarlo a toda costa y devolver a Barbie a una caja.",
    anio: "2023",
    duracion: "1h 54min",
    calificacion: "70%",
    genero: "Musical • Comedia • Aventura",
    director: "Greta Gerwig<",
    reparto: "Margot Robbie, Ryan Gosling, América Ferrera",
    estreno: "21/07/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "todo_bien",
        titulo: "¿Todo bien?",
        imagen: "https://image.tmdb.org/t/p/w300/arVt18It7zOpOa2WZTzMiBxmyrY.jpg"
      },
      {
        id: "salve_maria",
        titulo: "Salve maria",
        imagen: "https://image.tmdb.org/t/p/w300/c1vxdtbIyKE31mX9znwIsrHJ30S.jpg"
      },
      {
        id: "rehen",
        titulo: "iRehén!",
        imagen: "https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"
      },
      {
        id: "nahir",
        titulo: "Nahir",
        imagen: "https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"
      },
      {
        id: "desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      },
      {
        id: "donde_esta_el_fantasma",
        titulo: "¿Donde esta el fantasma?",
        imagen: "https://image.tmdb.org/t/p/w300/pAVGfrADDvKMgoZnJLSCiLBCCiG.jpg"
      }
    ]
  },

  sentencia_de_muerte: {
    id: "sentencia_de_muerte",
    titulo: "Sentencia de muerte",
    video: "https://dl.dropbox.com/scl/fi/iwe0m4idth0uvdpybb5r2/The.beekeeper.2024.1080p-dual-lat-cinecalidad.re.mp4?rlkey=2i1slz9vebdlhq0re9oh99u8m&st",
    poster: "https://image.tmdb.org/t/p/w780/f0ACHVpV707zqu4etZrXnWNdSgL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La brutal campaña de venganza de Adam Clay adquiere tintes nacionales tras revelarse que es un antiguo agente de una poderosa organización clandestina conocida como Beekeeper.",
    anio: "2024",
    duracion: "1h 45min",
    calificacion: "75%",
    genero: "Accion • Crimen • Suspenso",
    director: "David Ayer",
    reparto: "Jason Statham, Emmey Raver- Lampman, Josh Hutcherson",
    estreno: "12/01/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "la_bala_perdida_3",
        titulo: "La bala perdida 3",
        imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"
      },
      {
        id: "duro_de_entrenar",
        titulo: "Duro de entrenar",
        imagen: "https://image.tmdb.org/t/p/w300/eA6FztxHGs43AS6v1TF7PwugEXQ.jpg"
      },
      {
        id: "el_planeta_de_los_simios_2",
        titulo: "El planeta de los simios 2: Confrontacion",
        imagen: "https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera Del Mapa",
        imagen: "https://image.tmdb.org/t/p/w300/77dlklwA1VJOLCqIhhmkmS39BLH.jpg"
      },
      {
        id: "bad_boys",
        titulo: "Bad boys: Dos policias rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg"
      }
    ]
  },

  belleza_negra: {
    id: "belleza_negra",
    titulo: "Belleza Negra",
    video: "https://dl.dropbox.com/scl/fi/03d5lo8jpc6a52okead3j/Belleza-negra-2020.mp4?rlkey=d35vy5fmgtnqehi0yo4tl87z4&st=",
    poster: "https://image.tmdb.org/t/p/w780/lQAe1hfWYDdYypRVdzTbdg6JYWP.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/d3wE2OAmWsuuE4IOp6i8iSeRYy4.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una adolescente profundamente afligida por haber perdido a sus padres comienza a tener un vínculo muy especial con un caballo que ha sido alejado de su familia.",
    anio: "2020",
    duracion: "1h 48min",
    calificacion: "80%",
    genero: "Drama • Disney",
    director: "Adhlry Avis",
    reparto: "Mackenzie Foy, Kate Winslet, Lain Geln",
    estreno: "18/12/2020",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "Spirit",
        titulo: "Spirit: El corcel indomable",
        imagen: "https://image.tmdb.org/t/p/w300/gNRQdU3KEsYTIl4y9Xte3onUSsx.jpg"
      },
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/mdszPVnIY7cWgbgJ8zbwu1PiU5V.jpg"
      },
      {
        id: "el_maravilloso_mago_de_oz",
        titulo: "El Maravilloso Mago de Oz",
        imagen: "https://image.tmdb.org/t/p/w300/ruMUv9mtcUoiUWoZmLBBTDbn11J.jpg"
      },
      {
        id: "gran_turismo",
        titulo: "Gran Turismo",
        imagen: "https://image.tmdb.org/t/p/w300/51tqzRtKMMZEYUpSYkrUE7v9ehm.jpg"
      }
    ]
  },

  blancanieves: {
    id: "blancanieves",
    titulo: "Blancanieves",
    video: "https://dl.dropbox.com/scl/fi/alva123qrguovepzjw5mx/Blancanieves-2025.mp4?rlkey=xw3lhr9c1t7iuj9h6ue32c98d&st=",
    poster: "https://image.tmdb.org/t/p/w780/tyfO9jHgkhypUFizRVYD0bytPjP.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras la desaparición del benévolo Rey, la Reina Malvada dominó la otrora bella tierra con una vena cruel. La princesa Blancanieves huye del castillo cuando la Reina, celosa de su belleza interior, intenta matarla. En lo profundo del oscuro bosque, se topa con siete enanos mágicos y un joven bandido llamado Jonathan. Juntos, luchan por sobrevivir a la implacable persecución de la Reina y aspiran a recuperar el reino en el proceso.",
    anio: "2025",
    duracion: "1h 48min",
    calificacion: "70%",
    genero: "Animacion • Disney • Fantasia • Familia",
    director: "Marc Webb",
    reparto: "Rachel Zegler, Gal Gadot, Andrew Burnap<",
    estreno: "21/03/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "blancanieves_y_los_siete_enanitos",
        titulo: "Blancanieves y los siete enanitos",
        imagen: "https://image.tmdb.org/t/p/w300/wdA4lphQwywsPcEKj5sgQ9QSR55.jpg"
      },
      {
        id: "bambi_una_vida_en_el_bosque_2024",
        titulo: "Bambi: Una aventura en el bosque",
        imagen: "https://image.tmdb.org/t/p/w300/fvtIXQH4JcifptPe0J9GfLDIOAQ.jpg"
      },
      {
        id: "elemental",
        titulo: "Elemental",
        imagen: "https://image.tmdb.org/t/p/w300/8riWcADI1ekEiBguVB9vkilhiQm.jpg"
      },
      {
        id: "pinocho",
        titulo: "Pinocho",
        imagen: "https://image.tmdb.org/t/p/w300/sAluF7lNc4Mv3qxx1mmOgsfbr0C.jpg"
      },
      {
        id: "wish_el_poder_de_los_deseos",
        titulo: "Wish: El poder de los deseos",
        imagen: "https://image.tmdb.org/t/p/w300/rCCrG4swkxgFZflup56sx6ymk5i.jpg"
      },
      {
        id: "el_gato_con_botas",
        titulo: "El gato con botas",
        imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg"
      }
    ]
  },

  blancanieves_y_los_siete_enanitos: {
    id: "blancanieves_y_los_siete_enanitos",
    titulo: "Blancanieves y los siete enanitos",
    video: "https://dl.dropbox.com/scl/fi/y0n3cnn9kzqc2tj65u3pf/Blanca-nieves-y-los-7-enanitos-1950.mp4?rlkey=d04hcvajbv987mse74lnj2ocv&st=",
    poster: "https://image.tmdb.org/t/p/w780/wmSxNVGZOV1A51Yx6ChDXk3NVVi.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wdA4lphQwywsPcEKj5sgQ9QSR55.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La malvada madrastra de la princesa Blancanieves decide deshacerse de ella porque no puede soportar que la belleza de la joven sea superior a la de ella. Sin embargo, Blancanieves consigue salvarse y se refugia en la cabaña de los siete enanitos. A pesar de todo, su cruel madrastra consigue encontrarla y la envenena con una manzana. Pero la princesa no está muerta, sólo dormida, a la espera de que un príncipe azul la rescate.",
    anio: "1938",
    duracion: "1h 23min",
    calificacion: "84%",
    genero: "Disney • Animación • Fantasía • Familia",
    director: "David Hand",
    reparto: "Adriana Cselotti, Harry Stockwell, Stuart Buchanan",
    estreno: "21/12/1937",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "coco",
        titulo: "Coco",
        imagen: "https://image.tmdb.org/t/p/w300/gGEsBPAijhVUFoiNpgZXqRVWJt2.jpg"
      },
      {
        id: "la_cenicienta",
        titulo: "La Cenicienta",
        imagen: "https://image.tmdb.org/t/p/w300/vqzeSm5Agvio7DahhKXaySUbUUW.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/Vc0KvO7z2OzEbRs6nyZs9xD81s.jpg"
      },
      {
        id: "los_increibles",
        titulo: "Los Increíbles",
        imagen: "https://image.tmdb.org/t/p/w300/al1jusd4T7JPatZlj4BuYkDDOzr.jpg"
      }
    ]
  },


  blue_beetle: {
    id: "blue_beetle",
    titulo: "Blue Beetle",
    video: "https://dl.dropbox.com/scl/fi/3svr8baxgz7ck7rxw79ko/Blue-beeble-2023.mp4?rlkey=065f4qyjyhr7omielpd42btiz&st=",
    poster: "https://image.tmdb.org/t/p/w780/H6j5smdpRqP9a8UnhWp6zfl0SC.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/z5mkvXYNRauSzHdZgxAj6MzrLTY.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un adolescente mexicano encuentra un escarabajo alienígena que le proporciona una armadura superpoderosa.",
    anio: "2023",
    duracion: "2h 07min",
    calificacion: "67%",
    genero: "Acción • Ciencia ficción • Aventura",
    director: "Angel Manuel Soto",
    reparto: "Xolo Maridueña, Bruna Marquezine, Becky G",
    estreno: "18/08/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "los_vengadores_endgame",
        titulo: "Los vengadores: Endgame",
        imagen: "https://image.tmdb.org/t/p/w300/br6krBFpaYmCSglLBWRuhui7tPc.jpg"
      },
      {
        id: "pantera_negra",
        titulo: "Pantera Negra",
        imagen: "https://image.tmdb.org/t/p/w300/qUhjmU8P2OA7AG4IgqXzbwvl4Tq.jpg"
      },
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "deadpool_2",
        titulo: "Deadpool 2",
        imagen: "https://image.tmdb.org/t/p/w300/jA4DpT3ywxfchnTfMBiouBhq9nU.jpg"
      },
      {
        id: "spider_man1",
        titulo: "Spider-Man: Regreso a casa",
        imagen: "https://image.tmdb.org/t/p/w300/81qIJbnS2L0rUAAB55G8CZODpS5.jpg"
      },
      {
        id: "thor",
        titulo: "Thor",
        imagen: "https://image.tmdb.org/t/p/w300/liSKTEK5B9ARmfsho6teomLLBA4.jpg"
      }
    ]
  },

  bob_esponja_1: {
    id: "bob_esponja_1",
    titulo: "Bob Esponja: La película",
    video: "https://dl.dropbox.com/scl/fi/hh1xgnzlbp108s5sidez3/Bob-esponja-2004.mp4?rlkey=jtpfvtdn2c3e6h55tnwxe73wq&st=",
    poster: "https://image.tmdb.org/t/p/w780/6ZnHQKcqQ8nmfP6woZu2v5EtXoW.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/j4Sqs3SKNaJ4chdKXS1qqUlaWyW.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Hay problemas en Bikini Bottom: la corona del Rey Neptuno ha desaparecido y las sospechas recaen en el Sr. Krabs. Junto a Patrick, su mejor amigo, Bob Esponja marcha a la peligrosa Ciudad de Shell para rescatar la corona de Neptuno y salvar al Sr. Krabs.",
    anio: "2004",
    duracion: "1h 27min",
    calificacion: "70%",
    genero: "Animación • Aventura • Familia • Comedia",
    director: "Stephen Hillenburg",
    reparto: "Cliff Burton, Tom Kenny, Rodger Bumpass",
    estreno: "19/11/2004",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bob_esponja_2",
        titulo: "Bob Esponja 2: Un héroe fuera del agua",
        imagen: "https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg"
      },
      {
        id: "bob_esponja_3",
        titulo: "Bob Esponja 3: Un héroe al rescate",
        imagen: "https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg"
      },
       {
        id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
        titulo: "Bob Esponja 4: En busca de los pantalones Cuadrados",
        imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "mi_villano_favorito",
        titulo: "Mi villano favorito",
        imagen: "https://image.tmdb.org/t/p/w300/7ml02WwUzz4jlZJdiEI4ZIYHj1J.jpg"
      }
    ]
  },

  bob_esponja_2: {
    id: "bob_esponja_2",
    titulo: "Bob Esponja 2: Un héroe fuera del agua",
    video: "https://dl.dropbox.com/scl/fi/gk6eyb1i3zrk8gd0jtmt5/The.spongebob.movie.sponge.out.of.water.2015.hd-dual-lat.mp4?rlkey=3xhpm12vhmvh2motyw95dbjca&st=",
    poster: "https://image.tmdb.org/t/p/w780/sqcqFzAj3IPSFprQSLHr5JOBU4m.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El argumento gira en torno a Bob Esponja y sus inseparables amigos del mar, como son Patricio y la ardilla Arenita, y otros personajes de la serie en la que se basa, como Calamardo y Don Cangrejo. En esta ocasión Bob Esponja y cía se embarcan en una aventura en la que deberán encontrar una receta robada, lo que llevará a los personajes de Fondo Bikini hasta nuestra dimensión.",
    anio: "2015",
    duracion: "1h 32min",
    calificacion: "70%",
    genero: "Animación • Comedia • Aventura • Familia",
    director: "Paul Tibbitt",
    reparto: "Tom Kenny, Bill Fagerbakke, Rodger Bumpass",
    estreno: "06/02/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bob_esponja_1",
        titulo: "Bob Esponja: La película",
        imagen: "https://image.tmdb.org/t/p/w300/CtISczftMz6g7kyk5uLxBben8b.jpg"
      },
      {
        id: "bob_esponja_3",
        titulo: "Bob Esponja 3: Un héroe al rescate",
        imagen: "https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg"
      },
       {
        id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
        titulo: "Bob Esponja 4: En busca de los pantalones Cuadrados",
        imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      }
    ]
  },

  bob_esponja_3: {
    id: "bob_esponja_3",
    titulo: "Bob Esponja 3: Un héroe al rescate",
    video: "https://dl.dropbox.com/scl/fi/kqyva7e33se5cmxe8zgme/The.spongebob.movie.sponge.on.the.run.2020.1080p-dual-lat-cinecalidad.is.mp4?rlkey=d7ab7cve2mus26ve8yr4iwupe&st=",
    poster: "https://image.tmdb.org/t/p/w780/wu1uilmhM4TdluKi2ytfz8gidHf.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando desaparece su amigo Gary, Bob Esponja se embarca en una alocada misión con Patricio muy lejos de Fondo de Bikini para rescatarlo.  ",
    anio: "2020",
    duracion: "1h 35min",
    calificacion: "74%",
    genero: "Animación • Aventura • Comedia • Familia",
    director: "Tim Hill",
    reparto: "Tom Kenny, Bill Fagerbakke, Clancy Brown",
    estreno: "14/08/2021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bob_esponja_1",
        titulo: "Bob Esponja: La película",
        imagen: "https://image.tmdb.org/t/p/w300/CtISczftMz6g7kyk5uLxBben8b.jpg"
      },
      {
        id: "bob_esponja_2",
        titulo: "Bob Esponja 2: Un héroe fuera del agua",
        imagen: "https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg"
      },
       {
        id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
        titulo: "Bob Esponja 4: En busca de los pantalones Cuadrados",
        imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      }
    ]
  },

  bob_esponja_4_en_busca_de_los_pantalones_Cuadrados: {
    id: "bob_esponja_4_en_busca_de_los_pantalones_Cuadrados",
    titulo: "Bob Esponja 4: En busca de los Pantalones Cuadrados",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/gbjK8p5S1aLXWCwOoXqr9aWZvqG.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/eAoe5NsdIFstr9Jxbeet5tpgH6r.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Desesperado por ser un tipo grande, Bob Esponja se propone demostrar su valentía al Sr. Cangrejo siguiendo al Holandés Errante, un misterioso pirata fantasma audaz, en una aventura marítima que lo lleva a las profundidades del mar, donde ninguna Esponja ha ido antes.",
    anio: "2025",
    duracion: "0h 008min",
    calificacion: "73%",
    genero: "Animación • Familia • Comedia • Aventura",
    director: "Derek Drymon",
    reparto: "Tom Kenny, Clancy Brown, Rodger Bumpass",
    estreno: "01/01/2026",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "bob_esponja_1",
        titulo: "Bob Esponja: La película",
        imagen: "https://image.tmdb.org/t/p/w300/CtISczftMz6g7kyk5uLxBben8b.jpg"
      },
      {
        id: "bob_esponja_2",
        titulo: "Bob Esponja 2: Un héroe fuera del agua",
        imagen: "https://image.tmdb.org/t/p/w300/z5aphafm6OEcAq4jwOs5Ml9F384.jpg"
      },
       {
        id: "bob_esponja_3",
        titulo: "Bob Esponja 3: Un héroe al rescate",
        imagen: "https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon",
        titulo: "Como entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg"
      }
    ]
  },

  boruto_2015: {
    id: "boruto_2015",
    titulo: "Boruto: La Película",
    video: "https://dl.dropbox.com/scl/fi/m3ii7qi3wogk7etwssiqp/Boruto-Uzumaki-2015.mp4?rlkey=o5bj6l9w6u5k3qyqjsiw49no2&st=",
    poster: "https://image.tmdb.org/t/p/w780/keIqryt6u5qGVThjrzFURMsRTri.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/t9F4Yzi8rZO8Rn55ceyQPAofrI9.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Han pasado algunos años desde el final de la Guerra Shinobi. Naruto Uzumaki es el séptimo Hokage de Konoha, en esta nueva era. Su hijo, Boruto Uzumaki, pronto entrará en los exámenes de Chunin, junto Sarada Uchiha y el misterioso Mitsuki.",
    anio: "2015",
    duracion: "1h 35min",
    calificacion: "87%",
    genero: "Anime • Animación • Fantasía • Aventura • Acción",
    director: "Hiroyuki Yamashita",
    reparto: "Karen Vallejo, Isabel Martiñon, Victor Ugarte",
    estreno: "07/08/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "naruto_the_last",
        titulo: "Naruto The last: La pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/phPfQ4jWhwmZrmPhAtVYUJfqfwG.jpg"
      },
      {
        id: "naruto_shippuden_1",
        titulo: "Naruto Shippuden: La Muerte de Naruto",
        imagen: "https://image.tmdb.org/t/p/w300/mpNOFVmqcqCTxVRN1h0vwIyynyY.jpg"
      },
      {
        id: "naruto_shippuden_2",
        titulo: "Naruto Shippuden 2: Lazos",
        imagen: "https://image.tmdb.org/t/p/w300/bBqEiQbbfyt4MWR3NhDZMbS4Wp8.jpg"
      },
      {
        id: "naruto_shippuden_3",
        titulo: "Naruto Shippuden 3: Los Herederos de la Voluntad de Fuego",
        imagen: "https://image.tmdb.org/t/p/w300/1J4qxwTqHuYzTCTcHtlXKzj0J3x.jpg"
      },
      {
        id: "naruto_shippuden_4",
        titulo: "Naruto Shippuden 4: La torre perdida",
        imagen: "https://image.tmdb.org/t/p/w300/jCDNRKHUEPLPzdilMmh7xX8pMB8.jpg"
      },
      {
        id: "naruto_shippuden_5",
        titulo: "Naruto Shippuden 5: Prisión de Sangre",
        imagen: "https://image.tmdb.org/t/p/w300/iYXuahpWvVwbGTVkc6QK6kRpLRN.jpg"
      }
    ]
  },

  /*C*/

  el_conjuro: {
    id: "el_conjuro",
    titulo: "El Conjuro",
    video: "https://dl.dropbox.com/scl/fi/1z32m2t6koera1vo52cbd/The.Conjuring.2013.1080p-dual-lat.mp4?rlkey=5846lca5ja5sar0tr2b50hb5s&st=",
    poster: "https://image.tmdb.org/t/p/w780/mXndmCbpvlqnD6po0EMfxEZcUSn.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Basada en una historia real documentada por los reputados demonólogos Ed y Lorraine Warren. Narra los encuentros sobrenaturales que vivió la familia Perron en su casa de Rhode Island a principios de los 70. El matrimonio Warren, investigadores de renombre en el mundo de los fenómenos paranormales, acudieron a la llamada de esta familia aterrorizada por la presencia en su granja de un ser maligno.",
    anio: "2013",
    duracion: "1h 52min",
    calificacion: "83.6%",
    genero: "Terror",
    director: "James Wan",
    reparto: "Vera Farmiga, Patrick Wilson, Lili Taylor",
    estreno: "05/09/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_conjuro_2",
        titulo: "El Conjuro 2: El caso Enfield",
        imagen: "https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El conjuro 3: El diablo me obligo hacerlo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 2: La creacion",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "el_conjurp_4",
        titulo: "El conjuro 4: El ultimo rito",
        imagen: "https://image.tmdb.org/t/p/w300/8sSNvHO5Swhk6FKEAH7WANtjtga.jpg"
      },
      {
        id: "baghead_contacto_con_la_muerte",
        titulo: "Baghead: Contacto con la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/5ssaCHmqvTZDVZtcNhNZTzfb7Nj.jpg"
      },
      {
        id: "los_extraños_capitulo_1",
        titulo: "Los extraños: Capitulo uno",
        imagen: "https://image.tmdb.org/t/p/w300/za4jDcPQ5IV4p27UGcC5uEgsNGG.jpg"
      },
    ]
  },

  el_conjuro_2: {
    id: "el_conjuro_2",
    titulo: "El conjuro 2: El caso enfield",
    video: "https://dl.dropbox.com/scl/fi/6gvzq42a2a2nfc3ca0h90/The.conjuring.2.2016.1080P-Dual-Lat.mp4?rlkey=374ee3fe8poyos64nybo5dz87&st=",
    poster: "https://image.tmdb.org/t/p/w780/mFCS5OuhPYT79KXu5jl7RqznPR1.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Secuela de la exitosa 'Expediente Warren' (2013), que lleva de nuevo a la pantalla otro caso real de los expedientes de los renombrados demonólogos Ed y Lorraine Warren. En este caso ambos viajarán al norte de Londres para ayudar a una madre soltera que tiene a su cargo cuatro hijos y que vive sola con ellos en una casa plagada de espíritus malignos.",
    anio: "2016",
    duracion: "2h 14min",
    calificacion: "87%",
    genero: "Terror",
    director: "James Wan",
    reparto: "Vera Farmiga, Patrick Wilson, Madison Wolfe",
    estreno: "09/06/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_conjuro",
        titulo: "El conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El conjuro 3: El diablo me obligo hacerlo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "el_conjurp_4",
        titulo: "El conjuro 4: El ultimo rito",
        imagen: "https://image.tmdb.org/t/p/w300/8sSNvHO5Swhk6FKEAH7WANtjtga.jpg"
      },
      {
        id: "annabeññe_2014",
        titulo: "Annabelle",
        imagen: "https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg"
      },
      {
        id: "la_monja",
        titulo: "La monja",
        imagen: "https://image.tmdb.org/t/p/w300/ce6f0GNhtHdIvtCoXfp3amE0fWz.jpg"
      },
      {
        id: "eliminar_amigo",
        titulo: "Eliminar amigo",
        imagen: "https://image.tmdb.org/t/p/w300/pzxHNiKjHL8Sz7DZ7POXXqohxet.jpg"
      }
    ]
  },

  el_conjuro_3: {
    id: "el_conjuro_3",
    titulo: "El conjuro 3: El diablo me obligo hacerlo",
    video: "https://dl.dropbox.com/scl/fi/iuf0861zknazyjvqjrjlu/The.Conjuring.The.Devil.Made.Me.Do.It.2021.1080P-Dual-Lat.mp4?rlkey=c3pjsl6y9xcfzwv9izf9j75sm&st=",
    poster: "https://image.tmdb.org/t/p/w780/nUMtHNnM4EWQ3Md4NfjJQBCvHos.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Los investigadores paranormales Ed y Lorraine Warren se encuentran con lo que se convertiría en uno de los casos más sensacionales de sus archivos. La lucha por el alma de un niño los lleva más allá de todo lo que habían visto antes, para marcar la primera vez en la historia de los Estados Unidos que un sospechoso de asesinato reclamaría posesión demoníaca como defensa.",
    anio: "2021",
    duracion: "1h 52min",
    calificacion: "82%",
    genero: "Terror",
    director: "Michael Chaves",
    reparto: "Vera Farmiga, Patrick Wilson, Sterling Jerins",
    estreno: "03/06/2021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_conjuro_4",
        titulo: "El conjuro 4: El ultimo rito",
        imagen: "https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg"
      },
      {
        id: "el_conjuro",
        titulo: "El conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "el_conjuro_2",
        titulo: "El conjuro 2: El caso enfield",
        imagen: "https://image.tmdb.org/t/p/w300/x4jUJ0fF60SSOeUUTkaRtmnDvwG.jpg"
      },
      {
        id: "el_exorcista_creyente",
        titulo: "El exorcista creyentes",
        imagen: "https://image.tmdb.org/t/p/w300/aNoNB5jWIzqcBqHEYzW232B2ktx.jpg"
      },
      {
        id: "la_monja_2",
        titulo: "La monja II",
        imagen: "https://image.tmdb.org/t/p/w300/qKq8dflkSBxoBapvfOAFP3LE03q.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 3: Vuelve a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3ZZB2UHGK2iqj4XYgmivkeCgGJn.jpg"
      },
    ]
  },

  el_conjuro_4: {
    id: "nombredepelicula",
    titulo: "El conjuro 4: El ultimo rito",
    video: "https://www.dropbox.com/scl/fi/ims8wuaqas1egdbuxkb07/The.conjuring.last.rites.2025.1080p-dual-lat-cinecalidad.ro.mp4?rlkey=xii0kollzql7x0e4denj7a5ls&st=",
    poster: "https://image.tmdb.org/t/p/w780/fJXqP9S9llRUy9tuccuwvIYFBA4.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Los investigadores de lo paranormal Ed y Lorraine Warren se enfrentan a un último caso aterrador en el que están implicadas entidades misteriosas a las que deben enfrentarse.",
    anio: "2025",
    duracion: "2h 15min",
    calificacion: "00%",
    genero: "Terror",
    director: "Michael Chaves",
    reparto: "Patrick Wilson, Vera Farmiga, Mia Tomlinson",
    estreno: "05/09/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_conjuro",
        titulo: "El conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "el_conjuro_2",
        titulo: "El conjuro 2: El caso enfield",
        imagen: "https://image.tmdb.org/t/p/w300/x4jUJ0fF60SSOeUUTkaRtmnDvwG.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El conjuro 3: El diablo me obligo hacerlo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "la_monja",
        titulo: "La monja",
        imagen: "https://image.tmdb.org/t/p/w300/ce6f0GNhtHdIvtCoXfp3amE0fWz.jpg"
      },
      {
        id: "annabelle_3",
        titulo: "Annabelle 2: La creacion",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "Insidiuos_puerta_roja",
        titulo: "Insidiuos: puerta roja",
        imagen: "https://image.tmdb.org/t/p/w300/wD4eLIHUaTvrXQqAzlfduHQ1NYg.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
  
  capitan_america1: {
    id: "capitanamerica1",
    titulo: "Capitán América: El primer vengador",
    video: "https://dl.dropbox.com/scl/fi/d4u3zogldzedwq4w9wbpd/Captain.america.the.first.avenger.2011.1080P-Dual-Lat.mp4?rlkey=s1qalyvhze89mwmh6f3pf9z0i&st=",
    poster: "https://image.tmdb.org/t/p/w780/yFuKvT4Vm3sKHdFY4eG6I4ldAnn.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Nacido durante la Gran Depresión, Steve Rogers creció como un chico enclenque en una familia pobre. Horrorizado por las noticias que llegaban de Europa sobre los nazis, decidió enrolarse en el ejército; sin embargo, debido a su precaria salud, fue rechazado una y otra vez. Enternecido por sus súplicas, el general Chester Phillips le ofrece la oportunidad de tomar parte en un experimento especial: la Operación Renacimiento.",
    anio: "2011",
    duracion: "2h 48min",
    calificacion: "70%",
    genero: "Acción • Marvel • Ciencia ficción",
    director: "Joe Johnston",
    reparto: "Chris Evans, Hufo Weaving, Hayley Atwell",
    estreno: "22/07/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "capitan_america2",
        titulo: "Capitán América 2: El soldado de invierno",
        imagen: "https://image.tmdb.org/t/p/w300/wP7JcCzpWlX5XeROpf4ox9ZVFT6.jpg"
      },
      {
        id: "capitan_america3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "pantera_negra1",
        titulo: "Pantera Negra: Wakanda por siempre",
        imagen: "https://image.tmdb.org/t/p/w300/qUhjmU8P2OA7AG4IgqXzbwvl4Tq.jpg"
      },
      {
        id: "los_vengadores_endgame",
        titulo: "Los vengadores: Endgame",
        imagen: "https://image.tmdb.org/t/p/w300/br6krBFpaYmCSglLBWRuhui7tPc.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg",
      }
    ]
  },

  capitan_america2: {
    id: "capitanamerica2",
    titulo: "Capitán América 2: El soldado de invierno",
    video: "https://dl.dropbox.com/scl/fi/zezkut1tqjhaayz6f77zh/Captain.america.the.winter.soldier.2014.1080P-Dual-Lat.mp4?rlkey=n741zfgm8h3un08zp3xp113jw&st=",
    poster: "https://image.tmdb.org/t/p/w780/ku1lKmW4iCbHNixRntDgcCdMyNs.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wP7JcCzpWlX5XeROpf4ox9ZVFT6.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Capitán América, Viuda Negra y un nuevo aliado, Falcon, se enfrentan a un enemigo inesperado mientras intentan sacar a la luz una conspiración que pone en riesgo al mundo.",
    anio: "2014",
    duracion: "2h 16min",
    calificacion: "89%",
    genero: "Acción • Marvel • Ciencia ficción",
    director: "Anthony y Joe Russo",
    reparto: "Chris Evans, Scarlett Johansson, Sebastian Stan",
    estreno: "10/04/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "capitan_america3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "guardianes_de_la_galaxia_3",
        titulo: "Guardianes de la galaxia Vol.3",
        imagen: "https://image.tmdb.org/t/p/w300/r2J02Z2OpNTctfOSN1Ydgii51I3.jpg"
      },
      {
        id: "spiderman_3",
        titulo: "Spider-Man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3LSdA2l3EmI9duIJKzNElUPs0RK.jpg"
      }
    ]
  },

  capitan_america3: {
    id: "capitanamerica3",
    titulo: "Capitán América 3: Civil War",
    video: "https://dl.dropbox.com/scl/fi/ibggidfciyx46ewzmjq1v/Captain.america.civil.war.2016.1080P-Dual-Lat.mp4?rlkey=yi5qkprhtque5befp5amea8xi&st=",
    poster: "https://image.tmdb.org/t/p/w780/jbviMV7wLGyYMpxDOLHP92lCtki.jpg",
    // 🖼 POSTER TARJETA
    imagen: "https://image.tmdb.org/t/p/w300/xHIzL54EuCFXVMaSudLLuHjuZ5r.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Continúa la historia de “Los vengadores: La era de Ultron”, con Steve Rogers liderando un nuevo equipo de Vengadores en su esfuerzo por proteger a la humanidad. Tras otro incidente internacional relacionado con los Vengadores que ocasiona daños colaterales, la presión política fuerza a crear un sistema de registro y un cuerpo gubernamental para determinar cuándo se requiere los servicios del equipo. El nuevo status quo divide a los Vengadores mientras intentan salvar al mundo de un nuevo y perverso villano. ",
    anio: "2016",
    duracion: "2h 27min",
    calificacion: "90%",
    genero: "Acción • Marvel • Ciencia ficción",
    director: "Anthony y Joe Russo",
    reparto: "Chris Evans, Scarlett Johansson, Robert Downey JR",
    estreno: "27/04/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "capitan_america2",
        titulo: "Capitán América 2: El soldado del invierno",
        imagen: "https://image.tmdb.org/t/p/w300/wP7JcCzpWlX5XeROpf4ox9ZVFT6.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "los_vengadores",
        titulo: "Los vengadores",
        imagen: "https://image.tmdb.org/t/p/w300/ugX4WZJO3jEvTOerctAWJLinujo.jpg"
      },
      {
        id: "thor_amor_y_trueno4",
        titulo: "Thor: Amor y trueno",
        imagen: "https://image.tmdb.org/t/p/w300/qTdnMVkjoP3b1ocwYyW0qrsEabc.jpg"
      },
      {
        id: "doctor_strange2",
        titulo: "Doctor Strange: En el multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg"
      }
    ]
  },
  
  capitan_america4: {
    id: "capitanamerica4",
    titulo: "Capitán América 4: Un nuevo mundo",
    video: "https://dl.dropbox.com/scl/fi/e5x75pq9aciu61908fdll/Capitan.America.Un.Nuevo.Mundo.2025.1080P-Dual-Lat.mkv?rlkey=7i2hthiznmol3xfyrysv037dj&st=",
    poster: "https://image.tmdb.org/t/p/w780/8eifdha9GQeZAkexgtD45546XKx.jpg",
    // 🖼 POSTER TARJETA
    imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras reunirse con el recién elegido presidente de los EE. UU., Thaddeus Ross, Sam se encuentra en medio de un incidente internacional. Debe descubrir el motivo que se esconde tras un perverso complot global, antes de que su verdadero artífice enfurezca al mundo entero.",
    anio: "2025",
    duracion: "1h 59min",
    calificacion: "70%",
    genero: "Acción • Marvel • Ciencia ficción",
    director: "Julius Onah",
    reparto: "Anthony Mackie, Harrison Ford, Danny Haas",
    estreno: "14/02/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "capitan_america2",
        titulo: "Capitán América 2: El soldado de invierno",
        imagen: "https://image.tmdb.org/t/p/w300/6QBRnyvJHD7slOlX6aukvMwcEu.jpg"
      },
      {
        id: "capitan_america3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-Man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/rkLhaNa37IwzWis8rzWMAYTCdIK.jpg"
      },
      {
        id: "los_vengadores_1",
        titulo: "Los Vengadores",
        imagen: "https://image.tmdb.org/t/p/w300/ugX4WZJO3jEvTOerctAWJLinujo.jpg"
      }
    ]
  },

  culpa_tuya: {
    id: "culpa_tuya",
    titulo: "Culpa tuya",
    video: "https://dl.dropbox.com/scl/fi/zm9h8doo7g7kwd0f7sm6c/Culpa-tuya-2024.mp4?rlkey=cfyicql2ul47jgli8g676mozv&st=",
    poster: "https://image.tmdb.org/t/p/w780/k24eZq5I3jyz4htPkZCRpnUmBzE.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El amor entre Noah y Nick parece inquebrantable, a pesar de las maniobras de sus padres por separarles. Pero el trabajo de él y la entrada de ella en la universidad, abre sus vidas a nuevas relaciones. La aparición de una exnovia que busca venganza y de la madre Nick con intenciones poco claras, removerán los cimientos no solo de su relación, sino de la propia familia Leister. Cuando tantas personas están dispuestas a destruir una relación, ¿puede realmente acabar bien?",
    anio: "2024",
    duracion: "2h 00min",
    calificacion: "77%",
    genero: "Romance • Drama",
    director: "Domingo González",
    reparto: "Nicole Wallace, Gabriel Guevara, Gabriela Andrada",
    estreno: "18/12/2024",
    idioma: "España 🇪🇸",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "culpa_mia_2",
        titulo: "Culpa mía 2: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "culpa_nuestra_3",
        titulo: "Culpa nuestra 3",
        imagen: "https://image.tmdb.org/t/p/w300/6kmi6vmp6iOn4KzI7WfnVtAeJhU.jpg"
      },
      {
        id: "after_2019",
        titulo: "After: Aquí empieza todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Dias",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "cincuentas_sombras_de_grey_1",
        titulo: "Cincuenta sombras de Grey",
        imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg"
      },
      {
        id: "millers_girl",
        titulo: "Miller's Girl",
        imagen: "https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"
      }
    ]
  },

  culpa_mia_2: {
    id: "culpa_mia_2",
    titulo: "Culpa mia 2: Londres",
    video: "https://dl.dropbox.com/scl/fi/f0ssvb8zrj6p2p30zgzrs/My.fault.london.2025.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=ncm7to2oy0sfttbrp4ftlz6rg&st",
    poster: "https://image.tmdb.org/t/p/w780/8FH23n6noUWqeNBtcnmFhsrTTwD.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Noah, de 18 años, se traslada de Estados Unidos a Londres con su madre, que se ha enamorado recientemente de William, un adinerado hombre de negocios británico. Noah conoce al hijo de William, el malote Nick. Pese a los esfuerzos de ambos por evitarlo, se sienten atraídos. Mientras Noah pasa el verano adaptándose a su nueva vida, su doloroso pasado la irá atrapando a la vez que se va enamorando.",
    anio: "2025",
    duracion: "2h 00min",
    calificacion: "70%",
    genero: "Romance • Drama",
    director: "Domingo González",
    reparto: "Nicole Wallace, Gabriel Guevara, Gabriela Andrada",
    estreno: "13/02/2025",
    idioma: "España 🇪🇸",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "culpa_nuestra_3",
        titulo: "Culpa nuestra 3",
        imagen: "https://image.tmdb.org/t/p/w300/6kmi6vmp6iOn4KzI7WfnVtAeJhU.jpg"
      },
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "dias_365_3",
        titulo: "365 Dias 3: Mas",
        imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg"
      },
      {
        id: "after_4",
        titulo: "After 4: Aquí acaba todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "un_ladron_romantico",
        titulo: "Un ladrón romántico",
        imagen: "https://image.tmdb.org/t/p/w300/nif2JUyqNQBBmMYrDfmpTgwleOJ.jpg"
      }
    ]
  },

  culpa_nuestra_3: {
    id: "culpa_nuestra_3",
    titulo: "Culpa nuestra 3",
    video: "https://dl.dropbox.com/scl/fi/1mv1wem69f14nfr4l73eu/Culpa.Nuestra.2025.1080P-Dual-Lat.mkv?rlkey=pmlgufabviceovbib0264li54&st=",
    poster: "https://image.tmdb.org/t/p/w780/7QirCB1o80NEFpQGlQRZerZbQEp.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/6kmi6vmp6iOn4KzI7WfnVtAeJhU.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La boda de Jenna y Lion trae consigo el tan esperado reencuentro entre Noah y Nick tras su ruptura. La incapacidad de Nick para perdonar a Noah se erige como una barrera insalvable. Él, heredero de los negocios de su abuelo, y ella, que inicia su carrera profesional, se resisten a avivar una llama que aún sigue viva. Pero ahora que sus caminos se han cruzado de nuevo, ¿será el amor más fuerte que el rencor?.",
    anio: "2025",
    duracion: "1h 53min",
    calificacion: "90%",
    genero: "Romance • Drama",
    director: "Domingo González",
    reparto: "Nicole Wallace, Gabriel Guevara, Gabriela Andrada",
    estreno: "16/10/2025",
    idioma: "España 🇪🇸",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "culpa_mia_2",
        titulo: "Culpa mía 2: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "romper_el_circulo",
        titulo: "Romper el circulo",
        imagen: "https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "aladdin_1",
        titulo: "Aladdin",
        imagen: "https://image.tmdb.org/t/p/w300/oakAd8syy7jNQ4ZoaAGCQkTqcOV.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      }
    ]
  },

  cantar_desnuda: {
    id: "cantar_desnuda",
    titulo: "Cantar desnuda",
    video: "https://dl.dropbox.com/scl/fi/hztorlebywiaqdapqabky/Cantar-Desnuda-2025.mp4?rlkey=2k9g57uwrkw3pvlbbk871fpp9&st=",
    poster: "https://image.tmdb.org/t/p/w780/fwfggfE52rpd8d4yeeoRcbGN4oQ.jpg",
    imagen: "https://cinepelayo.com/wp-content/uploads/2025/01/cartel-cantar-desnuda.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: true,      // true si es +18
    sinopsis: "¿Si hay la necesidad de pintar y dejarse pintar desnuda, o hacer una escultura o una danza, de interpretar desnuda en una obra de teatro y por supuesto, donde más, en cine, por qué no en la canción sobre todo si transmite pasión y entrega? Por primera vez en el mundo, creo que en la historia, Anikka lo hace. Quería hacer esta película desde hace más de diez años pero no contaba con ninguna gran cantante que necesitara también hacerla. Hasta que conocí a Anikka.",
    anio: "2025",
    duracion: "1h 05min",
    calificacion: "00%",
    genero: "Musical • Drama • Adulto",
    director: "Gonzalo Garcia-Pelayo",
    reparto: "Anikka, Samy Jones, Tasha, El NONO Hub",
    estreno: "28/01/2025",
    idioma: "Argentina 🇦🇷",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cato",
        titulo: "CATO",
        imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"
      },
      {
        id: "banger",
        titulo: "Banger",
        imagen: "https://image.tmdb.org/t/p/w300/x2pegSby27ebOwW361GJb1aKcxa.jpg"
      },
      {
        id: "vivir_en_sevilla",
        titulo: "Vivir en Sevilla",
        imagen: "https://image.tmdb.org/t/p/w300/bGrSAgLWcqg752fPAJkTTtcNKYW.jpg",
        adulto: true
      },
      {
        id: "freestyle",
        titulo: "Freestyle",
        imagen: "https://image.tmdb.org/t/p/w300/8jwbiJB8Am1N9OsqaJs9vrGerlG.jpg"
      },
      {
        id: "deseaba_llamarla_sumision",
        titulo: "Deseaba llamarla Sumisión",
        imagen: "https://cinepelayo.com/wp-content/uploads/2025/07/cartel-de-DLLSOcSNSLQQA.jpg",
        adulto: true
      },
      {
        id: "tu_coño",
        titulo: "Tu coño",
        imagen: "https://image.tmdb.org/t/p/w300/7QVwPedlx5lynEF6xGXfXAFJwKj.jpg",
        adulto: true
      }
    ]
  },

  cars: {
    id: "cars",
    titulo: "Cars",
    video: "https://dl.dropbox.com/scl/fi/otghj0f0orj98yr1kqo80/Cars.2006.1080P-Dual-Lat.mp4?rlkey=w4tsieopb0gbp09h66ym7tcaw&st=",
    poster: "https://image.tmdb.org/t/p/w780/hCV6eVyuIZZuyBNVvFwYmCDgLaG.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/abW5AzHDaIK1n9C36VdAeOwORRA.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El aspirante a campeón de carreras Rayo McQueen está sobre la vía rápida al éxito, la fama y todo lo que él había soñado, hasta que por error toma un desvío inesperado en la polvorienta y solitaria Ruta 66. Su actitud arrogante se desvanece cuando llega a una pequeña comunidad olvidada que le enseña las cosas importantes de la vida que había olvidado.",
    anio: "2006",
    duracion: "1h 56min",
    calificacion: "79%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "John Lasseter",
    reparto: "Sergio Gutierrez, Gabriel Pingarron, Eduardo Tijedo",
    estreno: "09/06/2006",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cars_2",
        titulo: "Cars 2",
        imagen: "https://image.tmdb.org/t/p/w300/eQo1LQs5Vo9RHVHYUhNSfMZa3VB.jpg"
      },
      {
        id: "cars_3",
        titulo: "Cars 3",
        imagen: "https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg"
      },
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "el_rey_leon_2019",
        titulo: "El Rey León",
        imagen: "https://image.tmdb.org/t/p/w300/yysmQpv26DdP79XtR3zsL3nVFbN.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      }
    ]
  },

  cars_2: {
    id: "cars_2",
    titulo: "Cars 2",
    video: "https://dl.dropbox.com/scl/fi/xdrx2lp58mqr15ys54sw5/Cars.2.2011.1080P-Dual-Lat.mp4?rlkey=83bh2z5o20313z7ph7yfxpeqq&st=",
    poster: "https://image.tmdb.org/t/p/w780/2SSZlcXtliCi45Nd2LAs6tec0oc.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/okIz1HyxeVOMzYwwHUjH2pHi74I.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Rayo McQueen y Mate compiten en el Gran Premio Mundial, la carretera se llena de divertidas sorpresas... especialmente cuando Mate se ve atrapado en una aventura de espionaje internacional.",
    anio: "2011",
    duracion: "1h 47min",
    calificacion: "76%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "John Lasseter y Brad Lewis",
    reparto: "Kuno Becker, Cesar Bono, Kate Del Castillo",
    estreno: "24/06/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cars",
        titulo: "Cars",
        imagen: "https://image.tmdb.org/t/p/w300/abW5AzHDaIK1n9C36VdAeOwORRA.jpg"
      },
      {
        id: "cars_3",
        titulo: "Cars 3",
        imagen: "https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg"
      },
      {
        id: "coco",
        titulo: "Coco",
        imagen: "https://image.tmdb.org/t/p/w300/gGEsBPAijhVUFoiNpgZXqRVWJt2.jpg"
      },
      {
        id: "frozen_2",
        titulo: "Frozen 2",
        imagen: "https://image.tmdb.org/t/p/w300/qXsndsv3WOoxszmdlvTWeY688eK.jpg"
      },
      {
        id: "hercules",
        titulo: "Hércules",
        imagen: "https://image.tmdb.org/t/p/w300/dK9rNoC97tgX3xXg5zdxFisdfcp.jpg"
      },
      {
        id: "moana",
        titulo: "Moana",
        imagen: "https://image.tmdb.org/t/p/w300/zLZxomOWttSCxJOnY8Hiy72qcm0.jpg"
      }
    ]
  },

  cars_3: {
    id: "cars_3",
    titulo: "Cars 3",
    video: "https://dl.dropbox.com/scl/fi/rfttgbbnb9l02i2o4lwe4/Cars.3.2017.1080P-Dual-Lat.mp4?rlkey=4hieb2sawy7wokplmahkmc1ik&st=",
    poster: "https://image.tmdb.org/t/p/w780/uVeDyl6hqzBKh45OG7PMH8HTZdO.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Rayo McQueen sigue siendo el mejor coche de carreras del mundo, pero ahora que es uno de los más veteranos del circuito, debe demostrar a todo el mundo que aún puede ganar una gran carrera y que no necesita jubilarse. En esta ocasión, el famoso bólido de carreras tendrá que enfrentarse a una nueva generación de corredores más jóvenes, potentes y veloces, que amenaza con cambiar el deporte de su vida. Entre ellos está el competitivo y revolucionario Jackson Storm, que no se lo pondrá nada fácil.",
    anio: "2017",
    duracion: "1h 42min",
    calificacion: "74%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "Brian Fee",
    reparto: "Kuno Becker, Cesar Bono, Raul Anaya",
    estreno: "16/06/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cars",
        titulo: "Cars",
        imagen: "https://image.tmdb.org/t/p/w300/abW5AzHDaIK1n9C36VdAeOwORRA.jpg"
      },
      {
        id: "cars_2",
        titulo: "Cars 2",
        imagen: "https://image.tmdb.org/t/p/w300/okIz1HyxeVOMzYwwHUjH2pHi74I.jpg"
      },
      {
        id: "leo",
        titulo: "Leo",
        imagen: "https://image.tmdb.org/t/p/w300/pD6sL4vntUOXHmuvJPPZAgvyfd9.jpg"
      },
      {
        id: "luck_suerte",
        titulo: "Luck: Suerte",
        imagen: "https://image.tmdb.org/t/p/w300/cQDqNCtq7j5xaCXGeLsLZK90RuR.jpg"
      },
      {
        id: "los_croods",
        titulo: "Los Croods",
        imagen: "https://image.tmdb.org/t/p/w300/p7lJkqHlK01nr0zNacunUFI5Qxy.jpg"
      },
      {
        id: "madagascar",
        titulo: "Madagascar",
        imagen: "https://image.tmdb.org/t/p/w300/zrV5GnfCcLWzyjrFgYTpjQPRMfl.jpg"
      }
    ]
  },

  cato: {
    id: "cato",
    titulo: "CATO",
    video: "https://dl.dropbox.com/scl/fi/n4wq66s8ccs8gcnq7v86b/Cato.2021.1080P-Dual-Lat.mp4?rlkey=smqkbc2vkko0ocx77l7dcvh2a&st=",
    poster: "https://image.tmdb.org/t/p/w780/lN93Tm0AH7CUhSZ9WavhkKoHjh3.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cato, un artista de hip-hop que justo cuando su carrera está a punto de despegar, se mete en problemas con la ley y una red organizada violentamente de hooligans del fútbol argentino.",
    anio: "2021",
    duracion: "0h 008min",
    calificacion: "85%",
    genero: "Musica • Drama",
    director: "Peta Rivero Y Hornos",
    reparto: "Tiago PZK, Alberto Ajaka, Magala Zanotta",
    estreno: "14/10/2021",
    idioma: "Argentina 🇦🇷",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "freestyle",
        titulo: "Freestyle",
        imagen: "https://image.tmdb.org/t/p/w300/8jwbiJB8Am1N9OsqaJs9vrGerlG.jpg"
      },
      {
        id: "karol_g",
        titulo: "Karol G: Mañana fue muy bonito",
        imagen: "https://image.tmdb.org/t/p/w300/5aXoQYwaQ7JJVUWclHAEXJgiS2M.jpg"
      },
      {
        id: "rehen",
        titulo: "iRehén!",
        imagen: "https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"
      },
      {
        id: "la_evaluacion",
        titulo: "La evaluación",
        imagen: "https://image.tmdb.org/t/p/w300/rCGwGWI4a2EaNQCyTe4vDfoiMtk.jpg"
      },
      {
        id: "Tiempo_de_guerra",
        titulo: "Warfare. Tiempo de guerra",
        imagen: "https://image.tmdb.org/t/p/w300/fkVpNJugieKeTu7Se8uQRqRag2M.jpg"
      },
      {
        id: "Desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      }
    ]
  },

  cementerio_de_animales: {
    id: "cementerio_de_animales",
    titulo: "Cementerio de animales",
    video: "https://dl.dropbox.com/scl/fi/lz8v83q3nayi9uur98kyv/Pet.sematary.2019.1080p-dual-lat-cinecalidad.to.mp4?rlkey=cn2p2d3xp84onffr4bybdb9vj&st=",
    poster: "https://image.tmdb.org/t/p/w780/dMWVeuVce8ZLKLI0xpVevihEwm8.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vnw6g9c7qzNdzvpQhwWGRzBxwM0.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El doctor Louis Creed (Clarke) se muda con su mujer Racher (Seimetz) y sus dos hijos pequeños de Boston a un pueblecito de Maine, cerca del nuevo hogar de la familia descubrirá una terreno misterioso escondido entre los árboles. Cuando la tragedia llega, Louis hablará con su nuevo vecino, Jud Crandall (Lithgow), desencadenando una peligrosa reacción en cadena que desatará un mal de horribles consecuencias.",
    anio: "2019",
    duracion: "1h 40min",
    calificacion: "57%",
    genero: "Terror • Misterio",
    director: "Kevin Kolsch Y Dennis Widmyer",
    reparto: "Jasin Clarke, Amy Seimetz, John Lithgow",
    estreno: "04/04/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cementerio_de_animales_2",
        titulo: "Cementerio de animales 2: Los orígenes",
        imagen: "https://image.tmdb.org/t/p/w300/h6OOcYnuYVoaQQm3zGIYJ7XfTuo.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        id: "un_lugar_en_silencio",
        titulo: "Un lugar en silencio",
        imagen: "https://image.tmdb.org/t/p/w300/hE51vC3iZJCqFecLzIO1Q4eYXqK.jpg"
      },
      {
        id: "terrifier_#",
        titulo: "Terrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      },
      {
        id: "sonrie",
        titulo: "Sonríe",
        imagen: "https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg"
      },
      {
        id: "poseida",
        titulo: "Poseída",
        imagen: "https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg"
      }
    ]
  },

  cementerio_de_animales_2: {
    id: "cementerio_de_animales_2",
    titulo: "Cementerio de animales 2: Los origenes",
    video: "https://dl.dropbox.com/scl/fi/2q23uzus9696wgps1i7e7/Cementerio-de-animales-2-2023.mp4?rlkey=xxd9d09auq1vi9fp74d31xxsx&st=",
    poster: "https://image.tmdb.org/t/p/w780/dRWhJ4godwy40JdmNuRZy23oViY.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/h6OOcYnuYVoaQQm3zGIYJ7XfTuo.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En 1969, el joven Jud Crandall y sus amigos de la infancia se unen para enfrentarse a un antiguo peligro que se ha apoderado de su ciudad natal.",
    anio: "2023",
    duracion: "1h 27min",
    calificacion: "60%",
    genero: "Terror • Suspenso • Sobrenatural",
    director: "Lindsey Anderson Beer",
    reparto: "Jack Mulhern, Jackson White, Natalie Alyn Lind",
    estreno: "06/10/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cementerio_de_animales",
        titulo: "Cementerio de Animales",
        imagen: "https://image.tmdb.org/t/p/w300/vnw6g9c7qzNdzvpQhwWGRzBxwM0.jpg"
      },
      {
        id: "ofrenda_al_demonio",
        titulo: "Ofrenda al demonio",
        imagen: "https://image.tmdb.org/t/p/w300/7C1T0aFplHKaYacCqRdeGYLTKCW.jpg"
      },
      {
        id: "maligna",
        titulo: "Maligno",
        imagen: "https://image.tmdb.org/t/p/w300/oCVDRqnh6xtaexTKQ8OkXD89rkL.jpg"
      },
      {
        id: "mara",
        titulo: "Mara",
        imagen: "https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg"
      },
      {
        id: "martyrs",
        titulo: "Martyrs",
        imagen: "https://image.tmdb.org/t/p/w300/5kymocKK0SfyEEV0ohNEBz1lxNx.jpg"
      },
      {
        id: "la_monja",
        titulo: "La Monja",
        imagen: "https://image.tmdb.org/t/p/w300/q2JFJ8x0IWligHyuLJbBjqNsySf.jpg"
      }
    ]
  },

  chicas_malas_2004: {
    id: "chicas_malas_2004",
    titulo: "Chicas malas",
    video: "https://dl.dropbox.com/scl/fi/5em8dekr0073nlu0df8y9/Chicaa-malas-2004.mp4?rlkey=4537xqlunaurfnb6f1lxsedku&st=",
    poster: "https://image.tmdb.org/t/p/w780/6DqzZaTAzFrT53JtRt3MLKs0Y9i.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7L7wCakqwuoz6S9zRVaAH0NLJ3H.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una joven adolescente, Cady, acostumbrada a vivir en África con sus padres, zoólogos, se encuentra una nueva jungla cuando se muda a Illinois. Allí acude a la escuela pública, donde se enamorará del ex-novio de la chica más popular del colegio. Las chicas comenzarán a hacer la vida imposible a Cady, y ésta no tendrá otro remedio que usar sus mismas tácticas para mantenerse a flote.",
    anio: "2003",
    duracion: "1h 36min",
    calificacion: "72%",
    genero: "Drama • Comedia",
    director: "Mark Waters",
    reparto: "Lindsay Lohan, Raquel McAdams, Lizzy Caplan",
    estreno: "07/09/2004",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "chicas_malas_2",
        titulo: "Chicas malas 2",
        imagen: "https://image.tmdb.org/t/p/w300/m4cVT2dGfdjkbnlMpwsNnslPHv8.jpg"
      },
      {
        id: "chicas_malas_2024",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg"
      },
      {
        id: "no_me_la_toquen",
        titulo: "No Me La Toquen",
        imagen: "https://image.tmdb.org/t/p/w300/yEsYJyBsnDdMUbsehxIofMa9Oh7.jpg"
      },
      {
        id: "ricky_el_impostor",
        titulo: "Ricky Stanicky: El impostor",
        imagen: "https://image.tmdb.org/t/p/w300/oJQdLfrpl4CQsHAKIxd3DJqYTVq.jpg"
      },
      {
        id: "unos_suegro_de_armas_tomar",
        titulo: "Unos suegros de armas tomar",
        imagen: "https://image.tmdb.org/t/p/w300/5dliMQ2ODbGNoq0hlefdnuXQxMw.jpg"
      },
      {
        id: "viaje_de_fin_de_curso",
        titulo: "Viaje de fin de curso: Mallorca",
        imagen: "https://image.tmdb.org/t/p/w300/A8E8EqXqETV8ggPiOkHjaBU8H9N.jpg"
      }
    ]
  },

  
  chicas_malas_2024: {
    id: "chicas_malas_2024",
    titulo: "Chicas malas",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Ffeb24%2FVer%20Chicas%20pesadas%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/accTIUygtg24TM7wT7uQMMdvYUW.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La nueva estudiante Cady Heron es bienvenida a la cima de la cadena social por el elitista grupo de chicas populares llamado Las Plásticas, gobernado por la intrigante abeja reina Regina George y sus secuaces Gretchen y Karen. Sin embargo, cuando Cady comete el grave error de enamorarse del ex novio de Regina, Aaron Samuels, se encuentra en el punto de mira de Regina.",
    anio: "2024",
    duracion: "1h 52min",
    calificacion: "74%",
    genero: "Comedia",
    director: "Samantha Jayne",
    reparto: "Arroz Angourie, Renée Rapp, Auliʻi Cravalho",
    estreno: "12/01/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "chicas_malas_2004",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/7L7wCakqwuoz6S9zRVaAH0NLJ3H.jpg"
      },
      {
        id: "barbie",
        titulo: "Barbie",
        imagen: "https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg"
      },
      {
        id: "diario_de_mi_vagina",
        titulo: "Diario de mi vagina",
        imagen: "https://image.tmdb.org/t/p/w300/hyFKdAN5Dl93mt2JHfcfvIyf38g.jpg"
      },
      {
        id: "mi_abuelo_es_un_peligro",
        titulo: "Mi abuelo es un peligro",
        imagen: "https://image.tmdb.org/t/p/w300/7r9pn1g3lY95DjiwzxpmNqlJzeO.jpg"
      },
      {
        id: "asesino_serial",
        titulo: "Asesino serial",
        imagen: "https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg"
      },
      {
        id: "el_guason",
        titulo: "El Guasón",
        imagen: "https://image.tmdb.org/t/p/w300/2cta3k9kgsgweUTY2LvMSFjuB6e.jpg"
      }
    ]
  },

  chicas_malas_2: {
    id: "chicas_malas_2",
    titulo: "Chicas malas 2",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/9A4LywU6zVpkVwjk4EjNwUH3RGs.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/m4cVT2dGfdjkbnlMpwsNnslPHv8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Jo, una estudiante de último año y segura de sí misma, comienza el nuevo año escolar rompiendo su propia regla de oro: no involucrarse en dramas de chicas. Pero cuando ve a la tímida Abby siendo acosada por la Reina Abeja Mandi y sus secuaces, toma partido en una guerra mundial de chicas terriblemente divertida que pone patas arriba la Escuela Secundaria North Shore.",
    anio: "2011",
    duracion: "0h 008min",
    calificacion: "76%",
    genero: "Comedia",
    director: "Melanie Mayron",
    reparto: "Meaghan Jette Martín, Jennifer Stone, Maiara Walsh",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "chicas_malas_2004",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/7L7wCakqwuoz6S9zRVaAH0NLJ3H.jpg"
      },
      {
        id: "chicas_malas_2024",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg"
      },
      {
        id: "cato",
        titulo: "CATO",
        imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"
      },
      {
        id: "karol_g",
        titulo: "Karol G: Mañana fue muy bonito",
        imagen: "https://image.tmdb.org/t/p/w300/5aXoQYwaQ7JJVUWclHAEXJgiS2M.jpg"
      },
      {
        id: "a_ganar",
        titulo: "¡A Ganar!",
        imagen: "https://image.tmdb.org/t/p/w300/6GVYL9K2IBFrfIqwwFqMPu5DdC5.jpg"
      },
      {
        id: "harta",
        titulo: "Harta",
        imagen: "https://image.tmdb.org/t/p/w300/4d2PJ6QLAVd9w66E918JSWjkgs7.jpg"
      }
    ]
  },

  cincuentas_sombras_de_grey_1: {
    id: "cincuentas_sombras_de_grey_1",
    titulo: "Cincuenta sombras de Grey",
    video: "https://dl.dropbox.com/scl/fi/h5ihjkfrfn0u4wiaywykv/Fifty.shades.of.grey.2015.r-hd-dual-lat.mp4?rlkey=cawwdlddgus5y8pxvsthsdf5d&st=",
    poster: "https://image.tmdb.org/t/p/w780/wQyzgDIOSMpoHOazmZb2yLRBRHd.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando la estudiante de Literatura Anastasia Steele recibe el encargo de entrevistar al exitoso y joven empresario Christian Grey, queda impresionada al encontrarse ante un hombre atractivo, seductor y también muy intimidante. La inexperta e inocente Ana intenta olvidarle, pero pronto comprende cuánto le desea. Cuando la pareja por fin inicia una apasionada relación, Ana se sorprende por las peculiares prácticas eróticas de Grey, al tiempo que descubre los límites de sus propios y más oscuros deseos.",
    anio: "2015",
    duracion: "2h 05min",
    calificacion: "84%",
    genero: "Romance • Drama",
    director: "Sam Taylor-Johnson",
    reparto: "Dakota Johnson, Jamie Dornan, Jennifer Ehle",
    estreno: "12/02/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cincuenta_sombras_más_oscuras_2",
        titulo: "Cincuenta sombras 2: Más oscuras",
        imagen: "https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg"
      },
      {
        id: "cincuenta_sombra_liberadas_3",
        titulo: "Cincuenta sombras 3: Liberadas",
        imagen: "https://image.tmdb.org/t/p/w300/sM8hwgWZlmZf0h4aOkNopb3HBIo.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseo prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "el_guason",
        titulo: "El Guasón",
        imagen: "https://image.tmdb.org/t/p/w300/2cta3k9kgsgweUTY2LvMSFjuB6e.jpg"
      },
      {
        id: "todo_bien",
        titulo: "¿Todo bien?",
        imagen: "https://image.tmdb.org/t/p/w300/mKdRfCpWkcH0wEEg6yO4a8ES4TX.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      }
    ]
  },

  cincuenta_sombra_liberadas_3: {
    id: "cincuenta_sombra_liberadas_3",
    titulo: "Cincuenta Sombras 3: liberadas",
    video: "https://dl.dropbox.com/scl/fi/ay2g3bqh66l6zid67elin/Fifty.shades.freed.2018.1080p.unrated-dual-lat-cinecalidad.to.mp4?rlkey=8m6ym2y7rhf8ki2uz32zwkqwd&st=",
    poster: "https://image.tmdb.org/t/p/w780/mYJTOuQmLRGhhMqGTQUZLTBekXF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/sM8hwgWZlmZf0h4aOkNopb3HBIo.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Creyendo que han dejado atrás las sombras del pasado, Christian y Anastasia disfrutan de su relación y de su vida llena de lujos. Pero justo cuando Ana empieza a relajarse, aparecen nuevas amenazas que ponen en riesgo su felicidad.",
    anio: "2018",
    duracion: "1h 50min",
    calificacion: "87%",
    genero: "Romance • Drama",
    director: "James Foley",
    reparto: "Dakota Johnson, Jamie Dornan, Eric Johnson",
    estreno: "09/02/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cincuentas_sombras_de_grey_1",
        titulo: "Cincuenta sombras de Grey",
        imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg"
      },
      {
        id: "cincuenta_sombras_más_oscuras_2",
        titulo: "Cincuenta sombras 2: Más oscuras",
        imagen: "https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg"
      },
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "dias_365_2",
        titulo: "365 Dias 2 Aquel día",
        imagen: "https://image.tmdb.org/t/p/w300/jBpqADo9XAKaecvI3f0J4hRAEyO.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa Mia 2: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "tierra_baja",
        titulo: "Tierra Baja",
        imagen: "https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"
      }
    ]
  },
      
  cincuenta_sombras_más_oscuras_2: {
    id: "cincuenta_sombras_más_oscuras_2",
    titulo: "Cincuenta sombras 2: Más oscuras",
    video: "https://dl.dropbox.com/scl/fi/789jnn4bk1fpilkgxpgkh/Fifty.shades.darker.2017.1080P-Dual-Lat.mp4?rlkey=c5e9pgc9i97kuf7y1tri8z062&st=",
    poster: "https://image.tmdb.org/t/p/w780/9dWH18IZf0KdGx0kJaONzWcmD69.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Christian Grey, que se siente herido, intenta convencer a Anastasia Steele de que vuelva a formar parte de su vida, ella le exige un nuevo acuerdo antes de aceptar. Pero cuando la pareja empieza a ser más confiada y a tener una cierta estabilidad, aparecen mujeres del pasado de Christian decididas a frenar en seco sus esperanzas de un futuro juntos...",
    anio: "2017",
    duracion: "2h 11min",
    calificacion: "84%",
    genero: "Romance • Drama",
    director: "James Foley",
    reparto: "Dakota Johnson, Eloise Mumford, Bella Heathcote",
    estreno: "09/02/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cincuentas_sombras_de_grey_1",
        titulo: "Cincuenta sombras de Grey",
        imagen: "https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg"
      },
      {
        id: "cincuenta_sombra_liberadas_3",
        titulo: "Cincuenta sombras 3: Liberadas",
        imagen: "https://image.tmdb.org/t/p/w300/sM8hwgWZlmZf0h4aOkNopb3HBIo.jpg"
      },
      {
        id: "millers_girl",
        titulo: "Miller's Girl",
        imagen: "https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"
      },
      {
        id: "un_ladron_romantico",
        titulo: "Un ladrón romántico",
        imagen: "https://image.tmdb.org/t/p/w300/nif2JUyqNQBBmMYrDfmpTgwleOJ.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "after_3",
        titulo: "After 3: Amor infinito",
        imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg"
      }
    ]
  },

  coco: {
    id: "coco",
    titulo: "Coco",
    video: "https://dl.dropbox.com/scl/fi/pfndn0wnjws80pfeotf1o/Coco.2017.1080p-dual-lat.mp4?rlkey=2sa8qu2l7wgs97udnwqmdijr9&st=",
    poster: "https://image.tmdb.org/t/p/w780/u018zss2PloCHqXgrvKMsDDuVDd.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/yAvisTUocxmXQZQJZ521dL9a36p.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un joven aspirante a músico llamado Miguel se embarca en un viaje extraordinario a la mágica tierra de sus ancestros. Allí, el encantador embaucador Héctor se convierte en su inesperado amigo y le ayuda a descubrir los misterios detrás de las historias y tradiciones de su familia.",
    anio: "2017",
    duracion: "1h 45min",
    calificacion: "82%",
    genero: "Animacion • Aventura • Musical • Disney",
    director: "Lee Unkrich",
    reparto: "Anthony Gonzalez, Gael García Bernal, Alanna Ubach",
    estreno: " 27/10/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "frozen",
        titulo: "Frozen",
        imagen: "https://image.tmdb.org/t/p/w300/kgwjIb2JDHRhNk13lmSxiClFjVk.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      },
      {
        id: "la_cenicienta",
        titulo: "La Cenicienta",
        imagen: "https://image.tmdb.org/t/p/w300/vqzeSm5Agvio7DahhKXaySUbUUW.jpg"
      },
      {
        id: "wonka",
        titulo: "Wonka",
        imagen: "https://image.tmdb.org/t/p/w300/cDkMUi0i85qgjlRqq92k2yzRHA2.jpg"
      },
      {
        id: "los_increibles",
        titulo: "Los Increíbles",
        imagen: "https://image.tmdb.org/t/p/w300/al1jusd4T7JPatZlj4BuYkDDOzr.jpg"
      },
      {
        id: "moana",
        titulo: "Moana",
        imagen: "https://image.tmdb.org/t/p/w300/zLZxomOWttSCxJOnY8Hiy72qcm0.jpg"
      }
    ]
  },

  como_entrenar_a_tu_dragon_1: {
    id: "como_entrenar_a_tu_dragon_1",
    titulo: "Cómo entrenar a tu dragón",
    video: "https://dl.dropbox.com/scl/fi/wcv2c8x291ibgtgborwe8/How.to.Train.Your.Dragon.2010.bluray-latino-e-ingles-subt.mp4?rlkey=z0evkc1z75o7cipc4vxqo0t2u&st=",
    poster: "https://image.tmdb.org/t/p/w780/aH9KWmXFMamXkHMgLjnQmSYjScL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Ambientada en el mítico mundo de los rudos vikingos y los dragones salvajes, y basada en el libro infantil de Cressida Cowell, esta comedia de acción narra la historia de Hipo, un vikingo adolescente que no encaja exactamente en la antiquísima reputación de su tribu como cazadores de dragones.",
    anio: "2010",
    duracion: "1h 37min",
    calificacion: "78%",
    genero: "Animacion • Aventura • Fantansia • Familia",
    director: "Chris Sanders",
    reparto: "Jay Baruchel, Gerard Butler, América Ferrera",
    estreno: "26/03/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "como_entrenar_a_tu_dragon_2",
        titulo: "Cómo entrenar a tu dragón 2",
        imagen: "https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon_3",
        titulo: "Cómo entrenar a tu dragón 3",
        imagen: "https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La pelicula de arenita ejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "el_bebe_jefazo",
        titulo: "El bebé jefazo",
        imagen: "https://image.tmdb.org/t/p/w300/dPiXM1aFbJ9XJGPyf5ZULmEjzkR.jpg"
      },
      {
        id: "shrek",
        titulo: "Shrek",
        imagen: "https://image.tmdb.org/t/p/w300/5G1RjHMSt7nYONqCqSwFlP87Ckk.jpg"
      }
    ]
  },

  como_entrenar_a_tu_dragon_2: {
    id: "como_entrenar_a_tu_dragon_2",
    titulo: "Cómo entrenar a tu dragón 2",
    video: "https://dl.dropbox.com/scl/fi/yom0ju4bqtimjb23jk7s6/How.to.Train.Your.Dragon.2.2014.bluray-latino-e-ingles-subt.mp4?rlkey=a5azf1sl970v81s2lcm7ihtcf&st=",
    poster: "https://image.tmdb.org/t/p/w780/5MnP0h7RcUCeX7gpxMYoMScmfq7.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Han pasado cinco años desde que Hipo empezó a entrenar a su dragón, rompiendo la tradición vikinga de cazarlos. Astrid y el resto de la pandilla han conseguido difundir en la isla un nuevo deporte: las carreras de dragones. Mientras realizan una carrera, atraviesan los cielos llegando a territorios inhóspitos, donde nadie antes ha estado.",
    anio: "2014",
    duracion: "1h 42min",
    calificacion: "86%",
    genero: "Animacion • Aventura • Fantansia • Familia",
    director: "Decano DeBlois",
    reparto: "Jay Baruchel, Cate Blanchett, Gerard Butler",
    estreno: "13/06/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "como_entrenar_a_tu_dragon_1",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon_3",
        titulo: "Cómo entrenar a tu dragón 3",
        imagen: "https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg"
      },
      {
        id: "los_croods",
        titulo: "Los Croods: Una nueva era",
        imagen: "https://image.tmdb.org/t/p/w300/A8fYqHsOKF0wI5tYnpScjab3f3p.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La Sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/2w7EVsWEWfk45OZBxRTVxlyp00.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      }
    ]
  },

  como_entrenar_a_tu_dragon_3: {
    id: "como_entrenar_a_tu_dragon_3",
    titulo: "Cómo entrenar a tu dragón 3",
    video: "https://dl.dropbox.com/scl/fi/paumvpjg9tvq0tmmlp4kl/How.to.train.your.dragon.the.hidden.world.2019.1080p-dual-lat-cinecalidad.is.mp4?rlkey=pevwy0uw25ntsxamaw50accel&st=",
    poster: "https://image.tmdb.org/t/p/w780/h3KN24PrOheHVYs9ypuOIdFBEpX.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Lo que comenzó como la inesperada amistad entre un joven vikingo y un temible dragón, Furia Nocturna, se ha convertido en una épica trilogía que ha recorrido sus vidas. En esta nueva entrega, Hipo y Desdentao descubrirán finalmente su verdadero destino: para uno, gobernar Isla Mema junto a Astrid; para el otro, ser el líder de su especie.",
    anio: "2018",
    duracion: "1h 44min",
    calificacion: "78%",
    genero: "Animacion • Aventura • Fantansia • Familia",
    director: "Dean DeBlois",
    reparto: "Jay Baruchel, America Ferrera, Cate Blanchett",
    estreno: "31/01/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "como_entrenar_a_tu_dragon_1",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon_2",
        titulo: "Cómo entrenar a tu dragón 2",
        imagen: "https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      },
      {
        id: "lilo_y_stich_2025",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      }
    ]
  },

  como_entrenar_a_tu_dragon: {
    id: "como_entrenar_a_tu_dragon",
    titulo: "Cómo entrenar a tu dragón",
    video: "https://dl.dropbox.com/scl/fi/x3ok1brr670j2ax3i1nx9/Como-entrenar-a-tu-dragon-2025.mp4?rlkey=hklv7tg3xvxqx37lujhjlwq4k&st=",
    poster: "https://image.tmdb.org/t/p/w780/ovZasZ9EeZcp6UsrElkQ63hFCd.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En la escarpada isla de Mema, donde vikingos y dragones han sido enemigos acérrimos durante generaciones, Hipo se desmarca desafiando siglos de tradición cuando entabla amistad con Desdentao, un temido dragón Furia Nocturna. Su insólito vínculo revela la verdadera naturaleza de los dragones y desafía los cimientos de la sociedad vikinga.",
    anio: "2025",
    duracion: "2h 05min",
    calificacion: "81%",
    genero: "Aventura • Ciencia ficcion • Familia",
    director: "Dean DeBlois",
    reparto: "Mason Thames, Nico Parker, Gerard Butler",
    estreno: "13/06/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "como_entrenar_a_tu_dragon_1",
        titulo: "Cómo entrenar a tu dragón",
        imagen: "https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon_2",
        titulo: "Cómo entrenar a tu dragón 2",
        imagen: "https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg"
      },
      {
        id: "",
        titulo: "Cómo entrenar a tu dragón 3",
        imagen: "https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg"
      },
      {
        id: "el_gato_con_botas",
        titulo: "El gato con botas",
        imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg"
      },
      {
        id: "garfield_fuera_de_casa",
        titulo: "Garfield: Fuera de casa",
        imagen: "https://image.tmdb.org/t/p/w300/p6AbOJvMQhBmffd0PIv0u8ghWeY.jpg"
      },
      {
        id: "el_origen_de_los_guardiane",
        titulo: "El origen de los guardianes",
        imagen: "https://image.tmdb.org/t/p/w300/kDVXsTZhssIJeZIMBC33MqmgkrQ.jpg"
      }
    ]
  },

  codigo_8: {
    id: "codigo_8",
    titulo: "Codigo 8: Parte 1",
    video: "https://dl.dropbox.com/scl/fi/wgy6blkygtsxdbwawv5c3/Code.8.Renegados2019.1080P-Dual-Lat.mp4?rlkey=6womxdw43w8leazr39c0210ts&st=",
    poster: "https://image.tmdb.org/t/p/w780/wlnDNMQlnwl5ETlVY6n9CEtR5s0.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ubXn3H2PWkoqH9TIBrWRJSKzuaD.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "",
    anio: "2019",
    duracion: "1h 38min",
    calificacion: "63%",
    genero: "Accion • Crimen • Ciencia ficción",
    director: "Jeff Chan",
    reparto: "Robbie Amell, Esteban Amell, Kari Matchett",
    estreno: "13/12/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "codigo_8_parte_2",
        titulo: "Codigo 8: Parte 2",
        imagen: "https://image.tmdb.org/t/p/w300/dg6WrJUIQLU4pssA4ZucGfdOj8.jpg"
      },
      {
        id: "bala_perdida_2",
        titulo: "La bala perdida",
        imagen: "https://image.tmdb.org/t/p/w300/4F2lHozzpR6kzsKJluUidDsNfbY.jpg"
      },
      {
        id: "damsel",
        titulo: "Damsel",
        imagen: "https://image.tmdb.org/t/p/w300/gh7oa9IKlu5yMveemyJkzLfopuB.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "g20",
        titulo: "G20",
        imagen: "https://image.tmdb.org/t/p/w300/xihssRPgRDZ7xwIjx3xuPTnqPfU.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      }
    ]
  },

  codigo_8_parte_2: {
    id: "codigo_8_parte_2",
    titulo: "Codigo 8: Parte 2",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Ffeb24%2FVer%20C%C3%B3digo%208-%20Renegados%20(Parte%20II)%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/hKwMOnf7I2061ZsgBqctNaqSiz3.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/dg6WrJUIQLU4pssA4ZucGfdOj8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una chica que lucha por buscar justicia para su hermano asesinado por policías corruptos. Ella solicita la ayuda de un ex convicto y su ex pareja, pero deben enfrentar a un sargento de policía muy respetado y bien protegido.",
    anio: "2024",
    duracion: "1h 40min",
    calificacion: "64%",
    genero: "Accion • Crimen • Ciencia ficción",
    director: "Jeff Chan",
    reparto: "Robbie Amell, Esteban Amell, Sirena Gulamgaus",
    estreno: "28/02/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "codigo_8",
        titulo: "Codigo 8: Parte 1",
        imagen: "https://image.tmdb.org/t/p/w300/AtQDTlj3MFOXJd5C9OopaRo3rRo.jpg"
      },
      {
        id: "el_sindicato",
        titulo: "El sindicato",
        imagen: "https://image.tmdb.org/t/p/w300/1UHp2QEBPnTrcx0i6aYw6jWtDbI.jpg"
      },
      {
        id: "finestkind",
        titulo: "Finestkind: Entre hermanos",
        imagen: "https://image.tmdb.org/t/p/w300/90D6sXfbXKhDpd4S1cHICdAe8VD.jpg"
      },
      {
        id: "el_asesino",
        titulo: "El asesino",
        imagen: "https://image.tmdb.org/t/p/w300/wXbAPrZTqJzlqmmRaUh95DJ5Lv1.jpg"
      },
      {
        id: "nyad",
        titulo: "Nyad",
        imagen: "https://image.tmdb.org/t/p/w300/eh1IjDZfDRjgv5NzMBkjN1GzKgy.jpg"
      },
      {
        id: "alarum_codigo_letal",
        titulo: "Alarum: Código letal",
        imagen: "https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg"
      }
    ]
  },

  como_matar_a_mama: {
    id: "como_matar_a_mama",
    titulo: "¿Cómo matar a mamá?",
    video: "https://dl.dropbox.com/scl/fi/xvnts72ky3wm8pfcbeqs8/Como-matar-a-mama.2023.1080p.LAT.cinecalidad.com.mx.mp4?rlkey=rradp9eh0b5lhd091btqk0kxm&st=",
    poster: "https://image.tmdb.org/t/p/w780/xKWKsL7VgaN5Ep697TB3H03lglM.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/zQch27gPbimK96vtbrEq4jFHg2D.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Luego de enterarse de una verdad irreversible, Camila, Teté y Margo deben abandonar sus ajetreadas vidas para idear un plan para acabar con la vida de su madre Rosalinda.",
    anio: "2023",
    duracion: "1h 35min",
    calificacion: "72%",
    genero: "Comedia • Drama",
    director: "Jose Ramon Chavez Delgado",
    reparto: "Blanca  Guerra, Diana Bovio, Ximena Sariñana",
    estreno: "10/03/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "barbie",
        titulo: "Barbie",
        imagen: "https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg"
      },
      {
        id: "chicas_malas_2024",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg"
      },
      {
        id: "nonnas",
        titulo: "Nonnas",
        imagen: "https://image.tmdb.org/t/p/w300/6YsEHhqgT6c8nJlS1TL1Zyrxwgw.jpg"
      },
      {
        id: "unos_suegro_de_armas_tomar",
        titulo: "Unos suegros de armas tomar",
        imagen: "https://image.tmdb.org/t/p/w300/5dliMQ2ODbGNoq0hlefdnuXQxMw.jpg"
      },
      {
        id: "desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      },
      {
        id: "echo_valley",
        titulo: "Echo Valley",
        imagen: "https://image.tmdb.org/t/p/w300/1E4WCgTodyS7zo8pSp1gZlPO0th.jpg"
      }
    ]
  },

  contraataque: {
    id: "contraataque",
    titulo: "Contraataque",
    video: "https://dl.dropbox.com/scl/fi/wm7rassc0i8dxnhpzh51m/Contraataque-2025.mp4?rlkey=jgm6n2fpwx2nfkv6y752aqchn&st=",
    poster: "https://image.tmdb.org/t/p/w780/deUWVEgNh2IGjShyymZhaYP40ye.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kxnFdLJhi37ZVFDCL1ka0yeQVU5.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "SinopsisEn una misión de rescate de rehenes, el capitán Guerrero y sus soldados de élite sufren una emboscada de un despiadado cártel de la droga.",
    anio: "2025",
    duracion: "1h 25min",
    calificacion: "77%",
    genero: "Acción • Suspenso • Aventura",
    director: "Chava Cartas",
    reparto: "Luis Alberti, Noe Hernandez, Leonardo Alonso",
    estreno: "28/02/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "la_viuda_negra",
        titulo: "La viuda negra",
        imagen: "https://image.tmdb.org/t/p/w300/uuabL0qp3zygLDEjImbPiWR9j2e.jpg"
      },
      {
        id: "heroico",
        titulo: "Heroico",
        imagen: "https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"
      },
      {
        id: "corazones_de_acero",
        titulo: "Corazones de acero",
        imagen: "https://image.tmdb.org/t/p/w300/kbtH5G8L8REzy72LkLmKYoBVaGv.jpg"
      },
      {
        id: "warfare_tiempo_de_guerra",
        titulo: "Warfare. Tiempo de guerra",
        imagen: "https://image.tmdb.org/t/p/w300/fkVpNJugieKeTu7Se8uQRqRag2M.jpg"
      },
      {
        id: "rehen",
        titulo: "iRehén!",
        imagen: "https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"
      },
      {
        id: "la_madre",
        titulo: "La Madre",
        imagen: "https://image.tmdb.org/t/p/w300/A8BXrFD0FIH2iVbOoTEw7DxnHCb.jpg"
      }
    ]
  },

  corazon_delator: {
    id: "corazon_delator",
    titulo: "Corazón delator",
    video: "https://dl.dropbox.com/scl/fi/vmh407hvi95cmvo5enay4/Corazon-delator-2025.mp4?rlkey=3yne2josbq2x7wlijg0yfjfbf&st=",
    poster: "https://image.tmdb.org/t/p/w780/leveUHJVT3kTomCjjqhJ0MMOuxw.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un amor que trasciende la muerte. Juan Manuel, un empresario frío, recibe el corazón de Pedro, un hombre humilde. Al investigar el origen de su donante, conoce a Valeria, la viuda, y se enamora de ella. Ocultando su identidad, lucha por salvar el barrio de Pedro, sin revelar que en su pecho late su corazón.",
    anio: "2025",
    duracion: "1h 29min",
    calificacion: "00%",
    genero: "Romance • Drama",
    director: "Marcos Carnevale",
    reparto: "Benjamin Vicuña, Julieta Diaz, Facundo Espinosa",
    estreno: "30/03/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "dias_365_2",
        titulo: "365 Dias 2: Aquel día",
        imagen: "https://image.tmdb.org/t/p/w300/jBpqADo9XAKaecvI3f0J4hRAEyO.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "after_4",
        titulo: "After 4: Aquí acaba todo",
        imagen: "https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg"
      },
      {
        id: "almas_marcadas",
        titulo: "Almas marcadas: Rule + Shaw",
        imagen: "https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg"
      },
      {
        id: "tierra_baja",
        titulo: "Tierra Baja",
        imagen: "https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"
      }
    ]
  },

  corazones_de_acero: {
    id: "corazones_de_acero",
    titulo: "Corazones de acero",
    video: "https://dl.dropbox.com/scl/fi/s5nyzg18d9qhwadnruv4l/Fury.2014.hd-latino-e-ingles-subt.mp4?rlkey=dz6egrkdrh0es46i4d6oi8vs9&st=",
    poster: "https://image.tmdb.org/t/p/w780/5ENhq5KEmflufK7aXXaquG1l2vb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kbtH5G8L8REzy72LkLmKYoBVaGv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Abril de 1945. Al mando del veterano sargento Wardaddy, un pelotón de cinco soldados americanos a bordo de un carro de combate -el Fury- ha de luchar contra un ejército nazi al borde de la desesperación, pues los alemanes saben que su derrota estaba ya cantada por aquel entonces. ",
    anio: "2014",
    duracion: "2h 14min",
    calificacion: "89%",
    genero: "Guerra • Bélica • Drama • Acción",
    director: "David Ayer",
    reparto: "Brad Pitt, Shia LaBeouf. Logan Lerman",
    estreno: "17/10/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "hasta_el_ultimo_hombre",
        titulo: "Hasta el ultimo hombre",
        imagen: "https://image.tmdb.org/t/p/w300/v5ZCVgxlFmlpFnR9DWVUkOVw4hW.jpg"
      },
      {
        id: "heroico",
        titulo: "Heroico",
        imagen: "https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"
      },
      {
        id: "napoleon",
        titulo: "Napoleon",
        imagen: "https://image.tmdb.org/t/p/w300/zoo5k1Rsx4Bel0ng9G8yRwku2ND.jpg"
      },
      {
        id: "la_madre",
        titulo: "La Madre",
        imagen: "https://image.tmdb.org/t/p/w300/A8BXrFD0FIH2iVbOoTEw7DxnHCb.jpg"
      },
      {
        id: "radical",
        titulo: "Radical",
        imagen: "https://image.tmdb.org/t/p/w300/eSatbygYZp8ooprBHZdb6GFZxGB.jpg"
      },
      {
        id: "el_planeta_de_los_simios_3",
        titulo: "El planeta de los simios 3: La guerra",
        imagen: "https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg"
      }
    ]
  },

  crater_un_viaje_inolvidable: {
    id: "crater_un_viaje_inolvidable",
    titulo: "Cráter: Un viaje inolvidable",
    video: "https://dl.dropbox.com/scl/fi/k0mfodc5lt9anzzq9x65w/Crater-un-viaje-inolvidable-2023.mp4?rlkey=861xx2vypv5psonie0uz4hs8f&st=",
    poster: "https://image.tmdb.org/t/p/w780/wUMDnvi1xa8iMEpRDVAXrcAtqus.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ppEvMrq2nvV9DfBHuCRilf2MBnm.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cinco chicos de una colonia minera lunar roban un rover para poder explorar un misterioso cráter.",
    anio: "2023",
    duracion: "1h 44min",
    calificacion: "67%",
    genero: "Acción • Aventura • Disney • Familia • Ciencia ficción",
    director: "Kyle Patrick Alvarez",
    reparto: "Billy Barratt, Orson Hong, Thomas Boyce",
    estreno: "12/03/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      },
      {
        id: "rapidos_y_furiosos_x",
        titulo: "Rápidos y furiosos X",
        imagen: "https://image.tmdb.org/t/p/w300/x3zlm6VxPvVrYWE3bHkYUQMR798.jpg"
      },
      {
        id: "novocaine",
        titulo: "Novocaine: Sin dolor",
        imagen: "https://image.tmdb.org/t/p/w300/6YbTJhN5GJQOlZ1IyRiCyhKSiJE.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El planeta de los simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/kkFn3KM47Qq4Wjhd8GuFfe3LX27.jpg"
      }
    ]
  },

  /*D*/

  damsel: {
    id: "damsel",
    titulo: "Damsel",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmar24%2FVer%20Damsel%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/deLWkOLZmBNkm8p16igfapQyqeq.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/gh7oa9IKlu5yMveemyJkzLfopuB.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La boda de una joven con un príncipe encantador se convierte en una encarnizada lucha por sobrevivir cuando la ofrecen como sacrificio a una dragona escupefuego.",
    anio: "2024",
    duracion: "1h 49min",
    calificacion: "70%",
    genero: "Acción • Fantasía • Aventura",
    director: "Juan Carlos Fresnadillo",
    reparto: "Millie Bobby Brown, Ray Winstone, Nick Robinson",
    estreno: "08/03/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "bad_boys",
        titulo: "Bad boys: Dos policias rebeldes",
        imagen: "https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El planeta de los simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/kkFn3KM47Qq4Wjhd8GuFfe3LX27.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      }
    ]
  },

  demon_slaye_kimetsu_no_yaiba_castillo_infinito: {
    id: "demon_slaye_kimetsu_no_yaiba_castillo_infinito",
    titulo: "Demon Slayer: Kimetsu no Yaiba – Castillo Infinito",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/1RgPyOhN4DRs225BGTlHJqCudII.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fWVSwgjpT2D78VUh6X8UBd2rorW.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El Cuerpo de Cazadores de Demonios se adentra en el Castillo Infinito, donde Tanjiro, Nezuko y Hashira se enfrentan a terroríficos demonios de rango superior en una lucha desesperada mientras comienza la batalla final contra Muzan Kibutsuji.",
    anio: "2025",
    duracion: "0h 008min",
    calificacion: "78%",
    genero: "Anime • Animacion • Accion • Fantasia",
    director: "Haruo Sotozaki",
    reparto: "Iván Bastidas, Marc Winslow, José Luis Piedra",
    estreno: "18/07/2025",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  desaparecidos_en_la_noche: {
    id: "desaparecidos_en_la_noche",
    titulo: "Desaparecidos en la noche",
    video: "https://dl.dropbox.com/scl/fi/i44ucg82wmidxhg4k7n6q/Desaparecidos.En.La.Noche.2024.1080P-Dual-Lat.mkv?rlkey=v4frj42ei4286likd0tm9mplm&st=",
    poster: "https://image.tmdb.org/t/p/w780/kairgu1N35rYxW6JNjzRTyqNNfy.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un padre inmerso en un divorcio problemático emprende una peligrosa misión cuando sus hijos desaparecen sin dejar rastro de su aislada casa de campo.",
    anio: "2024",
    duracion: "1h 32min",
    calificacion: "77%",
    genero: "Drama • Misterio • Suspenso",
    director: "Renato de Maria",
    reparto: "Riccardo Scamarcio, Annabelle Wallis, Massimiliano Gall",
    estreno: "11/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "como_matar_a_mama",
        titulo: "¿Cómo matar a mamá?",
        imagen: "https://image.tmdb.org/t/p/w300/Af6hIxZhKzUNr02TZwethdrk5rP.jpg"
      },
      {
        id: "asesino_serial",
        titulo: "Asesino serial",
        imagen: "https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg"
      },
      {
        id: "rehen",
        titulo: "iRehén!",
        imagen: "https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"
      },
      {
        id: "nada_que_ver",
        titulo: "Nada qué ver",
        imagen: "https://image.tmdb.org/t/p/w300/ofnOwcG9l1DuGl7vB45JHsfSlR6.jpg"
      },
      {
        id: "el_guason_2",
        titulo: "El guasón 2",
        imagen: "https://image.tmdb.org/t/p/w300/fCQyAQ2K1N1RM5n79ZyCLRSgZuz.jpg"
      },
      {
        id: "la_evaluacion",
        titulo: "La evaluación",
        imagen: "https://image.tmdb.org/t/p/w300/rCGwGWI4a2EaNQCyTe4vDfoiMtk.jpg"
      }
    ]
  },
  
  destino_final: {
    id: "destino_final",
    titulo: "Destino final",
    video: "https://dl.dropbox.com/scl/fi/inbncscoq93wyr90a74wa/Final.destination.2000.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=i7r5fi99tzdcyia328uph64cb&st=",
    poster: "https://image.tmdb.org/t/p/w780/rBF9AumHuVdANpraeB8GoAYyN5x.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/2g4Jz0Jr54aYCpFLWKYDo5VZvzN.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Al subir a un avión con destino a Paris, junto con sus compañeros de clase, Alex tiene una premonición, por lo que desembarca justo antes de despegar junto a seis de sus amigos y una profesora. Poco después el aparato explota en el aire. El grupo de supervivientes se verá perseguido por la dama de la guadaña, la propia muerte, que no se dará por vencida.",
    anio: "2000",
    duracion: "1h 37min",
    calificacion: "65%",
    genero: "Terror",
    director: "James Wong",
    reparto: "Devon Sawa, Ali Larter, Kerr Smith",
    estreno: "21/03/2000",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final_2",
        titulo: "Destino final 2",
        imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg"
      },
      {
        id: "destino_final_3",
        titulo: "Destino final 3",
        imagen: "https://image.tmdb.org/t/p/w300/p7ARuNKUGPGvkBiDtIDvAzYzonX.jpg"
      },
      {
        id: "destino_final_4",
        titulo: "Destino final 4",
        imagen: "https://image.tmdb.org/t/p/w300/5vxXrr1MqGsT4NNeRITpfDnl4Rq.jpg"
      },
      {
        id: "destino_final_5",
        titulo: "Destino final 5",
        imagen: "https://image.tmdb.org/t/p/w300/Akx1Po4ZLetOWfYJhQf75tbhTtK.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg"
      },
      {
        id: "un_lugar_en_silencio",
        titulo: "Un lugar en silencio",
        imagen: "https://image.tmdb.org/t/p/w300/hE51vC3iZJCqFecLzIO1Q4eYXqK.jpg"
      }
    ]
  },

  destino_final_2: {
    id: "destino_final_2",
    titulo: "Destino final 2",
    video: "https://dl.dropbox.com/scl/fi/b6kovpfj3l9uuncm87tis/Final.destination.2.2003.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=fepv6ygkaezwcfryerpn1cicw&st=",
    poster: "https://image.tmdb.org/t/p/w780/tKnmfO5lAyu8hpTZMyMaI4lhZpJ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Kimberly Corman se va de vacaciones con sus amigos Dano , Shaina y Frankie hacia Daytona Beach. De repente, un camión cargado de troncos pierde el control al reventarse la cadena que los sostiene, cayendo y rodando por la autopista donde se origina un trágico accidente, matando a todos los que se encuentran a su paso.",
    anio: "2003",
    duracion: "1h 30min",
    calificacion: "64%",
    genero: "Terror",
    director: "David R. Ellis",
    reparto: "Ali Larter, AJ Cook, Michael Landes",
    estreno: "31/01/2003",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final",
        titulo: "Destino final",
        imagen: "https://image.tmdb.org/t/p/w300/6F3MEcGHeMAMxledi7vQfqkZRkc.jpg"
      },
      {
        id: "destino_final_3",
        titulo: "Destino final 3",
        imagen: "https://image.tmdb.org/t/p/w300/p7ARuNKUGPGvkBiDtIDvAzYzonX.jpg"
      },
      {
        id: "destino_final_4",
        titulo: "Destino final 4",
        imagen: "https://image.tmdb.org/t/p/w300/5vxXrr1MqGsT4NNeRITpfDnl4Rq.jpg"
      },
      {
        id: "destino_final_5",
        titulo: "Destino final 5",
        imagen: "https://image.tmdb.org/t/p/w300/Akx1Po4ZLetOWfYJhQf75tbhTtK.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      }
    ]
  },

  destino_final_3: {
    id: "destino_final_3",
    titulo: "Destino final 3",
    video: "https://dl.dropbox.com/scl/fi/r3pdgd5e0n8nlwi4p3hwz/Final.destination.3.2006.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=mnqb0pywg4offpvghzmkr0enx&st=",
    poster: "https://image.tmdb.org/t/p/w780/nSV1NIAK0Sp5dM1oiobtqbJ8Jrv.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5sSZBolbPCxCVXabzmL0bKWLgsv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una estudiante del instituto tiene una premonición sobre una tragedia en un parque de atracciones local, por lo que decide no montarse en una montaña rusa que presiente va a descarrilar...",
    anio: "2006",
    duracion: "1h 32min",
    calificacion: "75%",
    genero: "Terror",
    director: "James Wong",
    reparto: "María Isabel Winstead, Ryan Merriman, Kris Lemche",
    estreno: "10/06/2006",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final",
        titulo: "Destino final",
        imagen: "https://image.tmdb.org/t/p/w300/6F3MEcGHeMAMxledi7vQfqkZRkc.jpg"
      },
      {
        id: "destino_final_2",
        titulo: "Destino final 2",
        imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg"
      },
      {
        id: "destino_final_4",
        titulo: "Destino final 4",
        imagen: "https://image.tmdb.org/t/p/w300/5vxXrr1MqGsT4NNeRITpfDnl4Rq.jpg"
      },
      {
        id: "destino_final_5",
        titulo: "Destino final 5",
        imagen: "https://image.tmdb.org/t/p/w300/Akx1Po4ZLetOWfYJhQf75tbhTtK.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg"
      },
      {
        id: "terrifier_3",
        titulo: "Terrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      }
    ]
  },

  destino_final_4: {
    id: "destino_final_4",
    titulo: "Destino final 4",
    video: "https://dl.dropbox.com/scl/fi/xag0hb8gpeo1g43xacyo4/The.final.destination.2009.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=lcw7li325grckah9vbe5psp58&st=",
    poster: "https://image.tmdb.org/t/p/w780/6LGX6bPhMEsuwi8CsfSBqnB1qnN.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/8b1tsUQW8hogJRi6FFHHfO7D1fu.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Nick O’Bannon y unos amigos acuden a un circuito de carreras para presenciar una prueba del Nascar. Durante ésta tiene lugar un terrible accidente que conlleva desastrosas consecuencias para el estadio. Pero Nick descubre que se trata de sólo una visión de algo que está a punto de suceder, y junto con otras doce personas consigue salir del recinto y escapar de una tragedia segura.",
    anio: "2009",
    duracion: "1h 21min",
    calificacion: "56%",
    genero: "Terror",
    director: "David R. Ellis",
    reparto: "Bobby Campo, Shantel VanSanten, Nick Zano",
    estreno: "28/08/2009",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final",
        titulo: "Destino final",
        imagen: "https://image.tmdb.org/t/p/w300/6F3MEcGHeMAMxledi7vQfqkZRkc.jpg"
      },
      {
        id: "destino_final_2",
        titulo: "Destino final 2",
        imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg"
      },
      {
        id: "destino_final_3",
        titulo: "Destino final 3",
        imagen: "https://image.tmdb.org/t/p/w300/p7ARuNKUGPGvkBiDtIDvAzYzonX.jpg"
      },
      {
        id: "destino_final_5",
        titulo: "Destino final 5",
        imagen: "https://image.tmdb.org/t/p/w300/Akx1Po4ZLetOWfYJhQf75tbhTtK.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg"
      },
      {
        id: "martyrs",
        titulo: "Martyrs",
        imagen: "https://image.tmdb.org/t/p/w300/5kymocKK0SfyEEV0ohNEBz1lxNx.jpg"
      }
    ]
  },

  destino_final_5: {
    id: "destino_final_5",
    titulo: "Destino final 5",
    video: "https://dl.dropbox.com/scl/fi/xag0hb8gpeo1g43xacyo4/The.final.destination.2009.1080p-dual-lat-cinecalidad.rs.mp4?rlkey=lcw7li325grckah9vbe5psp58&st=",
    poster: "https://image.tmdb.org/t/p/w780/xjp3ySB9qNqQj5Vu6UZ4L7nD6qd.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xMBIeENKIZq3V0undgvaZbFdMw2.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Sam Lawton tiene una premonición sobre la destrucción de un puente colgante que causaría su muerte y la de otras personas. La visión se hace realidad, pero Sam se las arregla para salvarse a sí mismo y a algunos otros de la catastrófica tragedia. Sin embargo, Sam y su novia Molly descubren que no están realmente a salvo: la muerte los persigue a ellos y a los que sobrevivieron al horrible accidente... Quinta entrega de la popular serie de terror Destino final.",
    anio: "2011",
    duracion: "1h 21min",
    calificacion: "61%",
    genero: "Terror",
    director: "Steven Quale",
    reparto: "Nicholas D'Agosto, Emma Bell, Miles Fisher",
    estreno: "12/08/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final",
        titulo: "Destino final",
        imagen: "https://image.tmdb.org/t/p/w300/6F3MEcGHeMAMxledi7vQfqkZRkc.jpg"
      },
      {
        id: "destino_final_2",
        titulo: "Destino final 2",
        imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg"
      },
      {
        id: "destino_final_3",
        titulo: "Destino final 3",
        imagen: "https://image.tmdb.org/t/p/w300/p7ARuNKUGPGvkBiDtIDvAzYzonX.jpg"
      },
      {
        id: "destino_final_4",
        titulo: "Destino final 4",
        imagen: "https://image.tmdb.org/t/p/w300/5vxXrr1MqGsT4NNeRITpfDnl4Rq.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg"
      },
      {
        id: "el_exorcismo_de_georgetown",
        titulo: "El exorcista de Georgetown",
        imagen: "https://image.tmdb.org/t/p/w300/ioQCdjn2YPfAJMfJlgzNdXgYZrr.jpg"
      }
    ]
  },

  destino_final_6: {
    id: "destino_final_6",
    titulo: "Destino final 6: Lazos de sangre",
    video: "https://dl.dropbox.com/scl/fi/u1i6cu71xhjirqcmf4yv0/Destino-final-6-Lazos-de-sangre-.2025.1080p-dual-lat.mp4?rlkey=ghfloc7si76zv58ufqysq4btj&st=",
    poster: "https://image.tmdb.org/t/p/w780/bse2E5xgKcsL6w8h2efqpecvnxV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Acosada por una violenta pesadilla recurrente, la estudiante universitaria Stefanie se dirige a casa para localizar a la única persona que podría ser capaz de romper el ciclo y salvar a su familia de la espeluznante muerte que inevitablemente les espera a todos.",
    anio: "2025",
    duracion: "1h 49min",
    calificacion: "82%",
    genero: "Terror",
    director: "Zach Lipovsky & Adán B. Stein",
    reparto: "Kaitlyn Santa Juana, Teo Briones, Rya Kihlstedt",
    estreno: "16/05/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "destino_final",
        titulo: "Destino final",
        imagen: "https://image.tmdb.org/t/p/w300/6F3MEcGHeMAMxledi7vQfqkZRkc.jpg"
      },
      {
        id: "destino_final_2",
        titulo: "Destino final 2",
        imagen: "https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg"
      },
      {
        id: "destino_final_3",
        titulo: "Destino final 3",
        imagen: "https://image.tmdb.org/t/p/w300/p7ARuNKUGPGvkBiDtIDvAzYzonX.jpg"
      },
      {
        id: "destino_final_4",
        titulo: "Destino final 4",
        imagen: "https://image.tmdb.org/t/p/w300/5vxXrr1MqGsT4NNeRITpfDnl4Rq.jpg"
      },
      {
        id: "destino_final_5",
        titulo: "Destino final 5",
        imagen: "https://image.tmdb.org/t/p/w300/Akx1Po4ZLetOWfYJhQf75tbhTtK.jpg"
      },
      {
        id: "el_exorcista_creyente",
        titulo: "El exorcista creyentes",
        imagen: "https://image.tmdb.org/t/p/w300/aNoNB5jWIzqcBqHEYzW232B2ktx.jpg"
      }
    ]
  },

  detonantes: {
    id: "detonantes",
    titulo: "Detonantes",
    video: "https://dl.dropbox.com/scl/fi/0dnvfyz78ouo5w3t39k5x/Detonante-2024.1080p-dual-lat.mp4?rlkey=meddp3xkkb5d3x4tbb0xem533&st=",
    poster: "https://image.tmdb.org/t/p/w780/eIk878ea0umT07VbWYpeH0GTid8.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una soldado de las fuerzas especiales descubre una peligrosa conspiración cuando regresa a casa en busca de respuestas sobre la muerte de su padre.",
    anio: "2024",
    duracion: "1h 46min",
    calificacion: "57%",
    genero: "Acción",
    director: "Mouly Surya",
    reparto: "Jessica Alba, Anthony Michael Hall, Campana de tono",
    estreno: "21/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "damsel",
        titulo: "Damsel",
        imagen: "https://image.tmdb.org/t/p/w300/gh7oa9IKlu5yMveemyJkzLfopuB.jpg"
      },
      {
        id: "la_bala_perdida_3",
        titulo: "La bala perdida 3",
        imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"
      },
      {
        id: "alarum_codigo_letal",
        titulo: "Alarum: Código letal",
        imagen: "https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg"
      },
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera Del Mapa",
        imagen: "https://image.tmdb.org/t/p/w300/77dlklwA1VJOLCqIhhmkmS39BLH.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      },
      {
        id: "la_fuente_de_la_eterna_juventud",
        titulo: "La fuente de la eterna juventud",
        imagen: "https://image.tmdb.org/t/p/w300/nJ9qnZLhmj6wD3NgOe6lKoXJQMx.jpg"
      }
    ]
  },

  diario_de_mi_vagina: {
    id: "diario_de_mi_vagina",
    titulo: "Diario de mi vagina",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmar24%2FVer%20Diario%20de%20mi%20vagina%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/eNUFAIm3Wr4AvpVZThxVb9BlXf0.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7PzGmlaai6mRUslfrdBhfXjfA1J.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de que Carlos tiene la tarea de cuidar a Paola, ambos personajes deben resolver sus diferencias para que su tiempo juntos sea más placentero. A medida que pasa el tiempo, se enfrentan a lecciones sobre la confianza, el respeto mutuo y quizás el amor.",
    anio: "2024",
    duracion: "1h 45min",
    calificacion: "70%",
    genero: "Drama • Comedia",
    director: "Molly McGlynn",
    reparto: "Maddie Ziegler, Emily Hampshire, D'Pharaoh Woon-A-Tai",
    estreno: "23/04/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "chicas_malas_2024",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Dias",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "romper_el_circulo",
        titulo: "Romper el circulo",
        imagen: "https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"
      },
      {
        id: "el_es_asi",
        titulo: "El es asi",
        imagen: "https://image.tmdb.org/t/p/w300/gTboh2Tf7zKlXWJk4UdOL1G8ki7.jpg"
      },
      {
        id: "doblemente_embarazada_2",
        titulo: "Doblemente Embarazada 2",
        imagen: "https://image.tmdb.org/t/p/w300/mNkAOFyb4TV2gTSc92jx2O9evtj.jpg"
      },
      {
        id: "finestkind",
        titulo: "Finestkind: Entre hermanos",
        imagen: "https://image.tmdb.org/t/p/w300/90D6sXfbXKhDpd4S1cHICdAe8VD.jpg"
      }
    ]
  },

  doctor_strange: {
    id: "doctor_strange",
    titulo: "Doctor strange: El hechicero supremo",
    video: "https://dl.dropbox.com/scl/fi/7j3crrxjyup90dc45fu7n/Doctor.strange.2016.1080P-Dual-Lat.mp4?rlkey=uyr3jq0lcx32qt8870r209g2d&st=",
    poster: "https://image.tmdb.org/t/p/w780/tqX1kakpqHNMFOeYbT4XZgudn7x.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/sOsvKTJS0XwtfLsNMO3C0CVWJ4u.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La vida del famoso neurocirujano Stephen Strange cambia radicalmente cuando un accidente le impide el uso de sus manos y se ve forzado a buscar una cura en un misterioso enclave. Inmediatamente aprende que no solo es un sanatorio... es también el frente de batalla contra oscuras fuerzas, y debe elegir entre volver a su antigua vida o defender al mundo como el más poderoso hechicero del momento.",
    anio: "2016",
    duracion: "1h 55min",
    calificacion: "86%",
    genero: "Accion • Marvel • Fantasía",
    director: "Scott Derrickson",
    reparto: "Benedicto Cumberbatch, Chiwetel Ejiofor, Raquel McAdams",
    estreno: "13/10/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "doctor_strange_2",
        titulo: "Doctor strange 2: En el multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/xu0RftYPT4crY4ZSf9SMa5UM8dr.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        href: "wandavision (2021).html",
        titulo: "Wandavisión",
        imagen: "https://image.tmdb.org/t/p/w300/frobUz2X5Pc8OiVZU8Oo5K3NKMM.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/rkLhaNa37IwzWis8rzWMAYTCdIK.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "thor_ragnarok3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/rzRwTcFvttcN1ZpX2xv4j3tSdJu.jpg"
      }
    ]
  },

  doctor_strange_2: {
    id: "doctor_strange_2",
    titulo: "Doctor Strange 2: El multiverso de la locura",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/51wwXoVKpS6oJMbz03qvN0Hxt99.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qd7NMF0SyUCx5IS6nTOOUPdTwvB.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Viaja a lo desconocido con el Doctor Strange, quien, con la ayuda de tanto antiguos como nuevos aliados místicos, recorre las complejas y peligrosas realidades alternativas del multiverso para enfrentarse a un nuevo y misterioso adversario.",
    anio: "2022",
    duracion: "0h 008min",
    calificacion: "00%",
    genero: "Accion • Marvel • Fantasía",
    director: "Sam Raimi",
    reparto: "Benedicto Cumberbatch, Xóchitl Gómez, Elizabeth Olsen",
    estreno: "06/03/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "doctor_strange",
        titulo: "Doctor strange: El hechicero supremo",
        imagen: "https://image.tmdb.org/t/p/w300/sOsvKTJS0XwtfLsNMO3C0CVWJ4u.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "pantera_negra2",
        titulo: "Pantera Negra: Wakanda por siempre",
        imagen: "https://image.tmdb.org/t/p/w300/qUhjmU8P2OA7AG4IgqXzbwvl4Tq.jpg"
      },
      {
        id: "capitan_america_3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "spider_man1",
        titulo: "Spider-Man: Regreso a casa",
        imagen: "https://image.tmdb.org/t/p/w300/81qIJbnS2L0rUAAB55G8CZODpS5.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg"
      }
    ]
  },
  donde_esta_el_fantasma: {
    id: "donde_esta_el_fantasma",
    titulo: "¿Donde esta el fantasma?",
    video: "https://dl.dropbox.com/scl/fi/lyyx8ahi44zfsgxu0cx1r/Y.D-nde.Esta.El.Fantasma.2013.1080P-Dual-Lat.mp4?rlkey=2leb7pw5f6d83iupbg7ttvuis&st=",
    poster: "https://image.tmdb.org/t/p/w780/1KPRGZlb0fjoIgHATwnmqG9DiDl.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pAVGfrADDvKMgoZnJLSCiLBCCiG.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Malcolm y Kisha se mudan a la casa de sus sueños, pero pronto descubren que un demonio también reside allí. Cuando Kisha es poseída, Malcolm, decidido a mantener su vida sexual en orden, recurre a un sacerdote, un vidente y un equipo de cazafantasmas en busca de ayuda en esta parodia de todas las películas de metraje encontrado/estilo documental estrenadas en los últimos años.",
    anio: "2013",
    duracion: "1h 26min",
    calificacion: "88%",
    genero: "Comedia • Terror",
    director: "Michael Tiddes",
    reparto: "Marlon Wayans, Esencia Atkins, Nick Swardson",
    estreno: "21/03/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "donde_esta_el_fantasma_2",
        titulo: "¿Donde esta el fantasma? 2",
        imagen: "https://image.tmdb.org/t/p/w300/vRbDuqlmGPM9wGZ3VwbrjQu16Oa.jpg"
      },
      {
        id: "ricky_el_impostor",
        titulo: "Ricky Stanicky: El impostor",
        imagen: "https://image.tmdb.org/t/p/w300/oJQdLfrpl4CQsHAKIxd3DJqYTVq.jpg"
      },
      {
        id: "no_me_la_toquen",
        titulo: "No Me La Toquen",
        imagen: "https://image.tmdb.org/t/p/w300/yEsYJyBsnDdMUbsehxIofMa9Oh7.jpg"
      },
      {
        id: "mi_abuelo_es_un_peligro",
        titulo: "Mi abuelo es un peligro",
        imagen: "https://image.tmdb.org/t/p/w300/7r9pn1g3lY95DjiwzxpmNqlJzeO.jpg"
      },
      {
        id: "los_instigadores",
        titulo: "Los instigadores",
        imagen: "https://image.tmdb.org/t/p/w300/zDWHsjfdsvZZkMWo1u1Ep7Y77FQ.jpg"
      },
      {
        id: "scary_movie",
        titulo: "Scary Movie",
        imagen: "https://image.tmdb.org/t/p/w300/bbfF5sJux8UOPBEdSX3SawB0jQG.jpg"
      }
    ]
  },

  donde_esta_el_fantasma_2: {
    id: "donde_esta_el_fantasma_2",
    titulo: "¿Donde esta el fantasma? 2",
    video: "https://dl.dropbox.com/scl/fi/03x9fzrhnndf5min3kzhw/A.Haunted.House.2.1080P-Dual-Lat.mp4?rlkey=nk410hmc7wo5jn1khc8ivm5cy&st=",
    poster: "https://image.tmdb.org/t/p/w780/2lWladET1Od8JErz7DAFeprilnV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vRbDuqlmGPM9wGZ3VwbrjQu16Oa.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de haber exorcizado los demonios de su ex, Malcolm comienza de nuevo con su nueva novia y sus dos hijos. Después de mudarse a la casa de sus sueños, sin embargo, Malcolm está rodeado una vez más por extraños acontecimientos paranormales.",
    anio: "2014",
    duracion: "1h 26min",
    calificacion: "87%",
    genero: "Terror • Comedia",
    director: "Michael Tiddes",
    reparto: "Marlon Wayans, Jaime Pressly, Esencia Atkins",
    estreno: "18/04/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "donde_esta_el_fantasma",
        titulo: "¿Donde esta el fantasma?",
        imagen: "https://image.tmdb.org/t/p/w300/pAVGfrADDvKMgoZnJLSCiLBCCiG.jpg"
      },
      {
        id: "diario_de_mi_vagina",
        titulo: "Diario de mi Vagina",
        imagen: "https://image.tmdb.org/t/p/w300/hyFKdAN5Dl93mt2JHfcfvIyf38g.jpg"
      },
      {
        id: "el_sindicato",
        titulo: "El sindicato",
        imagen: "https://image.tmdb.org/t/p/w300/1UHp2QEBPnTrcx0i6aYw6jWtDbI.jpg"
      },
      {
        id: "quiero_tu_vida",
        titulo: "Quiero tu vida",
        imagen: "https://image.tmdb.org/t/p/w300/hk2kW6uwTEa8cxDeF1UPfIpEYkF.jpg"
      },
      {
        id: "que_paso_ayer",
        titulo: "¿Que paso ayer?",
        imagen: "https://image.tmdb.org/t/p/w300/kZaaJQEi7n3mrBct1H3l2g45Ijb.jpg"
      },
      {
        id: "iron_man_2",
        titulo: "Iron-Man 2",
        imagen: "https://image.tmdb.org/t/p/w300/ayyJVOV5I4MGjti7nIHC3mVCagR.jpg"
      }
    ]
  },

  depredador_tierras_salvajes: {
    id: "depredador_tierras_salvajes",
    titulo: "Depredador: Tierras salvajes",
    video: "https://dl.dropbox.com/scl/fi/0xj205842hedvxjumipca/Depredador-Tierras-Salvajes-2025-HDTS-1080p.mp4?rlkey=ee2dfhcu88kf8p8qxqxu0tnwh&st=",
    poster: "https://image.tmdb.org/t/p/original/82lM4GJ9uuNvNDOEpxFy77uv4Ak.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/r7TEWHLr1lsIsTkiEFwtM3hAWma.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Expulsado de su clan, un joven Depredador encuentra un aliado inesperado en un androide dañado y se embarca en un peligroso viaje en busca del adversario definitivo.",
    anio: "2025",
    duracion: "1h 47min",
    calificacion: "77%",
    genero: "Acción • Ciencia Ficción • Aventura",
    director: "Dan Trachtenberg",
    reparto: "Elle Fanning, Ravi Narayan, Michael Homick",
    estreno: "06/11/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "depredador_asesino_de_asesinos",
        titulo: "Depredador: Asesino de asesinos",
        imagen: "https://image.tmdb.org/t/p/w300/e9gpb3U9kerduyipUX31Y00vfuJ.jpg"
      },
      {
        id: "el_depredador",
        titulo: "El depredador",
        imagen: "https://image.tmdb.org/t/p/w300/dyAGJ75aJXwDVDZqBWLWNxb82AA.jpg"
      },
      {
        id: "depredadores",
        titulo: "Depredadores",
        imagen: "https://image.tmdb.org/t/p/w300/90Op5jzIzcKN8RJHhgbuTbpP61N.jpg"
      },
      {
        id: "aliens_vs_depredador_2",
        titulo: "Aliens vs. Depredador 2: Réquiem",
        imagen: "https://image.tmdb.org/t/p/w300/1oHhdpITbDVIJGyt0xfDEMrk0fG.jpg"
      },
      {
        id: "aliens_vs_depredador",
        titulo: "AVP: Alien vs. Depredador",
        imagen: "https://image.tmdb.org/t/p/w300/2wHDyHrjIXkYjEtUKts6yUld6Ld.jpg"
      },
      {
        id: "depredador_la_presa",
        titulo: "Depredador: La presa",
        imagen: "https://image.tmdb.org/t/p/w300/49ldE9yPMkYCrTLEpdhJgqlQXYK.jpg"
      }
    ]
  },

  dragon_ball_el_camino_hacia_el_poder: {
    id: "dragon_ball_el_camino_hacia_el_poder",
    titulo: "Dragon Ball: El camino hacia el poder",
    video: "https://dl.dropbox.com/scl/fi/lroundzohbydbuc46qfpz/Dragon-Ball-El-camino-hacia-el-poder.mp4?rlkey=z1drtoge9pyyn4oo8omj2z5ld&st=",
    poster: "https://image.tmdb.org/t/p/original/xDmJk9zQVsTKwNzHeKySoCguAk5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wPkoqtFhDoIbzt61oOYwmLOZdAg.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El camino hacia el más fuerte es la 17ª película basada basada en la serie de manga y anime Dragon Ball, y la 4º de la etapa Dragon Ball. Se hizo con motivo del 10º aniversario de la serie, y cuenta de una manera diferente desde el principio de la serie hasta el fin del Ejército Red Ribbon, con una mejora gráfica y efectos en 3D: Goku conoce a Bulma y juntos deciden ir en busca de las siete Dragon Ball para resucitar al Dragón Xeron y pedirle un deseo.",
    anio: "1996",
    duracion: "1h 19min",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Shigeyasu Yamauchi",
    reparto: "Laura Torres, Isabel Martiñón, Jesús Colí",
    estreno: "02/03/1996",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      },
      {
        id: "dragon_ball_z_el_super_saiyayin_son_goku",
        titulo: "Dragon Ball Z: El super saiyajin Son Goku",
        imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg"
      },
      {
        id: "dragon_ball_z_episodio_de_bardock",
        titulo: "Dragon Ball Z: Episodio de Bardock",
        imagen: "https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      }
    ]
  },

  dragon_ball_gran_aventura_mistica: {
    id: "dragon_ball_gran_aventura_mistica",
    titulo: "Dragon Ball: Gran aventura mística",
    video: "https://dl.dropbox.com/scl/fi/l1fn1epxjjk4s3nuqxmia/Dragon-Ball-una-aventura-mistica.mp4?rlkey=frr86ylu658y84l8wb830ynmv&st=",
    poster: "https://image.tmdb.org/t/p/w780/xIv4HuvPP9nL7PU9vq2PKOKFvhj.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/f2BipTKswrdpqoCc1xJDyL35rJy.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de haber terminado su entrenamiento con el maestro Roshi, Goku y Krilín participan en un torneo de artes marciales organizado por el rey Chaoz, quien tiene bajo su mando a varios integrantes de la armada de la patrulla roja. Mientras tanto, el consejero del emperador está intentando reunir las 7 bolas de dragón, y para ello cuenta con la ayuda de Tao Pai Pai y Ten Shin Han.",
    anio: "1998",
    duracion: "45min 49s",
    calificacion: "77%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Kazuhisa Takenouchi",
    reparto: "Laura Torres, Isabel Martiñón, Jesús Colín",
    estreno: "09/09/1988",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_los_tres_grendes_guerreros_saiyajin",
        titulo: "Dragon Ball Z: Los tres grandes Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg"
      }
    ]
  },

  dragon_ball_gt_despues_de_100_años: {
    id: "dragon_ball_gt_despues_de_100_años",
    titulo: "Dragon Ball GT: Después 100 años ",
    video: "https://dl.dropbox.com/scl/fi/h6q0a27m2a2pnqusgn70f/Dragon-ball-gt-100a-os.mp4?rlkey=ymjozpox8hov55usqzu343oxz&st=",
    poster: "https://image.tmdb.org/t/p/w780/sLCN5b2WYsYkWMrMMCnRHGv1VEO.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La historia se sitúa a 100 años después del final de DBGT. La nieta de Goku, Pan, es ahora la abuelita de Goku Jr. (que vendría siendo el tatataranieto de Goku) quien tiene un carácter débil y no conoce su gran poder. Al enfermar Pan, Goku Jr. decide armarse de valor y salir a buscar las bolas de dragón. Por el camino se enfrentará a numerosos peligros que le harán descubrir el gran poder que lleva dentro.",
    anio: "1996",
    duracion: "43min 11s",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Osamu Kasai",
    reparto: "Irma Carmona, Gloria Rocha",
    estreno: "16/04/1997",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_devuelveme_a_mi_gohan",
        titulo: "Dragon Ball Z: Devuélvanme a mi Gohan",
        imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      }
    ]
  },

  dragon_ball_la_leyenda_de_shenron: {
    id: "dragon_ball_la_leyenda_de_shenron",
    titulo: "Dragon Ball: La leyenda del dragón Shenron",
    video: "https://dl.dropbox.com/scl/fi/s3lv2gz361hor0u0g1drg/Dragon-Ball-La-leyenda-de-shen-long.mp4?rlkey=t11dh1yqgqhzyfepifw6v6g3t&st=",
    poster: "https://image.tmdb.org/t/p/w780/ydBG5pa3p3wsVktrFza2WNI55yw.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Primera película animada de Dragon Ball. Viene a ser un resumen de los primeros capítulos de la serie, pese a incorporar algunos cambios. Cuenta la leyenda que hay siete esferas de dragón desparramadas por toda la Tierra. Aquél que logre juntarlas podrá invocar al dragón Shen-Ron que es capaz de cumplir cualquier deseo. Acompaña a Goku, Bulma, Oolong, Yamcha y Puar en esta fantástica aventura mientras enfrentan al ambicioso Rey Gourmeth quien tiene oprimido a su pueblo.",
    anio: "1996",
    duracion: "50min 14s",
    calificacion: "68%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: "Laura Torres, Rocío Garcel, Abel Rocha",
    estreno: "20/12/1986",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_devuelveme_a_mi_gohan",
        titulo: "Dragon Ball Z: Devuélvanme a mi Gohan",
        imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      }
    ]
  },

  dragon_ball_la_princesa_durmiente: {
    id: "dragon_ball_la_princesa_durmiente",
    titulo: "Dragon Ball: La princesa durmiente del castillo del mal",
    video: "https://dl.dropbox.com/scl/fi/7xzvzapcrpv648koabnqp/Dragon-ball-la-leyenda-de-la-princesa-durmiente.mp4?rlkey=s37apy3egwrh88iwr6udzjjlp&st=",
    poster: "https://image.tmdb.org/t/p/w780/o8laRnRa6BLMNsMi4nqeSMx3zRV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La Bella Durmiente en el Castillo del Mal es la 2ª película basada en la serie de manga y anime Dragon Ball estrenada el 18 de julio de 1987. Es una continuación directa de La Leyenda del Dragón Shenron. Para poder ser aceptados como alumnos del Duende Tortuga, Goku y Krilín deben cumplir una mision: salvar a la bella durmiente que descansa en el castillo del mal.",
    anio: "1987",
    duracion: "44min 29s",
    calificacion: "73%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: "Laura Torres, Rossy Aguirre, Rocío Garcel,  Ricardo Mendoza",
    estreno: "18/07/1987",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_z_el_super_saiyayin_son_goku",
        titulo: "Dragon Ball Z: El super saiyajin Son Goku",
        imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg"
      },
      {
        id: "dragon_ball_z_el_mas_fuerte_del_mundo",
        titulo: "Dragon Ball Z: El más fuerte del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      }
    ]
  },

  dragon_ball_super_broly: {
    id: "dragon_ball_super_broly",
    titulo: "Dragon Ball Super: Broly",
    video: "https://dl.dropbox.com/scl/fi/0ifr6542qw05d5gzve7s4/Dragon.ball.super.broly.2018.1080P-Dual-Lat.mp4?rlkey=eawj0b1jj7ju4umy954wyyq02&st=",
    poster: "https://image.tmdb.org/t/p/w780/6OTRuxpwUUGbmCX3MKP25dOmo59.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La Tierra vive en paz después de que concluyó el Torneo de Fuerza. Luego de darse cuenta que los Universos aún tienen muchos guerreros poderosos, Gokú pasa todos los días entrenando para alcanzar un nivel de pelea mayor. Un día Gokú y Vegeta enfrentan a un nuevo saiyajin llamado “Broly”, a quien nunca antes han visto. Supuestamente, los saiyajin fueron arrasados durante la destrucción del planeta Vegeta; entonces ¿qué hace uno de ellos en la Tierra? Este encuentro entre tres saiyajin, que han tenido destinos diferentes, se convierte en una batalla estupenda, con Freezer (que ha vuelto del infierno) atrapado en medio de ellos.",
    anio: "2018",
    duracion: "1h 41min",
    calificacion: "94%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Tatsuya Nagamine",
    reparto: "Mario Castañeda, René García, Ricardo Brust",
    estreno: "14/12/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/wFYXVMKWLAoazjWTBNQ4IiQSKJg.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_el_camino_hacia_el_poder",
        titulo: "Dragon Ball: El camino hacia el poder",
        imagen: "https://image.tmdb.org/t/p/w300/2PiRMHl7QDwuB0rAw0GjVHVb847.jpg"
      }
    ]
  },

  dragon_ball_super_super_hero: {
    id: "dragon_ball_super_super_hero",
    titulo: "Dragon Ball Super: Super hero",
    video: "https://dl.dropboxusercontent.com/scl/fi/jy535c6e18x0ydikzhniw/Dragon.Ball.Super.Super.Hero.2022.1080P-Dual-Lat.mp4?rlkey=c2634uyll6a1rimri72e2akf9&st=",
    poster: "https://image.tmdb.org/t/p/w780/xvqzAso5RNA09PbMFmyrL2I9VdY.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wFYXVMKWLAoazjWTBNQ4IiQSKJg.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Son Goku destruyó en su momento al Ejército Red Ribbon. Ahora, ciertos individuos han decidido continuar con su legado y han creado a los androides definitivos: Gamma 1 y Gamma 2. Estos dos androides se autoproclaman <superhéroes> y deciden atacar a Piccolo y a Gohan. ¿Cuál es el objetivo del Nuevo Ejército Red Ribbon? Ante un peligro inminente, ¡llega el momento del despertar del Superhéroe!.",
    anio: "2022",
    duracion: "1h 39min",
    calificacion: "89%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Tetsuro Kodama",
    reparto: "Luis Manuel Ávila, Carlos Segundo, Víctor Ugarte",
    estreno: "11/06/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_z_los_guerreros_del_futuro",
        titulo: "Dragon Ball Z: Los dos guerreros del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      }
    ]
  },

  dragon_ball_z_la_galaxia_corre_peligro: {
    id: "dragon_ball_z_la_galaxia_corre_peligro",
    titulo: "Dragon Ball Z: La galaxia corre peligro",
    video: "https://dl.dropbox.com/scl/fi/cuuf6t5cdynyr0whktcqj/Dragon-Ball-Z-La-galaxia-corre-peligro.mp4?rlkey=79rjsljijl55kd9nzuu2janlw&st=",
    poster: "https://image.tmdb.org/t/p/w780/k5ypSoY4Ze0Gfi8zkijhr6lV3yx.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El rico Gyosan Money organiza un nuevo torneo de las artes marciales y Mr. Satán desafía a todos los luchadores. Mientras Piccolo y Vegeta se mantienen al margen, Gohan, Trunks y Krilín se clasifican para la final del torneo. Lo que desconocen es que el poderoso Bojack y su banda han escapado de su prisión en el planeta Kaito y pretenden conquistar el Universo. Goku observa desde el Más Allá cómo sus amigos van cayendo hasta quedar sólo Gohan.",
    anio: "1996",
    duracion: "50min 25s",
    calificacion: "88%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Yoshihiro Ueda",
    reparto: "Laura Torres, Luis Daniel Ramírez, Sergio Bonilla",
    estreno: "10/07/1993",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_de_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de Cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock Vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      },
      {
        id: "dragon_ball_z_episodio_de_bardock",
        titulo: "Dragon Ball Z: Episodio de Bardock",
        imagen: "https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg"
      }
    ]
  },

  dragon_ball_z_devuelveme_a_mi_gohan: {
    id: "dragon_ball_z_devuelveme_a_mi_gohan",
    titulo: "Dragon Ball Z: Devuelveme a mi Gohan",
    video: "https://dl.dropbox.com/scl/fi/8lkjuo97s0p9b82mup84b/Dragon-Ball-Z-Devuelveme-a-mi-gohan.mp4?rlkey=17woye5yiqutff36s6oi0iry6&st=",
    poster: "https://image.tmdb.org/t/p/w780/wvneAN9gJSVS3HwnGpEBdv9zOlO.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "",
    anio: "1989",
    duracion: "41min",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: "Laura Torres, Carlos Segundo, Eduardo Garza",
    estreno: "15/07/1989",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_el_camino_hacia_el_poder",
        titulo: "Dragon Ball: El camino hacia el poder",
        imagen: "https://image.tmdb.org/t/p/w300/2PiRMHl7QDwuB0rAw0GjVHVb847.jpg"
      },
      {
        id: "dragon_ball_z_los_tres_grendes_guerreros_saiyajin",
        titulo: "Dragon Ball Z: Los tres grandes Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg"
      },
      {
        id: "dragon_ball_z_plan_erradicar",
        titulo: "Dragon Ball Z: Plan para erradicar a los Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de Cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      },
      {
        id: "dragon_ball_z_el_mas_fuerte_del_mundo",
        titulo: "Dragon Ball Z: El más fuerte del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      }
    ]
  },

  dragon_ball_z_el_ataque_del_dragon: {
    id: "dragon_ball_z_el_ataque_del_dragon",
    titulo: "Dragon Ball Z: El ataque del dragon",
    video: "https://dl.dropbox.com/scl/fi/0f93yiciodiw3l020d138/DBZ-El-Ataque-Del-Dragon-1080p.mp4?rlkey=xtivpfhd0igzyah8i1ejv7wmf&st=",
    poster: "https://image.tmdb.org/t/p/w780/dskU66PycrhgW7gq00eJAOkuK4Q.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Hoi, un extraño hechicero, le pide a Goku que reúna las bolas de dragón para liberar de su prisión al héroe de su planeta, Tapión. Goku accede a ayudarle, pero pronto se da cuenta de que Tapión es realmente un poderoso monstruo llamado Hildengan.",
    anio: "1995",
    duracion: "1h 19min",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Mitsuo Hashimoto",
    reparto: "Mario Castañeda, René García, Gaby Willer",
    estreno: "15/07/1995",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_los_tres_grendes_guerreros_saiyajin",
        titulo: "Dragon Ball Z: Los tres grandes Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg"
      },
      {
        id: "dragon_ball_z_devuelveme_a_mi_gohan",
        titulo: "Dragon Ball Z: Devuélvanme a mi Gohan",
        imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg"
      },
      {
        id: "dragon_ball_z_los_guerreros_del_futuro",
        titulo: "Dragon Ball Z: Los dos guerreros del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },
      {
        id: "dragon_ball_z_el_super_saiyayin_son_goku",
        titulo: "Dragon Ball Z: El super saiyajin Son Goku",
        imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg"
      },
      {
        id: "dragon_ball_z_el_mas_fuerte_del_mundo",
        titulo: "Dragon Ball Z: El más fuerte del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg"
      }
    ]
  },

  dragon_ball_z_el_combate_final: {
    id: "dragon_ball_z_el_combate_final",
    titulo: "Dragon Ball Z: El combate final",
    video: "https://dl.dropbox.com/scl/fi/3jrehlcdoq89wx14nm8im/Dragon-Ball-Z-El-combate-final.mp4?rlkey=lox3zi5gsqn5r3xrh069vy9of&st=",
    poster: "https://image.tmdb.org/t/p/w780/1516leTXPqSEt6t7Fd7EphmR34W.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Mr. Satán acepta el desafío de un viejo conocido, Vagger Batta, para combatir contra sus bioguerreros. Satán no quiere problemas y prefiere llevarse al androide N°18 para que luche por él. Todo va bien hasta que entra en escena el luchador estrella de Vagger, un bioguerrero creado a partir del ADN de Broly.",
    anio: "1994",
    duracion: "1h 19min",
    calificacion: "73%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Yoshihiro Ueda",
    reparto: "Laura Torres, Mónica Villaseñor, Luis Daniel Ramírez",
    estreno: "09/07/1994",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/4Kru90S3N0v8cEqlfehmDgvsF1h.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "dragon_ball_gt_despues_de_100_años",
        titulo: "Dragon Ball GT: Despues de 100 años",
        imagen: "https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      }
    ]
  },

  dragon_ball_z_el_mas_fuerte_del_mundo: {
    id: "dragon_ball_z_el_mas_fuerte_del_mundo",
    titulo: "Dragon Ball Z: El más fuerte del mundo",
    video: "https://dl.dropbox.com/scl/fi/94klww2le3y1vw9zgkunv/Dragon-Ball-Z-El-mas-fuerte-del-mundo-1990.mp4?rlkey=avx55mssi1k7adzwfcg3rnlfk&st=",
    poster: "https://image.tmdb.org/t/p/w780/x1GZYK1guxTBOIkAPDf9IZCD3qK.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Shen Long es invocado por el Dr. Kochin, el cual pide como deseo que el Dr. Wheelo y su laboratorio, sean descongelados del hielo irrompible. Más tarde, el Dr. Kochin secuestra al Maestro Roshi junto con Bulma, para transferir el cerebro del Dr. Wheelo a el cuerpo del Hombre más fuerte del Mundo, pero Bulma se encarga de informarle, que el Más fuerte de este Mundo, actualmente, es Gokú, el cúal se encuentra en camino para ir a rescatarlos.",
    anio: "1990",
    duracion: "45min",
    calificacion: "87%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: " Mario Castañeda, Jesús Colín, Rocío Garcel",
    estreno: "10/03/1990",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_el_camino_hacia_el_poder",
        titulo: "Dragon Ball: El camino hacia el poder",
        imagen: "https://image.tmdb.org/t/p/w300/2PiRMHl7QDwuB0rAw0GjVHVb847.jpg"
      }
    ]
  },

  dragon_ball_z_el_regreso_de_cooler: {
    id: "dragon_ball_z_el_regreso_de_cooler",
    titulo: "Dragon Ball Z: El regreso de cooler",
    video: "https://dl.dropbox.com/scl/fi/de7bw7x1p33ohv4705p4f/DBZ-Los-Guerreros-Mas-Poderosos-1080p.mkv?rlkey=u9r2q4mcaloa3vq2v9nhoe3ey&st=",
    poster: "https://image.tmdb.org/t/p/w780/utXkOLm5Ivr0rJtJFHVMKjcSTzQ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Dende, el nuevo kamisama, se entera de que el planeta Namek está en peligro, pide ayuda a Goku y sus amigos para que salven su planeta natal. Al llegar, Goku y los demás se dan cuenta de que una especie de estrella está atacando al planeta. Detrás de todo esto está Cooler, el hermano mayor de Freezer, al que pensaban que Goku había vencido.",
    anio: "1992",
    duracion: "50min",
    calificacion: "84%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: " Daisuke Nishio",
    reparto: "Ricardo Brust, Laura Torres, Luis Daniel Ramírez",
    estreno: "07/03/1992",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_devuelveme_a_mi_gohan",
        titulo: "Dragon Ball Z: Devuélvanme a mi Gohan",
        imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg"
      },
      {
        id: "dragon_ball_z_los_guerreros_del_futuro",
        titulo: "Dragon Ball Z: Los dos guerreros del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_la_princesa_durmiente",
        titulo: "Dragon Ball: La princesa durmiente del castillo del mal",
        imagen: "https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg"
      }
    ]
  },

  dragon_ball_z_el_poder_invencible: {
    id: "dragon_ball_z_el_poder_invencible",
    titulo: "Dragon Ball Z: El poder Invencible",
    video: "https://dl.dropbox.com/scl/fi/k6jc657iads0r9vqq6rx5/Dragon-Ball-Z-El-poder-invensible.mp4?rlkey=i68x0gs8hleuva7pe25q1edey&st=",
    poster: "https://image.tmdb.org/t/p/w780/mTI2iRLkgkqPpy1s3l1dMwthjB6.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un desconocido saiyan, Paragus, llega a la Tierra en busca de Vegeta para pedirle que gobierne el nuevo mundo de los saiyan y así reestablecer el antiguo poder de su raza. Trunks, Goku, Piccolo y Gohan acompañan a Vegeta al planeta de los saiyans. Pero el objetivo real de Paragus es que Broly, el súper saiyan legendario, extermine a los últimos saiyans que quedaban en la Tierra.",
    anio: "1993",
    duracion: "44min",
    calificacion: "83%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Shigeyasu Yamauchi",
    reparto: "Enrique Mederos, René García, Ricardo Brust",
    estreno: "05/03/1993",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      }
    ]
  },

  dragon_ball_z_el_regreso_de_broly: {
    id: "dragon_ball_z_el_regreso_de_broly",
    titulo: "Dragon Ball Z: El regreso del guerrero legendario",
    video: "https://dl.dropbox.com/scl/fi/gxs1v21167asaezzvleb4/Dragon-Ball-Z-El-Regreso-de-broly.mp4?rlkey=smi2eok2rr2ujvrvw60metzq7&st=",
    poster: "https://image.tmdb.org/t/p/w780/ptLGiI2nEhI3n9PJ2xITXpRQxwr.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Broly regresa clamando venganza. Tras ser derrotado por Goku en una dura batalla en el planeta Neo Vegeta, Broly logra escapar en una cápsula espacial y llega a la Tierra. Allí queda congelado en un lago durante 7 años. Mientras, buscando las bolas de dragón, Videl, Goten y Trunks llegan a un extraño pueblo amenazado por la presencia de un monstruo. Los sollozos de Goten despiertan a Broly y comienza una batalla.",
    anio: "1994",
    duracion: "52min",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Shigeyasu Yamauchi",
    reparto: "Luis Daniel Ramírez, Ricardo Brust, Carola Vázquez",
    estreno: "12/03/1994",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_gt_despues_de_100_años",
        titulo: "Dragon Ball GT: Despues de 100 años",
        imagen: "https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg"
      },
      {
        id: "dragon_ball_la_leyenda_de_shenron",
        titulo: "Dragon Ball: La leyenda del dragón Shenron",
        imagen: "https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg"
      }
    ]
  },

  dragon_ball_z_el_super_saiyayin_son_goku: {
    id: "dragon_ball_z_el_super_saiyayin_son_goku",
    titulo: "Dragon Ball Z: El super saiyajin Son Goku",
    video: "https://dl.dropbox.com/scl/fi/rigo9c1fnq2syinp7szhk/Dragon-Ball-Z-Goku-es-un-super-saiyajin.mp4?rlkey=uzgflgbicfkdgiog8v1c65csf&st=",
    poster: "https://image.tmdb.org/t/p/w780/cyQK5IzMXDUS8o84HYbSIFQt1Vy.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En (Capsule Corporation) descubren que un gigantesco asteroide va a chocar contra la Tierra, todos intentan esconderse para protegerse del asteroide, pero Goku y Krilin intentan detenerlo. En un principio parece que fallan y pierden el conocimiento. Justo antes de que el asteroide se estrellase contra la Tierra explota en miles de pedazos por el fuerte ataque de Goku y Krilin, de el asteroide sale una nave y de ella montones de soldados que informan a los humanos curiosos que se acercaron que el planeta desde ese momento pertenecía a Lord Slug (un namekiano que fue expulsado de su planeta natal por su maldad). Su fin es reunir las bolas de dragón para recuperar la juventud y convertir el planeta en un sitio adecuado para el ejército de demonios que le acompaña.",
    anio: "1991",
    duracion: "52min",
    calificacion: "86%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: " Mitsuo Hashimoto",
    reparto: "Mario Castañeda, Laura Torres, Rocío Garcel",
    estreno: "19/03/1991",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      }
    ]
  },

  dragon_ball_z_episodio_de_bardock: {
    id: "dragon_ball_z_episodio_de_bardock",
    titulo: "Dragon Ball Z: Episodio de Bardock",
    video: "https://dl.dropbox.com/scl/fi/ppa1vjh7mjlfekdy4st94/Dragon-ball-z-episodio-de-Bardock-Espa-ol-Latino-Completo-720P_HD.mp4?rlkey=o0jel4tqau5s8khpsioifzwpk&st=",
    poster: "https://image.tmdb.org/t/p/w780/pLctvg69kQGVfqSLqeL55sbWNRr.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "OVA de Bola de Dragón desarrollada tras los eventos del especial {Dragon Ball Z: El último combate (Bardock, el padre de Goku) de 1990}. Cuenta la historia de Bardock, tras escapar con vida del ataque de Freezer al Planeta Vegeta. Se basa en el manga de Naho Ôishi.",
    anio: "2011",
    duracion: "22min",
    calificacion: "70%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Yoshihiro Ueda",
    reparto: "Gerardo Reyero, Tulio Ramírez, Mario Castañeda",
    estreno: "17/12/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma vs Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/sXybjRq6BsCkWcDBfNphSH9biqn.jpg"
      }
    ]
  },

  dragon_ball_z_la_batalla_de_los_dioses: {
    id: "dragon_ball_z_la_batalla_de_los_dioses",
    titulo: "Dragon Ball Z: La batalla de los dioses",
    video: "https://dn720303.ca.archive.org/0/items/dragon-ball-z-la-batalla-de-los-dioses/Dragon%20Ball%20Z%20la%20Batalla%20de%20los%20Dioses.ia.mp4",
    poster: "https://image.tmdb.org/t/p/w780/yIDS5QLvKtgzfu43eUWx5JkGW6p.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La historia se sitúa varios años después de la batalla con Majin Buu. Bils, el Dios de la destrucción que mantenía el equilibrio del universo, se ha despertado de un largo sueño. Al escuchar rumores sobre un saiyajin que ha vencido a Freezer, Bills parte a la búsqueda de Goku. Emocionado por el hecho de que haya aparecido un oponente tan poderoso tras tanto tiempo, Goku ignora las advertencias de Kaito y decide enfrentarse a él.",
    anio: "2013",
    duracion: "1h 25min",
    calificacion: "92%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Masahiro Hosoda",
    reparto: "Mario Castañeda, René García, José Luis Orozco",
    estreno: "30/03/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },,
      {
        id: "dragon_ball_z_el_super_saiyayin_son_goku",
        titulo: "Dragon Ball Z: El super saiyajin Son Goku",
        imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg"
      },
      {
        href: "../View Series/Dragon Ball Daima (2024).php",
        titulo: "Dragon Ball Daima",
        imagen: "https://image.tmdb.org/t/p/w300/jcNmdE3Rgn6Xld0osZyIgPU6H40.jpg"
      }
    ]
  },

  dragon_ball_z_la_resurreccion_de_freezer: {
    id: "dragon_ball_z_la_resurreccion_de_freezer",
    titulo: "Dragon Ball Z: La resurrección de Freezer",
    video: "https://dl.dropbox.com/scl/fi/pmdvuuxtgni6xfi252vx5/Dragon.ball.z.resurrection.f.2015.1080p-dual-lat.mp4?rlkey=pkfk0l2ojjgk0911baoxtl54g&st=",
    poster: "https://image.tmdb.org/t/p/w780/69DzGEMRGuLCXBF4fz81foG9nXT.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de que Bills, el Dios de la destrucción, decidiera no destruir la Tierra, se vive una gran época de paz. Hasta que Sorbet y Tagoma, antiguos miembros élite de la armada de Freezer, llegan a la Tierra con el objetivo de revivir a su líder por medio de las Bolas de Dragón. Su deseo es concedido y ahora Freezer planea su venganza en contra de los Saiyajin. La historia hace que una gran oleada de hombres bajo el mando de Freezer lo acompañe.",
    anio: "2015",
    duracion: "1h 34min",
    calificacion: "87%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Tadayoshi Yamamuro",
    reparto: "Mario Castañeda, Luis Alfonso Mendoza, Gerardo Reyero",
    estreno: "18/04/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "naruto_the_last",
        titulo: "Naruto The last: La pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/phPfQ4jWhwmZrmPhAtVYUJfqfwG.jpg"
      },
    ]
  },

  dragon_ball_z_la_fusion_de_goku_y_vegeta: {
    id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
    titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
    video: "https://dl.dropbox.com/scl/fi/9me4yfnqx8c60jn5hdcbc/Dragon-Ball-Z-La-fusion-de-goku-y-vegeta.mp4?rlkey=qurdzya65bttvpoztxc9j8vks&st=",
    poster: "https://image.tmdb.org/t/p/w780/mdntijZT6aiYvFKRD5JytFfsSZF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En el Otro mundo, un pequeño error provoca la aparición de un poderoso monstruo llamado Janemba, que ha sido creado con toda la maldad de los habitantes del Infierno. Para derrotarlo, a Vegeta y a Goku no les queda otro remedio que fusionarse y convertirse así en el guerrero más fuerte del Universo.",
    anio: "1995",
    duracion: "51min",
    calificacion: "87%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Shigeyasu Yamauchi",
    reparto: "Mario Castañeda, René García, Enrique Mederos",
    estreno: "04/03/1995",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      },
      {
        id: "dragon_ball_z_el_combate_final",
        titulo: "Dragon Ball Z: El combate final",
        imagen: "https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg"
      },
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      }
    ]
  },

  dragon_ball_z_bardock_vs_freezer: {
    id: "dragon_ball_z_bardock_vs_freezer",
    titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
    video: "https://dl.dropbox.com/scl/fi/zhgnaas4gvkhu1ui2xwst/Dragon-ball-z-bardock-vs-freezer.mp4?rlkey=84kzgu6yg62lxcnk0yxybqg3k&st=",
    poster: "https://image.tmdb.org/t/p/w780/93WDwbpnt40peVQthtBQ6U8FCjr.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Especial para televisión que se ubica en el arco argumental de Dragon Ball Z. Se cuenta la historia del padre de Goku, Bardock, y su intento por salvar su planeta aunque le cueste la vida...",
    anio: "1990",
    duracion: "48min",
    calificacion: "88%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Mitsuo Hashimoto",
    reparto: "Mario Castañeda, Enrique Cervantes",
    estreno: "17/10/1990",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_plan_erradicar",
        titulo: "Dragon Ball Z: Plan para erradicar a los Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg"
      },
      {
        id: "dragon_ball_gt_despues_de_100_años",
        titulo: "Dragon Ball GT: Despues de 100 años",
        imagen: "https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg"
      },
      {
        id: "dragon_ball_la_leyenda_de_shenron",
        titulo: "Dragon Ball: La leyenda del dragón Shenron",
        imagen: "https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg"
      },
      {
        id: "dragon_ball_gran_aventura_mistica",
        titulo: "Dragon Ball: Gran aventura mística",
        imagen: "https://image.tmdb.org/t/p/w300/f2BipTKswrdpqoCc1xJDyL35rJy.jpg"
      },
      {
        id: "dragon_ball_la_princesa_durmiente",
        titulo: "Dragon Ball: La princesa durmiente del castillo del mal",
        imagen: "https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg"
      },
      {
        id: "dragon_ball_el_camino_hacia_el_poder",
        titulo: "Dragon Ball: El camino hacia el poder",
        imagen: "https://image.tmdb.org/t/p/w300/2PiRMHl7QDwuB0rAw0GjVHVb847.jpg"
      }
    ]
  },

  dragon_ball_z_la_super_batalla: {
    id: "dragon_ball_z_la_super_batalla",
    titulo: "Dragon Ball Z: La super batalla",
    video: "https://dl.dropbox.com/scl/fi/1emxqgfmpt7mh1epsvvn2/Dragon-Ball-Z-La-batalla-mas-grande-de-este-mundo-esta-por-comenzar.mp4?rlkey=idmr36ccujczdjlx04uee39jr&st=",
    poster: "https://image.tmdb.org/t/p/w780/en28AEmaJxE3SPXZcMD8OvgX6Jz.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/69dMY6CPe6mqi7nMC2bVeCcjJQI.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Sexta película basada en el manga/anime de Akira Toriyama y tercera de la etapa Dragon Ball Z. Una banda de desertores del ejército de Freezer, capitaneada por el misterioso Tarles, llega a la Tierra con una semilla terrible. Una vez plantada, surge un árbol monstruoso que amenaza con absorber toda vida en el planeta. Por una broma del destino, Tarles resulta ser un saiyan que se parece a Son Goku como si fuera su hermano gemelo.",
    anio: "1990",
    duracion: "1h 00min",
    calificacion: "89%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: "David Arnaiz, Laura Torres, Mario Castañeda",
    estreno: "07/07/1990",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_batalla_de_los_dioses",
        titulo: "Dragon Ball Z: La batalla de los dioses",
        imagen: "https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"
      },
      {
        id: "dragon_ball_z_la_resurreccion_de_freezer",
        titulo: "Dragon Ball Z: La resurreccion de Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg"
      },
      {
        id: "dragon_ball_super_broly",
        titulo: "Dragon Ball Super: Broly",
        imagen: "https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      },
      {
        id: "dragon_ball_z_el_poder_invencible",
        titulo: "Dragon Ball Z: El poder Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_broly",
        titulo: "Dragon Ball Z: El regreso Del guerrero legendario",
        imagen: "https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg"
      }
    ]
  },

  dragon_ball_z_los_guerreros_del_futuro: {
    id: "dragon_ball_z_los_guerreros_del_futuro",
    titulo: "Dragon Ball Z: Los dos guerreros del futuro",
    video: "https://dl.dropbox.com/scl/fi/hidpvshoygb92gqjc5erl/Dragon-Ball-Z-Los-dos-guerreros-del-futuro-1993.mp4?rlkey=qi3ifgwldwdvwi2u3xb4i5tet&st=",
    poster: "https://image.tmdb.org/t/p/w780/5Kn26cG3KIafnCer4FDTjvtbtyD.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En un futuro alternativo en el que Goku lleva muerto varios años, los androides N°17 y N°18 siembran el caos y el terror. Gohan, quien perdió un brazo en uno de sus primeros enfrentamientos con los androides, y Trunks son los únicos guerreros supervivientes e intentan ocasionalmente detenerlos, siempre con escasa fortuna.",
    anio: "1993",
    duracion: "48min",
    calificacion: "86%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Yoshihiro Ueda",
    reparto: "Sergio Bonilla, Luis Alfonso Mendoza, Laura Torres",
    estreno: "24/02/1993",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      },
      {
        id: "dragon_ball_z_episodio_de_bardock",
        titulo: "Dragon Ball Z: Episodio de Bardock",
        imagen: "https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg"
      },
      {
        id: "dragon_ball_z_la_super_batalla",
        titulo: "Dragon Ball Z: La super batalla",
        imagen: "https://image.tmdb.org/t/p/w300/69dMY6CPe6mqi7nMC2bVeCcjJQI.jpg"
      },
      {
        id: "dragon_ball_z_el_mas_fuerte_del_mundo",
        titulo: "Dragon Ball Z: El más fuerte del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg"
      },
      {
        id: "dragon_ball_z_los_tres_grendes_guerreros_saiyajin",
        titulo: "Dragon Ball Z: Los tres grandes Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg"
      }
    ]
  },

  dragon_ball_z_los_rivales_mas_poderosos: {
    id: "dragon_ball_z_los_rivales_mas_poderosos",
    titulo: "Dragon Ball Z: Los rivales mas poderosos",
    video: "https://dl.dropbox.com/scl/fi/819k8li0r6tsuu1epx0zk/Dragon-Ball-Z-Los-Rivales-mas-Poderosos.mp4?rlkey=cyn55wxn69ev3go616ogn3e7p&st=",
    poster: "https://image.tmdb.org/t/p/w780/bI7Up9hXoJU8YN8v9zp2KGYLmui.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras enterarse de la muerte de Freezer, Cooler llega a la Tierra con la intención de matar a Son Goku, para vengar la muerte de su hermano y así acabar con la raza de los Saiyajin a los cuales Cooler y todos los de su especie odian.",
    anio: "1991",
    duracion: "47min",
    calificacion: "89%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Mitsuo Hashimoto",
    reparto: "Mario Castañeda, Carlos Segundo , Ricardo Brust",
    estreno: "20/07/1991",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_el_mas_fuerte_del_mundo",
        titulo: "Dragon Ball Z: El más fuerte del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg"
      },
      {
        id: "dragon_ball_z_devuelveme_a_mi_gohan",
        titulo: "Dragon Ball Z: Devuélvanme a mi Gohan",
        imagen: "https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg"
      },
      {
        id: "dragon_ball_z_los_guerreros_del_futuro",
        titulo: "Dragon Ball Z: Los dos guerreros del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg"
      },
      {
        id: "dragon_ball_z_la_fusion_de_goku_y_vegeta",
        titulo: "Dragon Ball Z: La fusión de Goku y Vegeta",
        imagen: "https://image.tmdb.org/t/p/w300/yo9ioIpVLR8AitD9Q9m13Nf3of8.jpg"
      },
      {
        id: "dragon_ball_z_el_super_saiyayin_son_goku",
        titulo: "Dragon Ball Z: El super saiyajin Son Goku",
        imagen: "https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg"
      },
      {
        id: "dragon_ball_z_la_super_batalla",
        titulo: "Dragon Ball Z: La super batalla",
        imagen: "https://image.tmdb.org/t/p/w300/69dMY6CPe6mqi7nMC2bVeCcjJQI.jpg"
      }
    ]
  },

  dragon_ball_z_los_tres_grendes_guerreros_saiyajin: {
    id: "dragon_ball_z_los_tres_grendes_guerreros_saiyajin",
    titulo: "Dragon Ball Z: Los tres grandes Super Saiyans",
    video: "https://dl.dropbox.com/scl/fi/s60h3fjqyfocpgd528frg/Dragon-Ball-Z-Los-tres-super-saiyajins.mp4?rlkey=uukcaj37oqfeptt9k5ajyj095&st=",
    poster: "https://image.tmdb.org/t/p/w780/mGr18hk6oDQyGjaSpbF7o5epoJV.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Décima película basada en el manga/anime de Akira Toriyama y séptima de la etapa Dragon Ball Z. Cuando el Dr. Gero murió asesinado por #17, el ordenador central no quedo desactivado, sino siguió trabajando. Este trabajo consistió en crear tres nuevos androides: #13, #14 y #15; con el único motivo de destruir a Son Goku...",
    anio: "1992",
    duracion: "46min",
    calificacion: "93%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Daisuke Nishio",
    reparto: "Mario Castañeda, René García, Sergio Bonilla",
    estreno: "11/07/1992",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        href: "../View Series/Dragon Ball Daima (2024).php",
        titulo: "Dragon Ball Daima",
        imagen: "https://image.tmdb.org/t/p/w300/jcNmdE3Rgn6Xld0osZyIgPU6H40.jpg"
      },
      {
        id: "dragon_ball_z_plan_erradicar",
        titulo: "Dragon Ball Z: Plan para erradicar a los Super Saiyans",
        imagen: "https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg"
      },
      {
        id: "dragon_ball_gt_despues_de_100_años",
        titulo: "Dragon Ball GT: Despues de 100 años",
        imagen: "https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg"
      },
      {
        id: "dragon_ball_la_leyenda_de_shenron",
        titulo: "Dragon Ball: La leyenda del dragón Shenron",
        imagen: "https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg"
      },
      {
        id: "dragon_ball_gran_aventura_mistica",
        titulo: "Dragon Ball: Gran aventura mística",
        imagen: "https://image.tmdb.org/t/p/w300/f2BipTKswrdpqoCc1xJDyL35rJy.jpg"
      },
      {
        id: "dragon_ball_la_princesa_durmiente",
        titulo: "Dragon Ball: La princesa durmiente del castillo del mal",
        imagen: "https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg"
      }
    ]
  },

  dragon_ball_z_plan_erradicar: {
    id: "dragon_ball_z_plan_erradicar",
    titulo: "Dragon Ball Z: Plan para erradicar a los Super Saiyans",
    video: "https://dl.dropbox.com/scl/fi/4vlwun60x6z3ib5et82zn/EL-PLAN-PARA-ERRADICAR-A-LOS-SAYAYIN-PELICULA-COMPLETA-ESPA-OL-LATINO-HD-720P_HD.mp4?rlkey=jdhjaktzb1ofwxtob6542g0ae&st=",
    poster: "https://image.tmdb.org/t/p/w780/tElmkcm6Unf9iEQ6Vv1duNF4YsM.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Goku y Gohan son contactados por Mr. Popo, ya que un gas extraterrestre llamado Gas Destron está debilitando la naturaleza. Mr. Popo les informa de la existencia de cuatro máquinas que producen el gas en cuatro locaciones diferentes. Luego descubrirán que la amenza aún continúa al encontrar una quinta máquina... Remake de la OVA homónima de 1993, incluida como extra en el videojuego Dragon Ball Raging Blast 2.",
    anio: "2010",
    duracion: "27min",
    calificacion: "67%",
    genero: "Anime • Animacion • Accion • Ciencia Ficcion",
    director: "Yoshihiro Ueda",
    reparto: "AdryAlbin Fandubs, Matilow Fandubs",
    estreno: "11/11/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dragon_ball_z_los_rivales_mas_poderosos",
        titulo: "Dragon Ball Z: Los rivales mas poderosos",
        imagen: "https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg"
      },
      {
        id: "dragon_ball_z_el_regreso_de_cooler",
        titulo: "Dragon Ball Z: El regreso de cooler",
        imagen: "https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg"
      },
      {
        id: "dragon_ball_z_el_ataque_del_dragon",
        titulo: "Dragon Ball Z: El ataque del dragon",
        imagen: "https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg"
      },
      {
        id: "dragon_ball_z_la_galaxia_corre_peligro",
        titulo: "Dragon Ball Z: La galaxia corre peligro",
        imagen: "https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg"
      },
      {
        id: "dragon_ball_z_bardock_vs_freezer",
        titulo: "Dragon Ball Z: La pelea de Bardock vs Freezer",
        imagen: "https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg"
      },
      {
        id: "dragon_ball_z_episodio_de_bardock",
        titulo: "Dragon Ball Z: Episodio de Bardock",
        imagen: "https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg"
      }
    ]
  },

  dulce_venganza: {
    id: "dulce_venganza",
    titulo: "Dulce venganza",
    video: "https://dl.dropbox.com/scl/fi/jwxixeqyhdc5xnbvbhgfm/Dulce-venganza-2010.mp4?rlkey=ya5aotr8iby6cmcv19na8lzpx&st=",
    poster: "https://image.tmdb.org/t/p/w780/w86s0hTKFJ9Vtusm8kknGoU61Up.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/yfJwNAIzPPyAAOoCue1goOuHM81.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una joven escritora viaja al campo en busca de un poco de soledad y tranquilidad para trabajar en su nuevo libro. Pero en su lugar encuentra una horrible pesadilla. Tras ser agredida por cuatro degenerados, Jennifer logra sobrevivir y mientras intenta sobreponerse, en su mente, ahora dañada y psicótica sólo existe una idea: la venganza sobre esos cuatro hombres que la violaron.",
    anio: "2010",
    duracion: "1h 46min",
    calificacion: "80%",
    genero: "Crimen • Terror • Suspenso",
    director: "Steven R. Monroe",
    reparto: "Sarah Butler, Jeff Branson, Andrés Howard",
    estreno: "08/10/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dulce_venganza_2",
        titulo: "Dulce venganza 2",
        imagen: "https://image.tmdb.org/t/p/w300/g1WEqWtielGmcWj0hleLhDriB7w.jpg"
      },
      {
        id: "dulce_venganza_3",
        titulo: "Dulce venganza 3",
        imagen: "https://image.tmdb.org/t/p/w300/aH3TbHO71FkUSXVKDbpPaoFtgG3.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        id: "un_lugar_en_silencio_3",
        titulo: "Un lugar en silencio 3: Día uno",
        imagen: "https://image.tmdb.org/t/p/w300/mB9GP9Wd7RduYpCSiqurZSnarl6.jpg"
      },
      {
        id: "terrifier_3",
        titulo: "tTerrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      },
      {
        id: "megan_2",
        titulo: "M3GAN 2",
        imagen: "https://image.tmdb.org/t/p/w300/6tPr2pXIpqIldCSTKUt6GCSyvnf.jpg"
      }
    ]
  },

  dulce_venganza_2: {
    id: "dulce_venganza_2",
    titulo: "Dulce venganza 2",
    video: "https://dl.dropbox.com/scl/fi/sqrdnz1c7jzw8hsqixmgb/I.Spit.On.Your.Grave.2.2013.bluray-latino-e-ingles-subt.mp4?rlkey=b9gvpowgkwbbqru5pcbnxwlb6&st=",
    poster: "https://image.tmdb.org/t/p/w780/6TD67XbLc4bwGbDaQg5RkCtBx9O.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/g1WEqWtielGmcWj0hleLhDriB7w.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Jessica acaba de instalarse en Nueva York, donde, al igual que muchas otras jóvenes trata de hacer carrera como modelo. Pero en su primera sesión de fotos en la gran ciudad termina siendo brutalmente violada y torturada. Creyéndola muerta, Jessica es enterrada viva, pero contra todo pronóstico, se las arregla para escapar con vida de su tumba. Ahora exigirá su venganza…",
    anio: "2013",
    duracion: "1h 46min",
    calificacion: "84%",
    genero: "Crimen • Terror • Suspenso",
    director: "Steven R. Monroe",
    reparto: "Jemma Dallender, Joe Absolom, Aleksandar Aleksiev",
    estreno: "20/09/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dulce_venganza",
        titulo: "Dulce venganza",
        imagen: "https://image.tmdb.org/t/p/w300/yfJwNAIzPPyAAOoCue1goOuHM81.jpg"
      },
      {
        id: "dulce_venganza_3",
        titulo: "Dulce venganza 3",
        imagen: "https://image.tmdb.org/t/p/w300/aH3TbHO71FkUSXVKDbpPaoFtgG3.jpg"
      },
      {
        id: "mara",
        titulo: "Mara",
        imagen: "https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg"
      },
      {
        id: "martyrs",
        titulo: "Martyrs",
        imagen: "https://image.tmdb.org/t/p/w300/5kymocKK0SfyEEV0ohNEBz1lxNx.jpg"
      },
      {
        id: "la_sustancia",
        titulo: "La Sustancia",
        imagen: "https://image.tmdb.org/t/p/w300/cQD1qEnPOKUPHAui0okOLZSgitu.jpg"
      },
      {
        id: "la_primera_profecia",
        titulo: "La primera profecia",
        imagen: "https://image.tmdb.org/t/p/w300/kJkrr39cjRcfz3jR6XcGa8wSkyl.jpg"
      }
    ]
  },

  duro_de_entrenar: {
    id: "duro_de_entrenar",
    titulo: "Duro de entrenar",
    video: "https://dl.dropbox.com/scl/fi/8ghvgnjusj64tj9uh4ri7/duro.de.entrenar.dual.2023.mkv?rlkey=4pcuom5psx1lp2smub7o8ntix&st=",
    poster: "https://image.tmdb.org/t/p/w780/un2kba7UHYrydCNL3OkLocU5mG.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/lXkS6kSA0W3c0zVr3QrCBseaNgc.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El cómico estadounidense Kevin Hart quiere ser una estrella de acción, pero para conseguir un papel que cambie su vida, primero tiene que aprender a ser un héroe de acción.",
    anio: "2023",
    duracion: "1h 24min",
    calificacion: "77%",
    genero: "Accion • Comedia • Suspenso",
    director: "Eric Appel",
    reparto: "Kevin Hart, John Travolta, Nathalie Emmanuel",
    estreno: "24/02/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera del mapa",
        imagen: "https://image.tmdb.org/t/p/w300/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg"
      },
      {
        id: "sentencia_de_muerte",
        titulo: "Beekeeper: Sentencia de muerte",
        imagen: "https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg"
      },
      {
        id: "novocaine",
        titulo: "Novocaine: Sin dolor",
        imagen: "https://image.tmdb.org/t/p/w300/6YbTJhN5GJQOlZ1IyRiCyhKSiJE.jpg"
      },
      {
        id: "la_fuente_de_la_eterna_juventud",
        titulo: "La fuente de la eterna juventud",
        imagen: "https://image.tmdb.org/t/p/w300/nJ9qnZLhmj6wD3NgOe6lKoXJQMx.jpg"
      },
      {
        id: "godzilla_y_kong_el_nuevo_imperio",
        titulo: "Godzilla y Kong: El nuevo imperio",
        imagen: "https://image.tmdb.org/t/p/w300/rRLqnazAys1CQGNX5BpXN0Gbowy.jpg"
      },
      {
        id: "estragos",
        titulo: "Estragos",
        imagen: "https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"
      }
    ]
  },

  echo_valley: {
    id: "echo_valley",
    titulo: "Echo Valley",
    video: "https://dl.dropbox.com/scl/fi/vddnodblow075ik1cixyx/Echo-Valley-2025.mp4?rlkey=0y1b1q9x8vqgibk6de4sswf34&st=",
    poster: "https://image.tmdb.org/t/p/w780/aQ5nvQGT6mM6TliOM5iSgrKVF4C.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1E4WCgTodyS7zo8pSp1gZlPO0th.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Kate es una madre que trata de reconducir su relación con su problemática hija Claire. La situación se vuelve crítica cuando Claire aparece sin avisar en casa de Kate, histérica y cubierta de la sangre de otra persona. A medida que Kate trata de desvelar la terrible verdad de lo sucedido, descubrirá hasta qué punto puede llegar una madre para tratar de salvar a su hija.",
    anio: "2025",
    duracion: "1h 44min",
    calificacion: "75%",
    genero: "Suspenso • Drama",
    director: "Michael Pearce",
    reparto: "Julianne Moore, Sídney Sweeney, Domhnall Gleeson",
    estreno: "12/06/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "cato",
        titulo: "CATO",
        imagen: "https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"
      },
      {
        id: "desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      },
      {
        id: "todo_bien",
        titulo: "¿Todo bien?",
        imagen: "https://image.tmdb.org/t/p/w300/mKdRfCpWkcH0wEEg6yO4a8ES4TX.jpg"
      },
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      },
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera Del Mapa",
        imagen: "https://image.tmdb.org/t/p/w300/77dlklwA1VJOLCqIhhmkmS39BLH.jpg"
      },
      {
        id: "extraterritorial",
        titulo: "Extraterritorial",
        imagen: "https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"
      }
    ]
  },

  deadpool: {
    id: "deadpool",
    titulo: "",
    video: "https://dl.dropbox.com/scl/fi/pf7aaukbvu760p88hm7f7/Deadpool.2016.1080p-dual-lat.mp4?rlkey=qdszat19stbqzhnv1f32hqex3&st=",
    poster: "https://image.tmdb.org/t/p/w780/rFj9IKlL75B2pXhZA60jkNWvxeW.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7BYksRLQ9HtZbUtanhAIdeQO9eD.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Basado en el anti-héroe menos convencional de Marvel, Deadpool narra el origen de un ex agente de las fuerzas especiales llamado Wade Wilson, reconvertido a mercenario, y que tras ser sometido a un cruel experimento para curar su cáncer adquiere poderes de curación rápida, adoptando entonces el alter ego de Deadpool. Armado con sus nuevas habilidades y un oscuro y retorcido sentido del humor, Deadpool intentará dar caza al hombre que casi destruye su vida.",
    anio: "2016",
    duracion: "1h 48min",
    calificacion: "87%",
    genero: "Acción • Marvel • Comedia • Aventura",
    director: "Tim Miller",
    reparto: "Ryan Reynolds, Morena Baccarin, Ed Skrein",
    estreno: "11/02/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "deadpool_2",
        titulo: "Deadpool 2",
        imagen: "https://image.tmdb.org/t/p/w300/jA4DpT3ywxfchnTfMBiouBhq9nU.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "doctor_strange",
        titulo: "Doctor Strange",
        imagen: "https://image.tmdb.org/t/p/w300/dAh03zjNzjhiQPrq4Dcr7qKDPlR.jpg"
      },
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "spider_man1",
        titulo: "Spider-Man: Regreso a casa",
        imagen: "https://image.tmdb.org/t/p/w300/81qIJbnS2L0rUAAB55G8CZODpS5.jpg"
      },
      {
        id: "thor_ragnarok3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      }
    ]
  },

  deadpool_2: {
    id: "deadpool_2",
    titulo: "Deadpool 2",
    video: "https://dl.dropbox.com/scl/fi/zncte4hm0depgczukmc6d/Deadpool.2.2018.1080p.unrated-dual-lat-cinecalidad.to.mp4?rlkey=ibcvq1tdl0kanagzrnxrh7he1&st=",
    poster: "https://image.tmdb.org/t/p/w780/zlu94cDgn99dr8D0HyNgfknLvDv.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jA4DpT3ywxfchnTfMBiouBhq9nU.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Wade Wilson, mejor conocido como Deadpool, su nombre de batalla e identidad antiheroica, está de regreso y en esta ocasión su misión será salvar a un chico llamado Russell de las manos de un poderoso rival llamado Cable. En aras de dar cumplimiento a su tarea el antihéroe formará un grupo al cual pondrá el nombre de X-Force. Secuela de la exitosa película Deadpool (2016), parodia de los superhéroes mutantes.",
    anio: "2018",
    duracion: "1h 43min",
    calificacion: "78%",
    genero: "Acción • Marvel • Comedia • Aventura",
    director: "David Leitch",
    reparto: "Ryan Reynolds, Josh Brolin, Morena Baccarin",
    estreno: "17/03/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "deadpool",
        titulo: "Deadpool",
        imagen: "https://image.tmdb.org/t/p/w300/7BYksRLQ9HtZbUtanhAIdeQO9eD.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg"
      },
      {
        id: "lobezno_inmortal",
        titulo: "Lobezno inmortal",
        imagen: "https://image.tmdb.org/t/p/w300/1xeClr2YmO9fMKwvyUFb4qtI9yT.jpg"
      },
      {
        id: "pantera_negra",
        titulo: "Pantera Negra",
        imagen: "https://image.tmdb.org/t/p/w300/qUhjmU8P2OA7AG4IgqXzbwvl4Tq.jpg"
      },
      {
        id: "blue_beetle",
        titulo: "Blue beetle",
        imagen: "https://image.tmdb.org/t/p/w300/lZ2sOCMCcGaPppaXj0Wiv0S7A08.jpg"
      }
    ]
  },

  deadpool_y_wolverine: {
    id: "deadpool_y_wolverine",
    titulo: "Deadpool Y Wolverine",
    video: "https://dl.dropbox.com/scl/fi/0jllkqnacl4shcm9twz2x/Deadpool.and.wolverine.2024.1080p-dual-lat-cinecalidad.re.mp4?rlkey=z10p865x2422x67z41cg3pu30&st=",
    poster: "https://image.tmdb.org/t/p/w780/f6TCICUC8OSBtZDKgg18T6PjfIM.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un apático Wade Wilson se afana en la vida civil tras dejar atrás sus días como Deadpool, un mercenario moralmente flexible. Pero cuando su mundo natal se enfrenta a una amenaza existencial, Wade debe volver a vestirse a regañadientes con un Lobezno aún más reacio a ayudar.",
    anio: "2024",
    duracion: "2h 09min",
    calificacion: "89%",
    genero: "Acción • Comedia • Ciencia ficción • Marvel",
    director: "Shawn Lrvy",
    reparto: "Ryan Reynolds, Emma Corrin, Hung Jackman",
    estreno: "25/07/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "deadpool",
        titulo: "Deadpool",
        imagen: "https://image.tmdb.org/t/p/w300/7BYksRLQ9HtZbUtanhAIdeQO9eD.jpg"
      },
      {
        id: "deadpool_2",
        titulo: "Deadpool 2",
        imagen: "https://image.tmdb.org/t/p/w300/jA4DpT3ywxfchnTfMBiouBhq9nU.jpg"
      },
      {
        id: "doctor_strange_2",
        titulo: "Doctor strange 2: En el multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg"
      },
      {
        id: "thor_ragnarok3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      },
      {
        id: "los_vengadores_endgame",
        titulo: "Los vengadores: Endgame",
        imagen: "https://image.tmdb.org/t/p/w300/br6krBFpaYmCSglLBWRuhui7tPc.jpg"
      }
    ]
  },

  desafiante_rivales: {
    id: "desafiante_rivales",
    titulo: "Desafiante rivales",
    video: "https://dl.dropbox.com/scl/fi/hy4wfwbsn4t00joc9lv74/Challengers.2024.1080p-dual-lat-cinecalidad.re.mp4?rlkey=1w644zncwe5vtj2dyqur6j8zu&st=",
    poster: "https://image.tmdb.org/t/p/w780/4CcUgdiGe83MeqJW1NyJVmZqRrF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Casada con un campeón con una mala racha de derrotas, la estrategia de Tashi para la redención de su marido da un giro sorprendente cuando éste debe enfrentarse a Patrick (su antiguo mejor amigo y ex novio de Tashi). A medida que sus pasados y presentes chocan, y las tensiones se disparan, Tashi debe preguntarse a sí misma cuánto le costará ganar.",
    anio: "2024",
    duracion: "2h 11min",
    calificacion: "81%",
    genero: "Drama • Romance",
    director: "Luca Guadagnico",
    reparto: "Zendaya, Mike Faist, Josh O'Connor",
    estreno: "25/04/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "after_3",
        titulo: "After 3: Amor infinito",
        imagen: "https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Días",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseo prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa mia 2: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "cincuenta_sombras_más_oscuras_2",
        titulo: "Cincuenta sombras más oscuras 2",
        imagen: "https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg"
      }
    ]
  },

  /*E*/

  el_bufon: {
    id: "el_bufon",
    titulo: "El bufón",
    video: "https://dl.dropbox.com/scl/fi/2j2znk120azkf1ba646w4/El.Buf-n.2023.1080P-Dual-Lat.mp4?rlkey=1wmowabjy8qg8niel8mlhycxf&st=",
    poster: "https://image.tmdb.org/t/p/w780/5akfl4CrFYbapAZCeKsJx502Mws.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/6a6PmabZ32a0xIn2TJx4MGKN6Q6.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un ser malévolo conocido como El Bufón aterroriza a los habitantes de un pequeño pueblo en la noche de Halloween, incluidas dos hermanas distanciadas que deben unirse para encontrar una manera de derrotar a esta entidad maligna.",
    anio: "2023",
    duracion: "1h 30min",
    calificacion: "81%",
    genero: "Terror • Misterio",
    director: "Colin Krawchu",
    reparto: "Matt Servitto, Lelia Symington, Delaney White",
    estreno: "29/09/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_bufon_2",
        titulo: "El bufón 2",
        imagen: "https://image.tmdb.org/t/p/w300/47dsw1jSOV0Be5zmy7CtLhYpqU.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El conjuro 3: El diablo me obligo hacerlo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      },
      {
        id: "tarot",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/r8kgyBIT5umT330gISJH5hqRhhy.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        id: "five_nigth_at_freddys",
        titulo: "Five Nights at Freddy's",
        imagen: "https://image.tmdb.org/t/p/w300/7BpNtNfxuocYEVREzVMO75hso1l.jpg"
      },
      {
        id: "ofrenda_al_demonio",
        titulo: "Ofrenda al demonio",
        imagen: "https://image.tmdb.org/t/p/w300/7C1T0aFplHKaYacCqRdeGYLTKCW.jpg"
      }
    ]
  },

  el_arca_de_noe: {
    id: "el_arca_de_noe",
    titulo: "El arca de Noé",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmar24%2FVer%20Arca%20de%20No%C3%A9%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/qSc5JzPvSm6KxVv54nrn7SNXFtk.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fRaBjht3S1HU6lJrz2SoFwwOZQM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Dos ratones: Vini, un carismático poeta con un terrible miedo escénico, y Tito, un talentoso y encantador guitarrista. Cuando llega el diluvio, solo se permite que un macho y una hembra de cada especie suban al Arca de Noé. Con la ayuda de una ingeniosa cucaracha y buena suerte, Vini y Tito se cuelan en el Arca y juntos intentarán evitar el enfrentamiento entre carnívoros y herbívoros. ¿Podrán estos talentosos polizones usar la música para romper la tensión y ayudar a todas las especies a convivir sin comerse unos a otros durante 40 días y 40 noches?.",
    anio: "2024",
    duracion: "1h 35min",
    calificacion: "73%",
    genero: "Animación • Música • Familia • Fantasía • Comedia",
    director: "Alois Di Leo",
    reparto: "Rodrigo Santoro, Marcelo Adnet, Alice Braga",
    estreno: "07/11/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_rey_mono",
        titulo: "El rey mono",
        imagen: "https://image.tmdb.org/t/p/w300/vJ9MZHG3XQDusXLIbhRAzTlcZ2v.jpg"
      },
      {
        id: "elemental",
        titulo: "Elemental",
        imagen: "https://image.tmdb.org/t/p/w300/8riWcADI1ekEiBguVB9vkilhiQm.jpg"
      },
      {
        id: "intesanmente_2",
        titulo: "Intesanmente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      },
      {
        id: "el_pajaro_loco_se_va_de_campamento",
        titulo: "El pájaro Loco se va de campamento",
        imagen: "https://image.tmdb.org/t/p/w300/x7QXH6T8oTKlUbKt8TD1rPimzCr.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/lWEUafLv3z00YB70ZInXLNnWRik.jpg"
      }
    ]
  },

  un_jefe_en_pañales: {
    id: "un_jefe_en_pañales",
    titulo: "El bebé jefazo",
    video: "https://dl.dropbox.com/scl/fi/b1czcia7ews3wvynxmiso/The.boss.baby.2017.1080p-dual-lat.mp4?rlkey=x7cvmwxi7swrozabuzs3nm51y&st=",
    poster: "https://image.tmdb.org/t/p/w780/4IrZC1uuaDpGScO4TDyEe4E4bq2.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/dPiXM1aFbJ9XJGPyf5ZULmEjzkR.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La llegada de un hermanito trastoca por completo la idílica vida del pequeño Tim, hasta entonces hijo único de 7 años y el ojito derecho de sus padres. Su nuevo hermano es un peculiar bebé, que viste traje y corbata y lleva maletín. Tim comienza a sospechar de él, hasta que descubre que puede hablar.",
    anio: "2017",
    duracion: "1h 37min",
    calificacion: "84%",
    genero: "Animación • Comedia • Familia • Aventura",
    director: "Tom McGrath",
    reparto: "Alec Baldwin, Steve Buscemi, Miles Bakshi",
    estreno: "31/03/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "un_jefe_en_pañales_2",
        titulo: "Un jefe en pañales 2",
        imagen: "https://image.tmdb.org/t/p/w300/kv2Qk9MKFFQo4WQPaYta599HkJP.jpg"
      },
      {
        id: "sonic_3",
        titulo: "Sonic 3: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"
      },
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man: Cruzando el multiverso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "super_mario_bros",
        titulo: "Super Mario Bros: La película",
        imagen: "https://image.tmdb.org/t/p/w300/7k4fOuxA4vhblSSa5cTDRLlR7jU.jpg"
      },
      {
        id: "leo",
        titulo: "Leo",
        imagen: "https://image.tmdb.org/t/p/w300/pD6sL4vntUOXHmuvJPPZAgvyfd9.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      }
    ]
  },

  el_astronauta: {
    id: "el_astronauta",
    titulo: "El astronauta",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fmar24%2FVer%20El%20astronauta%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/824e60sDlEXUP1vXCYNqh5RSlI5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kyYNMXbXzuAw1LpnvzheqTKNaoL.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras seis meses en una solitaria misión espacial, un astronauta hace frente a los problemas de su matrimonio con la ayuda del misterioso polizón que encuentra en su nave.",
    anio: "2024",
    duracion: "1h 48min",
    calificacion: "67%",
    genero: "Ciencia ficción • Aventura • Drama",
    director: "Johan Renck",
    reparto: "Adam Sandler, Paul Dano, Carey Mulligan",
    estreno: "01/03/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "spider_man3",
        titulo: "Spider-Man: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3LSdA2l3EmI9duIJKzNElUPs0RK.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El planeta de los simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/kkFn3KM47Qq4Wjhd8GuFfe3LX27.jpg"
      },
      {
        id: "detonantes",
        titulo: "Detonantes",
        imagen: "https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg"
      },
      {
        id: "codigo_8_parte_2",
        titulo: "Codigo 8: Parte 2",
        imagen: "https://image.tmdb.org/t/p/w300/dg6WrJUIQLU4pssA4ZucGfdOj8.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "twisters",
        titulo: "Twisters",
        imagen: "https://image.tmdb.org/t/p/w300/pjnD08FlMAIXsfOLKQbvmO0f0MD.jpg"
      }
    ]
  },

  el_asesino: {
    id: "el_asesino",
    titulo: "El Asesino",
    video: "https://dl.dropbox.com/scl/fi/ziy36tqc55pyr0i5b2t5v/el.asesino.dual.2023.mkv?rlkey=3eq9dvaeowgg7ur1u5cedshi1&st=",
    poster: "https://image.tmdb.org/t/p/w780/mRmRE4RknbL7qKALWQDz64hWKPa.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wXbAPrZTqJzlqmmRaUh95DJ5Lv1.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de un fatídico error, un asesino se enfrenta a sus jefes —y a sí mismo— en una persecución internacional de castigo, según él, no es nada personal.",
    anio: "2023",
    duracion: "2h 00min",
    calificacion: "68%",
    genero: "Crimen • Suspenso",
    director: "David Fincher",
    reparto: "Michael Fassbender, Tilda Swinton, Charles Parnell",
    estreno: "26/10/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "dulce_venganza_2",
        titulo: "Dulce venganza 2",
        imagen: "https://image.tmdb.org/t/p/w300/g1WEqWtielGmcWj0hleLhDriB7w.jpg"
      },
      {
        id: "ocho_valley",
        titulo: "Echo Valley",
        imagen: "https://image.tmdb.org/t/p/w300/1E4WCgTodyS7zo8pSp1gZlPO0th.jpg"
      },
      {
        id: "capitan_america1",
        titulo: "Capitán América: El primer vengador",
        imagen: "https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"
      },
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      },
      {
        id: "el_hoyo_2",
        titulo: "El hoyo 2",
        imagen: "https://image.tmdb.org/t/p/w300/jHGgM019xAoy62cKZtDmTxvQlUY.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El planeta de los simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/kkFn3KM47Qq4Wjhd8GuFfe3LX27.jpg"
      }
    ]
  },

  el_bosque_de_los_suicidios: {
    id: "el_bosque_de_los_suicidios",
    titulo: "El bosque de los suicidios",
    video: "https://dl.dropbox.com/scl/fi/muu5ip103tcqut0mdsu9g/el-bosque-de-los-suicidios.mkv?rlkey=kg9n3w99tjxtlx11z98ktoyaq&st=",
    poster: "https://image.tmdb.org/t/p/w780/c6GGumaw3bvhX15NXK30heuCnaC.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xrk5IwznK8x5kR2BlBYdu2H5GcI.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Sara es una chica estadounidense que busca a su hermana gemela desaparecida en el bosque Aokigahara, a los pies del Monte Fuji en Japón. A pesar de las advertencias de todo el mundo para que no entre en el bosque, la joven acaba yendo para descubrir la verdad sobre lo sucedido y averiguar el destino de su hermana.",
    anio: "2016",
    duracion: "1h 33min",
    calificacion: "73,5%",
    genero: "Terror • Suspeno • Misterio",
    director: "Jason Zada",
    reparto: "Natalie Dormer, Eoin Macken, Stephanie Vogt",
    estreno: "04/02/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "terrifier_3",
        titulo: "Terrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      },
      {
        id: "azrael",
        titulo: "Azrael",
        imagen: "https://image.tmdb.org/t/p/w300/62sRNfaCe0GC34N8LhSdb6Sm0Fk.jpg"
      },
      {
        id: "argen_1978_a",
        titulo: "1978",
        imagen: "https://image.tmdb.org/t/p/w300/iyKixwGhGRas1ppAih8E7SG5QDZ.jpg"
      },
      {
        id: "cementerio_de_animales",
        titulo: "Cementerio de Animales",
        imagen: "https://image.tmdb.org/t/p/w300/vnw6g9c7qzNdzvpQhwWGRzBxwM0.jpg"
      },
      {
        id: "eliminar_amigo",
        titulo: "Eliminar amigo",
        imagen: "https://image.tmdb.org/t/p/w300/pzxHNiKjHL8Sz7DZ7POXXqohxet.jpg"
      },
      {  
        id: "el_hoyo_2",
        titulo: "El hoyo 2",
        imagen: "https://image.tmdb.org/t/p/w300/jHGgM019xAoy62cKZtDmTxvQlUY.jpg"
      },
    ]
  },

  el_gato_con_botas: {
    id: "el_gato_con_botas",
    titulo: "El gato con botas",
    video: "https://dl.dropbox.com/scl/fi/sv3tgf0v42tyqgij9ww7l/Gato.Con.Botas.2011.1080P-Dual-Lat.mkv?rlkey=samzxrq49zbdkr47jjrzxi5gr&st=",
    poster: "https://image.tmdb.org/t/p/w780/9Jf5uqvxGfpd0lXUB71iglugrjM.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Mucho antes de que conociera a Shrek, el conocido espadachín, amante y fuera de la ley Gato con Botas se convierte en un héroe al emprender una aventura junto a la dura y espabilada Kitty Zarpassuaves y el astuto Humpty Dumpty para salvar a su pueblo. Complicándoles las cosas por el camino están los infames forajidos Jack y Jill, que harán cualquier cosa para que Gato y su banda no lo consigan.",
    anio: "2011",
    duracion: "1h 31min",
    calificacion: "85%",
    genero: "Animación • Aventura • Fantasía • Comedia • Familia",
    director: "Chris Miller",
    reparto: "Antonio Banderas, Salma Hayek Pinault, Zach Galifianakis",
    estreno: "08/12/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_gato_con_botas_2",
        titulo: "El gato con botas 2: El último deseo",
        imagen: "https://image.tmdb.org/t/p/w300/ygqZ758t5oBYKP1y8LHdeflNW79.jpg"
      },
      {
        id: "shrek_2",
        titulo: "Shrek 2",
        imagen: "https://image.tmdb.org/t/p/w300/qaB0t4temTE7L96nQwOnIFJ6HzA.jpg"
      },
      {
        id: "vivo",
        titulo: "VIVO",
        imagen: "https://image.tmdb.org/t/p/w300/yzZFLQQnjJCgG8iYfcF4JqmdBMo.jpg"
      },
      {
        id: "enredados",
        titulo: "Enredados",
        imagen: "https://image.tmdb.org/t/p/w300/34ycj8YVbraioUWlUP3AdE1ZJOc.jpg"
      },
      {
        id: "angry_birds_2",
        titulo: "Angry Birds 2",
        imagen: "https://image.tmdb.org/t/p/w300/yIkmA2y50NYJkAYT9nZ5pcle9tP.jpg"
      },
      {
        id: "red",
        titulo: "Red",
        imagen: "https://image.tmdb.org/t/p/w300/djM4COTksd5YRIdd9uEl8eA3iaa.jpg"
      }
    ]
  },

  el_gato_con_botas_2: {
    id: "el_gato_con_botas_2",
    titulo: "El gato con botas: El último deseo",
    video: "https://dl.dropbox.com/scl/fi/xxwt7vsy5dr0ibzmzalxq/Gato.Con.Botas-.El.-ltimo.Deseo.2022.1080P-Dual-Lat.mp4?rlkey=hg39kqdjdze54mpi3d07nu3th&st=",
    poster: "https://image.tmdb.org/t/p/w780/jNyZcfBd10rIChqZ5aqFJMpWA0n.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ygqZ758t5oBYKP1y8LHdeflNW79.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El Gato con Botas se embarca en un viaje épico para encontrar al mítico Último Deseo y recuperar sus nueve vidas.",
    anio: "2023",
    duracion: "0h 008min",
    calificacion: "84%",
    genero: "Animación • Aventura • Fantasía • Comedia • Familia",
    director: "Joel Crawford",
    reparto: "Antonio Banderas, Salma Hayek Pinault, Harvey Guillén",
    estreno: "05/01/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_gato_con_botas",
        titulo: "El gato con botas",
        imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg"
      },
      {
        id: "la_era_de_hielo_#",
        titulo: "La era de hielo 3",
        imagen: "https://image.tmdb.org/t/p/w300/pKQAvnsf6eIL9EGEI2lAgvI94zQ.jpg"
      },
      {
        id: "los_minions",
        titulo: "Los  minions",
        imagen: "https://image.tmdb.org/t/p/w300/nmqLwaTfgyWLQWbYd82w159cAqJ.jpg"
      },
      {
        id: "los_tipos_malos",
        titulo: "Los tipos malos",
        imagen: "https://image.tmdb.org/t/p/w300/czxHSOXyKd6zEvIOvUTxAwqOjcK.jpg"
      },
      {
        id: "zootopia_2",
        titulo: "Zootopia 2",
        imagen: "https://image.tmdb.org/t/p/w300/jy3FeyNUNYIwylzapjtRx0eQw1P.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      }
    ]
  },

  el_planeta_de_los_simios: {
    id: "el_planeta_de_los_simios",
    titulo: "El planeta de los simios: [R] Evolucion",
    video: "https://dl.dropbox.com/scl/fi/s7wdlhn2o2vr4r8t8rwge/Rise.of.the.planet.of.the.apes.2011.1080p-dual-lat.mp4?rlkey=1cudv5381fhlyhwulzc955bts&st=",
    poster: "https://image.tmdb.org/t/p/w780/xDpa4rl47if5ixG1VRmzHErzy8h.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/uQsVXnHCKOzhWZUqNX0nAvMGhx7.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Will Rodman es un joven científico que está investigando con monos para obtener un tratamiento contra el alzheimer, una enfermedad que afecta a su padre. Uno de esos primates, al que llaman César, experimenta una evolución en su inteligencia tan notable que el protagonista decide llevárselo a su casa para protegerlo. Le ayudará una bella primatóloga llamada Caroline.",
    anio: "2011",
    duracion: "1h 45min",
    calificacion: "85,9%",
    genero: "Acción • Ciencia ficción • Suspenso",
    director: "Rupert Wyatt",
    reparto: "Andy Serkis, James Franco, Freida Pinto",
    estreno: "05/08/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_planeta_de_los_simios_2",
        titulo: "El planeta de los simios 2: Confrontacion",
        imagen: "https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg"
      },
      {
        id: "el_planeta_de_los_simios_3",
        titulo: "El planeta de los simios 3: La guerra",
        imagen: "https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El Planeta De Los Simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/p2wJF2CtbHhtQtnAxoHeptoSv1E.jpg"
      },
      {
        id: "kong_la_isla_calavera",
        titulo: "Kong: la Isla Calavera",
        imagen: "https://image.tmdb.org/t/p/w300/s6gT3P9Zenp2e0udMP6BYNnw18o.jpg"
      },
      {
        id: "damsel",
        titulo: "Damsel",
        imagen: "https://image.tmdb.org/t/p/w300/gh7oa9IKlu5yMveemyJkzLfopuB.jpg"
      },
      {
        id: "frente_al_Tornado",
        titulo: "Frente al tornado",
        imagen: "https://image.tmdb.org/t/p/w300/7e2BuOfD6jFQm4IPMJWubsFXdUo.jpg"
      }
    ]
  },

  el_planeta_de_los_simios_2: {
    id: "el_planeta_de_los_simios_2",
    titulo: "El planeta de los simios 2: Confrontación",
    video: "https://dl.dropbox.com/scl/fi/73jw0bwu0qkzj1iqyiup5/Dawn.of.the.planet.of.the.apes.2014.1080p-dual-lat.mp4?rlkey=d5ryregqrjsguowlu2az28lmi&st=",
    poster: "https://image.tmdb.org/t/p/w780/zlU8BIkgY7E6SMfD3USTWC6bchL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un grupo de simios con grandes cualidades para la supervivencia toma las calles, liderado por César, un simio dotado de una inteligencia y unos instintos superiores para cualquier primate. Ante la necesidad de libertad, esta raza animal decide no doblegarse ante los humanos. Tendrá que luchar contra un grupo de humanos que han sobrevivido a una fuerte epidemia, desatada en la década anterior. Ambas partes han establecido una tregua, pero se verá interrumpida.",
    anio: "2014",
    duracion: "2h 10min",
    calificacion: "80,3%",
    genero: "Acción • Ciencia ficción • Suspenso",
    director: "Matt Reeves",
    reparto: "Andy Serkis, Jason Clarke, Toby Kebbell",
    estreno: "16/07/2014",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_planeta_de_los_simios",
        titulo: "El planeta de los simios: [R] Evolucion",
        imagen: "https://image.tmdb.org/t/p/w300/ztJVSZSmTh6YHtJpMKffrPJM0DI.jp"
      },
      {
        id: "el_planeta_de_los_simios_3",
        titulo: "El planeta de los simios 3: La guerra",
        imagen: "https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El Planeta De Los Simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/p2wJF2CtbHhtQtnAxoHeptoSv1E.jpg"
      },
      {
        id: "capitan_america_3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool Y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "el_hoyo_2",
        titulo: "El hoyo 2",
        imagen: "https://image.tmdb.org/t/p/w300/jHGgM019xAoy62cKZtDmTxvQlUY.jpg"
      }
    ]
  },

  el_planeta_de_los_simios_3: {
    id: "el_planeta_de_los_simios_3",
    titulo: "El planeta de los simios 3: La guerra",
    video: "https://dl.dropbox.com/scl/fi/frq9iu4id74n1l28rnytp/War.for.the.planet.of.the.apes.2017.1080p-dual-lat.mp4?rlkey=oclamavp01zvvh98kqkyo9ssl&st=",
    poster: "https://image.tmdb.org/t/p/w780/ulMscezy9YX0bhknvJbZoUgQxO5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "César y sus monos son forzados a encarar un conflicto mortal contra un ejército de humanos liderado por un brutal coronel. Después de sufrir pérdidas enormes, César lucha con sus instintos más oscuros en una búsqueda por vengar a su especie. Cuando finalmente se encuentren, Cesar y el Coronel protagonizarán una batalla que pondrá en juego el futuro de ambas especies y el del mismo planeta. Tercera película de la nueva saga de El Planeta de los Simios.",
    anio: "2017",
    duracion: "2h 20min",
    calificacion: "78%",
    genero: "Acción • Ciencia ficción • Suspenso",
    director: "Matt Reeves",
    reparto: "Andy Serkis, Woody Harrelson, Karin Konoval",
    estreno: "03/08/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_planeta_de_los_simios",
        titulo: "El planeta de los simios: [R] Evolucion",
        imagen: "https://image.tmdb.org/t/p/w300/ztJVSZSmTh6YHtJpMKffrPJM0DI.jp"
      },
      {
        id: "el_planeta_de_los_simios_2",
        titulo: "El planeta de los simios 2: Confrontacion",
        imagen: "https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg"
      },
      {
        id: "el_planeta_de_los_simios_4",
        titulo: "El Planeta De Los Simios 4: Un nuevo reino",
        imagen: "https://image.tmdb.org/t/p/w300/p2wJF2CtbHhtQtnAxoHeptoSv1E.jpg"
      },
      {
        id: "godzilla_y_kong_el_nuevo_imperio",
        titulo: "Godzilla y Kong: El nuevo imperio",
        imagen: "https://image.tmdb.org/t/p/w300/rRLqnazAys1CQGNX5BpXN0Gbowy.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      },
      {
        id: "el_contratista",
        titulo: "El contratista",
        imagen: "https://image.tmdb.org/t/p/w300/uboar85WH92Q5Ct2Y0B2YEdYRNF.jpg"
      }
    ]
  },

  el_planeta_de_los_simios_4: {
    id: "el_planeta_de_los_simios_4",
    titulo: "El planeta de los simios 4: Un nuevo reino",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fjul24%2FVer%20El%20planeta%20de%20los%20simios-%20Nuevo%20reino%20online%20HD%20-%20Cuevana%202.mp4",
    poster: "https://image.tmdb.org/t/p/w780/f3sGWbkJ2xDDdXsXps6CRpNnPD3.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/p2wJF2CtbHhtQtnAxoHeptoSv1E.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Ambientada varias generaciones en el futuro tras el reinado de César, en la que los simios son la especie dominante que vive en armonía y los humanos se han visto reducidos a vivir en la sombra. Mientras un nuevo y tiránico líder simio construye su imperio, un joven simio emprende un angustioso viaje que le llevará a cuestionarse todo lo que sabe sobre el pasado y a tomar decisiones que definirán el futuro de simios y humanos por igual.",
    anio: "2024",
    duracion: "2h 24min",
    calificacion: "84%",
    genero: "Acción • Ciencia ficción • Suspenso",
    director: "Wes Ball",
    reparto: "Owen Teague, Freya Allan, Kevin Durand",
    estreno: "02/08/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_planeta_de_los_simios",
        titulo: "El planeta de los simios: [R] Evolucion",
        imagen: "https://image.tmdb.org/t/p/w300/ztJVSZSmTh6YHtJpMKffrPJM0DI.jp"
      },
      {
        id: "el_planeta_de_los_simios_2",
        titulo: "El planeta de los simios 2: Confrontacion",
        imagen: "https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg"
      },
      {
        id: "el_planeta_de_los_simios_3",
        titulo: "El planeta de los simios 3: La guerra",
        imagen: "https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg"
      },
      {
        id: "godzilla_vs_kong",
        titulo: "Godzilla vs Kong",
        imagen: "https://image.tmdb.org/t/p/w300/pgqgaUx1cJb5oZQQ5v0tNARCeBp.jpg"
      },
      {
        id: "kong_la_isla_calavera",
        titulo: "Kong: La isla calavera",
        imagen: "https://image.tmdb.org/t/p/w300/s6gT3P9Zenp2e0udMP6BYNnw18o.jpg"
      },
      {
        id: "lift_el_robo_de_primera",
        titulo: "Lift: El robo de primera",
        imagen: "https://image.tmdb.org/t/p/w300/gma8o1jWa6m0K1iJ9TzHIiFyTtI.jpg"
      }
    ]
  },

  el_origen_de_los_guardianes: {
    id: "el_origen_de_los_guardianes",
    titulo: "El origen de los guardianes",
    video: "https://dl.dropbox.com/scl/fi/sqjmg32yafdg3myp6kkhk/Rise.of.the.Guardians.2012.1080P-Dual-Lat.mkv?rlkey=257p5zh984mb9gz23ifdacxlj&st=",
    poster: "https://image.tmdb.org/t/p/w780/46IGtYNjpIvQYRIQlb2X493Wh8x.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kDVXsTZhssIJeZIMBC33MqmgkrQ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una aventura épica y mágica que cuenta la historia de Santa Claus, el Conejo de Pascua, el Hada de los Dientes y Jack Escarcha; personajes legendarios con desconocidas habilidades extraordinarias. Cuando un espíritu maligno, conocido como Sombra, decide inundar de miedo los corazones de los niños, los Guardianes inmortales unen sus fuerzas para proteger los deseos, las creencias y la imaginación de los niños.  ",
    anio: "2012",
    duracion: "1h 37min",
    calificacion: "76%",
    genero: "Animación • Familia • Fantasía",
    director: "Peter Ramsey",
    reparto: "Chris Pine, Alec Baldwin, Jude Law",
    estreno: "22/11/2012",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "",
        titulo: "Mr Peabody y Sherman",
        imagen: "https://image.tmdb.org/t/p/w300/c6kZC5pvwNIRSxiLL2JFGGc46He.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/mdszPVnIY7cWgbgJ8zbwu1PiU5V.jpg"
      },
      {
        id: "garfield_fuera_de_casa",
        titulo: "Garfield: Fuera de casa",
        imagen: "https://image.tmdb.org/t/p/w300/p6AbOJvMQhBmffd0PIv0u8ghWeY.jpg"
      },
      {
        id: "leo",
        titulo: "Leo",
        imagen: "https://image.tmdb.org/t/p/w300/pD6sL4vntUOXHmuvJPPZAgvyfd9.jpg"
      },
      {
        id: "las_momias_y_el_anillo_perdido",
        titulo: "Las momias y el anillo perdido",
        imagen: "https://image.tmdb.org/t/p/w300/nqt0jrqBG2zEScNkTuuRAd11Unc.jpg"
      },
      {
        id: "minions_el_origen_De_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      }
    ]
  },

  encerrado_2025: {
    id: "encerrado_2025",
    titulo: "Encerrado",
    video: "https://dl.dropbox.com/scl/fi/mz87h83azuy6q7owmqe51/Encerrado-2025-Mp4.mp4?rlkey=2flai43y0n6vec0efhrqfvgf1&st=",
    poster: "https://image.tmdb.org/t/p/w780/r4X2xRrWleVgx0kahP27xRmm3ia.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/wlo2rGpjjHh3X8XImBdeUayKJ6g.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un ladrón irrumpe en un todoterreno de lujo y se da cuenta de que ha tropezado con una trampa compleja y mortal tendida por una misteriosa figura.",
    anio: "2025",
    duracion: "1h 35min",
    calificacion: "70%",
    genero: "Terror • Suspenso",
    director: "David Yarovesky",
    reparto: "Bill Skarsgård, Anthony Hopkins, Ashley Cartwright",
    estreno: "08/05/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "el_conjuro",
        titulo: "El conjuro",
        imagen: "https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg"
      },
      {
        id: "destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"
      },
      {
        id: "abigail",
        titulo: "Abigail",
        imagen: "https://image.tmdb.org/t/p/w300/kmB9grIf2fvpwwsDmNMN0XFz1tT.jpg"
      },
      {
        id: "it_eso",
        titulo: "It (Eso)",
        imagen: "https://image.tmdb.org/t/p/w300/ha6UC0JVrVuu4KDZobgpedPyxkL.jpg"
      },
      {
        id: "evil_dead_el_despertar",
        titulo: "Evil dead:El desperta",
        imagen: "https://image.tmdb.org/t/p/w300/uwF8bBauJob5TISQ1cMHoVgIdWD.jpg"
      },
      {
        id: "megan_2",
        titulo: "M3GAN 2",
        imagen: "https://image.tmdb.org/t/p/w300/6tPr2pXIpqIldCSTKUt6GCSyvnf.jpg"
      }
    ]
  },

  el_mono: {
    id: "el_mono",
    titulo: "El mono",
    video: "https://dl.dropbox.com/scl/fi/zmxx4i4o9g0wullrcyjzt/El-mono-2025.mp4?rlkey=1h28dios3l8rxvc3gfs09tp18&st=",
    poster: "https://image.tmdb.org/t/p/w780/25CY0FggI3YXy7AS4xIfVBcRaMq.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/z15wy8YqFG8aCAkDQJKR63nxSmd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando dos hermanos gemelos encuentran un misterioso mono de cuerda, una serie de muertes atroces separan a su familia. Veinticinco años después, el mono comienza una nueva matanza que obliga a los hermanos a enfrentarse al juguete maldito.",
    anio: "2025",
    duracion: "1h 38min",
    calificacion: "60%",
    genero: "Terror",
    director: "Osgood Perkins",
    reparto: "Theo James, Tatiana Maslany, Christian Convery",
    estreno: "20/02/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "Destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"
      },
      {
        id: "un_lugar_en_silencio_3",
        titulo: "Un lugar en silencio 3: Día uno",
        imagen: "https://image.tmdb.org/t/p/w300/mB9GP9Wd7RduYpCSiqurZSnarl6.jpg"
      },
      {
        id: "tarot_de_la_muerte",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/r8kgyBIT5umT330gISJH5hqRhhy.jpg"
      },
      {
        id: "la_primera_profecia",
        titulo: "La Primera profecia",
        imagen: "https://image.tmdb.org/t/p/w300/kJkrr39cjRcfz3jR6XcGa8wSkyl.jpg"
      },
      {
        id: "el_conjuro_2",
        titulo: "El Conjuro 2: El caso Enfield",
        imagen: "https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg"
      },
      {
        id: "baghead_contacto_con_la_muerte",
        titulo: "Baghead: Contacto con la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/5ssaCHmqvTZDVZtcNhNZTzfb7Nj.jpg"
      }
    ]
  },


  /*I*/

  it_2017: {
    id: "it_2017",
    titulo: "It (eso)",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/qVGpxnjrGlHaSTCqTQI6viBDSfp.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qIT7jyxnQrjwxLa021yBpgIFxOA.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Remake del clásico de Stephen King en el que un payaso aterroriza a los niños de un vecindario. En un pequeño pueblo de Maine, siete niños conocidos como el Club de los Perdedores se encuentran cara a cara con problemas de la vida, matones y un monstruo que toma la forma de un payaso llamado Pennywise.",
    anio: "2017",
    duracion: "0h 008min",
    calificacion: "81,7%",
    genero: "Terror • Suspenso",
    director: "Andy Muschietti",
    reparto: "Jaeden Martell, Jeremy Ray Taylor, Sophia Lillis",
    estreno: "21/09/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        href: "../View Series/IT Bienvenidos a Derry (2025).php",
        titulo: "It: Bienvenidos a Derry",
        imagen: "https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg"
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },
  
  intensamente_2: {
    id: "intensamente_2",
    titulo: "Intensamente 2",
    video: "https://dl.dropbox.com/scl/fi/24m32j58fus8nbfsfodbz/Intensamente-2-2024.mp4?rlkey=8rs1t247kjorwo3illourso2f&st=",
    poster: "https://image.tmdb.org/t/p/w780/xg27NrXi7VXCGUr7MG75UqLl6Vg.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/4HEJdpcmTGm3BWWic31G4aCnuC6.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Riley, ahora adolescente, enfrenta una reforma en la Central de sus emociones. Alegría, Tristeza, Ira, Miedo y Asco deben adaptarse a la llegada de nuevas emociones: Ansiedad, Vergüenza, Envidia y Ennui.",
    anio: "2024",
    duracion: "1h 36min",
    calificacion: "86%",
    genero: "Animacion • Aventura • Disney • Familia",
    director: "Kelsey Mann",
    reparto: "Amy Poehler, Maya Hawke, Kensington Tallman",
    estreno: "13/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "intensamente",
        titulo: "Intensamente",
        imagen: "https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg"
      },
      {
        id: "sing_cantar",
        titulo: "Sing: Cantar",
        imagen: "https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg"
      },
      {
        id: "turbo",
        titulo: "Turbo",
        imagen: "https://image.tmdb.org/t/p/w300/ysNUm2zWPkJQKa3Op0N4EmqrZ0h.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      },
      {
        id: "pinocho",
        titulo: "Pinocho",
        imagen: "https://image.tmdb.org/t/p/w300/nsnyd6MFznuFSaHk1iveAdWc3nI.jpg"
      },
      {
        id: "valiente",
        titulo: "Valiente",
        imagen: "https://image.tmdb.org/t/p/w300/iNmfdZ1UqYoccsUxfuQJDcZ7De5.jpg"
      }
    ]
  },

  intensamente: {
    id: "intensamente",
    titulo: "Intensamente",
    video: "https://dl.dropbox.com/scl/fi/nsxuun1nwqbfdj0xwcgf4/Inside.Out.2015.1080P-Dual-Lat.mkv?rlkey=fltrr22tpdcyhfb6yl3aor88j&st=",
    poster: "https://image.tmdb.org/t/p/w780/j29ekbcLpBvxnGk6LjdTc2EI5SA.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Riley, de 11 años, se muda a una nueva ciudad, sus Emociones se unen para ayudarla en la transición. Alegría, Miedo, Ira, Desagrado y Tristeza trabajan juntas, pero cuando Alegría y Tristeza se pierden, deben recorrer lugares desconocidos para regresar a casa.",
    anio: "2015",
    duracion: "1h 34min",
    calificacion: "87%",
    genero: "Animacion • Drama • Aventura • Disney • Fantasia",
    director: "Pete Docter",
    reparto: "Amy Poehler, Phyllis Smith, Richard Kind",
    estreno: "17/06/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/mdszPVnIY7cWgbgJ8zbwu1PiU5V.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      },
      {
        id: "madagascar_3",
        titulo: "Madagascar 3: De marcha por Europa",
        imagen: "https://image.tmdb.org/t/p/w300/l7d5JCkwvGrqiQcppobohXYnjxt.jpg"
      },
      {
        id: "los_croods",
        titulo: "Los Croods",
        imagen: "https://image.tmdb.org/t/p/w300/p7lJkqHlK01nr0zNacunUFI5Qxy.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      }
    ]
  },

  iron_man_1: {
    id: "iron_man_1",
    titulo: "Iron-Man",
    video: "https://dl.dropbox.com/scl/fi/2q1vvm0iypz67dsc5renj/Iron.man.2008.1080p-dual-lat.mp4?rlkey=cwjc2t9wlt91aqx01wepukzrd&st=",
    poster: "https://image.tmdb.org/t/p/w780/cyecB7godJ6kNHGONFjUyVN9OX5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bFj7XRg5avQDvuvWaag3IttjEAw.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El multimillonario fabricante de armas Tony Stark debe enfrentarse a su turbio pasado después de sufrir un accidente con una de sus armas. Equipado con una armadura de última generación tecnológica, se convierte en 'El hombre de hierro' para combatir el mal a escala global.",
    anio: "2008",
    duracion: "2h 06min",
    calificacion: "77%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Jon Favreau",
    reparto: "Robert Downey Jr, Terrence Howard, Jeff Bridges",
    estreno: "30/04/2008",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "iron_man_2",
        titulo: "Iron-Man 2",
        imagen: "https://image.tmdb.org/t/p/w300/1NHEyFPxKnsLdMuDVPy6AI7GRmE.jpg"
      },
      {
        id: "iron_man_3",
        titulo: "Iron-Man 3",
        imagen: "https://image.tmdb.org/t/p/w300/2ZZhlnlkYIMHXsjaHH7ywNVy89k.jpg"
      },
      {
        id: "capitan_america_3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "el_hombre_araña_3",
        titulo: "El hombre araña 3",
        imagen: "https://image.tmdb.org/t/p/w300/qFmwhVUoUSXjkKRmca5yGDEXBIj.jpg"
      },
      {
        id: "los_vengadores",
        titulo: "Los vengadores",
        imagen: "https://image.tmdb.org/t/p/w300/ugX4WZJO3jEvTOerctAWJLinujo.jpg"
      }
    ]
  },

  iron_man_2: {
    id: "iron_man_2",
    titulo: "Iron-Man 2",
    video: "https://dl.dropbox.com/scl/fi/k35aqcaji7hcsd5czkzs2/Iron.man.2.2010.1080P-Dual-Lat.mp4?rlkey=bekd8y72w9e0mfb7fmb6xw8qf&st=",
    poster: "https://image.tmdb.org/t/p/w780/7lmBufEG7P7Y1HClYK3gCxYrkgS.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1NHEyFPxKnsLdMuDVPy6AI7GRmE.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El mundo sabe que el multimillonario Tony Stark es Iron Man, el superhéroe enmascarado. Sometido a presiones por parte del gobierno, la prensa y la opinión pública para que comparta su tecnología con el ejército, Tony es reacio a desvelar los secretos de la armadura de Iron Man porque teme que esa información pueda caer en manos indeseables.",
    anio: "2010",
    duracion: "2h 04min",
    calificacion: "70%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Jon Favreau",
    reparto: "Robert Downey Jr., Gwyneth Paltrow, Don Cheadle",
    estreno: "07/05/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "iron_man",
        titulo: "Iron-Man",
        imagen: "https://image.tmdb.org/t/p/w300/bFj7XRg5avQDvuvWaag3IttjEAw.jpg"
      },
      {
        id: "iron_man_3",
        titulo: "Iron-Man 3",
        imagen: "https://image.tmdb.org/t/p/w300/2ZZhlnlkYIMHXsjaHH7ywNVy89k.jpg"
      },
      {
        id: "lobezno_inmortal",
        titulo: "Lobezno inmortal",
        imagen: "https://image.tmdb.org/t/p/w300/1xeClr2YmO9fMKwvyUFb4qtI9yT.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool Y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"
      },
      {
        id: "capitan_america_3",
        titulo: "Capitán América 3: Civil war",
        imagen: "https://image.tmdb.org/t/p/w300/fwqAK9Vlh14mWMX3GNMi11P8XR4.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      }
    ]
  },

  /*K*/

  kung_fu_panda_4: {
    id: "kung_fu_panda_4",
    titulo: "Kung fu panda 4",
    video: "https://dl.dropbox.com/scl/fi/xu02xc78tpp4n1950ptz2/Kung.Fu.Panda.4.2024.1080P-Dual-Lat.mp4?rlkey=ktsfbpax3rem6rmlhhbxlfqmm&st=",
    poster: "https://image.tmdb.org/t/p/w780/4z88bpDf7aqZcYkLDDEIdj8TfZU.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Po se prepara para convertirse en el líder espiritual de su Valle de la Paz, pero también necesita a alguien que ocupe su lugar como Guerrero Dragón. Por ello, entrenará a un nuevo practicante de kung fu para el puesto y se encontrará con un villano llamado Camaleón, que invoca villanos del pasado.",
    anio: "2024",
    duracion: "1h 33min",
    calificacion: "84%",
    genero: "Animacion • Fantasia • Aventura • Accion",
    director: "Mike Mitchell",
    reparto: "Jack Black, Awkwafina, Viola Davis",
    estreno: "07/03/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "kung_fu_panda",
        titulo: "Kung Fu Panda",
        imagen: "https://image.tmdb.org/t/p/w300/vfsIQ2awFz5j4se9G1hsjQrEWX4.jpg"
      },
      {
        id: "kung_fu_panda_2",
        titulo: "Kung Fu Panda 2",
        imagen: "https://image.tmdb.org/t/p/w300/xTgDQql0HrvnlUxMaggRpd7EpgL.jpg"
      },
      {
        id: "kung_fu_panda_3",
        titulo: "Kung Fu Panda 3",
        imagen: "https://image.tmdb.org/t/p/w300/rGR4ggIJiK1VH3CN0M0V88cNHQo.jpg"
      },
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man: Cruzando el multiverso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "sonic_3",
        titulo: "Sonic 3: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
  

  /*L*/

  la_novia_cadaver: {
    id: "la_novia_cadaver",
    titulo: "La novia cadáver",
    video: "https://dl.dropbox.com/scl/fi/gyt6wbkcag0tpqkrl7tgv/Elcad-verdelanovia.2005.1080P-Dual-Lat-1.mp4?rlkey=xgwj65bxbf2wqd9p66v0znkqi&st=",
    poster: "https://image.tmdb.org/t/p/w780/v23fWgJUEt8EMmvn19btIacxP8E.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/3ALM0VeZjGUryAqWo6pqohzbLDh.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Ambientada en un pueblecito europeo en el siglo XIX, esta película de animación fotograma a fotograma cuenta la historia de Victor, un joven que es llevado de repente al infierno, donde se casa con una misteriosa Novia Cadáver, mientras que su verdadera novia, Victoria espera en el mundo de los vivos.",
    anio: "2005",
    duracion: "1h 17min",
    calificacion: "79%",
    genero: "Animación • Romance • Fantasía",
    director: "Mike Johnson y Tim Burton",
    reparto: "Johnny Depp, Helena Bonham Carter, Emily Watson",
    estreno: "13/10/2005",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "vivo",
        titulo: "Vivo",
        imagen: "https://image.tmdb.org/t/p/w300/yzZFLQQnjJCgG8iYfcF4JqmdBMo.jpg"
      },
      {
        id: "flow_un_mundo_que_salvar",
        titulo: "Flow, un mundo que salvar",
        imagen: "https://image.tmdb.org/t/p/w300/337MqZW7xii2evUDVeaWXAtopff.jpg"
      },
      {
        id: "la_familia_del_futuro",
        titulo: "La familia del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/1V34tiUPo3memMuCFlGhpA7ODbj.jpg"
      },
      {
        id: "peabody_y_sherman",
        titulo: "Mr Peabody y Sherman",
        imagen: "https://image.tmdb.org/t/p/w300/c6kZC5pvwNIRSxiLL2JFGGc46He.jpg"
      },
      {
        id: "las_momias_y_el_anillo_perdido",
        titulo: "Las momias y el anillo perdido",
        imagen: "https://image.tmdb.org/t/p/w300/nqt0jrqBG2zEScNkTuuRAd11Unc.jpg"
      },
      {
        id: "angry_birds",
        titulo: "Angry Birds",
        imagen: "https://image.tmdb.org/t/p/w300/wHlxud7DzsolgvasBFt6gFHIUwP.jpg"
      }
    ]
  },

  la_conexion_sueca: {
    id: "la_conexion_sueca",
    titulo: "El vínculo sueco",
    video: "https://dl.dropbox.com/scl/fi/r2j6y7q6h4p89e8htk8ex/La-conexi-n-sueco-2026.mp4?rlkey=ncbj2udfa5pvfy5mbqec1doqw&st=",
    poster: "https://image.tmdb.org/t/p/w780/56VfTGANetZ5IIYQsXuciNePM28.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/snlnvSB232OZwPCuO8zkWYJ6P7j.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El burócrata del Ministerio de Asuntos Exteriores sueco, Gösta Engzell, ignorado durante la Segunda Guerra Mundial, rescató a miles de personas y convirtió a la supuesta neutral Suecia en una fuerza moral. Sus esfuerzos desafiaron el statu quo y dejaron un legado humanitario perdurable.",
    anio: "2026",
    duracion: "1h 42min",
    calificacion: "80%",
    genero: "Drama • Historia • Guerra",
    director: "Marcus Olsson",
    reparto: "Henrik Dorsin, Sissela Benn, Jonas Karlsson",
    estreno: "19/02/2026",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "mision_imposible_2",
        titulo: "Misión de rescate 2",
        imagen: "https://image.tmdb.org/t/p/w300/szsOY5gX0jV6PHqXgvHNJlos8h9.jpg"
      },
      {
        id: "heroico",
        titulo: "Heroico",
        imagen: "https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"
      },
      {
        id: "rapido_y_furioso_x",
        titulo: "Rapidos y furiosos X",
        imagen: "https://image.tmdb.org/t/p/w300/AcwmKWzrJ9tMPjU8jU9XlEpmsmZ.jpg"
      },
      {
        id: "uncharted",
        titulo: "Uncharted: Fuera del mapa",
        imagen: "https://image.tmdb.org/t/p/w300/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg"
      },
      {
        id: "Fineskind",
        titulo: "Fineskind: Entre hermanos",
        imagen: "https://image.tmdb.org/t/p/w300/90D6sXfbXKhDpd4S1cHICdAe8VD.jpg"
      },
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      }
    ]
  },

  love_me_love_me: {
    id: "love_me_love_me",
    titulo: "Love me, Love me",
    video: "https://dl.dropbox.com/scl/fi/wnkfefp6ec2t6twy0pvvu/Love-me-love-me-2026.mp4?rlkey=hltzsxjzz6jyiosdslnwdrf67&st=",
    poster: "https://image.tmdb.org/t/p/w780/o0jRpVznKXuLvoXQX9UTKVtGjxK.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras la muerte de su hermano, June se muda a Milán para empezar de nuevo, encontrando consuelo en Will, el estudiante de honor perfecto en su nueva escuela internacional. Pero cuando su atribulado mejor amigo, James, quien oculta una vida peligrosa en peleas clandestinas de MMA, desata una rivalidad que rápidamente se convierte en una atracción irresistible, June debe elegir entre la seguridad y un amor que trastoca todo lo que creía desear.",
    anio: "2026",
    duracion: "1h 39min",
    calificacion: "70%",
    genero: "Romance • Drama",
    director: "Roger Kumble",
    reparto: "Pepe Barroso, Mia Jenkins, Luca Melucci",
    estreno: "13/02/2026",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "la_empleada",
        titulo: "La empleada",
        imagen: "https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg"
      },
      {
        id: "ojala_estuvieras_aqui",
        titulo: "Ojalá estuvieras aquí",
        imagen: "https://image.tmdb.org/t/p/w300/8sxm0NyS72bf7G88jFPOYqGBZyG.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      },
      {
        id: "nahir",
        titulo: "Nahir",
        imagen: "https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool Y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg"
      },
      {
        id: "bad_boys_4",
        titulo: "Bad boys 4: Hasta la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"
      }
    ]
  },

  la_empleada: {
    id: "la_empleada",
    titulo: "La empleada",
    video: "https://dl.dropbox.com/scl/fi/05cykeak5rs2jyalt0mdw/La-empleada-2026.mp4?rlkey=q1b7vbu30cdl0yoi3lcizav50&st=",
    poster: "https://image.tmdb.org/t/p/w780/tNONILTe9OJz574KZWaLze4v6RC.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Intentando escapar de su pasado, Millie Calloway acepta un trabajo como empleada doméstica interna para los adinerados Nina y Andrew Winchester. Pero lo que comienza como un trabajo de ensueño pronto se convierte en algo mucho más peligroso: un juego sensual y seductor de secretos, escándalos y poder.",
    anio: "2026",
    duracion: "2h 11min",
    calificacion: "72%",
    genero: "Drama • Misterio • Suspenso",
    director: "Pablo Feig",
    reparto: "Sídney Sweeney, Amanda Seyfried, Brandon Sklenar",
    estreno: "01/01/2026",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "love_me_love_me",
        titulo: "Love me, Love me",
        imagen: "https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg"
      },
      {
        id: "la_conexion_sueca",
        titulo: "El vínculo sueco",
        imagen: "https://image.tmdb.org/t/p/w300/snlnvSB232OZwPCuO8zkWYJ6P7j.jpg"
      },
      {
        id: "cortafuego",
        titulo: "Cortafuego",
        imagen: "https://image.tmdb.org/t/p/w300/gJFTLShjozMdgg3nSJCzzs9R5XU.jpg"
      },
      {
        id: "pavane",
        titulo: "Pavane",
        imagen: "https://image.tmdb.org/t/p/w300/wOXc8stx1CLvM6GC0ABKfWOkbYw.jpg"
      },
      {
        id: "goat_como_cabras",
        titulo: "GOAT: Como cabras",
        imagen: "https://image.tmdb.org/t/p/w300/wfuqMlaExcoYiUEvKfVpUTt1v4u.jpg"
      },
      {
        id: "solo_amigos",
        titulo: "¡Uf! ¿Solo amigos?",
        imagen: "https://image.tmdb.org/t/p/w300/fDcHWsESmG7j8fnVbPxR6dQz0vA.jpg"
      }
    ]
  },

  los_vengadores_infinity_war: {
    id: "los_vengadores_infinity_war",
    titulo: "Los Vengadores: Infinity War",
    video: "https://dl.dropbox.com/scl/fi/sqmr5y1rfwcnbfq0wl9k7/Avengers-Infinity-War-2018-1080p-Latino.mkv?rlkey=zl5w5m6t7jsmix6asdum87qae&st=",
    poster: "https://image.tmdb.org/t/p/w780/kbGO5mHPK7rh516MgAIJUQ9RvqD.jpg",
    // 🖼 POSTER TARJETA
    imagen: "https://image.tmdb.org/t/p/w300/z58HrY2Hd9PlSpBTsZuoavfDavd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El todopoderoso Thanos ha despertado con la promesa de arrasar con todo a su paso, portando el Guantelete del Infinito, que le confiere un poder incalculable. Los únicos capaces de pararle los pies son los Vengadores y el resto de superhéroes de la galaxia, que deberán estar dispuestos a sacrificarlo todo por un bien mayor. Capitán América e Ironman deberán limar sus diferencias, Black Panther apoyará con sus tropas desde Wakanda, Thor y los Guardianes de la Galaxia e incluso Spider-Man se unirán antes de que los planes de devastación y ruina pongan fin al universo. ¿Serán capaces de frenar el avance del titán del caos?",
    anio: "2018",
    duracion: "2h 29min",
    calificacion: "94%",
    genero: "Accion • Marvel • Ciencia Ficcion",
    director: "Anthony Russo",
    reparto: "Robert Downey Jr, Chris Evans, Chris Hemsworth",
    estreno: "26/04/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "los_vengadores_endgame",
        titulo: "Los Vengadores: Endgame",
        imagen: "https://image.tmdb.org/t/p/w300/zBXAjVMp92PvGovg148Qz0IjrEF.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-Man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/kOfFNsaf6JdsZgdpGVZa2wnBVzn.jpg"
      },
      {
        id: "doctor_strange_2",
        titulo: "Doctor strange 2: El multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/xu0RftYPT4crY4ZSf9SMa5UM8dr.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "pantera_negra2",
        titulo: "Pantera Negra 2: Wakanda por siempre",
        imagen: "https://image.tmdb.org/t/p/w300/qUhjmU8P2OA7AG4IgqXzbwvl4Tq.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool Y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg"
      }
    ]
  },

  
  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  lospitufos_2025: {
    id: "lospitufos_2025",
    titulo: "Los Pitufos",
    video: "https://dl.dropbox.com/scl/fi/xcjeczkrom5iw9mmo78tv/Los-Pitufos-2025.mp4?rlkey=g3hmttnkwgkttnd0th5tj4r8a&st=",
    poster: "https://image.tmdb.org/t/p/w780/9whEVuKte4Qi0LI4TzPf7glinJW.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/zBdQclxQnEDOhDOjkKgKPW6jEHh.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Papá Pitufo es secuestrado misteriosamente por los malvados magos Razamel y Gargamel, Pitufina guía a los Pitufos en una misión al mundo real para salvarlo. Con la ayuda de nuevos amigos, los Pitufos deben descubrir qué define su destino para salvar el universo.",
    anio: "2025",
    duracion: "1h 32min",
    calificacion: "70%",
    genero: "Animacion • Fantasia • Aventura",
    director: "Chris Miller",
    reparto: "Rihanna, James Corden, Nick Offerman",
    estreno: "17/07/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "los_pitufos",
        titulo: "Los Pitufos",
        imagen: "https://image.tmdb.org/t/p/w300/ufpzGicM1hZQMzRzTRHAcIQXjXi.jpg"
      },
      {
        id: "los_pitufos_2",
        titulo: "Los Pitufos 2",
        imagen: "https://image.tmdb.org/t/p/w300/vjb5WNkwZJDIcmVrzR4GyPmPRZo.jpg"
      },
      {
        id: "los_pitufos_la_aldea_perdida",
        titulo: "Los Pitufos: La aldea perdida",
        imagen: "https://image.tmdb.org/t/p/w300/zqXn3gaMBNHNK7YHdYNidwWrJoT.jpg"
      },
      {
        id: "lilo_y_stitch",
        titulo: "Lilo & Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/d2In25p3RW9lwxEPX9zAhkg0L5l.jpg"
      },
      {
        id: "luck_suerte",
        titulo: "Luck: Suerte",
        imagen: "https://image.tmdb.org/t/p/w300/bsAP4qvSay35SSwRqqnvubJKzgW.jpg"
      },
      {
        id: "los_croods",
        titulo: "Los Croods",
        imagen: "https://image.tmdb.org/t/p/w300/27zvjVOtOi5ped1HSlJKNsKXkFH.jpg"
      }
    ]
  },

  lightyear: {
    id: "lightyear",
    titulo: "Lightyear",
    video: "https://dl.dropbox.com/scl/fi/rxnwajbcwydosci025nzv/Lightyear.2022.1080p-dual-lat-cinecalidad.re.mp4?rlkey=voeyfoclotyrg6x6d9zg0xoaq&st=",
    poster: "https://image.tmdb.org/t/p/w780/nW5fUbldp1DYf2uQ3zJTUdachOu.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Atrapado en un planeta hostil a 4,2 millones de años luz de la Tierra junto a su comandante y su tripulación, Buzz Lightyear intenta encontrar la manera de volver a casa a través del espacio y el tiempo. Pero la llegada de Zurg, una presencia imponente con un ejército de robots despiadados y una agenda misteriosa, complica aún más las cosas y pone en peligro la misión.",
    anio: "2022",
    duracion: "1h 45min",
    calificacion: "78%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "Angus MacLane",
    reparto: "Erick Selin, Jessica Angeles, Ramon Bazet",
    estreno: "17/07/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "toy_story",
        titulo: "Toy Story",
        imagen: "https://image.tmdb.org/t/p/w300/9eTdRP1wEZX2JYO7kHNk5yGBbh5.jpg"
      },
      {
        id: "toy_story_2",
        titulo: "Toy Story 2",
        imagen: "https://image.tmdb.org/t/p/w300/t1VBfUln1XwTDHYjQaijyb7m888.jpg"
      },
      {
        id: "toy_story_3",
        titulo: "Toy Story 3",
        imagen: "https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg"
      },
      {
        id: "toy_story_4",
        titulo: "Toy Story 4",
        imagen: "https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg"
      },
      {
        id: "spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/lWEUafLv3z00YB70ZInXLNnWRik.jpg"
      },
      {
        id: "metegol",
        titulo: "Metegol",
        imagen: "https://image.tmdb.org/t/p/w300/dXWCHlDvkth40JhX2ctSdK5DC9d.jpg"
      }
    ]
  },

  /*M*/

  mi_año_en_oxford: {
    id: "mi_año_en_oxford",
    titulo: "Mi año en Oxford",
    video: "https://dl.dropbox.com/scl/fi/sr3khm0rzk9xnpzycvd4y/Mi-a-o-en-Oxford-2025-Mp4.mp4?rlkey=oehp8kbdd6rnimns0087l3apf&st=",
    poster: "https://image.tmdb.org/t/p/w780/5gGdNbO2duu0IwFtgWdUUDydDFL.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iKT49ApsXGKYY3wdZ0THYhhgOBe.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Mientras cumple su sueño de estudiar en Oxford, una ambiciosa estadounidense se enamora de un encantador británico que esconde un secreto capaz de poner su vida patas arriba.",
    anio: "2025",
    duracion: "1h 52min",
    calificacion: "86,7%",
    genero: "Romance",
    director: "Iain Morris",
    reparto: "Sofia Carson, Corey Mylchreest, Esmé Kingdom",
    estreno: "01/08/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "love_me_love_me",
        titulo: "Love me, Love me",
        imagen: "https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg"
      },
      {
        id: "la_empleada",
        titulo: "La empleada",
        imagen: "https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg"
      },
      {
        id: "diario_de_mi_vagina",
        titulo: "Diario de mi vagina",
        imagen: "https://image.tmdb.org/t/p/w300/7PzGmlaai6mRUslfrdBhfXjfA1J.jpg"
      },
      {
        id: "desaparecidos_en_la_noche",
        titulo: "Desaparecidos en la noche",
        imagen: "https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"
      },
      {
        id: "ojala_estuvieras_aqui",
        titulo: "Ojala estuvieras aqui",
        imagen: "https://image.tmdb.org/t/p/w300/zVRDebamaWViYk9P7q8FgJ8CJO8.jpg"
      },
      {
        id: "pideme_lo_que_quieras",
        titulo: "Pideme lo que quieras",
        imagen: "https://image.tmdb.org/t/p/w300/5rtaLwyKAjbceww4J1ro8aA8BNB.jpg"
      }
    ]
  },

  minecraft: {
    id: "minecraft",
    titulo: "Minecraft: La pelicula",
    video: "https://dl.dropbox.com/scl/fi/6909s4hi0zmr48fn23xhe/Una.Pel-cula.De.Minecraft.2025.1080P-Dual-Lat.mkv?rlkey=dretqxyom7e469dazz7ojkf69&st=",
    poster: "https://image.tmdb.org/t/p/w780/2Nti3gYAX513wvhp8IiLL6ZDyOm.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/rZYYmjgyF5UP1AVsvhzzDOFLCwG.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuatro inadaptados se encuentran luchando con problemas ordinarios cuando de repente se ven arrastrados a través de un misterioso portal al Mundo Exterior: un extraño país de las maravillas cúbico que se nutre de la imaginación. Para volver a casa, tendrán que dominar este mundo mientras se embarcan en una búsqueda mágica con un inesperado experto artesano, Steve.",
    anio: "2025",
    duracion: "1h 41min",
    calificacion: "82,7%",
    genero: "Aventura • Comedia • Fantasia",
    director: "Jared Hess",
    reparto: "Jason Momoa, Jack Black, Emma Myers",
    estreno: "03/04/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "aquaman_y_el_reino_perdido",
        titulo: "Aquaman y el reino perdido",
        imagen: "https://image.tmdb.org/t/p/w300/a0QwtpUNIKjOlNoOVmk7d2LFnQW.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "la_bala_perdida_3",
        titulo: "La bala perdida 3",
        imagen: "https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"
      },
      {
        id: "turno_nocturno",
        titulo: "Turno nocturno",
        imagen: "https://image.tmdb.org/t/p/w300/iSSx9Bys64vlOkvkyKXtp19P7Re.jpg"
      },
      {
        id: "megan",
        titulo: "M3GAN",
        imagen: "https://image.tmdb.org/t/p/w300/d9nBoowhjiiYc4FBNtQkPY7c11H.jpg"
      }
    ]
  },

  mara: {
    id: "mara",
    titulo: "Mara",
    video: "https://dl.dropbox.com/scl/fi/65k6f2q01kfm0oziensoa/Mara.mp4?rlkey=gh6q5dr8t12qy4f2wvjy49hfp&st=",
    poster: "https://image.tmdb.org/t/p/w780/kQrGGrAylBQM0O7OkNfjgmgwIhE.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La psicóloga criminalista Kate Fuller es asignada al caso de asesinato de un hombre que aparentemente fue estrangulado mientras dormía por su esposa, y cuyo único testigo es su hija de ocho años, Sophie. Mientras Kate profundiza en el misterio de un antiguo demonio que mata a las personas mientras duermen, ella comienza a experimentar los mismos síntomas petrificantes que todas las víctimas anteriores.",
    anio: "2018",
    duracion: "1h 39min",
    calificacion: "67%",
    genero: "Terror",
    director: "Clive Tonge",
    reparto: "Olga Kurylenko, Javier Botet, Mitch Eakins",
    estreno: "07/09/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "maligno",
        titulo: "Maligno",
        imagen: "https://image.tmdb.org/t/p/w300/oCVDRqnh6xtaexTKQ8OkXD89rkL.jpg"
      },
      {
        id: "megan",
        titulo: "M3GAN",
        imagen: "https://image.tmdb.org/t/p/w300/d9nBoowhjiiYc4FBNtQkPY7c11H.jpg"
      },
      {
        id: "poseida",
        titulo: "Poseída",
        imagen: "https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg"
      },
      {
        id: "mientras_duermes",
        titulo: "Mientras duermes",
        imagen: "https://image.tmdb.org/t/p/w300/aDi56oSNirZStVwgl8R12nkQrIk.jpg"
      },
      {
        id: "saw_x",
        titulo: "Saw X",
        imagen: "https://image.tmdb.org/t/p/w300/nzVkXj1IX0BNoYzpTVogtkcCStf.jpg"
      },
      {
        id: "tarot",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/r8kgyBIT5umT330gISJH5hqRhhy.jpg"
      }
    ]
  },

  madagascar: {
    id: "madagascar",
    titulo: "Madagascar",
    video: "https://dl.dropbox.com/scl/fi/vnsocfvwlau9mslcm4phw/Madagascar.2005.1080P-Dual-Lat.mp4?rlkey=uql999w7tyw9mzfg8zjaj0234&st=",
    poster: "https://image.tmdb.org/t/p/w780/j7A8wkcW9UYvchFdWu89oHN9b6O.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/v6bFSYpmAREGriQiMJvvO9TiapM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuatro amigos animales prueban la vida salvaje cuando escapan del cautiverio en el Zoológico de Central Park y llegan a la costa de la isla de Madagascar.",
    anio: "2005",
    duracion: "1h 25min",
    calificacion: "70%",
    genero: "Animación • Aventura",
    director: "Eric Darnell y Tom McGrath",
    reparto: "Ben Stiller, Chris Rock, David Schwimmer",
    estreno: "07/07/2005",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "madagascar_2",
        titulo: "Madagascar 2",
        imagen: "https://image.tmdb.org/t/p/w300/zYbvSjajQrb2jU9rUo5Mt06stPd.jpg"
      },
      {
        id: "madagascar_3",
        titulo: "Madagascar 3: De marcha por Europa",
        imagen: "https://image.tmdb.org/t/p/w300/l7d5JCkwvGrqiQcppobohXYnjxt.jpg"
      },
      {
        id: "pinguinos_de_madagascar",
        titulo: "Pinguinos de madagascar",
        imagen: "https://image.tmdb.org/t/p/w300/dXbpNrPDZDMEbujFoOxmMNQVMHa.jpg"
      },
      {
        id: "peabody_y_sherman",
        titulo: "Mr Peabody y Sherman",
        imagen: "https://image.tmdb.org/t/p/w300/c6kZC5pvwNIRSxiLL2JFGGc46He.jpg"
      },
      {
        id: "las_momias_y_el_anillo_perdido",
        titulo: "Las momias y el anillo perdido",
        imagen: "https://image.tmdb.org/t/p/w300/nqt0jrqBG2zEScNkTuuRAd11Unc.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      }
    ]
  },

  mi_villano_favorito: {
    id: "mi_villano_favorito",
    titulo: "Mi villano favorito",
    video: "https://dl.dropbox.com/scl/fi/zqync4g3e4a390cw6wav4/Despicable.me.2010.1080P-Dual-Lat.mp4?rlkey=ntnwx7w5hvxg03jqfre8ge22x&st=",
    poster: "https://image.tmdb.org/t/p/w780/r5OvZIQJ1kIvmxNQc8SBW0PvGIb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pgDbf2DPNWVz5D8PvgsCoI21k7j.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En una alegre urbanización con cuidados jardines rodeados por verjas de madera pintadas de blanco y llenos de rosales, sobresale una casa negra con el césped amarillento. Los vecinos ignoran que debajo de la vivienda hay un enorme escondite secreto. Allí está Gru, rodeado por un pequeño ejército de lacayos, planeando el mayor robo de toda la historia. ¡Va a hacerse con la luna!.",
    anio: "2010",
    duracion: "1h 34min",
    calificacion: "73%",
    genero: "Animación • Familia • Comedia",
    director: "Chris Renaud y Pierre Coffin",
    reparto: "Steve Carell, Jason Segel, Miranda Cosgrove",
    estreno: "29/07/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "mi_villano_favorito_2",
        titulo: "Mi villano favorito 2",
        imagen: "https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg"
      },
      {
        id: "mi_villano_favorito_3",
        titulo: "Mi villano favorito 3",
        imagen: "https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg"
      },
      {
        id: "mi_villano_favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      },
      {
        id: "los_minions",
        titulo: "Los Minions",
        imagen: "https://image.tmdb.org/t/p/w300/nmqLwaTfgyWLQWbYd82w159cAqJ.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      }
    ]
  },

  mi_villano_favorito_2: {
    id: "mi_villano_favorito_2",
    titulo: "Mi villano favorito 2",
    video: "https://dl.dropbox.com/scl/fi/lj05wziedsdhkxk8p2x8p/Despicable.me.2.2013.bluray-latino-e-ingles-subt.mp4?rlkey=ylegvae9yedl91cnsssvlpnne&st=",
    poster: "https://image.tmdb.org/t/p/w780/az8kg8kyXXj1P3cF2vXzjwtf9Q5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La película de animación de los estudios Universal, giraba en torno a una apacible y colorida población en la que vivía el malvado Gru. Ayudado por su batallón de Minions, unos pequeños seres de color amarillo, intentaron robar la Luna, aunque no todo acabó como esperaban. Tres niñas curiosas y algo traviesas se cruzaron en su camino, entorpeciéndole todos sus planes y conviertiendo su objetivo en una alocada hazaña interestelar. Ahora, 'Gru, mi villano favorito 2', narra una nueva aventura en la que Gru volverá a estar acompañado de simpáticos humanoides.",
    anio: "2013",
    duracion: "1h 37min",
    calificacion: "69%",
    genero: "Animación • Familia • Comedia",
    director: "Pierre Coffin y Chris Renaud",
    reparto: "Steve Carell, Kristen Wiig, Benjamín Bratt",
    estreno: "03/07/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "mi_villano_favorito",
        titulo: "Mi villano favorito",
        imagen: "https://image.tmdb.org/t/p/w300/7ml02WwUzz4jlZJdiEI4ZIYHj1J.jpg"
      },
      {
        id: "mi_villano_favorito_3",
        titulo: "Mi villano favorito 3",
        imagen: "https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg"
      },
      {
        id: "mi_villano_favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      },
      {
        id: "minions",
        titulo: "Los Minions",
        imagen: "https://image.tmdb.org/t/p/w300/nmqLwaTfgyWLQWbYd82w159cAqJ.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      }
    ]
  },

  mi_villano_favorito_3: {
    id: "mi_villano_favorito_3",
    titulo: "Mi villano favorito 3",
    video: "https://dl.dropbox.com/scl/fi/2bnx70zw1g8d3yucky2u6/Despicable.me.3.2017.1080p-dual-lat.mp4?rlkey=93ltxh9aq0peyqxf8qy9bla9l&st=",
    poster: "https://image.tmdb.org/t/p/w780/ftRkFtAGuHngHnLiOxktq0aCVMF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "A Gru lo han despedido. Se ha quedado sin trabajo porque ha dejado escapar al supervillano Balthazar Bratt, estrella infantil de los años 80 que con la llegada de la pubertad lo perdió todo y ha estado creando el caos en todo el planeta. Además, Gru va a conocer a su hermano gemelo Dru y tendrá que decidir si está listo para continuar la tradición familiar de dedicarse al crimen, y unirse a el para llevar a cabo un último golpe. Aunque ha dejado esa vida atrás… ¿volverá a convertirse Gru en un villano?.",
    anio: "2017",
    duracion: "1h 29min",
    calificacion: "78%",
    genero: "Animación • Familia • Comedia",
    director: "Kyle Balda y Pierre Coffin",
    reparto: "Steve Carell, Kristen Wiig, Trey Parker",
    estreno: "30/06/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "mi_villano_favorito",
        titulo: "Mi villano favorito",
        imagen: "https://image.tmdb.org/t/p/w300/7ml02WwUzz4jlZJdiEI4ZIYHj1J.jpg"
      },
      {
        id: "mi_villano_favorito_2",
        titulo: "Mi villano favorito 2",
        imagen: "https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg"
      },
      {
        id: "mi_villano_favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      },
      {
        id: "los_minions",
        titulo: "Los Minions",
        imagen: "https://image.tmdb.org/t/p/w300/nmqLwaTfgyWLQWbYd82w159cAqJ.jpg"
      },
      {
        id: "gato_con_botas_2",
        titulo: "Gato con botas 2: El último deseo",
        imagen: "https://image.tmdb.org/t/p/w300/b5Jb7GoQaqIXy4VEdnQa0UrQZI.jpg"
      }
    ]
  },

  mi_villano_favorito_4: {
    id: "mi_villano_favorito_4",
    titulo: "Mi villano favorito 4",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/twsxsfao6ZOVvT8LfudH603MMi6.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "",
    anio: "2024",
    duracion: "0h 008min",
    calificacion: "84%",
    genero: "Animación • Familia • Comedia",
    director: "Chris Renaud",
    reparto: "Steve Carell, Kristen Wiig, Will Ferrell",
    estreno: "03/07/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "mi_villano_favorito",
        titulo: "Mi villano favorito",
        imagen: "https://image.tmdb.org/t/p/w300/7ml02WwUzz4jlZJdiEI4ZIYHj1J.jpg"
      },
      {
        id: "mi_villano_favorito_2",
        titulo: "Mi villano favorito 2",
        imagen: "https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg"
      },
      {
        id: "mi_villano_favorito_3",
        titulo: "Mi villano favorito 3",
        imagen: "https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg"
      },
      {
        id: "minions_el_origen_de_gru",
        titulo: "Minions: El origen de Gru",
        imagen: "https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"
      },
      {
        id: "los_minions",
        titulo: "Los Minions",
        imagen: "https://image.tmdb.org/t/p/w300/nmqLwaTfgyWLQWbYd82w159cAqJ.jpg"
      },
      {
        id: "luck_suerte",
        titulo: "Luck: Suerte",
        imagen: "https://image.tmdb.org/t/p/w300/cQDqNCtq7j5xaCXGeLsLZK90RuR.jpg"
      }
    ]
  },

  millers_girl: {
    id: "millers_girl",
    titulo: "Miller's Girl",
    video: "https://dl.dropbox.com/scl/fi/6mmlrt7ojmqzpi9wf2aei/Miller-s.Girl.2024.1080P-Dual-Lat.mkv?rlkey=ehkpbcav964gnna87mfkscvyw&st=",
    poster: "https://image.tmdb.org/t/p/w780/hMhPkVvqQz9gMMrJENciIKSQgVb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/a5YCKz2HV3xEtaOhr4I7FGe05qQ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una joven escritora precoz se involucra con su profesor de escritura creativa de la escuela secundaria en un oscuro drama sobre la mayoría de edad que examina las líneas borrosas de conectividad emocional entre profesor y protegido.",
    anio: "2024",
    duracion: "1h 33min",
    calificacion: "77%",
    genero: "Romance • Drama",
    director: "Jade Halley Bartlett",
    reparto: "Jenna Ortega, Martín Freeman, Bashir Salahuddin",
    estreno: "26/01/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Dias",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "culpa_tuya",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseo prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "desafiante_rivales",
        titulo: "Desafiante Rivales",
        imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      }
    ]
  },

  mi_abuelo_es_un_peligro: {
    id: "mi_abuelo_es_un_peligro",
    titulo: "Mi abuelo es un peligro",
    video: "https://dl.dropbox.com/scl/fi/3tjglc6m3qejrye4838im/Mi.abuelo.es.un.peligro.2016.1080p-dual-lat.mp4?rlkey=6v3r565011fuwcznfxjocjqts&st=",
    poster: "https://image.tmdb.org/t/p/w780/esh6PunfeHvgculCRvSjzwE8Kip.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/7r9pn1g3lY95DjiwzxpmNqlJzeO.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Jason Kelly está a una semana de casarse con la hija controladora de su jefe, lo que le abre las puertas a una posible incorporación a la firma de abogados. Sin embargo, cuando el puritano Jason es engañado para llevar a su malhablado abuelo, Dick, a Daytona para las vacaciones de primavera, su inminente boda se ve repentinamente comprometida.",
    anio: "2016",
    duracion: "1h 42min",
    calificacion: "80%",
    genero: "Comedia",
    director: "Dan Mazer",
    reparto: "Robert De Niro, Zac Efron, Plaza Aubrey",
    estreno: "22/01/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "donde_esta_el_fantasma_2",
        titulo: "¿Donde esta el fantasma? 2",
        imagen: "https://image.tmdb.org/t/p/w300/vRbDuqlmGPM9wGZ3VwbrjQu16Oa.jpg"
      },
      {
        id: "doblemente_embarazada_2",
        titulo: "Doblemente embarazada 2",
        imagen: "https://image.tmdb.org/t/p/w300/mNkAOFyb4TV2gTSc92jx2O9evtj.jpg"
      },
      {
        id: "chicas_malas_2024",
        titulo: "Chicas malas",
        imagen: "https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg"
      },
      {
        id: "bendita_suegra",
        titulo: "Bendita suegra",
        imagen: "https://image.tmdb.org/t/p/w300/5xupm2thQic5GzYi6nim6URMZOY.jpg"
      },
      {
        id: "tequila_repasado",
        titulo: "Tequila repasado",
        imagen: "https://image.tmdb.org/t/p/w300/7onQmk5ZzUjx6SEAiqILWQFTaYC.jpg"
      },
      {
        id: "ricky_el_impostor",
        titulo: "Ricky Stanicky: El impostor",
        imagen: "https://image.tmdb.org/t/p/w300/oJQdLfrpl4CQsHAKIxd3DJqYTVq.jpg"
      }
    ]
  },

  moana: {
    id: "moana",
    titulo: "Moana",
    video: "https://dl.dropbox.com/scl/fi/s30qf1hyi27386un4oekd/Moana.2016.1080p-dual-lat.mp4?rlkey=x95hdcwm1fujjc6j2u4m9mp4t&st=",
    poster: "https://image.tmdb.org/t/p/w780/iYLKMV7PIBtFmtygRrhSiyzcVsF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pwW2sC4ugeFaygOPu6nYCAV3JWG.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una gran aventura acerca de una enérgica adolescente que se embarca en una misión audaz para salvar a su pueblo de una antigua y misteriosa amenaza, en un viaje de autodescubrimiento. La acompañará el arrogante semidiós Maui, quien la guiará en su travesía por el océano en un viaje lleno de acción, plagado de temibles criaturas e imposibles desafíos para restaurar el orden perdido.",
    anio: "2016",
    duracion: "1h 47min",
    calificacion: "80%",
    genero: "Animación • Disney • Aventura • Familia • Comedia",
    director: "Juan Musker y Ron Clements",
    reparto: "Auliʻi Cravalho, Dwayne Johnson, Casa de Rachel",
    estreno: "26/12/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      },
      {
        id: "mufasa_rl_rey",
        titulo: "Mufasa: El rey león",
        imagen: "https://image.tmdb.org/t/p/w300/lk4NNdeQrb6zbRSogDSdE6qmjk8.jpg"
      },
      {
        id: "al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas",
        titulo: "Al rescate de fondo de Bikini: La película de Arenita Mejillas",
        imagen: "https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg"
      },
      {
        id: "el_niño_y_la_garza",
        titulo: "El niño y la garza",
        imagen: "https://image.tmdb.org/t/p/w300/jDQPkgzerGophKRRn7MKm071vCU.jpg"
      },
      {
        id: "shrek",
        titulo: "Shrek",
        imagen: "https://image.tmdb.org/t/p/w300/5G1RjHMSt7nYONqCqSwFlP87Ckk.jpg"
      }
    ]
  },

  moana_2: {
    id: "moana_2",
    titulo: "Moana 2",
    video: "https://dl.dropbox.com/scl/fi/ej548y6fqwu5y93wzgjvg/Moana.2.2024.1080P-Dual-Lat.mkv?rlkey=egjrop2zd3y0ivtnpuxnsweo5&st=",
    poster: "https://image.tmdb.org/t/p/w780/vYqt6kb4lcF8wwqsMMaULkP9OEn.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras recibir una inesperada llamada de sus antepasados, Vaiana debe viajar a los lejanos mares de Oceanía y adentrarse en peligrosas aguas perdidas para vivir una aventura sin precedentes.",
    anio: "2024",
    duracion: "1h 39min",
    calificacion: "89%",
    genero: "Animación • Disney • Aventura • Familia • Comedia",
    director: "Dana Ledoux Miller",
    reparto: "Auliʻi Cravalho, Dwayne Johnson, Hualālai Chung",
    estreno: "12/03/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "moana",
        titulo: "Moana",
        imagen: "https://image.tmdb.org/t/p/w300/pwW2sC4ugeFaygOPu6nYCAV3JWG.jpg"
      },
      {
        id: "tierra_de_osos",
        titulo: "Tierra de osos",
        imagen: "https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg"
      },
      {
        id: "coco",
        titulo: "Coco",
        imagen: "https://image.tmdb.org/t/p/w300/gGEsBPAijhVUFoiNpgZXqRVWJt2.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "lilo_y_stich_2025",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"
      },
      {
        id: "leo",
        titulo: "Leo",
        imagen: "https://image.tmdb.org/t/p/w300/pD6sL4vntUOXHmuvJPPZAgvyfd9.jpg"
      }
    ]
  },

  /*S*/

  sonic_3: {
    id: "sonic_3",
    titulo: "Sonic 3: La pelicula",
    video: "https://dl.dropbox.com/scl/fi/xnj72sohseayha1tmitgt/Sonic.3.La.Pel-cula.2024.1080P-Dual-Lat.mkv?rlkey=s0vxtwdbq5itp10bfaxy4i73b&st=",
    poster: "https://image.tmdb.org/t/p/w780/uEDY5c1VrmwMxL7OlXXTTwYygUX.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vlAXtzNWQ3VSZtIinhHqcPXS1Oc.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Sonic, Knuckles y Tails se reúnen para enfrentarse a un nuevo y poderoso adversario, Shadow, un misterioso villano con poderes nunca vistos. Con sus habilidades superadas en todos los sentidos, el Equipo Sonic debe buscar una alianza improbable con la esperanza de detener a Shadow y proteger el planeta.",
    anio: "2025",
    duracion: "1h 50min",
    calificacion: "77%",
    genero: "Acción • Ciencia ficción • Comedia • Familia",
    director: "Jeff Fowler",
    reparto: "Jim Carrey, Ben Schwartz, Keanu Reeves",
    estreno: "23/01/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sonic",
        titulo: "Sonic: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/zwS0XnNi1Vb6sQecG5GNNlKx7cv.jpg"
      },
      {
        id: "sonic_2",
        titulo: "Sonic 2: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/l0iTNJutdtP5cMjEuurPOiSonNl.jpg"
      },
      {
        id: "mi_villano_favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      },
      {
        id: "mufasa_el_rey_leon",
        titulo: "Mufasa: El rey leon",
        imagen: "https://image.tmdb.org/t/p/w300/yOivfBs1vLrPRVw2Ci4AlGkl1G3.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      }
    ]
  },

  sonrie: {
    id: "sonrie",
    titulo: "Sonríe",
    video: "https://objectstorage.us-phoenix-1.oraclecloud.com/n/axa4wow3dcia/b/bucket-20201001-1658/o/2022pelicu%2Fnoviembr%2FVer%20Smile%20Online%20Castellano%20Latino%20Subtitulada%20HD%20-%20HDFull.mp4",
    poster: "https://image.tmdb.org/t/p/w780/kMZIMqEXO5MFd5Y1Ha2jZZF4pvF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de presenciar un incidente extraño y traumático que involucra a un paciente, la Dra. Rose Cotter comienza a experimentar sucesos aterradores que no puede explicar.",
    anio: "2022",
    duracion: "1h 55min",
    calificacion: "72%",
    genero: "Terror",
    director: "-----",
    reparto: "Salsa de tocino, Kyle Gallner, Jessie T. Usher",
    estreno: "28/09/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sonrie",
        titulo: "Sonrie 2",
        imagen: "https://image.tmdb.org/t/p/w300/aQtWauWpy5KQEHsBURDnoTD6svd.jpg"
      },
      {
        id: "saw_3",
        titulo: "Saw III",
        imagen: "https://image.tmdb.org/t/p/w300/4iO9n24Rb10peXV0JH2EldIOrAp.jpg"
      },
      {
        id: "un_lugar_en_silencio_2",
        titulo: "Un lugar en silencio 2",
        imagen: "https://image.tmdb.org/t/p/w300/6uRb5axnwAd17h4ak4ENHcJqHVr.jpg"
      },
      {
        id: "winnie_the_pooh",
        titulo: "Winnie the pooh: Miel y sangre",
        imagen: "https://image.tmdb.org/t/p/w300/lfetuG7lq3MVRt6jb1kfX7Va2H.jpg"
      },
      {
        id: "cementerio_de_animales_2",
        titulo: "Cementerio de animales 2: Los orígenes",
        imagen: "https://image.tmdb.org/t/p/w300/sbzfFLgExjl7ekLeNFEZ9EwOA9V.jpg"
      },
      {
        id: "en_las_profundidades_del_sena",
        titulo: "En las profundidades del sena",
        imagen: "https://image.tmdb.org/t/p/w300/3Nr9KwcPMF31BGlOfHXeAJhO2dF.jpg"
      }
    ]
  },

  sonrie_2: {
    id: "sonrie_2",
    titulo: "Sonrie 2",
    video: "https://dl.dropbox.com/scl/fi/u5uw6fg4m9t1gfy5i1enm/Sonr-e.2.2024.1080p-Dual-Lat.mkv?rlkey=c8rosff7ig5g878aptuuymyys&st=",
    poster: "https://image.tmdb.org/t/p/w780/iR79ciqhtaZ9BE7YFA1HpCHQgX4.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/aQtWauWpy5KQEHsBURDnoTD6svd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La estrella del pop mundial Skye Riley está a punto de embarcarse en una nueva gira mundial cuando empieza a experimentar una serie de sucesos cada vez más aterradores e inexplicables. Angustiada por la espiral de horrores y la abrumadora presión de la fama, Skye tendrá que enfrentarse a su oscuro pasado para recuperar el control de su vida antes de que sea demasiado tarde. Secuela del exitoso film de terror 'Smile' (2022).",
    anio: "2024",
    duracion: "2h 09min",
    calificacion: "70%",
    genero: "Terror",
    director: "----",
    reparto: "Naomi Scott, Rosemarie DeWitt, Lucas Gage",
    estreno: "17/10/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sonrie",
        titulo: "Sonrie",
        imagen: "https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg"
      },
      {
        id: "hablame",
        titulo: "Háblame",
        imagen: "https://image.tmdb.org/t/p/w300/hQpcO9OIGXEZtm7KfUEMtZxXukI.jpg"
      },
      {
        id: "la_niña_de_la_comunion",
        titulo: "La niña de la comunión",
        imagen: "https://image.tmdb.org/t/p/w300//oV3R0E1GOXVrybojkEDvool22Bi.jpg"
      },
      {
        id: "megan",
        titulo: "M3GAN",
        imagen: "https://image.tmdb.org/t/p/w300/d9nBoowhjiiYc4FBNtQkPY7c11H.jpg"
      },
      {
        id: "no_estaras_sola",
        titulo: "No estarás sola",
        imagen: "https://image.tmdb.org/t/p/w300/moBrEYoOxLRc1LsFl8IXeilYwtq.jpg"
      },
      {
        id: "el_exorcista_del_papa",
        titulo: "El exorcista del papa",
        imagen: "https://image.tmdb.org/t/p/w300/4n7HJ322ARRWytwxLKEZi0mIrYE.jpg"
      }
    ]
  },

  spider_man_un_nuevo_universo: {
    id: "spider_man_un_nuevo_universo",
    titulo: "Spider-Man: un nuevo universo",
    video: "https://dl.dropbox.com/scl/fi/0d3cbzxc8i709uulyo20o/Spider-Mna-Un-nuevo-universo-2018-Mp4.mp4?rlkey=kxmqh3ej5i50vupheekx8vio8&st=",
    poster: "https://image.tmdb.org/t/p/w780/qhkv1h4yyuL1bxqURqYxcrUlLW9.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En un universo paralelo donde Peter Parker ha muerto, un joven de secundaria llamado Miles Morales es el nuevo Spider-Man. Sin embargo, cuando el líder mafioso Wilson Fisk construye el 'Super Colisionador' trae a una versión alternativa de Peter Parker que tratará de enseñar a Miles como ser un mejor Spider-Man. Pero no será el único Spider Man en entrar a este universo: cuatro versiones alternativas buscan regresar a su universo antes de que toda la realidad se colapse.",
    anio: "2018",
    duracion: "1h 56min",
    calificacion: "92%",
    genero: "Animación • Acción • Aventura • Ciencia Ficción",
    director: "Rodney Rothman, Bob Persichetti, Peter Ramsey",
    reparto: "Emilio Treviño, Miguel Ángel Ruiz, Alondra Hidalgo",
    estreno: "27/12/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man 2: Cruzando el Multi-Verso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "vivo",
        titulo: "Vivo",
        imagen: "https://image.tmdb.org/t/p/w300/yzZFLQQnjJCgG8iYfcF4JqmdBMo.jpg"
      },
      {
        id: "flow_un_mundo_que_salvar",
        titulo: "Flow, un mundo que salvar",
        imagen: "https://image.tmdb.org/t/p/w300/337MqZW7xii2evUDVeaWXAtopff.jpg"
      },
      {
        id: "baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma vs Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/sXybjRq6BsCkWcDBfNphSH9biqn.jpg"
      },
      {
        id: "sing_cantar",
        titulo: "Sing: Cantar",
        imagen: "https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg"
      },
      {
        id: "encanto",
        titulo: "Encanto",
        imagen: "https://image.tmdb.org/t/p/w300/vhk52Hxd43F8hNr53FI2ynljZQn.jpg"
      }
    ]
  },

  spiderman_man_cruzando_el_multi_verso_2: {
    id: "spiderman_man_cruzando_el_multi_verso_2",
    titulo: "Spider-Man 2: Cruzando el Multi-Verso",
    video: "https://dl.dropbox.com/scl/fi/mkqt6y9en3w47r5evfn0g/Spider-Man-2-Cruzando-el-Multiverso-2023.mp4?rlkey=nbovtswylku4wqmv126ls16nz&st=",
    poster: "https://image.tmdb.org/t/p/w780/2I5eBh98Q4aPq8WdQrHdTC8ARhY.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de reunirse con Gwen Stacy, el amigable Spider-Man del vecindario de Brooklyn es catapultado a través del Multiverso, donde se encuentra con la Sociedad Araña, un equipo de Spider-Man encargado de proteger la existencia misma del Multiverso. Pero cuando los héroes chocan sobre cómo manejar una nueva amenaza, Miles se encuentra enfrentado a las otras Arañas y debe emprender su propio camino para salvar a los que más ama.",
    anio: "2023",
    duracion: "2h 20min",
    calificacion: "92%",
    genero: "Animación • Acción • Aventura • Ciencia Ficción",
    director: "Justin K. Thompson, Joaquín Dos Santos y Kemp Powers",
    reparto: "Emilio Treviño, Alondra Hidalgo, José Luis Rivera",
    estreno: "01/06/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-Man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3LSdA2l3EmI9duIJKzNElUPs0RK.jpg"
      },
      {
        id: "doctor_strange_2",
        titulo: "Doctor strange 2: El multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/xu0RftYPT4crY4ZSf9SMa5UM8dr.jpg"
      },
      {
        id: "el_hombre_araña",
        titulo: "El Hombre Araña",
        imagen: "https://image.tmdb.org/t/p/w300/2ufIbl01RhJ9QkSUxD0UjDakxvk.jpg"
      },
      {
        id: "thor_ragnarok3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      },
      {
        id: "los_vengadores_endgame",
        titulo: "Los Vengadores: Endgame",
        imagen: "https://image.tmdb.org/t/p/w300/zBXAjVMp92PvGovg148Qz0IjrEF.jpg"
      }
    ]
  },

  scooby_2020: {
    id: "scooby_2020",
    titulo: "¡Scooby!",
    video: "https://dl.dropbox.com/scl/fi/s17ns7ovwacflf5v7lxoe/Scoob.2020.1080p-dual-lat-cinecalidad.is.mp4?rlkey=kv09107fadgpq8d8t6eenjocq&st=",
    poster: "https://image.tmdb.org/t/p/w780/sJjuXNHNT7PfzcgkqM3oSIkVOXB.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/tOhuq4RYr2Rt9TM7X4dkr7A9HSd.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Con cientos de casos resueltos y aventuras compartidas, Scooby y la pandilla se enfrentan al misterio más grande y desafiante de todos los tiempos: un complot para liberar al perro fantasma Cerberus en el mundo. Mientras compiten para detener esta escasez de perros global, la pandilla descubre que Scooby tiene un legado secreto y un destino épico más grande de lo que cualquiera podría haber imaginado.",
    anio: "2020",
    duracion: "1h 33min",
    calificacion: "88%",
    genero: "Animacion • Aventura • Comedia",
    director: "Tony Cervone",
    reparto: "Frank Welker, Amanda Seyfried, Will Forte",
    estreno: "15/03/2020",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "scooby_doo1",
        titulo: "Scooby-Doo",
        imagen: "https://image.tmdb.org/t/p/w300/vX6NAkWHicHDgGYa192Kt47dPWI.jpg"
      },
      {
        id: "scooby_doo2",
        titulo: "Scooby-Doo 2: Desatado",
        imagen: "https://image.tmdb.org/t/p/w300/5anMa9cWwDL6JSvmkdbCpUsFIdu.jpg"
      },
      {
        id: "scooby_doo_cypto",
        titulo: "Scooby crypto al rescate",
        imagen: "https://image.tmdb.org/t/p/w300/ntMOnvlYYnio7Fx3xqBu9B1Sz7f.jpg"
      },
      {
        id: "sonic_la_pelicula_3",
        titulo: "Sonic 3: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"
      },
      {
        id: "spiderman_man_cruzando_el_multi_verso_2",
        titulo: "Spider-Man: Cruzando el multiverso",
        imagen: "https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  sugar_baby: {
    id: "sugar_baby",
    titulo: "Sugar baby",
    video: "https://dl.dropbox.com/scl/fi/ux7t24q3r11uti6l0jwdg/Sugar-Baby-2024.mp4?rlkey=ihi8zabn7u7y9mivljpgg5kne&st=",
    poster: "https://image.tmdb.org/t/p/w780/gscL9tqxpkmAMmbeRLLzLZPyJXb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El amor llega sin pedirlo. A menudo no a quienes lo necesitan, ni así ni en ese momento. ¿Puede un sentimiento genuino sobrevivir cuando todo empezó mal y el mundo entero está en contra? ¿Renunciar a todo o rendirse? ¿Arder o sobrevivir?",
    anio: "2024",
    duracion: "1h 43min",
    calificacion: "72%",
    genero: "Romance • Drama",
    director: "Alexander Prost",
    reparto: "Daniil Vorobyov, Angelina Zagrebina, Alla Yuganova",
    estreno: "25/04/2024 ",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "millers_girl",
        titulo: "Miller's Girl",
        imagen: "https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"
      },
      {
        id: "dias_365_3",
        titulo: "365 Dias 3: Mas",
        imagen: "https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg"
      },
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa tuya",
        imagen: "https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseos prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "sonrie",
        titulo: "Sonrie",
        imagen: "https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg"
      }

    ]
  },

  sing_cantar: {
    id: "sing_cantar",
    titulo: "Sing: Cantar",
    video: "https://dl.dropbox.com/scl/fi/ti5ljw8be0676y80bubaj/Sing.2016.1080P-Dual-Lat.mp4?rlkey=x0lxkv10p64rcit1fvtlts0sn&st=",
    poster: "https://image.tmdb.org/t/p/w780/ijoyefFMGHsN82yOM19NS6VRKmh.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un koala llamado Buster recluta a su mejor amigo para que lo ayude a conseguir clientes para su teatro organizando un concurso de canto.",
    anio: "2016",
    duracion: "1h 47min",
    calificacion: "85%",
    genero: "Animación • Musical • Comedia • Familiar",
    director: "Garth Jennings",
    reparto: "Matthew McConaughey, Reese Witherspoon, Seth MacFarlane",
    estreno: "21/12/2016",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sing_cantar_2",
        titulo: "Sing 2: Cantar",
        imagen: "https://image.tmdb.org/t/p/w300/aWeKITRFbbwY8txG5uCj4rMCfSP.jpg"
      },
      {
        id: "steven_universe_la_pelicula",
        titulo: "Steven Universe: La película",
        imagen: "https://image.tmdb.org/t/p/w300/bewhxwbmWTMe16dEQa8ICGe9Y1Y.jpg"
      },
      {
        id: "super_mario_bros",
        titulo: "Super Mario Bros: La película",
        imagen: "https://image.tmdb.org/t/p/w300/7k4fOuxA4vhblSSa5cTDRLlR7jU.jpg"
      },
      {
        id: "frozen",
        titulo: "Frozen",
        imagen: "https://image.tmdb.org/t/p/w300/hAKhrHvzQDUHQP5zd5HFeqF2BCN.jpg"
      },
      {
        id: "como_entrenar_a_tu_dragon_3",
        titulo: "Cómo entrenar a tu dragón 3",
        imagen: "https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg"
      },
      {
        id: "cars_3",
        titulo: "Cars 3",
        imagen: "https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg"
      }
    ]
  },


  sing_cantar_2: {
    id: "sing_cantar_2",
    titulo: "Sing 2: Cantar",
    video: "https://dl.dropbox.com/scl/fi/775fx6x5isbhxos7irvfb/Sing.2.2021.1080P-Dual-Lat.mp4?rlkey=56z9mws1nf3jfz1vd7x8qkafc&st=",
    poster: "https://image.tmdb.org/t/p/w780/ztiFxuG0gC6wQ8y7JZFYbCQyN4Y.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/aWeKITRFbbwY8txG5uCj4rMCfSP.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Buster y su nuevo elenco ahora tienen la mira puesta en estrenar un nuevo espectáculo en el Teatro Crystal Tower de la glamurosa Ciudad Redshore. Pero sin contactos, él y sus cantantes deben colarse en las oficinas de Crystal Entertainment, dirigidas por el despiadado magnate de los lobos Jimmy Crystal, donde el grupo presenta la ridícula idea de contratar a la leyenda del rock, Clay Calloway, para su espectáculo.",
    anio: "2021",
    duracion: "1h 49min",
    calificacion: "82%",
    genero: "Animación • Musical • Comedia • Familiar",
    director: "Garth Jennings",
    reparto: "Matthew McConaughey, Reese Witherspoon, Scarlett Johansson",
    estreno: "22/12/2021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sing_cantar",
        titulo: "Sing: Cantar",
        imagen: "https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg"
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  steven_universe_la_pelicula: {
    id: "steven_universe_la_pelicula",
    titulo: "Steven Universe: La pelicula",
    video: "https://dl.dropbox.com/scl/fi/6o1xd70czrwpeve54uunm/Steven.Universe.La.Pel-cula.2019.720p-Dual-Lat.mkv?rlkey=i4rro7sprq9j5fs7zevxuty28&st=",
    poster: "https://image.tmdb.org/t/p/w780/dymE9LUx7aQNTw2lwcKwBscKr6U.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bewhxwbmWTMe16dEQa8ICGe9Y1Y.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Ambientada dos años después de los acontecimientos acaecidos en la quinta temporada, un Steven de 16 años se enfrenta a un mundo repleto de novedades. Aunque cree que su papel como guardián de la Tierra ya ha terminado, una nueva amenaza se cierne sobre Beach City, por lo que el joven héroe tendrá que enfrentarse a su mayor desafío hasta la fecha.",
    anio: "2019",
    duracion: "1h 22min",
    calificacion: "85%",
    genero: "Animacion • Aventura • Fantasia • Musical • Ciencia ficción",
    director: "Rebecca Sugar",
    reparto: "Zach Callison, Deedee Magno Hall, Michaela Dietz",
    estreno: "26/08/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "trollhunters_el_despertar_de_los_titanes",
        titulo: "Trollhunters: El despertar de los titanes",
        imagen: "https://image.tmdb.org/t/p/w300/fhhjAX2iDmnZksQWsJ8DdAcDBc5.jpg"
      },
      {
        id: "ninja_turtles_caos_mutante",
        titulo: "Ninja Turtles: Caos mutante",
        imagen: "https://image.tmdb.org/t/p/w300/mgBXgA8jHext4KRWg84Cux5Y94L.jpg"
      },
      {
        id: "baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma vs Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/sXybjRq6BsCkWcDBfNphSH9biqn.jpg"
      },
      {
        id: "black_clover_la_espada_del_rey_mago",
        titulo: "Black Clover: La espada del rey mago",
        imagen: "https://image.tmdb.org/t/p/w300/jm2BckEhy1upr4iPpOZ6WTx1tWw.jpg"
      },
      {
        id: "los_siete_pecados_capitales_la_maldición_de_la_luz",
        titulo: "Los siete pecados capitales: La maldición de la luz",
        imagen: "https://image.tmdb.org/t/p/w300/w6U2pGQokqWh2wJLRaXi0bVd3zF.jpg"
      },
      {
        id: "dragon_ball_super_super_hero",
        titulo: "Dragon Ball Super: Super Hero",
        imagen: "https://image.tmdb.org/t/p/w300/o3a2yc2zkmdsq9wZ6Hnyu3jfLcC.jpg"
      }
    ]
  },

  sidelined_2: {
    id: "sidelined_2",
    titulo: "Sidelined 2: Interceptado",
    video: "https://dl.dropbox.com/scl/fi/pa1r76owiauts5eo61pvv/sidelined-2026.mp4?rlkey=ncyb63d49eve1v5zz3mt6bx06&st=",
    poster: "https://image.tmdb.org/t/p/w780/iMxWTPRqWJJKVW7KYBlmirSeazw.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/sEIP1pTVXa8BJaYSuVeVG3wFN10.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El mariscal de campo estrella de primer año, Drayton, lidia con el peso de las expectativas tras un revés devastador, mientras que la bailarina Dallas comienza a cuestionar el futuro que creía desear. A medida que su relación en la preparatoria se desmorona bajo la presión de la distancia y el autodescubrimiento, aprenderán que las versiones más valientes de sí mismos emergen cuando se dejan llevar por la vida.",
    anio: "2026",
    duracion: "1h 38min",
    calificacion: "00%",
    genero: "Comedia • Romance",
    director: "Justin Wu",
    reparto: "Noé Beck, Siena Agudong, Charlie Gillespie",
    estreno: "27/11/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "after_2",
        titulo: "After 2: En mil pedazos",
        imagen: "https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg"
      },
      {
        id: "dias_365",
        titulo: "365 Días",
        imagen: "https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"
      },
      {
        id: "anora",
        titulo: "Anora",
        imagen: "https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      },
      {
        id: "babygirl",
        titulo: "Babygirl: Deseos prohibido",
        imagen: "https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg"
      },
      {
        id: "millers_girl",
        titulo: "Miller's Girl",
        imagen: "https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"
      }
    ]
  },

  /*O*/

  ojala_estuvieras_aqui: {
    id: "ojala_estuvieras_aqui",
    titulo: "Ojala estuvieras aqui",
    video: "https://dl.dropbox.com/scl/fi/8b4pl9czbbzihp0oh7j81/Ojala-estuvieras-aqui-2025.mp4?rlkey=offj5mjoi1mrmd9si7sz4qkjr&st=",
    poster: "https://image.tmdb.org/t/p/w780/wWXW3LBV2leDfRNlsYdQ4mvuF1Q.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/zVRDebamaWViYk9P7q8FgJ8CJO8.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando la noche perfecta con un perfecto desconocido termina repentinamente a la mañana siguiente, Charlotte busca respuestas y sentido a su decepcionante vida. Hasta que descubre un secreto que lo cambia todo.",
    anio: "2025",
    duracion: "1h 39min",
    calificacion: "75%",
    genero: "Romance • Drama",
    director: "Julia Stiles",
    reparto: "Isabelle Fuhrman, Mena Massoud, Gabby Kono",
    estreno: "17/01/2025",
    idioma: "España 🇪🇸",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "corazon_delator",
        titulo: "Corazón delator",
        imagen: "https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg"
      },
      {
        id: "mala_influencia",
        titulo: "Mala influencia",
        imagen: "https://image.tmdb.org/t/p/w300/oogmlZekRCHP0JDhHKDZIyDIfpP.jpg"
      },
      {
        id: "tierra_baja",
        titulo: "Tierra Baja",
        imagen: "https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"
      },
      {
        id: "almas_marcadas",
        titulo: "Almas marcadas: Rule + Shaw",
        imagen: "https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg"
      },
      {
        id: "culpa_mia_2",
        titulo: "Culpa Mia: Londres",
        imagen: "https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg"
      },
      {
        id: "desafiante_rivales",
        titulo: "Desafiante Rivales",
        imagen: "https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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

  /*T*/

  terrifier: {
    id: "terrifier",
    titulo: "Terrifier: El Inicio",
    video: "https://dl.dropbox.com/scl/fi/sbg79efy195znmdty1fjm/Terrifier-2013.mp4?rlkey=3yjgs2dtlickyz2od8uekowx6&st=",
    poster: "https://image.tmdb.org/t/p/w780/tXOOveb17bv0Jhp37XJPrPeA2Jz.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/nfRlQCl590F30L37aihuqBGBvaO.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En la noche más oscura de Halloween, una cinta maldita revive a un payaso demoníaco sediento de sangre. La niñera y los niños deberán enfrentar el terror absoluto, donde cada historia se convierte en una amenaza real.",
    anio: "2018",
    duracion: "1h 24min",
    calificacion: "64%",
    genero: "Terror",
    director: "Damián Leone",
    reparto: "David Howard Thornton, Jenna Kanell, Jenna Kanell",
    estreno: "25/01/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "terrifier_2",
        titulo: "Terrifier 2",
        imagen: "https://image.tmdb.org/t/p/w300/qEAlwXbYk6IHA4ztoS2XFFaa7Xo.jpg"
      },
      {
        id: "terrifier_3",
        titulo: "Terrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      },
      {
        id: "un_lugar_en_silencio_3",
        titulo: "Un lugar en silencio 3: Día uno",
        imagen: "https://image.tmdb.org/t/p/w300/mB9GP9Wd7RduYpCSiqurZSnarl6.jpg"
      },
      {
        id: "annabelle_2",
        titulo: "Annabelle 2: La creación",
        imagen: "https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg"
      },
      {
        id: "atrapados_en_lo_profundo",
        titulo: "Atrapados en lo Profundo",
        imagen: "https://image.tmdb.org/t/p/w300/fSY6BYUZMObTIzPfRBlhuAb5lsd.jpg"
      },
      {
        id: "eliminar_amigo",
        titulo: "Eliminar amigo",
        imagen: "https://image.tmdb.org/t/p/w300/pzxHNiKjHL8Sz7DZ7POXXqohxet.jpg"
      }
    ]
  },

  terrifier_2: {
    id: "terrifier_2",
    titulo: "Terrifier 2",
    video: "https://dl.dropbox.com/scl/fi/xln7vq6jyxqlaavwyo42u/Terrifier-2-2022.mp4?rlkey=uwirc5qpspzeo1bysyyxd6nye&st=",
    poster: "https://image.tmdb.org/t/p/w780/y5Z0WesTjvn59jP6yo459eUsbli.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qEAlwXbYk6IHA4ztoS2XFFaa7Xo.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Después de ser resucitado por una entidad siniestra, Art the Clown regresa al condado de Miles, donde debe cazar y destruir a una adolescente y a su hermano menor en la noche de Halloween.",
    anio: "2022",
    duracion: "2h 18min",
    calificacion: "67%",
    genero: "Terror",
    director: "Damián Leone",
    reparto: "David Howard Thornton, Lauren LaVera, Elliott Fullam",
    estreno: "12/01/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "terrifier",
        titulo: "Terrifier",
        imagen: "https://image.tmdb.org/t/p/w300/rQR4NQwV9ixWoOHHTJd2Dt0chGc.jpg"
      },
      {
        id: "terrifier_3",
        titulo: "Terrifier 3",
        imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"
      },
      {
        id: "sonrie_2",
        titulo: "Sonríe 2",
        imagen: "https://image.tmdb.org/t/p/w300/aQtWauWpy5KQEHsBURDnoTD6svd.jpg"
      },
      {
        id: "poseida",
        titulo: "Poseída",
        imagen: "https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg"
      },
      {
        id: "mara",
        titulo: "Mara",
        imagen: "https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg"
      },
      {
        id: "martyrs",
        titulo: "Martyrs",
        imagen: "https://image.tmdb.org/t/p/w300/5kymocKK0SfyEEV0ohNEBz1lxNx.jpg"
      }
    ]
  },

  terrifier_3: {
    id: "terrifier_3",
    titulo: "Terrifier 3",
    video: "https://dl.dropbox.com/scl/fi/9ep2vlwxm0he5r7sdto2q/Terrifier.3.Payaso.siniestro.2024.720p-Dual-Lat.mkv?rlkey=i5am7375srqch10fjyc30u286&st=",
    poster: "https://image.tmdb.org/t/p/w780/bHfGHipZ32Oec94FDJO4mWs3aZ5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cinco años después de sobrevivir a la masacre de Halloween de Art el Payaso, Sienna y Jonathan siguen luchando por reconstruir sus vidas destrozadas. Con la llegada de las fiestas, intentan abrazar el espíritu navideño y dejar atrás los horrores del pasado. Pero justo cuando creen estar a salvo, Art regresa, decidido a convertir su alegría navideña en una nueva pesadilla.",
    anio: "2024",
    duracion: "2h 05min",
    calificacion: "80%",
    genero: "Terror",
    director: "Damián Leone",
    reparto: "Lauren LaVera, David Howard Thornton, Samantha Scaffidi",
    estreno: "31/10/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "terrifier",
        titulo: "Terrifier",
        imagen: "https://image.tmdb.org/t/p/w300/rQR4NQwV9ixWoOHHTJd2Dt0chGc.jpg"
      },
      {
        id: "terrifier_2",
        titulo: "Terrifier 2",
        imagen: "https://image.tmdb.org/t/p/w300/qEAlwXbYk6IHA4ztoS2XFFaa7Xo.jpg"
      },
      {
        id: "turno_nocturno",
        titulo: "Turno nocturno",
        imagen: "https://image.tmdb.org/t/p/w300/6f7EZ60KwDfjCv0KGKPIQk5QdJ5.jpg"
      },
      {
        id: "tarot_de_la_muerte",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/r8kgyBIT5umT330gISJH5hqRhhy.jpg"
      },
      {
        id: "un_lugar_en_silencio",
        titulo: "Un lugar en silencio",
        imagen: "https://image.tmdb.org/t/p/w300/hE51vC3iZJCqFecLzIO1Q4eYXqK.jpg"
      },
      {
        id: "juicio_al_diablo",
        titulo: "Juicio al diablo",
        imagen: "https://image.tmdb.org/t/p/w300/7C1T0aFplHKaYacCqRdeGYLTKCW.jpg"
      }
    ]
  },

  tarot: {
    id: "tarot",
    titulo: "Tarot de la muerte",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/otfoeC96neoOdA4HqsX06OWuzE9.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/Adh7xmtgSIUGZBaMj9VLTmq2G8z.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando un grupo de amigos viola imprudentemente la regla sagrada de las lecturas de Tarot, desatan sin saberlo un mal indescriptible atrapado en las cartas malditas. Uno a uno, se enfrentan al destino y terminan en una carrera contra la muerte.",
    anio: "2024",
    duracion: "0h 008min",
    calificacion: "00%",
    genero: "Terror",
    director: "Anna Halberg y Spenser Cohen",
    reparto: "Harriet Slater, Wolfgang Novogratz, Adain Bradley",
    estreno: "16/05/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "",
        titulo: "Imaginario: Juguete diabolico",
        imagen: "https://image.tmdb.org/t/p/w300/s3SNMGwZ7TiiZJDQEOJ1Z4e5WeX.jpg"
      },
      {
        id: "it_eso",
        titulo: "It (Eso)",
        imagen: "https://image.tmdb.org/t/p/w300/ha6UC0JVrVuu4KDZobgpedPyxkL.jpg"
      },
      {
        id: "evil_dead_el_despertar",
        titulo: "Evil dead:El desperta",
        imagen: "https://image.tmdb.org/t/p/w300/uwF8bBauJob5TISQ1cMHoVgIdWD.jpg"
      },
      {
        id: "el_exorcismo_de_georgetown",
        titulo: "El exorcista de georgetown",
        imagen: "https://image.tmdb.org/t/p/w300/ioQCdjn2YPfAJMfJlgzNdXgYZrr.jpg"
      },
      {
        id: "el_exorcista_creyente",
        titulo: "El exorcista creyentes",
        imagen: "https://image.tmdb.org/t/p/w300/aNoNB5jWIzqcBqHEYzW232B2ktx.jpg"
      },
      {
        id: "cementerio_de_animales_2",
        titulo: "Cementerio de animales 2: Los orígenes",
        imagen: "https://image.tmdb.org/t/p/w300/sbzfFLgExjl7ekLeNFEZ9EwOA9V.jpg"
      }
    ]
  },

  tarzan: {
    id: "tarzan",
    titulo: "Tarzán",
    video: "https://dl.dropbox.com/scl/fi/kd1vm3xvgoaeadfox3jou/Tarzan-1999.mp4?rlkey=wgt3fb4ld462a3ajec54q2h2m&st=",
    poster: "https://image.tmdb.org/t/p/w780/gAuUsA4orQIifYEHTueAjbOaKug.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/u9WgwjFpBWc3eQxddUFSicH2K6p.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Kala, una gorila hembra, encuentra un niño huérfano en la jungla, decide adoptarlo como su propio hijo a pesar de la oposición de Kerchak, el jefe de la manada. Junto a Terk, un gracioso mono, y Tantor, un elefante algo neurótico, Tazán crecerá en la jungla desarrollando los instintos de los animales y aprendiendo a deslizarse entre los árboles a velocidad de vértigo. Pero cuando una expedición se adentra en la jungla y Tarzán conoce a Jane, descubrirá quién es realmente y cuál es el mundo al que pertenece...",
    anio: "1999",
    duracion: "1h 28min",
    calificacion: "80%",
    genero: "Animacion • Drama • Aventura • Familia • Disney",
    director: "Kevin Lima y Chris Buck",
    reparto: "Tony Goldwyn, Minnie Driver, Glenn Close",
    estreno: "18/06/1999",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tarzan_2",
        titulo: "Tarzán 2",
        imagen: "https://image.tmdb.org/t/p/w300/5KRnGepv2b1daJ2WM8ZGnPS64nl.jpg"
      },
      {
        id: "mufasa_el_rey_leon",
        titulo: "Mufasa: El rey león",
        imagen: "https://image.tmdb.org/t/p/w300/lk4NNdeQrb6zbRSogDSdE6qmjk8.jpg"
      },
      {
        id: "trolls_3",
        titulo: "Trolls 3: Todos juntos",
        imagen: "https://image.tmdb.org/t/p/w300/saGpA6u71TPA8DyTYoAqSBGVZW.jpg"
      },
      {
        id: "la_sirenita",
        titulo: "La sirenita",
        imagen: "https://image.tmdb.org/t/p/w300/2w7EVsWEWfk45OZBxRTVxlyp00.jpg"
      },
      {
        id: "intensamente_2",
        titulo: "Intensamente 2",
        imagen: "https://image.tmdb.org/t/p/w300/hbNrgcQjLkPcE56MLGUWSD5SO6V.jpg"
      },
      {
        id: "zootopia_2",
        titulo: "Zootopia 2",
        imagen: "https://image.tmdb.org/t/p/w300/3Wg1LBCiTEXTxRrkNKOqJyyIFyF.jpg"
      }
    ]
  },

  tarzan_2: {
    id: "tarzan_2",
    titulo: "Tarzán 2",
    video: "https://dl.dropbox.com/scl/fi/nnz8cpr8w0oqxo1flslka/Tarz-n-2.mp4?rlkey=llywabb8eifa0gt8snkqe3fkb&st=",
    poster: "https://image.tmdb.org/t/p/w780/lBmemfERELoZDxyLOCoumfkHAEO.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5KRnGepv2b1daJ2WM8ZGnPS64nl.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Antes de ser el Rey de la Selva, Tarzán era un niño inquieto que intentaba encontrar su papel en el mundo. Cuando una de sus travesuras pone a su familia en peligro, Tarzán decide que ellos estarán mejor sin él. Así se embarca en un emocionante viaje que le llevará a encontrarse cara a cara con el misterioso Zugor, la fuerza más poderosa del planeta. Juntos, Tarzán y Zugor descubren que ser diferentes no es una debilidad y que los amigos y la familia son el mayor apoyo de todos.",
    anio: "2005",
    duracion: "1h 11min",
    calificacion: "65%",
    genero: "Animacion • Drama • Aventura • Familia • Disney",
    director: "Brian Smith",
    reparto: "Harrison Chad, George Carlin, Brad Garrett",
    estreno: "13/06/2005",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tarzan",
        titulo: "Tarzán",
        imagen: "https://image.tmdb.org/t/p/w300/u9WgwjFpBWc3eQxddUFSicH2K6p.jpg"
      },
      {
        id: "pocahontas",
        titulo: "Pocahontas",
        imagen: "https://image.tmdb.org/t/p/w300/iKZioEcxgDGsJkRkd9n2R5q2ctx.jpg"
      },
      {
        id: "peter_pan_y_wendy",
        titulo: "Peter Pan y Wendy",
        imagen: "https://image.tmdb.org/t/p/w300/9NXAlFEE7WDssbXSMgdacsUD58Y.jpg"
      },
      {
        id: "lilo_y_stitch_1",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/9jrmKyhNGam2pj89bcxmhQzXCNo.jpg"
      },
      {
        id: "mi_villano__favorito_4",
        titulo: "Mi villano favorito 4",
        imagen: "https://image.tmdb.org/t/p/w300/b6JX0fBne5yPFNBtdp4Imi3CpiE.jpg"
      },
      {
        id: "super_mario_bros",
        titulo: "Super Mario Bros: La película",
        imagen: "https://image.tmdb.org/t/p/w300/qNBAXBIQlnOThrVvA6mA2B5ggV6.jpg"
      }
    ]
  },

  thor_2011: {
    id: "thor_2011",
    titulo: "Thor",
    video: "https://dl.dropbox.com/scl/fi/efinzeuww5q4t0kxu10me/Thor.2011.1080P-Dual-Lat.mp4?rlkey=k0ifmv0jblh0bfii0xvb1ineo&st=",
    poster: "https://image.tmdb.org/t/p/w780/cDJ61O1STtbWNBwefuqVrRe3d7l.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Thor es un arrogante y codicioso guerrero cuya imprudencia desata una antigua guerra. Por ese motivo, su padre Odín lo castiga desterrándolo a la Tierra para que viva entre los hombres y descubra así el verdadero sentido de la humildad. Cuando el villano más peligroso de su mundo envía a la Tierra a las fuerzas más oscuras de Asgard, Thor se dará cuenta de lo que realmente hace falta para ser un verdadero héroe.",
    anio: "2011",
    duracion: "1h 54min",
    calificacion: "80%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Kenneth Branagh",
    reparto: "Chris Hemsworth, Natalie Portman, Tom Hiddleston",
    estreno: "28/04/2011",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "thor_2",
        titulo: "Thor 2: El mundo oscuro",
        imagen: "https://image.tmdb.org/t/p/w300/iY2E6b5huleYrM0NYKrb7a7lSGZ.jpg"
      },
      {
        id: "thor_3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      },
      {
        id: "thor_4",
        titulo: "Thor 4: Amor y trueno",
        imagen: "https://image.tmdb.org/t/p/w300/qTdnMVkjoP3b1ocwYyW0qrsEabc.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-Man: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/3LSdA2l3EmI9duIJKzNElUPs0RK.jpg"
      },
      {
        id: "iron_man_3",
        titulo: "Iron Man 3",
        imagen: "https://image.tmdb.org/t/p/w300/qhPtAc1TKbMPqNvcdXSOn9Bn7hZ.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      }
    ]
  },

  thor_2: {
    id: "thor_2",
    titulo: "Thor 2: El mundo oscuro",
    video: "https://dl.dropbox.com/scl/fi/v2w7vfkmojd891j1rttow/Thor.the.dark.world.2013.1080P-Dual-Lat.mp4?rlkey=060xi8oagx2z8rl7107ugyf9w&st=",
    poster: "https://image.tmdb.org/t/p/w780/uhYoytlNaq46dG81wLmHqaSuzWu.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iY2E6b5huleYrM0NYKrb7a7lSGZ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Thor lucha por restablecer el orden en el cosmos, pero una antigua raza liderada por el vengativo Malekith regresa con el propósito de volver a sumir el universo en la oscuridad. Se trata de un villano con el que ni siquiera Odín y Asgard se atreven a enfrentarse; por esa razón, Thor tendrá que emprender un viaje muy peligroso, durante el cual se reunirá con Jane Foster y la obligará a sacrificarlo todo para salvar el mundo.",
    anio: "2013",
    duracion: "1h 52min",
    calificacion: "73%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Alan Taylor",
    reparto: "Chris Hemsworth, Natalie Portman, Tom Hiddleston",
    estreno: "21/11/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "thor_2011",
        titulo: "Thor",
        imagen: "https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg"
      },
      {
        id: "thor_3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      },
      {
        id: "thor_4",
        titulo: "Thor 4: Amor y trueno",
        imagen: "https://image.tmdb.org/t/p/w300/qTdnMVkjoP3b1ocwYyW0qrsEabc.jpg"
      },
      {
        id: "los_vengadores",
        titulo: "Los Vengadores",
        imagen: "https://image.tmdb.org/t/p/w300/ugX4WZJO3jEvTOerctAWJLinujo.jpg"
      },
      {
        id: "doctor_strange",
        titulo: "Doctor strange",
        imagen: "https://image.tmdb.org/t/p/w300/dAh03zjNzjhiQPrq4Dcr7qKDPlR.jpg"
      },
      {
        id: "viuda_negra",
        titulo: "Viuda negra",
        imagen: "https://image.tmdb.org/t/p/w300/tvl0OXmNQtLrPk7fJ8UHvLrD37R.jpg"
      }
    ]
  },

  thor_3: {
    id: "thor_3",
    titulo: "Thor 3: Ragnarok",
    video: "https://dl.dropbox.com/scl/fi/7l78trirdlhlek867qhbk/Thor.ragnarok.2017.1080P-Dual-Lat.mp4?rlkey=umab1me76zbm666ge0mvxr1iq&st=",
    poster: "https://image.tmdb.org/t/p/w780/AeH2ez3nOX2YEwkYJ79QbCXZsI7.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Thor está preso al otro lado del universo sin su poderoso martillo y se enfrenta a una carrera contra el tiempo. Su objetivo es volver a Asgard y parar el Ragnarok porque significaría la destrucción de su planeta natal y el fin de la civilización Asgardiana a manos de una todopoderosa y nueva amenaza, la implacable Hela.",
    anio: "2017",
    duracion: "2h 10min",
    calificacion: "83%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Taika Waititi",
    reparto: "Chris Hemsworth, Mark Ruffalo, Tom Hiddleston",
    estreno: "03/11/2017",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "los_vengadores_infinity_war",
        titulo: "Los vengadores: Infinity war",
        imagen: "https://image.tmdb.org/t/p/w300/q6Q81fP4qPvfQTH2Anlgy12jzO2.jpg"
      },
      {
        id: "thor_2011",
        titulo: "Thor",
        imagen: "https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg"
      },
      {
        id: "thor_2",
        titulo: "Thor 2: El mundo oscuro",
        imagen: "https://image.tmdb.org/t/p/w300/iY2E6b5huleYrM0NYKrb7a7lSGZ.jpg"
      },
      {
        id: "thor_4",
        titulo: "Thor 4: Amor y trueno",
        imagen: "https://image.tmdb.org/t/p/w300/qTdnMVkjoP3b1ocwYyW0qrsEabc.jpg"
      },
      {
        id: "los_vengadores_la_era_de_ultron",
        titulo: "Los vengadores 2: La era de ultron",
        imagen: "https://image.tmdb.org/t/p/w300/71wSx6MQm8EbMNDi3vVaD8nD9SL.jpg"
      },
      {
        id: "los_4_fantasticos_2025",
        titulo: "Los 4 Fantásticos: Primeros pasos",
        imagen: "https://image.tmdb.org/t/p/w300/u6iFFGcOXk4d6C5pZes1qRgU8Nt.jpg"
      }
    ]
  },

  thor_4: {
    id: "thor_4",
    titulo: "Thor 4: Amor y trueno",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/t3y3eZbz7Adwi43IumSGdDbc2P5.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/qTdnMVkjoP3b1ocwYyW0qrsEabc.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "El Dios del Trueno emprende un viaje que no se parece en nada a lo que se ha enfrentado hasta ahora: una búsqueda de la paz interior. Pero el retiro de Thor se ve interrumpido por un asesino galáctico conocido como Gorr el Carnicero de Dioses. Para hacer frente a la amenaza, Thor solicita la ayuda de Valkiria, de Korg y de su ex novia Jane Foster que, para sorpresa de Thor, empuña su martillo mágico, Mjolnir, como la Poderosa Thor",
    anio: "2022",
    duracion: "0h 008min",
    calificacion: "70%",
    genero: "Accion • Marvel • Comedia • Ciencia Ficcion",
    director: "Taika Waititi",
    reparto: "Chris Hemsworth, Natalie Portman, Christian Bale",
    estreno: "08/09/2022",
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

  tierra_de_osos: {
    id: "tierra_de_osos",
    titulo: "Tierra de osos",
    video: "https://dl.dropbox.com/scl/fi/mlhfuhcrykf9amu0smno5/Tierra.De.Osos.2003.1080P-Dual-Lat.mkv?rlkey=02mdac96vjma1uwjxd9p0nc6z&st=",
    poster: "https://image.tmdb.org/t/p/w780/gksSgap0EMOPUTWyQA1SmzLaS7N.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En los bosques del noroeste americano vive un niño indio llamado Kenai, cuya vida sufre un giro inesperado cuando los Grandes Espíritus lo transforman en un oso, el animal que más odia. Kenai se hace amigo de un osezno llamado Koda y se propone recuperar su forma humana. Mientras, su hermano (que no sabe que Kenai es ahora un oso) lo persigue para cumplir una misión de venganza en la que está en juego el honor familiar.",
    anio: "2003",
    duracion: "1h 25min",
    calificacion: "73%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "Aaron Blaise, Robert Walker",
    reparto: "Joaquin Phoenix, Jeremy Suarez, Jason Raize",
    estreno: "23/10/2003",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tierra_de_osos_2",
        titulo: "Tierra de osos 2",
        imagen: "https://image.tmdb.org/t/p/w300/iiRaRi7SFCawo6lieWi3Ntcy936.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      },
      {
        id: "pinocho",
        titulo: "Pinocho",
        imagen: "https://image.tmdb.org/t/p/w300/sAluF7lNc4Mv3qxx1mmOgsfbr0C.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      },
      {
        id: "lilo_y_stitch",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/9jrmKyhNGam2pj89bcxmhQzXCNo.jpg"
      },
      {
        id: "aladdin_2019",
        titulo: "Aladdin",
        imagen: "https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg"
      }
    ]
  },
  
  tierra_de_osos_2: {
    id: "tierra_de_osos_2",
    titulo: "Tierra de osos 2",
    video: "https://dl.dropbox.com/scl/fi/sg7nq5u8a8yscah24wr69/Tierra.De.Osos.2.2006.1080P-Dual-Lat.mkv?rlkey=thb88lnf4gx84lskunxyzuca1&st=",
    poster: "https://image.tmdb.org/t/p/w780/dMqbyB5GQvDujXiKdKPhWT0VTRw.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/iiRaRi7SFCawo6lieWi3Ntcy936.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La trama se centra de nuevo en Kenai (que había sido transformado en oso durante la primera parte) y sus aventuras en la pintoresca costa del Pacífico Norte al final de la Edad de Hielo. Kenai sueña repetidamente con su anterior vida como humano, cuando era niño y su mejor amiga era Nita, la hija del jefe de la tribu. Ahora ambos revivirán este pasado común cuando se ven obligados a embarcarse en una peligrosa aventura.",
    anio: "2006",
    duracion: "1h 13min",
    calificacion: "70%",
    genero: "Animación • Disney • Aventura • Familia",
    director: "Ben Gluck",
    reparto: "Patrick Dempsey, Mandy Moore,Jeremy Suarez",
    estreno: "17/08/2006",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tierra_de_osos",
        titulo: "Tierra de osos",
        imagen: "https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg"
      },
      {
        id: "los_pinguinos_de_madagascar",
        titulo: "Los Pinguinos de madagascar",
        imagen: "https://image.tmdb.org/t/p/w300/dXbpNrPDZDMEbujFoOxmMNQVMHa.jpg"
      },
      {
        id: "peabody_y_sherman",
        titulo: "Mr Peabody y Sherman",
        imagen: "https://image.tmdb.org/t/p/w300/c6kZC5pvwNIRSxiLL2JFGGc46He.jpg"
      },
      {
        id: "luck_suerte",
        titulo: "Luck: Suerte",
        imagen: "https://image.tmdb.org/t/p/w300/cQDqNCtq7j5xaCXGeLsLZK90RuR.jpg"
      },
      {
        id: "los_croods",
        titulo: "Los Croods",
        imagen: "https://image.tmdb.org/t/p/w300/p7lJkqHlK01nr0zNacunUFI5Qxy.jpg"
      },
      {
        id: "madagascar",
        titulo: "Madagascar",
        imagen: "https://image.tmdb.org/t/p/w300/zrV5GnfCcLWzyjrFgYTpjQPRMfl.jpg"
      }
    ]
  },

  toy_story: {
    id: "toy_story",
    titulo: "Toy story",
    video: "https://dl.dropbox.com/scl/fi/2y9sffhp4sr1tewmhkmc5/Toy.story.1.1995.1080P-Dual-Lat.mp4?rlkey=s600jmi8s02b890riguaupv5w&st=",
    poster: "https://image.tmdb.org/t/p/w780/xEIGPk5QTxD9E5knNFSXggNxEAP.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Los juguetes de Andy, un niño de seis años, temen que haya llegado su hora y que un nuevo regalo de cumpleaños les sustituya en el corazón de su dueño. Woody, un vaquero que ha sido hasta ahora el juguete favorito de Andy, trata de tranquilizarlos hasta que aparece Buzz Lightyear, un héroe espacial dotado de todo tipo de avances tecnológicos. Woody es relegado a un segundo plano, pero su constante rivalidad se transformará en una gran amistad cuando ambos se pierden en la ciudad sin saber cómo volver a casa.",
    anio: "1995",
    duracion: "1h 21min",
    calificacion: "80%",
    genero: "Animacion • Disney • Aventura • Familia • Comedia",
    director: "Juan Lasseter",
    reparto: "Tom Hanks, Tim Allen, Don Rickles",
    estreno: "14/03/1995",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "toy_story_2",
        titulo: "Toy story 2",
        imagen: "https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg"
      },
      {
        id: "toy_story_3",
        titulo: "Toy story 3",
        imagen: "https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg"
      },
      {
        id: "toy_story_4",
        titulo: "Toy story 4",
        imagen: "https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "intensamente",
        titulo: "Intensamente",
        imagen: "https://image.tmdb.org/t/p/w300/cTXHRoiKnuNdLRy4qn7JhQXHZO0.jpg"
      },
      {
        id: "lilo_y_stitch_1",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/9jrmKyhNGam2pj89bcxmhQzXCNo.jpg"
      }
    ]
  },

  toy_story_2: {
    id: "toy_story_2",
    titulo: "Toy story 2",
    video: "https://dl.dropbox.com/scl/fi/5m200rgcrb5e600nr8rt1/Toy.story.2.1999.1080P-Dual-Lat.mp4?rlkey=9sqjh8i1rjxvu93kx724wo1rp&st=",
    poster: "https://image.tmdb.org/t/p/w780/q4CId9Q7b8jwX2obTHbMYXUmfRi.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Cuando Andy se va de campamento dejando solos a los juguetes, Al McWhiggin, un compulsivo coleccionista de juguetes valiosos, secuestra a Woody. Buzz Lightyear y el resto de los juguetes de Andy deberán actuar con rapidez para rescatarlo, poniéndose al frente de una operación de rescate durante la cual se enfrentarán a múltiples peligros y divertidas situaciones.",
    anio: "1999",
    duracion: "1h 32min",
    calificacion: "83%",
    genero: "Animacion • Disney • Aventura • Familia • Comedia",
    director: "Juan Lasseter",
    reparto: "Tom Hanks, Tim Allen, Joan Cusack",
    estreno: "24/11/1999",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "toy_story",
        titulo: "Toy story",
        imagen: "https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg"
      },
      {
        id: "toy_story_3",
        titulo: "Toy story 3",
        imagen: "https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg"
      },
      {
        id: "toy_story_4",
        titulo: "Toy story 4",
        imagen: "https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg"
      },
      {
        id: "wish_el_poder_de_los_deseos",
        titulo: "Wish: El poder de los deseos",
        imagen: "https://image.tmdb.org/t/p/w300/rCCrG4swkxgFZflup56sx6ymk5i.jpg"
      },
      {
        id: "un_gran_dinosaurio",
        titulo: "Un gran dinosaurio",
        imagen: "https://image.tmdb.org/t/p/w300/AdBRNzPeeQHujTug9y4vSbibWF8.jpg"
      },
      {
        id: "la_familia_del_futuro",
        titulo: "La familia del futuro",
        imagen: "https://image.tmdb.org/t/p/w300/1V34tiUPo3memMuCFlGhpA7ODbj.jpg"
      }
    ]
  },

  toy_story_3: {
    id: "toy_story_3",
    titulo: "Toy story 3",
    video: "https://dl.dropbox.com/scl/fi/yw9aayb417d4r3ypg2vme/Toy.story.3.2010.1080P-Dual-Lat.mp4?rlkey=fhunm53ch46c1n3oolnb3jo9p&st=",
    poster: "https://image.tmdb.org/t/p/w780/egybPos4AIOpC3o1WlTdRPE0Y02.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Woody, Buzz y el resto de los juguetes de Andy no han sido utilizados en años. Con Andy a punto de ir a la universidad, la pandilla se encuentra abandonada accidentalmente en una guardería siniestra. Los juguetes deben unirse para escapar y regresar a casa con Andy.",
    anio: "2010",
    duracion: "1h 43min",
    calificacion: "78%",
    genero: "Animacion • Disney • Aventura • Familia • Comedia",
    director: "Lee Unkrich",
    reparto: "Tom Hanks, Joan Cusack, Don Rickles",
    estreno: "17/06/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "toy_story",
        titulo: "Toy story",
        imagen: "https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg"
      },
      {
        id: "toy_story_2",
        titulo: "Toy story 2",
        imagen: "https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg"
      },
      {
        id: "toy_story_4",
        titulo: "Toy story 4",
        imagen: "https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      },
      {
        id: "coco",
        titulo: "Coco",
        imagen: "https://image.tmdb.org/t/p/w300/gGEsBPAijhVUFoiNpgZXqRVWJt2.jpg"
      }
    ]
  },

  toy_story_4: {
    id: "toy_story_4",
    titulo: "Toy story 4",
    video: "https://dl.dropbox.com/scl/fi/dsymsmj1uaxh4cn28lnsk/Toystory4.2019.1080P-Dual-Lat.mp4?rlkey=8xjorrdpn0rkvgttajy0rejef&st=",
    poster: "https://image.tmdb.org/t/p/w780/eHz61dRrYZB16glXDttV0CnJf6j.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Woody siempre ha tenido claro cuál es su labor en el mundo y cuál es su prioridad: cuidar a su dueño, ya sea Andy o Bonnie. Sin embargo, Woody descubrirá lo grande que puede ser el mundo para un juguete cuando Forky se convierta en su nuevo compañero de habitación. Los juguetes se embarcarán en una aventura de la que no se olvidarán jamás.",
    anio: "2019",
    duracion: "1h 40min",
    calificacion: "82%",
    genero: "Animacion • Disney • Aventura • Familia • Comedia",
    director: "Josh Cooley",
    reparto: "Tom Hanks, Tim Allen, Annie Potts",
    estreno: "20/06/2019",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "toy_story",
        titulo: "Toy story",
        imagen: "https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg"
      },
      {
        id: "toy_story_2",
        titulo: "Toy story 2",
        imagen: "https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg"
      },
      {
        id: "toy_story_3",
        titulo: "Toy story 3",
        imagen: "https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg"
      },
      {
        id: "lightyear",
        titulo: "Lightyear",
        imagen: "https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg"
      },
      {
        id: "hercules",
        titulo: "Hércules",
        imagen: "https://image.tmdb.org/t/p/w300/dK9rNoC97tgX3xXg5zdxFisdfcp.jpg"
      },
      {
        id: "elemental",
        titulo: "Elemental",
        imagen: "https://image.tmdb.org/t/p/w300/8riWcADI1ekEiBguVB9vkilhiQm.jpg"
      }
    ]
  },

  turbo: {
    id: "turbo",
    titulo: "Turbo",
    video: "https://dl.dropbox.com/scl/fi/9ja5hvm1exw6nszniuc0p/Turbo.2013.1080p-dual-lat.mp4?rlkey=h219hf7hzn2e2acy8qshde87k&st=",
    poster: "https://image.tmdb.org/t/p/w780/3qfiZd8ETMk9MQsmhbOB5QAAx0l.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/ysNUm2zWPkJQKa3Op0N4EmqrZ0h.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "La historia de un caracol de jardín común y corriente que sueña con ganar la Indy 500.",
    anio: "2013",
    duracion: "1h 36min",
    calificacion: "63%",
    genero: "Animación • Aventura • Disney",
    director: "David Soren",
    reparto: "Ryan Reynolds, Paul Giamatti, Michael Peña",
    estreno: "18/07/2013",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "sonic_3",
        titulo: "Sonic 3: La Pelicula",
        imagen: "https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"
      },
      {
        id: "scoovy",
        titulo: "¡Scooby!",
        imagen: "https://image.tmdb.org/t/p/w300/tOhuq4RYr2Rt9TM7X4dkr7A9HSd.jpg"
      },
      {
        id: "spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg"
      },
      {
        id: "robot salvaje",
        titulo: "Robot salvaje",
        imagen: "https://image.tmdb.org/t/p/w300/dE8Cwtnb31637ygPHTVDxFkg8K4.jpg"
      },
      {
        id: "gato_con_botas",
        titulo: "El gato con botas",
        imagen: "https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  /*P*/

  pinocho: {
    id: "pinocho",
    titulo: "Pinocho",
    video: "https://dl.dropbox.com/scl/fi/e9gca8rftc03rahs51mlp/Pinocho.19940.1080P-Dual-Lat.mkv?rlkey=w9gea9hvjys4ga94m11sgcsvd&st=",
    poster: "https://image.tmdb.org/t/p/w780/gJVzFQd3IQdFbfLtot2cjtYfIgb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/nsnyd6MFznuFSaHk1iveAdWc3nI.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un anciano llamado Geppetto fabrica una marioneta de madera a la que llama Pinocho, con la esperanza de que se convierta en un niño de verdad. El Hada Azul hace realidad su deseo y da vida a Pinocho, pero conserva su cuerpo de madera. Pepito Grillo, la conciencia de Pinocho, tendrá que aconsejarlo para que se aleje de las situaciones difíciles o peligrosas hasta conseguir que el muñeco se convierta en un niño de carne y hueso.",
    anio: "1940",
    duracion: "1h 27min",
    calificacion: "82%",
    genero: "Animacion • Aventura • Disney • Familia",
    director: "Hamilton Luske",
    reparto: "Dickie Jones, Cliff Edwards, Christian Rub",
    estreno: "23/02/1940",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "pinocho_2022",
        titulo: "Pinocho",
        imagen: "https://image.tmdb.org/t/p/w300/h32gl4a3QxQWNiNaR4Fc1uvLBkV.jpg"
      },
      {
        id: "pinocho_de_guillermo_del_toro",
        titulo: "Pinocho de Guillermo del Toro",
        imagen: "https://image.tmdb.org/t/p/w300/mJLFkiATSjU9sbtblR1yFWhHs4h.jpg"
      },
      {
        id: "peter_pan",
        titulo: "Peter Pan",
        imagen: "https://image.tmdb.org/t/p/w300/tDvGRWSdqT31ADijJf9OhbTbQ77.jpg"
      },
      {
        id: "tierra_de_osos",
        titulo: "Tierra de osos",
        imagen: "https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg"
      },
      {
        id: "bambi",
        titulo: "Bambi",
        imagen: "https://image.tmdb.org/t/p/w300/q9LI5Uloz1WRqaJjr8Tq2aOeSeH.jpg"
      },
      {
        id: "blancanieves",
        titulo: "Blancanieves",
        imagen: "https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"
      }
    ]
  },

  pinocho_2022: {
    id: "pinocho_2022",
    titulo: "Pinocho",
    video: "https://dl.dropbox.com/scl/fi/wvqv31p9qpqexxocwv8wc/Pinocho.2022.720p-Dual-Lat.mkv?rlkey=60upi6z6nldei50cym6o73cjd&st=",
    poster: "https://image.tmdb.org/t/p/w780/mRMZySTB40NDpI9suxQJhjxYd9n.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/h32gl4a3QxQWNiNaR4Fc1uvLBkV.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Versión en acción real del aclamado cuento sobre una marioneta que se embarca en una trepidante aventura para convertirse en un niño de verdad. La historia también presenta a otros personajes, como Gepetto, el carpintero que fabrica a Pinocho y lo trata como a su propio hijo; Pepito Grillo, que hace las veces de guía y “conciencia” de Pinocho; el Hada Azul; el Honrado Juan; la gaviota Sofía, y el cochero.",
    anio: "2022",
    duracion: "1h 45min",
    calificacion: "86%",
    genero: "Aventura • Disney • Familia",
    director: "Roberto Zemeckis",
    reparto: "José Gordon-Levitt, Tom Hanks, Benjamín Evan Ainsworth",
    estreno: "08/09/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "pinocho",
        titulo: "Pinocho",
        imagen: "https://image.tmdb.org/t/p/w300/sAluF7lNc4Mv3qxx1mmOgsfbr0C.jpg"
      },
      {
        id: "pinocho_de_guillermo_del_toro",
        titulo: "Pinocho de Guillermo del Toro",
        imagen: "https://image.tmdb.org/t/p/w300/mJLFkiATSjU9sbtblR1yFWhHs4h.jpg"
      },
      {
        id: "moana_2",
        titulo: "Moana 2",
        imagen: "https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"
      },
      {
        id: "la_sirenita_2",
        titulo: "La sirenita 2: Regreso al mar",
        imagen: "https://image.tmdb.org/t/p/w300/1m6JsSmBa4X1Kp35YW3QPbMpByF.jpg"
      },
      {
        id: "lilo_y_stitch",
        titulo: "Lilo y Stitch",
        imagen: "https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"
      },
      {
        id: "plankton",
        titulo: "Plankton",
        imagen: "https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"
      }
    ]
  },

  poseida: {
    id: "poseida",
    titulo: "Poseida",
    video: "https://dl.dropbox.com/scl/fi/vaojm6s9wj0v8xkxqb0fs/Poseida-2025.mp4?rlkey=0e2wb0onm7ejvdjyzqmxn2ff4&st=",
    poster: "https://image.tmdb.org/t/p/w780/j7b27zJOkjLylvjxFEd7Hc7BRfz.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Una niña está poseída por una entidad oscura y misteriosa. Luchará con todos los recursos a su disposición para librarse de ella. Ni su madre, ni la medicina tradicional, ni un supuesto experto en exorcismos podrán hacer desaparecer al demonio, hasta que una monja se involucra en el caso y siembra una duda aún más terrible que la propia posesión.",
    anio: "2025",
    duracion: "1h 22min",
    calificacion: "80%",
    genero: "Terror • Misterio",
    director: "Yossy Zagha y Jack Zagha ​​Kababie",
    reparto: "Gia Hunter, Fernanda Romero, Alice Coulthard",
    estreno: "08/11/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "Destino_final_6",
        titulo: "Destino final 6: Lazos de sangre",
        imagen: "https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"
      },
      {
        id: "el_mono",
        titulo: "El mono",
        imagen: "https://image.tmdb.org/t/p/w300/z15wy8YqFG8aCAkDQJKR63nxSmd.jpg"
      },
      {
        id: "La_calle_del_terror",
        titulo: "La calle del terror: La reina del baile",
        imagen: "https://image.tmdb.org/t/p/w300/kYeTcmPmuMvBgmwOdOtR5fUwRuH.jpg"
      },
      {
        id: "presencia",
        titulo: "Presencia",
        imagen: "https://image.tmdb.org/t/p/w300/8mRO5AdZ4Rn1crgjTHaUnWWhJXB.jpg"
      },
      {
        id: "until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        id: "un_lugar_en_silencio_3",
        titulo: "Un lugar en silencio 3: Día uno",
        imagen: "https://image.tmdb.org/t/p/w300/mB9GP9Wd7RduYpCSiqurZSnarl6.jpg"
      }
    ]
  },

  pideme_lo_que_quieras: {
    id: "pideme_lo_que_quieras",
    titulo: "Pideme lo que quieras",
    video: "https://dl.dropbox.com/scl/fi/4db8q8kufefbrj0by3z57/P-deme.Lo.Que.Quieras.2025.1080P-Cast.mkv?rlkey=tbvtwt7jxfbp2j2qoobzqiotd&st=",
    poster: "https://image.tmdb.org/t/p/w780/fSO7cnetDfNEflwAsxlkzV446Ua.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/5rtaLwyKAjbceww4J1ro8aA8BNB.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Judith Flores es una chica normal. Tiene un trabajo que le apasiona, muy buenos amigos y un padre encantador. Pero su vida cambia radicalmente cuando conoce a Eric Zimmerman, dueño de la empresa donde ella trabaja. Su relación con Eric está a punto de dinamitar su vida por completo.",
    anio: "2024",
    duracion: "1h 54min",
    calificacion: "89%",
    genero: "Romance • Drama",
    director: "Lucía Alemany",
    reparto: "Gabriela Andrada, Mario Ermito, Celia Freijeiro",
    estreno: "29/11/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "ojala_estuvieras_aqui",
        titulo: "Ojalá estuvieras aquí",
        imagen: "https://image.tmdb.org/t/p/w300/8sxm0NyS72bf7G88jFPOYqGBZyG.jpg"
      },
      {
        id: "romper_el_circulo",
        titulo: "Romper el circulo",
        imagen: "https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"
      },
      {
        id: "sugar_baby",
        titulo: "Sugar Baby",
        imagen: "https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"
      },
      {
        id: "tierra_baja",
        titulo: "Tierra Baja",
        imagen: "https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"
      },
      {
        id: "corazon_delator",
        titulo: "Corazón delator",
        imagen: "https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg"
      },
      {
        id: "la_mitad_de_ana",
        titulo: "La mitad de Ana",
        imagen: "https://image.tmdb.org/t/p/w300/c24RWnJzwAtWZ039o9u6K7c8jyw.jpg"
      }
    ]
  },

  /*R*/

  regalo_maldito: {
    id: "regalo_maldito",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  /*U*/

  until_dawn_noche_de_terror: {
    id: "until_dawn_noche_de_terror",
    titulo: "Until Dawn: Noche de terror",
    video: "https://dl.dropbox.com/scl/fi/pz0ljf2y98oh57s8325xz/Until-Dawn-2025.mp4?rlkey=rdkmtvquhmvim8e2kj8w7lbmq&st=",
    poster: "https://image.tmdb.org/t/p/w780/pibqMWU2qqejx8nBEdWSZnuKKvj.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Un año después de la misteriosa desaparición de su hermana Melanie, Clover y sus amigas se dirigen al remoto valle donde desapareció en busca de respuestas. Mientras exploran un centro de visitantes abandonado, son acechadas por un asesino enmascarado que las mata una a una de forma horrible… para después despertar y encontrarse de nuevo al principio de la misma noche.  ",
    anio: "2025",
    duracion: "1h 43min",
    calificacion: "83,6%",
    genero: "Terror • Misterio",
    director: "David F. Sandberg",
    reparto: "Ella Rubin, Maia Mitchell, Michael Cimino",
    estreno: "25/04/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "tarot_de_la_muerte",
        titulo: "Tarot de la muerte",
        imagen: "https://image.tmdb.org/t/p/w300/r8kgyBIT5umT330gISJH5hqRhhy.jpg"
      },
      {
        id: "ofrenda_al_demonio",
        titulo: "Ofrenda al demonio",
        imagen: "https://image.tmdb.org/t/p/w300/7C1T0aFplHKaYacCqRdeGYLTKCW.jpg"
      },
      {
        id: "la_monja",
        titulo: "La Monja",
        imagen: "https://image.tmdb.org/t/p/w300/q2JFJ8x0IWligHyuLJbBjqNsySf.jpg"
      },
      {
        id: "hablame",
        titulo: "Háblame",
        imagen: "https://image.tmdb.org/t/p/w300/rS8fjd6dYcf64v3ZhAE6fKrxoaF.jpg"
      },
      {
        id: "guerra_mundial_z",
        titulo: "Guerra mundial Z",
        imagen: "https://image.tmdb.org/t/p/w300/4CNaqP1AWLnfXKn71WgZK7WRI9o.jpg"
      },
      {
        id: "el_exorcista_creyente",
        titulo: "El exorcista creyentes",
        imagen: "https://image.tmdb.org/t/p/w300/aNoNB5jWIzqcBqHEYzW232B2ktx.jpg"
      }
    ]
  },

  nombredepelicula: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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

  /*V*/

  venom: {
    id: "venom",
    titulo: "Venom",
    video: "https://dl.dropbox.com/scl/fi/4pr09g8wo9czjjjo7fqvf/Venom.2018.1080p-dual-lat-cinecalidad.to.mp4?rlkey=k4b2w3givd51ay3gc5mqeouob&st=",
    poster: "https://image.tmdb.org/t/p/w780/mUBsvochQCt4r0ezwEC1j2JIafp.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Eddie Brock es un consolidado periodista y astuto reportero que está investigando una empresa llamada Fundación Vida. Esta fundación, dirigida por el eminente científico Carlton Drake, está ejecutando secretamente experimentos ilegales en seres humanos y realizando pruebas que involucran formas de vida extraterrestres y amorfas conocidas como simbiontes.",
    anio: "2018",
    duracion: "1h 52min",
    calificacion: "78%",
    genero: "Acción • Ciencia ficción • Marvel",
    director: "Rubén Fleischer",
    reparto: "Tom Hardy, Michelle Williams, Riz Ahmed",
    estreno: "05/10/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "venom2",
        titulo: "Venom 2: Carnage liberado",
        imagen: "https://image.tmdb.org/t/p/w300/kviQ0gYXjBug7JEYteV8IbZzE3l.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/wWTxMbNpAILRYQXw7orNMpmJmui.jpg"
      },
      {
        id: "el_sorprendente_hombre_araña",
        titulo: "El sorprendente hombre araña",
        imagen: "https://image.tmdb.org/t/p/w300/9MsCANWyLJmz2MAEqiy9vKMpyc8.jpg"
      },
      {
        id: "el_hombre_araña",
        titulo: "El hombre araña",
        imagen: "https://image.tmdb.org/t/p/w300/gh4cZbhZxyTbgxQPxD0dOudNPTn.jpg"
      },
      {
        id: "deadpool_y_wolverine",
        titulo: "Deadpool y Wolverine",
        imagen: "https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg"
      },
      {
        id: "doctor_strange",
        titulo: "Doctor strange",
        imagen: "https://image.tmdb.org/t/p/w300/dAh03zjNzjhiQPrq4Dcr7qKDPlR.jpg"
      }
    ]
  },

  venom2: {
    id: "venom2",
    titulo: "Venom 2: Carnage liberado",
    video: "https://dl.dropbox.com/scl/fi/rl5k0nzze56txlmhc3fdr/Venom.let.there.be.carnage.2021.1080p-dual-lat-cinecalidad.re.mp4?rlkey=5dv2j6jp54034rgq726c4x49d&st=",
    poster: "https://image.tmdb.org/t/p/w780/vIgyYkXkg6NC2whRbYjBD7eb3Er.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/kviQ0gYXjBug7JEYteV8IbZzE3l.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Eddie Brock y su simbionte Venom todavía están intentando descubrir cómo vivir juntos cuando un preso que está en el corredor de la muerte se infecta con un simbionte propio.",
    anio: "2021",
    duracion: "1h 37min",
    calificacion: "73%",
    genero: "Acción • Ciencia ficción • Marvel",
    director: "Andy Serkis",
    reparto: "Tom Hardy, Woody Harrelson, Michelle Williams",
    estreno: "01/10/2021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "venom",
        titulo: "Venom",
        imagen: "https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg"
      },
      {
        id: "venom3",
        titulo: "Venom 3: El ultimo baile",
        imagen: "https://image.tmdb.org/t/p/w300/wWTxMbNpAILRYQXw7orNMpmJmui.jpg"
      },
      {
        id: "viuda_negra",
        titulo: "Viuda negra",
        imagen: "https://image.tmdb.org/t/p/w300/tvl0OXmNQtLrPk7fJ8UHvLrD37R.jpg"
      },
      {
        href: "../View Series/Loki (2021).html",
        titulo: "Loki",
        imagen: "https://image.tmdb.org/t/p/w300/xRm4YaFi7aouVnFAtnkyQw2N7tW.jpg"
      },
      {
        id: "el_hombre_araña_3",
        titulo: "El hombre araña 3",
        imagen: "https://image.tmdb.org/t/p/w300/yqQRaD0uBWKLvSVe6LuMveb0ZLv.jpg"
      },
      {
        id: "thor_ragnarok3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      }
    ]
  },

  venom3: {
    id: "venom_3",
    titulo: "Vemon 3: El último baile",
    video: "https://dl.dropbox.com/scl/fi/jxmi1vi26s67442p5qbh8/Venom.El.-ltimo.baile.2024.1080p-Dual-Lat.mkv?rlkey=70jktptopc447xx0ud7j5k9ux&st=",
    poster: "https://image.tmdb.org/t/p/w780/d5oFul5tqDPuCbWEkq0gLBbs9sb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Eddie y Venom están a la fuga. Perseguidos por sus sendos mundos y cada vez más cercados, el dúo se ve abocado a tomar una decisión devastadora que hará que caiga el telón sobre el último baile de Venom y Eddie.",
    anio: "2024",
    duracion: "1h 49min",
    calificacion: "78%",
    genero: "Acción • Ciencia ficción • Marvel",
    director: "Kelly Marcel",
    reparto: "Tom Hardy, Chiwetel Ejiofor, Templo de Juno",
    estreno: "24/10/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "venom",
        titulo: "Venom",
        imagen: "https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg"
      },
      {
        id: "venom2",
        titulo: "Venom 2: Carnage liberado",
        imagen: "https://image.tmdb.org/t/p/w300/kviQ0gYXjBug7JEYteV8IbZzE3l.jpg"
      },
      {
        id: "spider_man3",
        titulo: "Spider-Man 3: Sin camino a casa",
        imagen: "https://image.tmdb.org/t/p/w300/rkLhaNa37IwzWis8rzWMAYTCdIK.jpg"
      },
      {
        id: "capitan_america4",
        titulo: "Capitán América 4: Un nuevo mundo",
        imagen: "https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"
      },
      {
        id: "doctor_strange_2",
        titulo: "Doctor strange 2: En el multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg"
      },
      {
        id: "iron_man3",
        titulo: "Iron-Man 3",
        imagen: "https://image.tmdb.org/t/p/w300/qhPtAc1TKbMPqNvcdXSOn9Bn7hZ.jpg"
      }
    ]
  },
  spiderman: {
    id: "nombredepelicula",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
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
        id: "cantardesnuda",
        titulo: "Solo Adultos",
        imagen: "https://image.tmdb.org/t/p/w300/",
        adulto: true
      }
    ]
  },

  /*w*/

  winnie_the_pooh_2: {
    id: "winnie_the_pooh_2",
    titulo: "Winnie the Pooh 2: El bosque sangriento",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Fjun24%2FWatch%20Winnie%20the%20Pooh%20Blood%20and%20Honey%202024%20WEBDLSCR1080L4T%20mp4.mp4",
    poster: "https://image.tmdb.org/t/p/w780/4qALHrtmrWxOOKGvG3GJKyDuLVA.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/17UmQl8TuDmHWGlcKeFIjnR8bJF.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "En lo más profundo del Bosque de los Cien Acres, crece una furia destructiva cuando Winnie-the-Pooh, Piglet, Owl y Tigger ven peligrar su hogar y sus vidas después de que Christopher Robin revelara su existencia. No queriendo seguir viviendo en la sombra, el grupo decide llevar la lucha al pueblo de Ashdown, hogar de Christopher Robin, dejando un sangriento rastro de muerte y caos a su paso. Winnie y sus salvajes amigos demostrarán a todo el mundo que son más mortíferos, más fuertes y más listos de lo que nadie podría imaginar y conseguirán vengarse de Christopher Robin, de una vez por todas. ",
    anio: "2024",
    duracion: "1h 33min",
    calificacion: "70%",
    genero: "Terror",
    director: "Rhys Frake-Waterfield",
    reparto: "Scott Chambers, Ryan Oliva, Tallulah Evans",
    estreno: "13/06/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "winnie_the_pooh",
        titulo: "Winnie the Pooh: Miel y sangre",
        imagen: "https://image.tmdb.org/t/p/w300/lfetuG7lq3MVRt6jb1kfX7Va2H.jpg"
      },
      {
        id: "it__capitulo_2",
        titulo: "It: Capitulo 2",
        imagen: "https://image.tmdb.org/t/p/w300/9oERKIVyTWpHNum3STVsAGD4ojz.jpg"
      },
      {
        id: "hablame",
        titulo: "Háblame",
        imagen: "https://image.tmdb.org/t/p/w300/rS8fjd6dYcf64v3ZhAE6fKrxoaF.jpg"
      },
      {
        id: "five_night_at_freddy",
        titulo: "Five night at Freddy´s",
        imagen: "https://image.tmdb.org/t/p/w300/7BpNtNfxuocYEVREzVMO75hso1l.jpg"
      },
      {
        id: "el_exorcismo_de_emily_rose",
        titulo: "El exorcismo de Emily Rose",
        imagen: "https://image.tmdb.org/t/p/w300/2H445u87FDUOl3EijrvUJqZekoY.jpg"
      },
      {
        id: "el_conjuro_3",
        titulo: "El conjuro 3: El diablo me obligo hacerlo",
        imagen: "https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg"
      }
    ]
  },

  winnie_the_pooh: {
    id: "winnie_the_pooh",
    titulo: "Winnie the Pooh: Miel y sangre",
    video: "https://grrfff66me7t.objectstorage.sa-saopaulo-1.oci.customer-oci.com/n/grrfff66me7t/b/Cubojoselyn/o/reset%2Fpeliculas%2Foctubre%2FWatch%20Winnie%20the%20Pooh%20Miel%20Y%20Sangre%20mp4.mp4",
    poster: "https://image.tmdb.org/t/p/w780/jEybIB7yl3zgEdTHukaiR8b9yJp.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/cUXqVDrHaOGEJD1clvVd7ucAHdt.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Christopher Robin se dirige a la universidad y ha abandonado a sus viejos amigos, Pooh y Piglet, lo que lleva al dúo a aceptar sus monstruos internos.",
    anio: "2023",
    duracion: "1h 24min",
    calificacion: "70%",
    genero: "Terror",
    director: "Rhys Frake-Waterfield",
    reparto: "Nikolai Leon, Maria Taylor, Craig David Dowsett",
    estreno: "10/03/2023",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "winnie_the_pooh_2",
        titulo: "Winnie the Pooh 2: El bosque sangriento",
        imagen: "https://image.tmdb.org/t/p/w300/17UmQl8TuDmHWGlcKeFIjnR8bJF.jpg"
      },
      {
        id: "la_llorona",
        titulo: "La Llorona",
        imagen: "https://image.tmdb.org/t/p/w300/yVsINl4Aa9vvQ9lE2LF77qNj7AP.jpg"
      },
      {
        id: "sonrie_2",
        titulo: "Sonrie 2",
        imagen: "https://image.tmdb.org/t/p/w300/aQtWauWpy5KQEHsBURDnoTD6svd.jpg"
      },
      {
        id: "ouija_el_origen_del_mal",
        titulo: "Ouija: El origen del mal",
        imagen: "https://image.tmdb.org/t/p/w300/qELKmWDNahBIHlHHLf3eLCq6j97.jpg"
      },
      {
        id: "el_bosque_de_los_suicidios",
        titulo: "El bosque de los suicidios",
        imagen: "https://image.tmdb.org/t/p/w300/xrk5IwznK8x5kR2BlBYdu2H5GcI.jpg"
      },
      {
        id: "la_monja",
        titulo: "La Monja",
        imagen: "https://image.tmdb.org/t/p/w300/7fxjwtEvqI1BYkXEbGqJ3dQBgXD.jpg"
      }
    ]
  },

  /*Y*/

  yo_tonya: {
    id: "yo_tonya",
    titulo: "Yo, Tonya",
    video: "https://dl.dropbox.com/scl/fi/xyj06qjhvdam4dl57a0zz/Yo-tonya-2017.mp4?rlkey=c39mxfwxgb13abc0zcpya9qos&st=",
    poster: "https://image.tmdb.org/t/p/w780/5owIV2sqdfILBKyOOIkLYVqanZp.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/aVWX0t95Igd8kKC3ejmtHCy1vX6.jpg",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tonya Harding fue la primera mujer estadounidense en completar un salto de triple axel en competición en patinaje sobre hielo en 1991. Pero el éxito en el hielo no siempre estuvo acompañado de felicidad en su vida personal.",
    anio: "2018",
    duracion: "1h 59min",
    calificacion: "92%",
    genero: "Drama",
    director: "Craig Gillespie",
    reparto: "Margot Robbie, Sebastian Stan, Allison Janney",
    estreno: "08/03/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "todo_bien",
        titulo: "¿Todo bien?",
        imagen: "https://image.tmdb.org/t/p/w300/mKdRfCpWkcH0wEEg6yO4a8ES4TX.jpg"
      },
      {
        id: "contraataque",
        titulo: "Contraataque",
        imagen: "https://image.tmdb.org/t/p/w300/kxnFdLJhi37ZVFDCL1ka0yeQVU5.jpg"
      },
      {
        id: "Tiempo_de_guerra",
        titulo: "Warfare. Tiempo de guerra",
        imagen: "https://image.tmdb.org/t/p/w300/fkVpNJugieKeTu7Se8uQRqRag2M.jpg"
      },
      {
        id: "almas_marcadas",
        titulo: "Almas marcadas: Rule + Shaw",
        imagen: "https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg"
      },
      {
        id: "a_ganar",
        titulo: "¡A ganar!",
        imagen: "https://image.tmdb.org/t/p/w300/6GVYL9K2IBFrfIqwwFqMPu5DdC5.jpg"
      },
      {
        id: "la_evaluacion",
        titulo: "La evaluación",
        imagen: "https://image.tmdb.org/t/p/w300/rCGwGWI4a2EaNQCyTe4vDfoiMtk.jpg"
      }
    ]
  },

  bombre: {
    id: "bombre",
    titulo: "",
    video: "",
    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    calidad: "1080P",   // 720P | 1080P | 4K
    cam: false,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "",
    anio: "1996",
    duracion: "1h 19min",
    calificacion: "92%",
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

  /*Z*/

  zootopia_2: {
    id: "zootopia_2",
    titulo: "Zootopia 2",
    video: "https://dl.dropbox.com/scl/fi/rmbqexi8qbhr71ec4lzs9/Zootopia-2-2025.mp4?rlkey=0y4yo5977vs22c03kb4j4wc3d&st=",
    poster: "https://image.tmdb.org/t/p/w780/zdva2LmrzZ0OdTI27ayzKPw0wkF.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/3Wg1LBCiTEXTxRrkNKOqJyyIFyF.jpg",
    calidad: "",   // 720P | 1080P | 4K
    cam: true,         // true si es cámara
    adulto: false,      // true si es +18
    sinopsis: "Tras resolver el caso más importante de la historia de Zootopia, los policías novatos Judy Hopps y Nick Wilde se encuentran en la sinuosa pista de un gran misterio cuando Gary De'Snake llega y revoluciona la metrópolis animal. Para resolver el caso, Judy y Nick deben infiltrarse en nuevos e inesperados rincones de la ciudad, donde su creciente colaboración se pone a prueba como nunca antes.",
    anio: "2025",
    duracion: "1h 40min",
    calificacion: "88%",
    genero: "Animacion • Disney • Comedia • Aventura • Familia",
    director: "Jared Bush",
    reparto: "Ginnifer Goodwin, Jason Bateman, Ke Huy Quan",
    estreno: "27/11/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 RECOMENDACIONES
    recomendaciones: [
      {
        id: "zootopia",
        titulo: "Zootopia",
        imagen: "https://image.tmdb.org/t/p/w300/3BISFHsKQawmAk8yrbbZxVRPotR.jpg"
      },
      {
        id: "vecinos_invasores",
        titulo: "Vecinos invasores",
        imagen: "https://image.tmdb.org/t/p/w300/69fRZPeXBw2ul88oMOCjWtKTEYY.jpg"
      },
      {
        id: "ratatouille",
        titulo: "Ratatouille",
        imagen: "https://image.tmdb.org/t/p/w300/bGL269BBSug2yDJk3VU8WLAdTvv.jpg"
      },
      {
        id: "kung_fu_panda_4",
        titulo: "Kung fu panda 4",
        imagen: "https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"
      },
      {
        id: "buscando_a_nemo",
        titulo: "Buscando a Nemo",
        imagen: "https://image.tmdb.org/t/p/w300/jPhak722pNGxQIXSEfeWIUqBrO5.jpg"
      },
      {
        id: "tierra_de_osos",
        titulo: "Tierra de osos",
        imagen: "https://image.tmdb.org/t/p/w300/6kGf1Nm99GKtyOCrxmNs6thHmdW.jpg"
      }
    ]
  },

};

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
  document.querySelector(".genero-badge").textContent = movie.genero;

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

  progress.style.background = `
    linear-gradient(
      90deg,
      #007bff 0%,
      #00cfff ${percent * 0.4}%,
      #ffffff ${percent * 0.7}%,
      #ff4fa3 ${percent}%,
      rgba(255,255,255,0.25) ${percent}%,
      rgba(255,255,255,0.25) 100%
    )
  `;
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
video.addEventListener("loadedmetadata", () => {
  if (resumeApplied) return;

  const savedTime = parseFloat(localStorage.getItem(STORAGE_KEY));
  if (!savedTime || savedTime < 5) return;

  if (savedTime < video.duration - 5) {
    video.currentTime = savedTime;
  }

  resumeApplied = true;
});


  /* ================= PROGRESO (SIN PAUSA) ================= */
/* ================= PROGRESO + GUARDADO ================= */
let lastSavedTime = 0;

video.addEventListener("timeupdate", () => {
  if (!video.duration || isSeeking) return;

  const percent = (video.currentTime / video.duration) * 100;
  updateProgressUI(percent);

  currentTimeEl.textContent = formatTime(video.currentTime);
  durationEl.textContent = formatTime(video.duration);

  // 💾 guardar cada 5 segundos
  if (!video.paused && !video.ended) {
    if (Math.abs(video.currentTime - lastSavedTime) >= 5) {
      localStorage.setItem(STORAGE_KEY, video.currentTime);
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

  showOverlay();

  const percent = progress.value;
  const previewTime = (percent / 100) * video.duration;

  // 🔥 sincroniza color + circulito en tiempo real
  updateProgressUI(percent);

  // solo preview del tiempo (no mueve el video aún)
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
  video.addEventListener("ended", () => {
  localStorage.removeItem(STORAGE_KEY);
  resumeApplied = false;
  lastSavedTime = 0;

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
    if (e.target.tagName === "BUTTON" || e.target.type === "range") return;
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
const tipo = movie.tipo || "pelicula";

  fetch("guardar_favorito.php", {
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

    if(data.status === "success"){
      mostrarModalFavoritos("Película agregada a favoritos");

      setTimeout(() => {
        window.location.href = "favoritos.php";
      }, 1500);
    }

    else if(data.status === "exists"){
      mostrarModalFavoritos("Esta película ya está en favoritos");
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
      padding: 4px 10px;
      background-color: rgba(255, 255, 255, 0.1);
      color: rgba(255, 0, 81, 1);
      border: 1px solid rgba(255, 0, 81, 1);
      border-radius: 20px;
      font-size: 0.7rem;
      font-style: normal;
      max-width: 135px;          /* Limita el ancho máximo */
      white-space: nowrap;       /* No saltar de línea */
      overflow: hidden;          /* Oculta el exceso */
      text-overflow: ellipsis;   /* Agrega los ... */
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

    .genero-badge {
      padding: 4px 10px;
      background-color: rgba(255, 255, 255, 0.1);
      color: rgba(255, 0, 81, 1);
      border: 1px solid rgba(255, 0, 81, 1);
      border-radius: 20px;
      font-size: 0.7rem;
      font-style: normal;
      max-width: 135px;          /* Limita el ancho máximo */
      white-space: nowrap;       /* No saltar de línea */
      overflow: hidden;          /* Oculta el exceso */
      text-overflow: ellipsis;   /* Agrega los ... */
    }
    
  </style>

  <div class="recomendaciones">
    <h4>Podría interesarte:</h4>
    <br/>
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

  // Datos de la película
  const movieId = movie.id;
  const titulo = movie.titulo ? movie.titulo.trim() : "";
  const tipo = movie.tipo ? movie.tipo.trim() : "pelicula";
  const historialImagen = movie.imagen || "";
  const archivo = "Reproductor Universal.php?id="+movieId;

  // Guardar entrada inicial en historial
  fetch("guardar_historial.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body:
      "movie_id=" + encodeURIComponent(movieId) +
  "&titulo=" + encodeURIComponent(titulo) +
  "&tipo=" + encodeURIComponent(tipo) +
  "&imagen=" + encodeURIComponent(historialImagen) +
  "&archivo=" + encodeURIComponent(archivo) +
  "&progreso=" + encodeURIComponent("Asistido 0 min")
  })
  .then(res => res.json())
  .then(data => {

    if (data.status === "new") {
      esperarFinLoader(() => {
        mostrarModalFavoritos("✅ Agregado al historial");
      });
    }

  });

  // Actualizar progreso cada 10 segundos
  setInterval(() => {

    if (!videoElement.paused && !videoElement.ended) {

      const minutos = Math.floor(videoElement.currentTime / 60);
      const progreso = "Asistido " + minutos + " min";

      fetch("guardar_historial.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body:
          "movie_id=" + encodeURIComponent(movieId) +
          "&titulo=" + encodeURIComponent(titulo) +
          "&tipo=" + encodeURIComponent(tipo) +
          "&imagen=" + encodeURIComponent(historialImagen) +
          "&progreso=" + encodeURIComponent(progreso)
      });

    }

  }, 10000);

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