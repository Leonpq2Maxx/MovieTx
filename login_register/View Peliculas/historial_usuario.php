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
      height: 190px;
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
      left:5px;
      width:20px;
      height:20px;
      border-radius:4px;
      background:#000a;
      border:1px solid #fff;
      display:none;
    }

    .item.selected .selector {
      background:#00ff99;
      display:block;
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

    .delete-modal {
      position:fixed;
      inset:0;
      display:none;
      justify-content:center;
      align-items:center;
      background:rgba(0,0,0,.75);
      z-index:99999;
    }

    .delete-box {
    background: #151515;
    padding: 25px;
    border-radius: 14px;
    text-align: center;
    width: 75%;
    max-width: 350px;
    border: 2px solid #ff2d5b;
    box-shadow: 0 0 22px rgba(255, 45, 91, 0.5);
    position: relative;
}

    .delete-img {
      width:90%;
      border-radius:8px;
      margin-bottom:12px;
    }

    .btn-delete {
      background:linear-gradient(135deg,#ff2d55,#ff5e7e);
      border:none;
      padding:10px;
      color:#fff;
      border-radius:999px;
      width:100%;
    }

    .close-delete {
      position:absolute;
      top:6px;
      right:8px;
      background:#444;
      color:#fff;
      border:none;
      padding:4px 8px;
      border-radius:6px;
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

    .multi-select-active .category-badge { top:32px; }

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
/* 📱 CELULARES GRANDES */
/* ========================= */
@media (max-width: 480px) {
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
    height: 240px;
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
  .delete-box {
    max-width: 200px;
    width: 40%;
    padding: 30px;
    border-radius: 18px;
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
    <button class="close-delete" id="cancelDelete">✖</button>
    <img id="deleteImg" class="delete-img">
    <h3 id="deleteTitle"></h3>
    <p>¿Deseas eliminar este historial?</p>
    <button id="confirmDelete" class="btn-delete">Eliminar</button>
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

  // Si ya existe clave → cambia texto del label
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

  // 🔐 Si NO hay clave guardada ⇒ Crear clave nueva
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

  // 🔐 Si SÍ existe clave ⇒ Comprobar
  if (claveIngresada !== claveGuardada) {
    result.textContent = "Clave incorrecta.";
    return;
  }

  // ✔ Clave correcta y edad OK → entra
  result.style.color = "lime";
  result.textContent = "Acceso autorizado. Entrando...";

  setTimeout(() => {
    if (pendingRedirect) window.location.href = pendingRedirect;
  }, 1200);
});
</script>

<script>
  /* ================================
   🔞 VERIFICACIÓN DE EDAD INTEGRADA
================================== */

function abrirItemHistorial(item) {
  if (item.adulto === true) {
    // REQUIERE VERIFICAR EDAD
    verificarAcceso(item.archivo, true);
  } else {
    // ENTRA NORMAL
    location.href = item.archivo;
  }
}

</script>

<script>

function normalizar(str){
  return str.normalize("NFD").replace(/[\u0300-\u036f]/g,"").toLowerCase();
}

let historial = [];

async function cargarHistorial(){

  const res = await fetch("obtener_historial.php");
  const data = await res.json();

  /* 🔧 NORMALIZAR DATOS DEL SERVIDOR */

  historial = data.map(item => {

    /* titulo seguro */
    let titulo = item.titulo;
    if(!titulo || titulo === "undefined"){
      titulo = item.movie_id.replace(/_/g," ");
      titulo = titulo.replace(/\b\w/g,l=>l.toUpperCase());
    }

    /* tipo seguro */
    let tipo = (item.tipo || "").toLowerCase();

    if(tipo !== "serie" && tipo !== "pelicula"){
      if(item.movie_id.includes("s01") || item.movie_id.includes("s02")){
        tipo = "serie";
      } else {
        tipo = "pelicula";
      }
    }

    /* timestamp desde visto_en */
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

  if(openOrderMenu.innerText.includes("recientes")){
    historial.sort((a,b)=>b.timestamp-a.timestamp);
  } else {
    historial.sort((a,b)=>a.timestamp-b.timestamp);
  }

  const c = document.getElementById("historial-container");
  c.innerHTML="";

  historial.forEach((item,i)=>{

    const ahora = Date.now();
    const edad = ahora - item.timestamp;

    let tiempo = "";

    if(edad < 86400000) tiempo = "Hoy";
    else if(edad < 172800000) tiempo = "Ayer";
    else tiempo = Math.floor(edad / 86400000) + " días";

    const tipo = item.tipo;

    const div = document.createElement("div");
    div.className="item";

    div.innerHTML = `
      <div class="selector"></div>

      <img src="${item.imagen}">

      <div class="category-badge category-${tipo}">
        ${tipo.toUpperCase()}
      </div>

      <div class="item-title">
        ${item.titulo}
      </div>

      <div class="item-info">
        ${tiempo}
      </div>

      <button class="delete-btn" onclick="event.stopPropagation();eliminarItem(${i})">×</button>
    `;

    div.onclick = () => {

      if(multiMode){

        div.classList.toggle("selected");

        if(div.classList.contains("selected"))
          seleccionados.push(i);
        else
          seleccionados = seleccionados.filter(x=>x!==i);

      }

      else{
        abrirItemHistorial(item);
      }

    };

    div.oncontextmenu=e=>{
      e.preventDefault();
      activarMulti();
    };

    c.appendChild(div);

  });

}

function activarMulti(){
  multiMode=true;

  document.body.classList.add("multi-select-active");

  document.querySelectorAll(".selector")
  .forEach(x=>x.style.display="block");

  multiDeleteBtn.style.display="inline-block";
  cancelSelectBtn.style.display="inline-block";
}

cancelSelectBtn.onclick = () => {

  multiMode = false;
  seleccionados = [];

  document.body.classList.remove("multi-select-active");

  document.querySelectorAll(".item")
  .forEach(card => card.classList.remove("selected"));

  document.querySelectorAll(".selector")
  .forEach(x => x.style.display = "none");

  multiDeleteBtn.style.display = "none";
  cancelSelectBtn.style.display = "none";
};

multiDeleteBtn.onclick = async () => {

  for (let i of seleccionados) {

    const item = historial[i];

    await fetch("eliminar_historial.php", {
      method: "POST",
      headers: {"Content-Type":"application/x-www-form-urlencoded"},
      body: "movie_id=" + encodeURIComponent(item.movie_id)
    });

  }

  historial = historial.filter((_,i)=>!seleccionados.includes(i));

  cancelSelectBtn.onclick();
  renderHistorial();

};

function eliminarItem(i){

  indexAEliminar=i;

  deleteImg.src=historial[i].imagen;
  deleteTitle.innerText=historial[i].titulo;

  deleteModal.style.display="flex";

}

cancelDelete.onclick=()=>deleteModal.style.display="none";

confirmDelete.onclick = async () => {

  const item = historial[indexAEliminar];

  await fetch("eliminar_historial.php", {
    method:"POST",
    headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:"movie_id="+encodeURIComponent(item.movie_id)
  });

  historial.splice(indexAEliminar,1);

  deleteModal.style.display="none";

  renderHistorial();

};

buscar.oninput=e=>{

  const term=normalizar(e.target.value);

  let vis=0;

  document.querySelectorAll(".item").forEach(card=>{

    const t=normalizar(card.querySelector(".item-title").innerText);

    if(t.includes(term)){
      card.style.display="";
      vis++;
    }
    else{
      card.style.display="none";
    }

  });

  noResultsMsg.style.display=vis===0?"block":"none";

};

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

</body>
</html>
