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
/* =========================
   🔥 LOADER BASE CENTRADO
========================= */
#loader-screen {
  position: fixed;
  inset: 0;

  display: flex;
  align-items: center;
  justify-content: center;

  background:
    radial-gradient(circle at 30% 20%, rgba(255,0,120,0.15), transparent 40%),
    radial-gradient(circle at 70% 80%, rgba(0,170,255,0.15), transparent 40%),
    #000;

  z-index: 10000;

  padding: 20px;
  box-sizing: border-box;

  transition: opacity 1s ease, visibility 1s ease;
}
#loader-screen.hidden {
  opacity: 0;
  visibility: hidden;
}

.loader-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  text-align: center;
  width: 100%;
  max-width: 400px;

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

/* =========================
   🔥 CÍRCULO ARCOIRIS
========================= */
.loader-circle {
  position: relative;

  width: 160px;
  height: 160px;

  border-radius: 50%;

  display: flex;
  align-items: center;
  justify-content: center;

  margin-bottom: 20px;
}

/* 🌈 ARO GIRATORIO */
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

/* 🔥 CENTRO NEGRO */
.loader-circle::after {
  content: "";
  position: absolute;
  inset: 4px;
  border-radius: 50%;
  background: #000;
  z-index: 1;
}

/* 🔥 LOGO CENTRADO PERFECTO */
.loader-logo {
  position: absolute;
  top: 50%;
  left: 50%;

  width: 90px;

  transform: translate(-50%, -50%);
  z-index: 2;

  animation: pulse 2.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    transform: translate(-50%, -50%) scale(1);
  }
  50% {
    transform: translate(-50%, -50%) scale(1.08);
  }
}

/* 🔄 ROTACIÓN */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* =========================
   🌈 TEXTO MOVIETX ARCOIRIS
========================= */
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

  text-shadow:
    0 0 8px rgba(255,255,255,0.1),
    0 0 15px rgba(255,0,120,0.2);
}

@keyframes rainbowMove {
  0% { background-position: 0%; }
  100% { background-position: 300%; }
}

/* =========================
   TEXTO
========================= */
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

/* =========================
   🔥 BARRA DE CARGA
========================= */
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
}

/* =========================
   📱 RESPONSIVE
========================= */

/* 📱 CELULARES */
/* 📱 CELULARES */
@media (max-width: 480px) {

  .loader-circle {
    width: 180px;
    height: 180px;
  }

  /* 🔥 logo más grande */
  .loader-logo {
    width: 85px;
  }

  .loader-title {
    font-size: 2rem;
  }

  /* 🔥 barra más corta y prolija */
  .loading-bar {
    width: 65%;
    height: 12px;
  }

  .loading-percent {
    font-size: 10px;
  }

}

/* 💻 PC */
@media (min-width: 1024px) {
  .loader-circle {
    width: 200px;
    height: 200px;
  }

  .loader-logo {
    width: 110px;
  }

  .loader-title {
    font-size: 3rem;
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

/* fin*/

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
const PERFIL_ACTIVO = <?php echo isset($_SESSION['perfil_id']) ? 'true' : 'false'; ?>;
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

  // 🔥 DETECTAR PERFIL O USUARIO
  const URL_GUARDADO = (typeof PERFIL_ACTIVO !== "undefined" && PERFIL_ACTIVO)
    ? "perfil_inicio_serie.php"
    : "inicio_serie.php";

  function guardarProgreso() {

    const progreso = Math.floor(video.currentTime);

    fetch(URL_GUARDADO, { // 🔥 CAMBIO AUTOMÁTICO
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

<!-- ==========================
    ESTILO DE CARGA DE VIDEO
    ===========================
-->

<style>
  #player-loader {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(10, 10, 10, 0.45);
  backdrop-filter: blur(8px);
  z-index: 20;
  border-radius: 8px;

  opacity: 0;
  pointer-events: none;
  transition: opacity 0.35s ease;
}

#player-loader.active {
  opacity: 1;
  pointer-events: all;
}

.loader-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* 🔥 Spinner PRO */
.player-spinner {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: conic-gradient(
    #ff007f,
    #ffae00,
    #00ffcc,
    #00aaff,
    #9d00ff,
    #ff007f
  );
  animation: spin 1.2s linear infinite;
  position: relative;
  margin-bottom: 14px;
  filter: drop-shadow(0 0 10px rgba(255, 0, 127, 0.5));
}

/* Centro del spinner */
.player-spinner::before {
  content: "";
  position: absolute;
  inset: 5px;
  background: #111;
  border-radius: 50%;
}

/* Rotación */
@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Texto */
.player-loading-text {
  font-size: 0.85rem;
  color: #fff;
  font-weight: 600;
  letter-spacing: 0.5px;
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
  <div class="loader-content">
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
        titulo: "La Reina del Sur",
        imagen: "https://image.tmdb.org/t/p/w300/lADayXkcxSA5DXXDMBJLmvgAJH7.jpg"
      },
      {
        id: "",
        titulo: "Pablo Escobar",
        imagen: "https://image.tmdb.org/t/p/w300/jZqm7HFuxfH9P0LFjQZ8nzrCUHP.jpg"
      },
      {
        href: "",
        titulo: "Sin Senos No Hay Paraiso",
        imagen: "https://image.tmdb.org/t/p/w300/pSsQ5rFalyT7ehwgeSlITgBSi5S.jpg"
      },
      {
        id: "",
        titulo: "Josue Y La Tierra Prometida",
        imagen: "https://image.tmdb.org/t/p/w300/ciY0RaYIzKwt2nEQfVAXJyoxv3c.jpg"
      },
      {
        id: "",
        titulo: "Tres veces Ana",
        imagen: "https://image.tmdb.org/t/p/w300/nHURRha9cvKI6LaFVl7gbfEaGjt.jpg"
      },
      {
        id: "",
        titulo: "El Rico Y Lazaro",
        imagen: "https://image.tmdb.org/t/p/w300/k6AxLEM3kBXbQv1mTLWXt4dLs8V.jpg"
      }
    ]
  },

  agatha: {
    id: "agatha",
    titulo: "Agatha ¿Quien si no?",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/tYLXJW1sZQU09VWY1BhSVPKGIwc.jpg",
    imagen: "https://image.tmdb.org/t/p/original/nbkbguUUNWQZygVJKjODyELBQk9.jpg",
    imgserie: "https://image.tmdb.org/t/p/original/nOqGjJO7TtPiA3cS2y0ud8tofz9.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Agatha Harkness reúne un aquelarre de brujas y parte hacia la Senda de las Brujas.",
    anio: "2024",
    duracion: "38min",
    calificacion: "79%",
    genero: "Accion • Marvel • Drama • Misterios",
    director: "Jac Schaeffer",
    reparto: "Kathryn Hahn, Joe Locke, Sasheer Zamata",
    estreno: "18/09/2024",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2024,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/ykcfjdlbo89p91oiw9un8/Agatha-1.mp4?rlkey=tcr55mm5wj4pu63d68lzibmky&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/5q17bickl0pv3ifff0ejg/Agatha-2.mp4?rlkey=24zg3mwzaqs9himwswhb3y2rr&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/2p3i4yg83hm806220d4jp/Agatha-3.mp4?rlkey=xdpcf2gmlqit2nzbtue722iox&st=" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
        ]
      }
    ],

    recomendaciones: [
      {
        id: "loki",
        titulo: "Loki",
        imagen: "https://image.tmdb.org/t/p/w300/1ggA8LZLrnMzkCrpMDuJKfAO8vZ.jpg"
      },
      {
        id: "bruja_escarlata_y_vision",
        titulo: "Bruja Escarlata y Visión",
        imagen: "https://image.tmdb.org/t/p/w300/v3UHVBe7tQD4zVamNSqaTyycuwg.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=doctor_strange2",
        titulo: "Doctor Strange: En el multiverso de la locura",
        imagen: "https://image.tmdb.org/t/p/w300/9Gtg2DzBhmYamXBS1hKAhiwbBKS.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=spider_man_un_nuevo_universo",
        titulo: "Spider-Man: Un nuevo universo",
        imagen: "https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=thor_3",
        titulo: "Thor 3: Ragnarok",
        imagen: "https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=viuda_negra",
        titulo: "Viuda negra",
        imagen: "https://image.tmdb.org/t/p/w300/tvl0OXmNQtLrPk7fJ8UHvLrD37R.jpg"
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
    calificacion: "89%",
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
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/dxk408d40g8ctxhg8czw4/Baki-Dou_El_samur-i_invencible_Latino_06.mp4?rlkey=5bl5qtsmvjo0dtvwzv59t1l9b&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/a9h85ll5le81m2guzoyb2/Baki-Dou_El_samur-i_invencible_Latino_07.mp4?rlkey=7oohz9q9r6pfhynd3mcbe0irv&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/nywu5q6ptj8z45dfpvhro/Baki-Dou-El-samur-i-invencible-Latino-08.mp4?rlkey=ckv0khzlcrfvrjwr044ezqd7v&st=" },
          { id: "t1e9", number: 9, src: "https://dl.dropbox.com/scl/fi/ellgph9a2b8jt5faqa44n/Baki-Dou_El_samur-i_invencible_Latino_09.mp4?rlkey=m5uh723a9nicc1zgh6io4oy8x&st=" },
          { id: "t1e10", number: 10, src: "https://dl.dropbox.com/scl/fi/8xqdpcb95gnxhvlrkq3t1/Baki-Dou_El_samur-i_invencible_Latino_10.mp4?rlkey=os0my2dl0odt741otwhhcucmf&st=" },
          { id: "t1e11", number: 11, src: "https://dl.dropbox.com/scl/fi/53fml6me5gt9sp5noyflo/Baki-Dou_El_samur-i_invencible_Latino_11.mp4?rlkey=egxfvqsivjxsqc6xk6z344bpb&st=" },
          { id: "t1e12", number: 12, src: "https://dl.dropbox.com/scl/fi/hyoxxty6w87w3z7vtibp7/Baki-Dou_El_samur-i_invencible_Latino_12.mp4?rlkey=obhhtdvkpgto40t7tjt2tqlh7&st=" },
          { id: "t1e13", number: 13, src: "https://dl.dropbox.com/scl/fi/vitnw17z6cv0mg8fkkruf/Baki-Dou_El_samur-i_invencible_Latino_13.mp4?rlkey=fows772f8xdtgmywybuvsi9g9&st=" },
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
        href: "View Peliculas/Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma VS Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg"
      },
      {
        id: "dragon_ball_z",
        titulo: "Dragon Ball Z",
        imagen: "https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"
      },
      {
        id: "hajime_no_ippo",
        titulo: "Hajime no ippo",
        imagen: "https://image.tmdb.org/t/p/w300/i3U3J2MWovIBZBnZYYiOLBXqNJZ.jpg"
      },
      {
        id: "naruto_shippuden",
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
    calificacion: "92%",
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
        href: "View Peliculas/Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura",
        titulo: "Baki Hanma VS Kengan Ashura",
        imagen: "https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg"
      },
      {
        id: "",
        titulo: "Los siete pecados capitales",
        imagen: "https://image.tmdb.org/t/p/w300/dWEKYfDkaTpx3BZsXbSnf0U9G4X.jpg"
      },
      {
        id: "dragon_ball_z",
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

  /*C*/

  chespitiro_sin_querer_queriendo: {
    id: "chespitiro_sin_querer_queriendo",
    titulo: "Chespirito: Sin querer queriendo",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/uWtq2F3IBBbpgUB1rEw5VDoeYD.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/bLyhzXAWvOn0L17NbCYP2aZ4sPt.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/uWtq2F3IBBbpgUB1rEw5VDoeYD.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "La historia de Roberto Gómez Bolaños, el hombre que transformó su anhelo de hacer reír en un legado universal. Desde su infancia hasta su consagración en las décadas de los 50 a los 80, esta biografía íntima nos revela cómo un joven soñador encontró su lugar en la naciente industria televisiva, creando personajes inolvidables cargados de ternura y humor.",
    anio: "2025",
    duracion: "51min",
    calificacion: "80%",
    genero: "Drama • Comedia",
    director: "Roberto Gómez Fernández",
    reparto: "Pablo Cruz Guerrero, Paulina Dávila, Bárbara López",
    estreno: "05/06/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2025,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/2rxn8bbjx7offllrzzddk/Chespirito-sin-querer-queriendo-T1-C1.mp4?rlkey=hjvi2jjx2oinq0hekxp875ds9&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/6sr2f4vypmw7r9iv0xtax/Chespirito-sin-querer-queriendo-T1-C2.mp4?rlkey=8jp6gc91xi9le5jo97g5dckot&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/8d92w87l1ignjd8mev0ff/Chespirito-sin-querer-queriendo-T1-C3.mp4?rlkey=2bojt7pyhqykmd3et8nlvldbi&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/kx7zbroyx4mso2je46syv/Chespirito-sin-querer-queriendo-T1-C4.mp4?rlkey=q3rfpfx6kes5co31bv4ur8q37&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/gc9b1435w1c1d72erjzzq/Chespirito-sin-querer-queriendo-T1-C5.mp4?rlkey=7gq2fl14jepxorqv7umw64kmo&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/v8xkbejc01pf2gn193z85/Chespirito-sin-querer-queriendo-T1-C6.mp4?rlkey=e0pu51p3yy1qs09g0lo2abyik&st=" },
          { id: "t1e6", number: 6, src: "" },
          { id: "t1e7", number: 7, src: "" },
          { id: "t1e8", number: 8, src: "" },
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

  /* D */

  dragon_ball_gt: {
    id: "dragon_ball_gt",
    titulo: "Dragon Ball Gt",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/erMkdaaYEyUqCSUEdYtqBtl63rK.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/pLYjbFYHOX1SrHs5BQsGlmv83lZ.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/gabEJZX7D10ufDYpZS2FUvHIYBP.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "La historia nos muestra cómo Goku volvía por una maldición a su más tierna infancia -aunque conservando todo su poder- y, acompañado por Trunks -hijo de Vegeta y Bulma- y Pan, nieta del propio protagonista, debía volver a su tamaño normal recuperando las siete Bolas de Dragón. Consta de varias etapas: Dragones, Baby Vegeta y Super-17.",
    anio: "1996",
    duracion: "24min",
    calificacion: "90%",
    genero: "Anime • Animación • Aventura • Accion",
    director: "Minoru Okazaki, Osamu Kasai",
    reparto: "José Luis Castañeda, Isabel Martiñón, Eduardo Borja, Ricardo",
    estreno: "07/02/1996",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 1996,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/5hqlo9auck67llnaqvdka/DR4G0N-B4LL-GT-01.mkv?rlkey=gayseglov9iipim8hv27nlave&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/0etlzf67ymreomb0zwxy2/DR4G0N-B4LL-GT-02.mkv?rlkey=ovstqzvy56aeo93qtntywphcp&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/3ivb6l3txk21co4jvri9u/DR4G0N-B4LL-GT-03.mkv?rlkey=8yo4jujtimdq8ev8mdbx70mhx&st=" },
          /* { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" }, */
        ]
      }
    ],

    recomendaciones: [
      {
        id: "dragon_ball_Z",
        titulo: "Dragon Ball z",
        imagen: "https://image.tmdb.org/t/p/w300/33JjW9m2cTxgOsTuFFt73oVCGwx.jpg"
      },
      {
        id: "dragon_ball_super",
        titulo: "Dragon Ball Super",
        imagen: "https://image.tmdb.org/t/p/w300/82RfjTfjdxxaIaqHJQl3wTDR4TO.jpg"
      },
      {
        id: "dragon_ball_daima",
        titulo: "Dragon Ball Daima",
        imagen: "https://image.tmdb.org/t/p/w300/uJw6nLCzQ8SftuCUJQNXTrvjlm4.jpg"
      },
      {
        id: "dragon_ball",
        titulo: "Dragon Ball",
        imagen: "https://image.tmdb.org/t/p/w300/9kUdy9ANzV2AIofwQR5NNpHpMgR.jpg"
      },
      {
        id: "dragon_ball_heroes",
        titulo: "Dragon Ball Heroes",
        imagen: "https://image.tmdb.org/t/p/w300/jYeTfpxS3IzgqKkYCjmdCKwq8PW.jpg"
      },
      {
        id: "dragon_ball_z_kai",
        titulo: "Dragon Ball Z: Kai",
        imagen: "https://image.tmdb.org/t/p/w300/je57jXdeWeJO9tGoWSJi4MCuXbN.jpg"
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
    anio: "2021",
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
        year: 2021,
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
        year: 2024,
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
        id: "t3",
        name: "Temporada 3",
        year: 2025,
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
        id: "avenida_brasil",
        titulo: "Avenida Brasil",
        imagen: "https://image.tmdb.org/t/p/w300/jgd86jJQGAl1GYThvd8oHLIy5AG.jpg"
      },
      {
        id: "chespitiro_sin_querer_queriendo",
        titulo: "Chespirito: Sin querer queriendo",
        imagen: "https://image.tmdb.org/t/p/w300/uYNsAlcCKP3bnRx9PpbAhPzU9ne.jpg"
      },
      {
        id: "the_godd_doctor",
        titulo: "The good doctor",
        imagen: "https://image.tmdb.org/t/p/w300/53P8oHo9cfOsgb1cLxBi4pFY0ja.jpg"
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

    poster: "https://image.tmdb.org/t/p/w780/eD3mMo87ekLJkTJQfvN5VXz4eYD.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/vQANo4LO7Hi57XxQqhRWeAZkD5h.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/v8xHeTkIBOl51yRp0zcAu2VYhdZ.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Cinco prisioneras forjan un vínculo único después de un accidente mortal... hasta que la corrupción y las guerras territoriales dentro de una prisión despiadada amenazan con destruirlas.",
    anio: "2026",
    duracion: "51min",
    calificacion: "87%",
    genero: "Crimen • Drama",
    director: "Sebastián Ortega",
    reparto: "Ana 6aribaldi, Valentina Zenere, Rita Cortese",
    estreno: "14/08/2025",
    idioma: "Argentina 🇦🇷",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2026,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/bawnnxf6ccjy765pzdsr1/En-el-barro-Cap-1.mp4?rlkey=xf38z7zwucx1747ox9ax1389z&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/x63tx11kljnw75sh82f6x/En-el-barro-Cap-2.mp4?rlkey=twb14o7mk2rwvp52yxet5z0qn&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/uf3d7ix814z61eqlwmkwp/En-el-barro-Cap-3.mp4?rlkey=ridj4j6adxlz7huiz98mchy8m&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/2e6fvycayb34l9g3bylnw/En-el-barro-Cap-4.mp4?rlkey=6rntz4zs9w6t5vq0z2b7lpt6r&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/obw8hyond30vefv8k913o/En-el-barro-Cap-5.mp4?rlkey=8ck166oplq3bwysq8o6dal45z&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/wdsoc61l9l9vajkmzlalo/En-el-barro-Cap-6.mp4?rlkey=9qqjsgflib7bqros0v48zlzxf&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/4xus4t4rc4qys6i0lwnvx/En-el-barro-Cap-7.mp4?rlkey=3piczo9bsm6fh3u8hemubjyii&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/0qqcolegs007jvbqrl7v2/en-el-barro-T1C8.mp4?rlkey=wwq2ac6dwpp91c4p3fh42izoq&st=" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2026,
        episodes: [
          { id: "t2e1", number: 1, src: "https://dl.dropbox.com/scl/fi/018ffqjfclhn36382rlyw/en-el-barro-T2C1.mp4?rlkey=z2rovjoxpyspohzo30378dbn0&st=" },
          { id: "t2e2", number: 2, src: "https://dl.dropbox.com/scl/fi/0imuhs10rho7f3621ytqx/en-el-barro-T2C2.mp4?rlkey=li9x92c7kqxmkxi59ulrlilkv&st=" },
          { id: "t2e3", number: 3, src: "https://dl.dropbox.com/scl/fi/x6hwp2ahho3pxorna32e1/en-el-barro-T2C3.mp4?rlkey=tf0bmdzafm9gh933yg97vsu6r&st=" },
          { id: "t2e4", number: 4, src: "https://dl.dropbox.com/scl/fi/ei6nkmgem6yx3knnac207/en-el-barro-T2C4.mp4?rlkey=mr47fw5okgr7hmdvwi1yjrzds&st=" },
          { id: "t2e5", number: 5, src: "https://dl.dropbox.com/scl/fi/zw4vdxcg067z1z7wbmvs8/en-el-barro-T2C5.mp4?rlkey=fuvnuf2hp5z9rzkd8umqb03tt&st=" },
          { id: "t2e6", number: 6, src: "https://dl.dropbox.com/scl/fi/pi3hre0zapyxjqyxsy9bz/en-el-barro-T2C6.mp4?rlkey=bxk0poe5kylseofg6e3j9x01r&st=" },
          { id: "t2e7", number: 7, src: "https://dl.dropbox.com/scl/fi/tkwk8pjg6mvmiopclf0ll/en-el-barro-T2C7.mp4?rlkey=ikln84rb7qfadn7pqqqdnnajc&st=" },
          { id: "t2e8", number: 8, src: "https://dl.dropbox.com/scl/fi/8u0hulw2j4afx76epeome/en-el-barro-T2C8.mp4?rlkey=0wuo90yna13lgr6apnn15n2k3&st=" }
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "El Marginal",
        imagen: "https://image.tmdb.org/t/p/w300/eAy2tSlGtzseWfYemt398QrPtf2.jpg"
      },
      {
        id: "",
        titulo: "The Walking Dead",
        imagen: "https://image.tmdb.org/t/p/w300/9iYinsg30olSCuDoH8VxtRN5gZx.jpg"
      },
      {
        id: "",
        titulo: "Chucky",
        imagen: "https://image.tmdb.org/t/p/w300/kY0BogCM8SkNJ0MNiHB3VTM86Tz.jpg"
      },
      {
        id: "",
        titulo: "Duki: Desde el fin del mundo",
        imagen: "https://image.tmdb.org/t/p/w300/9CSTzX1pUrNLD7lsJ8h9hRFJtLQ.jpg"
      },
      {
        id: "",
        titulo: "Dragon Ball Z",
        imagen: "https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"
      },
      {
        id: "",
        titulo: "Dr.House",
        imagen: "https://image.tmdb.org/t/p/w300/wfxsizfb7NV9uwy9QCqqvfR20F2.jpg"
      }
    ]
  },
  
  /* F */

  from: {
    id: "from",
    titulo: "FROM",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/seddLBvNOW9QK3j8uaJT0CSkVhI.jpg",
    imagen: "https://image.tmdb.org/t/p/original/cjXLrg4R7FRPFafvuQ3SSznQOd9.jpg",
    imgserie: "https://image.tmdb.org/t/p/original/32y5F0HdA4mzgcG9eiHrlYz7jSu.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Desvela el misterio de un pueblo de pesadilla en el centro de Norteamérica que atrapa a todos los que entran. Mientras los residentes involuntarios luchan por mantener una sensación de normalidad y buscan una salida, también deben sobrevivir a las amenazas del bosque circundante, incluidas las aterradoras criaturas que salen cuando se pone el sol.",
    anio: "2022",
    duracion: "53min",
    calificacion: "83%",
    genero: "Drama • Misterio • Fantasía",
    director: "John Griffin",
    reparto: "Harold Perrineau, Eion Bailey, Pegah Ghafoori",
    estreno: "19/04/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2022,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/u27blh0lj225x8v3zjliw/From.S01e01.1080P-Dual-Lat.mp4?rlkey=xlab17yk6uq20hh6b3x4ledrr&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/heqwhfg02c1uz472hduap/From.S01e02.1080P-Dual-Lat.mp4?rlkey=slfg2xusd3l6ldehqfosdtkuk&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/ymocsm45sgasnri5ln4d5/From.S01e03.1080P-Dual-Lat.mp4?rlkey=og1okqettbgsee2un4tuc4z6k&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/u127d55py3a0mj123zhto/From.S01e04.1080P-Dual-Lat.mp4?rlkey=fzs8zywhpsg6tuuo7walb0j62&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/9qu6sutxqx2hma3z341x8/From.S01e05.1080P-Dual-Lat.mp4?rlkey=6ftx15dc93wastublk1uw4xrh&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/2v55xe8e043yfk77z8i5o/From.S01e06.1080P-Dual-Lat.mp4?rlkey=vhmc7ib8177vgr8soi3b1u0im&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/kaq6aqtrqrj7zbic8th86/From.S01e07.1080P-Dual-Lat.mp4?rlkey=t8y9xltacd4yvw45nilgb8n29&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/eo4p185ynqaaop33evx3q/From.S01e08.1080P-Dual-Lat.mp4?rlkey=hr43yfk9mml6d5yh3jjyngfiv&st=" },
          { id: "t1e9", number: 9, src: "https://dl.dropbox.com/scl/fi/b88wyvrui5fz18wuserr3/From.S01e09.1080P-Dual-Lat.mp4?rlkey=eze1kjpwmzijqrw7ph0fozhwh&st=" },
          { id: "t1e10", number: 10, src: "https://dl.dropbox.com/scl/fi/53zitzye7vusdsyqcq2a6/From.S01e10.1080P-Dual-Lat.mp4?rlkey=ljtwc3r1kay6xqbc9dn11ag3d&st=" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2023,
        episodes: [
          { id: "t2e1", number: 1, src: "https://dl.dropbox.com/scl/fi/ao0mr4hq9iuytw05qfg65/From.S02e01.2023.1080P-Dual-Lat.mp4?rlkey=nvw5vdc16t7glv6m8frbv2wz4&st=" },
          { id: "t2e2", number: 2, src: "https://dl.dropbox.com/scl/fi/lwe6xlztbv05jum04mk9e/From.S02e02.2023.1080P-Dual-Lat.mkv?rlkey=c29hi7k0ktwmq7k7soqrfi0hi&st=" },
          { id: "t2e3", number: 3, src: "https://dl.dropbox.com/scl/fi/0ib1k2dbt2u01ewjd01rh/From.S02e03.2023.1080P-Dual-Lat.mkv?rlkey=leg8q8t655u9efgqo3pykhm0r&st=" },
          { id: "t2e4", number: 4, src: "https://dl.dropbox.com/scl/fi/ix8w2qfpq7xq5rra9t6rr/From.S02e04.2023.1080P-Dual-Lat.mkv?rlkey=ulzltvr5f2ohzfqrktq44cwbe&st=" },
          { id: "t2e5", number: 5, src: "https://dl.dropbox.com/scl/fi/861rh8rpimtlp0o040eui/From.S02e05.2023.1080P-Dual-Lat.mkv?rlkey=unjrt38rqn0h3rnpljl8e7ysd&st=" },
          { id: "t2e6", number: 6, src: "https://dl.dropbox.com/scl/fi/rrcjt3drrza3sex7nu86u/From.S02e06.2023.1080P-Dual-Lat.mkv?rlkey=erxz6r7xjt0l0i5i08y36ahra&st=" },
          { id: "t2e7", number: 7, src: "https://dl.dropbox.com/scl/fi/popm735v60o0k8tbqbksx/From.S02e07.2023.1080P-Dual-Lat.mkv?rlkey=kz21sspa2rt9lme6d6yerddgr&st=" },
          { id: "t2e8", number: 8, src: "https://dl.dropbox.com/scl/fi/n8zqgi2mtm5qg8kk3f926/From.S02e08.2023.1080P-Dual-Lat.mkv?rlkey=v3t7qy6fumydegrvhnm11yw8u&st=" },
          { id: "t2e9", number: 9, src: "https://dl.dropbox.com/scl/fi/d8df3b4oszir99nx3586s/From.S02e09.2023.1080P-Dual-Lat.mkv?rlkey=2dxva5cs0hi8b86m4dyb6zloo&st=" },
          { id: "t2e10", number: 10, src: "https://dl.dropbox.com/scl/fi/dkz0103byxtpx1rckuwug/From.S02e10.2023.1080P-Dual-Lat.mkv?rlkey=cmr5y26mzsm2bv5me1i9irjbz&st=" }
        ]
      },
      {
        id: "t3",
        name: "Temporada 3",
        year: 2024,
        episodes: [
          { id: "t3e1", number: 1, src: "https://dl.dropbox.com/scl/fi/lba89efzw6ndt0n30c28a/Frmf14jh651dfgvbn32l3x1.mkv?rlkey=z01krawde1adky9c374zee8zf&st=" },
          { id: "t3e2", number: 2, src: "https://dl.dropbox.com/scl/fi/v4q71kuxqb64xnn5gtjml/Frmf14jh651dfgvbn32l3x2.mkv?rlkey=vz8xy31mg5l0v6v5d2hwcxz1d&st=" },
          { id: "t3e3", number: 3, src: "https://dl.dropbox.com/scl/fi/0sqzpql016w7ecexnj9ps/Frmf14jh651dfgvbn32l3x3.mkv?rlkey=r5kcq3yx77hpqg6zwda3zp3y8&st=" },
          { id: "t3e4", number: 4, src: "https://dl.dropbox.com/scl/fi/zwyr5cu5bjkhqbdvg6am4/Frmf14jh651dfgvbn32l3x4.mkv?rlkey=qg8vgs0wg5wei89gd3qhyg9tv&st=" },
          { id: "t3e5", number: 5, src: "https://dl.dropbox.com/scl/fi/qex4ud3kcye7rejz9myrg/Frmf14jh651dfgvbn32l3x5.mkv?rlkey=d3br8o0wqnxgzulr20nrg3zf7&st=" },
          { id: "t3e6", number: 6, src: "https://dl.dropbox.com/scl/fi/b0nj0a2mia8x7ofsjd6hy/Frmf14jh651dfgvbn32l3x6.mkv?rlkey=2n110g93zzeoh0r7q05g5a90r&st=" },
          { id: "t3e7", number: 7, src: "https://dl.dropbox.com/scl/fi/jrsjxsku8a10fq3sts1kd/Frmf14jh651dfgvbn32l3x7.mkv?rlkey=kniw5h9qmjro4lunt9nh020cm&st=" },
          { id: "t3e8", number: 8, src: "https://dl.dropbox.com/scl/fi/39ee8sbbx680lt7pdyxlh/Frmf14jh651dfgvbn32l3x8.mkv?rlkey=gw8f6nkumt7labwm4ewzfzpvu&st=" },
          { id: "t3e9", number: 9, src: "https://dl.dropbox.com/scl/fi/bmswjxfup4cyhvg3nma3u/Frmf14jh651dfgvbn32l3x9.mkv?rlkey=76rzrtjv21z7tngfcbg85wgj3&st=" },
          { id: "t3e10", number: 10, src: "https://dl.dropbox.com/scl/fi/b43p6tmff0welp5xa9qdq/Frmf14jh651dfgvbn32l3x10.mkv?rlkey=vvf4wd330ldhh4jmi3zae401s&st=" }
        ]
      },
      {
        id: "t4",
        name: "Temporada 4",
        year: 2026,
        episodes: [
          { id: "t4e1", number: 1, src: "" },
          /* { id: "t4e2", number: 2, src: "" },
          { id: "t4e3", number: 3, src: "" },
          { id: "t4e4", number: 4, src: "" },
          { id: "t4e5", number: 5, src: "" } */
        ]
      }
    ],

    recomendaciones: [
      {
        id: "",
        titulo: "The Walking Dead",
        imagen: "https://image.tmdb.org/t/p/w300/9iYinsg30olSCuDoH8VxtRN5gZx.jpg"
      },
      {
        id: "",
        titulo: "Chespirito: Sin querer queriendo",
        imagen: "https://image.tmdb.org/t/p/w300/bLyhzXAWvOn0L17NbCYP2aZ4sPt.jpg"
      },
      {
        id: "",
        titulo: "La maldición de Hill House",
        imagen: "https://image.tmdb.org/t/p/w300/y4D0MkSEYeEgAIqQK9GjQtiUZXH.jpg"
      },
      {
        id: "",
        titulo: "Chucky",
        imagen: "https://image.tmdb.org/t/p/w300/sdCJbGkvnIsIKLxaFQrviriODVq.jpg"
      },
      {
        id: "",
        titulo: "Archivo 81",
        imagen: "https://image.tmdb.org/t/p/w300/rLgOasUfugmhshlhURKKULDEdrB.jpg"
      },
      {
        id: "",
        titulo: "Marianne",
        imagen: "https://image.tmdb.org/t/p/w300/9ycFxQF8bwK2ZkHlBdzea0aoQEU.jpg"
      }
    ]
  },

  /*G*/

  genesis: {
    id: "genesis",
    titulo: "Genesis",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/eu6gHTDUWSKwT2sgoIYBRB9S6v7.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/8hUZa9LzC4vyQiwX8KadKLIBXWg.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/eu6gHTDUWSKwT2sgoIYBRB9S6v7.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "La novela está basada en el libro de Génesis en la Biblia y cuenta la historia de la creación del mundo, el primer hombre, Adán, y la primera mujer, Eva; el gran diluvio; la Torre de Babel, el viaje de Abraham, y llega hasta el período de esclavitud del pueblo hebreo en Egipto.",
    anio: "2022",
    duracion: "48min",
    calificacion: "87%",
    genero: "Drama • Biblico • Historia",
    director: "Camilo Pellegrini, Raphaela Castro, Stephanie Ribeiro",
    reparto: "Juliano Laham, Ingra Lyberato, Petrônio Gontijo",
    estreno: "22/08/2022",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2022,
        episodes: [
          { id: "t1e1", number: 1, src: "https://www.febspot.com/get_file/27/906ccdf946c8b8d2567f8c2208cf3eac/2426000/2426645/2426645_720p.mp4" },
          { id: "t1e2", number: 2, src: "https://www.febspot.com/get_file/27/e1df7fe88c58a3163c9bc169d0ebc6fb/2429000/2429993/2429993_720p.mp4" },
          { id: "t1e3", number: 3, src: "https://www.febspot.com/get_file/27/e79a4998c919166677dfba622cbe755a/2431000/2431074/2431074_720p.mp4" },
          { id: "t1e4", number: 4, src: "https://www.febspot.com/get_file/27/d541dcab47636399f7f1d24574109108/2431000/2431169/2431169_720p.mp4" },
          { id: "t1e5", number: 5, src: "https://www.febspot.com/get_file/27/e22f0e23dd8df3dc39049d44dc4497ce/2431000/2431220/2431220_720p.mp4" },
          { id: "t1e6", number: 6, src: "https://www.febspot.com/get_file/25/6ae5a543760c57d995e700816b7732c5/2449000/2449182/2449182_720p.mp4" },
          { id: "t1e7", number: 7, src: "https://www.febspot.com/get_file/22/8720414e34f4fbffe303fa6b2eb87829/2452000/2452640/2452640_720p.mp4" },
          { id: "t1e8", number: 8, src: "https://www.febspot.com/get_file/22/55409aa1cb73134a657658e64af53fbc/2452000/2452850/2452850_720p.mp4" },
          { id: "t1e9", number: 9, src: "https://www.febspot.com/get_file/5/0a6d1de6f40f212ea4f44c8db1546d4f/2495000/2495456/2495456_720p.mp4" },
          { id: "t1e10", number: 10, src: "https://www.febspot.com/get_file/5/dcb42d4db3562f941c5dea2b0c22784a/2495000/2495374/2495374_720p.mp4" },
          { id: "t1e11", number: 11, src: "https://www.febspot.com/get_file/20/e8776101a13398b70d78429d19bceed1/1440000/1440968/1440968_720p.mp4" },
          { id: "t1e12", number: 12, src: "https://www.febspot.com/get_file/20/641a3c32109a5cfa7d9fd549e40178ac/1440000/1440984/1440984_720p.mp4" },
          { id: "t1e13", number: 13, src: "https://www.febspot.com/get_file/20/f83cc251db7939647da7df24e4f0efeb/1440000/1440986/1440986_720p.mp4" },
          { id: "t1e14", number: 14, src: "https://www.febspot.com/get_file/20/7c78c7864b9f49b09c6fe39ace0673ea/1440000/1440996/1440996_720p.mp4" },
          { id: "t1e15", number: 15, src: "https://www.febspot.com/get_file/20/e11032470f71a417dc133fdf11f9a834/1440000/1440998/1440998_720p.mp4" },
          { id: "t1e16", number: 16, src: "https://www.febspot.com/get_file/20/56bbe3361557c92d9cb6fe86eb2f8917/1445000/1445322/1445322_720p.mp4" },
          { id: "t1e17", number: 17, src: "https://www.febspot.com/get_file/20/2080b5e7a7d3342937305a423e3c4501/1445000/1445334/1445334_720p.mp4" },
          { id: "t1e18", number: 18, src: "https://www.febspot.com/get_file/20/885a8f2317061f5b50bf349bebefb82f/1445000/1445336/1445336_720p.mp4" },
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

  /* I */

  it_bienvenido_a_derry: {
    id: "it_bienvenido_a_derry",
    titulo: "It: Bienvenidos a Derry",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/2fOKVDoc2O3eZmBZesWPuE5kgPN.jpg",
    imagen: "https://image.tmdb.org/t/p/original/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg",
    imgserie: "https://image.tmdb.org/t/p/original/j4Dv5ILvxA2awirOZenMIYR5yqi.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "En 1962, una pareja y su hijo se mudan a Derry, Maine, justo cuando un niño desaparece. Con su llegada, comienzan a suceder cosas muy malas en el pueblo.",
    anio: "2025",
    duracion: "1h",
    calificacion: "84%",
    genero: "Terror • Misterio",
    director: "Andy Muschietti",
    reparto: "Taylou Paige, Jovan Adepo, James Remar",
    estreno: "26/10/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2025,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/y9l8yjg5fp3bqq3szksik/301880-cd79f023-f7e7-4a02-a405-5364c328960d-cgfc-2660727-streamwish.mp4?rlkey=zfcf9lahx4wk9yzw4q99m8314&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/crus6hguloppn8e0nbr87/It.Bienvenido.A.Derry.S01e02.2025.1080P-Dual-Lat.mkv?rlkey=3bxjupbgq7terofstyadw78l3&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/1gli63fuyiix8jk5vept3/It-Bienvenido-a-derry-T1E3.mp4?rlkey=ygvz6xgkz0rojupghwbyb9g3n&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/jav54823p6tvoxwo34gng/It-Bienvenido-a-derry-T1E4.mp4?rlkey=nl2qid8plx25nm6teitpwc1en&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/6su9nz6aw01yxag7yf508/It-Bienvenido-a-derry-T1E5.mp4?rlkey=j1lbiwtgbkmfr49ln0utkpoup&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/ze9rqfdib6y4bls91p5mt/It-Bienvenido-a-Derry-T1E6.mp4?rlkey=43j35kuemejwl1nrnuu9k3hwt&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/h6wpk6x418i79p5kzupio/It-Bienvenido-a-Derry-T1E7.mp4?rlkey=qae1icg0osuxu8qpu1rqf48ny&st=" },
          { id: "t1e8", number: 8, src: "https://dl.dropbox.com/scl/fi/yt89fwfim0l8bxppz300i/It.Bienvenidos.A.Derry.S01e08.2025.1080p-dual-lat.mkv?rlkey=boag8vagzf96enahjk0s6h8hq&st=" }      
        ]
      }
    ],

    recomendaciones: [
      {
        href: "View Peliculas/Reproductor Universal.php?id=it_2017",
        titulo: "It (Eso)",
        imagen: "https://image.tmdb.org/t/p/w300/kI2c93Ybv8C8seSJunNSlpepFPX.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=it_capitulo_2",
        titulo: "It: Capitulo 2",
        imagen: "https://image.tmdb.org/t/p/w300/pxw6j2AwlUsw5iS4fCxPoCP0jPh.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=el_payaso_del_mal",
        titulo: "El payaso del mal",
        imagen: "https://image.tmdb.org/t/p/w300/o4bNaBX6COOzgvWQGLSNuIbztvF.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=it_terrifier",
        titulo: "Terrifier",
        imagen: "https://image.tmdb.org/t/p/w300/ju10W5gl3PPK3b7TjEmVOZap51I.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=until_dawn_noche_de_terror",
        titulo: "Until Dawn: Noche de terror",
        imagen: "https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"
      },
      {
        href: "View Peliculas/Reproductor Universal.php?id=hablame",
        titulo: "Háblame",
        imagen: "https://image.tmdb.org/t/p/w300/rS8fjd6dYcf64v3ZhAE6fKrxoaF.jpg"
      }
    ]
  },

  invencible: {
    id: "invencible",
    titulo: "Invencible",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/original/dfmPbyeZZSz3bekeESvMJaH91gS.jpg",
    imagen: "https://image.tmdb.org/t/p/original/zCgPbsPJ7d1qlXVn1cKvTlcob1H.jpg",
    imgserie: "https://image.tmdb.org/t/p/original/g0qI829YWXmgUSYPNZXlOq62k7X.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Mark Grayson es un adolescente normal, excepto por el hecho de que su padre es el superhéroe más poderoso del planeta. Poco después de su decimoséptimo cumpleaños, Mark comienza a desarrollar sus propios poderes y entra en la tutela de su padre.",
    anio: "2021",
    duracion: "45min",
    calificacion: "92%",
    genero: "Anime • Acción • Animación • Aventura",
    director: "Dan Duncan",
    reparto: "Emilio Treviño, Humberto Solórzano, Nycolle González",
    estreno: "26/032021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2021,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
          { id: "t1e6", number: 6, src: "" },
          { id: "t1e7", number: 7, src: "" },
          { id: "t1e8", number: 8, src: "" }
        ]
      },
      {
        id: "t2",
        name: "Temporada 2",
        year: 2023,
        episodes: [
          { id: "t2e1", number: 1, src: "" },
          { id: "t2e2", number: 2, src: "" },
          { id: "t2e3", number: 3, src: "" },
          { id: "t2e4", number: 4, src: "" },
          { id: "t2e5", number: 5, src: "" },
          { id: "t2e6", number: 6, src: "" },
          { id: "t2e7", number: 7, src: "" },
          { id: "t2e8", number: 8, src: "" }
        ]
      },

      {
        id: "t3",
        name: "Temporada 3",
        year: 2025,
        episodes: [
          { id: "t3e1", number: 1, src: "" },
          { id: "t3e2", number: 2, src: "" },
          { id: "t3e3", number: 3, src: "" },
          { id: "t3e4", number: 4, src: "" },
          { id: "t3e5", number: 5, src: "" },
          { id: "t3e6", number: 6, src: "" },
          { id: "t3e7", number: 7, src: "" },
          { id: "t3e8", number: 8, src: "" }
        ]
      },
      {
        id: "t4",
        name: "Temporada 5",
        year: 2026,
        episodes: [
          { id: "t4e1", number: 1, src: "" },
          { id: "t4e2", number: 2, src: "" },
          { id: "t4e3", number: 3, src: "" },
          { id: "t4e4", number: 4, src: "" },
          { id: "t4e5", number: 5, src: "" },
          { id: "t4e6", number: 6, src: "" },
          { id: "t4e7", number: 7, src: "" },
          { id: "t4e8", number: 8, src: "" }
        ]
      },
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

  /* M */

  moises_y_los_diez_mandamientos: {
    id: "moises_y_los_diez_mandamientos",
    titulo: "Moisés y los Diez Mandamientos",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/cvaXVVVHi7TNv2HPJwQLTsoilbb.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/spMIIipBp3sz24zIG1oXgGFfcNZ.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/iKKg8ekErwjnHU38imBsbxJuj0E.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Conoce la historia de Moisés, siguiendo la conocida historia de las plagas y mandamientos, y entretejiendo otras historias de los hebreos y egipcios que influyeron en su vida.",
    anio: "2015",
    duracion: "1h",
    calificacion: "90%",
    genero: "Drama • Biblico",
    director: "Vivian de Oliveira",
    reparto: "Guilherme Winter, Giselle Itié, Marcela Barrozo",
    estreno: "23/03/2015",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
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
          { id: "t1e9", number: 9, src: "https://cdnapisec.kaltura.com/p/2657331/sp/265733100/playManifest/entryId/1_qt9h3ys4/format/applehttp/protocol/https/flavorIds/1_ergmxfln,1_usnvtc4q,1_unwfdjjc,1_9bsmgdcw,1_f58nkxpa,1_t3p1zc32,1_lbul3fhx,1_r1l7cqvs/preferredBitrate/1500/maxBitrate/10000/a.m3u8" },
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
      }
    ],

    recomendaciones: [
      {
        id: "genesis",
        titulo: "Genesis",
        imagen: "https://image.tmdb.org/t/p/w300/8hUZa9LzC4vyQiwX8KadKLIBXWg.jpg"
      },
      {
        id: "rey_david",
        titulo: "Rey David",
        imagen: "https://image.tmdb.org/t/p/w300/5sWqEJSGfLq6ssXFexDAB5PdopR.jpg"
      },
      {
        id: "josue_y_la_tierra_prometida",
        titulo: "Josue y la tierra prometida",
        imagen: "https://image.tmdb.org/t/p/w300/ciY0RaYIzKwt2nEQfVAXJyoxv3c.jpg"
      },
      {
        id: "el_rico_y_lazaro",
        titulo: "El rico y Lazaro",
        imagen: "https://image.tmdb.org/t/p/w300/k6AxLEM3kBXbQv1mTLWXt4dLs8V.jpg"
      },
      {
        id: "jesus",
        titulo: "Jesús",
        imagen: "https://image.tmdb.org/t/p/w300/9dkO9nB20g3lkiWgxj7jxg3QDFk.jpg"
      },
      {
        id: "jose_de_egipto",
        titulo: "Jose de egipto",
        imagen: "https://image.tmdb.org/t/p/w300/1BuIBHKlBqhyjF3qpupd2enVjNT.jpg"
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

  /*U*/

  una_buena_familia_americana: {
    id: "una_buena_familia_americana",
    titulo: "Una buena familia americana",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/sdn6qjAATvVBUU7ZcGgoMUPwFoC.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/aIAdaQ0R9G75h3iCckaoxQrHRH.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/dzfkaCKnBaxPJTRXrJiQmb508eS.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Esta serie dramática, contada desde varios puntos de vista para tratar de plasmar todas las perspectivas, prejuicios y traumas, está inspirada por la inquietante historia de una pareja del Medio Oeste estadounidense que adopta a una niña con un tipo de enanismo raro.",
    anio: "2025",
    duracion: "51min",
    calificacion: "81%",
    genero: "Drama",
    director: "Katie Robbins",
    reparto: "Ellen Pompeo, Mark Duplass, Imogen Faith Reid",
    estreno: "19/03/2025",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2025,
        episodes: [
          { id: "t1e1", number: 1, src: "https://dl.dropbox.com/scl/fi/u7of1bnagiofdsgwbmzit/Una-buena-familia-america-1.mp4?rlkey=kimbt8muoj5vv4pj4o4axtmwy&st=" },
          { id: "t1e2", number: 2, src: "https://dl.dropbox.com/scl/fi/5iire9gbcz6y69vc16kkw/Una-buena-familia-america-2.mp4?rlkey=0n5yoy0a748yq6njvg8mmzs5f&st=" },
          { id: "t1e3", number: 3, src: "https://dl.dropbox.com/scl/fi/jvxwfz71m3k71ykpbbwev/Una-buena-familia-america-3.mp4?rlkey=kqdexbx32bks08pwjnen58yeq&st=" },
          { id: "t1e4", number: 4, src: "https://dl.dropbox.com/scl/fi/zkwd4tfni113hi5c0g2cq/Una-buena-familia-americana-4.mp4?rlkey=2bvnmnt0jl54itrq2ug5by5db&st=" },
          { id: "t1e5", number: 5, src: "https://dl.dropbox.com/scl/fi/2hijinz3bmhr8leyw2l6q/Una-buena-familia-americana-5.mp4?rlkey=chhqdddlo6zqzxs4llzkhe5cx&st=" },
          { id: "t1e6", number: 6, src: "https://dl.dropbox.com/scl/fi/spjwe4bv0i0pp1588ay0x/Una-buena-familia-americana-6.mp4?rlkey=2ay0so9gcmctn0p52b86g1md1&st=" },
          { id: "t1e7", number: 7, src: "https://dl.dropbox.com/scl/fi/u1u1gtcc5vb3wjqnftn3s/Una-buena-familia-7.mp4?rlkey=v0nuwy21cgzibttfrs2sc20g5&st=" },
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
function saveLocalProgress() {
  localStorage.setItem(PROGRESS_KEY, JSON.stringify(episodeProgress));
}

function getEpisodeKey() {
  if (!currentSeason || !currentEpisode) return null;
  return `t${currentSeason.id}e${currentEpisode.id}`;
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
const PROGRESS_KEY = `progress_${movieId}`;
let episodeProgress = JSON.parse(localStorage.getItem(PROGRESS_KEY)) || {};
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

const epKey = `t${season.id}e${savedEpisodeId}`;

resumeTime =
  episodeProgress[epKey] ||  // 🔥 LOCAL MANDA
  parseFloat(progreso.tiempo) || 0;

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

function updateSeasonActiveUI() {
  document.querySelectorAll(".season-item").forEach(btn => {
    const isActive = btn.textContent === currentSeason.name;
    btn.classList.toggle("active", isActive);
  });
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
  updateRainbowProgress();

  currentTimeEl.textContent = "0:00";
  durationEl.textContent = "0:00";

  lastSavedTime = 0;
  resumeTime = 0;
  isSeeking = false;

  btnPlay.textContent = "▶";
  overlay.classList.remove("hide");
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

  // 🔥 GUARDAR PROGRESO DEL EPISODIO ANTERIOR
  const prevKey = getEpisodeKey();

if (prevKey && video.duration && video.currentTime < (video.duration - 2)) {
  // 👉 SOLO guarda si NO terminó el episodio
  episodeProgress[prevKey] = video.currentTime;
  saveLocalProgress();
} else if (prevKey) {
  // 👉 si terminó → BORRAR
  delete episodeProgress[prevKey];
  saveLocalProgress();
}

  // 🔥 CAMBIAR EPISODIO
  setActiveEpisode(ep);
  currentEpisode = ep;

  // 🔥 RESET UI
  progress.value = 0;
  updateRainbowProgress();

  currentTimeEl.textContent = "0:00";
  durationEl.textContent = "0:00";

  lastSavedTime = 0;
  isSeeking = false;

  // 🔥 OBTENER TIEMPO GUARDADO (LOCAL)
  const epKey = `t${currentSeason.id}e${ep.id}`;
  resumeTime = episodeProgress[epKey] || 0;

  // 🔥 GUARDAR EPISODIO ACTUAL
  lastEpisodeBySeason[currentSeason.id] = ep.id;
  saveSeasonProgress();

  // 🔥 GUARDAR EN BD
  guardarProgresoDB();

  // 🔥 CARGAR VIDEO
  video.src = ep.src;
  video.load();

  videoLoaded = true;

  // 🔥 APLICAR RESUME (FIX REAL)
  video.onloadedmetadata = () => {
    if (resumeTime > 0) {
      video.currentTime = resumeTime;
    }
    video.play();
  };

  btnPlay.textContent = "❚❚";
  overlay.classList.add("hide");

  requestAnimationFrame(() => {
    scrollToActiveEpisode();
  });
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

  // 🔥 BD
  guardarProgresoDB();

  // 🔥 LOCAL (NUEVO)
  const epKey = getEpisodeKey();
  if (epKey) {
    episodeProgress[epKey] = video.currentTime;
    saveLocalProgress();
  }

  lastSavedTime = video.currentTime;
}
  });

  video.addEventListener("ended", () => {

  // 🔥 BORRAR PROGRESO LOCAL DEL EP ACTUAL
  const epKey = getEpisodeKey();
  if (epKey) {
    delete episodeProgress[epKey];
    saveLocalProgress();
  }

  // 🔥 GUARDAR EN BD (por última vez en 0)
  guardarProgresoDB();

  // 🔥 IR AL SIGUIENTE EPISODIO
  const episodes = currentSeason.episodes || [];
  const index = episodes.findIndex(e => e.id === currentEpisode.id);
  const next = episodes[index + 1];

  if (!next) {
    console.log("No hay más episodios");
    return;
  }

  // 🔥 SIMULAR CLICK REAL (SOLUCIÓN CLAVE)
  const nextCard = document.querySelector(`.episode-box[data-id="${next.id}"]`);
  if (nextCard) {
    nextCard.click();
  }
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
document.addEventListener("DOMContentLoaded", () => {

  // 🔹 Obtener movie correctamente
  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");
  const movie = typeof MOVIES_DB !== "undefined" ? MOVIES_DB[movieId] : null;

  if (!movie) {
    console.warn("Movie no encontrada");
    return;
  }

  // 🔹 Función modal
  function mostrarModalFavoritos(mensaje) {
    const modal = document.getElementById('modal-favoritos');
    const texto = document.getElementById('modal-fav-texto');
    const btnAceptar = document.getElementById('modal-fav-aceptar');

    if (!modal || !texto || !btnAceptar) return;

    texto.textContent = mensaje;
    modal.setAttribute('aria-hidden', 'false');

    let cerrarTimeout = setTimeout(cerrarModal, 15000);

    function cerrarModal() {
      modal.setAttribute('aria-hidden', 'true');
      clearTimeout(cerrarTimeout);
    }

    btnAceptar.onclick = cerrarModal;

    const backdrop = modal.querySelector('.modal-fav-backdrop');
    if (backdrop) backdrop.onclick = cerrarModal;
  }

  // 🔹 Botón favoritos
  const btn = document.getElementById('btn-favorito');
  if (!btn) return;

  btn.addEventListener('click', () => {

    const id = movie.id.toLowerCase();
    const titulo = movie.titulo || movie.id.replace(/_/g," ");

    // 🔥 FIX IMPORTANTE (imagen fallback)
    const imagen = movie.imagen || movie.poster || "";

    // 🔥 FIX tipo correcto
    const tipo = movie.type || "series";

    fetch("guardar_favorito.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body:
        "movie_id=" + encodeURIComponent(id) +
        "&titulo=" + encodeURIComponent(titulo) +
        "&imagen=" + encodeURIComponent(imagen) +
        "&tipo=" + encodeURIComponent(tipo)
    })

    .then(res => res.json())
    .then(data => {

      if (data.status === "success") {
        mostrarModalFavoritos("Serie agregada a favoritos");

        setTimeout(() => {
          window.location.href = "favoritos.php";
        }, 1500);
      }

      else if (data.status === "exists") {
        mostrarModalFavoritos("Esta serie ya está en favoritos");
      }

      else if (data.status === "error") {
        mostrarModalFavoritos("Debes iniciar sesión");
      }

      else {
        mostrarModalFavoritos("Error inesperado");
      }

    })
    .catch(() => {
      mostrarModalFavoritos("Error al guardar favorito");
    });

    // 🔥 Animación botón
    btn.classList.add('animado');
    setTimeout(() => btn.classList.remove('animado'), 300);

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

  <div class="series-grid" id="recomendaciones-grid">
    <!-- JS inserta aquí -->
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