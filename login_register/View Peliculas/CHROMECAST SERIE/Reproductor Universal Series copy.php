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
  background:
    radial-gradient(circle at 30% 20%, rgba(255,0,120,0.15), transparent 40%),
    radial-gradient(circle at 70% 80%, rgba(0,170,255,0.15), transparent 40%),
    #000;
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

.loader-content {
  text-align: center;
  animation: fadeUp 0.8s ease;
}

@keyframes fadeUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.loader-circle {
  position: relative;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 25px;
}

/* 🔥 ARO GIRATORIO */
.loader-circle::before {
  content: "";
  position: absolute;
  inset: -6px;
  border-radius: 50%;
  background: conic-gradient(
    #00aaff,
    #00ffcc,
    #ff00aa,
    #ff3c3c,
    #00aaff
  );
  animation: spin 2s linear infinite;
  z-index: 0;
  filter: blur(2px);
}

/* 🔥 BORDE INTERNO NEGRO (para efecto limpio) */
.loader-circle::after {
  content: "";
  position: absolute;
  inset: 4px;
  border-radius: 50%;
  background: #000;
  z-index: 1;
}

/* 🔥 IMAGEN CENTRADA (NO GIRA) */
.loader-logo {
  width: 100px;
  z-index: 2;
  position: relative;
  animation: pulse 2.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.08); }
}

/* 🔄 ROTACIÓN SOLO DEL ARO */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loader-circle::before {
  content: "";
  position: absolute;
  inset: -6px;
  border-radius: 50%;
  background: conic-gradient(
    #00aaff,
    #00ffcc,
    #ff00aa,
    #ff3c3c,
    #ffaa00,
    #00aaff
  );
  animation: spin 2s linear infinite;
  z-index: 0;
  filter: blur(3px);
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.loader-title {
  font-size: 2.6rem;
  font-weight: 800;
  letter-spacing: 3px;

  background: linear-gradient(
    90deg,
    #ff0000,
    #ff9900,
    #ffee00,
    #00ff99,
    #00aaff,
    #7a00ff,
    #ff00aa,
    #ff0000
  );

  background-size: 300%;

  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;

  animation: rainbowMove 6s linear infinite;

  /* 🔥 glow suave */
  text-shadow:
    0 0 8px rgba(255,255,255,0.1),
    0 0 15px rgba(255,0,120,0.2);
}
@keyframes rainbowMove {
  0% {
    background-position: 0%;
  }
  100% {
    background-position: 300%;
  }
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
  position: relative;
  overflow: hidden;
}

/* brillo que se mueve */
.loading-fill::after {
  content: "";
  position: absolute;
  top: 0;
  left: -50%;
  width: 50%;
  height: 100%;
  background: linear-gradient(120deg, transparent, rgba(255,255,255,0.5), transparent);
  animation: shine 1.5s infinite;
}

@keyframes shine {
  0% { left: -50%; }
  100% { left: 120%; }
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

/* 🔥 CAPA DE COLOR ANIMADA */
#progressBar::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  height: 100%;
  width: var(--progress, 0%);
  border-radius: 999px;

  background: linear-gradient(
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

  background-size: 400% 100%;
  animation: rainbowMove 6s linear infinite;

  box-shadow: 0 0 10px rgba(255,255,255,0.3);
}

/* 🎞️ ANIMACIÓN */
@keyframes rainbowMove {
  0% { background-position: 0% 50%; }
  100% { background-position: 200% 50%; }
}

#progressBar {
  position: relative;
  z-index: 1;
}

#progressBar::before {
  z-index: 0;
}

#progressBar::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: white;
  border: 3px solid #00cfff;

  /* 🔥 brillo fuerte */
  box-shadow: 
    0 0 10px rgba(0,255,255,0.9),
    0 0 20px rgba(0,255,255,0.6);

  margin-top: -4px;
 /* 🔥 centrado perfecto */
  position: relative;
  z-index: 3;
}

/* Firefox */
#progressBar::-moz-range-thumb {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: white;
  border: 3px solid #00cfff;
}
#progressBar {
  overflow: visible;
}

@media (max-width: 768px) {
  #progressBar::-webkit-slider-thumb {
    margin-top: -3px; /* ajuste fino mobile */
  }
}

</style>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const loader = document.getElementById('loader-screen');
  const bar = document.getElementById('loading-fill');
  const percent = document.getElementById('loading-percent');

  // 🔒 Si no existe el loader, no rompe nada
  if (!loader || !bar || !percent) return;

  let progreso = 0;
  let terminado = false;

  // 🔥 Animación controlada
  const anim = setInterval(() => {
    if (progreso < 90) {
      progreso += 1.5; // más suave
      actualizar();
    }
  }, 60);

  function actualizar() {
    if (!bar || !percent) return;

    progreso = Math.min(progreso, 100);
    bar.style.width = progreso + "%";
    percent.textContent = Math.floor(progreso) + "%";
  }

  function finalizar() {
    if (terminado) return;
    terminado = true;

    clearInterval(anim);

    // 🔥 subida final limpia
    const finalAnim = setInterval(() => {
      if (progreso < 100) {
        progreso += 2;
        actualizar();
      } else {
        clearInterval(finalAnim);

        setTimeout(() => {
          loader.classList.add("hidden");
        }, 300);
      }
    }, 20);
  }

  // ✅ SOLO cuando todo cargó
  window.addEventListener("load", () => {
    setTimeout(finalizar, 200);
  });

  // ✅ fallback seguro (por si algo falla)
  setTimeout(() => {
    finalizar();
  }, 3500);

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
  from {transform: scale(.9); opacity:0;}
  to {transform: scale(1); opacity:1;}
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

<script>
document.addEventListener('DOMContentLoaded', () => {

  const video = document.getElementById('videoPlayer');
  if (!video) return;

  // 🔥 OBTENER movie correctamente (igual que tu otro script)
  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");
  const movie = typeof MOVIES_DB !== "undefined" ? MOVIES_DB[movieId] : null;

  if (!movie) {
    console.log("Serie no encontrada");
    return;
  }

  const titulo = movie.titulo || "";
  const imgserie = movie.imgserie || "";

  function guardarProgreso() {

    const progreso = Math.floor(video.currentTime);

    fetch("inicio_serie.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "id=" + encodeURIComponent(movieId) +
        "&titulo=" + encodeURIComponent(titulo) +
        "&imgserie=" + encodeURIComponent(imgserie) +
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

<!-- 
========================
TEMPORADAS Y EPISODIOS
========================
-->

<style>

/* =========================
   HEADER
========================= */
.series-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
}

.left-header {
  display: flex;
  align-items: center;
}

/* BOTÓN TEMPORADAS */
#btn-open-seasons {
  display: flex;
  align-items: center;
  gap: 6px;

  background: linear-gradient(135deg,#ff2d55,#ff5e7e);
  border: none;
  color: #fff;

  padding: 8px 16px;
  border-radius: 999px;

  font-size: 13px;
  font-weight: 600;

  cursor: pointer;
  transition: transform 0.15s ease;
}

#btn-open-seasons:active {
  transform: scale(0.95);
}

.arrow {
  font-size: 10px;
  opacity: 0.8;
}

/* CONTADOR */
.episodes-counter {
  font-size: 0.8rem;
  font-weight: 600;
  color: #9ad0ff;
}

/* =========================
   SCROLL EPISODIOS
========================= */
.episodes-scroll {
  display: flex;
  flex-direction: row;     /* 🔥 asegura horizontal */
  flex-wrap: nowrap;       /* 🔥 NUNCA baja de línea */

  gap: 8px;
  padding: 8px 10px;

  overflow-x: auto;
  overflow-y: hidden;

  scroll-snap-type: x mandatory;
  -webkit-overflow-scrolling: touch;

  align-items: center;     /* 🔥 centra verticalmente */

  white-space: nowrap;     /* 🔥 extra fix para algunos Android */

  scroll-padding-left: 10px;
}

/* =========================
   EPISODIOS (CUADRADOS)
========================= */
.episode-box {
  flex: 0 0 auto;

  width: 48px;
  min-width: 40px;
  max-width: 60px;

  aspect-ratio: 1 / 1;

  background: linear-gradient(145deg,#1a1a1a,#111);
  border: 1px solid #2a2a2a;
  border-radius: 10px;

  display: flex;
  align-items: center;
  justify-content: center;

  font-size: 0.8rem;
  font-weight: 700;
  color: #fff;

  cursor: pointer;

  scroll-snap-align: center;

  transition: transform .15s ease, box-shadow .2s ease, background .2s ease;
}

/* TOUCH */
.episode-box:active {
  transform: scale(0.92);
}

/* HOVER PC */
@media (hover:hover) {
  .episode-box:hover {
    transform: scale(1.1);
    background: #222;
  }
}

/* ACTIVO */
.episode-box.active {
  border-color: #00c6ff;
  box-shadow: 0 0 10px rgba(0,198,255,.7);
  transform: scale(1.08);
}

/* VIENDO */
.episode-box.watching {
  border-color: #ff2d55;
  box-shadow: 0 0 10px rgba(255,45,91,.6);
}

/* =========================
   SCROLLBAR
========================= */
.episodes-scroll::-webkit-scrollbar {
  height: 3px;
}

.episodes-scroll::-webkit-scrollbar-thumb {
  background: #444;
  border-radius: 10px;
}

/* =========================
   MODAL
========================= */
.season-modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.75);
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
  max-width: 340px;
  border-radius: 14px;
  padding: 20px;
  position: relative;

  box-shadow: 0 0 25px rgba(255,45,91,.4);
}

.season-close {
  position: absolute;
  top: 8px;
  right: 12px;
  background: none;
  border: none;
  color: #fff;
  font-size: 18px;
  cursor: pointer;
}

/* TEMPORADAS */
/* =========================
   MODAL MEJORADO
========================= */
.season-box h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
  color: #fff;
}

/* LISTA */
.season-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 15px;
}

/* BOTONES */
.season-item {
  padding: 12px;
  border-radius: 12px;

  background: #1a1a1a;
  border: 1px solid #2a2a2a;

  color: #fff;
  font-size: 14px;
  font-weight: 500;

  cursor: pointer;

  transition: all 0.2s ease;
}

/* HOVER PC */
@media (hover:hover) {
  .season-item:hover {
    background: #222;
    transform: scale(1.03);
  }
}

/* ACTIVA */
.season-item.active {
  background: linear-gradient(135deg,#ff2d55,#ff5e7e);
  border: none;
  box-shadow: 0 0 15px rgba(255,45,91,.6);
}

/* CLICK */
.season-item:active {
  transform: scale(0.96);
}

/* =========================
   RESPONSIVE
========================= */

/* Android chicos */
@media (max-width: 400px) {
  .episode-box {
    width: 40px;
    font-size: 0.7rem;
  }
}

/* móviles normales */
@media (min-width: 401px) and (max-width: 768px) {
  .episode-box {
    width: 44px;
  }
}

/* PC */
@media (min-width: 1024px) {
  .episode-box {
    width: 55px;
  }
}

/* pantallas grandes */
@media (min-width: 1400px) {
  .episode-box {
    width: 60px;
  }
}

/* Android grandes */
@media (hover:none) and (min-width: 600px) {
  .episode-box {
    width: 46px;
  }
}
.episode-box.viendo {
  pointer-events: none;
  opacity: 0.7;
  cursor: default;
}
</style>


<!-- ========================
     STYLE DE CHROMECAST
    =========================
-->
<style>
  .cast-btn {
  background: transparent;   /* ❌ elimina el cuadrado blanco */
  border: none;
  outline: none;
  cursor: pointer;

  display: flex;
  align-items: center;
  justify-content: center;

  width: 40px;
  height: 40px;

  border-radius: 50%;
  transition: all 0.2s ease;
}

.cast-btn svg {
  width: 22px;
  height: 22px;
  fill: #aaa; /* gris suave */
  transition: 0.2s;
}

/* hover */
.cast-btn:hover svg {
  fill: #fff;
}

/* activo (cuando casteas) */
.cast-btn.casting svg {
  fill: #00c3ff; /* azul estilo Chromecast */
}

</style>

<script>
  const btnOpen = document.getElementById("btn-open-seasons");

btnOpen.onclick = () => {
  modal.classList.remove("hidden");
  btnOpen.classList.add("active"); // 🔥 gira flecha
};

btnClose.onclick = () => {
  modal.classList.add("hidden");
  btnOpen.classList.remove("active");
};

modal.onclick = (e) => {
  if (e.target === modal) {
    modal.classList.add("hidden");
    btnOpen.classList.remove("active");
  }
};

</script>

<!-- 
========================
FIN
========================
-->

<script src="https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1"></script>

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
        <button id="btnCast" class="cast-btn">
  <svg viewBox="0 0 24 24">
    <path d="M1,18V21H4C4,19.34 2.66,18 1,18M1,14V17C3.76,17 6,19.24 6,22H9C9,17.58 5.42,14 1,14M1,10V13C6.52,13 11,17.48 11,23H14C14,15.82 8.18,10 1,10M21,3H3C1.89,3 1,3.89 1,5V8H3V5H21V19H14V21H21C22.11,21 23,20.11 23,19V5C23,3.89 22.11,3 21,3Z"/>
  </svg>
</button>
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

  <span class="genero-badge">
    <span id="genero-texto"></span>
  </span>

  <!-- 🔥 HEADER NUEVO -->
  <div class="series-header">

  <button id="btn-open-seasons" class="btn-seasons">
    <span id="season-name">Temporadas</span>
    <span class="arrow">▼</span>
  </button>

  <div id="episodes-counter" class="episodes-counter">
    0 Capítulos
  </div>

</div>

<!-- EPISODIOS -->
<div id="episodes-container" class="episodes-scroll"></div>

<!-- MODAL -->
<div id="season-modal" class="season-modal hidden">
  <div class="season-box">
    <button class="season-close">✖</button>
    <h3>Seleccionar temporada</h3>
    <div id="season-list" class="season-list"></div>
  </div>
</div>

  <p class="sinopsis">
    <!--Sinopsis-->
  </p>

  <div class="ficha-tecnica" style="text-align:center;margin-top:20px;font-size:0.9rem;color:#ccc;">
    <p><strong>Director:</strong></p>
    <p><strong>Reparto:</strong></p>
    <p><strong>Estreno:</strong> | <strong>Idioma:</strong></p>
  </div>

  <br>

  <div class="acciones">
    <button id="btn-favorito">⭐ Agregar a Favoritos</button>
  </div>

</div>

<!-- MODAL CAST -->
<div id="castModal" class="cast-modal hidden">
  <div class="cast-box">
    <h3>Transmitir a dispositivo</h3>

    <p class="cast-status">Buscando dispositivos...</p>

    <button id="startCastBtn" class="cast-start">Buscar dispositivos</button>
    <button id="stopCastBtn" class="cast-stop hidden">Detener transmisión</button>

    <button id="closeCastModal" class="cast-close">Cancelar</button>
  </div>
</div>

<style>
  .cast-modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.cast-modal.hidden {
  display: none;
}

.cast-box {
  background: #111;
  padding: 20px;
  border-radius: 12px;
  width: 90%;
  max-width: 320px;
  text-align: center;
  color: #fff;
}

.cast-box h3 {
  margin-bottom: 10px;
}

.cast-status {
  font-size: 14px;
  color: #aaa;
  margin-bottom: 15px;
}

.cast-start {
  width: 100%;
  padding: 10px;
  background: #e50914;
  border: none;
  border-radius: 6px;
  color: white;
  margin-bottom: 10px;
  cursor: pointer;
}

.cast-close {
  width: 100%;
  padding: 8px;
  background: transparent;
  border: 1px solid #555;
  border-radius: 6px;
  color: #ccc;
  cursor: pointer;
}

.cast-stop {
  width: 100%;
  padding: 10px;
  background: #444;
  border: none;
  border-radius: 6px;
  color: white;
  margin-bottom: 10px;
  cursor: pointer;
  transition: 0.3s;
}

.cast-stop:hover {
  background: #666;
}


</style>

  

  <script>
const MOVIES_DB = {
  
  /* A */

  avenida_brasil: {
    id: "avenida_brasil",
    titulo: "Avenida Brasil",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/5ssgDp28z1Nif6hR8OFrNQUspd4.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/jgd86jJQGAl1GYThvd8oHLIy5AG.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/jERSCpL8bGDJSmXGY85XyiSl6PK.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Esta es la dramática historia de Rita, que lucha por recuperar parte de la vida que su terrible madrastra, Carmina, le robó cuando todavía era solo una niña. Pero ella tendrá que enfrentarse a su pasado y decidir hasta dónde está dispuesta a llegar para vengarse de los que más le hicieron daño.",
    anio: "2012",
    duracion: "51min",
    calificacion: "97%",
    genero: "Drama • Misterio • Crimen",
    director: "João Emanuel Carneiro",
    reparto: "Débora Falabella, Adriana Esteves, Murilo Benício",
    estreno: "28/03/2012",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2012,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/yzecvh6g06fdm6sxmk4lw/Avenidad-Brasil-1080p-Capitulo-01.mp4?rlkey=k910lbvco10en9hybd2j7azhr&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/nndg4k8ca8lgszz9j9im9/Avenidad-Brasil-1080p-Capitulo-02.mp4?rlkey=hxrng86rnati7bdmieurorw8z&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/4z4hywwa2qou7bw6cz73j/Avenidad-Brasil-1080p-Capitulo-03.mp4?rlkey=m4nv2au0uvm4wxni40w0o31ya&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/zky1lsadvk71gg4x1icnh/Avenidad-Brasil-1080p-Capitulo-04.mp4?rlkey=azus6ridnribslceatxvcd9va&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/nb4hhc7lgvwsserqhjs8l/Avenidad-Brasil-1080p-Capitulo-05.mp4?rlkey=x8k0lk635prkenkaxntwgl5o2&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/sk6ojgux4ublgxqkqt3e3/Avenidad-Brasil-1080p-Capitulo-06.mp4?rlkey=950jtxl42h25gjxtewf6rr7xi&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/2pross26f5sjyiat0gb31/Avenidad-Brasil-1080p-Capitulo-07.mp4?rlkey=ca948wmf7yn2bsef9f14mqohn&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/80q6vzq866gn9yh39jm2c/Avenidad-Brasil-1080p-Capitulo-08.mp4?rlkey=jyfkd2pc4nce4h6gtqfxfyqd2&st=" },
          { id: "t1e9", number: 9, src: "https://dl.dropbox.com/scl/fi/rz8mj7rh1zu4jvd6wvpiz/Avenidad-Brasil-1080p-Capitulo-09.mp4?rlkey=jq4m2oxzji650detbfo11f1v2&st=" },
          { id: "t1e10", number: 10, src: "https://dl.dropbox.com/scl/fi/uet3nmuhdij50s8y5i3re/Avenidad-Brasil-1080p-Capitulo-10.mp4?rlkey=9699p5g53i7u5c45opil3s3dm&st=" },
          { id: "t1e11", number: 11, src: "https://dl.dropbox.com/scl/fi/31jgtbp6ty307tucfd9sv/Avenidad-Brasil-1080p-Capitulo-11.mp4?rlkey=rigo1ft1aqvqnwmoho7407g6c&st=" },
          { id: "t1e12", number: 12, src: "https://dl.dropbox.com/scl/fi/7l2s27aciduus9wtgfoz7/Avenidad-Brasil-1080p-Capitulo-12.mp4?rlkey=n6yxlfrs2xkjss23qlnngp3gs&st=" },
          { id: "t1e13", number: 13, src: "https://dl.dropbox.com/scl/fi/ezud5pk434f0wq4gr3uy7/Avenidad-Brasil-1080p-Capitulo-13.mp4?rlkey=v0srumlxkzazdzs8ivh4cmnzf&st=" },
          { id: "t1e14", number: 14, src: "https://dl.dropbox.com/scl/fi/dxcm67erb9vzsd7e4l4o2/Avenidad-Brasil-1080p-Capitulo-14.mp4?rlkey=n1vbvrk7xmqh0z2npaamu70f2&st=" },
          { id: "t1e15", number: 15, src: "https://dl.dropbox.com/scl/fi/l4pkra8uniqkcqwn6a7ul/Avenidad-Brasil-1080p-Capitulo-15.mp4?rlkey=eql2aag5c1b3owbj3fi0p98rw&st=" },
          { id: "t1e16", number: 16, src: "https://dl.dropbox.com/scl/fi/vt2cgm0nrt21ii8r5122z/Avenidad-Brasil-1080p-Capitulo-16.mp4?rlkey=kqicf3c3p9juxdtf1htoxbs89&st=" },
          { id: "t1e17", number: 17, src: "https://dl.dropbox.com/scl/fi/yigrk15scrzyf064qvqpi/Avenidad-Brasil-1080p-Capitulo-17.mp4?rlkey=6al4tgl3fb5npsbydku2ya85e&st=" }
        ]
      }
    ],

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
        href: "",
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

  nombre_de_serie: {
    id: "nombre_de_serie",
    titulo: "",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        href: "",
        titulo: "",
        imagen: ""
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
  


  /* B */

  baki_dou_el_samurai_invencible: {
    id: "baki_dou_el_samurai_invencible",
    titulo: "Baki-Dou: El samurái invencible",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/1M8uW8o6fdDaa3zqmBD2UUKu0VZ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vIbiGAJR69775GHFlYlPFG4GSpb.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/vKT9vs8p8TC2bIMDVhpKhn1xaw5.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Baki y los luchadores más fuertes del Estadio clandestino se enfrentan a una amenaza de proporciones históricas: el resucitado Musashi Miyamoto, el samurái más famoso de Japón.",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: "Acciónn • Anime • Animación • Fantasía",
    director: "",
    reparto: "Nobunaga Shimazaki, Naoya Uchida, Akio Otsuka",
    estreno: "26/02/2026",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/8byf64ikzur5psubjxthp/Baki-Dou_El_samur-i_invencible_Latino_01.mp4?rlkey=x9w7a0b4uu8dahbeghhhv8smk&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/0g33klcwcsza6nngmgpzb/Baki-Dou-El-samur-i-invencible-Latino-02.mp4?rlkey=50a1vqia9brfz5kqfdfsqpa1l&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/f861vz5em9kuxuqfre2j6/Baki-Dou_El_samur-i_invencible_Latino_03.mp4?rlkey=xj1lsxw8txr2ft9vbcpow7biw&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/qy651anpt00b6ghzbxfz8/Baki-Dou_El_samur-i_invencible_Latino_04.mp4?rlkey=jct6hg31sib1c8pwnroh37507&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/y63zh1gjje0t34aiew3qu/Baki-Dou_El_samur-i_invencible_Latino_05.mp4?rlkey=24wyi9u88fge4i71bzcm87cyx&st=" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "baki_2018",
        titulo: "Baki",
        imagen: "https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/x145FSI9xJ6UbkxfabUsY2SFbu3.jpg"
      },
      {
        href: "Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma VS Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg"
      },
      {
        id: "",
        titulo: "Dragon Ball Z",
        imagen: "https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"
      },
      {
        id: "",
        titulo: "Hajime no ippo",
        imagen: "https://image.tmdb.org/t/p/w300/i3U3J2MWovIBZBnZYYiOLBXqNJZ.jpg"
      },
      {
        id: "",
        titulo: "Naruto Shippuden",
        imagen: "https://image.tmdb.org/t/p/w300/3V7kzJX7hvF0H9CDJsgcWKXTSsR.jpg"
      }
    ]
  },

  blue_lock_2022: {
    id: "blue_lock_2022",
    titulo: "Blue Lock",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/d8cPdOdjeZXIrYndN7azFscMEgJ.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/1DFhWgHKzzlzAvrmK8ZzLx4XcTY.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/x6OSrIgRRueeTQ63PoZfUgwpceK.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Anime de fútbol extremo donde los delanteros compiten para ser el mejor del mundo.",
    anio: "2022",
    duracion: "Serie",
    calificacion: "82%",
    genero: "Acción • Deportes",
    director: "Luis Daniel Garza",
    reparto: "Armando Guerrero, Diego Becerril, Lalo Garza",
    estreno: "2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2022,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/cmtqvydawmzvu7c25uu7h/AnimeOnlineNinja-_Blue_Lock_BD_Latino_01.mp4?rlkey=9ffnbkh4ri6bz26fccwxyk8ib&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/j4npboobqyt7molvia54d/AnimeOnlineNinja-_Blue_Lock_BD_Latino_02.mp4?rlkey=69czpk8tu8ll2jkawz48c1yes&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/do7h4u1r7gibbrtzbz15h/Blue_Lock_BD_Latino_03.mp4?rlkey=n3xnk3voitrb89l1o76yeip6q&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/vhgvl1zfu53gfbp12f7jm/AnimeOnlineNinja-_Blue_Lock_BD_Latino_04.mp4?rlkey=jw79n9nj2syo1f53bzh2dxbwx&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/kvno70f9zhnnldfcicx85/AnimeOnlineNinja-_Blue_Lock_BD_Latino_05.mp4?rlkey=mjo3s6jw0q9yc4wxvohaehxs3&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/mlfe4a5l2w4erkxna4vhn/AnimeOnlineNinja-Blue-Lock-BD-Latino-06.mp4?rlkey=8eluqunzmam15p7xr5bb03jou&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/c1orx55bwpdqgzy3nqyy0/AnimeOnlineNinja-_Blue_Lock_BD_Latino_07.mp4?rlkey=b0i87a1xxam7jpa2p9k9dmmn8&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/3mku33wx8hsws69656enq/AnimeOnlineNinja-Blue-Lock-BD-Latino-08.mp4?rlkey=rpycqhga0d60aai2gzgl709st&st=" },
          { id: "t1e9", number: 9, src: "https://dl.dropbox.com/scl/fi/ipb74iprstv3s9izui83j/AnimeOnlineNinja-Blue-Lock-BD-Latino-09.mp4?rlkey=1ua0ojlu40b65p6bqrq03bs8b&st=" },
          { id: "t1e10", number: 10, src: "https://dl.dropbox.com/scl/fi/4pxro99k99g580l50b69s/AnimeOnlineNinja-Blue-Lock-BD-Latino-10.mp4?rlkey=zgpe18e6v5jicttubbk34hkir&st=" },
          { id: "t1e11", number: 11, src: "https://dl.dropbox.com/scl/fi/8qfkaommz55kbsulwcqfo/AnimeOnlineNinja-Blue-Lock-BD-Latino-11.mp4?rlkey=n6fll024xnvaidiv0cvua88ah&st=" },
          { id: "t1e12", number: 12, src: "https://dl.dropbox.com/scl/fi/6862ho1agi44gzhqhxvk3/AnimeOnlineNinja-Blue-Lock-BD-Latino-12.mp4?rlkey=r8whvrj6ue61nitue5jj9g2q5&st=" },
          { id: "t1e13", number: 13, src: "https://dl.dropbox.com/scl/fi/y3dnp7zfh58ij4ieo4nmv/AnimeOnlineNinja-Blue-Lock-BD-Latino-13.mp4?rlkey=83un1m5ab59va8jof080vzsbu&st=" },
          { id: "t1e14", number: 14, src: "https://playmogo.com/e/xftw296rv7o0" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "Blue Lock: La película - episodio Nagi",
        imagen: "https://image.tmdb.org/t/p/w300/tT7wk65X4czBIxDjoPRMNw5JhKD.jpg"
      },
      {
        id: "",
        titulo: "Capitan Tsubasa",
        imagen: "https://image.tmdb.org/t/p/w300/zHgc9nTXiP77qoy14BO7WUFTwkp.jpg"
      },
      {
        href: "",
        titulo: "Hajime no ippo",
        imagen: "https://image.tmdb.org/t/p/w300/i3U3J2MWovIBZBnZYYiOLBXqNJZ.jpg"
      },
      {
        id: "",
        titulo: "Dragon Ball Gt",
        imagen: "https://image.tmdb.org/t/p/w300/pLYjbFYHOX1SrHs5BQsGlmv83lZ.jpg"
      },
      {
        id: "",
        titulo: "Baki-Dou: El samurái invensible",
        imagen: "https://image.tmdb.org/t/p/w300/vIbiGAJR69775GHFlYlPFG4GSpb.jpg"
      },
      {
        id: "",
        titulo: "Los siete pecados capitales: El rencor de edimburgo",
        imagen: "https://image.tmdb.org/t/p/w300/by5ZMxYI4RD4CzKMJhX6X74JhFl.jpg"
      }
    ]
  },

  baki_2018: {
    id: "baki_2018",
    titulo: "Baki",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/8iph3FOdIKONU2yEvQcDLZAZiTD.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/vyeQfmEAMZvgkqe65BhfAKMwdNK.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Mientras el campeón de artes marciales Baki Hanma entrena duro para superar a su legendario padre, cinco violentos presos condenados a muerte descienden sobre Tokio para enfrentarse a él.",
    anio: "2018",
    duracion: "24min",
    calificacion: "97%",
    genero: "Accion • Anime • Animacion",
    director: "Toshiki Hirano",
    reparto: "Nobunaga Shimazaki, Kenjiro Tsuda, Takehito Koyasu",
    estreno: "25/06/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2018,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/iwkdw8cw1ro3fbxfzir5e/Baki-T1C1-Espa-ol-Latino-2018.mp4?rlkey=jeeocv11wuiir35gr73bfk0k6&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/uxqhiyomps7wf1mm4kbwo/Baki-T1C2-Espa-ol-Latino-2018.mp4?rlkey=odq2u3sljmok0poa48d8d2koo&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/afv513927snr50tbpuci7/Baki-T1C3-Espa-ol-Latino-2018.mp4?rlkey=gpsv8ri1pqrsq1eemovmm7oj8&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/klko5x0hvgl1i6kb0o2dp/Baki-T1C4-Espa-ol-Latino-2018.mp4?rlkey=nq31tpfdpp6hq94oi110vn8y9&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/v4un3na3lvrgw0usdsvvm/Baki-T1C5-Espa-ol-Latino-2018.mp4?rlkey=lvw1abntpx4ukn177uc3fc0vo&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/eh68wp5rm94ishs494l4t/Baki-T1C6-Espa-ol-Latino-2018.mp4?rlkey=geq4klxwwrwefs6hlitbqmd5l&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/ycjo4q6sgfwit2k9f921j/Baki-T1C7-Espa-ol-Latino-2018.mp4?rlkey=2kgqzps7fcip6uprbwg1273pp&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/6x34x6cjgsprwp83fo87r/Baki-T1C8-Espa-ol-Latino-2018.mp4?rlkey=qn7shn17y57btkenxllpby5b4&st=" },
          { id: "t1e9", number: 9, src: "https://dl.dropbox.com/scl/fi/1fy7u318v36ee78947r76/Baki-T1C9-Espa-ol-Latino-2018.mp4?rlkey=tt00fzgzrvoy9w1uqbeg7xs2b&st=" },
          { id: "t1e10", number: 10, src: "https://dl.dropbox.com/scl/fi/n0ailx154o9115bejgdza/Baki-T1C10-Espa-ol-Latino-2018.mp4?rlkey=gprr3f3fwv9cddmm8rmn2kpp0&st=" },
          { id: "t1e11", number: 11, src: "https://dl.dropbox.com/scl/fi/u4kbsijeyyrsf258sjjjd/Baki-T1C11-Espa-ol-Latino-2018.mp4?rlkey=65spww2j83hfbtz0q51pvzrqe&st=" },
          { id: "t1e12", number: 12, src: "https://dl.dropbox.com/scl/fi/nru9lwlwrhmxr9vx4qcmu/Baki-T1C12-Espa-ol-Latino-2018.mp4?rlkey=fslhxyuumcxxktaauzqctehsn&st=" },
          { id: "t1e13", number: 13, src: "https://dl.dropbox.com/scl/fi/7f2mnauzv15p3k6rmhu6y/Baki-T1C13-Espa-ol-Latino-2018.mp4?rlkey=sidom6z2gom03htyuma13gpe0&st=" },
          { id: "t1e14", number: 14, src: "https://dl.dropbox.com/scl/fi/31289wt4skea7pnjn9b1y/Baki-T1C14-Espa-ol-Latino-2018.mp4?rlkey=9h6sorgxemwtz4yh142mzsjeg&st=" },
          { id: "t1e15", number: 15, src: "https://dl.dropbox.com/scl/fi/8emednj2s5wk8jasze4w8/Baki-T1C15-Espa-ol-Latino-2018.mp4?rlkey=or8051sx0iei034gwbfx1pv2f&st=" },
          { id: "t1e16", number: 16, src: "https://dl.dropbox.com/scl/fi/o2niturx1ynp720d25i2s/Baki-T1C16-Espa-ol-Latino-2018.mp4?rlkey=4bqny5hl21aq94yfs8iekxiwh&st=" },
          { id: "t1e17", number: 17, src: "https://dl.dropbox.com/scl/fi/c1y6o0vibeztrtq1p2v5i/Baki-T1C17-Espa-ol-Latino-2018.mp4?rlkey=abkc889mhqw13ceifwb2viyfp&st=" },
          { id: "t1e18", number: 18, src: "https://dl.dropbox.com/scl/fi/f9k90xuosu9jmvrbw0twb/Baki-T1C18-Espa-ol-Latino-2018.mp4?rlkey=9wxotajfe8uu0iucum4i4cpem&st=" },
          { id: "t1e19", number: 19, src: "https://dl.dropbox.com/scl/fi/3wbjv9yti8z9taw6z543n/Baki-T1C19-Espa-ol-Latino-2018.mp4?rlkey=3gyup92xdteqbol2feyf4qx50&st=" },
          { id: "t1e20", number: 20, src: "https://dl.dropbox.com/scl/fi/6hwb9h36o46x03u9fj3yz/Baki-T1C20-Espa-ol-Latino-2018.mp4?rlkey=csb13iv128otlib9ysg8lffjw&st=" },
          { id: "t1e21", number: 21, src: "https://dl.dropbox.com/scl/fi/vhyfzayc8yeqjhkmh4khu/Baki-T1C21-Espa-ol-Latino-2018.mp4?rlkey=ni8ozlr3h9jq4gg90h238janh&st=" },
          { id: "t1e22", number: 22, src: "https://dl.dropbox.com/scl/fi/v3poe449i82d25pntzz1f/Baki-T1C22-Espa-ol-Latino-2018.mp4?rlkey=eawn787c25zz7udt5eorrpp1s&st=" },
          { id: "t1e23", number: 23, src: "https://dl.dropbox.com/scl/fi/2d6upqad5xjnyok665vau/Baki-T1C23-Espa-ol-Latino-2018.mp4?rlkey=a43ubjhex7fitiqpx5u2yi9dw&st=" },
          { id: "t1e24", number: 24, src: "https://dl.dropbox.com/scl/fi/xo9c4sgny4vqyiold76u5/Baki-T1C24-Espa-ol-Latino-2018.mp4?rlkey=t4drb67i7p8iaj4gus4lrcob8&st=" },
          { id: "t1e25", number: 25, src: "https://dl.dropbox.com/scl/fi/8y5x859pc574gr8yqmbrj/Baki-T1C25-Espa-ol-Latino-2018.mp4?rlkey=p8xjet7sjdz79heelca8zz81x&st=" },
          { id: "t1e26", number: 26, src: "https://dl.dropbox.com/scl/fi/787gn6l9g6sfl59wuz7bb/Baki-T1C26-Espa-ol-Latino-2018.mp4?rlkey=9m3qobvrf0u1m5pegw2g8sv2e&st=" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2019,
        episodes: [
          { id: "t2e1", number: 1, src: "https://dl.dropbox.com/scl/fi/50is3fwzec3q1mxb2s9nj/Baki-T2C1-Espa-ol-Latino-2018.mp4?rlkey=l0a59xwt537bean9hscfl6l93&st=" },
          { id: "t2e2", number: 2, src: "https://dl.dropbox.com/scl/fi/xflr1nzjmltikzsjnxxjf/Baki-T2C2-Espa-ol-Latino-2018.mp4?rlkey=bt4dwpwjxe3xsnikryf3innew&st=" },
          { id: "t2e3", number: 3, src: "https://dl.dropbox.com/scl/fi/4xyfi6f2cth9j9wfx0bof/Baki-T2C3-Espa-ol-Latino-2018.mp4?rlkey=cjmdr3xz700cpe5s0xnffomyz&st=" },
          { id: "t2e4", number: 4, src: "https://dl.dropbox.com/scl/fi/u5srx3agoyuvrfv23zd0w/Baki-T2C4-Espa-ol-Latino-2018.mp4?rlkey=dk6f2rwj1qh7i5xjsrduv44cd&st=" },
          { id: "t2e5", number: 5, src: "https://dl.dropbox.com/scl/fi/9slzkbczt2iuqeqszzeh9/Baki-T2C5-Espa-ol-Latino-2018.mp4?rlkey=qou247o0dgameczkh64scoed8&st=" },
          { id: "t2e6", number: 6, src: "https://dl.dropbox.com/scl/fi/cvi7v5w3dj2unc7p6ql22/Baki-T2C6-Espa-ol-Latino-2018.mp4?rlkey=h5oaogsq56zl26bo8zyf3hb1d&st=" },
          { id: "t2e7", number: 7, src: "https://dl.dropbox.com/scl/fi/wmcv6p4b3i2azj9wxsyhk/Baki-T2C7-Espa-ol-Latino-2018.mp4?rlkey=7we1u88gkalywa5ca8i3rpw4v&st=" },
          { id: "t2e8", number: 8, src: "https://dl.dropbox.com/scl/fi/73b61skjws39ky51ma7sk/Baki-T2C8-Espa-ol-Latino-2018.mp4?rlkey=l3mkhb5mtpxb8w1bfdsy9eq6f&st=" },
          { id: "t2e9", number: 9, src: "https://dl.dropbox.com/scl/fi/103m85umtb7lp6iwpnnq2/Baki-T2C9-Espa-ol-Latino-2018.mp4?rlkey=lqulqyjld6gpl2qrd7r842ztd&st=" },
          { id: "t2e10", number: 10, src: "https://dl.dropbox.com/scl/fi/87by1l5m7uq691fp05s9r/Baki-T2C10-Espa-ol-Latino-2018.mp4?rlkey=6wbfvoilqbn4918dgtjnxnqzp&st=" },
          { id: "t2e11", number: 11, src: "https://dl.dropbox.com/scl/fi/swxf8dfp2da5xxk8uag4b/Baki-T2C11-Espa-ol-Latino-2018.mp4?rlkey=r8fu18q5tnbbjbx0lz9bwnjca&st=" },
          { id: "t2e12", number: 12, src: "https://dl.dropbox.com/scl/fi/ept2htv439eghcayn0dx6/Baki-T2C12-Espa-ol-Latino-2018.mp4?rlkey=yc0yi04qr51e1pfyzc6xts2e7&st=" },
          { id: "t2e13", number: 13, src: "https://dl.dropbox.com/scl/fi/1ggzk71biarcuzk5zq8sr/Baki-T2C13-Espa-ol-Latino-2018.mp4?rlkey=d2m0mfmj1uyt0f8nlcjw34zhi&st=" }
        ]
      }
    ],

    recomendaciones: [
      {
        id: "baki_dou_el_samurai_invencible",
        titulo: "Baki-Dou: El samirai invensible",
        imagen: "https://image.tmdb.org/t/p/w300/vIbiGAJR69775GHFlYlPFG4GSpb.jpg"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/x145FSI9xJ6UbkxfabUsY2SFbu3.jpg"
      },
      {
        href: "Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma VS Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg"
      },
      {
        id: "",
        titulo: "Los siete pecados capitales",
        imagen: "https://image.tmdb.org/t/p/w300/dWEKYfDkaTpx3BZsXbSnf0U9G4X.jpg"
      },
      {
        id: "",
        titulo: "Dragon Ball Z",
        imagen: "https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"
      },
      {
        id: "",
        titulo: "Demon Slayer: Kimetsu no Yaiba – Castillo Infinito",
        imagen: "https://image.tmdb.org/t/p/w300/fWVSwgjpT2D78VUh6X8UBd2rorW.jpg"
      }
    ]
  },

  /* E */

  el_juego_del_calamar: {
    id: "el_juego_del_calamar",
    titulo: "El juego del calamar",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/87mebbBtoWzHV0kILgV6M7yIfun.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/qbIpsvspeBrHkyauS5lQQEJENaX.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Mientras el campeón de artes marciales Baki Hanma entrena duro para superar a su legendario padre, cinco violentos presos condenados a muerte descienden sobre Tokio para enfrentarse a él.",
    anio: "2018",
    duracion: "24min",
    calificacion: "81%",
    genero: "Accion • Anime • Animacion",
    director: "Toshiki Hirano",
    reparto: "Nobunaga Shimazaki, Kenjiro Tsuda, Takehito Koyasu",
    estreno: "25/06/2018",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2018,
        episodes: [
          { id: "t1e1", number: 1, src: "https://pomf2.lain.la/f/ms1jmsqt.mp4" },
          { id: "t1e2", number: 2, src: "https://pomf2.lain.la/f/qt4mljp.mp4" },
          { id: "t1e3", number: 3, src: "https://pomf2.lain.la/f/l6nnuimm.mp4" },
          { id: "t1e4", number: 4, src: "https://pomf2.lain.la/f/7b498vvh.mp4" },
          { id: "t1e5", number: 5, src: "https://pomf2.lain.la/f/1qkw8fnl.mp4" },
          { id: "t1e6", number: 6, src: "https://pomf2.lain.la/f/biuc9q09.mp4" },
          { id: "t1e7", number: 7, src: "https://pomf2.lain.la/f/durwx8id.mp4" },
          { id: "t1e8", number: 8, src: "https://pomf2.lain.la/f/lj8wulcj.mp4" },
          { id: "t1e9", number: 9, src: "https://pomf2.lain.la/f/4wkb714z.mp4" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2019,
        episodes: [
          { id: "t2e1", number: 1, src: "https://pomf2.lain.la/f/ms26sodv.mp4" },
          { id: "t2e2", number: 2, src: "https://pomf2.lain.la/f/vh3a6css.mp4" },
          { id: "t2e3", number: 3, src: "https://pomf2.lain.la/f/njqor1xp.mp4" },
          { id: "t2e4", number: 4, src: "https://pomf2.lain.la/f/4wbmkpbu.mp4" },
          { id: "t2e5", number: 5, src: "https://dl.dropboxusercontent.com/scl/fi/pulw5k1twd035wc9dkckr/El-Juego-Del-Calamar-T2-Cap5.mp4?rlkey=wbgasljxsqaa8jz31396bjzy3&st=" },
          { id: "t2e6", number: 6, src: "https://pomf2.lain.la/f/yt14iy7u.mp4" },
          { id: "t2e7", number: 7, src: "https://pomf2.lain.la/f/a7npytuo.mp4" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2019,
        episodes: [
          { id: "t3e1", number: 1, src: "https://dl.dropbox.com/scl/fi/6vque8yn2d81v084pfqwv/El-juego-del-calamar-T3-C1-2025.mp4?rlkey=yp1jrx2x4ec489c63m6nyfo82&st=" },
          { id: "t3e2", number: 2, src: "https://dl.dropbox.com/scl/fi/yknn3xevxuk43z9fdk8px/El-juego-del-calamar-T3-C2-2025.mp4?rlkey=e51mv72g3lvso172elh8tw1ts&st=" },
          { id: "t3e3", number: 3, src: "https://dl.dropbox.com/scl/fi/ob63d7ent24jjqndac8lt/El-juego-del-calamar-T3-C3-2025.mp4?rlkey=1h841i5vpc67h6lyc4szrz1mo&st=" },
          { id: "t3e4", number: 4, src: "https://dl.dropbox.com/scl/fi/k5fpwtfy4e37pmkqaw3ig/El-juego-del-calamar-T3-C4-2025.mp4?rlkey=siblgnbxzs7sj4nbqag8i5tji&st=" },
          { id: "t3e5", number: 5, src: "https://dl.dropbox.com/scl/fi/ahh500o19r7jpz8j7fzaf/El-juego-del-calamar-T3-C5-2025.mp4?rlkey=p22znr5st1tj7ndzewi010iuz&st=" },
          { id: "t3e6", number: 6, src: "https://dl.dropbox.com/scl/fi/0qq28soexzcb13fia17u3/El-juego-del-calamar-T3-C6-2025.mp4?rlkey=bnbwerj1jfd63vv5fcdu940c0&st=" }
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "El juego del calamar: El desafío",
        imagen: "https://image.tmdb.org/t/p/w300/AvOcYB2lcqRFvhq3ybJlcXgpRRu.jpg"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/x145FSI9xJ6UbkxfabUsY2SFbu3.jpg"
      },
      {
        href: "Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma VS Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg"
      },
      {
        id: "",
        titulo: "Los siete pecados capitales",
        imagen: "https://image.tmdb.org/t/p/w300/dWEKYfDkaTpx3BZsXbSnf0U9G4X.jpg"
      },
      {
        id: "",
        titulo: "Dragon Ball Z",
        imagen: "https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"
      },
      {
        id: "",
        titulo: "Demon Slayer: Kimetsu no Yaiba – Castillo Infinito",
        imagen: "https://image.tmdb.org/t/p/w300/fWVSwgjpT2D78VUh6X8UBd2rorW.jpg"
      }
    ]
  },

  en_el_barro: {
    id: "en_el_barro",
    titulo: "En el barro",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        href: "",
        titulo: "",
        imagen: ""
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
  
  /* F */

  nombre_de_serie: {
    id: "nombre_de_serie",
    titulo: "",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        href: "",
        titulo: "",
        imagen: ""
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

  /* I */

  nombre_de_serie: {
    id: "nombre_de_serie",
    titulo: "",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        href: "",
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

  /* M */

  nombre_de_serie: {
    id: "nombre_de_serie",
    titulo: "",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "2026",
    duracion: "24min",
    calificacion: "97%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        id: "baki_hanma",
        titulo: "Baki Hanma",
        imagen: "https://image.tmdb.org/t/p/w300/"
      },
      {
        href: "",
        titulo: "",
        imagen: ""
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

  /* T */

  the_walking_dead: {
    id: "the_walking_dead",
    titulo: "The Walking Dead",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/j0WWIhJzMazd876qG7hf8adwc4s.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/9iYinsg30olSCuDoH8VxtRN5gZx.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/mu1zFlKK7pQbGbkCHDyRRQ6RMRW.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "El ayudante del sheriff Rick Grimes despierta de un coma y se encuentra en un mundo postapocalíptico dominado por zombis devoradores de carne. Se lanza a la búsqueda de su familia y, en el camino, se topa con muchos otros supervivientes.",
    anio: "2010",
    duracion: "51min",
    calificacion: "87%",
    genero: "Accion • Drama • Apocalipsis",
    director: "Frank Darabont",
    reparto: "Andrés Lincoln, Norman Reedus, Lauren Cohan",
    estreno: "31/10/2010",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2010,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/os51dly96eql621e8edvb/The-Walking-dead-T1C1.mp4?rlkey=1s2bxyvx3nrmj1745vegcd4pr&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/kn8onakbbb5hfqn199kv1/The-Walking-dead-T1C2.mp4?rlkey=qns1y6bq48ie8e2e6wcdl1w7k&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/lvpvu0leerrvlmfsr0vqc/The-Walking-dead-T1C3.mp4?rlkey=xl0sa2x9ya5cwye9kv2nv1fk1&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/elj0k8xub06vos165h5f0/The-Walking-dead-T1C4.mp4?rlkey=eihckvsihu86ijlda37m5wwti&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/zjb98qgzz03gewjxi60uy/The-Walking-dead-T1C5.mp4?rlkey=cwpgoz405bdec5algud76xu6s&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/tx6uz9hy7qd8r4aiek5rr/The-Walking-dead-T1C6.mp4?rlkey=prh66f6av9lumvolfmiwco4t1&st=" }
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "The Walking Dead: The ones two lives",
        imagen: "https://image.tmdb.org/t/p/w300/t1yUeYKw3s0VntQxzdUgza4msGU.jpg"
      },
      {
        id: "",
        titulo: "The Walking Dead: Daryl Dixon",
        imagen: "https://image.tmdb.org/t/p/w300/kdM24KINoAVK9wjCtDJCkdffEpc.jpg"
      },
      {
        href: "",
        titulo: "Fear The Walking Dead",
        imagen: "https://image.tmdb.org/t/p/w300/yd8cDZG4XjNRk7jfEWy18KH07d2.jpg"
      },
      {
        id: "",
        titulo: "The Walking Dead: Dead city",
        imagen: "https://image.tmdb.org/t/p/w300/ugdiqJsceA9slg2k4Uu6VKegaw8.jpg"
      },
      {
        id: "",
        titulo: "The Walking Dead: World Beyond",
        imagen: "https://image.tmdb.org/t/p/w300/6HanIV2hTLE2w7A5bI1KJb3bTL7.jpg"
      },
      {
        id: "",
        titulo: "Tales Of The Walking Dead",
        imagen: "https://image.tmdb.org/t/p/w300/zRMUHvTgQ79zteQafNI46Nd9XFm.jpg"
      }
    ]
  },


};
</script>

<script>
let episodiosActuales = [];
let episodioActualIndex = 0;

function actualizarMarcadorVisual() {
  const cards = document.querySelectorAll(".ep-card");

  cards.forEach((card, index) => {
    if (index === episodioActualIndex) {
      card.classList.add("activo");
    } else {
      card.classList.remove("activo");
    }
  });
}
</script>


<script>
let castSession = null;
let isCasting = false;
let castInterval = null;

const stopCastBtn = document.getElementById("stopCastBtn");


// ===============================
// ELEMENTOS UI
// ===============================
const btnCast = document.getElementById("btnCast");
const modal = document.getElementById("castModal");
const closeModal = document.getElementById("closeCastModal");
const startCastBtn = document.getElementById("startCastBtn");
const statusText = document.querySelector(".cast-status");

// ===============================
// ABRIR / CERRAR MODAL
// ===============================
btnCast.addEventListener("click", () => {
  modal.classList.remove("hidden");
});

closeModal.addEventListener("click", () => {
  modal.classList.add("hidden");
});

// ===============================
// BOTÓN BUSCAR DISPOSITIVOS + ENVIAR VIDEO
// ===============================
startCastBtn.addEventListener("click", async () => {
  const context = cast.framework.CastContext.getInstance();

  try {
    statusText.textContent = "Buscando dispositivos...";

    // 🔥 abre selector de dispositivos
    await context.requestSession();

    castSession = context.getCurrentSession();
    isCasting = true;

    statusText.textContent = "Conectado ✔";

    updateCastUI(true);

    // 🔥 obtener video
    const video = document.getElementById("videoPlayer");
    const videoSrc = video.currentSrc || video.src;

    if (!videoSrc) {
      alert("No hay video cargado");
      return;
    }

    // 🔥 datos de reproducción
    const playbackData = {
      movieId: typeof MOVIE_KEY !== "undefined" ? MOVIE_KEY : null,
      seasonId: typeof currentSeason !== "undefined" ? currentSeason?.id : null,
      episodeId: typeof currentEpisode !== "undefined" ? currentEpisode?.id : null,
      time: Math.floor(video.currentTime || 0)
    };

    // 🔥 enviar al Chromecast
    loadMediaToCast(videoSrc, playbackData);

    // cerrar modal
    modal.classList.add("hidden");

  } catch (err) {
    console.error(err);
    statusText.textContent = "No se encontró ningún dispositivo";
  }
});

// ===============================
// INIT CAST
// ===============================
window['__onGCastApiAvailable'] = function(isAvailable) {
  if (isAvailable) {
    initializeCast();
  }
};

function initializeCast() {
  const context = cast.framework.CastContext.getInstance();

  context.setOptions({
    receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
    autoJoinPolicy: chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED
  });

  context.addEventListener(
    cast.framework.CastContextEventType.SESSION_STATE_CHANGED,
    (event) => {

      if (
        event.sessionState === cast.framework.SessionState.SESSION_STARTED ||
        event.sessionState === cast.framework.SessionState.SESSION_RESUMED
      ) {
        castSession = context.getCurrentSession();
        isCasting = true;

        updateCastUI(true);
        startCastSync();

      } else if (
        event.sessionState === cast.framework.SessionState.SESSION_ENDED
      ) {
        isCasting = false;

        updateCastUI(false);
        stopCastSync();
      }
    }
  );
}

// ===============================
// ENVIAR MEDIA
// ===============================
function loadMediaToCast(src, data) {
  if (!castSession) return;

  const mediaInfo = new chrome.cast.media.MediaInfo(src, getMimeType(src));

  mediaInfo.metadata = new chrome.cast.media.GenericMediaMetadata();
  mediaInfo.metadata.title = "MovieTX";

  mediaInfo.customData = {
    movieId: data.movieId,
    seasonId: data.seasonId,
    episodeId: data.episodeId
  };

  const request = new chrome.cast.media.LoadRequest(mediaInfo);

  request.currentTime = data.time;
  request.autoplay = true;

  castSession.loadMedia(request)
    .then(() => {

      console.log("📺 Enviado al Chromecast");

      const media = castSession.getMediaSession();

      // 🔥 fix delay
      if (media && data.time > 0) {
        setTimeout(() => {
          const seekRequest = new chrome.cast.media.SeekRequest();
          seekRequest.currentTime = data.time;
          media.seek(seekRequest);
        }, 1000);
      }

      // ⏸ pausar local
      document.getElementById("videoPlayer").pause();

      if (typeof guardarProgresoDB === "function") {
        guardarProgresoDB();
      }

    })
    .catch(err => console.error("❌ Error enviando:", err));
}

// ===============================
// SYNC
// ===============================
function startCastSync() {
  stopCastSync();

  castInterval = setInterval(() => {

    if (!castSession) return;

    const media = castSession.getMediaSession();
    if (!media) return;

    const tiempo = media.getEstimatedTime();
    const video = document.getElementById("videoPlayer");

    if (tiempo && video) {
      video.currentTime = tiempo;

      if (typeof guardarProgresoDB === "function") {
        guardarProgresoDB();
      }
    }

  }, 5000);
}

function stopCastSync() {
  if (castInterval) {
    clearInterval(castInterval);
    castInterval = null;
  }
}

// ===============================
// MIME TYPE
// ===============================
function getMimeType(url) {
  if (url.includes(".m3u8")) return "application/x-mpegURL";
  if (url.includes(".mp4")) return "video/mp4";
  return "video/mp4";
}

// ===============================
// UI
// ===============================
function updateCastUI(active) {
  if (active) {
    btnCast.classList.add("casting");
    stopCastBtn.classList.remove("hidden"); // 🔥 mostrar
  } else {
    btnCast.classList.remove("casting");
    stopCastBtn.classList.add("hidden"); // 🔥 ocultar
  }
}

stopCastBtn.addEventListener("click", stopCasting);


function stopCasting() {
  const context = cast.framework.CastContext.getInstance();

  try {
    // 🔥 FORZAR STOP REAL
    context.endCurrentSession(true);

    console.log("🛑 Transmisión detenida (REAL)");

    isCasting = false;
    castSession = null;

    stopCastSync();
    updateCastUI(false);

    statusText.textContent = "Transmisión finalizada";

    // 🔥 volver al video local
    const video = document.getElementById("videoPlayer");
    if (video && video.src) {
      video.play().catch(() => {});
    }

  } catch (err) {
    console.error("Error al detener:", err);
  }
}

const video = document.getElementById("videoPlayer");

video.addEventListener("play", () => {
  if (!isCasting || !castSession) return;

  const videoSrc = video.currentSrc || video.src;
  if (!videoSrc) return;

  console.log("🔄 Reenviando video al Chromecast...");

  const playbackData = {
    movieId: typeof MOVIE_KEY !== "undefined" ? MOVIE_KEY : null,
    seasonId: typeof currentSeason !== "undefined" ? currentSeason?.id : null,
    episodeId: typeof currentEpisode !== "undefined" ? currentEpisode?.id : null,
    time: Math.floor(video.currentTime || 0)
  };

  loadMediaToCast(videoSrc, playbackData);
});

let lastSrc = "";

setInterval(() => {
  const video = document.getElementById("videoPlayer");
  if (!video) return;

  const currentSrc = video.currentSrc || video.src;

  if (isCasting && castSession && currentSrc && currentSrc !== lastSrc) {
    console.log("🎬 Detectado cambio de video → enviando al Chromecast");

    lastSrc = currentSrc;

    const playbackData = {
      movieId: typeof MOVIE_KEY !== "undefined" ? MOVIE_KEY : null,
      seasonId: typeof currentSeason !== "undefined" ? currentSeason?.id : null,
      episodeId: typeof currentEpisode !== "undefined" ? currentEpisode?.id : null,
      time: Math.floor(video.currentTime || 0)
    };

    loadMediaToCast(currentSrc, playbackData);
  }

}, 1500);


</script>




<!-- ===========================================================================
    ESTE SCRIPT TIENE LA TEMPORADA/EPISODIO/RECOMENDACIONES/BASE DE DATOS/ETC
    ============================================================================-->

<script>
document.addEventListener("DOMContentLoaded", () => {

  /* =========================
     PARAMS / DATA
  ========================= */
  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");
  const movie = typeof MOVIES_DB !== "undefined" ? MOVIES_DB[movieId] : null;

  function saveSeasonProgress() {
  localStorage.setItem(SEASON_KEY, JSON.stringify(lastEpisodeBySeason));
}

  if (!movie) {
    alert("Película no encontrada");
    window.location.href = "../index.html";
    return;
  }

  /* =========================
     VARIABLES GLOBALES
  ========================= */
  let currentEpisode = null;
  let currentSeason = movie.type === "series" && movie.seasons ? movie.seasons[0] : null;

  const MOVIE_KEY = movie.id;
  const SEASON_KEY = `season_progress_${movieId}`;
let lastEpisodeBySeason = JSON.parse(localStorage.getItem(SEASON_KEY)) || {};
  const VIDEO_SRC = movie.video;
  const seasonYearEl = document.querySelector(".fal.fa-calendar-alt")?.nextElementSibling;
  const grid = document.getElementById("recomendaciones-grid");


  // ✅ FIX: tiempo de reanudación real
  let resumeTime = 0;

  /* =========================
     ELEMENTOS
  ========================= */
  const video = document.getElementById("videoPlayer");
  const player = document.getElementById("mobilePlayer");
  const overlay = document.getElementById("playerOverlay");

  const btnPlay = document.getElementById("btnPlay");
  const btnRewind = document.getElementById("btnRewind");
  const btnForward = document.getElementById("btnForward");
  const btnFullscreen = document.getElementById("btnFullscreen");

  const progress = document.getElementById("progressBar");
  const currentTimeEl = document.getElementById("currentTime");
  const durationEl = document.getElementById("duration");

  const btnOpen = document.getElementById("btn-open-seasons");
  const modal = document.getElementById("season-modal");
  const seasonList = document.getElementById("season-list");
  const closeBtn = document.querySelector(".season-close");

  const episodesContainer = document.getElementById("episodes-container");
  const counter = document.getElementById("episodes-counter");
  const seasonNameText = document.getElementById("season-name");

  /* =========================
     FLAGS
  ========================= */
  let isSeeking = false;
  let videoLoaded = false;
  let overlayTimeout = null;
  let lastSavedTime = 0;


  function updateSeasonYearUI() {
  if (!seasonYearEl || !currentSeason) return;
  seasonYearEl.textContent = currentSeason.year;
}

  /* =========================
     PROGRESO BD
  ========================= */

  function guardarProgresoDB() {
    if (!movieId) return;

    fetch("guardar_progreso.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        movie_id: MOVIE_KEY,
        temporada: currentSeason ? currentSeason.id : null,
        episodio: currentEpisode ? currentEpisode.id : null,
        tiempo: video.currentTime || 0
      })
    }).catch(err => console.log("Error guardando progreso:", err));
  }

  async function cargarProgresoDB() {
    try {
      const res = await fetch(`obtener_progreso.php?movie_id=${MOVIE_KEY}`);
      const data = await res.json();

      if (data.status !== "ok" || !data.data) {
        loadSeasons();
        loadEpisodes();
        return;
      }

      const progreso = data.data;

      const season = movie.seasons.find(s => s.id === progreso.temporada);
      if (!season) {
        loadSeasons();
        loadEpisodes();
        return;
      }

      currentSeason = season;
      updateSeasonYearUI();

// 🔥 recuperar episodio por temporada (localStorage + fallback BD)
const savedEpisodeId =
  progreso?.episodio ||   // 🔥 PRIORIDAD BD
  lastEpisodeBySeason?.[season.id] ||
  null;

  // 🔥 sincronizar SIEMPRE con la BD
if (progreso?.episodio) {
  lastEpisodeBySeason[season.id] = progreso.episodio;
  saveSeasonProgress();
}


const ep =
  season.episodes.find(e => e.id === savedEpisodeId) ||
  season.episodes[0];

currentEpisode = ep;

// guardar en memoria para que el UI lo respete
lastEpisodeBySeason[season.id] = ep.id;

// opcional pero recomendado
saveSeasonProgress();

resumeTime = parseFloat(progreso.tiempo) || 0;

seasonNameText.textContent = season.name;

loadSeasons();
loadEpisodes();

    } catch (err) {
      console.log("Error cargando progreso:", err);
      loadSeasons();
      loadEpisodes();
    }
  }

  function scrollToActiveEpisode() {
  const active = document.querySelector(".episode-box.active");
  const container = document.getElementById("episodes-container");

  if (!active || !container) return;

  active.scrollIntoView({
    behavior: "smooth", // 🔥 animación suave
    inline: "center",   // 🔥 horizontal centrado
    block: "nearest"
  });
}

  // =========================
// BOTÓN TEMPORADAS (FIX)
// =========================
btnOpen.onclick = () => {
  modal.classList.remove("hidden");

  // 🔥 FIX: actualizar visual al abrir
  updateSeasonActiveUI();
};

closeBtn.onclick = () => {
  modal.classList.add("hidden");
};

function updateRainbowProgress() {
  const value = progress.value;
  progress.style.setProperty("--progress", value + "%");
}


  /* =========================
   DRAG PROGRESS BAR (SEEK)
========================= */

// 🔒 cuando empiezas a arrastrar
progress.addEventListener("mousedown", () => {
  isSeeking = true;
});

progress.addEventListener("touchstart", () => {
  isSeeking = true;
});

// 👀 mientras arrastras (preview del tiempo)
progress.addEventListener("input", () => {
  updateRainbowProgress();

  if (!video.duration) return;

  const previewTime = (progress.value / 100) * video.duration;

  currentTimeEl.textContent =
    `${Math.floor(previewTime / 60)}:${Math.floor(previewTime % 60).toString().padStart(2, "0")}`;
});

// 🎯 cuando sueltas el mouse o dedo
function applySeek() {
  if (!video.duration) return;

  video.currentTime = (progress.value / 100) * video.duration;

  isSeeking = false;
}

progress.addEventListener("mouseup", applySeek);
progress.addEventListener("touchend", applySeek);

// 🧹 seguridad mobile (evita bug si sales del slider)
progress.addEventListener("mouseleave", () => {
  isSeeking = false;
});

  /* =========================
     OVERLAY
  ========================= */
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

  /* =========================
     INFO PELÍCULA
  ========================= */
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

  function setActiveEpisode(ep) {
  currentEpisode = ep;

  document.querySelectorAll(".episode-box").forEach(card => {
    const isActive = card.dataset.id === ep.id;

    card.classList.toggle("active", isActive);
    card.classList.toggle("viendo", isActive);
  });

  // 🔥 SCROLL AUTOMÁTICO
  requestAnimationFrame(() => {
  scrollToActiveEpisode();
});
}

  function resetPlayerUI() {
  video.pause();
  video.removeAttribute("src");
  video.load();

  videoLoaded = false;

  progress.value = 0;
  currentTimeEl.textContent = "0:00";
  durationEl.textContent = "0:00";

  btnPlay.textContent = "▶";
  overlay.classList.remove("hide");
}

  function updateSeasonActiveUI() {
  document.querySelectorAll(".season-item").forEach(btn => {
    btn.classList.remove("active");

    const seasonName = btn.textContent;

    if (currentSeason && seasonName === currentSeason.name) {
      btn.classList.add("active");
    }
  });
}

  /* =========================
     VIDEO RESTORE TIME (🔥 FIX CLAVE)
  ========================= */
  video.addEventListener("loadedmetadata", () => {
    if (resumeTime > 0) {
      video.currentTime = resumeTime;
      resumeTime = 0;
    }
  });

  /* =========================
     SERIES
  ========================= */
  if (movie.type === "series" && currentSeason) {

    function loadEpisodes() {
      episodesContainer.innerHTML = "";
      const episodes = currentSeason.episodes || [];

      counter.textContent = episodes.length + " Capítulos";

      episodes.forEach((ep) => {

        const card = document.createElement("div");
        card.className = "episode-box";
        card.textContent = ep.number;
        card.dataset.id = ep.id;

        card.onclick = async () => {

  if (!ep.src) return alert("Episodio no disponible");

  setActiveEpisode(ep);
  currentEpisode = ep;

  // 🔥 guardar episodio local
  lastEpisodeBySeason[currentSeason.id] = ep.id;
  saveSeasonProgress();

  // 🔥 GUARDAR EN BD (CLAVE)
  guardarProgresoDB();

  video.src = ep.src;
  video.load();

  videoLoaded = true;

  video.onloadedmetadata = () => {
    video.play();
  };

  btnPlay.textContent = "❚❚";
  overlay.classList.add("hide");

  requestAnimationFrame(() => {
    scrollToActiveEpisode();
  });

  // ===============================
  // 🔥 CHROMECAST
  // ===============================
  if (isCasting && castSession) {

    const playbackData = {
      movieId: MOVIE_KEY,
      seasonId: currentSeason?.id || null,
      episodeId: ep.id,
      time: 0
    };

    loadMediaToCast(ep.src, playbackData);
  }
};



        episodesContainer.appendChild(card);
      });

      const activeId =
  lastEpisodeBySeason[currentSeason.id] || currentEpisode?.id;

if (activeId) {
  const ep = episodes.find(e => e.id === activeId);
  if (ep) setActiveEpisode(ep);
} else {
  currentEpisode = episodes[0];
  setActiveEpisode(currentEpisode);
}
    }

    function loadSeasons() {
      seasonList.innerHTML = "";

      movie.seasons.forEach(season => {
        const btn = document.createElement("button");
        btn.className = "season-item";
        btn.textContent = season.name;

        if (season === currentSeason) btn.classList.add("active");

        btn.onclick = () => {

  // 💾 guardar episodio actual de la temporada anterior
  if (currentSeason && currentEpisode) {
  lastEpisodeBySeason[currentSeason.id] = currentEpisode.id;
  saveSeasonProgress();
}

  currentSeason = season;

  // 🔁 recuperar episodio de esa temporada si existe
  const savedEpisodeId = lastEpisodeBySeason[currentSeason.id];

  currentEpisode =
    currentSeason.episodes.find(e => e.id === savedEpisodeId)
    || currentSeason.episodes?.[0]
    || null;

  seasonNameText.textContent = season.name;

  resetPlayerUI();
  loadEpisodes();
  updateSeasonActiveUI();
  modal.classList.add("hidden");
  updateSeasonYearUI();
};

        seasonList.appendChild(btn);
      });
    }

    function playNextEpisode() {
  const episodes = currentSeason.episodes || [];
  const index = episodes.findIndex(e => e.id === currentEpisode.id);
  const next = episodes[index + 1];

  if (!next) {
    video.pause();
    video.removeAttribute("src");
    video.load();

    currentEpisode = null;
    videoLoaded = false;

    btnPlay.textContent = "▶";
    overlay.classList.remove("hide");

    progress.value = 0;
    currentTimeEl.textContent = "0:00";
    durationEl.textContent = "0:00";
    return;
  }

  if (isCasting && castSession && next?.src) {

  const playbackData = {
    movieId: MOVIE_KEY,
    seasonId: currentSeason?.id || null,
    episodeId: next.id,
    time: 0
  };

  loadMediaToCast(next.src, playbackData);
}


  // 🔥 ACTUALIZAR EPISODIO
  currentEpisode = next;

  // 🔥 GUARDAR EPISODIO POR TEMPORADA
  lastEpisodeBySeason[currentSeason.id] = next.id;
  saveSeasonProgress();

  // 🔥 GUARDAR EN BASE DE DATOS
  guardarProgresoDB();

  setActiveEpisode(next);

  video.src = next.src;
  video.load();
  video.currentTime = 0;

  video.onloadedmetadata = () => {
    video.play();
  };
}


    loadSeasons();
    loadEpisodes();
    seasonNameText.textContent = currentSeason.name;
  }



  /* =========================
     PLAYER
  ========================= */
  btnPlay.onclick = () => {

    if (movie.type === "series") {
      if (!currentEpisode) return;

      if (!videoLoaded || video.src !== currentEpisode.src) {
        video.src = currentEpisode.src;
        video.load();
        videoLoaded = true;
      }
    } else {
      if (!videoLoaded) {
        video.src = VIDEO_SRC;
        video.load();
        videoLoaded = true;
      }
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

  /* =========================
   BOTONES CONTROL VIDEO
========================= */

// ⏪ ATRASAR 10s
btnRewind.onclick = (e) => {
  e.stopPropagation();

  if (!videoLoaded || !video.duration) return;

  showOverlay();
  video.currentTime = Math.max(0, video.currentTime - 10);
};

// ⏩ ADELANTAR 10s
btnForward.onclick = (e) => {
  e.stopPropagation();

  if (!videoLoaded || !video.duration) return;

  showOverlay();
  video.currentTime = Math.min(video.duration, video.currentTime + 10);
};

// ⛶ FULLSCREEN
function enterFullscreen() {
  try {
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
  e.stopPropagation();
  enterFullscreen();
};


  /* =========================
     PROGRESO
  ========================= */
  video.addEventListener("timeupdate", () => {
    if (!video.duration || isSeeking) return;

    progress.value = (video.currentTime / video.duration) * 100;

// 🌈 actualizar barra
updateRainbowProgress();


    currentTimeEl.textContent =
      `${Math.floor(video.currentTime / 60)}:${Math.floor(video.currentTime % 60).toString().padStart(2,"0")}`;

    durationEl.textContent =
      `${Math.floor(video.duration / 60)}:${Math.floor(video.duration % 60).toString().padStart(2,"0")}`;

    if (!video.paused && Math.abs(video.currentTime - lastSavedTime) >= 5) {
      guardarProgresoDB();
      lastSavedTime = video.currentTime;
    }
  });

  video.addEventListener("ended", () => {
    guardarProgresoDB();
    playNextEpisode();
  });

  function cargarRecomendaciones() {

  const grid = document.getElementById("recomendaciones-grid");
  if (!movie.recomendaciones || !grid) return;

  grid.innerHTML = "";

  movie.recomendaciones.forEach(rec => {

    const a = document.createElement("a");
    a.className = "serie";

    // 🔹 CASO 1: archivo externo
    if (rec.href) {
      a.href = `../${rec.href}`;
    }

    // 🔹 CASO 2: reproductor
    else if (rec.id && rec.id.trim() !== "") {

      if (rec.adulto) {
        a.setAttribute("data-href", `Reproductor Universal.php?id=${rec.id}`);
        a.setAttribute("data-adulto", "adulto");
        a.addEventListener("click", handleAdultLinkClick);
      } else {
        a.href = `Reproductor Universal Series.php?id=${rec.id}`;
      }
    }

    // 🔹 SI NO HAY LINK → NO HACE NADA
    else {
      a.href = "#";
    }

    a.innerHTML = `
      <img loading="lazy" src="${rec.imagen}" alt="${rec.titulo}">
      <p>${rec.titulo}</p>
    `;

    grid.appendChild(a);
  });
}


  /* =========================
     INIT
  ========================= */
  cargarDatosPelicula();
  cargarProgresoDB();
  cargarRecomendaciones(); // 🔥 ESTE ES CLAVE

  player.addEventListener("click", (e) => {
  if (e.target.tagName === "BUTTON" || e.target.type === "range") return;

  overlay.classList.toggle("hide");

  if (!overlay.classList.contains("hide")) {
    hideOverlayAuto();
  }
});


});
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
const tipo = movie.type || "pelicula";

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
      mostrarModalFavoritos("Serie agregada a favoritos");

      setTimeout(() => {
        window.location.href = "favoritos.php";
      }, 1500);
    }

    else if(data.status === "exists"){
      mostrarModalFavoritos("Esta Serie ya está en favoritos");
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

  <div class="series-grid" id="recomendaciones-grid">
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

  // ✅ OBTENER MOVIE BIEN (como ya hacés en el otro script)
  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");
  const movie = typeof MOVIES_DB !== "undefined" ? MOVIES_DB[movieId] : null;

  if (!movie) {
    console.log("❌ Movie no encontrada");
    return;
  }

  const titulo = movie.titulo ? movie.titulo.trim() : "";
  const tipo = movie.type ? movie.type.trim() : "series"; // 🔥 corregido (era "tipo")
  const historialImagen = movie.imagen || "";
  const archivo = "Reproductor Universal Series.php?id=" + movieId;

  // ✅ GUARDAR EN HISTORIAL
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

    console.log("Respuesta historial:", data); // 🔥 DEBUG

    if (data.status === "new") {
      esperarFinLoader(() => {
        mostrarModalFavoritos("Agregado al historial");
      });
    }

  })
  .catch(err => console.log("❌ Error historial:", err));


  // ✅ ACTUALIZAR PROGRESO
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