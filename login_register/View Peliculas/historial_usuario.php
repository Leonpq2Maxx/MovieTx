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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Historial</title>

  <style>
    body {
      margin: 0;
      background: #000;
      color: #fff;
      font-family: sans-serif;
    }

    .search-box,
    .order-box {
      width: 90%;
      margin: 15px auto;
      display: block;
      padding: 10px;
      border-radius: 10px;
      background: #1c1c1c;
      border: 1px solid #333;
      color: white;
    }

    .order-box { background:#111; }

    .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 5px;
      padding: 0 12px 30px;
      margin-top: 25px;
    }

    .item {
      background: #000;
      border-radius: 10px;
      position: relative;
      opacity: 0;
      transform: scale(0.9);
      animation: fadeIn .3s forwards;
    }

    @keyframes fadeIn {
      to { opacity:1; transform:scale(1); }
    }

    .item img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      border-radius: 8px;
    }

    .item-title {
      padding: 6px;
      font-size: .6rem;
      text-align: center;
    }

    .item-info {
      font-size:.55rem;
      text-align:center;
      color:#bbb;
      padding-bottom:6px;
    }

    .delete-btn {
      position:absolute;
      top:5px;
      right:5px;
      background:crimson;
      border:none;
      color:#fff;
      width:22px;
      height:22px;
      border-radius:50%;
    }

    .selector {
  position:absolute;
  top:5px;
  right:5px; /* 🔥 ahora ocupa lugar de la X */
  width:20px;
  height:20px;
  border-radius:4px;
  background:#000a;
  border:1px solid #fff;
  display:none;
}

.multi-select-active .delete-btn {
  display: none;
}

.multi-select-active .selector {
  display: block;
}

    .item.selected .selector {
  background:#00ff99;
}

    .modal-order {
      display:none;
      position:fixed;
      inset:0;
      background:rgba(0,0,0,.7);
      justify-content:center;
      align-items:center;
      z-index:9999;
    }

    .order-box-modal {
      background:#111;
      padding:20px;
      width:90%;
      max-width:350px;
      border-radius:15px;
      text-align:center;
    }

    .order-option,
    .close-order {
      width:100%;
      padding:12px;
      border:none;
      border-radius:10px;
      margin:8px 0;
      background:#222;
      color:white;
    }

    /* =========================
   🔥 MODAL OVERLAY
========================= */
.delete-modal{
  position:fixed;
  inset:0;
  display:none;
  justify-content:center;
  align-items:center;

  background:rgba(0,0,0,.85);
  backdrop-filter: blur(8px);

  z-index:99999;
  padding:15px;
}

/* =========================
   💎 CAJA PRINCIPAL
========================= */
.delete-box{
  width:100%;
  max-width:420px;

  background:linear-gradient(180deg,#111,#0a0a0a);
  border-radius:18px;

  overflow:hidden;

  border:1px solid rgba(255,255,255,0.05);

  box-shadow:
    0 20px 60px rgba(0,0,0,.9),
    0 0 20px rgba(255,0,80,.15);

  animation:modalShow .25s ease;
}

@keyframes modalShow{
  from{
    transform:scale(.85);
    opacity:0;
  }
  to{
    transform:scale(1);
    opacity:1;
  }
}

/* =========================
   🖼️ CONTENEDOR IMAGEN
========================= */
.delete-media{
  width:92%;
  padding:12px;

  display:flex;
  justify-content:center;
  align-items:center;

  /* 🔥 evita que rompa */
  max-height:65vh;
  overflow:hidden;
}

/* 🔥 IMAGEN PERFECTA */
.delete-img{
  width:100%;
  height:auto;

  max-height:60vh;

  object-fit:contain;

  border-radius:12px;

  /* efecto suave */
  transition:transform .3s ease;
}

.delete-box:hover .delete-img{
  transform:scale(1.03);
}

/* =========================
   📝 INFO
========================= */
.delete-info{
  padding:15px;
  text-align:center;
}

.delete-title{
  font-size:1.05rem;
  font-weight:600;
  margin-bottom:6px;

  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

.delete-text{
  font-size:.85rem;
  color:#aaa;
}

/* =========================
   🔘 BOTONES
========================= */
.delete-actions{
  display:flex;
  gap:10px;
  padding:15px;
}

.btn-delete{
  flex:1;
  padding:12px;
  border:none;
  border-radius:10px;

  background:linear-gradient(135deg,#ff2d55,#ff004c);
  color:white;
  font-weight:bold;

  cursor:pointer;
  transition:.25s;
}

.btn-delete:hover{
  transform:scale(1.05);
  box-shadow:0 0 15px rgba(255,0,80,.6);
}

.btn-cancel{
  flex:1;
  padding:12px;
  border:none;
  border-radius:10px;

  background:#2a2a2a;
  color:#ddd;

  cursor:pointer;
  transition:.25s;
}

.btn-cancel:hover{
  background:#3a3a3a;
}

/* =========================
   ❌ BOTÓN CERRAR
========================= */
.close-delete{
  position:absolute;
  top:10px;
  right:10px;

  width:34px;
  height:34px;

  border:none;
  border-radius:50%;

  background:rgba(0,0,0,.65);
  color:#fff;

  cursor:pointer;
  z-index:10;

  display:flex;
  align-items:center;
  justify-content:center;

  font-size:16px;

  transition:.25s;
}

.close-delete:hover{
  background:#ff2d55;
  transform:rotate(90deg);
}

/* =========================
   📱 MÓVILES
========================= */
@media(max-width:480px){

  .delete-box{
    max-width:95%;
    border-radius:16px;
  }

  .delete-title{
    font-size:.95rem;
  }

  .delete-text{
    font-size:.8rem;
  }

  .delete-actions{
    flex-direction:column;
  }

  .btn-delete,
  .btn-cancel{
    width:100%;
  }
}

/* =========================
   📲 TABLETS
========================= */
@media(min-width:481px) and (max-width:900px){

  .delete-box{
    max-width:380px;
  }

}

/* =========================
   💻 PC
========================= */
@media(min-width:901px){

  .delete-box{
    max-width:440px;
  }

  .delete-title{
    font-size:1.2rem;
  }

  .delete-text{
    font-size:.9rem;
  }
}

    .category-badge {
      position:absolute;
      top:6px;
      left:6px;
      background:rgba(0,0,0,.75);
      padding:3px 6px;
      font-size:.55rem;
      border-radius:6px;
      font-weight:bold;
      text-transform:uppercase;
    }

    .category-pelicula { background:#e50914; }
    .category-serie { background:#1db954; }

    #multiDeleteBtn, #cancelSelectBtn {
      display:none;
      margin:10px;
      padding:10px;
      border:none;
      border-radius:8px;
      color:white;
    }

    #multiDeleteBtn { background:#ff007f; }
    #cancelSelectBtn { background:#333; }

    #noResultsMsg {
      display:none;
      text-align:center;
      color:#aaa;
      margin-top:20px;
    }
    /* ========================= */
/* 📱 CELULARES PEQUEÑOS */
/* ========================= */
@media (max-width: 360px) {
  .grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
  }

  .item img {
    height: 150px;
  }

  .item-title {
    font-size: 0.5rem;
  }

  .item-info {
    font-size: 0.45rem;
  }

  .search-box,
  .order-box {
    font-size: 0.75rem;
    padding: 8px;
  }
}

/* ========================= */
/* 📱 CELULARES GRANDES @media (max-width: 480px) {
  .grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .item img {
    height: 170px;
  }

  .item-title {
    font-size: 0.6rem;
  }

  .item-info {
    font-size: 0.5rem;
  }
  
}
*/
/* ========================= */

/* ========================= */ 
/* 📱 CELULARES GRANDES */
/* ========================= */
@media (max-width: 480px) {

  html, body {
    overflow-x: hidden;
  }

  /* 🔥 GRID */
  .grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    padding: 0 6px;
  }

  .item img {
    width: 100%;
    height: 190px;
    object-fit: cover;
  }

  .item-title {
    font-size: 0.6rem;
  }

  .item-info {
    font-size: 0.5rem;
  }

  /* ========================= */
  /* 🔥 DELETE MODAL */
  /* ========================= */

  .delete-box {
    position: relative;
    width: 90%;
    max-width: 320px;
    margin: 0 auto;
    border-radius: 20px;
    overflow: hidden;
    z-index: 0;
  }

  /* 🔥 ARCOIRIS BORDE */
  .delete-box::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 20px;

    background: conic-gradient(
      from var(--angle),
      red,
      orange,
      yellow,
      lime,
      cyan,
      blue,
      violet,
      red
    );

    animation: rotateBorder 3s linear infinite;

    z-index: 0;
  }

  /* 🔥 FONDO INTERNO */
  .delete-box::after {
    content: "";
    position: absolute;
    inset: 2px;
    border-radius: 18px;

    background: linear-gradient(180deg, #111, #0a0a0a);

    z-index: 1;
  }

  /* 🔥 CONTENIDO */
  .delete-box > * {
    position: relative;
    z-index: 2;
  }

  /* ========================= */
  /* 🖼️ IMAGEN CENTRADA SIN CORTE */
  /* ========================= */

  .delete-box .img-container {
    width: 100%;
    max-height: 180px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #000;
    border-radius: 16px;
    overflow: hidden;
  }

  .delete-box img {
    max-width: 100%;
    max-height: 100%;

    width: auto;
    height: auto;

    object-fit: contain; /* 🔥 NO recorta */
    display: block;
  }

}

/* 🔥 soporte animación */
@property --angle {
  syntax: "<angle>";
  initial-value: 0deg;
  inherits: false;
}

/* 🔄 animación arcoiris */
@keyframes rotateBorder {
  0% { --angle: 0deg; }
  100% { --angle: 360deg; }
}


/* 🔄 animación REAL del borde  */
@keyframes rotateBorder {
  0% {
    --angle: 0deg;
  }
  100% {
    --angle: 360deg;
  }
}
/* 🔄 animación */
@keyframes borderSpin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* ========================= */
/* 📲 TABLETS */
/* ========================= */
@media (min-width: 481px) and (max-width: 768px) {
  .grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .item img {
    height: 190px;
  }

  .item-title {
    font-size: 0.65rem;
  }

  .item-info {
    font-size: 0.55rem;
  }
}

/* ========================= */
/* 💻 NOTEBOOKS Y PCs */
/* ========================= */
@media (min-width: 769px) and (max-width: 1200px) {
  .grid {
    grid-template-columns: repeat(5, 1fr);
  }
}

/* ========================= */
/* 📺 PANTALLAS GRANDES */
/* ========================= */
@media (min-width: 1201px) {
  .grid {
    grid-template-columns: repeat(7, 1fr);
  }

  .item img {
    height: 280px;
  }

  .item-title {
    font-size: 0.75rem;
  }

  .item-info {
    font-size: 0.6rem;
  }
}

/* ========================= */
/* 💻 MODAL ELIMINAR EN PC */
/* ========================= */
@media (min-width: 1024px) {

  /* 🔥 VARIABLE PARA ROTACIÓN */
  @property --angle {
    syntax: "<angle>";
    initial-value: 0deg;
    inherits: false;
  }

  /* 🌈 BORDE ARCOIRIS ANIMADO */
  .delete-box {
    position: relative;
    z-index: 0;
    overflow: hidden;
  }

  /* 🔥 capa arcoiris */
  .delete-box::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 20px;

    background: conic-gradient(
      from var(--angle),
      red,
      orange,
      yellow,
      lime,
      cyan,
      blue,
      violet,
      red
    );

    animation: rotateBorder 3s linear infinite;

    z-index: 0;
  }

  /* 🔥 capa interna */
  .delete-box::after {
    content: "";
    position: absolute;
    inset: 2px;
    border-radius: 18px;

    background: linear-gradient(180deg,#111,#0a0a0a);

    z-index: 1;
  }

  /* 🔥 CONTENIDO ENCIMA */
  .delete-box > * {
    position: relative;
    z-index: 2;
  }

  /* 🔄 animación REAL continua */
  @keyframes rotateBorder {
    0% { --angle: 0deg; }
    100% { --angle: 360deg; }
  }

  .delete-img {
    width: 80%;
    border-radius: 8px;
    margin-bottom: 12px;
  }

  .delete-title {
    font-size: 1.4rem;
  }

  .delete-text {
    font-size: 1rem;
  }

  .btn-delete {
    font-size: 1rem;
    padding: 14px;
  }

  .close-delete {
    font-size: 10px;
    padding: 6px 10px;
  }
}

/* 🔥 HOVER para las opciones de orden */
.order-option {
  transition: 0.25s ease;
}

.order-option:hover {
  background: #ff0033;
  color: #fff;
  transform: scale(1.03);
  box-shadow: 0 0 12px rgba(255, 0, 51, 0.7);
}
/* 🔥 Opción seleccionada */
.order-option.active {
  background: #ff0033 !important;
  color: #fff !important;
  transform: scale(1.03);
  box-shadow: 0 0 12px rgba(255, 0, 51, 0.7);
}

  </style>
</head>

<body>

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

<input id="buscar" class="search-box" placeholder="Buscar historial...">

<button id="openOrderMenu" class="order-box">Ordenar: Más recientes</button>

<p id="noResultsMsg">No se encuentra su Película o Serie en el historial</p>

<button id="cancelSelectBtn">Cancelar selección</button>
<button id="multiDeleteBtn">Eliminar seleccionados</button>

<div class="grid" id="historial-container"></div>

<div id="orderModal" class="modal-order">
  <div class="order-box-modal">
    <h3>Ordenar por</h3>
    <button class="order-option" data-value="recientes">Más recientes</button>
    <button class="order-option" data-value="antiguos">Más antiguos</button>
    <button class="close-order">Cerrar</button>
  </div>
</div>

<div id="deleteModal" class="delete-modal">

  <div class="delete-box">

    <div class="delete-media">
      <img id="deleteImg" class="delete-img" loading="lazy">
    </div>

    <div class="delete-info">
      <h3 id="deleteTitle" class="delete-title"></h3>
      <p class="delete-text">
        ¿Estas segur@ que quieres borrarlo del historial?
      </p>
    </div>

    <div class="delete-actions">
      <button id="confirmDelete" class="btn-delete">Eliminar</button>
      <button id="cancelDelete2" class="btn-cancel">Cancelar</button>
    </div>

  </div>

</div>

<!-- Modal flotante de edad + clave -->
<div id="ageModal" class="age-modal hidden">
  <div class="age-modal-content">
    <span class="close-button" onclick="closeModal()">×</span>

    <h2>Verificación de Edad</h2>

    <label for="birthyear">Año de nacimiento:</label>
    <input type="number" id="birthyear" placeholder="Año" min="1900" max="2030" />

    <label for="age">Edad actual:</label>
    <input type="number" id="age" placeholder="----" min="1" max="120" />

    <label for="clave">Clave de acceso (solo adultos):</label>
    <input type="password" id="clave" placeholder="Clave secreta" />

    <button id="resetClaveBtn" style="background:#444;margin-top:10px;">
      Olvidé mi clave
    </button>

    <button id="confirmAgeBtn">Validar acceso</button>

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
const resetModal = document.getElementById("resetModal");
const alertModal = document.getElementById("alertModal");
const alertTexto = document.getElementById("alertTexto");

document.getElementById("resetClaveBtn").addEventListener("click", () => {
  resetModal.style.display = "flex";
});

document.getElementById("cancelReset").onclick = () => {
  resetModal.style.display = "none";
};

document.getElementById("confirmReset").onclick = () => {
  localStorage.removeItem("claveAdultos");
  claveGuardada = null;

  resetModal.style.display = "none";
  showAlert("Clave eliminada. Ahora podés crear una nueva.");
  openModal();
};

document.getElementById("closeAlert").onclick = () => {
  alertModal.style.display = "none";
};

function showAlert(msg) {
  alertTexto.textContent = msg;
  alertModal.style.display = "flex";
}
</script>

<script>
let pendingRedirect = null;

// 🔎 Revisamos si ya existe una clave guardada
let claveGuardada = localStorage.getItem("claveAdultos") || null;

function verificarAcceso(url, requiereEdad) {
  if (requiereEdad) {
    pendingRedirect = url;
    openModal();
  } else {
    location.assign(url);
  }
}

function openModal() {
  document.getElementById("ageModal").classList.remove("hidden");
  document.getElementById("result-message").innerText = "";
  document.getElementById("birthyear").value = "";
  document.getElementById("age").value = "";
  document.getElementById("clave").value = "";

  if (claveGuardada) {
    document.querySelector("label[for='clave']").innerText = "Ingresa tu clave:";
    document.getElementById("clave").placeholder = "Clave guardada";
  } else {
    document.querySelector("label[for='clave']").innerText = "Crea una clave:";
    document.getElementById("clave").placeholder = "Nueva clave";
  }
}

function closeModal() {
  document.getElementById("ageModal").classList.add("hidden");
  pendingRedirect = null;
}

document.getElementById("confirmAgeBtn").addEventListener("click", function () {
  const birthYear = parseInt(document.getElementById("birthyear").value);
  const enteredAge = parseInt(document.getElementById("age").value);
  const claveIngresada = document.getElementById("clave").value;
  const currentYear = new Date().getFullYear();
  const calculatedAge = currentYear - birthYear;
  const result = document.getElementById("result-message");

  result.style.color = "red";

  if (!birthYear || !enteredAge || !claveIngresada) {
    result.textContent = "Completa todos los campos.";
    return;
  }

  if (enteredAge !== calculatedAge) {
    result.textContent = "La edad no coincide con el año de nacimiento.";
    return;
  }

  if (enteredAge < 18) {
    result.textContent = "Debes ser mayor de edad.";
    return;
  }

  if (!claveGuardada) {
    localStorage.setItem("claveAdultos", claveIngresada);
    claveGuardada = claveIngresada;

    result.style.color = "lime";
    result.textContent = "Clave creada y acceso autorizado. Entrando...";

    setTimeout(() => {
      if (pendingRedirect) window.location.href = pendingRedirect;
    }, 1200);
    return;
  }

  if (claveIngresada !== claveGuardada) {
    result.textContent = "Clave incorrecta.";
    return;
  }

  result.style.color = "lime";
  result.textContent = "Acceso autorizado. Entrando...";

  setTimeout(() => {
    if (pendingRedirect) window.location.href = pendingRedirect;
  }, 1200);
});
</script>

<script>
function abrirItemHistorial(item) {
  if (item.adulto === true) {
    verificarAcceso(item.archivo, true);
  } else {
    location.href = item.archivo;
  }
}
</script>

<script>

function normalizar(str){
  return str.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
}

let historial = [];

/* 🔥 NUEVO: marcar "Más recientes" por defecto */
document.addEventListener("DOMContentLoaded", () => {
  const btnDefault = document.querySelector('.order-option[data-value="recientes"]');
  if (btnDefault) {
    btnDefault.classList.add("active");
  }
});

async function cargarHistorial(){

  const res = await fetch("obtener_historial.php");
  const data = await res.json();

  historial = data.map(item => {

    let titulo = item.titulo;
    if(!titulo || titulo === "undefined"){
      titulo = item.movie_id.replace(/_/g," ");
      titulo = titulo.replace(/\b\w/g,l=>l.toUpperCase());
    }

    let tipo = (item.tipo || "").toLowerCase();

    if(tipo === "series") tipo = "serie";

    if(tipo !== "serie" && tipo !== "pelicula"){
      if(item.movie_id.includes("s01") || item.movie_id.includes("s02")){
        tipo = "serie";
      } else {
        tipo = "pelicula";
      }
    }

    let timestamp = item.timestamp;

    if(!timestamp && item.visto_en){
      timestamp = new Date(item.visto_en).getTime();
    }

    if(!timestamp){
      timestamp = Date.now();
    }

    return {
      ...item,
      titulo,
      tipo,
      timestamp
    };

  });

  renderHistorial();
}

cargarHistorial();

let seleccionados = [];
let multiMode = false;
let indexAEliminar = null;

function renderHistorial(){

  const container = document.getElementById("historial-container");
  const noResults = document.getElementById("noResultsMsg");

  container.innerHTML = "";
  let filtrados = historial;

  if (openOrderMenu.innerText.includes("recientes")) {
    filtrados.sort((a,b)=>b.timestamp-a.timestamp);
  } else {
    filtrados.sort((a,b)=>a.timestamp-b.timestamp);
  }

  if (filtrados.length === 0) {
    noResults.style.display = "block";
    return;
  } else {
    noResults.style.display = "none";
  }

  filtrados.forEach((item,index)=>{

    const ahora = Date.now();
    const edad = ahora - item.timestamp;

    let tiempo = "";
    if(edad < 86400000) tiempo = "Hoy";
    else if(edad < 172800000) tiempo = "Ayer";
    else tiempo = Math.floor(edad / 86400000) + " días";

    const tipo = item.tipo;

    const div = document.createElement("div");
    div.className = "item";

    div.innerHTML = `
      <div class="selector"></div>

      <img src="${item.imagen}">

      <div class="category-badge category-${tipo}">
        ${tipo.toUpperCase()}
      </div>

      <div class="item-title">${item.titulo}</div>
      <div class="item-info">${tiempo}</div>

      <button class="delete-btn"
        onclick="event.stopPropagation(); eliminarItem(${index})">×</button>
    `;

    // 🔥 CLICK NORMAL
    div.onclick = (e) => {
      if (longPressActivo) return;

      if (multiMode) {
        div.classList.toggle("selected");

        if (div.classList.contains("selected")) {
          seleccionados.push(index);
        } else {
          seleccionados = seleccionados.filter(i => i !== index);
        }

      } else {
        abrirItemHistorial(item);
      }
    };

    // =========================
    // 🔥 LONG PRESS COMO FAVORITOS
    // =========================
    let pressTimer;
    let longPressActivo = false;

    div.addEventListener("touchstart", () => {

      longPressActivo = false;

      pressTimer = setTimeout(() => {

        longPressActivo = true;

        activarSeleccionMultiple();

        div.classList.add("selected");

        if (!seleccionados.includes(index)) {
          seleccionados.push(index);
        }

      }, 400);

    });

    div.addEventListener("touchend", (e) => {
      clearTimeout(pressTimer);

      if (longPressActivo) {
        e.preventDefault();
        e.stopPropagation();
      }
    });

    div.addEventListener("touchmove", () => {
      clearTimeout(pressTimer);
    });

    container.appendChild(div);

  });

}

// 🔥 BUSCADOR IGUAL A FAVORITOS
document.getElementById("buscar").oninput = e => {

  const term = normalizar(e.target.value);
  let visibles = 0;

  document.querySelectorAll("#historial-container .item").forEach(card => {

    const title = normalizar(
      card.querySelector(".item-title").innerText
    );

    if (title.includes(term)) {
      card.style.display = "";
      visibles++;
    } else {
      card.style.display = "none";
    }

  });

  document.getElementById("noResultsMsg").style.display =
    visibles === 0 ? "block" : "none";

};

function activarSeleccionMultiple() {

  multiMode = true;
  seleccionados = [];

  document.body.classList.add("multi-select-active");

  document.querySelectorAll(".item").forEach(item => {
    item.classList.remove("selected");
  });

  document.querySelectorAll(".selector").forEach(s => {
    s.style.display = "block";
  });

  document.getElementById("multiDeleteBtn").style.display = "inline-block";
  document.getElementById("cancelSelectBtn").style.display = "inline-block";
}

document.getElementById("cancelSelectBtn").onclick = () => {

  multiMode = false;
  seleccionados = [];

  document.body.classList.remove("multi-select-active");

  document.querySelectorAll(".item").forEach(item => {
    item.classList.remove("selected");
  });

  document.querySelectorAll(".selector").forEach(s => {
    s.style.display = "none";
  });

  document.getElementById("multiDeleteBtn").style.display = "none";
  document.getElementById("cancelSelectBtn").style.display = "none";
};

multiDeleteBtn.onclick = () => {

  let promesas = [];

  seleccionados.forEach(i => {

    const movieId = historial[i].movie_id;
const tipo = historial[i].tipo;

promesas.push(
  fetch("eliminar_historial.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body:
      "movie_id=" + encodeURIComponent(movieId) +
      "&tipo=" + encodeURIComponent(tipo)
  })
);


  });

  Promise.all(promesas).then(() => {

    historial = historial.filter((_, i) => !seleccionados.includes(i));

    seleccionados = [];
    multiMode = false;

    document.body.classList.remove("multi-select-active");

    document.getElementById("multiDeleteBtn").style.display = "none";
    document.getElementById("cancelSelectBtn").style.display = "none";

    renderHistorial();

  });

};

function eliminarItem(i){

  indexAEliminar = i;

  deleteImg.src = historial[i].imagen;
  deleteTitle.innerText = historial[i].titulo;

  deleteModal.style.display = "flex";

}

openOrderMenu.onclick=()=>orderModal.style.display="flex";

document.querySelector(".close-order").onclick=
()=>orderModal.style.display="none";

document.querySelectorAll(".order-option").forEach(b=>{

  b.onclick=()=>{

    document.querySelectorAll(".order-option")
    .forEach(x=>x.classList.remove("active"));

    b.classList.add("active");

    openOrderMenu.innerText="Ordenar: "+b.innerText;

    renderHistorial();

    orderModal.style.display="none";

  };

});

</script>

<script>

// 🔥 ELEMENTOS DEL MODAL
const deleteModal = document.getElementById("deleteModal");
const confirmDelete = document.getElementById("confirmDelete");
const cancelDeleteBtn = document.getElementById("cancelDelete2");
const closeDeleteBtn = document.querySelector(".close-delete");

// ✅ CANCELAR
cancelDeleteBtn.onclick = () => {
  deleteModal.style.display = "none";
};

// ✅ BOTÓN X (esto es lo que te faltaba)
if(closeDeleteBtn){
  closeDeleteBtn.onclick = () => {
    deleteModal.style.display = "none";
  };
}

// ✅ CLICK FUERA
deleteModal.addEventListener("click", (e) => {
  if (e.target === deleteModal) {
    deleteModal.style.display = "none";
  }
});

// ✅ ESC
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    deleteModal.style.display = "none";
  }
});

// 🔥 CONFIRMAR ELIMINAR (ESTO ES CLAVE)
confirmDelete.onclick = async () => {

  const item = historial[indexAEliminar];

  await fetch("eliminar_historial.php", {
    method:"POST",
    headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:
  "movie_id=" + encodeURIComponent(item.movie_id) +
  "&tipo=" + encodeURIComponent(item.tipo)

  });

  // 🔥 BORRAR PROGRESO LOCAL
  localStorage.removeItem(`season_progress_${item.movie_id}`);

  historial.splice(indexAEliminar,1);

  deleteModal.style.display="none";

  renderHistorial();

};

</script>

</body>
</html>
