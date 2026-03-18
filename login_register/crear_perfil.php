<?php
session_start();
require_once "config.php";

/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear perfil</title>

<link href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet"/>

<style>

/* =========================
RESET
========================= */
*{
margin:0;
padding:0;
box-sizing:border-box;
}

/* =========================
BODY
========================= */
body{
font-family:'Segoe UI',Arial;
background:#0b0b0b;
color:#fff;
min-height:100vh;
display:flex;
flex-direction:column;
}

/* =========================
LAYOUT RESPONSIVE
========================= */
.wrapper{
flex:1;
display:flex;
align-items:center;
justify-content:center;
padding:20px;
}

/* =========================
CARD
========================= */
.crear-perfil-box{
width:100%;
max-width:420px;
background:#141414;
padding:30px;
border-radius:20px;
box-shadow:0 10px 40px rgba(0,0,0,0.8);
text-align:center;
animation:fadeIn 0.4s ease;
}

@keyframes fadeIn{
from{opacity:0;transform:translateY(20px)}
to{opacity:1;transform:translateY(0)}
}

.crear-perfil-box h2{
margin-bottom:20px;
font-size:20px;
}

/* =========================
AVATAR
========================= */
.avatar-preview{
width:120px;
height:120px;
margin:0 auto 20px;
border-radius:50%;
overflow:hidden;
background:#222;
display:flex;
align-items:center;
justify-content:center;
cursor:pointer;
border:2px solid #333;
transition:0.3s;
position:relative;
}

.avatar-preview:hover{
border-color:#e50914;
transform:scale(1.05);
}

.avatar-preview img{
width:100%;
height:100%;
object-fit:cover;
}

.avatar-plus{
position:absolute;
font-size:40px;
color:#aaa;
}

.file-input{
display:none;
}

/* =========================
FORM
========================= */
.form-perfil{
display:flex;
flex-direction:column;
gap:15px;
}

.form-perfil input{
padding:14px;
border-radius:10px;
border:none;
background:#222;
color:white;
font-size:15px;
}

.form-perfil input:focus{
outline:none;
box-shadow:0 0 0 2px #e50914;
}

/* BOTON */
.form-perfil button{
background:#e50914;
border:none;
padding:14px;
border-radius:10px;
color:white;
font-weight:bold;
cursor:pointer;
transition:0.3s;
}

.form-perfil button:hover{
background:#ff1f1f;
transform:scale(1.03);
}

/* =========================
MODAL CROPPER FULLSCREEN
========================= */
#cropModal{
display:none;
position:fixed;
top:0;
left:0;
width:100%;
height:100%;
background:#000;
z-index:999;
flex-direction:column;
}

/* HEADER */
.crop-header{
padding:15px;
display:flex;
justify-content:space-between;
align-items:center;
background:#111;
}

.crop-header h3{
font-size:16px;
}

.crop-header button{
background:none;
border:none;
color:#fff;
font-size:14px;
cursor:pointer;
}

/* AREA IMAGEN */
.crop-container{
flex:1;
display:flex;
align-items:center;
justify-content:center;
background:#000;
}

.crop-container img{
max-width:100%;
max-height:100%;
}

/* FOOTER */
.crop-footer{
padding:15px;
background:#111;
display:flex;
gap:10px;
}

.crop-footer button{
flex:1;
padding:12px;
border:none;
border-radius:8px;
cursor:pointer;
font-weight:bold;
}

.btn-save{
background:#e50914;
color:white;
}

.btn-cancel{
background:#333;
color:white;
}

/* =========================
RESPONSIVE
========================= */
@media (min-width:768px){

.crear-perfil-box{
padding:40px;
}

.avatar-preview{
width:140px;
height:140px;
}

}

/* desktop grande */
@media (min-width:1200px){

.wrapper{
padding:40px;
}

.crear-perfil-box{
max-width:500px;
}

}

</style>
</head>

<body>

<div class="wrapper">

<div class="crear-perfil-box">

<h2>Crear nuevo perfil</h2>

<form id="formPerfil" action="guardar_perfil.php" method="POST" enctype="multipart/form-data" class="form-perfil">

<label class="avatar-preview">
<img id="preview" src="default.png">
<div class="avatar-plus" id="avatarPlus">+</div>
<input type="file" name="foto" class="file-input" accept="image/*" onchange="previewImage(event)">
</label>

<input type="text" name="nombre" placeholder="Nombre del perfil" required>

<button type="submit">Crear perfil</button>

</form>

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

<!-- =========================
MODAL CROPPER PRO
========================= -->
<div id="cropModal">

<div class="crop-header">
<button onclick="cerrarCrop()">Cancelar</button>
<h3>Ajustar foto</h3>
<button onclick="recortarImagen()">Guardar</button>
</div>

<div class="crop-container">
<img id="imageToCrop">
</div>

<div class="crop-footer">
<button class="btn-cancel" onclick="zoomOut()">- Zoom</button>
<button class="btn-save" onclick="zoomIn()">+ Zoom</button>
</div>

</div>

<script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.js"></script>

<script>

let cropper;
let imagenFinalBlob = null;

/* =========================
ABRIR CROPPER
========================= */
function previewImage(event){

const file = event.target.files[0];
if(!file) return;

const reader = new FileReader();

reader.onload = function(){

document.getElementById("imageToCrop").src = reader.result;
document.getElementById("cropModal").style.display = "flex";

if(cropper){
    cropper.destroy();
}

cropper = new Cropper(document.getElementById("imageToCrop"),{
    aspectRatio:1,
    viewMode:1,
    dragMode:'move',
    autoCropArea:1,
    movable:true,
    zoomable:true,
    scalable:true,
    rotatable:false,
    responsive:true,
    background:false,
    wheelZoomRatio:0.1
});

}

reader.readAsDataURL(file);

}

/* =========================
ZOOM BOTONES
========================= */
function zoomIn(){
if(cropper) cropper.zoom(0.1);
}

function zoomOut(){
if(cropper) cropper.zoom(-0.1);
}

/* =========================
RECORTAR
========================= */
function recortarImagen(){

const canvas = cropper.getCroppedCanvas({
    width:400,
    height:400
});

document.getElementById("preview").src = canvas.toDataURL();
document.getElementById("avatarPlus").style.display="none";

canvas.toBlob(function(blob){
    imagenFinalBlob = blob;
});

cerrarCrop();

}

function cerrarCrop(){
document.getElementById("cropModal").style.display = "none";
}

/* =========================
SUBMIT
========================= */
document.getElementById("formPerfil").addEventListener("submit", function(e){

e.preventDefault();

let formData = new FormData(this);

if(imagenFinalBlob){
    formData.set("foto", imagenFinalBlob, "perfil.png");
}

fetch("guardar_perfil.php",{
    method:"POST",
    body:formData
})
.then(res=>res.text())
.then(res=>{
    if(res.trim()==="ok"){
        window.location.href="cuentas.php";
    }else{
        alert("Error al crear perfil");
    }
});

});

</script>

</body>
</html>
