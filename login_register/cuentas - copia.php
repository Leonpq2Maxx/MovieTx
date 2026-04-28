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
SELECT id, name, email, password, foto, max_perfiles, paid_until, auto_renew
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

    if($esPerfil){
        echo "no_autorizado";
        exit;
    }

    $idEliminar = intval($_POST['delete_perfil']);

    $stmtFoto = $conn->prepare("SELECT foto FROM perfiles WHERE id=? AND user_id=?");
    $stmtFoto->bind_param("ii", $idEliminar, $userId);
    $stmtFoto->execute();
    $resFoto = $stmtFoto->get_result()->fetch_assoc();

    if($resFoto){

        $rutaFoto = "uploads/perfiles/" . $resFoto['foto'];

        if(!empty($resFoto['foto']) && $resFoto['foto'] !== "default.png" && file_exists($rutaFoto)){
            unlink($rutaFoto);
        }

        $del = $conn->prepare("DELETE FROM perfiles WHERE id=? AND user_id=?");
        $del->bind_param("ii",$idEliminar,$userId);

        echo $del->execute() ? "ok" : "error";
    } else {
        echo "error";
    }

    exit;
}

/* =========================
   CAMBIAR CONTRASEÑA
========================= */
if(isset($_POST['cambiar_pass'])){

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
   ❌ CANCELAR PLAN (FIX REAL)
========================= */
if(isset($_POST['cancelar_plan'])){

    if($esPerfil){
        echo "no_autorizado";
        exit;
    }

    // 🔥 NO BORRA paid_until → mantiene días
    $stmt = $conn->prepare("UPDATE users SET auto_renew=0 WHERE id=?");
    $stmt->bind_param("i", $userId);

    if($stmt->execute()){
        echo "ok";
    } else {
        echo "error";
    }

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

/* =========================
   🔥 TABS MODERNAS
========================= */
.top-tabs{
    position:sticky;
    top:0;
    z-index:20;
    background:rgba(10,10,10,0.95);
    backdrop-filter:blur(10px);
    padding:10px 0;
}

/* CONTENEDOR */
.tabs-wrapper{
    display:flex;
    position:relative;
    max-width:500px;
    margin:auto;
    background:#1a1a1a;
    border-radius:50px;
    padding:5px;
    box-shadow:0 0 15px rgba(0,0,0,0.6);
}

/* INDICADOR DESLIZANTE */
.tab-indicator{
    position:absolute;
    top:5px;
    left:5px;
    width:50%;
    height:calc(100% - 10px);
    background:linear-gradient(135deg,#e50914,#ff3c3c);
    border-radius:50px;
    transition:0.35s cubic-bezier(.77,0,.18,1);
}

/* TAB */
.tab{
    flex:1;
    text-align:center;
    cursor:pointer;
    padding:10px;
    z-index:2;
    color:#aaa;
    font-weight:600;
    transition:0.3s;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:2px;
}

/* ICONO */
.tab span{
    font-size:18px;
}

/* TEXTO */
.tab p{
    margin:0;
    font-size:13px;
}

/* ACTIVO */
.tab.active{
    color:#fff;
}

/* HOVER */
.tab:hover{
    color:#fff;
    transform:scale(1.05);
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

/* =========================
   🔥 PLAN PREMIUM
========================= */
.plan-card{
    background:linear-gradient(145deg,#141414,#1c1c1c);
    border-radius:20px;
    padding:25px;
    box-shadow:0 10px 40px rgba(0,0,0,0.6);
    max-width:600px;
    margin:auto;
    animation:fadeIn 0.4s ease;
}

/* HEADER */
.plan-header{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
}

.plan-header img{
    width:70px;
    height:70px;
    border-radius:50%;
    border:2px solid #e50914;
}

/* ESTADO */
.plan-status{
    text-align:center;
    padding:10px;
    border-radius:10px;
    font-weight:bold;
    margin-bottom:15px;
}

.plan-status.activo{
    background:#0f5132;
    color:#4cff88;
}

.plan-status.vencido{
    background:#5a1a1a;
    color:#ff4c4c;
}

/* PROGRESO */
.plan-progress{
    background:#333;
    height:8px;
    border-radius:10px;
    overflow:hidden;
    margin-bottom:20px;
}

.plan-progress div{
    height:100%;
    background:linear-gradient(90deg,#e50914,#ff3c3c);
    transition:0.4s;
}

/* INFO */
.plan-info{
    display:grid;
    gap:10px;
}

.plan-item{
    display:flex;
    justify-content:space-between;
    background:#1f1f1f;
    padding:12px;
    border-radius:10px;
}

/* BOTONES */
.plan-actions{
    margin-top:20px;
    display:flex;
    flex-direction:column;
    gap:10px;
}

/* BOTON GLOW */
.btn-glow{
    background:linear-gradient(135deg,#e50914,#ff3c3c);
    border:none;
    padding:12px;
    border-radius:10px;
    color:#fff;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
}

.btn-glow:hover{
    transform:scale(1.05);
}

/* BOTON OUTLINE */
.btn-outline{
    background:transparent;
    border:1px solid #e50914;
    padding:12px;
    border-radius:10px;
    color:#fff;
    cursor:pointer;
}

.btn-outline:hover{
    background:#e50914;
}

/* ANIMACION */
@keyframes fadeIn{
    from{opacity:0; transform:translateY(10px);}
    to{opacity:1; transform:translateY(0);}
}

/* 🔥 CUENTA PRO */
.cuenta-card{
    animation:fadeIn 0.4s ease;
}

/* HEADER */
.cuenta-header{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:20px;
}

.cuenta-header img{
    width:80px;
    height:80px;
    border-radius:50%;
    border:3px solid #e50914;
}

.cuenta-header h2{
    margin:0;
    font-size:20px;
}

.cuenta-header p{
    margin:2px 0;
    color:#aaa;
}

.tag{
    background:#e50914;
    padding:3px 8px;
    border-radius:6px;
    font-size:12px;
}

/* STATS */
.cuenta-stats{
    display:flex;
    justify-content:space-between;
    background:#1f1f1f;
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
}

/* TITULO */
.titulo-seccion{
    margin-bottom:10px;
}

/* GRID PERFIL */
.perfiles-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
    gap:15px;
}

/* CARD PERFIL */
.perfil-card{
    background:#1a1a1a;
    padding:12px;
    border-radius:14px;
    text-align:center;
    position:relative;
    cursor:pointer;
    transition:0.3s;
}

.perfil-card:hover{
    transform:scale(1.05);
    background:#222;
}

.perfil-card img{
    width:70px;
    height:70px;
    border-radius:50%;
    margin-bottom:8px;
    border:2px solid #e50914;
}

.perfil-card p{
    margin:0;
    font-size:14px;
}

/* BOTON ELIMINAR */
.perfil-card button{
    position:absolute;
    top:6px;
    right:6px;
    width:24px;
    height:24px;
    border-radius:50%;
    background:#000;
    border:none;
    color:#fff;
    font-size:14px;
    font-weight:bold;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:0.2s;
}

.perfil-card button:hover{
    background:#e50914;
    transform:scale(1.1);
}

/* PRINCIPAL */
.perfil-card.main{
    border:2px solid #e50914;
}

/* AGREGAR */
.perfil-card.add{
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    font-size:20px;
    color:#aaa;
}

.perfil-card.add div{
    font-size:30px;
}

/* 📱 MOBILE */
@media (max-width:600px){
    .cuenta-header{
        flex-direction:column;
        text-align:center;
    }

    .cuenta-header img{
        width:70px;
        height:70px;
    }
}
</style>
</head>

<body>

<div class="top-tabs">

    <div class="tabs-wrapper">

        <div class="tab-indicator" id="tabIndicator"></div>

        <div 
        class="tab <?= $seccion=='cuenta'?'active':'' ?>" 
        onclick="cambiarSeccion('cuenta', this)">
            <span>👤</span>
            <p>Cuenta</p>
        </div>

        <div 
        class="tab <?= $seccion=='plan'?'active':'' ?>" 
        onclick="cambiarSeccion('plan', this)">
            <span>💳</span>
            <p>Plan</p>
        </div>

    </div>

</div>


<div class="container">

<?php if($seccion=="cuenta"): ?>

<div class="card cuenta-card">

<!-- HEADER -->
<div class="cuenta-header">
    <img src="<?= htmlspecialchars($foto) ?>" onerror="this.src='uploads/usuarios/default.png'">
    
    <div>
        <h2><?= htmlspecialchars($nombre) ?> <span class="tag">Principal</span></h2>
        <p><?= htmlspecialchars($email) ?></p>
    </div>
</div>

<!-- INFO RÁPIDA -->
<div class="cuenta-stats">
    <div>
        <span>👥 Perfiles</span>
        <b><?= count($perfiles)+1 ?>/<?= $maxPerfiles ?></b>
    </div>
</div>

<h3 class="titulo-seccion">Perfiles</h3>

<div class="perfiles-grid">

<!-- PERFIL PRINCIPAL -->
<div class="perfil-card main">
    <img src="<?= htmlspecialchars($foto) ?>">
    <p><?= htmlspecialchars($nombre) ?></p>
    <span>Principal</span>
</div>

<?php foreach($perfiles as $p): 
$fotoPerfil = !empty($p['foto']) 
? 'uploads/perfiles/'.$p['foto'] 
: 'uploads/perfiles/default.png';
?>

<div class="perfil-card">
    <img src="<?= htmlspecialchars($fotoPerfil) ?>">
    <p><?= htmlspecialchars($p['nombre']) ?></p>

    <?php if(!$esPerfil): ?>
    <button onclick="eliminarPerfil(<?= $p['id'] ?>)">✕</button>
    <?php endif; ?>
</div>

<?php endforeach; ?>

<!-- AGREGAR PERFIL -->
<?php if($totalReal < $maxPerfiles): ?>
<div class="perfil-card add" onclick="location.href='crear_perfil.php'">
    <div>+</div>
    <p>Agregar</p>
</div>
<?php endif; ?>

</div>

</div>
<?php endif; ?>

<?php if($seccion=="plan"): 

$hoy = date("Y-m-d");
$activo = (!empty($user['paid_until']) && $user['paid_until'] >= $hoy);

$diasRestantes = 0;
if($activo){
    $diasRestantes = ceil((strtotime($user['paid_until']) - time()) / 86400);
}

// 🔥 progreso (30 días base)
$progreso = $activo ? max(0, min(100, ($diasRestantes / 30) * 100)) : 0;
?>

<div class="plan-card">

    <!-- HEADER -->
    <div class="plan-header">
        <img src="<?= htmlspecialchars($foto) ?>">
        
        <div>
            <h2><?= htmlspecialchars($nombre) ?></h2>
            <p><?= htmlspecialchars($email) ?></p>
        </div>
    </div>

    <!-- ESTADO -->
    <div class="plan-status <?= $activo ? 'activo' : 'vencido' ?>">
        <?= $activo ? "✔ Plan Activo" : "⚠ Plan Vencido" ?>
    </div>

    <!-- BARRA PROGRESO -->
    <div class="plan-progress">
        <div style="width:<?= $progreso ?>%"></div>
    </div>

    <!-- INFO -->
    <div class="plan-info">

        <div class="plan-item">
            <span>👥 Perfiles</span>
            <b><?= $maxPerfiles ?></b>
        </div>

        <div class="plan-item">
            <span>📅 Expira</span>
            <b><?= $expira ?></b>
        </div>

        <div class="plan-item">
            <span>⏳ Días restantes</span>
            <b><?= $activo ? $diasRestantes : 0 ?></b>
        </div>

        <button class="btn-outline" onclick="cancelarPlan()">
    ❌ Cancelar plan
</button>

    </div>

    <!-- ACCIONES -->
    <?php if(!$esPerfil): ?>
    <div class="plan-actions">

        <button class="btn-glow" onclick="mostrarCambioPass()">
            🔐 Cambiar contraseña
        </button>

        <button class="btn-outline" onclick="location.href='pago.php'">
            💳 Renovar plan
        </button>

    </div>
    <?php endif; ?>

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
// 🔥 CONTROL REAL DEL BOTÓN ATRÁS (FIX DEFINITIVO)
(function () {

    // Reemplaza el estado actual (NO agrega múltiples)
    history.replaceState({page: "cuentas"}, "", location.href);

    window.addEventListener("popstate", function () {
        // Siempre salir a inicio
        window.location.href = "inicio.php";
    });

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
   🔥 TAB INDICATOR
========================= */
function moverIndicador(element){
    const indicator = document.getElementById("tabIndicator");
    const rect = element.getBoundingClientRect();
    const parent = element.parentElement.getBoundingClientRect();

    indicator.style.width = rect.width + "px";
    indicator.style.left = (rect.left - parent.left) + "px";
}

// 🔥 MODIFICAMOS TU FUNCIÓN
function cambiarSeccion(sec, el){
    window.location.href = 'cuentas.php?sec=' + sec;
}

// 🔥 AL CARGAR
window.addEventListener("load", () => {
    const active = document.querySelector(".tab.active");
    if(active){
        moverIndicador(active);
    }
});

function cancelarPlan(){

    document.getElementById("tituloConfirm").innerText = "Cancelar plan";
    document.getElementById("textoConfirm").innerText = "¿Seguro que querés cancelar tu suscripción? Perderás el acceso inmediatamente.";
    document.getElementById("modalConfirm").style.display = "flex";

    document.getElementById("btnOk").onclick = function(){

        fetch("cuentas.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "cancelar_plan=1"
        })
        .then(res => res.text())
        .then(res => {

            if(res.trim() === "ok"){
                location.reload();
            }else if(res.trim() === "no_autorizado"){
                mostrarMensaje("No tenés permiso para hacer esto");
            }else{
                mostrarMensaje("Error al cancelar plan");
            }

            cerrarConfirm();
        });

    };
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
