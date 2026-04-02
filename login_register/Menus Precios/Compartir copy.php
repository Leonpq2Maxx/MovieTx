<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Compartir MovieTx</title>

<!-- QR -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<style>
:root{
  --primary:#e50914;
  --bg:#0b0b0f;
  --card:#16161d;
}

*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:'Segoe UI',sans-serif;
}

body{
  background:var(--bg);
  color:white;
}

/* HEADER */
.header{
  position:fixed;
  top:0;
  width:100%;
  height:60px;
  display:flex;
  align-items:center;
  padding:0 15px;
  background:rgba(0,0,0,0.7);
  backdrop-filter:blur(10px);
  border-bottom:1px solid #222;
  z-index:1000;
}

.header button{
  background:none;
  border:none;
  cursor:pointer;
}

.header svg{
  width:26px;
  fill:white;
}

.header span{
  margin-left:10px;
  font-weight:600;
}

/* CONTENEDOR */
.container{
  max-width:420px;
  margin:auto;
  padding:90px 20px 40px;
}

/* TITULO */
.hero{
  text-align:center;
  margin-bottom:25px;
}

.hero h1{
  font-size:30px;
}

.hero p{
  color:#aaa;
  font-size:14px;
}

/* CARD */
.card{
  background:var(--card);
  border-radius:16px;
  padding:20px;
  margin-bottom:20px;
  box-shadow:0 8px 30px rgba(0,0,0,0.5);
}

/* QR */
.qr{
  background:white;
  padding:12px;
  border-radius:12px;
  display:flex;
  justify-content:center;
  margin-bottom:15px;
}

/* BOTONES */
.btn{
  width:100%;
  padding:14px;
  border:none;
  border-radius:12px;
  font-weight:bold;
  margin-top:10px;
  cursor:pointer;
  transition:0.2s;
}

.btn:active{
  transform:scale(0.97);
}

.whatsapp{ background:#25D366; }
.instagram{ background:linear-gradient(45deg,#f09433,#dc2743,#bc1888); }
.copy{ background:#333; }
.apk{ background:var(--primary); }

/* MENSAJE */
.msg{
  text-align:center;
  font-size:13px;
  color:#00ffcc;
  display:none;
  margin-top:10px;
}

/* TV */
.tv h3{
  text-align:center;
  margin-bottom:10px;
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
  <button onclick="history.back()">
    <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
  </button>
  <span>Compartir</span>
</div>

<div class="container">

<!-- HERO -->
<div class="hero">
  <h1>MovieTx</h1>
  <p>Compartí la app y disfrutá más contenido 🚀</p>
</div>

<!-- CARD SHARE -->
<div class="card">

  <div class="qr">
    <div id="qrApp"></div>
  </div>

  <button class="btn whatsapp" onclick="shareWA()">📲 WhatsApp</button>
  <button class="btn instagram" onclick="shareIG()">📸 Instagram</button>
  <button class="btn copy" onclick="copyLink()">📋 Copiar link</button>

  <button id="apkBtn" class="btn apk" style="display:none;">
    📥 Descargar APK
  </button>

  <div id="msg" class="msg">✔ Copiado</div>

</div>

<!-- CARD TV -->
<div class="card tv">

  <h3>Ver en Smart TV 📺</h3>

  <div class="qr">
    <div id="qrTV"></div>
  </div>

  <button class="btn whatsapp" onclick="shareTV()">Compartir Google Home</button>

</div>

</div>

<script>
/* LINKS */
const appLink = "https://www.mediafire.com/file/bcbql4viydtsvw9/MovieTx.apk/file";
const message = `🔥 Descarga MovieTx:\n${appLink}`;
const tvLink = "https://play.google.com/store/apps/details?id=com.google.android.apps.chromecast.app";

/* QR */
new QRCode(document.getElementById("qrApp"), {
  text: appLink,
  width: 180,
  height: 180
});

new QRCode(document.getElementById("qrTV"), {
  text: tvLink,
  width: 180,
  height: 180
});

/* FUNCIONES */
function shareWA(){
  window.open(`https://wa.me/?text=${encodeURIComponent(message)}`);
}

function shareIG(){
  navigator.clipboard.writeText(message);
  showMsg();
}

function copyLink(){
  navigator.clipboard.writeText(appLink);
  showMsg();
}

function shareTV(){
  window.open(`https://wa.me/?text=${encodeURIComponent(tvLink)}`);
}

function showMsg(){
  const m = document.getElementById("msg");
  m.style.display = "block";
  setTimeout(()=> m.style.display = "none", 2000);
}

/* DETECTAR ANDROID */
if(/android/i.test(navigator.userAgent)){
  const btn = document.getElementById("apkBtn");
  btn.style.display = "block";
  btn.onclick = () => window.open(appLink);
}
</script>

</body>
</html>