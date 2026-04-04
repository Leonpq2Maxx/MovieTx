<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Precios | MovieTx</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:'Poppins',sans-serif;
}

body{
background:
radial-gradient(circle at top,#151515,#050505 70%);
color:#fff;
padding-top:70px;
}

/* MENU */

.menu-superior{
position:fixed;
top:0;
left:0;
right:0;
height:60px;
background:#0d0d0d;
display:flex;
align-items:center;
padding:0 20px;
z-index:9999;
box-shadow:0 6px 25px rgba(0,0,0,.7);
}

.menu-superior button{
background:none;
border:none;
cursor:pointer;
}

.menu-superior svg{
width:28px;
height:28px;
fill:white;
}

.titulo{
font-size:18px;
margin-left:12px;
font-weight:600;
}

/* CONTENEDOR */

.container{
max-width:1050px;
margin:auto;
padding:30px 20px;
text-align:center;
}

/* TITULO */

h1{
font-size:36px;
margin-bottom:25px;
background:linear-gradient(90deg,#00d9ff,#ff0077);
-webkit-background-clip:text;
color:transparent;
}

/* INFO */

.info{
background:#121212;
padding:25px;
border-radius:14px;
margin-bottom:35px;
line-height:1.7;
color:#cfcfcf;
box-shadow:0 10px 30px rgba(0,0,0,.6);
}

/* BENEFICIOS */

.beneficios{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:20px;
margin-bottom:45px;
}

.beneficio{
background:#121212;
padding:22px;
border-radius:14px;
transition:.35s;
border:1px solid rgba(255,255,255,0.05);
}

.beneficio:hover{
transform:translateY(-6px);
background:#171717;
box-shadow:0 10px 25px rgba(0,0,0,.8);
}

.beneficio h3{
margin-bottom:10px;
color:#00d9ff;
font-size:18px;
}

/* TARJETA PRECIO */

.precios{
display:flex;
justify-content:center;
margin-top:10px;
}

.card{
background:linear-gradient(145deg,#141414,#1c1c1c);
border-radius:18px;
padding:40px 35px;
max-width:350px;
width:100%;
box-shadow:0 15px 40px rgba(0,0,0,.8);
position:relative;
overflow:hidden;
transition:.35s;
}

.card:hover{
transform:scale(1.04);
}

/* EFECTO BRILLO */

.card::before{
content:"";
position:absolute;
width:200%;
height:200%;
background:linear-gradient(120deg,transparent,#00d9ff33,transparent);
transform:rotate(25deg);
top:-150%;
left:-150%;
animation:shine 5s linear infinite;
}

@keyframes shine{
0%{top:-150%;left:-150%;}
50%{top:100%;left:100%;}
100%{top:-150%;left:-150%;}
}

.card h2{
font-size:22px;
margin-bottom:12px;
}

.precio{
font-size:42px;
font-weight:700;
margin:10px 0;
color:#00d9ff;
text-shadow:0 0 15px rgba(0,217,255,.5);
}

.detalle{
color:#bdbdbd;
font-size:14px;
margin-bottom:25px;
}

/* BOTON */

.boton{
display:inline-block;
padding:12px 28px;
border-radius:40px;
background:linear-gradient(90deg,#ff0077,#00d9ff);
color:white;
text-decoration:none;
font-weight:600;
transition:.3s;
}

.boton:hover{
transform:scale(1.08);
box-shadow:0 5px 20px rgba(255,0,119,.5);
}

/* PAGOS */

.pagos{
margin-top:50px;
padding:25px;
border-radius:14px;
background:linear-gradient(90deg,#ff0077,#00d9ff);
font-weight:500;
box-shadow:0 8px 30px rgba(0,0,0,.7);
}

.pagos strong{
color:#000;
}

/* FOOTER */

.footer{
margin-top:40px;
color:#777;
font-size:14px;
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

<div class="menu-superior">
<button onclick="window.history.back()">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
<path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
</svg>
</button>
<div class="titulo">Precios</div>
</div>

<div class="container">

<h1>Acceso a MovieTx</h1>

<div class="info">
MovieTx cuenta con películas recientes (2025, 2024, 2023 y más).  
Se agregan nuevas películas constantemente de diferentes géneros:
Acción, Terror, Comedia, Romance y muchos más.
</div>

<div class="beneficios">

<div class="beneficio">
<h3>🎬 Estrenos frecuentes</h3>
<p>Nuevas películas agregadas constantemente.</p>
</div>

<div class="beneficio">
<h3>📺 Streaming optimizado</h3>
<p>Reproducción rápida para TV y dispositivos.</p>
</div>

<div class="beneficio">
<h3>🔓 Acceso completo</h3>
<p>Disfruta todo el catálogo disponible.</p>
</div>

</div>

<div class="precios">

<div class="card">

<h2>Acceso Mensual</h2>

<div class="precio">$2500 ARS</div>

<div class="detalle">
Acceso durante <b>1 mes + 5 días adicionales</b>
</div>

<a class="boton" href="#">
Comprar acceso
</a>

</div>

</div>

<div class="pagos">
Formas de pago:  
<strong>Ualá, Mercado Pago, Rapipago, Naranja X</strong>
<br><br>
Enviar <b>nombre del comprador + comprobante</b> para habilitar el acceso.
</div>

<div class="footer">
Series próximamente disponibles en MovieTx.
</div>

</div>

</body>
</html>
