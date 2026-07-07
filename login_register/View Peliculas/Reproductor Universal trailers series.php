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
$foto   = !empty($user['foto']) ? $user['foto'] : 'Logo/Logo Nuevo.png';


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
  <link rel="icon" type="image/png" href="../Logo/Logo Nuevo.png">
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
      <img src="../Logo/Logo Nuevo.png" alt="Logo MovieTx" class="loader-logo">
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
    console.log("trailer no encontrada");
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

// ENVIAR PING CADA 30 SEGUNDOS
setInterval(() => {

    fetch("ping.php")
    .catch(() => {});

}, 30000);

</script>
  

  <script>
const MOVIES_DB = {
  
  /* A */

  nombre_de_serie: {
    id: "nombre_de_serie",
    titulo: "nombre de serie",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "0000",
    duracion: "00min",
    calificacion: "00%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 0",
        year: 0000,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
          { id: "t1e6", number: 6, src: "" }
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
    titulo: "nombre de serie",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "0000",
    duracion: "00min",
    calificacion: "00%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 0",
        year: 0000,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
          { id: "t1e6", number: 6, src: "" }
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

  /*I*/

  invencible: {
    id: "invencible",
    titulo: "Trailer Invencible",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/Adp5PR5EQ2U081t7piS2J0FPkc.jpg",
    imagen: "https://image.tmdb.org/t/p/w300/zCgPbsPJ7d1qlXVn1cKvTlcob1H.jpg",
    imgserie: "https://image.tmdb.org/t/p/w780/9lfpkLjVrAwynaStdUG7jXAjGx6.jpg",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "Mark Grayson es un adolescente normal, excepto por el hecho de que su padre es el superhéroe más poderoso del planeta. Poco después de su decimoséptimo cumpleaños, Mark comienza a desarrollar sus propios poderes y entra en la tutela de su padre.",
    anio: "2021",
    duracion: "00min",
    calificacion: "87%",
    genero: "Anime • Acción • Animación • Aventura",
    director: "Dan Duncan",
    reparto: "Steven Yeun, Sandra Oh, J.K. Simmons",
    estreno: "26/03/2021",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 1",
        year: 2021,
        episodes: [
          { id: "t1e1", number: 1, src: "" }
        ]
      },
       {
        id: "t2",
        name: "Temporada 2",
        year: 2023,
        episodes: [
          { id: "t2e1", number: 1, src: "" }
        ]
      },
       {
        id: "t3",
        name: "Temporada 3",
        year: 2025,
        episodes: [
          { id: "t3e1", number: 1, src: "" }
        ]
      },
       {
        id: "t4",
        name: "Temporada 4",
        year: 2026,
        episodes: [
          { id: "t4e1", number: 1, src: "" }
        ]
      }
    ],

    recomendaciones: [
      {
        id: "../View Peliculas/Reproductor Universal Series.php?id=invencible",
        titulo: "Invencible",
        imagen: "https://image.tmdb.org/t/p/w300/zCgPbsPJ7d1qlXVn1cKvTlcob1H.jpg"
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
    titulo: "nombre de serie",
    type: "series", // 🔥 IMPORTANTE

    poster: "https://image.tmdb.org/t/p/w780/",
    imagen: "https://image.tmdb.org/t/p/w300/",
    imgserie: "https://image.tmdb.org/t/p/w780/",

    calidad: "1080P",
    cam: false,
    adulto: false,

    sinopsis: "",
    anio: "0000",
    duracion: "00min",
    calificacion: "00%",
    genero: " • ",
    director: "",
    reparto: "",
    estreno: "",
    idioma: "Español Latino 🇲🇽",

    // 🔥 TEMPORADAS INTEGRADAS
    seasons: [
      {
        id: "t1",
        name: "Temporada 0",
        year: 0000,
        episodes: [
          { id: "t1e1", number: 1, src: "" },
          { id: "t1e2", number: 2, src: "" },
          { id: "t1e3", number: 3, src: "" },
          { id: "t1e4", number: 4, src: "" },
          { id: "t1e5", number: 5, src: "" },
          { id: "t1e6", number: 6, src: "" }
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
const PERFIL_ID = <?php echo isset($_SESSION['perfil_id']) ? (int)$_SESSION['perfil_id'] : 0; ?>;
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

  function getProgressEndpoints() {
  const isPerfil = (typeof PERFIL_ID !== "undefined" && PERFIL_ID > 0);

  return {
    guardar: isPerfil
      ? "perfil_guardar_progreso.php"
      : "guardar_progreso.php",

    obtener: isPerfil
      ? "perfil_obtener_progreso.php"
      : "obtener_progreso.php"
  };
}


/* =========================
   💾 GUARDAR PROGRESO
========================= */

function guardarProgresoDB() {
  if (!movieId) return;

  const { guardar } = getProgressEndpoints();

  fetch(guardar, {
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
  })
  .catch(err => console.log("Error guardando progreso:", err));
}


/* =========================
   📥 CARGAR PROGRESO
========================= */

async function cargarProgresoDB() {
  try {

    const { obtener } = getProgressEndpoints();

    const res = await fetch(`${obtener}?movie_id=${MOVIE_KEY}`);
    const data = await res.json();

    // 🔥 si no hay datos → cargar normal
    if (data.status !== "ok" || !data.data) {
      loadSeasons();
      loadEpisodes();
      return;
    }

    const progreso = data.data;

    // 🔥 buscar temporada
    const season = movie.seasons.find(s => s.id === progreso.temporada);

    if (!season) {
      loadSeasons();
      loadEpisodes();
      return;
    }

    currentSeason = season;
    updateSeasonYearUI();

    /* =========================
       🎯 EPISODIO
    ========================= */

    const savedEpisodeId =
      progreso?.episodio ||                // 🔥 BD manda
      lastEpisodeBySeason?.[season.id] ||  // fallback local
      null;

    // 🔥 sincronizar con memoria local
    if (progreso?.episodio) {
      lastEpisodeBySeason[season.id] = progreso.episodio;
      saveSeasonProgress();
    }

    const ep =
      season.episodes.find(e => e.id === savedEpisodeId) ||
      season.episodes[0];

    currentEpisode = ep;

    lastEpisodeBySeason[season.id] = ep.id;
    saveSeasonProgress();

    /* =========================
       ⏱️ TIEMPO
    ========================= */

    const epKey = `t${season.id}e${savedEpisodeId}`;

    resumeTime =
      episodeProgress[epKey] ||                // 🔥 LOCAL prioridad
      parseFloat(progreso.tiempo || 0) || 0;  // BD fallback

    /* =========================
       UI
    ========================= */

    seasonNameText.textContent = season.name;

    loadSeasons();
    loadEpisodes();

  } catch (err) {

    console.log("Error cargando progreso:", err);

    // 🔥 fallback total
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
        a.setAttribute("data-href", `Reproductor Universal trailers peliculas.phpid=${rec.id}`);
        a.setAttribute("data-adulto", "adulto");
        a.addEventListener("click", handleAdultLinkClick);
      } else {
        a.href = `Reproductor Universal trailers series.php?id=${rec.id}`;
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
    console.warn("Trailer no encontrada");
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
    const tipo = movie.type || "trailer";

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
        mostrarModalFavoritos("Trailer agregada a favoritos");

        setTimeout(() => {
          window.location.href = "favoritos.php";
        }, 1500);
      }

      else if (data.status === "exists") {
        mostrarModalFavoritos("Esta trailer ya está en favoritos");
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

  const params = new URLSearchParams(window.location.search);
  const movieId = params.get("id");

  if (!movieId) {
    console.log("❌ ID no encontrado");
    return;
  }

  const movie = (typeof MOVIES_DB !== "undefined") ? MOVIES_DB[movieId] : null;

  if (!movie) {
    console.log("❌ trailer no encontrada");
    return;
  }

  /* =========================
     DATOS LIMPIOS
  ========================= */
  const titulo = movie.titulo ? movie.titulo.trim() : "";
  const tipo   = movie.type ? movie.type.trim() : "trailer";
  const imagen = movie.imagen || "";
  const archivo = "Reproductor Universal trailers series.php?id=" + movieId;

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