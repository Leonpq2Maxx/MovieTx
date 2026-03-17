<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear perfil</title>

<style>

/* =========================
RESET
========================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
font-family:Arial, Helvetica, sans-serif;
background:linear-gradient(180deg,#0f0f0f,#141414,#0f0f0f);
color:white;
display:flex;
align-items:center;
justify-content:center;
min-height:100vh;
}

/* =========================
CONTENEDOR
========================= */

.crear-perfil-box{
width:420px;
background:#1c1c1c;
padding:40px;
border-radius:16px;
box-shadow:0 15px 50px rgba(0,0,0,0.7);
text-align:center;
animation:fadeIn 0.6s ease;
}

@keyframes fadeIn{
from{opacity:0;transform:translateY(20px)}
to{opacity:1;transform:translateY(0)}
}

.crear-perfil-box h2{
margin-bottom:25px;
font-weight:600;
}

/* =========================
FOTO PERFIL
========================= */

.avatar-preview{
width:120px;
height:120px;
margin:0 auto 20px;
border-radius:15px;
overflow:hidden;
background:#2a2a2a;
display:flex;
align-items:center;
justify-content:center;
cursor:pointer;
border:2px solid #333;
transition:0.3s;
position:relative;
}

.avatar-preview:hover{
border:2px solid #e50914;
}

/* IMAGEN */

.avatar-preview img{
width:100%;
height:100%;
object-fit:cover;
}

/* ICONO + */

.avatar-plus{
position:absolute;
font-size:40px;
color:#aaa;
transition:0.3s;
}

.avatar-preview:hover .avatar-plus{
color:#fff;
transform:scale(1.2);
}

/* INPUT FILE */

.file-input{
display:none;
}

/* =========================
FORM
========================= */

.form-perfil{
display:flex;
flex-direction:column;
gap:18px;
}

/* INPUT */

.form-perfil input[type="text"]{
padding:14px;
border-radius:8px;
border:none;
background:#2a2a2a;
color:white;
font-size:16px;
outline:none;
transition:0.3s;
}

.form-perfil input::placeholder{
color:#aaa;
}

.form-perfil input:focus{
background:#333;
box-shadow:0 0 0 2px #e50914;
}

/* BOTON */

.form-perfil button{
background:#e50914;
border:none;
padding:14px;
border-radius:8px;
color:white;
font-size:16px;
cursor:pointer;
transition:0.3s;
}

.form-perfil button:hover{
background:#ff1f1f;
transform:scale(1.03);
}

/* =========================
RESPONSIVE
========================= */

@media (max-width:600px){

.crear-perfil-box{
width:90%;
padding:30px;
}

.avatar-preview{
width:100px;
height:100px;
}

.avatar-plus{
font-size:35px;
}

}

</style>
</head>

<body>

<div class="crear-perfil-box">

<h2>Crear nuevo perfil</h2>

<form action="guardar_perfil.php" method="POST" enctype="multipart/form-data" class="form-perfil">

<label class="avatar-preview">

<img id="preview" src="default.png">

<div class="avatar-plus" id="avatarPlus">+</div>

<input type="file" name="foto" class="file-input" accept="image/*" onchange="previewImage(event)">

</label>

<input 
type="text" 
name="nombre" 
placeholder="Nombre del perfil"
required
>

<button type="submit">
Crear perfil
</button>

</form>

</div>

<script>

function previewImage(event){

const reader = new FileReader();

reader.onload = function(){

document.getElementById("preview").src = reader.result;

/* ocultar el + */

document.getElementById("avatarPlus").style.display="none";

}

reader.readAsDataURL(event.target.files[0]);

}

</script>

</body>
</html>
