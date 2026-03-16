<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>2025 Movie</title>
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
  
    <div class="movie locked" data-tipo="pelicula" data-titulo="el vinculo sueca" data-genero="drama guerra historia" data-anio="2026" data-html="../View Peliculas/Reproductor Universal.php?id=la_conexion_sueca" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2026</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/snlnvSB232OZwPCuO8zkWYJ6P7j.jpg">
      <span class="lock-icon">🔒</span>
      <p>El vínculo sueca</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="love me love me" data-genero="romance drama" data-anio="2026" data-html="../View Peliculas/Reproductor Universal.php?id=love_me_love_me" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2026</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg">
      <span class="lock-icon">🔒</span>
      <p>Love me, Love me</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="la empleada" data-genero="romaance drama misterio" data-anio="2026" data-html="../View Peliculas/Reproductor Universal.php?id=la_empleada" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2026</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg">
      <span class="lock-icon">🔒</span>
      <p>La empleada</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sidelined 2" data-genero="comedia romance" data-anio="2026" data-html="../View Peliculas/Reproductor Universal.php?id=sidelined_2" data-fecha="2026-02-19">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2026</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/sEIP1pTVXa8BJaYSuVeVG3wFN10.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sidelined 2: Interceptado</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mi año en oxford" data-genero="romance comedia drama" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=mi_año_en_oxford" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/iKT49ApsXGKYY3wdZ0THYhhgOBe.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mi año en Oxford</p>
    </div>
    
    <div class="movie locked" data-tipo="pelicula" data-titulo="echo valley" data-genero="drama suspenso" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=echo_valley" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1E4WCgTodyS7zo8pSp1gZlPO0th.jpg">
      <span class="lock-icon">🔒</span>
      <p>Echo valley</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="depredador tierras salvajes" data-genero="accion ciencia ficcion aventura" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=depredador_tierras_salvajes" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/r7TEWHLr1lsIsTkiEFwtM3hAWma.jpg">
      <span class="lock-icon">🔒</span>
      <p>Depredador: Tierras salvajes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="encerrado" data-genero="terror suspenso" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=encerrado_2025" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wlo2rGpjjHh3X8XImBdeUayKJ6g.jpg">
      <span class="lock-icon">🔒</span>
      <p>Encerrado</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="ojala estuvieras aqui" data-genero="romance drama" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=ojala_estuvieras_aqui" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/zVRDebamaWViYk9P7q8FgJ8CJO8.jpg">
      <span class="lock-icon">🔒</span>
      <p>Ojala estuvieras aqui</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="minecraft" data-genero="accion aventura comedia fantasia" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=minecraft" data-fecha="2026-02-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/rZYYmjgyF5UP1AVsvhzzDOFLCwG.jpg">
      <span class="lock-icon">🔒</span>
      <p>Minecraft: La pelicula</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="los pitufos" data-genero="animacion aventura fantasia" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=lospitufos_2025" data-fecha="2026-01-20">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/zBdQclxQnEDOhDOjkKgKPW6jEHh.jpg">
      <span class="lock-icon">🔒</span>
      <p>Los Pitulos</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="poseida" data-genero="terror misterio" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=poseida" data-fecha="2026-01-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg">
      <span class="lock-icon">🔒</span>
      <p>Poseída</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sonic 3" data-genero="animacion comedia ciencia ficcion familiar" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=sonic_3" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/vlAXtzNWQ3VSZtIinhHqcPXS1Oc.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sonic 3: La pelicula</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="zootopia 2" data-genero="animacion aventura comedia familia infantil niños disney" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=zootopia_2" data-fecha="2025-12-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <span class="year-tegg">CAM</span>
      <img src="https://image.tmdb.org/t/p/w300/3Wg1LBCiTEXTxRrkNKOqJyyIFyF.jpg">
      <span class="lock-icon">🔒</span>
      <p>Zootopia 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="until dawn noche de terror" data-genero="terror misterio" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=until_dawn_noche_de_terror" data-fecha="2025-12-29">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg">
      <span class="lock-icon">🔒</span>
      <p>Until Dawn: Noche de terror</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final 6 lazos de sangre" data-genero="terror" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final_6" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/f0156SDAw1GfrdZnSbSwkOst9aO.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final 6: Lazos de sangre</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="capitan america 4 un nuevo mundo" data-genero="heroes marvel ciencia ficcion accion" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=capitan_america4" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg">
      <span class="lock-icon">🔒</span>
      <p>Capitán América 4: Un nuevo mundo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="almas marcadas rule + shaw" data-genero="romance drama" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=almas_marcadas" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg">
      <span class="lock-icon">🔒</span>
      <p>Almas marcadas: Rule + Shaw</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="culpa nuestra" data-genero="romance drama" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=culpa_nuestra_3" data-fecha="2025-11-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/6kmi6vmp6iOn4KzI7WfnVtAeJhU.jpg">
      <span class="lock-icon">🔒</span>
      <p>Culpa nuestra</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="como entrenar a tu dragon" data-genero="aventura ciencia ficcion familia" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xLsMLfE0t0eyc8km2hAeSayUBa3.jpg">
      <span class="lock-icon">🔒</span>
      <p>Como entrenar a tu dragón</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="blancanieves y los siete enanitos" data-tipo="pelicula" data-genero="animacion fantasia familia disney princesas" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=blancanieves" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg">
      <span class="lock-icon">🔒</span>
      <p>Blancanieves</p>
    </div>

    <div class="movie locked" data-titulo="el conjuro 4 el ultimo rito" data-genero="Terror" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=el_conjuro_4" data-fecha="2025-12-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg">
      <span class="lock-icon">🔒</span>
      <p>El conjuro 4: El ultimo rito</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="culpa mia londres 2" data-genero="romance drama" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=culpa_mia_2" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/q0HxfkF9eoa6wSVnzwMhuDSK7ba.jpg">
      <span class="lock-icon">🔒</span>
      <p>Culpa mia: Londres</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="corazon delator" data-genero="drama romance" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=corazon_delator" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg">
      <span class="lock-icon">🔒</span>
      <p>Corazón delator</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="contraataque" data-genero="accion suspenso" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=contraataque" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/kxnFdLJhi37ZVFDCL1ka0yeQVU5.jpg">
      <span class="lock-icon">🔒</span>
      <p>Contraataque</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bala perdida 3" data-genero="accion suspenso crimen" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=bala_perdida_3" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bala perdida 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="1978 argentina" data-genero="terror argentina" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=argen_1978_a" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/iyKixwGhGRas1ppAih8E7SG5QDZ.jpg">
      <span class="lock-icon">🔒</span>
      <p>1978</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="amateur" data-genero="accion suspenso" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=amateur" data-fecha="2025-30-10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xzM5pMCIyp8jkGtsFBGcPlRhVBc.jpg">
      <span class="lock-icon">🔒</span>
      <p>Amateur</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cantar desnuda" data-adulto="true" data-genero="musical adulto porno documental" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=cantar_desnuda" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://cinepelayo.com/wp-content/uploads/2025/01/cartel-cantar-desnuda.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cantar desnuda</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="banger" data-genero="musical comedia pelicula" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=banger" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/x2pegSby27ebOwW361GJb1aKcxa.jpg">
      <span class="lock-icon">🔒</span>
      <p>Banger</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el astronauta" data-genero="ciencia ficcion aventura drama" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=el_astronauta" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/kyYNMXbXzuAw1LpnvzheqTKNaoL.jpg">
      <span class="lock-icon">🔒</span>
      <p>El astronauta</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el mono" data-genero="terror" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=el_mono" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/z15wy8YqFG8aCAkDQJKR63nxSmd.jpg">
      <span class="lock-icon">🔒</span>
      <p>El mono</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el planeta de los simios 4 un nuevo reino" data-genero="accion ciencia ficcion suspenso" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=el_planeta_de_los_simios_4" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/p2wJF2CtbHhtQtnAxoHeptoSv1E.jpg">
      <span class="lock-icon">🔒</span>
      <p>El planeta de los simios 4: Un nuevo reino</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el arca de noe" data-genero="animacion musical familia niño infantil" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=el_arca_de_noe" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fRaBjht3S1HU6lJrz2SoFwwOZQM.jpg">
      <span class="lock-icon">🔒</span>
      <p>El Arca De Noé</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="pideme lo que quieras" data-genero="romance drama" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=pideme_lo_que_quieras" data-fecha="2026-02-22">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5rtaLwyKAjbceww4J1ro8aA8BNB.jpg">
      <span class="lock-icon">🔒</span>
      <p>Pideme lo que quieras</p>
    </div>
    
    <div class="movie locked" data-tipo="pelicula" data-titulo="kung fu panda 4" data-genero="animacion aventura fantasia" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=kung_fu_panda_4" data-fecha="2026-01-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg">
      <span class="lock-icon">🔒</span>
      <p>Kung fu panda 4</p>
    </div>
    
    <div class="movie locked" data-tipo="pelicula" data-titulo="intensamente 2" data-genero="animacion drama aventura fantasia disney" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=intensamente_2" data-fecha="2026-01-20">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/4HEJdpcmTGm3BWWic31G4aCnuC6.jpg">
      <span class="lock-icon">🔒</span>
      <p>Intensamente 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="alarum codigo letal" data-genero="accion suspenso crimen" data-anio="2025" data-html="../View Peliculas/Reproductor Universal.php?id=alarum_codigo_letal" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2025</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg">
      <span class="lock-icon">🔒</span>
      <p>Alarum: Código letal</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="moana 2" data-genero="animacion aventura disney" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=moana_2" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg">
      <span class="lock-icon">🔒</span>
      <p>Moana 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bambi una vida en el bosque" data-genero="aventura documental familia disney" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=bambi_una_vida_en_el_bosque_2024" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fvtIXQH4JcifptPe0J9GfLDIOAQ.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bambi: Una vida en el bosque</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="deadpool 3 y wolverine" data-genero="accion heroes marvel ciencia ficcion" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=deadpool_y_wolverine" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg">
      <span class="lock-icon">🔒</span>
      <p>Deadpool y Wolverine</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="baki hanma vs kengan ashura" data-genero="animacion anime fantasia" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/etbHJxil0wHvYOCmibzFLsMcl2C.jpg">
      <span class="lock-icon">🔒</span>
      <p>Baki Hanma VS Kengan Ashura</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="azrael" data-genero="terror suspenso" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=azrael" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/oRZZDhHrxIqvXAuDgQLalm7vlrN.jpg">
      <span class="lock-icon">🔒</span>
      <p>Azrael</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="chicas malas" data-genero="drama comedia" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=chicas_malas_2024" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jCerTXgMp5iiSoJofwkKskp2w45.jpg">
      <span class="lock-icon">🔒</span>
      <p>Chicas malas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="babygirl deseo prohibido" data-genero="romance" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=babygirls" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fCCZlnzf6yEGGO9UEdVADRVvfhM.jpg">
      <span class="lock-icon">🔒</span>
      <p>Babygirl: Deseo prohibido</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="beekeeper sentencia de muerte" data-genero="accion crimen suspenso" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=sentencia_de_muerte" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/8hF8krJOG9SGMCwRNfzjsFVRcHE.jpg">
      <span class="lock-icon">🔒</span>
      <p>Beekeeper: Sentencia de muerte</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="anora" data-genero="drama romance" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=anora" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg">
      <span class="lock-icon">🔒</span>
      <p>Anora</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="damsel" data-genero="drama accion fantasia" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=damsel" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/gh7oa9IKlu5yMveemyJkzLfopuB.jpg">
      <span class="lock-icon">🔒</span>
      <p>Damsel</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="atrapados en lo profundo del mar atrapados en el abismo" data-genero="terror suspenso tiburones" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=atrapados_en_lo_profundo" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fSY6BYUZMObTIzPfRBlhuAb5lsd.jpg">
      <span class="lock-icon">🔒</span>
      <p>Atrapados en lo Profundo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="asesino serial" data-genero="crimen suspenso terror" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=asesino_serial" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg">
      <span class="lock-icon">🔒</span>
      <p>Asesino serial</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="diario de mi vagina" data-genero="drama" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=diario_de_mi_vagina" data-fecha="2025-11-30">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7PzGmlaai6mRUslfrdBhfXjfA1J.jpg">
      <span class="lock-icon">🔒</span>
      <p>Diario de mi vagina</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="60 minutos" data-genero="accion mma peleas " data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=secenta_minutos" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/cND79ZWPFINDtkA8uwmQo1gnPPE.jpg">
      <span class="lock-icon">🔒</span>
      <p>60 Minutos acechados por el mal</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="Detonantes" data-genero="accion" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=detonantes" data-fecha="2025-11-30">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg">
      <span class="lock-icon">🔒</span>
      <p>Detonantes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="culpa tuya 1" data-genero="drama romance" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=culpa_tuya" data-fecha="2025-10-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1jvCVdlgInyItAUEvvvCakm1Yxz.jpg">
      <span class="lock-icon">🔒</span>
      <p>Culpa tuya</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="codigo 8 parte 2" data-genero="accion crimen ciencia ficcion" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=codigo_8_parte_2" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/dg6WrJUIQLU4pssA4ZucGfdOj8.jpg">
      <span class="lock-icon">🔒</span>
      <p>Codigo 8: Parte 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="Winnie the pooh 2 el bosque sangriento" data-genero="terror suspenso" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=winnie_the_pooh_2" data-fecha="2026-02-29">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/17UmQl8TuDmHWGlcKeFIjnR8bJF.jpg">
      <span class="lock-icon">🔒</span>
      <p>Winnie the Pooh 2: El bosque sangriento</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="desaparecidos en la noche" data-genero="drama misterio suspenso" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=desaparecidos_en_la_noche" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg">
      <span class="lock-icon">🔒</span>
      <p>Desaparecidos en la noche</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="abigail" data-genero="terror" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=abigail" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/kmB9grIf2fvpwwsDmNMN0XFz1tT.jpg">
      <span class="lock-icon">🔒</span>
      <p>Abigail</p>
    </div>

    <div class="movie locked"  data-tipo="pelicula"data-titulo="Al rescate de fondo de Bikini bob esponja" data-genero="animacion aventura comedia familia" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=al_rescate_de_fondo_de_bikini_la_película_de_arenita_mejillas" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7WfWEy1EIJj4nLR6PdE6A09TcOv.jpg">
      <span class="lock-icon">🔒</span>
      <p>Al rescate de fondo de Bikini: La película de Arenita Mejillas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="desafiantes rivales" data-genero="drama romance" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=desafiante_rivales" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg">
      <span class="lock-icon">🔒</span>
      <p>Desafiantes Rivales</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sonrie 2" data-genero="terror" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=sonrie_2" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sonrie 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sugar baby" data-genero="romance drama" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=sugar_baby" data-fecha="2026-02-13">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sugar baby</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bad boys 4 hasta la muerte" data-genero="comedia accion crimen" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=bad_boys_4" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bad boys 4: Hasta la muerte</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="terrifier 3" data-genero="terror" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=terrifier_3" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg">
      <span class="lock-icon">🔒</span>
      <p>Terrifier 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="millers girl" data-genero="romance drama" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=millers_girl" data-fecha="2026-02-13">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/a5YCKz2HV3xEtaOhr4I7FGe05qQ.jpg">
      <span class="lock-icon">🔒</span>
      <p>Miller's Girl</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="venom 3 el ultimo baile" data-genero="marvel accion ciencia ficcion" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=venom_3" data-fecha="2026-01-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg">
      <span class="lock-icon">🔒</span>
      <p>Venom 3: El último baile</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="baghead contacto con la muerte" data-genero="terror suspenso misterio" data-anio="2024" data-html="../View Peliculas/Reproductor Universal.php?id=baghead_contacto_con_la_muerte" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2024</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5ssaCHmqvTZDVZtcNhNZTzfb7Nj.jpg">
      <span class="lock-icon">🔒</span>
      <p>Baghead: Contacto con la muerte</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="spider man cruzando el multiverso spider man 2" data-genero="accion aventura animacion marvel" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=spiderman_man_cruzando_el_multi_verso_2" data-fecha="2026-01-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg">
      <span class="lock-icon">🔒</span>
      <p>Spider-Man: Cruzando el Multi-Verso</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="asesino serial" data-genero="suspenso crimen" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=el_asesino" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wXbAPrZTqJzlqmmRaUh95DJ5Lv1.jpg">
      <span class="lock-icon">🔒</span>
      <p>El Asesino</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el gato con botas 2 el ultimo deseo" data-genero="animacion comedia aventura fantasia familia infantil" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=el_gato_con_botas_2" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg">
      <span class="lock-icon">🔒</span>
      <p>El gato con botas 2: El último deseo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="after 4 aquí acaba todo" data-genero="romance drama" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=after_4" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jO3VGQi5sHIj2BGS963g1F74yCq.jpg">
      <span class="lock-icon">🔒</span>
      <p>After 4: Aquí acaba todo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="duro de entrenar" data-genero="accion suspenso crimen" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=duro_de_entrenar" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/lXkS6kSA0W3c0zVr3QrCBseaNgc.jpg">
      <span class="lock-icon">🔒</span>
      <p>Duro de entrenar</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el Bufon" data-genero="terror suspenso misterio" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=el_bufon" data-fecha="2025-12-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/6a6PmabZ32a0xIn2TJx4MGKN6Q6.jpg">
      <span class="lock-icon">🔒</span>
      <p>El bufón</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="como matar a mama" data-genero="comedia drama" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=como_matar_a_mama" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/zQch27gPbimK96vtbrEq4jFHg2D.jpg">
      <span class="lock-icon">🔒</span>
      <p>¿Cómo matar a mamá?</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="winnie the pooh miel y sangre" data-genero="terror suspenso" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=winnie_the_pooh" data-fecha="2026-02-29">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/cUXqVDrHaOGEJD1clvVd7ucAHdt.jpg">
      <span class="lock-icon">🔒</span>
      <p>Winnie the Pooh: Miel y sangre</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="blue Beetle" data-genero="accion heroe ciencia ficcion" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=blue_beetle" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/z5mkvXYNRauSzHdZgxAj6MzrLTY.jpg">
      <span class="lock-icon">🔒</span>
      <p>Blue Beetle</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="anna nicole smith tu no me conoces" data-genero="documentacion" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=anna_nicole_smith" data-fecha="2025-30-10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mybL2Hd3PvsY7Qyjf7W6BKsoECu.jpg">
      <span class="lock-icon">🔒</span>
      <p>Anna Nicole Smith: Tú no me conoces</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cementerio de animales 2" data-genero="terror misterio sobrentural" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=cementerio_de_animales_2" data-fecha="2025-11-17">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/h6OOcYnuYVoaQQm3zGIYJ7XfTuo.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cementerio de animales 2: Los origenes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="crater un viaje inolvidable" data-genero="accion disney aventura fantasia ciencia ficcion" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=crater_un_viaje_inolvidable" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ppEvMrq2nvV9DfBHuCRilf2MBnm.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cráter: Un viaje inolvidable</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="barbie" data-genero="comedia aventura musical" data-anio="2023" data-html="../View Peliculas/Reproductor Universal.php?id=barbie" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2023</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg">
      <span class="lock-icon">🔒</span>
      <p>Barbie</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="pinocho" data-genero="animacion fantasia disney aventura" data-anio="1940" data-html="../View Peliculas/Reproductor Universal.php?id=pinocho_2022" data-fecha="2026-02-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1940</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/h32gl4a3QxQWNiNaR4Fc1uvLBkV.jpg">
      <span class="lock-icon">🔒</span>
      <p>Pinocho</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="terrifier 2" data-genero="terror" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=terrifier_2" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/qEAlwXbYk6IHA4ztoS2XFFaa7Xo.jpg">
      <span class="lock-icon">🔒</span>
      <p>Terrifier 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z dragon ball super super hero" data-genero="animacion anime accion ciencia ficcion" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_super_super_hero" data-fecha="2026-02-27">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wFYXVMKWLAoazjWTBNQ4IiQSKJg.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Super: Super hero</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="after 3 amor infinito" data-genero="romance drama" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=after_3" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/vcI9BD5kMmVI45Pzj5B1ZaGpFIR.jpg">
      <span class="lock-icon">🔒</span>
      <p>After 3: Amor infinito</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="365 dias 3 mas" data-genero="romance drama" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=dias_365_3" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mwcII5bXMeMTKyCejPuBPBTjmxu.jpg">
      <span class="lock-icon">🔒</span>
      <p>365 Dias 3: Mas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="lightyear" data-genero="animacion aventura familia disney toy story" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=lightyear" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/65WFr1ZMAbEniIh4jEhbRG9OHHN.jpg">
      <span class="lock-icon">🔒</span>
      <p>Lightyear</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sonrie" data-genero="terror" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=sonrie" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/hQTl9lp8rKY7qKQSudsdd8Duo8K.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sonrie</p>
    </div>
    
    <div class="movie locked" data-tipo="pelicula" data-titulo="365 dias 2 aquel dia " data-genero="romance drama" data-anio="2022" data-html="../View Peliculas/Reproductor Universal.php?id=dias_365_2" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2022</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/k3J2GdYxhR6U2RfsHZOsmHVKW7m.jpg">
      <span class="lock-icon">🔒</span>
      <p>365 Dias 2: Aquel dia</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el conjuro 3 el diablo me obligo hacerlo" data-genero="terro suspenso misterio" data-anio="2021" data-html="../View Peliculas/Reproductor Universal.php?id=el_conjuro_3" data-fecha="2025-12-17">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2021</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/79QjdRiT9zTLkrOq9FltoIxClma.jpg">
      <span class="lock-icon">🔒</span>
      <p>El conjuro 3: El diablo me obligo hacerlo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cato" data-genero="musical drama" data-anio="2021" data-html="../View Peliculas/Reproductor Universal.php?id=cato" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2021</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg">
      <span class="lock-icon">🔒</span>
      <p>CATO</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sing 2 cantar" data-genero="animacion musical fantasia familia niños" data-anio="2021" data-html="../View Peliculas/Reproductor Universal.php?id=sing_cantar_2" data-fecha="2026-01-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2021</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/aWeKITRFbbwY8txG5uCj4rMCfSP.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sing 2: Cantar</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="scooby ¡scooby!" data-genero="animacion aventura" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=scooby_2020" data-fecha="2025-10-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/tOhuq4RYr2Rt9TM7X4dkr7A9HSd.jpg">
      <span class="lock-icon">🔒</span>
      <p>¡Scooby!</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bob esponja 3 un heroe al rescate" data-genero="animacion aventura fantasia" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=bob_esponja_3" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fi2pg2mtAZwhq3qVuAs6PztjnHT.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bob Esponja 3: Un héroe al rescate</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="after 2 en mil pedazos" data-genero="romance drama" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=after_2" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/8nPw22C41EUWXREWmY9iIivMXxm.jpg">
      <span class="lock-icon">🔒</span>
      <p>After 2: En mil pedazos</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="365 dias 1" data-genero="romance drama" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=dias_365" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg">
      <span class="lock-icon">🔒</span>
      <p>365 Dias</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="belleza negra" data-genero="drama caballo disney" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=belleza_negra" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/d3wE2OAmWsuuE4IOp6i8iSeRYy4.jpg">
      <span class="lock-icon">🔒</span>
      <p>Belleza negra</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bad boys 3 para siempre" data-genero="comedia accion crimen" data-anio="2020" data-html="../View Peliculas/Reproductor Universal.php?id=bad_boys_3" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2020</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5XR7Pbo8qdwdpOIsFtWJOEiOJD6.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bad boys 3: Para siempre</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="aladdin" data-genero="aventura disney comedia fantasia" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=aladdin_2019" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fv9c5fsdxqUzkullgMB4cZja29y.jpg">
      <span class="lock-icon">🔒</span>
      <p>Aladdin</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="como entrenar a tu dragon 3" data-genero="animacion aventura fantasia familia" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_3" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cómo entrenar a tu dragón 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cementerio de animales 1" data-genero="terror misterio sobrentural" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=cementerio_de_animales" data-fecha="2025-11-17">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/vnw6g9c7qzNdzvpQhwWGRzBxwM0.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cementerio de animales</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="codigo 8 parte 1" data-genero="accion crimen ciencia ficcion" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=codigo_8" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ubXn3H2PWkoqH9TIBrWRJSKzuaD.jpg">
      <span class="lock-icon">🔒</span>
      <p>Codigo 8: Parte 1</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="toy story 4" data-genero="animacion disney familia comedia aventura" data-anio="1888" data-html="../View Peliculas/Reproductor Universal.php?id=toy_story_4" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1888</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg">
      <span class="lock-icon">🔒</span>
      <p>Toy story 4</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="after 1 aqui empieza todo" data-genero="romance drama" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=after_2019" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg">
      <span class="lock-icon">🔒</span>
      <p>After: Aqui empieza todo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="anabelle annabelle 3 vuelve a casa" data-genero="terror" data-anio="2019" data-html="../View Peliculas/Reproductor Universal.php?id=annabelle_3" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2019</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/3ZZB2UHGK2iqj4XYgmivkeCgGJn.jpg">
      <span class="lock-icon">🔒</span>
      <p>Annabelle 3: Vuelve a casa</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mara" data-genero="terror" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=mara" data-fecha="2026-02-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mara</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="spider man un nuevo universo" data-genero="accion aventura animacion marvel ciencia ficcion" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=spider_man_un_nuevo_universo" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xRMZikjAHNFebD1FLRqgDZeGV4a.jpg">
      <span class="lock-icon">🔒</span>
      <p>Spider-Man: Un nuevo universo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="los vengadores infinity war" data-genero="accion marvel ciencia ficcion" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=los_vengadores_infinity_war" data-fecha="2026-01-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/z58HrY2Hd9PlSpBTsZuoavfDavd.jpg">
      <span class="lock-icon">🔒</span>
      <p>Los Vengadores: Infinity War</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="terrifier el inicio" data-genero="terror" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=terrifier" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/nfRlQCl590F30L37aihuqBGBvaO.jpg">
      <span class="lock-icon">🔒</span>
      <p>Terrifier: El inicio</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball dragon ball z super broly" data-genero="animacion anime ciencia ficcion accion" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_super_broly" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/6JilEC1SON8tWIRHcdJzf4uVBpX.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Super: Broly</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="yo tonya" data-genero="drama" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=yo_tonya" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/aVWX0t95Igd8kKC3ejmtHCy1vX6.jpg">
      <span class="lock-icon">🔒</span>
      <p>Yo, Tonya</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="deadpool 2" data-genero="accion marvel comedia" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=deadpool_2" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jA4DpT3ywxfchnTfMBiouBhq9nU.jpg">
      <span class="lock-icon">🔒</span>
      <p>Deadpool 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="a ganar" data-genero="drama" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=a_ganar" data-fecha="2025-10-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/6GVYL9K2IBFrfIqwwFqMPu5DdC5.jpg">
      <span class="lock-icon">🔒</span>
      <p>¡A Ganar!</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="moana" data-genero="animacion aventura disney" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=moana" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/a4Jj3Tk2AZvmUYWx0H92HGfktKo.jpg">
      <span class="lock-icon">🔒</span>
      <p>Moana</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cincuenta sombras de grey 3 liberadas" data-genero="romance drama" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=cincuenta_sombra_liberadas_3" data-fecha="2025-11-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/sM8hwgWZlmZf0h4aOkNopb3HBIo.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cincuentas sombras 3: Liberadas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="steven universe la pelicula" data-genero="animacion ciencia ficcion aventura musical fantasia" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=steven_universe_la_pelicula" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/bewhxwbmWTMe16dEQa8ICGe9Y1Y.jpg">
      <span class="lock-icon">🔒</span>
      <p>Steven Universe: La pelicula</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="venom " data-genero="marvel accion ciencia ficcion" data-anio="2018" data-html="../View Peliculas/Reproductor Universal.php?id=venom" data-fecha="2026-02-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2018</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg">
      <span class="lock-icon">🔒</span>
      <p>Venom</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cars 3" data-genero="animacion disney familia aventura" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=cars_3" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cars 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="anabelle annabelle 2 la creacion" data-genero="terror" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=annabelle_2" data-fecha="2025-11-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/x0pekWNy7GS37bm30zuxWNLPXj8.jpg">
      <span class="lock-icon">🔒</span>
      <p>Annabelle 2: La creación</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el planeta de los simios 3 la guerra" data-genero="accion ciencia ficcion suspenso" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=el_planeta_de_los_simios_3" data-fecha="2025-12-17">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/4s51V3REPzdABoEDLC4TPDPkY3b.jpg">
      <span class="lock-icon">🔒</span>
      <p>El planeta de los simios 3: La guerra</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el bebe jefaso un jefe bebe" data-genero="animacion disney familia aventura" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=un_jefe_en_pañales" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/dPiXM1aFbJ9XJGPyf5ZULmEjzkR.jpg">
      <span class="lock-icon">🔒</span>
      <p>El bebé jefazo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="thor 3 ragnarok" data-genero="accion marvel comedia ciencia ficcion" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=thor_3" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/fx68UQgQvAOJZoRtMVigRkOozcQ.jpg">
      <span class="lock-icon">🔒</span>
      <p>Thor 3: Ragnarok</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="coco" data-genero="animacion disney familia aventura musical" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=coco" data-fecha="2025-11-34">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/yAvisTUocxmXQZQJZ521dL9a36p.jpg">
      <span class="lock-icon">🔒</span>
      <p>Coco</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mi villano favorito 3" data-genero="animacion aventura comedia familia accion" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito_3" data-fecha="2026-02-14">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1xQ6K6623qdjVkOwEjNneMSxdiB.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mi villano favorito 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cincuenta sombras de grey 2 mas oscuras" data-genero="romance drama" data-anio="2017" data-html="../View Peliculas/Reproductor Universal.php?id=cincuenta_sombras_más_oscuras_2" data-fecha="2025-11-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2017</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jvBAQOg2ObZKYXZGxYSz3Fkr7Qt.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cincuenta sombras 2: Más oscurass</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="capitan america 3 civil war" data-genero="heroes marvel accion ciencia ficcion" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=capitan_america3" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xHIzL54EuCFXVMaSudLLuHjuZ5r.jpg">
      <span class="lock-icon">🔒</span>
      <p>Capitán América 3: Civil War</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el conjuro 2 el caso enfield" data-genero="terror" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=el_conjuro_2" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/eYWH6pGsX102DUIjWpeybkDZfqA.jpg">
      <span class="lock-icon">🔒</span>
      <p>El conjuro 2: El caso enfield</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mi abuelo es un peligro" data-genero="comedia" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=mi_abuelo_es_un_peligro" data-fecha="2026-02-13">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7r9pn1g3lY95DjiwzxpmNqlJzeO.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mi abuelo es un peligro</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="doctor strange el hechicero supremo" data-genero="accion marvel" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=doctor_strange" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/sOsvKTJS0XwtfLsNMO3C0CVWJ4u.jpg">
      <span class="lock-icon">🔒</span>
      <p>Doctor Strange: El hechicero supremo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el bosque de los suicidios" data-genero="terror misterio suspenso" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=el_bosque_de_los_suicidios" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xrk5IwznK8x5kR2BlBYdu2H5GcI.jpg">
      <span class="lock-icon">🔒</span>
      <p>El bosque de los suicidios</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="sing cantar" data-genero="animacion musical fantasia familia niños" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=sing_cantar" data-fecha="2026-01-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg">
      <span class="lock-icon">🔒</span>
      <p>Sing: Cantar</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="deadpool" data-genero="accion marvel comedia" data-anio="2016" data-html="../View Peliculas/Reproductor Universal.php?id=deadpool" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2016</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7BYksRLQ9HtZbUtanhAIdeQO9eD.jpg">
      <span class="lock-icon">🔒</span>
      <p>Deadpool</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z la resurreccion de freezer" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_resurreccion_de_freezer" data-fecha="2025-11-06">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/hasMQTJXgv20EyNUDcNKMhQW6gq.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: La resurreccion de Freezer</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="boruto uzumaki naruto shippuden" data-tipo="pelicula" data-genero="animacion anime accion" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=boruto_2015" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/t9F4Yzi8rZO8Rn55ceyQPAofrI9.jpg">
      <span class="lock-icon">🔒</span>
      <p>Boruto: La Película</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="corazones de acero" data-genero="accion guerra belica" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=corazones_de_acero" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/kbtH5G8L8REzy72LkLmKYoBVaGv.jpg">
      <span class="lock-icon">🔒</span>
      <p>Corazones de acero</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="alvin y las ardillas fiesta sobre ruedas" data-genero="animacion comedia aventura musical" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_4" data-fecha="2025-30-10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/isz4uh337srL6PIYiKXTS5Htssq.jpg">
      <span class="lock-icon">🔒</span>
      <p>Alvin y las ardillas: Fiesta sobre ruedas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bob Esponja 2 un heroe fuera del agua" data-genero="animacion aventura comedia familia" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=bob_esponja_2" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/j4Sqs3SKNaJ4chdKXS1qqUlaWyW.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bob Esponja 2: Un héroe fuera del agua</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cincuenta sombras de grey" data-genero="romance drama" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=cincuentas_sombras_de_grey_1" data-fecha="2025-11-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mNZcZOIlTwDKd30xLnRR4p0ZELg.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cincuenta sombras de Grey</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="intensamente" data-genero="animacion drama aventura fantasia disney" data-anio="2015" data-html="../View Peliculas/Reproductor Universal.php?id=intensamente" data-fecha="2026-01-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2015</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg">
      <span class="lock-icon">🔒</span>
      <p>Intensamente</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="capitan America 2 el soldado de invierno" data-genero="accion heroes marvel ciencia ficcion" data-anio="2014" data-html="../View Peliculas/Reproductor Universal.php?id=capitan_america2" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2014</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wP7JcCzpWlX5XeROpf4ox9ZVFT6.jpg">
      <span class="lock-icon">🔒</span>
      <p>Capitán América 2: El soldado de invierno</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el planeta de los simios 2 confrontacion" data-genero="accion ciencia ficcion suspenso" data-anio="2014" data-html="../View Peliculas/Reproductor Universal.php?id=el_planeta_de_los_simios_2" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2014</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/yJXtXz8MFMeIfdoUHWjzTEuOhmK.jpg">
      <span class="lock-icon">🔒</span>
      <p>El planeta de los simios 2: Confrontacion</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="donde esta el fantasma 2" data-genero="comedia terror" data-anio="2014" data-html="../View Peliculas/Reproductor Universal.php?id=donde_esta_el_fantasma_2" data-fecha="2025-11-30">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2014</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/vRbDuqlmGPM9wGZ3VwbrjQu16Oa.jpg">
      <span class="lock-icon">🔒</span>
      <p>¿Donde esta el fantasma? 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="como entrenar a tu dragon 2" data-genero="animacion aventura fantasia familia" data-anio="2014" data-html="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_2" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2014</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ettHoubPw8byYfpV1vomGnyfBnp.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cómo entrenar a tu dragón 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="annabelle anabel" data-genero="terror" data-anio="2014" data-html="../View Peliculas/Reproductor Universal.php?id=annabelle_2014" data-fecha="2025-30-10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2014</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jNFqmsulwUrhYQW3MvqzfMc7SdS.jpg">
      <span class="lock-icon">🔒</span>
      <p>Annabelle</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="thor 2 el mundo oscuro" data-genero="accion marvel comedia ciencia ficcion" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=thor_2" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/iY2E6b5huleYrM0NYKrb7a7lSGZ.jpg">
      <span class="lock-icon">🔒</span>
      <p>Thor 2: El mundo oscuro</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el conjuro expediente warren" data-genero="terror suspenso" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=el_conjuro" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/10ir0eISr3p1MF1mjZwGTx7u4vv.jpg">
      <span class="lock-icon">🔒</span>
      <p>El Conjuro: Expediente Warren</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dulce venganza 2" data-genero="crimen terror violacion suspenso" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=dulce_venganza_2" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/g1WEqWtielGmcWj0hleLhDriB7w.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dulce venganza 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="turbo" data-genero="animacion aventura" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=turbo" data-fecha="2026-01-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ysNUm2zWPkJQKa3Op0N4EmqrZ0h.jpg">
      <span class="lock-icon">🔒</span>
      <p>Turbo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="donde esta el fantasma" data-genero="comedia terror" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=donde_esta_el_fantasma" data-fecha="2025-11-30">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/pAVGfrADDvKMgoZnJLSCiLBCCiG.jpg">
      <span class="lock-icon">🔒</span>
      <p>¿Donde esta el fantasma?</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z la batalla de los dioses" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_batalla_de_los_dioses" data-fecha="2025-11-06">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: La batalla de los dioses</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mi villano favorito 2" data-genero="animacion aventura comedia familia accion" data-anio="2013" data-html="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito_2" data-fecha="2026-02-14">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2013</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ikz6zymN62kqSFioVWAqn8mPufM.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mi villano favorito 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el origen de los guardianes" data-genero="animacion aventura fantasia" data-anio="2012" data-html="../View Peliculas/Reproductor Universal.php?id=el_origen_de_los_guardianes" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2012</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/kDVXsTZhssIJeZIMBC33MqmgkrQ.jpg">
      <span class="lock-icon">🔒</span>
      <p>El origen de los guardianes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el gato con botas" data-genero="animacion comedia aventura fantasia familia infantil" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=el_gato_con_botas" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1VmrC82zY4U33l9UHlZTWDB1asN.jpg">
      <span class="lock-icon">🔒</span>
      <p>El gato con botas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cars 2" data-genero="animacion disney familia aventura" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=cars_2" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/okIz1HyxeVOMzYwwHUjH2pHi74I.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cars 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="el planeta de los simios evolucion" data-genero="accion ciencia ficcion suspenso" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=el_planeta_de_los_simios" data-fecha="2026-03-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/uQsVXnHCKOzhWZUqNX0nAvMGhx7.jpg">
      <span class="lock-icon">🔒</span>
      <p>El planeta de los simios: [R] Evolucion</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z episodio de bardock " data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_episodio_de_bardock" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/f9a79aC4CaaUKZt4el5Ncnt24sM.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Episodio de Bardock</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="thor" data-genero="accion marvel comedia ciencia ficcion" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=thor" data-fecha="2026-01-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg">
      <span class="lock-icon">🔒</span>
      <p>Thor</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="alvin y las ardillas 3" data-genero="animacion comedia aventura musical" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_3" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/a52ebjlDqvrjcKtFGDtQgNQLaGH.jpg">
      <span class="lock-icon">🔒</span>
      <p>Alvin y las ardillas 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final 5" data-genero="terror" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final_5" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xMBIeENKIZq3V0undgvaZbFdMw2.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final 5</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="capitan america 1 el primer vengador" data-genero="accion heroes marvel" data-anio="2011" data-html="../View Peliculas/Reproductor Universal.php?id=capitan_america21" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2011</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg">
      <span class="lock-icon">🔒</span>
      <p>Capitán América: El primer vengador</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z plan para erradicar a los Super Saiyans" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_plan_erradicar" data-fecha="2025-12-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/qPv8avE1joxywziPMd49k6yINJp.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Plan para erradicar a los Super Saiyans</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dulce venganza" data-genero="crimen terror violacion suspenso" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=dulce_venganza" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/yfJwNAIzPPyAAOoCue1goOuHM81.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dulce venganza</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="iron man" data-genero="accion ciencia ficción marvel" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=iron_man_2" data-fecha="2026-02-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/1NHEyFPxKnsLdMuDVPy6AI7GRmE.jpg">
      <span class="lock-icon">🔒</span>
      <p>Iron-Man 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="como entrenar a tu dragon 1" data-genero="animacion aventura ciencia ficcion familia" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_1" data-fecha="2025-11-25">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/8ekxsUORMAsfmSc8GzHmG8gWPbp.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cómo entrenar a tu dragón</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="mi villano favorito" data-genero="animacion aventura comedia familia accion" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=mi_villano_favorito" data-fecha="2026-02-14">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/pgDbf2DPNWVz5D8PvgsCoI21k7j.jpg">
      <span class="lock-icon">🔒</span>
      <p>Mi villano favorito</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="toy story 3" data-genero="animacion disney familia comedia aventura" data-anio="2010" data-html="../View Peliculas/Reproductor Universal.php?id=toy_story_3" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2010</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mYSY87AVVogFNg45C4LE5Rh2ALG.jpg">
      <span class="lock-icon">🔒</span>
      <p>Toy story 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final 4" data-genero="terror" data-anio="2009" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final_4" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2009</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/8b1tsUQW8hogJRi6FFHHfO7D1fu.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final 4</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="alvin y las ardillas 2" data-genero="animacion comedia aventura musical" data-anio="2007" data-html="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas_2" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2009</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ye1MoMxdW6imx1BdytGxXYvj4BT.jpg">
      <span class="lock-icon">🔒</span>
      <p>Alvin y las ardillas 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="iron man" data-genero="accion marvel comedia ciencia ficcion" data-anio="2008" data-html="../View Peliculas/Reproductor Universal.php?id=iron_man_1" data-fecha="2026-01-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2008</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/bFj7XRg5avQDvuvWaag3IttjEAw.jpg">
      <span class="lock-icon">🔒</span>
      <p>Iron-Man</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="alvin y las ardillas" data-genero="animacion aventura comedia musical" data-anio="2007" data-html="../View Peliculas/Reproductor Universal.php?id=alvin_y_las_ardillas" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2007</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/jgvlT0DhzAQET6nkM6N1BVoGDSj.jpg">
      <span class="lock-icon">🔒</span>
      <p>Alvin y las ardillas</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final 3" data-genero="terror" data-anio="2006" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final_3" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2006</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5sSZBolbPCxCVXabzmL0bKWLgsv.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final 3</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="tierra de osos 2" data-genero="animacion disney familia aventura" data-anio="2006" data-html="../View Peliculas/Reproductor Universal.php?id=tierra_de_osos_2" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2006</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg">
      <span class="lock-icon">🔒</span>
      <p>Tierra de osos 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="cars 1" data-genero="animacion disney familia aventura" data-anio="2006" data-html="../View Peliculas/Reproductor Universal.php?id=cars" data-fecha="2025-11-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2006</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/abW5AzHDaIK1n9C36VdAeOwORRA.jpg">
      <span class="lock-icon">🔒</span>
      <p>Cars</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="madagascar" data-genero="animacion aventura familia animales" data-anio="2005" data-html="../View Peliculas/Reproductor Universal.php?id=madagascar" data-fecha="2026-02-16">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2005</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/v6bFSYpmAREGriQiMJvvO9TiapM.jpg">
      <span class="lock-icon">🔒</span>
      <p>Madagascar</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="la novia cadaver" data-genero="animacion romance fantasia" data-anio="2005" data-html="../View Peliculas/Reproductor Universal.php?id=la_novia_cadaver" data-fecha="2026-03-09">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2005</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/3ALM0VeZjGUryAqWo6pqohzbLDh.jpg">
      <span class="lock-icon">🔒</span>
      <p>La novia cadáver</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="tarzan 2" data-genero="animacion aventura familia disney" data-anio="2005" data-html="../View Peliculas/Reproductor Universal.php?id=tarzan_2" data-fecha="2025-01-20">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2005</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5KRnGepv2b1daJ2WM8ZGnPS64nl.jpg">
      <span class="lock-icon">🔒</span>
      <p>Tarzan 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bob esponja 1" data-tipo="pelicula" data-genero="animacion aventura niños infantil familia" data-anio="2004" data-html="../View Peliculas/Reproductor Universal.php?id=bob_esponja_1" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2004</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/j4Sqs3SKNaJ4chdKXS1qqUlaWyW.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bob Esponja: La película</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="tierra de osos" data-genero="animacion disney familia aventura" data-anio="2003" data-html="../View Peliculas/Reproductor Universal.php?id=tierra_de_osos" data-fecha="2026-02-28">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2003</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg">
      <span class="lock-icon">🔒</span>
      <p>Tierra de osos</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="chicas malas" data-tipo="pelicula" data-genero="drama comedia" data-anio="2003" data-html="../View Peliculas/Reproductor Universal.php?id=chicas_malas_2004" data-fecha="2025-11-24">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2003</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7L7wCakqwuoz6S9zRVaAH0NLJ3H.jpg">
      <span class="lock-icon">🔒</span>
      <p>Chicas malas</p>
    </div>
    
    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final 2" data-genero="terror" data-anio="2003" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final_2" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2003</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/w1dJluO5aKK7Puz7qNXoQeUh4Cb.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bad boys 2 dos policías rebeldes" data-genero="accion crimen comedia" data-anio="2003" data-html="../View Peliculas/Reproductor Universal.php?id=bad_boys_2" data-fecha="2025-11-02">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2003</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/qyHDZB87UQF9cu6uuQzhhaKGvuo.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bad boys 2: Dos policías rebeldes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="destino final" data-genero="terror" data-anio="2000" data-html="../View Peliculas/Reproductor Universal.php?id=destino_final" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">2000</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/2g4Jz0Jr54aYCpFLWKYDo5VZvzN.jpg">
      <span class="lock-icon">🔒</span>
      <p>Destino final</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="tarzan" data-genero="animacion aventura familia disney" data-anio="1999" data-html="../View Peliculas/Reproductor Universal.php?id=tarzan" data-fecha="2025-01-20">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1999</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/u9WgwjFpBWc3eQxddUFSicH2K6p.jpg">
      <span class="lock-icon">🔒</span>
      <p>Tarzan</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="toy story 2" data-genero="animacion disney familia comedia aventura" data-anio="1888" data-html="../View Peliculas/Reproductor Universal.php?id=toy_story_2" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1888</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/4rbcp3ng8n1MKHjpeqW0L7Fnpzz.jpg">
      <span class="lock-icon">🔒</span>
      <p>Toy story 2</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball dragon ball z una gran aventura mistica" data-genero="animacion anime accion" data-anio="1998" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_gran_aventura_mistica" data-fecha="2026-02-27">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1998</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/f2BipTKswrdpqoCc1xJDyL35rJy.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball: Gran aventura mística</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="aladdin 3 el rey de los ladrones" data-genero="animacion aventura disney romance fantasia " data-anio="1986" data-html="../View Peliculas/Reproductor Universal.php?id=aladdim_3" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1996</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/abWvjyJz4kcp1xDn28RwyXjoIds.jpg">
      <span class="lock-icon">🔒</span>
      <p>Aladdin 3: El rey de los ladrones</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball dragon ball z el camino hacia el poder" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="1996" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_el_camino_hacia_el_poder" data-fecha="2025-11-26">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1996</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wPkoqtFhDoIbzt61oOYwmLOZdAg.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball: El camino hacia el poder</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball dragon ball z  gt 100 años despues" data-tipo="pelicula" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="1996" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_gt_despues_de_100_años" data-fecha="2025-11-30">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1996</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/izZaeWcWDir9PvuSwaITV1E1rA8.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball GT: Después 100 años </p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z el ataque del dragon" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="1995" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_ataque_del_dragon" data-fecha="2026-03-10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1995</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/7uRu9EA3nie0n2mlVDDLlTI3IzC.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El ataque del dragon</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="bad boys 1 dos policías rebeldes" data-genero="accion crimen comedia" data-anio="1995" data-html="../View Peliculas/Reproductor Universal.php?id=bad_boys_1" data-fecha="2025-07-11">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1995</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/ZYpSdXaTMFYCGbmVmXOFbdJmSv.jpg">
      <span class="lock-icon">🔒</span>
      <p>Bad boys: Dos policías rebeldes</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="toy story" data-genero="animacion disney familia comedia aventura" data-anio="1995" data-html="../View Peliculas/Reproductor Universal.php?id=toy_story" data-fecha="2026-02-15">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1995</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/koUNJtRB1iRKhST9s4itGTzU6lp.jpg">
      <span class="lock-icon">🔒</span>
      <p>Toy story</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z el combate final" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1994" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_combate_final" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1994</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/gYcZAjYdTUGVf5oyqO2CawwuBla.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El combate final</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="aladdin 2 el retorno de fafar" data-genero="animacion aventura disney romance fantasia " data-anio="1994" data-html="../View Peliculas/Reproductor Universal.php?id=aladdin_2" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1994</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/tC54XTUu4NVsMeWdSofja2uye9c.jpg">
      <span class="lock-icon">🔒</span>
      <p>Aladdin 2: El retorno de Jafar</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="Ddragon ball z el regreso del guerrero legendario" data-genero="animacion anime accion ciencia ficcion" data-anio="1994" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_regreso_de_broly" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1994</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/iwvMmddNNf6DVLq3CBe8hhpHUgE.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El regreso del guerrero legendario</p>
    </div>


    <div class="movie locked" data-tipo="pelicula" data-titulo="aladdin" data-genero="animacion aventura disney fantasia musical familia" data-anio="1993" data-html="../View Peliculas/Reproductor Universal.php?id=aladdin" data-fecha="2025-10-12">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1993</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/eLFfl7vS8dkeG1hKp5mwbm37V83.jpg">
      <span class="lock-icon">🔒</span>
      <p>Aladdín</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z el poder invensible" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1993" data-html="../View Peliculas/dReproductor Universal.php?id=ragon_ball_z_el_poder_invencible" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1993</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/qanX5FNg7w7DfjLqwGHZJtiF0Ri.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El poder invensible</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z la galaxia corre peligro" data-genero="animacion anime accion ciencia ficcion" data-anio="1993" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_galaxia_corre_peligro" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1993</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/oAUr61gawC5q4LlxtmfrIwKeGco.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: La galaxia corre peligro</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z los dos guerreros del futuro" data-genero="animacion anime accion ciencia ficcion" data-anio="1993" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_guerreros_del_futuro" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1993</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/x0FCkSSdOGTA3gC99QayGJH0Dqx.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Los dos guerreros del futuro</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z el regreso de cooler" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1992" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_regreso_de_cooler" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1992</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/zJn14ySh0NTZCOIReQZiWE1fkje.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El regreso de cooler</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z ños tres grandes Super Saiyans" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1992" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_tres_grendes_guerreros_saiyajin" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1992</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/pIwjWaEuCcT3QVBd9Ng9wG3kbpU.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Los tres grandes Super Saiyans</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z los rivales mas poderosos" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1991" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_los_rivales_mas_poderosos" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1991</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/uqTSXqjaSgSAT2lCv3GyZeodQPG.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Los rivales mas poderosos</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z el super saiyajin son goku" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1991" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_super_saiyayin_son_goku" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1991</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/usMb0DzjnMkekizU3ZKkTHQ4x40.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El super saiyajin Son Goku</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z la super batalla" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1990" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_super_batalla" data-fecha="2025-12-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1990</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/69dMY6CPe6mqi7nMC2bVeCcjJQI.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: La super batalla</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z la pelea de bardock vs freezer" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1990" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_bardock_vs_freezer" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1990</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/mnFEyVcDlSshzl65hEdWoYXtnm3.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: La pelea de Bardock vs Freezer</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball z el mas fuerte del mundo" data-tipo="pelicula" data-genero="animacion anime accion ciencia fiscion" data-anio="1990" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_z_el_mas_fuerte_del_mundo" data-fecha="2025-12-07">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1990</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5elbm3iLgGQ6nA5vqUmi9vIojbF.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: El mas fuerte del mundo</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="dragon ball z devuelveme a mi gohan" data-genero="anime animacion accion ciencia ficcion" data-anio="1989" data-html="View Peliculas/Reproductor Universal.php?id=dragon_ball_z_devuelveme_a_mi_gohan" data-fecha="2026/03/10">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1989</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/koo5d4CdZd0sxcxxTgxXUHMSY10.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball Z: Devuelveme a mi Gohan</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball dragon ball z  la princesa durmiente del castillo del mal" data-tipo="pelicula" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="1987" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_la_princesa_durmiente" data-fecha="2025-12-01">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1987</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/sTTQ3efvJeW4VDheKvyoLgFAgku.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball: La princesa durmiente del castillo del mal</p>
    </div>

    <div class="movie locked" data-titulo="dragon ball dragon ball z la leyenda del dragon shenron" data-tipo="pelicula" data-genero="animacion anime accion ciencia ficcion fantasia" data-anio="1986" data-html="../View Peliculas/Reproductor Universal.php?id=dragon_ball_la_leyenda_de_shenron" data-fecha="2026-02-27">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1986</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/5uvaNiQ1rq08rAJgg5NyXQdBC58.jpg">
      <span class="lock-icon">🔒</span>
      <p>Dragon Ball: La leyenda del dragón Shenron</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="pinocho" data-genero="animacion disney aventura" data-anio="1940" data-html="../View Peliculas/Reproductor Universal.php?id=pinocho" data-fecha="2026-02-23">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1940</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/nsnyd6MFznuFSaHk1iveAdWc3nI.jpg">
      <span class="lock-icon">🔒</span>
      <p>Pinocho</p>
    </div>

    <div class="movie locked" data-tipo="pelicula" data-titulo="blancanieves y los siete enanitos" data-genero="animacion disney princesas" data-anio="1938" data-html="../View Peliculas/Reproductor Universal.php?id=blancanieves_y_los_siete_enanitos" data-fecha="2025-11-08">
      <span class="pelicula">Pelicula</span>
      <span class="year-tag">1938</span>
      <span class="year-tegg">HD</span>
      <img src="https://image.tmdb.org/t/p/w300/wdA4lphQwywsPcEKj5sgQ9QSR55.jpg">
      <span class="lock-icon">🔒</span>
      <p>Blancanieves y los siete enanitos</p>
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
