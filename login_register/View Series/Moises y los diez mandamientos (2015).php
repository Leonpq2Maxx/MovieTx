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

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>MovieTx - Reproductor</title>
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans&amp;family=Roboto&amp;display=swap" rel="stylesheet"/>
  <link rel="icon" type="image/png" href="../Logo Poster MovieTx PNG/Logo MovieTx.png">
  <link href="https://cdn.jsdelivr.net/gh/CDNSFree2/Plyr/plyr.css" rel="stylesheet"/>
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
    .player {
      position: relative;
      padding-top: 56.25%; /* 16:9 aspect ratio */
      height: 0;
      overflow: hidden;
      max-width: 100%;
      margin: 0 auto;
    }
    .player video,
    .player iframe,
    .player .plyr {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
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

    .plyr__progress input[type="range"] {
      height: 5px;
      border-radius: 999px;
      background: linear-gradient(270deg, #00c6ff, #00e0ff, #00c6ff);
      background-size: 400% 400%;
      animation: neonFlow 3s ease infinite;
      box-shadow: 0 0 14px rgba(0, 230, 255, 0.6);
      transition: all 0.3s ease;
    }


    .plyr__progress input[type="range"]:hover {
      box-shadow: 0 0 20px rgba(0, 250, 255, 0.8);
      transform: scaleY(1.1);
    }


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
  const continueModal = document.getElementById('continue-modal');
  if (continueModal) continueModal.style.display = 'none';
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

   window.addEventListener('load', () => {

  setTimeout(() => {
    loader.classList.add('hidden');

    // 🔥 ESTE ES EL CAMBIO IMPORTANTE
    guardarHistorial();

  }, 200);

});


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
    poster="https://image.tmdb.org/t/p/w780/cvaXVVVHi7TNv2HPJwQLTsoilbb.jpg"
  >
  <source type="video/mp4">
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
        <input type="range" id="progressBar" min="0" max="100" step="0.1" value="0">
        <button id="btnFullscreen" class="fullscreen-btn">⛶</button>
      </div>
    </div>
  </div>
</div>

  <div class="info">
    <i class="fal fa-calendar-alt"></i><span id="season-year">2015</span>
    <i class="fal fa-thumbs-up"></i><span>89%</span>
    <div class="hd-tag">
      <svg width="22" height="22" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <rect width="24" height="24" rx="4" fill="white" />
        <text x="4" y="17" fill="black" font-size="12" font-family="Arial, sans-serif" font-style="italic" font-weight="bold">HD</text>
      </svg>
      <span>1080P</span>
    </div>

  </div>
  
  <div class="info-pelicula">
    <h1>Moisés y los Diez Mandamientos</h1>
    <span class="genero-badge">Drama • Biblico</span>
    <!-- BOTÓN TEMPORADAS -->
<div class="series-ui">
  <button id="btn-open-seasons">Temporadas</button>
</div>

<!-- MODAL TEMPORADAS -->
<div id="season-modal" class="season-modal hidden">
  <div class="season-box">
    <button class="season-close">✖</button>
    <h3>Seleccionar temporada</h3>

    <div id="season-list" class="season-list"></div>
  </div>
</div>

<!-- EPISODIOS -->
<div id="episodes-counter" class="episodes-counter">0 Capitulos</div>
<div id="episodes-container" class="episodes-scroll"></div>
  
    <p class="sinopsis">
      Conoce la historia de Moisés, siguiendo la conocida historia de las plagas y mandamientos, y entretejiendo otras historias de los hebreos y egipcios que influyeron en su vida.
    </p>

    <div class="ficha-tecnica" style="text-align:center;margin-top:20px;font-size:0.9rem;color:#ccc;">
      <p><strong>Director:</strong> Vivian de Oliveira</p>
      <p><strong>Reparto:</strong> Guilherme Winter, Giselle Itié, Marcela Barrozo</p>
      <p><strong>Estreno:</strong> 23/03/2015 | <strong>Idioma:</strong> Español Latino 🇲🇽</p>
    </div>

    <br>

    <div class="acciones">
      <button id="btn-favorito">⭐ Agregar a Favoritos</button>
    </div>
  </div>

  <style>

  .episodes-counter {
  display: flex;
  justify-content: flex-end;
  margin: 6px 12px 0 12px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #9ad0ff;
}

#player-loader.hidden {
  display: none;
}


  /* BOTÓN */
.series-ui {
  text-align: center;
  margin: 20px 0;
}

#btn-open-seasons {
  background: linear-gradient(135deg,#ff2d55,#ff5e7e);
  border: none;
  color: #fff;
  padding: 10px 22px;
  border-radius: 999px;
  font-weight: 600;
  cursor: pointer;
}

/* MODAL */
.season-modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.7);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
}

.season-modal.hidden {
  display: none;
}

.season-box {
  background: #141414;
  width: 90%;
  max-width: 360px;
  border-radius: 14px;
  padding: 25px;
  position: relative;
  box-shadow: 0 0 25px rgba(255,45,91,.5);
}

.season-close {
  position: absolute;
  top: 10px;
  right: 14px;
  background: none;
  border: none;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
}

/* TEMPORADAS */
.season-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 15px;
}

.season-item {
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #ff2d55;
  cursor: pointer;
  text-align: center;
}

.season-item.active {
  background: #ff2d55;
  box-shadow: 0 0 12px rgba(255,45,91,.8);
}

/* EPISODIOS */
.episodes-scroll {
  display: flex;
  gap: 9px; /*estaba en 10*/
  padding: 5px; /*estaba en 10*/
  overflow-x: auto;
  scroll-snap-type: x mandatory;
}

.episode-box {
  min-width: 50px; /*estaba en 60*/
  height: 50px; /*estaba en 60*/
  background: #111;
  border: 1px solid #333;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  scroll-snap-align: start;
}

.episode-box.active {
  border-color: #00c6ff;
  box-shadow: 0 0 12px rgba(0,198,255,.7);
}

.episode-box.watching {
  border-color: #ff2d55;
}

</style>

  <!-- CONTINUAR -->

  <style>
/* 🔴 Modal Continuar Viendo estilo Netflix */
#continue-modal {
  position: fixed;
  inset: 0;
  display: none;
  justify-content: center;
  align-items: center;
  background: rgba(0,0,0,0.75);
  backdrop-filter: blur(6px);
  z-index: 99999;
}
#continue-modal.active { display: flex; }

#continue-modal .modal-box {
  background: #141414;
  color: #fff;
  border-radius: 12px;
  padding: 40px 25px 25px;
  width: 90%;
  max-width: 420px;
  box-shadow: 0 0 25px rgba(229,9,20,0.5);
  text-align: center;
  position: relative;
}

#continue-modal h3 {
  margin-bottom: 15px;
  font-size: 1.2rem;
  color: #e50914;
}

#continue-modal .actions {
  margin-top: 20px;
  display: flex;
  justify-content: space-around;
  gap: 10px;
}

#continue-modal button {
  flex: 1;
  padding: 8px 12px;
  background: #e50914;
  border: none;
  border-radius: 8px;
  color: #fff;
  font-weight: bold;
  cursor: pointer;
}
#continue-modal button:hover { background: #f6121d; }

#close-continue-modal {
  position: absolute;
  top: 5px;
  right: 12px;
  background: none;
  border: none;
  color: #fff;
  font-size: 0.6rem;  /* 🔹 Achicamos la X */
  padding: 2px 4px;   /* 🔹 Ajuste opcional */
  cursor: pointer;
}
.episode-box.disabled {
  pointer-events: none;
  opacity: 0.6;
  cursor: default;
}


</style>


  <script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('continue-modal');
  const btnContinuar = document.getElementById('btn-continuar');
  const btnNuevo = document.getElementById('btn-empezar-nuevo');
  const closeModalX = document.getElementById('close-continue-modal');
  const continueInfo = document.getElementById('continue-info');
  const videoElement = document.querySelector('video');

  // 🔹 Botón X para cerrar modal
  closeModalX.addEventListener('click', () => {
    modal.classList.remove('active');
  });

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



<script>
document.addEventListener("DOMContentLoaded", () => {
  
/* =====================================================
   IDENTIDAD ÚNICA DE LA SERIE
===================================================== */
const SERIES_KEY = "moises_y_los_diez_mandamientos_2015"; // 🔑 ÚNICA POR SERIE


/* =====================================================
   ELEMENTOS DEL REPRODUCTOR
===================================================== */
const player = document.getElementById("mobilePlayer");
const video = document.getElementById("videoPlayer");
const source = video.querySelector("source");

const overlay = document.getElementById("playerOverlay");
const btnPlay = document.getElementById("btnPlay");
const btnRewind = document.getElementById("btnRewind");
const btnForward = document.getElementById("btnForward");
const btnFullscreen = document.getElementById("btnFullscreen");

const progress = document.getElementById("progressBar");
const currentTimeEl = document.getElementById("currentTime");
const durationEl = document.getElementById("duration");

/* =====================================================
   UI TEMPORADAS / EPISODIOS
===================================================== */
const btnOpenSeasons = document.getElementById("btn-open-seasons");
const modal = document.getElementById("season-modal");
const seasonList = document.getElementById("season-list");
const episodesContainer = document.getElementById("episodes-container");
const btnCloseModal = document.querySelector(".season-close");
const episodesCounter = document.getElementById("episodes-counter");
const seasonYearEl = document.getElementById("season-year");



/* =====================================================
   DATA (TU seasonsData, SIN CAMBIOS)      { id: "t1e11", number: 11, src: "" },
===================================================== */
const seasonsData = [
  {
    id: "t1",
    name: "Temporada 1",
    year: 2015,
    episodes: [
      { id: "t1e1", number: 1, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_sqgi2mua/format/applehttp/protocol/https/flavorIds/1_30h1v5r4,1_mxxwv7xp,1_15gov07p,1_azcldmfw,1_68z8mkhk,1_dfy905r1,1_3h3bl0ga,1_gpdug8me/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e2", number: 2, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_n8b4cjwj/format/applehttp/protocol/https/flavorIds/1_umpi5qn4,1_4z4eafiu,1_nzrlox6p,1_k65ykb0c,1_z74xqy2s,1_221h8fq4,1_mn15nr7v,1_nmaonehv/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e3", number: 3, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_7c399pil/format/applehttp/protocol/https/flavorIds/1_l7yakppm,1_0q3vf9bf,1_q1n626e3,1_fqll7tt2,1_9vxfj790,1_bd3a71d4,1_pp96502r,1_v150prlw/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e4", number: 4, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_nb9jeye6/format/applehttp/protocol/https/flavorIds/1_ev5uqwiz,1_1pcwt9tq,1_7vrpgasr,1_dviv9xy8,1_meuiennw,1_4wvxfj2s,1_nkg7k71q,1_a13z8hfy/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e5", number: 5, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_9lnht10l/format/applehttp/protocol/https/flavorIds/1_gr4ejwsd,1_e5etniza,1_x88b6pg2,1_829h5unv,1_zsc40ew6,1_re3jyvbo,1_zl9s13wq,1_1np0wihz/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e6", number: 6, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_299n0ac4/format/applehttp/protocol/https/flavorIds/1_86k4uspl,1_0dr6ptbk,1_4doilpkh,1_ia72i3ef,1_wtyonpbf,1_pl2jdstc,1_15ep9qh7,1_3w1nn9ho/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e7", number: 7, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_sa0y9sbn/format/applehttp/protocol/https/flavorIds/1_uft6kdz3,1_ydi2tu9p,1_8ddp6k7k,1_wo9jj2wm,1_mxjqbo16,1_tgpqs1vl,1_ixwpx81u,1_94bj1m8s/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e8", number: 8, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_9bv4ipce/format/applehttp/protocol/https/flavorIds/1_oqa79fka,1_trv9jaor,1_rycj3nsw,1_qlkwl2xr,1_lc0ypvhh,1_1wzeguhz,1_dd23ccg8,1_anihk1ly/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e9", number: 9, src: "://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_qt9h3ys4/format/applehttp/protocol/https/flavorIds/1_ergmxfln,1_usnvtc4q,1_unwfdjjc,1_9bsmgdcw,1_f58nkxpa,1_t3p1zc32,1_lbul3fhx,1_r1l7cqvs/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e10", number: 10, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_yil10mzs/format/applehttp/protocol/https/flavorIds/1_rpc3x70n,1_vclcwx8f,1_wia5bk5q,1_iygzvz5h,1_fa2iiaft,1_fuonovpo,1_t2orrlqt,1_tsowhkw2/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e11", number: 11, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_qezrbfkw/format/applehttp/protocol/https/flavorIds/1_hwxvyglv,1_a10x5zfn,1_llyp7bv3,1_4mlg9ae3,1_jqtsci0c,1_f18r2hlp,1_qk5rshph,1_nu872lku/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e12", number: 12, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_63jcdurf/format/applehttp/protocol/https/flavorIds/1_nxc5o9pk,1_uqig1kno,1_by3rji3y,1_d1za1ubf,1_fvhtcbe8,1_y9udavvi,1_cxmh53lk,1_sdmck8wu/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e13", number: 13, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_nv7gt016/format/applehttp/protocol/https/flavorIds/1_g1nqvobx,1_5uh9p82l,1_thx4ht8r,1_lswg8nr5,1_a7go3tji,1_l9nrsr5e,1_sb1lsb3r,1_603qe4w2/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e14", number: 14, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_4520ivwj/format/applehttp/protocol/https/flavorIds/1_y9ccev7v,1_4sdr4lzr,1_4wwkxfm8,1_woj3jhft,1_01b1ivy8,1_9noh58o1,1_21sbi3qu,1_h0g90bzf/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e15", number: 15, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_u65ug10m/format/applehttp/protocol/https/flavorIds/1_l9ldaqgh,1_h2qd5k1p,1_lxvukv31,1_x61i12fo,1_egbyqfdb,1_h8sdmumk,1_7wg4wbq5,1_rf60342k/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e16", number: 16, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_plym731o/format/applehttp/protocol/https/flavorIds/1_j1bvcpnl,1_b4nb3hf4,1_m00n2mop,1_p3hxxm80,1_u3ixttth,1_27l331rt,1_2wfhc3xl,1_1u8xogtz/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e17", number: 17, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_5ec4suxz/format/applehttp/protocol/https/flavorIds/1_72e9qu3h,1_laundplo,1_2du6liuf,1_2zwopc73,1_um03lxjr,1_zho26qa9,1_y7tqwchf,1_3a3vhog9/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e18", number: 18, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_hqdjardg/format/applehttp/protocol/https/flavorIds/1_94f5xgmu,1_y2d5rfmj,1_zhpapibs,1_fc0iehi6,1_96eueein,1_mwgl4i5c,1_3o8hi6qs,1_51l5kkio/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e19", number: 19, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_wbtvxkli/format/applehttp/protocol/https/flavorIds/1_murm4mvy,1_h2ggq29k,1_7o0b7tnm,1_tbqwyvpj,1_0kifymop,1_a8xjwckh,1_0sjy6p2l,1_l4txome9/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e20", number: 20, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_619duc4s/format/applehttp/protocol/https/flavorIds/1_1mkd490l,1_36tt8puv,1_jigzhk6e,1_p4c1k0qg,1_dk706u2b,1_vf2hzvot,1_2l5ubc7m,1_aabfjo7e/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e21", number: 21, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_t20gqzmp/format/applehttp/protocol/https/flavorIds/1_9yqkiv5b,1_4svulaoh,1_smtqmm1n,1_0zirfldg,1_k5sgz13f,1_f20kx7u8,1_dj0ed3x3,1_okpna9aa/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e22", number: 22, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_74jrrdhd/format/applehttp/protocol/https/flavorIds/1_kjh76gxa,1_46mxbsdk,1_u0vj43b2,1_epi2qxus,1_1hjghcxc,1_56ejtzdv,1_h0rqf0h9,1_xxx4fmnf/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e23", number: 23, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_s0h6nuln/format/applehttp/protocol/https/flavorIds/1_cszgy7m3,1_w0wcsjnx,1_zsbhoqaz,1_x6slwhat,1_44fvs27t,1_xx6z25t9,1_vl56spvy,1_98syqhia/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e24", number: 24, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_qozwetxk/format/applehttp/protocol/https/flavorIds/1_wowr0gvg,1_e2ulxc4u,1_1usmflk4,1_5jzvandx,1_8i0bc7kn,1_ltp17bg0,1_9rguhbuo,1_anogi9s8/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e25", number: 25, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_0yc0k97m/format/applehttp/protocol/https/flavorIds/1_ym54xfiv,1_oyfxewwz,1_gqelv2il,1_b8wy8lkn,1_zux0lige,1_mfcwdtg7,1_9oauyzh5,1_7fsv646f/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e26", number: 26, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_s0z9hg5b/format/applehttp/protocol/https/flavorIds/1_jr2lmmnj,1_k6hebck4,1_mdww5q8k,1_xb2hbgwt,1_72guxynj,1_chq6we10,1_raqassm3,1_yvzdy6gd/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e27", number: 27, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_rdxqdvqt/format/applehttp/protocol/https/flavorIds/1_4omohz2o,1_cerhp2k2,1_9fpcs7ko,1_b02enwl9,1_sx0fqo5e,1_cfm1r5uq,1_x9jb166v,1_2vr8rpgz/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e28", number: 28, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_w10o9i0e/format/applehttp/protocol/https/flavorIds/1_us86q7mx,1_r8o5gqnf,1_obv5v6yz,1_r4htngo4,1_o4bhp1pk,1_kdaup6gn,1_20tiyjes,1_zgibhpep/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e29", number: 29, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_1gb417ae/format/applehttp/protocol/https/flavorIds/1_wtpcbo2z,1_kusf1vm7,1_35gh23ui,1_8544r8bp,1_ukx2gcjj,1_qf9pzqyg,1_oqkfb06m,1_829ftkp2/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e30", number: 30, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_0g1vlg5q/format/applehttp/protocol/https/flavorIds/1_crpnmg22,1_d0svbmiu,1_selgspqk,1_dcmkvy7z,1_aa8lputv,1_3gb3m2qq,1_xiu0n6pc,1_knf8kgbj/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e31", number: 31, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_m43qexmy/format/applehttp/protocol/https/flavorIds/1_d9nlx9xb,1_ba9zttmc,1_t05wpd60,1_b6ag27ra,1_wulf4c65,1_vflquvlt,1_atatgu3t,1_aclxbxly/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e32", number: 32, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_jcpkuv12/format/applehttp/protocol/https/flavorIds/1_so45lpft,1_zyp1lyb1,1_xxamfvlf,1_uv7eraa9,1_s6e39zlc,1_zh53lmi2,1_ljow0qr7,1_2tdz46c2/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e33", number: 33, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_p85a9k4z/format/applehttp/protocol/https/flavorIds/1_ypqamqeh,1_gc7g49sm,1_kup1zkhc,1_hzehkop9,1_58i4auif,1_wqsqb0r2,1_gowkbnlp,1_qmfrbw1c/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e34", number: 34, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_6f8l70l7/format/applehttp/protocol/https/flavorIds/1_gkekrnqi,1_s1axbgr3,1_s9xaf3t2,1_xytw4sta,1_dad57cwg,1_cyrlwctv,1_aejdhnfo,1_cynht55d/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e35", number: 35, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_9hzrm2dl/format/applehttp/protocol/https/flavorIds/1_ereebtjv,1_wgrqs168,1_vt3ab0tq,1_jmjdqb75,1_ptwgc4sl,1_p33t7jy3,1_qs5lmfj9,1_qjsjo0ha/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e36", number: 36, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_iuaak1yk/format/applehttp/protocol/https/flavorIds/1_w1snmoch,1_9o9equz6,1_crq81rmg,1_qvs6og0w,1_19coqv60,1_coxsrwxo,1_xxzkwwo8,1_mu7oikvh/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e37", number: 37, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_6zuy4h5r/format/applehttp/protocol/https/flavorIds/1_43rktltx,1_n55nc4ud,1_r8m2j1z1,1_bsn2cdxb,1_6gul9a1j,1_lafm06wp,1_p5rjjx40,1_hl3zankq/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e38", number: 38, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_g4r8qca6/format/applehttp/protocol/https/flavorIds/1_kw4djft5,1_z0v7omn0,1_2tr5dp9x,1_8vx17nci,1_2vsmtfne,1_zwkhp5dd,1_y3aarrj2,1_c55btu55/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e39", number: 39, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_zufxtrlc/format/applehttp/protocol/https/flavorIds/1_ze1qznft,1_c5opdms4,1_rsb1r9wr,1_i3qlpu5l,1_gkc0fj49,1_68jvsebt,1_kagdhs66,1_g7aaf1jy/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e40", number: 40, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_tb79qebc/format/applehttp/protocol/https/flavorIds/1_wwq64glt,1_p9jaxdlv,1_1flm0d0f,1_tly96qz7,1_y1kwg7ti,1_hk9ymq42,1_bz4lbnkv,1_ifoichza/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e41", number: 41, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_nslcwp78/format/applehttp/protocol/https/flavorIds/1_bgd858d1,1_xqget5ul,1_y4zu7owg,1_l3hcee3d,1_pkozziaq,1_goui9127,1_aeolrxrx,1_dy3nmd1n/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e42", number: 42, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_1bijk2dq/format/applehttp/protocol/https/flavorIds/1_0u6syr35,1_utlyfp78,1_mzjss8ff,1_a1wr5w9e,1_l1t5di30,1_0pptan92,1_zbvfknfw,1_sp54c5v5/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e43", number: 43, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_0251p0ke/format/applehttp/protocol/https/flavorIds/1_2n1inhpe,1_q5qjnwi3,1_5ut1jfsi,1_2icnrmw0,1_vh2suw65,1_bqbvzssw,1_dzipge2k,1_wht0uk2n/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      { id: "t1e44", number: 44, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_v6o8z1yi/format/applehttp/protocol/https/flavorIds/1_thovax0f,1_91f2tw44,1_gpsn7nsz,1_pczo7jzl,1_78q4ql2w,1_pfo7t0u8,1_uodq03cz,1_osp5o5tw/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
      
    ]
  }, 
  {
    id: "t2",
    name: "Temporada 2",
    year: 2019,
    episodes: [
      { id: "t2e1", number: 1, src: "" },
      { id: "t2e2", number: 2, src: "" },
      { id: "t2e3", number: 3, src: "" },
      { id: "t2e4", number: 4, src: "" },
      { id: "t2e5", number: 5, src: "" }
    ]
  }
];


/* =====================================================
   ESTADO
===================================================== */
let currentSeasonId = seasonsData[0].id;
let currentEpisodeId = seasonsData[0].episodes[0].id;
let isLoading = false;
let isSeeking = false;
let wasPlayingBeforeSeek = false;


// episodio activo por temporada
const seasonEpisodeState = {};

/* =====================================================
   GUARDAR EPISODIO ACTUAL
===================================================== */
const saveCurrentEpisode = (seasonId, episodeId) => {
  localStorage.setItem(`${SERIES_KEY}_lastSeason`, seasonId);
  localStorage.setItem(`${SERIES_KEY}_lastEpisode`, episodeId);
};

const loadCurrentEpisode = () => ({
  seasonId: localStorage.getItem(`${SERIES_KEY}_lastSeason`),
  episodeId: localStorage.getItem(`${SERIES_KEY}_lastEpisode`)
});



/* =====================================================
   REANUDACIÓN POR EPISODIO
===================================================== */
const resumeKey = id => `${SERIES_KEY}_resume_${id}`;
const saveResume = (id, t) => t > 5 && localStorage.setItem(resumeKey(id), t);
const loadResume = id => parseFloat(localStorage.getItem(resumeKey(id))) || 0;
const clearResume = id => localStorage.removeItem(resumeKey(id));

/* =====================================================
   UTIL
===================================================== */
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


/* =====================================================
   RESET PLAYER
===================================================== */
function resetPlayer() {

  isLoading = true;
  lastSavedTime = 0;

  video.pause();

  video.onloadedmetadata = null;
  video.onplay = null;
  video.onpause = null;

  video.currentTime = 0;

  updateProgressUI(0);

  currentTimeEl.textContent = "0:00";
  durationEl.textContent = "0:00";

  btnPlay.textContent = "▶";
  overlay.classList.remove("hide");

  requestAnimationFrame(() => {
    isLoading = false;
  });

}


/* =====================================================
   LOAD EPISODE
===================================================== */
function loadEpisode(seasonId, episode, autoplay = true) {

  if (!episode || !episode.src) return;

  isLoading = true;

  resetPlayer();

  overlay.classList.remove("hide");
  btnPlay.textContent = "▶";

  currentSeasonId = seasonId;
  currentEpisodeId = episode.id;
  seasonEpisodeState[seasonId] = episode.id;

  // 🔥 ACTUALIZAR AÑO DE TEMPORADA
  const seasonData = seasonsData.find(s => s.id === seasonId);
  if (seasonData && seasonYearEl) {
    seasonYearEl.textContent = seasonData.year;
  }

  // Guardar episodio actual
  saveCurrentEpisode(seasonId, episode.id);

  source.src = episode.src;
  video.load();

  video.onloadedmetadata = () => {

    durationEl.textContent = formatTime(video.duration);

    const resume = loadResume(episode.id);
    if (resume > 0 && resume < video.duration - 5) {
      video.currentTime = resume;
    }

    isLoading = false;

    if (autoplay) {
      video.play().then(() => {
        syncPlayButton();
        overlay.classList.add("hide");
      });
    }

  };

  // 🔥 VOLVER A DIBUJAR EPISODIOS
  renderEpisodes(seasonId);
  updateEpisodesCounter(seasonId);

}

/* =====================================================
   CONTROLES
===================================================== */
btnPlay.onclick = e => {
  e.stopPropagation();
  if (!source.src) return;

  if (video.paused) {
    video.play();
    overlay.classList.add("hide");
  } else {
    video.pause();
    overlay.classList.remove("hide");
  }

  syncPlayButton();
  showOverlay();

};


btnRewind.onclick = e => {
  e.stopPropagation();
  if (!video.duration) return;

  video.currentTime = Math.max(0, video.currentTime - 10);
  showOverlay();   // 👈 MOSTRAR CONTROLES
};


btnForward.onclick = e => {
  e.stopPropagation();
  if (!video.duration) return;

  video.currentTime = Math.min(video.duration, video.currentTime + 10);
  showOverlay();   // 👈 MOSTRAR CONTROLES
};


function syncPlayButton() {
  btnPlay.textContent = video.paused ? "▶" : "❚❚";
}

let overlayTimeout = null;

function showOverlay() {
  overlay.classList.remove("hide");

  clearTimeout(overlayTimeout);
  overlayTimeout = setTimeout(() => {
    if (!video.paused) {
      overlay.classList.add("hide");
    }
  }, 3000);
}



/* =====================================================
   FULLSCREEN
===================================================== */
btnFullscreen.onclick = e => {
  e.stopPropagation();

  if (!document.fullscreenElement) {
    if (player.requestFullscreen) {
      player.requestFullscreen();
    } else if (player.webkitRequestFullscreen) {
      player.webkitRequestFullscreen();
    }
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    }
  }
};

/* =====================================================
   PROGRESS + GUARDADO (CORRECTO)
===================================================== */

let lastSavedTime = 0;

video.addEventListener("timeupdate", () => {
  if (!video.duration || isSeeking) return;

  const percent = (video.currentTime / video.duration) * 100;
  updateProgressUI(percent);

  currentTimeEl.textContent = formatTime(video.currentTime);
  durationEl.textContent = formatTime(video.duration);

  // 💾 GUARDAR REANUDACIÓN CADA 5 SEGUNDOS
  if (!video.paused && !video.ended) {
    if (Math.abs(video.currentTime - lastSavedTime) >= 5) {
      saveResume(currentEpisodeId, video.currentTime);
      lastSavedTime = video.currentTime;
    }
  }
});



// ⬇️ empieza a arrastrar
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

// ⬇️ cuando suelta (AQUÍ ESTÁ EL EVENTO 3)
progress.addEventListener("mouseup", applySeek);
progress.addEventListener("touchend", applySeek);

function applySeek() {
  if (!video.duration) return;

  const newTime = (progress.value / 100) * video.duration;
  video.currentTime = newTime;

  isSeeking = false;

  if (wasPlayingBeforeSeek) {
    video.play();
  }
}


/* =====================================================
   AUTO SIGUIENTE
===================================================== */
video.addEventListener("ended", () => {
  // 1️⃣ borrar reanudación del episodio terminado
  clearResume(currentEpisodeId);

  const season = seasonsData.find(
    s => s.id === currentSeasonId
  );
  if (!season) return;

  const idx = season.episodes.findIndex(
    e => e.id === currentEpisodeId
  );

  // 2️⃣ pasar al siguiente episodio
  if (idx !== -1 && idx + 1 < season.episodes.length) {
    const nextEpisode = season.episodes[idx + 1];
    loadEpisode(season.id, nextEpisode, true); // 🔥 autoplay real
    return;
  }

  // 3️⃣ fin de temporada
  btnPlay.textContent = "▶";
  overlay.classList.remove("hide");
});


/* =====================================================
   OVERLAY TAP
===================================================== */
player.onclick = e => {
  // ⛔ ignorar botones y barra
  if (e.target.closest("button") || e.target.type === "range") return;

  // ⛔ ignorar clicks fuera del overlay/video
  if (!overlay.contains(e.target) && e.target !== video) return;

  overlay.classList.toggle("hide");
};



/* =====================================================
   TEMPORADAS
===================================================== */
function renderSeasons() {
  seasonList.innerHTML = "";

  seasonsData.forEach(season => {
    const div = document.createElement("div");
    div.className = "season-item";
    div.textContent = season.name;
    if (season.id === currentSeasonId) div.classList.add("active");

    div.onclick = () => {
      currentSeasonId = season.id;
      const epId =
        seasonEpisodeState[season.id] || season.episodes[0].id;
      const ep = season.episodes.find(e => e.id === epId);
      renderSeasons();
      loadEpisode(season.id, ep, false);
      modal.classList.add("hidden");
    };

    seasonList.appendChild(div);
  });

  btnOpenSeasons.textContent =
    seasonsData.find(s => s.id === currentSeasonId).name;
}

/* =====================================================
   EPISODIOS + SCROLL
===================================================== */
function renderEpisodes(seasonId) {
  episodesContainer.innerHTML = "";

  const season = seasonsData.find(s => s.id === seasonId);
  let activeEl = null;

  season.episodes.forEach(ep => {
    const box = document.createElement("div");
    box.className = "episode-box";
    box.textContent = ep.number;

    if (ep.id === currentEpisodeId) {
      box.classList.add("active");
      activeEl = box;
    }

    if (ep.id !== currentEpisodeId) {
  box.onclick = () => loadEpisode(seasonId, ep, true);
} else {
  box.classList.add("disabled");
}
    episodesContainer.appendChild(box);
  });

  if (activeEl) {
    requestAnimationFrame(() => {
      activeEl.scrollIntoView({
        behavior: "smooth",
        inline: "center",
        block: "nearest"
      });
    });
  }
}

function updateEpisodesCounter(seasonId) {
  const season = seasonsData.find(s => s.id === seasonId);
  if (!season) return;

  const total = season.episodes.filter(ep => ep.src).length;

  episodesCounter.textContent =
    total === 1 ? "1 Capítulo" : `${total} Capítulos`;
}

/* =====================================================
   MODAL
===================================================== */
btnOpenSeasons.onclick = () => modal.classList.remove("hidden");
btnCloseModal.onclick = () => modal.classList.add("hidden");

/* =====================================================
   INIT
===================================================== */
const last = loadCurrentEpisode();

if (last.seasonId && last.episodeId) {
  const season = seasonsData.find(s => s.id === last.seasonId);
  const episode = season?.episodes.find(e => e.id === last.episodeId);

  if (season && episode && episode.src) {
    currentSeasonId = season.id;
    currentEpisodeId = episode.id;
    seasonEpisodeState[season.id] = episode.id;

    loadEpisode(season.id, episode, false);
  } else {
    loadEpisode(
      seasonsData[0].id,
      seasonsData[0].episodes[0],
      false
    );
  }
} else {
  loadEpisode(
    seasonsData[0].id,
    seasonsData[0].episodes[0],
    false
  );
}

// ✅ renderizar DESPUÉS de restaurar el estado
renderSeasons();



});
</script>



  <!-- FIN -->
  
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
document.addEventListener("DOMContentLoaded", function () {

  function mostrarModalFavoritos(mensaje){

    const modal = document.getElementById('modal-favoritos');
    const texto = document.getElementById('modal-fav-texto');
    const btnAceptar = document.getElementById('modal-fav-aceptar');

    if(!modal) return;

    texto.textContent = mensaje;
    modal.setAttribute('aria-hidden','false');

    function cerrar(){
      modal.setAttribute('aria-hidden','true');
    }

    btnAceptar.onclick = cerrar;
    modal.querySelector('.modal-fav-backdrop').onclick = cerrar;
  }


  const btn = document.getElementById("btn-favorito");

  if(!btn){
    console.error("Botón favoritos no encontrado");
    return;
  }

  btn.addEventListener("click",function(){

    const titulo = "Moisés y los Diez Mandamientos";
    const movie_id = "moises_y_los_diez_mandamientos";
    const imagen = "https://image.tmdb.org/t/p/w300/spMIIipBp3sz24zIG1oXgGFfcNZ.jpg";
    const tipo = "serie"; // 🔹 SOLUCIÓN

    btn.classList.add("animado");
    setTimeout(()=>btn.classList.remove("animado"),300);


    fetch("../View Peliculas/guardar_favorito.php",{

      method:"POST",

      headers:{
        "Content-Type":"application/x-www-form-urlencoded"
      },

      body:
        "movie_id="+encodeURIComponent(movie_id)+
        "&titulo="+encodeURIComponent(titulo)+
        "&imagen="+encodeURIComponent(imagen)+
        "&tipo="+encodeURIComponent(tipo)

    })

    .then(res => res.json())

    .then(data => {

      console.log(data);

      if(data.status === "success"){

        mostrarModalFavoritos(" Serie agregada a favoritos");

        setTimeout(()=>{
          window.location.href="../View Peliculas/favoritos.php";
        },1500);

      }

      else if(data.status === "exists"){

        mostrarModalFavoritos("⚠️ Esta serie ya está en favoritos");

      }

      else{

        mostrarModalFavoritos("Debes iniciar sesión");

      }

    })

    .catch(error=>{
      console.error(error);
      mostrarModalFavoritos("Error al guardar favorito");
    });

  });

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
        <p></p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p></p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p></p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p></p>
      </a>
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p></p>
      </a>
      
      <a href="" class="serie">
        <img loading="lazy" src="https://image.tmdb.org/t/p/w300/" alt="">
        <p></p>
      </a>
    </div>
  </div>
  
  <!-- ¡¡¡NO BORRAR ESTE SCRIPT ES DE FAVORITOS Y SI LO BORRAN AFECTARAN EL FUNCIONAMIENTO DE REPRODUCTOR Y FAVORITOS!!! -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/plyr/3.6.7/plyr.min.js"></script>
  <script>
    document.getElementById('btn-favorito').addEventListener('click', () => {

      let favoritos = JSON.parse(localStorage.getItem('favoritos_detalles') || '[]');
      if (!favoritos.some(f => f.titulo === nuevaPelicula.titulo)) {
        favoritos.push(nuevaPelicula);
        localStorage.setItem('favoritos_detalles', JSON.stringify(favoritos));
      }
      window.location.href = "../View Peliculas/favoritos.html";
    });
  </script>
  
  <script>
    const videoElement = document.querySelector('.vid1');

    const player = new Plyr(videoElement, {
      controls: [
        'rewind', 'play-large', 'play', 'fast-forward',
        'progress', 'current-time', 'duration', 'mute', 'volume',
        'captions', 'airplay', 'fullscreen'
      ]
    });

    // ✅ Reanudar episodio al presionar Play


    document.addEventListener('DOMContentLoaded', () => {
      // ❌ Evita reanudar desde el tiempo guardado
  // if (savedTime && parseFloat(savedTime) > 10) {
  //   const msg = document.getElementById('resume-msg');
  //   msg.classList.add('visible');
  //   setTimeout(() => msg.classList.remove('visible'), 5000);
  // }

      const controls = document.querySelector('.plyr__controls');
  if (controls && !document.getElementById('custom-rewind')) {
    const rewindBtn = document.createElement('button');
    rewindBtn.id = 'custom-rewind';
    rewindBtn.className = 'plyr__control';
    rewindBtn.setAttribute('aria-label', 'Retroceder 10 segundos');
    rewindBtn.innerHTML = '<i class="fas fa-undo"></i>';
    rewindBtn.onclick = () => {
      videoElement.currentTime = 0;
    };
    controls.insertBefore(rewindBtn, controls.firstChild);
  }
});
    
    setInterval(() => {
      if (!videoElement.paused && !videoElement.ended) {
      }
    }, 5000);
    
    /* ACA VA LA NOMBRE, IMAGEN, GENERO, ARCHIVO DONDE GUARDAN SU HTML DE PELICULA Y EL TIEMPO */
    document.getElementById('btn-favorito').addEventListener('click', () => {

      /* animacion de favoritos */
      const btn = document.getElementById('btn-favorito');
      btn.classList.add('animado');
      setTimeout(() => btn.classList.remove('animado'), 300);

      let favoritos = JSON.parse(localStorage.getItem('favoritos_detalles') || '[]');
      if (!favoritos.some(f => f.titulo === nuevaPelicula.titulo)) {
  favoritos.push(nuevaPelicula);
  localStorage.setItem('favoritos_detalles', JSON.stringify(favoritos));
  window.location.href = "../View Peliculas/favoritos.html";
} else {
  mostrarModalFavoritoExistente();
}

    });
  </script>

<!--Recie agregado.-->
<script>
  if (window.player) {
  player.on('play', () => {
    const source = video.querySelector("source");

    if (
      currentEpisodeSrc &&
      (!source.getAttribute("src") || source.getAttribute("src") === "")
    ) {
      source.setAttribute("src", currentEpisodeSrc);
      video.load();
      video.play();
    }
  });
}

</script>



<script>
video.addEventListener("ended", () => {

  if (currentEpisodeId) {
    clearResumeTime(currentEpisodeId); // 🧹 borrar reanudación
  }

  const activeEpisode = document.querySelector(".episode-box.active");
  if (!activeEpisode) return;

  const nextEpisode = activeEpisode.nextElementSibling;
  if (!nextEpisode) return;

  nextEpisode.click();
});
</script>

<!--Fi-->


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
  
  

<script>
function esperarFinLoader(callback) {
  const loader = document.getElementById("loader-screen");
  if (!loader) {
    callback();
    return;
  }

  const check = setInterval(() => {
    const style = getComputedStyle(loader);

    if (
      loader.classList.contains("hidden") ||
      style.display === "none" ||
      style.visibility === "hidden" ||
      style.opacity === "0"
    ) {
      clearInterval(check);
      callback();
    }
  }, 100);
}
</script>

<script>

/* =========================
   DATOS DE LA SERIE
========================= */
const titulo = "Moisés y los Diez Mandamientos";
  const movieId = "moises_y_los_diez_mandamientos";
  const tipo = "serie";
  const archivo = "../View Series/Moises y los diez mandamientos (2015).php";
  const imagen = "https://image.tmdb.org/t/p/w300/spMIIipBp3sz24zIG1oXgGFfcNZ.jpg";

/* =========================
   MODAL
========================= */
function mostrarModal(mensaje){

  const modal = document.getElementById("modal-favoritos");
  const texto = document.getElementById("modal-fav-texto");
  const boton = document.getElementById("modal-fav-aceptar");

  if(!modal || !texto){
    alert(mensaje);
    return;
  }

  texto.textContent = mensaje;
  modal.setAttribute("aria-hidden","false");

  boton.onclick = () => {
    modal.setAttribute("aria-hidden","true");
  };

  setTimeout(()=>{
    modal.setAttribute("aria-hidden","true");
  },2000);
}

/* =========================
   GUARDAR HISTORIAL
========================= */
function guardarHistorial(){

  fetch("../View Peliculas/guardar_historial.php",{
    method:"POST",
    headers:{
      "Content-Type":"application/x-www-form-urlencoded"
    },
    body:
      "movie_id="+encodeURIComponent(movieId)+
      "&titulo="+encodeURIComponent(titulo)+
      "&tipo="+encodeURIComponent(tipo)+
      "&imagen="+encodeURIComponent(imagen)+
      "&progreso="+encodeURIComponent("Asistido 0 min")+
      "&archivo="+encodeURIComponent(archivo)
  })
  .then(res=>res.json())
  .then(data=>{
    console.log("Historial:",data);

    if(data.status === "new"){
      mostrarModal("Serie agregada al historial");
    }
  })
  .catch(error=>{
    console.error("Error historial:",error);
  });
}

/* =========================
   CUANDO TERMINA EL LOADER
========================= */
window.addEventListener('load', () => {

  setTimeout(() => {

    const loader = document.getElementById('loader-screen');
    if(loader) loader.classList.add('hidden');

    // 🔥 AHORA SÍ: después del loader
    guardarHistorial();

  }, 200);

});

/* =========================
   CUANDO EL DOM ESTÁ LISTO
========================= */
document.addEventListener("DOMContentLoaded", function () {

  const videoElement = document.getElementById("videoPlayer");

  if (!videoElement) {
    console.error("No se encontró el videoPlayer");
    return;
  }

  /* =========================
     ACTUALIZAR PROGRESO
  ========================= */
  setInterval(()=>{

    if(!videoElement.paused && !videoElement.ended){

      const minutos = Math.floor(videoElement.currentTime / 60);
      const progreso = "Asistido "+minutos+" min";

      fetch("../View Peliculas/guardar_historial.php",{
        method:"POST",
        headers:{
          "Content-Type":"application/x-www-form-urlencoded"
        },
        body:
          "movie_id="+encodeURIComponent(movieId)+
          "&titulo="+encodeURIComponent(titulo)+
          "&tipo="+encodeURIComponent(tipo)+
          "&imagen="+encodeURIComponent(imagen)+
          "&progreso="+encodeURIComponent(progreso)
      })
      .catch(error=>{
        console.error("Error actualizando progreso:",error);
      });

    }

  },10000);

});

</script>

  
</body>
</html>