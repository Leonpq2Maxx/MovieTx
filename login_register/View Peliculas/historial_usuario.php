<?php
session_start();
require_once "../config.php";

/* =========================================
   👤 OBTENER USUARIO / PERFIL
========================================= */

$userId = (int) ($_SESSION['id'] ?? 0);

$nombre = "Usuario";
$foto = "../Logo/Logo Nuevo -512x512.png";

/* =========================================
   🔥 USUARIO PRINCIPAL
========================================= */

if($userId > 0){

$stmt = $conn->prepare("
SELECT id, name, foto
FROM users
WHERE id=?
LIMIT 1
");

$stmt->bind_param("i",$userId);
$stmt->execute();

$user =
$stmt
->get_result()
->fetch_assoc();

if($user){

$nombre =
$user['name'] ?: "Usuario";

/* =========================================
   🖼 FOTO USUARIO
========================================= */

if(
!empty($user['foto']) &&
file_exists("../".$user['foto'])
){

$foto =
"../".$user['foto'];

}

}

/* =========================================
   👤 PERFIL ACTIVO
========================================= */

if(isset($_SESSION['perfil_id'])){

$perfilId =
(int) $_SESSION['perfil_id'];

$stmtPerfil = $conn->prepare("
SELECT nombre,foto
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

$nombre =
$perfil['nombre'] ?: $nombre;

/* =========================================
   🖼 FOTO PERFIL
========================================= */

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

}

}

}

}

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
$foto   = !empty($user['foto']) ? $user['foto'] : '../Logo/Logo Nuevo -512x512.png';


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

<meta charset="UTF-8" />

<meta
name="viewport"
content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<link
rel="icon"
type="image/png"
href="../Logo/Logo Nuevo -512x512.png">

<title>Historial</title>

<style>

/* =========================================================
🌌 RESET
========================================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
-webkit-tap-highlight-color:transparent;
}

:root{

--bg:#050505;
--card:#101010;
--card2:#171717;

--text:#ffffff;
--muted:#9b9b9b;

--primary:#00bfff;
--secondary:#ff006a;

--glass:rgba(255,255,255,.06);

--radius:18px;

--shadow:
0 10px 40px rgba(0,0,0,.45);

}

html{
scroll-behavior:smooth;
}

body{

font-family:
Inter,
system-ui,
sans-serif;

background:
radial-gradient(circle at top left,
rgba(0,191,255,.10),
transparent 30%),

radial-gradient(circle at bottom right,
rgba(255,0,106,.08),
transparent 35%),

#050505;

color:var(--text);

min-height:100vh;
overflow-x:hidden;

text-rendering:optimizeSpeed;
-webkit-font-smoothing:antialiased;
-moz-osx-font-smoothing:grayscale;

}

/* =========================================================
🔥 PERFORMANCE
========================================================= */

img{
display:block;
max-width:100%;
user-select:none;
-webkit-user-drag:none;
}

button,
input{
font-family:inherit;
}

.grid{
contain:layout style paint;
}

.item{
contain:layout paint;
content-visibility:auto;
contain-intrinsic-size:320px;
transform:translateZ(0);
}

.poster img{
transform:translateZ(0);
backface-visibility:hidden;
}

.topbar{
transform:translateZ(0);
}

/* =========================================================
🔥 SCROLL
========================================================= */

::-webkit-scrollbar{
width:7px;
}

::-webkit-scrollbar-track{
background:#080808;
}

::-webkit-scrollbar-thumb{
background:linear-gradient(
180deg,
var(--primary),
var(--secondary)
);
border-radius:20px;
}

/* =========================================================
🧊 TOP BAR
========================================================= */

.topbar{

position:sticky;
top:0;
z-index:999;

background:
linear-gradient(
180deg,
rgba(10,10,10,.96),
rgba(10,10,10,.75)
);

backdrop-filter:blur(8px);

border-bottom:
1px solid rgba(255,255,255,.05);

padding:
16px
14px;

}

.topbar-inner{

max-width:1700px;
margin:auto;

display:flex;
gap:12px;
align-items:center;
justify-content:space-between;

flex-wrap:wrap;

}

/* =========================================================
🎬 LOGO
========================================================= */

.brand{

display:flex;
align-items:center;
gap:12px;

}

.brand img{

width:52px;
height:52px;

border-radius:16px;

object-fit:cover;

box-shadow:
0 0 20px rgba(0,191,255,.18);

animation:pulseLogo 6s ease-in-out infinite;

}

@keyframes pulseLogo{

0%,100%{
transform:scale(1);
}

50%{
transform:scale(1.03);
}

}

.brand-text h1{

font-size:1.15rem;
font-weight:800;

letter-spacing:.5px;

background:
linear-gradient(
90deg,
#00bfff,
#ff006a
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;

}

.brand-text p{

font-size:.76rem;
color:var(--muted);

margin-top:2px;

}

/* =========================================================
🔍 SEARCH
========================================================= */

.search-wrapper{

position:relative;
flex:1;
min-width:220px;

}

.search-box{

width:100%;
height:52px;

padding:
0 52px 0 18px;

border:none;
outline:none;

background:
rgba(255,255,255,.05);

border:
1px solid rgba(255,255,255,.06);

border-radius:16px;

color:#fff;

font-size:.95rem;

transition:
background .15s ease,
border-color .15s ease;

backdrop-filter:blur(5px);

}

.search-box:focus{

border-color:
rgba(0,191,255,.45);

background:
rgba(255,255,255,.07);

}

.search-icon{

position:absolute;
right:16px;
top:50%;

transform:translateY(-50%);

font-size:1rem;
opacity:.7;

pointer-events:none;

}

/* =========================================================
🎛️ CONTROLS
========================================================= */

.controls{

display:flex;
gap:10px;
flex-wrap:wrap;

}

.control-btn{

height:50px;

padding:
0 18px;

border:none;
outline:none;

border-radius:15px;

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.05);

color:#fff;

font-weight:600;

cursor:pointer;

transition:
transform .16s ease,
background .16s ease;

backdrop-filter:blur(6px);

}

.control-btn:hover{

transform:translateY(-2px);

background:
linear-gradient(
135deg,
rgba(0,191,255,.16),
rgba(255,0,106,.16)
);

}

/* =========================================================
📊 STATS
========================================================= */

.stats{

max-width:1700px;

margin:
18px auto 0;

display:flex;
gap:12px;
flex-wrap:wrap;

padding:0 14px;

}

.stat-card{

flex:1;
min-width:140px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.04),
rgba(255,255,255,.02)
);

border:
1px solid rgba(255,255,255,.05);

border-radius:18px;

padding:16px;

overflow:hidden;

position:relative;

box-shadow:var(--shadow);

}

.stat-card::before{

content:"";

position:absolute;
inset:0;

background:
linear-gradient(
120deg,
transparent,
rgba(255,255,255,.025),
transparent
);

transform:translateX(-100%);
animation:shine 9s linear infinite;

}

@keyframes shine{

100%{
transform:translateX(100%);
}

}

.stat-label{

font-size:.8rem;
color:#b8b8b8;

margin-bottom:6px;

}

.stat-value{

font-size:1.3rem;
font-weight:800;

}

/* =========================================================
🎞️ GRID
========================================================= */

.grid{

max-width:1700px;

margin:
24px auto 120px;

padding:
0 14px;

display:grid;

grid-template-columns:
repeat(auto-fill,minmax(180px,1fr));

gap:18px;

}

/* =========================================================
🎬 CARD
========================================================= */

.item{

position:relative;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:
1px solid rgba(255,255,255,.05);

border-radius:22px;

overflow:hidden;

cursor:pointer;

opacity:0;
transform:translateY(8px);

animation:cardIn .22s ease forwards;

transition:
transform .16s ease,
border-color .16s ease;

box-shadow:var(--shadow);

}

@keyframes cardIn{

to{
opacity:1;
transform:translateY(0);
}

}

.item:hover{

transform:
translateY(-4px);

border-color:
rgba(0,191,255,.28);

}

.poster{

position:relative;
overflow:hidden;

aspect-ratio:2/3;

background:#0d0d0d;

}

.poster img{

width:100%;
height:100%;

object-fit:cover;

transition:transform .28s ease;

}

.item:hover .poster img{
transform:scale(1.04);
}

.poster::after{

content:"";

position:absolute;
inset:0;

background:
linear-gradient(
180deg,
transparent 45%,
rgba(0,0,0,.92)
);

}

/* =========================================================
🏷️ BADGES
========================================================= */

.category-badge{

position:absolute;

top:12px;
left:12px;

z-index:5;

padding:
6px 10px;

border-radius:999px;

font-size:.62rem;
font-weight:800;

letter-spacing:.5px;

border:
1px solid rgba(255,255,255,.08);

backdrop-filter:blur(4px);

}

.category-pelicula{
background:linear-gradient(135deg,#ff2d55,#ff0055);
}

.category-serie{
background:linear-gradient(135deg,#8a2eff,#5a00ff);
}

.category-trailer{
background:linear-gradient(135deg,#00aaff,#004cff);
}

/* =========================================================
🗑️ DELETE BTN
========================================================= */

.delete-btn{

position:absolute;

top:12px;
right:12px;

width:36px;
height:36px;

border:none;

border-radius:50%;

background:
rgba(0,0,0,.55);

color:#fff;

font-size:1rem;

cursor:pointer;

z-index:5;

transition:
transform .15s ease,
background .15s ease;

}

.delete-btn:hover{

background:#ff004c;

transform:
scale(1.04);

}

/* =========================================================
📝 CONTENT
========================================================= */

.item-content{
padding:14px;
}

.item-title{

font-size:.88rem;
font-weight:700;

line-height:1.35;

display:-webkit-box;
-webkit-line-clamp:2;
-webkit-box-orient:vertical;

overflow:hidden;

min-height:38px;

}

.item-meta{

margin-top:10px;

display:flex;
justify-content:space-between;
align-items:center;

font-size:.72rem;
color:#b5b5b5;

}

.watch-pill{

padding:
5px 10px;

border-radius:999px;

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.05);

}

/* =========================================================
✨ EMPTY
========================================================= */

#noResultsMsg{

display:none;

text-align:center;

padding:
60px 20px;

font-size:1rem;

color:#999;

}

/* =========================================================
⚡ MULTI SELECT
========================================================= */

.selector{

position:absolute;

top:12px;
right:12px;

width:28px;
height:28px;

border-radius:10px;

background:
rgba(0,0,0,.55);

border:
2px solid rgba(255,255,255,.6);

z-index:6;

display:none;

transition:.15s;

}

.multi-select-active .selector{
display:block;
}

.multi-select-active .delete-btn{
display:none;
}

.item.selected .selector{

background:
linear-gradient(
135deg,
#00ff99,
#00d67f
);

border-color:#00ff99;

}

/* =========================================================
🧊 FLOAT ACTIONS
========================================================= */

.float-actions{

position:fixed;

left:50%;
bottom:20px;

transform:translateX(-50%);

width:min(92%,520px);

display:flex;
gap:12px;

padding:10px;

background:
rgba(12,12,12,.82);

border:
1px solid rgba(255,255,255,.08);

border-radius:24px;

backdrop-filter:blur(14px);

box-shadow:
0 15px 40px rgba(0,0,0,.45);

z-index:99999;

opacity:0;
pointer-events:none;

transition:
opacity .2s ease,
transform .2s ease;

}

.float-actions.active{

opacity:1;
pointer-events:auto;

transform:
translateX(-50%)
translateY(0);

}

.float-btn{

flex:1;

height:54px;

padding:
0 18px;

border:none;

border-radius:16px;

font-weight:700;

cursor:pointer;

color:#fff;

font-size:.9rem;

transition:
transform .15s ease,
opacity .15s ease;

}

.float-btn:hover{
transform:translateY(-2px);
}

#multiDeleteBtn{

background:
linear-gradient(
135deg,
#ff004c,
#ff3d71
);

}

#cancelSelectBtn{

background:
rgba(255,255,255,.08);

border:
1px solid rgba(255,255,255,.06);

}

/* =========================================================
🧊 MODALS
========================================================= */

.modal-order,
.delete-modal{

position:fixed;
inset:0;

display:none;

align-items:center;
justify-content:center;

padding:18px;

background:
rgba(0,0,0,.72);

backdrop-filter:blur(6px);

z-index:99999;

}

.order-box-modal,
.delete-box{

width:100%;
max-width:430px;

background:
linear-gradient(
180deg,
#141414,
#0b0b0b
);

border-radius:28px;

border:
1px solid rgba(255,255,255,.06);

overflow:hidden;

animation:modalIn .18s ease;

}

@keyframes modalIn{

from{
opacity:0;
transform:translateY(10px) scale(.98);
}

to{
opacity:1;
transform:translateY(0) scale(1);
}

}

.order-header{

padding:22px;

font-size:1.1rem;
font-weight:800;

border-bottom:
1px solid rgba(255,255,255,.05);

}

.order-option,
.close-order{

width:100%;

padding:18px;

background:none;

border:none;

border-bottom:
1px solid rgba(255,255,255,.04);

color:#fff;

font-size:.95rem;

cursor:pointer;

transition:.15s;

}

.order-option:hover,
.order-option.active{

background:
linear-gradient(
90deg,
rgba(0,191,255,.16),
rgba(255,0,106,.16)
);

}

/* =========================================================
🗑️ DELETE MODAL
========================================================= */

.delete-media{

padding:20px;

display:flex;
justify-content:center;

}

.delete-img{

width:100%;
max-height:380px;

object-fit:contain;

border-radius:20px;

}

.delete-info{

padding:
0 22px 18px;

text-align:center;

}

.delete-title{

font-size:1.1rem;
font-weight:800;

margin-bottom:8px;

}

.delete-text{

font-size:.88rem;
color:#9f9f9f;

}

.delete-actions{

display:flex;
gap:12px;

padding:20px;

}

.btn-delete,
.btn-cancel{

flex:1;

height:54px;

border:none;

border-radius:16px;

font-weight:700;

cursor:pointer;

transition:transform .15s ease;

}

.btn-delete{

background:
linear-gradient(
135deg,
#ff004c,
#ff4d7c
);

color:#fff;

}

.btn-cancel{

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.05);

color:#fff;

}

/* =========================================================
📱 MOBILE
========================================================= */

@media(max-width:768px){

.grid{

grid-template-columns:
repeat(,minmax(0,1fr));

gap:12px;
padding:0 10px;

}

.poster{
aspect-ratio:2/2.9;
}

.topbar{
padding:14px 10px;
}

.stats{
padding:0 10px;
}

.item-title{
font-size:.76rem;
}

.float-actions{

bottom:max(12px,env(safe-area-inset-bottom));

width:calc(100% - 16px);

gap:10px;

padding:10px;

border-radius:22px;

}

.float-btn{

height:50px;

font-size:.82rem;

padding:0 12px;

}

}

/* =========================================================
🍎 IOS
========================================================= */

@media only screen
and (-webkit-min-device-pixel-ratio:2)
and (max-width:932px){

body{
-webkit-overflow-scrolling:touch;
}

.topbar{
padding-top:max(14px,env(safe-area-inset-top));
}

.float-actions{

padding-left:max(10px,env(safe-area-inset-left));
padding-right:max(10px,env(safe-area-inset-right));

}

input{
font-size:16px;
}

}

/* =========================================================
🖥️ PC
========================================================= */

@media(min-width:1024px){

.grid{

grid-template-columns:
repeat(auto-fill,minmax(210px,1fr));

gap:20px;
padding:0 20px;

}

.float-actions{
width:auto;
min-width:420px;
}

}

/* =========================================================
🔥 REDUCIR ANIMACIONES
========================================================= */

@media(prefers-reduced-motion:reduce){

*{
animation:none !important;
transition:none !important;
scroll-behavior:auto !important;
}

}

</style>

</head>

<body>

<!-- =========================================================
🔥 TOPBAR
========================================================= -->

<div class="topbar">

<div class="topbar-inner">

<div class="brand">

<img
src="../Logo/Logo Nuevo -512x512.png"
loading="lazy"
decoding="async">

<div class="brand-text">
<h1>MovieTx Historial</h1>
<p>Experiencia ultrarrápida</p>
</div>

</div>

<div class="search-wrapper">

<input
id="buscar"
class="search-box"
placeholder="Buscar película, serie o trailer..."
autocomplete="off"
spellcheck="false">

<span class="search-icon">⌕</span>

</div>

<div class="controls">

<button
id="openOrderMenu"
class="control-btn">

Más recientes

</button>

</div>

</div>

</div>

<!-- =========================================================
📊 STATS
========================================================= -->

<div class="stats">

<div class="stat-card">
<div class="stat-label">Contenido</div>
<div class="stat-value" id="totalItems">0</div>
</div>

<div class="stat-card">
<div class="stat-label">Películas</div>
<div class="stat-value" id="totalMovies">0</div>
</div>

<div class="stat-card">
<div class="stat-label">Series</div>
<div class="stat-value" id="totalSeries">0</div>
</div>

<div class="stat-card">
<div class="stat-label">Trailers</div>
<div class="stat-value" id="totalTrailers">0</div>
</div>

</div>

<p id="noResultsMsg">
No encontramos resultados en tu historial
</p>

<div
class="grid"
id="historial-container">
</div>

<!-- =========================================================
⚡ FLOAT ACTIONS
========================================================= -->

<div class="float-actions" id="floatActions">

<button
id="cancelSelectBtn"
class="float-btn">

Cancelar

</button>

<button
id="multiDeleteBtn"
class="float-btn">

Eliminar seleccionados

</button>

</div>

<!-- =========================================================
🎛️ ORDER MODAL
========================================================= -->

<div id="orderModal" class="modal-order">

<div class="order-box-modal">

<div class="order-header">
Ordenar historial
</div>

<button
class="order-option active"
data-value="recientes">

Más recientes

</button>

<button
class="order-option"
data-value="antiguos">

Más antiguos

</button>

<button class="close-order">
Cerrar
</button>

</div>

</div>

<!-- =========================================================
🗑️ DELETE MODAL
========================================================= -->

<div id="deleteModal" class="delete-modal">

<div class="delete-box">

<div class="delete-media">

<img
id="deleteImg"
class="delete-img"
loading="lazy"
decoding="async">

</div>

<div class="delete-info">

<h3
id="deleteTitle"
class="delete-title">
</h3>

<p class="delete-text">
Esta acción eliminará el contenido del historial
</p>

</div>

<div class="delete-actions">

<button
id="confirmDelete"
class="btn-delete">

Eliminar

</button>

<button
id="cancelDelete2"
class="btn-cancel">

Cancelar

</button>

</div>

</div>

</div>

<script>

/* =========================================================
⚡ HELPERS
========================================================= */

const qs = s => document.querySelector(s);
const qsa = s => document.querySelectorAll(s);

const container = qs("#historial-container");

const totalItems = qs("#totalItems");
const totalMovies = qs("#totalMovies");
const totalSeries = qs("#totalSeries");
const totalTrailers = qs("#totalTrailers");

function normalizar(str=""){

return str
.normalize("NFD")
.replace(/[\u0300-\u036f]/g,"")
.toLowerCase();

}

/* =========================================================
🔥 VARIABLES
========================================================= */

let historial = [];
let historialRender = [];

let seleccionados = new Set();

let multiMode = false;
let indexAEliminar = null;
let ordenActual = "recientes";

let searchRAF = null;
let touchTimer = null;

/* =========================================================
🚀 CARGA
========================================================= */

async function cargarHistorial(){

try{

const [a,b] = await Promise.all([

fetch("obtener_historial.php"),
fetch("perfil_obtener_historial.php")

]);

const [normal,perfil] = await Promise.all([
a.json(),
b.json()
]);

const data = [

...(Array.isArray(normal)
? normal
: []),

...(Array.isArray(perfil)
? perfil
: [])

];

historial = data.map(item=>{

let tipo =
(item.tipo || "")
.toLowerCase();

if(tipo === "series"){
tipo = "serie";
}

if(
tipo === "trailer" ||
item.archivo?.includes("trailers")
){
tipo = "trailer";
}

if(
tipo !== "serie" &&
tipo !== "pelicula" &&
tipo !== "trailer"
){

tipo =
item.movie_id?.includes("s01")
? "serie"
: "pelicula";

}

return{

...item,

tipo,

timestamp:
item.timestamp ||
new Date(
item.visto_en ||
item.creado_en ||
Date.now()
).getTime(),

titulo:
item.titulo ||
(item.movie_id || "")
.replace(/_/g," ")
.replace(/\b\w/g,l=>l.toUpperCase())

};

});

actualizarStats();

renderHistorial();

}catch(err){

console.error(err);

}

}

cargarHistorial();

/* =========================================================
📊 STATS
========================================================= */

function actualizarStats(){

let peliculas = 0;
let series = 0;
let trailers = 0;

for(let i=0;i<historial.length;i++){

const tipo = historial[i].tipo;

if(tipo === "pelicula") peliculas++;
else if(tipo === "serie") series++;
else if(tipo === "trailer") trailers++;

}

totalItems.textContent = historial.length;
totalMovies.textContent = peliculas;
totalSeries.textContent = series;
totalTrailers.textContent = trailers;

}

/* =========================================================
🎞️ RENDER
========================================================= */

function renderHistorial(){

if(!historial.length){

container.innerHTML = "";

qs("#noResultsMsg").style.display = "block";

return;

}

qs("#noResultsMsg").style.display = "none";

/* 🔥 IMPORTANTE:
NO mutar historial original */
historialRender = [...historial];

historialRender.sort((a,b)=>

ordenActual === "recientes"
? b.timestamp-a.timestamp
: a.timestamp-b.timestamp

);

const now = Date.now();

let html = "";

historialRender.forEach((item,index)=>{

const edad = now - item.timestamp;

let tiempo = "Hoy";

if(edad > 86400000){
tiempo = "Ayer";
}

if(edad > 172800000){

tiempo =
Math.floor(edad/86400000)
+ " días";

}

html += `

<div
class="item"
data-index="${index}">

<div class="selector"></div>

<div class="poster">

<img
src="${item.imagen}"
loading="lazy"
decoding="async"
draggable="false">

<div class="category-badge category-${item.tipo}">
${item.tipo.toUpperCase()}
</div>

<button
class="delete-btn"
data-delete="${index}">

✕

</button>

</div>

<div class="item-content">

<div class="item-title">
${item.titulo}
</div>

<div class="item-meta">

<div class="watch-pill">
${tiempo}
</div>

<div>
▶ Ver
</div>

</div>

</div>

</div>

`;

});

container.innerHTML = html;

}

/* =========================================================
🎬 ABRIR CONTENIDO
========================================================= */

function abrirItemHistorial(item){

if(!item) return;

/* 🔥 DESTINO FINAL */
let destino = "";

/* =========================================================
🔥 PRIORIDAD LINKS DIRECTOS
========================================================= */

if(item.url){

destino = item.url;

}

else if(item.link){

destino = item.link;

}

else if(item.archivo){

destino = item.archivo;

}

/* =========================================================
🔥 GENERAR AUTOMÁTICAMENTE
========================================================= */

else if(item.tipo === "serie"){

destino =
`serie.html?id=${encodeURIComponent(item.movie_id)}`;

}

else if(item.tipo === "trailer"){

destino =
`trailer.html?id=${encodeURIComponent(item.movie_id)}`;

}

else{

destino =
`pelicula.html?id=${encodeURIComponent(item.movie_id)}`;

}

/* =========================================================
🚫 SI NO EXISTE DESTINO
========================================================= */

if(!destino){

console.warn(
"No se encontró destino:",
item
);

return;

}

/* =========================================================
🔞 CONTROL ADULTOS
========================================================= */

if(item.adulto === true){

if(typeof verificarAcceso === "function"){

verificarAcceso(destino,true);

}else{

window.location.href = destino;

}

}else{

window.location.href = destino;

}

}

/* =========================================================
🔥 EVENTS
========================================================= */

container.addEventListener("click",e=>{

const deleteBtn =
e.target.closest(".delete-btn");

if(deleteBtn){

e.stopPropagation();

eliminarItem(
Number(deleteBtn.dataset.delete)
);

return;

}

const card =
e.target.closest(".item");

if(!card) return;

const index =
Number(card.dataset.index);

/* 🔥 IMPORTANTE:
usar historialRender */
const item =
historialRender[index];

if(!item) return;

if(multiMode){

card.classList.toggle("selected");

if(card.classList.contains("selected")){

seleccionados.add(item.movie_id);

}else{

seleccionados.delete(item.movie_id);

}

return;

}

/* 🔥 ABRIR */
abrirItemHistorial(item);

});

/* =========================================================
📱 TOUCH MULTISELECT
========================================================= */

container.addEventListener(
"touchstart",
e=>{

const card =
e.target.closest(".item");

if(!card) return;

clearTimeout(touchTimer);

touchTimer = setTimeout(()=>{

if(multiMode) return;

activarSeleccionMultiple();

card.classList.add("selected");

const index =
Number(card.dataset.index);

const item =
historialRender[index];

if(item){

seleccionados.add(
item.movie_id
);

}

navigator.vibrate?.(20);

},450);

},
{passive:true}
);

container.addEventListener(
"touchend",
()=>clearTimeout(touchTimer),
{passive:true}
);

container.addEventListener(
"touchmove",
()=>clearTimeout(touchTimer),
{passive:true}
);

/* =========================================================
🔍 SEARCH
========================================================= */

qs("#buscar").addEventListener("input",e=>{

cancelAnimationFrame(searchRAF);

searchRAF = requestAnimationFrame(()=>{

const term =
normalizar(e.target.value);

const cards =
qsa(".item");

let visibles = 0;

cards.forEach(card=>{

const title =
normalizar(
card.querySelector(".item-title")
.textContent
);

const visible =
title.includes(term);

card.style.display =
visible ? "" : "none";

if(visible) visibles++;

});

qs("#noResultsMsg").style.display =
visibles ? "none" : "block";

});

});

/* =========================================================
⚡ MULTI MODE
========================================================= */

function activarSeleccionMultiple(){

multiMode = true;

document.body.classList.add(
"multi-select-active"
);

qs("#floatActions")
.classList.add("active");

}

function cerrarSeleccionMultiple(){

multiMode = false;

seleccionados.clear();

document.body.classList.remove(
"multi-select-active"
);

qsa(".item").forEach(item=>
item.classList.remove("selected")
);

qs("#floatActions")
.classList.remove("active");

}

qs("#cancelSelectBtn").onclick =
cerrarSeleccionMultiple;

/* =========================================================
🗑️ MULTI DELETE
========================================================= */

qs("#multiDeleteBtn").onclick =
async ()=>{

const itemsEliminar =
historial.filter(item=>
seleccionados.has(item.movie_id)
);

const tareas = [];

itemsEliminar.forEach(item=>{

const body =
"movie_id=" +
encodeURIComponent(item.movie_id)
+
"&tipo=" +
encodeURIComponent(item.tipo);

tareas.push(

fetch("eliminar_historial.php",{
method:"POST",
headers:{
"Content-Type":
"application/x-www-form-urlencoded"
},
body
})

);

tareas.push(

fetch("perfil_eliminar_historial.php",{
method:"POST",
headers:{
"Content-Type":
"application/x-www-form-urlencoded"
},
body
})

);

});

await Promise.all(tareas);

historial =
historial.filter(item=>
!seleccionados.has(item.movie_id)
);

actualizarStats();

cerrarSeleccionMultiple();

renderHistorial();

};

/* =========================================================
🗑️ DELETE
========================================================= */

function eliminarItem(index){

const item =
historialRender[index];

if(!item) return;

indexAEliminar =
historial.findIndex(i=>
i.movie_id === item.movie_id
);

qs("#deleteImg").src =
item.imagen;

qs("#deleteTitle").textContent =
item.titulo;

qs("#deleteModal").style.display =
"flex";

}

qs("#cancelDelete2").onclick = ()=>{

qs("#deleteModal").style.display =
"none";

};

qs("#deleteModal").onclick = e=>{

if(e.target.id === "deleteModal"){

qs("#deleteModal").style.display =
"none";

}

};

qs("#confirmDelete").onclick =
async ()=>{

const item =
historial[indexAEliminar];

if(!item) return;

const body =
"movie_id=" +
encodeURIComponent(item.movie_id)
+
"&tipo=" +
encodeURIComponent(item.tipo);

await Promise.all([

fetch("eliminar_historial.php",{
method:"POST",
headers:{
"Content-Type":
"application/x-www-form-urlencoded"
},
body
}),

fetch("perfil_eliminar_historial.php",{
method:"POST",
headers:{
"Content-Type":
"application/x-www-form-urlencoded"
},
body
})

]);

historial.splice(indexAEliminar,1);

actualizarStats();

qs("#deleteModal").style.display =
"none";

renderHistorial();

};

/* =========================================================
🎛️ ORDER
========================================================= */

qs("#openOrderMenu").onclick = ()=>{

qs("#orderModal").style.display =
"flex";

};

qs(".close-order").onclick = ()=>{

qs("#orderModal").style.display =
"none";

};

qsa(".order-option").forEach(btn=>{

btn.onclick = ()=>{

qsa(".order-option")
.forEach(b=>
b.classList.remove("active")
);

btn.classList.add("active");

ordenActual =
btn.dataset.value;

qs("#openOrderMenu").textContent =
btn.textContent;

renderHistorial();

qs("#orderModal").style.display =
"none";

};

});

</script>

</body>
</html>