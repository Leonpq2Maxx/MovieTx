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
?>

<?php require_once "../auth.php"; ?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>2025 Movie</title>
  <link rel="icon" type="image/png" href="../Logo/Logo Nuevo.png">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background-color: #000000;
      color: #fff;
    }
    header {
      position: sticky;
      top: 0;
      background-color: #000000;
      padding: 15px;
      text-align: center;
      font-weight: bold;
      font-size: 1rem;
      z-index: 10;
      box-shadow: 0 2px 5px rgba(0,0,0,0.7);
    }
    h1 { margin: 0; font-size: 1rem; }
    .search-box {
      text-align: center;
      padding: 15px 0;
    }
    .search-box input {
      width: 90%;
      padding: 10px;
      border: none;
      border-radius: 6px;
      background: #1c1c1c;
      color: #fff;
      font-size: 1rem;
      transition: all 0.3s ease;
    }
    .search-box input:focus {
      outline: none;
      background: #222;
      box-shadow: 0 0 8px rgba(255, 32, 143, 0.5);
    }
    input::placeholder {
      color: #aaa;
      font-weight: 500;
    }

    .movie-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 5px;
      padding: 10px;
    }
    @media (max-width: 600px) {
      .movie-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (orientation: landscape) and (min-width: 700px) {
      .movie-grid { grid-template-columns: repeat(4, 1fr); }
    }

    @media (orientation: landscape) and (min-width: 1024px) {
      .movie-grid { grid-template-columns: repeat(8, 1fr); }
    }

    .movie {
      background: #000000;
      border-radius: 6px;
      overflow: hidden;
      position: relative;
      cursor: pointer;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .movie:hover {
      transform: scale(1.05);
      box-shadow: 0 0 15px rgba(255, 32, 143, 0.6);
    }
    .movie img {
  width: 100%;
  height: 180px;       /* 🔥 MISMO LARGO PARA TODAS */
  object-fit: contain;   /* 🔥 NO RECORTA */
  display: block;
}

.movie p {
  margin: 6px 4px 8px;
  text-align: center;
  font-size: 0.6rem;
  color: #f5f5f5;
  line-height: 1.2;
}

    
    .movie:hover img { filter: brightness(1.1); }
    .movie.locked img { filter: brightness(0.5); }
    .movie p {
      margin: 8px;
      text-align: center;
      font-size: 0.6rem;
      color: #f5f5f5;
    }

    /*PELICULA*/
    .movie .pelicula, .movie .year-tag {
      position: absolute;
      z-index: 2;
      color: rgba(255, 32, 143, 0.838);
      font-weight: bold;
      background: rgba(255, 255, 255, 0.838);
      padding: 2px 6px;
      font-size: 0.7rem;
      border-radius: 3px;
    }

    .movie .year-tegg {
      position: absolute;
      z-index: 2;
      color: rgb(255, 32, 143);
      font-weight: bold;
      background: rgba(255, 255, 255, 0.838);
      padding: 2px 6px;
      font-size: 0.6rem;
      border-radius: 3px;
    }
    .movie .pelicula { top: 5px; left: 5px; }
    .movie .year-tag { top: 25px; left: 5px; }
    .movie .year-tegg { top: 47px; left: 5px; }
    .movie .lock-icon {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 28px;
      height: 28px;
      background: rgba(0,0,0,0.6);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: white;
      animation: bounce 1s infinite;
    }
    /*FIN*/

    /*Serie*/
    .movie .SerieHd, .movie .year-tog {
      position: absolute;
      z-index: 2;
      color: rgba(255, 255, 255, 0.838);
      font-weight: bold;
      background: rgba(255, 0, 136, 0.838);
      padding: 2px 6px;
      font-size: 0.7rem;
      border-radius: 3px;
    }

    .movie .year-tagg {
      position: absolute;
      z-index: 2;
      color: rgba(255, 255, 255, 0.838);
      font-weight: bold;
      background: rgba(255, 0, 136, 0.838);
      padding: 2px 6px;
      font-size: 0.6rem;
      border-radius: 3px;
    }

    .movie .SerieHd { top: 5px; left: 5px; }
    .movie .year-tog { top: 25px; left: 5px; }
    .movie .year-tagg { top: 47px; left: 5px; }
    .movie .lock-icon {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 28px;
      height: 28px;
      background: rgba(0,0,0,0.6);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      color: white;
      animation: bounce 1s infinite;
    }

    /*FIN*/


    
    @keyframes bounce { 0%,100%{transform:translateY(0);}50%{transform:translateY(-5px);} }
    .movie .recien-tag {
      position: absolute;
      top: 160px;
      left: 5px;
      background: rgb(255, 32, 143);
      color: white;
      font-weight: bold;
      font-size: 0.65rem;
      padding: 2px 6px;
      border-radius: 3px;
      z-index: 3;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse {0%,100%{opacity:1;transform:scale(1);}50%{opacity:0.8;transform:scale(1.1);}}
    .no-results {
      text-align: center;
      padding: 20px;
      font-size: 1rem;
      color: #bbb;
      display: none;
    }
  </style>
</head>
<body>

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

<style>

/* HEADER FLEX */
.header-container{
  display:flex;
  justify-content:space-between;
  align-items:center;
}

/* BOTON FILTRO */
.btn-filtro{
  background:transparent;
  border:none;
  font-size:1.4rem;
  cursor:pointer;
  color:#fff;
  transition:0.3s;
}
.btn-filtro:hover{
  transform:scale(1.2);
  color:#ff007f;
}

/* MODAL */
.modal-genero{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.6);
  backdrop-filter:blur(6px);
  display:flex;
  justify-content:center;
  align-items:center;
  opacity:0;
  visibility:hidden;
  transition:0.3s;
  z-index:9999;
}

.modal-genero.activo{
  opacity:1;
  visibility:visible;
}

.modal-contenido{
  background:#111;
  padding:25px;
  border-radius:12px;
  width:90%;
  max-width:400px;
  text-align:center;
  box-shadow:0 0 25px rgba(255,0,128,0.4);
  position:relative;
}

.cerrar-modal{
  position:absolute;
  top:10px;
  right:10px;
  background:none;
  border:none;
  color:#fff;
  font-size:1.2rem;
  cursor:pointer;
}

/* BOTONES GENERO */
.generos{
  display:flex;
  flex-direction:column;
  gap:8px;
  margin-top:15px;
  max-height:250px;
  overflow-y:auto;
}

.genero-btn{
  width:100%;
  text-align:left;
  padding:10px 14px;
  border:none;
  border-radius:6px;
  background:#1a1a1a;
  color:#fff;
  cursor:pointer;
  transition:0.3s;
  font-size:0.9rem;
}

.genero-btn:hover{
  background:#ff007f;
}

.genero-btn.activo{
  background:#ff007f;
  box-shadow:0 0 10px #ff007f;
}

/* RESET */
.reset-btn{
  margin-top:20px;
  padding:8px 14px;
  border:none;
  border-radius:6px;
  background:#444;
  color:#fff;
  cursor:pointer;
}
.reset-btn:hover{
  background:#777;
}

.btn-filtro svg {
  display: block;        /* 🔥 evita línea fantasma inline */
}

.btn-filtro {
  line-height: 0;        /* 🔥 elimina espacio vertical invisible */
}

</style>
<!-- 🔴 Fin pantalla de carga neón -->

  <header>
  <div class="header-container">
    <h1 id="titulo-seccion">
      Agregados HOY 
      <span id="contador" style="font-size: 1rem; font-weight: normal; color: #bbb;"></span>
    </h1>
    <button class="btn-filtro" id="abrirModal" title="Filtrar por género">
  <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
    <line x1="4" y1="6" x2="20" y2="6"/>
    <circle cx="10" cy="6" r="2"/>
    <line x1="4" y1="12" x2="20" y2="12"/>
    <circle cx="14" cy="12" r="2"/>
    <line x1="4" y1="18" x2="20" y2="18"/>
    <circle cx="8" cy="18" r="2"/>
  </svg>
</button>

  </div>

</header>

<div class="modal-genero" id="modalGenero">
  <div class="modal-contenido">

    <button class="cerrar-modal" id="cerrarModal">✖</button>

    <h2>Seleccionar género</h2>

    <div class="generos">
      <button class="genero-btn">Accion</button>
      <button class="genero-btn">Animacion</button>
      <button class="genero-btn">Anime</button>
      <button class="genero-btn">Comedia</button>
      <button class="genero-btn">Crimen</button>
      <button class="genero-btn">Drama</button>
      <button class="genero-btn">Documental</button>
      <button class="genero-btn">Disney</button>
      <button class="genero-btn">Marvel</button>
      <button class="genero-btn">Musical</button>
      <button class="genero-btn">Suspenso</button>
      <button class="genero-btn">Romance</button>
      <button class="genero-btn">Peleas</button>
      <button class="genero-btn">Terror</button>
      <button class="genero-btn">Vengaza</button>
    </div>

    <button class="reset-btn" id="resetGenero">
      Quitar filtro
    </button>

  </div>
</div>



  <div class="search-box">
    <input type="text" id="search-input" placeholder="Buscar por nombre, género o año..." oninput="filtrarPeliculas()"> <!--ESTABA PUESTO "Buscar por nombre, género o año..." -->
  </div>

  <div class="movie-grid" id="movie-grid">   

     <!--SERIE-->
    <div class="movie locked" data-tipo="serie" data-titulo="" data-tipo="serie" data-genero="" data-anio="2015" data-html="../View Series/.html" data-fecha="2026-01-">
      <span class="SerieHd">Serie</span>
      <span class="year-tog">2015</span>
      <span class="year-tagg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>
    <!--FIN-->

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2026-04-18">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-11-108">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2021" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-07-11">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2021</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2026-04-18">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="" data-genero="" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-07-11">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    <div class="movie locked" data-titulo="" data-tipo="pelicula" data-genero="" data-anio="1988" data-html="../View Peliculas/Reproductor Universal.php?id=" data-fecha="2025-07-11">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1988</span>
      <img src="https://image.tmdb.org/t/p/w300/">
      <span class="lock-icon">🔒</span>
      <p></p>
    </div>

    
  </div>
  <div id="no-results" class="no-results">Pelicula o serie no encontrada</div> <!--ESTABA PUESTO "No se encontraron resultados 😕"-->

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
  e.stopImmediatePropagation(); // 🔥 CLAVE
  pendingRedirect = e.currentTarget.dataset.html;
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

document.querySelectorAll('.movie[data-adulto="true"]').forEach(card => {
  card.addEventListener("click", handleAdultLinkClick);
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
    let generoActivo = null;
    function filtrarPeliculas() {

  const input = document.getElementById("search-input");
  const texto = input.value.toLowerCase().trim();
  const palabras = texto.split(" ").filter(p => p.length > 0);

  const peliculas = document.querySelectorAll(".movie");
  let visibles = 0;

  peliculas.forEach(peli => {

    const titulo = (peli.dataset.titulo || "").toLowerCase();
    const genero = (peli.dataset.genero || "").toLowerCase();
    const anio = (peli.dataset.anio || "").toLowerCase();
    const tipo = (peli.dataset.tipo || "").toLowerCase();

    const contenido = `${titulo} ${genero} ${anio} ${tipo}`;

    // 🔹 FILTRO POR TEXTO
    const coincideBusqueda = palabras.every(p => contenido.includes(p));

    // 🔹 FILTRO POR GÉNERO
    const coincideGenero = !generoActivo || genero.includes(generoActivo.toLowerCase());

    const visible = coincideBusqueda && coincideGenero;

    peli.style.display = visible ? "block" : "none";

    if (visible) visibles++;
  });

  document.getElementById("no-results").style.display = visibles === 0 ? "block" : "none";

  actualizarContadorPeliculas();
}


    window.addEventListener("DOMContentLoaded", () => {
      const ultima = localStorage.getItem("ultimaBusqueda");
      const scroll = localStorage.getItem("scrollY");
      const input = document.getElementById("search-input");
      if (ultima && input) { input.value = ultima; filtrarPeliculas(); }
      if (scroll) window.scrollTo(0, parseInt(scroll));
      localStorage.removeItem("ultimaBusqueda");
      localStorage.removeItem("scrollY");
    });
    document.querySelectorAll(".movie").forEach(peli => {
      const htmlFile = peli.dataset.html;
      if (htmlFile && htmlFile.trim() !== "") {
        peli.classList.remove("locked");
        const lockIcon = peli.querySelector(".lock-icon");
        if (lockIcon) lockIcon.remove();
        peli.addEventListener("click", () => {
          localStorage.setItem("ultimaBusqueda", document.getElementById("search-input").value);
          localStorage.setItem("scrollY", window.scrollY);
          window.location.href = htmlFile;
        });
      }
    });
    document.querySelectorAll(".movie").forEach(peli => {
      const fecha = peli.dataset.fecha;
      if (fecha) {
        const fechaCreacion = new Date(fecha);
        const hoy = new Date();
        const diasDiferencia = (hoy - fechaCreacion) / (1000 * 60 * 60 * 24);
        if (diasDiferencia <= 5) {
          const recien = document.createElement("span");
          recien.className = "recien-tag";
          recien.textContent = "Recién agregado";
          peli.appendChild(recien);
        }
      }
    });
    function actualizarContadorPeliculas() {
      const peliculasVisibles = document.querySelectorAll(".movie-grid .movie:not([style*='display: none'])").length;
      const contador = document.getElementById("contador");
      if (contador) contador.textContent = `(${peliculasVisibles})`;
    }
    window.addEventListener("DOMContentLoaded", actualizarContadorPeliculas);
  </script>


<script>
document.addEventListener("DOMContentLoaded", () => {

  const modal = document.getElementById("modalGenero");
  const abrir = document.getElementById("abrirModal");
  const cerrar = document.getElementById("cerrarModal");
  const reset = document.getElementById("resetGenero");
  const botonesGenero = document.querySelectorAll(".genero-btn");
  const titulo = document.getElementById("titulo-seccion");
  const peliculas = document.querySelectorAll(".movie");

  filtrarPeliculas();


  abrir.addEventListener("click", () => {
    modal.classList.add("activo");
  });

  cerrar.addEventListener("click", () => {
    modal.classList.remove("activo");
  });

  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.classList.remove("activo");
    }
  });

  botonesGenero.forEach(btn => {
    btn.addEventListener("click", () => {

      botonesGenero.forEach(b => b.classList.remove("activo"));
      btn.classList.add("activo");

      generoActivo = btn.textContent.trim();

      titulo.innerHTML = `
        ${generoActivo.toUpperCase()}
        <span id="contador" style="font-size:1rem;font-weight:normal;color:#bbb;"></span>
      `;

      filtrarPeliculas();

      modal.classList.remove("activo");
    });
  });

  reset.addEventListener("click", () => {

    generoActivo = null;

    botonesGenero.forEach(b => b.classList.remove("activo"));

    titulo.innerHTML = `
      Agregados HOY
      <span id="contador" style="font-size:1rem;font-weight:normal;color:#bbb;"></span>
    `;

    filtrarPeliculas();

    modal.classList.remove("activo");
  });

});
</script>


</body>
</html>
