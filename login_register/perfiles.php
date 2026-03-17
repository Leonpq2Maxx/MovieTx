<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['id'])){
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['id'];

/* SELECCIONAR PERFIL */
if(isset($_GET['perfil_id'])){
    $perfilId = intval($_GET['perfil_id']);

    // 🔥 PERFIL PRINCIPAL (usuario)
    if($perfilId === 0){
        unset($_SESSION['perfil_id']);
        header("Location: inicio.php");
        exit;
    }

    // 🔒 VERIFICAR QUE EL PERFIL SEA DEL USUARIO
    $stmtPerfil = $conn->prepare("SELECT id FROM perfiles WHERE id=? AND user_id=?");
    $stmtPerfil->bind_param("ii", $perfilId, $userId);
    $stmtPerfil->execute();
    $resPerfil = $stmtPerfil->get_result();

    if($resPerfil->num_rows > 0){
        $_SESSION['perfil_id'] = $perfilId;
    }

    header("Location: inicio.php");
    exit;
}

/* OBTENER FOTO Y NOMBRE DEL USUARIO */

$stmtUser = $conn->prepare("SELECT foto, name FROM users WHERE id=?");
$stmtUser->bind_param("i",$userId);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$user = $resultUser->fetch_assoc();

$fotoUsuario = !empty($user['foto']) ? $user['foto'] : "default.png";
$nombreUsuario = $user['name'] ?? "Usuario";

/* 🔒 OBTENER LIMITE DE PERFILES */
$stmtLimit = $conn->prepare("SELECT max_perfiles FROM users WHERE id=?");
$stmtLimit->bind_param("i",$userId);
$stmtLimit->execute();
$maxPerfiles = $stmtLimit->get_result()->fetch_assoc()['max_perfiles'];

/* PERFILES */

$stmt = $conn->prepare("SELECT * FROM perfiles WHERE user_id=?");
$stmt->bind_param("i",$userId);
$stmt->execute();
$result = $stmt->get_result();

/* 🔒 CONTAR PERFILES */
$totalPerfiles = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MovieTx - Perfiles</title>

<style>

/* RESET */
*{
box-sizing:border-box;
margin:0;
padding:0;
}

body{
font-family:Arial, Helvetica, sans-serif;
background:#141414;
color:white;
}

/* HEADER */
.header{
display:flex;
justify-content:space-between;
align-items:center;
padding:15px 25px;
background:#0d0d0d;
border-bottom:1px solid #222;
}

/* LOGO */
.logo{
display:flex;
align-items:center;
gap:10px;
}

.logo img{
height:42px;
}

.logo span{
font-size:22px;
font-weight:bold;
color:#e50914;
}

/* BOTON LOGOUT */
.logout-btn{
background:#e50914;
border:none;
padding:8px 16px;
border-radius:6px;
color:white;
font-size:14px;
cursor:pointer;
transition:0.3s;
}

.logout-btn:hover{
background:#ff1f1f;
}

/* CONTENEDOR */
.container{
max-width:1200px;
margin:auto;
text-align:center;
padding:60px 20px;
}

/* TITULO */
h1{
margin-bottom:60px;
font-size:34px;
}

/* 🔥 GRID PERFECTO */
.perfiles-grid{
display:grid;
grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
gap:50px;
justify-items:center;
}

/* TARJETA */
.perfil-card{
display:flex;
flex-direction:column;
align-items:center;
justify-content:center;
cursor:pointer;
transition:0.3s;
}

/* 🔥 IMAGEN MUCHO MÁS GRANDE */
.perfil-card img{
width:190px;
height:190px;
border-radius:20px;
object-fit:cover;
border:3px solid transparent;
box-shadow:0 10px 25px rgba(0,0,0,0.6);
transition:0.3s;
}

/* TEXTO PERFECTAMENTE CENTRADO */
.perfil-card p{
margin-top:12px;
font-size:18px;
color:#ccc;
text-align:center;
width:100%;
}

/* HOVER */
.perfil-card:hover img{
border:3px solid white;
transform:scale(1.08);
}

.perfil-card:hover p{
color:white;
}

/* BOTON AGREGAR */
.add-profile{
width:190px;
height:190px;
border-radius:20px;
background:#2a2a2a;
display:flex;
align-items:center;
justify-content:center;
font-size:80px;
color:#aaa;
transition:0.3s;
}

.add-profile:hover{
background:#e50914;
color:white;
transform:scale(1.08);
}

.add-text{
margin-top:12px;
color:#aaa;
}

/* 📱 MOBILE (AHORA SI GRANDES DE VERDAD) */
@media (max-width:600px){

.perfiles-grid{
grid-template-columns:repeat(2, 1fr); /* 2 columnas */
gap:30px;
}

.perfil-card img{
width:150px;
height:150px;
}

.add-profile{
width:150px;
height:150px;
font-size:60px;
}

.perfil-card p{
font-size:15px;
}

h1{
font-size:24px;
margin-bottom:40px;
}

}

</style>

</head>

<body>

<!-- HEADER -->
<div class="header">

<div class="logo">
<img src="Logo Poster MovieTx PNG/Logo MovieTx.png">
<span>MovieTx</span>
</div>

<button class="logout-btn" onclick="location.href='logout.php'">
Cerrar sesión
</button>

</div>

<!-- CONTENEDOR -->
<div class="container">

<h1>¿Quién está viendo?</h1>

<div class="perfiles-grid">

<!-- USUARIO PRINCIPAL -->
<div class="perfil-card" onclick="location.href='perfiles.php?perfil_id=0'">
<img src="<?php echo $fotoUsuario; ?>">
<p><?php echo $nombreUsuario; ?></p>
</div>

<!-- PERFILES -->
<?php while($perfil = $result->fetch_assoc()){ ?>

<div class="perfil-card" onclick="location.href='perfiles.php?perfil_id=<?php echo $perfil['id']; ?>'">
<img src="uploads/perfiles/<?php echo $perfil['foto']; ?>">
<p><?php echo $perfil['nombre']; ?></p>
</div>

<?php } ?>

<!-- AGREGAR PERFIL -->
<?php if($totalPerfiles < ($maxPerfiles - 1)): ?>
<div class="perfil-card" onclick="location.href='crear_perfil.php'">
    <div class="add-profile">+</div>
    <p class="add-text">Agregar perfil</p>
</div>
<?php endif; ?>

</div>

</div>

</body>
</html>