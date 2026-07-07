<?php
session_start();

/* =========================================================
🚨 SEGURIDAD ADMIN
========================================================= */

if(
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
){
    session_unset();
    session_destroy();
}

/* =========================================================
📦 MENSAJES
========================================================= */

$loginError =
$_SESSION['login_error'] ?? '';

$registerError =
$_SESSION['register_error'] ?? '';

$registerSuccess =
$_SESSION['success'] ?? '';

$adminSuccess =
$_SESSION['admin_register_success'] ?? '';

$showAdminRegister =
$_SESSION['show_admin_register'] ?? true;

unset(
    $_SESSION['login_error'],
    $_SESSION['register_error'],
    $_SESSION['success'],
    $_SESSION['admin_register_success'],
    $_SESSION['show_admin_register']
);

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width,
initial-scale=1,
viewport-fit=cover">

<title>MovieTx</title>

<link
rel="icon"
type="image/png"
href="Logo/Logo Nuevo -512x512.png">

<link
rel="preconnect"
href="https://fonts.googleapis.com">

<link
rel="preconnect"
href="https://fonts.gstatic.com"
crossorigin>

<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
rel="stylesheet">

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
--card:#0f0f12;
--card2:#15151a;

--text:#ffffff;
--muted:#9d9d9d;

--primary:#00bfff;
--secondary:#ff006a;

--border:
rgba(255,255,255,.06);

--glass:
rgba(255,255,255,.05);

--shadow:
0 25px 60px rgba(0,0,0,.45);

--radius:28px;

}

html{
scroll-behavior:smooth;
}

body{

font-family:
'Inter',
system-ui,
sans-serif;

background:
radial-gradient(
circle at top left,
rgba(0,191,255,.16),
transparent 30%
),

radial-gradient(
circle at bottom right,
rgba(255,0,106,.12),
transparent 30%
),

#050505;

color:var(--text);

min-height:100vh;
overflow-x:hidden;

display:flex;
align-items:center;
justify-content:center;

padding:20px;

position:relative;

}

/* =========================================================
🌌 BACKGROUND FX
========================================================= */

body::before,
body::after{

content:"";

position:fixed;

width:420px;
height:420px;

border-radius:50%;

filter:blur(90px);

opacity:.18;

pointer-events:none;

z-index:-1;

}

body::before{

top:-140px;
left:-120px;

background:#00bfff;

animation:float1 12s ease-in-out infinite;

}

body::after{

bottom:-140px;
right:-120px;

background:#ff006a;

animation:float2 14s ease-in-out infinite;

}

@keyframes float1{

0%,100%{
transform:translateY(0);
}

50%{
transform:translateY(30px);
}

}

@keyframes float2{

0%,100%{
transform:translateY(0);
}

50%{
transform:translateY(-25px);
}

}

/* =========================================================
📦 APP
========================================================= */

.app{

width:100%;
max-width:1240px;

display:grid;

grid-template-columns:
1.05fr .95fr;

overflow:hidden;

border-radius:34px;

border:
1px solid rgba(255,255,255,.06);

background:
linear-gradient(
180deg,
rgba(255,255,255,.04),
rgba(255,255,255,.02)
);

backdrop-filter:blur(16px);

box-shadow:var(--shadow);

min-height:760px;

}

/* =========================================================
🎬 HERO
========================================================= */

.hero{

position:relative;

padding:60px;

display:flex;
flex-direction:column;
justify-content:space-between;

overflow:hidden;

background:
linear-gradient(
160deg,
rgba(0,191,255,.10),
rgba(255,0,106,.04)
);

}

.hero::before{

content:"";

position:absolute;
inset:0;

background:
linear-gradient(
180deg,
transparent,
rgba(0,0,0,.45)
);

pointer-events:none;

}

.hero-top{

position:relative;
z-index:2;

}

.hero-logo{

display:flex;
align-items:center;
gap:16px;

margin-bottom:34px;

}

.hero-logo img{

width:74px;
height:74px;

border-radius:24px;

object-fit:cover;

box-shadow:
0 0 35px rgba(0,191,255,.25);

animation:pulseLogo 6s ease infinite;

}

@keyframes pulseLogo{

0%,100%{
transform:scale(1);
}

50%{
transform:scale(1.04);
}

}

.hero-logo-text h1{

font-size:2rem;
font-weight:900;

background:
linear-gradient(
90deg,
#00bfff,
#ff006a
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;

}

.hero-logo-text p{

margin-top:4px;

color:var(--muted);

font-size:.92rem;

}

.hero-content{

position:relative;
z-index:2;

max-width:520px;

}

.hero-badge{

display:inline-flex;
align-items:center;
gap:10px;

padding:
10px 16px;

border-radius:999px;

background:
rgba(255,255,255,.05);

border:
1px solid rgba(255,255,255,.07);

font-size:.82rem;
font-weight:700;

margin-bottom:24px;

backdrop-filter:blur(10px);

}

.hero-title{

font-size:3.2rem;
font-weight:900;

line-height:1.02;

letter-spacing:-1.5px;

margin-bottom:22px;

}

.hero-title span{

background:
linear-gradient(
90deg,
#00bfff,
#ff006a
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;

}

.hero-text{

font-size:1rem;
line-height:1.8;

color:#cfcfcf;

max-width:480px;

}

.hero-stats{

position:relative;
z-index:2;

display:flex;
gap:18px;

margin-top:42px;

flex-wrap:wrap;

}

.hero-card{

flex:1;
min-width:150px;

padding:18px;

border-radius:22px;

background:
rgba(255,255,255,.04);

border:
1px solid rgba(255,255,255,.06);

backdrop-filter:blur(10px);

}

.hero-card strong{

display:block;

font-size:1.45rem;
font-weight:800;

margin-bottom:6px;

}

.hero-card span{

font-size:.85rem;
color:#bcbcbc;

}

/* =========================================================
🔐 PANEL
========================================================= */

.auth{

padding:42px;

display:flex;
align-items:center;
justify-content:center;

position:relative;

}

.forms{

width:100%;
max-width:460px;

}

.form-box{

display:none;

animation:fade .28s ease;

}

.form-box.active{
display:block;
}

@keyframes fade{

from{
opacity:0;
transform:translateY(12px);
}

to{
opacity:1;
transform:translateY(0);
}

}

.form-card{

padding:34px;

border-radius:30px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.04),
rgba(255,255,255,.02)
);

border:
1px solid rgba(255,255,255,.06);

box-shadow:
0 18px 50px rgba(0,0,0,.28);

}

/* =========================================================
🧠 HEADER FORM
========================================================= */

.form-header{

text-align:center;

margin-bottom:26px;

}

.form-avatar{

width:90px;
height:90px;

margin:auto auto 18px;

border-radius:26px;

overflow:hidden;

background:#101010;

border:
1px solid rgba(255,255,255,.06);

box-shadow:
0 0 25px rgba(0,191,255,.16);

}

.form-avatar img{

width:100%;
height:100%;

object-fit:cover;

}

.form-header h2{

font-size:1.7rem;
font-weight:900;

margin-bottom:8px;

}

.form-header p{

font-size:.92rem;
color:var(--muted);

}

/* =========================================================
💳 PLAN BOX
========================================================= */

.plan-box{

padding:18px;

border-radius:22px;

margin-bottom:22px;

background:
rgba(255,255,255,.03);

border:
1px solid rgba(255,255,255,.06);

}

.plan-price{

font-size:1.2rem;
font-weight:900;

margin-bottom:10px;

color:#00bfff;

}

.plan-text{

font-size:.9rem;
line-height:1.7;

color:#d3d3d3;

}

.plan-note{

margin-top:12px;

font-size:.84rem;
line-height:1.6;

color:#a8a8a8;

}

/* =========================================================
📥 INPUTS
========================================================= */

.input-group{

margin-bottom:16px;

}

.input-label{

display:block;

font-size:.82rem;
font-weight:700;

margin-bottom:10px;

color:#d0d0d0;

}

.input-box{

position:relative;

}

.input-box input{

width:100%;
height:58px;

padding:
0 52px 0 18px;

border:none;
outline:none;

border-radius:18px;

background:
rgba(255,255,255,.04);

border:
1px solid rgba(255,255,255,.06);

color:#fff;

font-size:.95rem;

transition:
border-color .16s ease,
background .16s ease,
transform .16s ease;

}

.input-box input:focus{

border-color:
rgba(0,191,255,.45);

background:
rgba(255,255,255,.06);

transform:translateY(-1px);

}

.toggle-pass{

position:absolute;

right:16px;
top:50%;

transform:translateY(-50%);

width:26px;
height:26px;

display:flex;
align-items:center;
justify-content:center;

cursor:pointer;

opacity:.72;

transition:.16s;

}

.toggle-pass:hover{
opacity:1;
}

.toggle-pass svg{

width:22px;
height:22px;

fill:#fff;

}

/* =========================================================
🚀 BUTTONS
========================================================= */

.btn{

width:100%;
height:58px;

border:none;
outline:none;

border-radius:18px;

cursor:pointer;

font-size:.95rem;
font-weight:800;

transition:
transform .16s ease,
opacity .16s ease;

}

.btn:hover{
transform:translateY(-2px);
}

.btn-primary{

background:
linear-gradient(
135deg,
#00bfff,
#006eff
);

color:#fff;

box-shadow:
0 14px 28px rgba(0,191,255,.24);

}

.btn-secondary{

margin-top:12px;

background:
rgba(255,255,255,.05);

border:
1px solid rgba(255,255,255,.06);

color:#fff;

}

/* =========================================================
📎 LINKS
========================================================= */

.form-links{

margin-top:18px;

text-align:center;

font-size:.9rem;

color:#bcbcbc;

}

.form-links a{

color:#00bfff;

text-decoration:none;

font-weight:700;

}

.form-links a:hover{
text-decoration:underline;
}

.link-btn{

background:none;
border:none;

padding:0;
margin:0;

color:#00bfff;

font-size:.92rem;
font-weight:700;

cursor:pointer;

transition:
opacity .15s ease,
transform .15s ease;

}

.link-btn:hover{

opacity:.9;

transform:translateY(-1px);

text-decoration:underline;

}

/* =========================================================
🚨 ALERTS
========================================================= */

.alert{

padding:14px 16px;

border-radius:16px;

margin-bottom:18px;

font-size:.88rem;
font-weight:600;

border:
1px solid transparent;

}

.alert-error{

background:
rgba(255,0,76,.10);

border-color:
rgba(255,0,76,.22);

color:#ff7a9e;

}

.alert-success{

background:
rgba(0,255,153,.10);

border-color:
rgba(0,255,153,.22);

color:#66ffc2;

}

/* =========================================================
⚠️ EXPIRED
========================================================= */

.expired{

margin-top:18px;

padding:18px;

border-radius:20px;

background:
rgba(255,0,76,.08);

border:
1px solid rgba(255,0,76,.20);

text-align:center;

font-size:.92rem;

line-height:1.7;

}

/* =========================================================
📱 MOBILE
========================================================= */

@media(max-width:980px){

.app{

grid-template-columns:1fr;

max-width:540px;

}

.hero{
display:none;
}

.auth{
padding:18px;
}

.form-card{
padding:28px 22px;
}

}

/* =========================================================
📱 SMALL PHONES
========================================================= */

@media(max-width:480px){

body{
padding:12px;
}

.form-card{
padding:24px 18px;
border-radius:26px;
}

.form-avatar{
width:78px;
height:78px;
}

.form-header h2{
font-size:1.45rem;
}

.input-box input{
height:54px;
font-size:.9rem;
}

.btn{
height:54px;
font-size:.88rem;
}

}

/* =========================================================
🍎 IOS
========================================================= */

@supports(padding:max(0px)){

body{

padding-left:
max(12px,env(safe-area-inset-left));

padding-right:
max(12px,env(safe-area-inset-right));

padding-top:
max(12px,env(safe-area-inset-top));

padding-bottom:
max(12px,env(safe-area-inset-bottom));

}

}

/* =========================================================
🔥 PERFORMANCE
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

<div class="app">

<!-- =========================================================
🎬 HERO
========================================================= -->

<section class="hero">

<div class="hero-top">

<div class="hero-logo">

<img
src="Logo/Logo Nuevo -512x512.png"
loading="lazy"
decoding="async">

<div class="hero-logo-text">
<h1>MovieTx</h1>
<p>Streaming Premium Experience</p>
</div>

</div>

<div class="hero-content">

<div class="hero-badge">
⚡ Plataforma ultrarrápida
</div>

<h2 class="hero-title">

Tu cine favorito
<span>sin límites</span>

</h2>

<p class="hero-text">

Accede a películas, series y trailers
con una experiencia fluida, moderna,
rápida y optimizada para Android,
iPhone, tablets y computadoras.

</p>

</div>

</div>

<div class="hero-stats">

<div class="hero-card">
<strong>4K+</strong>
<span>Contenido premium</span>
</div>

<div class="hero-card">
<strong>Ultra</strong>
<span>Velocidad optimizada</span>
</div>

<div class="hero-card">
<strong>24/7</strong>
<span>Disponibilidad total</span>
</div>

</div>

</section>

<!-- =========================================================
🔐 AUTH
========================================================= -->

<section class="auth">

<div class="forms">

<!-- =========================================================
🔑 LOGIN
========================================================= -->

<div
class="form-box active"
id="login-form">

<div class="form-card">

<div class="form-header">

<div class="form-avatar">

<img
id="logoPrincipal"
src="Logo/Logo Nuevo -512x512.png"
loading="lazy"
decoding="async">

</div>

<h2>Iniciar Sesión</h2>

<p>
Bienvenido nuevamente a MovieTx
</p>

</div>

<?php if($loginError): ?>

<div class="alert alert-error">
<?= $loginError ?>
</div>

<?php endif; ?>

<?php if($registerSuccess): ?>

<div class="alert alert-success">
<?= $registerSuccess ?>
</div>

<?php endif; ?>

<form
action="login_register.php"
method="post"
autocomplete="off">

<div class="input-group">

<label class="input-label">
Correo electrónico
</label>

<div class="input-box">

<input
type="email"
id="correo"
name="email"
placeholder="Ingresa tu correo"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Contraseña
</label>

<div class="input-box">

<input
type="password"
name="password"
placeholder="Ingresa tu contraseña"
required>

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<button
type="submit"
name="login"
class="btn btn-primary">

Ingresar

</button>

<button
type="button"
class="btn btn-secondary"
onclick="showForm('admin-form')">

Administrador

</button>

<div class="form-links">

¿No tienes cuenta?

<button
type="button"
class="link-btn"
onclick="showForm('register-form')">

Registrarse

</button>

</div>

</form>

<?php if(isset($_GET['expired'])): ?>

<div class="expired">

<strong>
⚠️ Cuenta expirada
</strong>

<br><br>

Para reactivar tu cuenta
debes comunicarte al:

<br><br>

<strong
style="cursor:pointer;user-select:all;-webkit-user-select:all;"
onclick="copyPhone()">

📞 3518175037 📋

</strong>

</div>

<?php endif; ?>

</div>

</div>

<!-- =========================================================
📝 REGISTER
========================================================= -->

<div
class="form-box"
id="register-form">

<div class="form-card">

<div class="form-header">

<div class="form-avatar">

<img
src="Logo/Logo Nuevo -512x512.png"
loading="lazy"
decoding="async">

</div>

<h2>Crear Cuenta</h2>

<p>
Registrate en MovieTx
</p>

</div>

<div class="plan-box">

<div class="plan-price">
💳 $2500 / mes
</div>

<div class="plan-text">

Mercado Pago • Naranja X • Transferencia

</div>

<div class="plan-note">

⚠️ Una vez registrado, el administrador
se comunicará con vos para activar
la cuenta luego del pago.

</div>

</div>

<?php if($registerError): ?>

<div class="alert alert-error">
<?= $registerError ?>
</div>

<?php endif; ?>

<form
action="registro.php"
method="POST"
autocomplete="off">

<div class="input-group">

<label class="input-label">
Nombre completo
</label>

<div class="input-box">

<input
type="text"
name="name"
placeholder="Nombre completo"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Correo electrónico
</label>

<div class="input-box">

<input
type="email"
name="email"
placeholder="Correo electrónico"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Número de teléfono
</label>

<div class="input-box">

<input
type="text"
name="telefono"
placeholder="Número de teléfono"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Contraseña
</label>

<div class="input-box">

<input
type="password"
name="password"
placeholder="Contraseña"
required>

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<button
type="submit"
class="btn btn-primary">

Crear Cuenta

</button>

<div class="form-links">

¿Ya tienes cuenta?

<button
type="button"
class="link-btn"
onclick="showForm('login-form')">

Iniciar sesión

</button>

</div>

</form>

</div>

</div>

<!-- =========================================================
👑 ADMIN
========================================================= -->

<div
class="form-box"
id="admin-form">

<div class="form-card">

<div class="form-header">

<div class="form-avatar">

<img
id="adminLogo"
src="Logo/Logo Nuevo -512x512.png"
loading="lazy"
decoding="async">

</div>

<h2>Administrador</h2>

<p>
Acceso exclusivo para administradores
</p>

</div>

<form
action="login_register.php"
method="post"
autocomplete="off">

<input
type="hidden"
name="login_type"
value="admin">

<div class="input-group">

<label class="input-label">
Correo electrónico
</label>

<div class="input-box">

<input
type="email"
id="adminCorreo"
name="email"
placeholder="Correo administrador"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Contraseña
</label>

<div class="input-box">

<input
type="password"
name="password"
placeholder="Contraseña"
required>

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<div
class="input-group"
id="adminKeyBox">

<label class="input-label">
Clave única
</label>

<div class="input-box">

<input
type="password"
id="adminKey"
name="admin_key"
placeholder="Clave principal">

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<button
type="submit"
name="admin_login"
class="btn btn-primary">

Ingresar al Panel

</button>

<?php if($showAdminRegister): ?>

<button
type="button"
class="btn btn-secondary"
onclick="showForm('admin-register-form')">

Registrar Admin Principal

</button>

<?php endif; ?>

<div class="form-links">

<button
type="button"
class="link-btn"
onclick="showForm('login-form')">

Volver atrás

</button>

</div>

</form>

</div>

</div>

<!-- =========================================================
👑 ADMIN REGISTER
========================================================= -->

<div
class="form-box"
id="admin-register-form">

<div class="form-card">

<div class="form-header">

<h2>
Administrador Principal
</h2>

<p>
Registro único de administrador
</p>

</div>

<?php if($adminSuccess): ?>

<div class="alert alert-success">
<?= $adminSuccess ?>
</div>

<?php endif; ?>

<form
action="login_register.php"
method="post">

<div class="input-group">

<label class="input-label">
Nombre completo
</label>

<div class="input-box">

<input
type="text"
name="name"
placeholder="Nombre completo"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Correo electrónico
</label>

<div class="input-box">

<input
type="email"
name="email"
placeholder="Correo electrónico"
required>

</div>

</div>

<div class="input-group">

<label class="input-label">
Contraseña del administrador
</label>

<div class="input-box">

<input
type="password"
name="password"
placeholder="Contraseña del admin"
required>

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<div class="input-group">

<label class="input-label">
Clave única
</label>

<div class="input-box">

<input
type="password"
name="admin_key"
placeholder="Clave principal"
required>

<span class="toggle-pass">

<svg viewBox="0 0 24 24">
<path d="M12 5C7 5 3 8 1 12c2 4 6 7 11 7s9-3 11-7c-2-4-6-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/>
</svg>

</span>

</div>

</div>

<button
type="submit"
name="register_admin"
class="btn btn-primary">

Registrar Administrador

</button>

<div class="form-links">

<button
type="button"
class="link-btn"
onclick="showForm('admin-form')">

Volver

</button>

</div>

</form>

</div>

</div>

</div>

</section>

</div>

<script>

/* =========================================================
⚡ FORM SWITCH
========================================================= */

let currentForm =
document.querySelector(".form-box.active");

function showForm(id){

const nextForm =
document.getElementById(id);

if(
!nextForm ||
nextForm === currentForm
)return;

currentForm.classList.remove("active");

requestAnimationFrame(()=>{

nextForm.classList.add("active");

currentForm = nextForm;

window.scrollTo({
top:0,
behavior:"smooth"
});

});

}

/* =========================================================
👁️ PASSWORD TOGGLE
========================================================= */

document
.querySelectorAll(".toggle-pass")
.forEach(btn=>{

btn.onclick = ()=>{

const input =
btn.parentElement.querySelector("input");

input.type =
input.type === "password"
? "text"
: "password";

};

});

/* =========================================================
📷 FOTO DINÁMICA
========================================================= */

const correoInput =
document.getElementById("correo");

const logoPrincipal =
document.getElementById("logoPrincipal");

const adminCorreo =
document.getElementById("adminCorreo");

const adminLogo =
document.getElementById("adminLogo");

const adminKeyBox =
document.getElementById("adminKeyBox");

const DEFAULT_LOGO =
"Logo/Logo Nuevo -512x512.png";

let debounce;

/* LOGIN NORMAL */

correoInput?.addEventListener(
"input",
function(){

clearTimeout(debounce);

const value =
this.value.trim();

if(value.length < 3){

logoPrincipal.src =
DEFAULT_LOGO;

return;

}

debounce = setTimeout(()=>{

fetch(
"buscar_foto.php?correo=" +
encodeURIComponent(value)
)

.then(r=>r.json())

.then(data=>{

if(
data.foto &&
data.foto !== "default.png"
){

logoPrincipal.src =
data.foto;

}else{

logoPrincipal.src =
DEFAULT_LOGO;

}

})

.catch(()=>{

logoPrincipal.src =
DEFAULT_LOGO;

});

},220);

}
);

/* LOGIN ADMIN */

adminCorreo?.addEventListener(
"input",
function(){

clearTimeout(debounce);

const value =
this.value.trim();

if(value.length < 3){

adminLogo.src =
DEFAULT_LOGO;

adminKeyBox.style.display =
"block";

return;

}

debounce = setTimeout(()=>{

fetch(
"buscar_foto.php?correo=" +
encodeURIComponent(value)
)

.then(r=>r.json())

.then(data=>{

if(
data.foto &&
data.foto !== "default.png"
){

adminLogo.src =
data.foto;

}else{

adminLogo.src =
DEFAULT_LOGO;

}

adminKeyBox.style.display =
data.admin_level === "normal"
? "none"
: "block";

})

.catch(()=>{

adminLogo.src =
DEFAULT_LOGO;

adminKeyBox.style.display =
"block";

});

},220);

}
);

/* =========================================================
📋 COPY PHONE
========================================================= */

function copyPhone(){

const numero = "3518175037";

navigator.clipboard.writeText(numero)
.then(() => {

alert("✅ Número copiado: " + numero);

})
.catch(() => {

const input = document.createElement("input");
input.value = numero;

document.body.appendChild(input);

input.select();
document.execCommand("copy");

document.body.removeChild(input);

alert("✅ Número copiado: " + numero);

});

}

/* =========================================================
🚫 BACK BLOCK
========================================================= */

history.pushState(
null,
"",
location.href
);

window.onpopstate = ()=>{

history.pushState(
null,
"",
location.href
);

location.replace("index.php");

};

window.addEventListener(
"pageshow",
event=>{

if(event.persisted){

location.replace(
"index.php"
);

}

}
);

</script>

</body>
</html>