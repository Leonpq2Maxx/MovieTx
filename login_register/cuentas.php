<?php
session_start();
require "config.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

if(!isset($_SESSION['email'])){
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];

/* =========================
   🔥 DETECTAR PERFIL
========================= */
$esPerfil = isset($_SESSION['perfil_id']);

/* =========================
   USUARIO
========================= */
$stmt = $conn->prepare("
SELECT id, name, email, password, foto, max_perfiles, paid_until 
FROM users 
WHERE email=? LIMIT 1
");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if(!$user){
    die("Usuario no encontrado");
}

$userId = $user['id'];
$nombre = $user['name'];

$foto = !empty($user['foto']) 
    ? (strpos($user['foto'], 'uploads/') !== false 
        ? $user['foto'] 
        : 'uploads/usuarios/'.$user['foto']) 
    : 'uploads/usuarios/default.png';

$maxPerfiles = intval($user['max_perfiles'] ?? 1);

$expira = !empty($user['paid_until']) 
    ? date("d/m/Y", strtotime($user['paid_until'])) 
    : "Sin fecha";

/* =========================
   ELIMINAR PERFIL
========================= */
if(isset($_POST['delete_perfil'])){

    // 🔒 BLOQUEAR SI ES PERFIL
    if($esPerfil){
        echo "no_autorizado";
        exit;
    }

    $idEliminar = intval($_POST['delete_perfil']);

    // 🔍 OBTENER FOTO DEL PERFIL
    $stmtFoto = $conn->prepare("SELECT foto FROM perfiles WHERE id=? AND user_id=?");
    $stmtFoto->bind_param("ii", $idEliminar, $userId);
    $stmtFoto->execute();
    $resFoto = $stmtFoto->get_result()->fetch_assoc();

    if($resFoto){

        $rutaFoto = "uploads/perfiles/" . $resFoto['foto'];

        // 🗑️ BORRAR ARCHIVO SI EXISTE Y NO ES DEFAULT
        if(!empty($resFoto['foto']) && $resFoto['foto'] !== "default.png" && file_exists($rutaFoto)){
            unlink($rutaFoto);
        }

        // 🗑️ BORRAR PERFIL
        $del = $conn->prepare("DELETE FROM perfiles WHERE id=? AND user_id=?");
        $del->bind_param("ii",$idEliminar,$userId);

        echo $del->execute() ? "ok" : "error";
    } else {
        echo "error";
    }

    exit;
}



/* =========================
   CAMBIAR CONTRASEÑA (PROTEGIDO)
========================= */
if(isset($_POST['cambiar_pass'])){

    // 🔒 BLOQUEAR SI ES PERFIL
    if($esPerfil){
        echo "no_autorizado";
        exit;
    }

    $correoInput = $_POST['correo'];
    $passActual = $_POST['pass_actual'];
    $passNueva = $_POST['pass_nueva'];

    if($correoInput !== $email){
        echo "correo_incorrecto"; exit;
    }

    if(!password_verify($passActual, $user['password'])){
        echo "pass_incorrecta"; 
        exit;
    }

    $hashNueva = password_hash($passNueva, PASSWORD_DEFAULT);

    $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param("si",$hashNueva,$userId);

    echo $upd->execute() ? "ok_pass" : "error";
    exit;
}

/* =========================
   PERFILES
========================= */
$stmtP = $conn->prepare("SELECT id, nombre, foto FROM perfiles WHERE user_id=?");
$stmtP->bind_param("i",$userId);
$stmtP->execute();

$perfiles = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
$totalReal = count($perfiles) + 1;

$seccion = $_GET['sec'] ?? "cuenta";
?>


<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cuenta</title>

<style>

/* RESET */
*{
    box-sizing:border-box;
}

/* BODY */
body{
    background:#0b0b0b;
    color:#fff;
    font-family:'Segoe UI',Arial;
    margin:0;
}

/* NAV */
.nav{
    display:flex;
    position:sticky;
    top:0;
    z-index:10;
    background:#111;
}
.nav button{
    flex:1;
    padding:15px;
    border:none;
    background:#1a1a1a;
    color:#bbb;
    cursor:pointer;
    font-weight:bold;
    transition:0.3s;
}
.nav button:hover{
    background:#222;
    color:#fff;
}
.nav .active{
    background:#e50914;
    color:#fff;
}

/* CONTAINER */
.container{
    padding:20px;
    max-width:1100px;
    margin:auto;
}

/* CARD */
.card{
    background:#141414;
    padding:25px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 0 20px rgba(0,0,0,0.5);
}

/* USER */
.main-user{
    text-align:center;
    margin-bottom:25px;
}
.main-user img{
    width:100px;
    height:100px;
    border-radius:50%;
    object-fit:cover;
    border:3px solid #e50914;
    transition:0.3s;
}
.main-user img:hover{
    transform:scale(1.05);
}

/* PERFILES GRID */
.perfiles{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:15px;
}

/* PERFIL */
.perfil{
    display:flex;
    align-items:center;
    justify-content:space-between;
    background:#1f1f1f;
    padding:12px;
    border-radius:12px;
    transition:0.3s;
}
.perfil:hover{
    background:#2a2a2a;
    transform:scale(1.02);
}
.perfil-info{
    display:flex;
    align-items:center;
    gap:12px;
}
.perfil img{
    width:55px;
    height:55px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid #e50914;
}

/* BUTTON */
.btn{
    background:#e50914;
    border:none;
    padding:8px 14px;
    border-radius:8px;
    color:#fff;
    cursor:pointer;
    font-weight:bold;
    transition:0.3s;
}
.btn:hover{
    background:#ff1f1f;
    transform:scale(1.05);
}

/* MODAL */
#modalPass{
    backdrop-filter:blur(5px);
}
#modalPass > div{
    animation:zoomIn 0.3s ease;
}
@keyframes zoomIn{
    from{transform:scale(0.7); opacity:0;}
    to{transform:scale(1); opacity:1;}
}

/* INPUT */
input{
    border:none;
    outline:none;
    border-radius:8px;
}

/* RESPONSIVE */
@media (max-width:600px){
    .main-user img{
        width:80px;
        height:80px;
    }
}

.input-box{
    position:relative;
    display:flex;
    align-items:center;
    margin:8px 0;
}

.input-box input{
    width:100%;
    padding:12px 40px 12px 10px;
    border-radius:8px;
}

.eye{
    position:absolute;
    right:10px;
    cursor:pointer;
    color:#000;
    background:#fff;
    padding:4px 7px;
    border-radius:6px;
    font-size:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    height:70%;
}


</style>
</head>

<body>

<div class="nav">
<button 
class="<?= $seccion=='cuenta'?'active':'' ?>" 
onclick="cambiarSeccion('cuenta')">
Cuenta
</button>

<button 
class="<?= $seccion=='plan'?'active':'' ?>" 
onclick="cambiarSeccion('plan')">
Plan
</button>
</div>


<div class="container">

<?php if($seccion=="cuenta"): ?>

<div class="card">

<h2>Cuenta</h2>

<div class="main-user">
<img src="<?= htmlspecialchars($foto) ?>" onerror="this.src='uploads/usuarios/default.png'">
<p><b><?= htmlspecialchars($nombre) ?> (Principal)</b></p>
<small><?= htmlspecialchars($email) ?></small><br>
<small>Límite de perfiles: <?= $maxPerfiles ?></small>
</div>

<h3>Perfiles</h3>

<div class="perfiles">

<div class="perfil">
<div class="perfil-info">
<img src="<?= htmlspecialchars($foto) ?>">
<span><?= htmlspecialchars($nombre) ?> (Principal)</span>
</div>
</div>

<?php foreach($perfiles as $p): 
$fotoPerfil = !empty($p['foto']) 
? 'uploads/perfiles/'.$p['foto'] 
: 'uploads/perfiles/default.png';
?>

<div class="perfil">
<div class="perfil-info">
<img src="<?= htmlspecialchars($fotoPerfil) ?>">
<span><?= htmlspecialchars($p['nombre']) ?></span>
</div>

<?php if(!$esPerfil): ?>
<button class="btn" onclick="eliminarPerfil(<?= $p['id'] ?>)">Eliminar</button>
<?php endif; ?>
</div>

<?php endforeach; ?>

</div>

<?php if($totalReal < $maxPerfiles): ?>
<button class="btn" style="margin-top:10px;" onclick="location.href='crear_perfil.php'">
+ Agregar Perfil
</button>
<?php endif; ?>

</div>
<?php endif; ?>

<?php if($seccion=="plan"): ?>
<div class="card">
<h2>Plan</h2>

<div class="main-user">
<img src="<?= htmlspecialchars($foto) ?>">
<p><b><?= htmlspecialchars($nombre) ?></b></p>
<small><?= htmlspecialchars($email) ?></small>
</div>
<?php if(!$esPerfil): ?>
<button class="btn" style="margin-top:15px;" onclick="mostrarCambioPass()">
Cambiar contraseña
</button>
<?php endif; ?>


<p><b>Límite perfiles:</b> <?= $maxPerfiles ?></p>
<p><b>Expira:</b> <?= $expira ?></p>

</div>
<?php endif; ?>

</div>

<!-- MODAL -->
<div id="modalPass" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#000000cc; justify-content:center; align-items:center;">

<div style="background:#1a1a1a; padding:20px; border-radius:15px; width:90%; max-width:400px;">

<h3>Cambiar contraseña</h3>

<input id="correo" type="email" value="<?= htmlspecialchars($email) ?>" placeholder="Correo" style="width:100%; margin:5px 0; padding:10px;">


<div class="input-box">
    <input id="pass_actual" type="password" placeholder="Contraseña actual">
    <span onclick="togglePass('pass_actual')" class="eye">👁</span>
</div>

<div class="input-box">
    <input id="pass_nueva" type="password" placeholder="Nueva contraseña">
    <span onclick="togglePass('pass_nueva')" class="eye">👁</span>
</div>


<button class="btn" style="width:100%; margin-top:10px;" onclick="cambiarPass()">Guardar</button>
<button class="btn" style="width:100%; margin-top:5px; background:#555;" onclick="cerrarModal()">Cancelar</button>

</div>
</div>

<div id="modalConfirm" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#000000cc; justify-content:center; align-items:center; z-index:999;">

<div style="background:#1a1a1a; padding:20px; border-radius:15px; width:90%; max-width:350px; text-align:center;">

<h3 id="tituloConfirm">Confirmar</h3>

<p id="textoConfirm" style="margin:15px 0;"></p>

<button class="btn" id="btnOk" style="width:100%; margin-bottom:10px;">Confirmar</button>
<button class="btn" style="width:100%; background:#555;" onclick="cerrarConfirm()">Cancelar</button>

</div>
</div>

<div id="modalMsg" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#000000cc; justify-content:center; align-items:center; z-index:999;">

<div style="background:#1a1a1a; padding:20px; border-radius:15px; width:90%; max-width:350px; text-align:center;">

<h3 id="msgTitulo">Mensaje</h3>

<p id="msgTexto" style="margin:15px 0;"></p>

<button class="btn" style="width:100%;" onclick="cerrarMsg()">Cerrar</button>

</div>
</div>



<script>
// 🔥 FORZAR HISTORIAL CONTROLADO
(function () {
    // Crear un estado falso extra
    history.pushState(null, null, location.href);
    history.pushState(null, null, location.href);

    window.onpopstate = function () {
        // 🚨 SIEMPRE ir a inicio
        window.location.replace('inicio.php');
    };
})();

// ⚡ CAMBIAR SECCIÓN SIN ROMPER EL FIX
function cambiarSeccion(sec){
    window.location.href = 'cuentas.php?sec=' + sec;
}


/* =========================
   ELIMINAR PERFIL (FIX)
========================= */
let perfilAEliminar = null;

function eliminarPerfil(id){
    perfilAEliminar = id;

    document.getElementById("tituloConfirm").innerText = "Eliminar perfil";
    document.getElementById("textoConfirm").innerText = "¿Seguro que querés eliminar este perfil?";
    document.getElementById("modalConfirm").style.display = "flex";
}

function cerrarConfirm(){
    document.getElementById("modalConfirm").style.display = "none";
}

document.getElementById("btnOk").onclick = function(){

    if(!perfilAEliminar) return;

    fetch("cuentas.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "delete_perfil=" + perfilAEliminar
    })
    .then(res => res.text())
    .then(res => {

        if(res.trim() === "ok"){
            location.reload();
        }else{
            mostrarMensaje("Error al eliminar perfil");
        }

        cerrarConfirm();
    });
};


/* =========================
   MODAL PASSWORD
========================= */
function mostrarCambioPass(){

    document.getElementById("modalPass").style.display="flex";

    document.getElementById("pass_actual").value = "";
    document.getElementById("pass_nueva").value = "";

    document.getElementById("pass_actual").focus();
}

function cerrarModal(){
    document.getElementById("modalPass").style.display="none";
}

function togglePass(id){
    let input = document.getElementById(id);
    input.type = input.type === "password" ? "text" : "password";
}


/* =========================
   CAMBIAR PASSWORD (FIX BUG)
========================= */
function cambiarPass(){

    let correo = document.getElementById("correo").value;
    let actual = document.getElementById("pass_actual").value;
    let nueva = document.getElementById("pass_nueva").value;

    fetch("cuentas.php",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"cambiar_pass=1&correo="+encodeURIComponent(correo)+"&pass_actual="+encodeURIComponent(actual)+"&pass_nueva="+encodeURIComponent(nueva)
    })
    .then(res=>res.text())
    .then(res=>{

        if(res.trim()==="ok_pass"){

            mostrarMensaje("✔ Contraseña actualizada correctamente");

            document.getElementById("pass_actual").value = "";
            document.getElementById("pass_nueva").value = "";

            cerrarModal();

        }else if(res.trim()==="correo_incorrecto"){
            mostrarMensaje("El correo no coincide con tu cuenta");

        }else if(res.trim()==="pass_incorrecta"){
            mostrarMensaje("La contraseña actual es incorrecta");

        }else if(res.trim()==="no_autorizado"){
            mostrarMensaje("No tenés permiso para cambiar la contraseña");

        }else{
            mostrarMensaje("Ocurrió un error");
        }

    });
}


/* =========================
   MENSAJES
========================= */
function mostrarMensaje(texto){
    document.getElementById("msgTexto").innerText = texto;
    document.getElementById("modalMsg").style.display = "flex";
}

function cerrarMsg(){
    document.getElementById("modalMsg").style.display = "none";
}
</script>




</body>
</html>
