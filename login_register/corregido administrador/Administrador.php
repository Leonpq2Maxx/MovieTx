<?php
session_start();
require_once "config.php";

/* ==========================
   EVITAR CACHE
========================== */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* ==========================
   VALIDAR ADMIN
========================== */
if (
    !isset($_SESSION['id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
){
    header("Location: index.php");
    exit();
}

$adminId = (int)$_SESSION['id'];


/* ==========================
   DATOS ADMIN
========================== */
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        email,
        foto,
        admin_level,
        user_quota,
        max_perfiles,
        status
    FROM admins
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $adminId);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    session_destroy();
    header("Location: index.php");
    exit();
}

/* ==========================
   VARIABLES
========================== */

$adminName   = $admin['name'];
$adminEmail  = $admin['email'];

$adminFoto = !empty($admin['foto'])
    ? $admin['foto']
    : 'https://i.imgur.com/4Z7YB7Q.png';

$adminLevel = $admin['admin_level'];

$isSuper  = ($adminLevel === 'super');
$isHelper = ($adminLevel === 'normal');

$quota        = (int)$admin['user_quota'];
$maxPerfiles  = (int)$admin['max_perfiles'];

/* ==========================
   CONTADORES
========================== */

// Usuarios
$totalUsuarios = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM users
")->fetch_assoc()['total'] ?? 0);

// Admins
$totalAdmins = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM admins
    WHERE role='admin'
")->fetch_assoc()['total'] ?? 0);

// Usuarios activos
$usuariosActivos = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE status='active'
")->fetch_assoc()['total'] ?? 0);

// Admins activos
$adminsActivos = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM admins
    WHERE role='admin'
    AND status='active'
")->fetch_assoc()['total'] ?? 0);

// Suspendidos
$totalSuspendidos = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE status='suspended'
")->fetch_assoc()['total'] ?? 0);

// Vencidos
$totalVencidos = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role='user'
    AND paid_until IS NOT NULL
    AND paid_until < CURDATE()
")->fetch_assoc()['total'] ?? 0);

// Pendientes
$totalPendientes = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE status='pending'
")->fetch_assoc()['total'] ?? 0);

$conn->query("
UPDATE admins
SET
    is_online = 1,
    last_ping = NOW()
WHERE id=".(int)$admin['id']
);

$conn->query("
UPDATE admins
SET is_online = 0
WHERE last_ping < NOW() - INTERVAL 2 MINUTE
");

/* ==========================
   TEXTO DEL ROL
========================== */

$roleText = $isSuper
    ? "Administrador Principal"
    : "Administrador Ayudante";
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>MovieTx Admin</title>
<link rel="icon" type="image/png" href="Logo/Logo Nuevo -512x512.png">
<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

:root{
--primary:#ff003c;
--primary-hover:#ff295f;
--bg:#080808;
--card:rgba(255,255,255,.05);
--card-hover:rgba(255,255,255,.08);
--border:rgba(255,255,255,.08);
--text:#fff;
--text2:#bdbdbd;
--shadow:0 15px 40px rgba(0,0,0,.45);
--glass:blur(18px);
}

*{
margin:0;
padding:0;
box-sizing:border-box;
-webkit-tap-highlight-color:transparent;
}

html{
scroll-behavior:smooth;
background:#080808;
}

body{
font-family:
"Segoe UI",
system-ui,
sans-serif;

background-color:#080808;

background:
radial-gradient(
circle at top,
rgba(255,0,60,.18),
transparent 35%
),
linear-gradient(
180deg,
#050505 0%,
#0d0d0d 40%,
#161616 100%
);

background-attachment:fixed;

color:var(--text);

min-height:100vh;

padding:
max(15px,env(safe-area-inset-top))
15px
max(15px,env(safe-area-inset-bottom));

overflow-x:hidden;

transform:translateZ(0);
-webkit-transform:translateZ(0);

backface-visibility:hidden;
-webkit-backface-visibility:hidden;
}

/* ==========================
CONTAINER
========================== */

.admin-container{
width:100%;
max-width:1300px;
margin:auto;

animation:fadeIn .35s ease;

transform:translateZ(0);
will-change:transform;
}

@keyframes fadeIn{
from{
opacity:0;
transform:translateY(10px);
}
to{
opacity:1;
transform:none;
}
}

/* ==========================
LOGO
========================== */

.logo{
text-align:center;
font-size:clamp(2rem,4vw,3.3rem);
font-weight:900;
letter-spacing:2px;

color:var(--primary);

margin-bottom:25px;

text-shadow:
0 0 15px rgba(255,0,60,.4),
0 0 30px rgba(255,0,60,.25);
}

/* ==========================
PROFILE CARD
========================== */

.profile-card{
position:relative;

background:var(--card);

backdrop-filter:var(--glass);
-webkit-backdrop-filter:var(--glass);

border:1px solid var(--border);

border-radius:28px;

padding:35px;

overflow:hidden;

box-shadow:var(--shadow);

transform:translateZ(0);
will-change:transform;
}

.profile-card::before{
content:"";

position:absolute;
top:0;
left:0;

width:100%;
height:4px;

background:
linear-gradient(
90deg,
transparent,
var(--primary),
transparent
);
}

/* ==========================
AVATAR
========================== */

.avatar-area{
position:relative;

width:clamp(110px,18vw,165px);
height:clamp(110px,18vw,165px);

margin:auto;
margin-bottom:20px;
}

.avatar-area img{
width:100%;
height:100%;

object-fit:cover;

border-radius:50%;

border:4px solid var(--primary);

box-shadow:
0 0 30px rgba(255,0,60,.35);
}

/* ==========================
TEXTOS
========================== */

.role{
text-align:center;
font-size:.85rem;
letter-spacing:2px;
text-transform:uppercase;
color:#a5a5a5;
margin-bottom:10px;
}

.admin-name{
text-align:center;
font-size:clamp(1.5rem,3vw,2rem);
font-weight:800;
}

.admin-email{
text-align:center;
margin-top:6px;
margin-bottom:25px;
color:var(--text2);
word-break:break-word;
}

/* ==========================
HELPER
========================== */

.helper-boxes{
display:grid;
grid-template-columns:1fr 1fr;
gap:12px;
margin-top:20px;
}

.helper-card{
background:var(--card);
border:1px solid var(--border);
padding:18px;
border-radius:18px;
text-align:center;

transition:
transform .25s ease,
background .25s ease,
border-color .25s ease;

cursor:pointer;
user-select:none;

transform:translateZ(0);
}

.helper-card:hover{
transform:translateY(-4px);
border-color:rgba(255,0,60,.4);
background:rgba(255,255,255,.08);
}

.helper-card:active{
transform:scale(.97);
}

.helper-card h3{
font-size:2rem;
color:var(--primary);
}

/* ==========================
ACCIONES
========================== */

.top-actions{
display:grid;
grid-template-columns:1fr 1fr;
gap:12px;
margin-top:25px;
}

.action-btn{
height:55px;

border:none;
border-radius:16px;

font-size:15px;
font-weight:700;

cursor:pointer;

transition:
transform .25s ease,
opacity .25s ease;
}

.refresh{
background:#1d1d1d;
color:white;
}

.logout{
background:var(--primary);
color:white;
}

.action-btn:hover{
transform:translateY(-3px);
}

/* ==========================
STATS
========================== */

.stats{
margin-top:25px;

display:grid;
grid-template-columns:repeat(3,1fr);

gap:12px;
}

.stat{
background:var(--card);

border:1px solid var(--border);

border-radius:18px;

padding:18px;

text-align:center;

transition:
transform .25s ease,
border-color .25s ease;
}

.stat:hover{
transform:translateY(-4px);
border-color:rgba(255,0,60,.4);
}

.stat-number{
font-size:2rem;
font-weight:800;
color:var(--primary);
}

.stat-title{
font-size:.85rem;
color:var(--text2);
}

/* ==========================
MENU
========================== */

.menu-grid{
margin-top:25px;

display:grid;

grid-template-columns:
repeat(4,1fr);

gap:12px;
}

.menu-card{
background:var(--card);

border:1px solid var(--border);

border-radius:18px;

padding:22px;

min-height:120px;

display:flex;
flex-direction:column;
justify-content:center;
align-items:center;

cursor:pointer;

transition:
transform .25s ease,
background .25s ease,
border-color .25s ease;

transform:translateZ(0);
}

.menu-card:hover{
transform:
translateY(-5px)
scale(1.02);

background:var(--card-hover);

border-color:
rgba(255,0,60,.4);
}

.menu-card i{
font-size:28px;
margin-bottom:10px;
color:var(--primary);
}

.menu-card span{
font-weight:700;
text-align:center;
}

.featured{
background:
linear-gradient(
135deg,
rgba(255,0,60,.22),
rgba(255,0,60,.04)
);
}

.featured i{
color:white;
}

.menu-link{
text-decoration:none;
color:inherit;
display:block;
}

.menu-card,
.menu-card:hover{
text-decoration:none;
color:white;
}

/* ==========================
TOAST
========================== */

.toast{
position:fixed;
top:20px;
right:20px;

min-width:260px;
max-width:320px;

padding:16px 18px;

border-radius:14px;

font-weight:700;
font-size:14px;

z-index:99999;

backdrop-filter:blur(18px);
-webkit-backdrop-filter:blur(18px);

box-shadow:0 15px 40px rgba(0,0,0,.35);

animation:toastIn .35s ease;
}

.toast.success{
background:rgba(0,255,120,.12);
border:1px solid rgba(0,255,120,.35);
color:#00ff8a;
}

.toast.error{
background:rgba(255,0,60,.12);
border:1px solid rgba(255,0,60,.35);
color:#ff003c;
}

@keyframes toastIn{
from{
transform:translateX(120px);
opacity:0;
}
to{
transform:translateX(0);
opacity:1;
}
}

/* ==========================
FOOTER
========================== */

.footer{
margin-top:30px;
text-align:center;
font-size:13px;
color:#8d8d8d;
}

/* ==========================
ANDROID + IPHONE
========================== */

@media(max-width:768px){

.profile-card{
padding:20px;
}

.menu-grid{
grid-template-columns:1fr 1fr;
}

.stats{
grid-template-columns:repeat(3,1fr);
}

.top-actions{
grid-template-columns:1fr 1fr;
}

.helper-boxes{
grid-template-columns:1fr 1fr;
}

.menu-card{
min-height:95px;
padding:15px;
}

.menu-card i{
font-size:22px;
}

.menu-card span{
font-size:13px;
}

.stat{
padding:14px;
}

.stat-number{
font-size:20px;
}

.stat-title{
font-size:12px;
}

.action-btn{
height:50px;
font-size:14px;
}

}

/* ==========================
IPHONE MINI
========================== */

@media(max-width:430px){

.logo{
font-size:28px;
}

.admin-name{
font-size:20px;
}

.menu-card{
min-height:85px;
}

.menu-card span{
font-size:12px;
}

}

/* ==========================
TABLETS
========================== */

@media(min-width:769px) and (max-width:1024px){

.menu-grid{
grid-template-columns:
repeat(3,1fr);
}

}

/* ==========================
PC GRANDE
========================== */

@media(min-width:1400px){

.menu-grid{
grid-template-columns:
repeat(5,1fr);
}

}

</style>
</head>
<body>

<?php if (!empty($_SESSION['mensaje_admin'])): ?>

<div class="toast success">
    <?= htmlspecialchars($_SESSION['mensaje_admin']) ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const toast = document.querySelector(".toast");

    if(toast){

        setTimeout(()=>{

            toast.style.opacity="0";
            toast.style.transform="translateX(100px)";
            toast.style.transition=".5s ease";

            setTimeout(()=>{
                toast.remove();
            },500);

        },4000);

    }

});
</script>

<?php
unset($_SESSION['mensaje_admin']);
?>

<?php endif; ?>

<?php if (isset($_SESSION['msg'])): ?>
    <div class="toast <?= $_SESSION['msg_type'] ?>">
        <?= $_SESSION['msg'] ?>
    </div>

    <?php
        unset($_SESSION['msg']);
        unset($_SESSION['msg_type']);
    ?>
<?php endif; ?>

<div class="logo">
MovieTx
</div>

<div class="profile-card">

<div class="avatar-area">
<img src="<?= htmlspecialchars($adminFoto) ?>">
</div>

<div class="role" id="roleText">
<?= htmlspecialchars($roleText) ?>
</div>

<div class="admin-name">
<?= htmlspecialchars($adminName) ?>
</div>

<div class="admin-email">
<?= htmlspecialchars($adminEmail) ?>
</div>

<!-- SOLO AYUDANTES -->

<div class="helper-boxes" id="helperBoxes">

<?php if($adminLevel === 'normal'): ?>

<div class="helper-card">
    <h3><?= $quota ?></h3>
    <span>Cupos Asignados</span>
</div>

<div class="helper-card">
    <h3><?= $maxPerfiles ?></h3>
    <span>Perfiles Asignados</span>
</div>

<?php endif; ?>

<div class="helper-card" onclick="location.href='activos_usuarios.php'">
    <h3><?= $usuariosActivos ?></h3>
<span>Activos Usuarios</span>
</div>

<?php if ($isSuper): ?>
    <div class="helper-card" onclick="location.href='activos_admin.php'">
        <h3><?= $adminsActivos ?></h3>
        <span>Activos Admin</span>
    </div>
<?php endif; ?>

<div class="helper-card" onclick="location.href='suspendidos.php'">
    <h3><?= $totalSuspendidos ?></h3>
<span>Suspendidos</span>
</div>

<div class="helper-card" onclick="location.href='vencidos.php'">
    <h3><?= $totalVencidos ?></h3>
<span>Vencidos</span>
</div>
</div>

<div class="top-actions">

<button class="action-btn refresh"
onclick="location.reload()">
🔄 Refrescar
</button>

<button class="action-btn logout"
onclick="location.href='logout.php'">
🚪 Salir
</button>

</div>

<div class="stats">

<div class="stat">
<div class="stat-number"><?= $totalUsuarios ?></div>
<div class="stat-title">Usuarios</div>
</div>

<div class="stat">
<div class="stat-number"><?= $totalAdmins ?></div>
<div class="stat-title">Admins</div>
</div>

<div class="stat">
<div class="stat-number"><?= $totalPendientes ?></div>
<div class="stat-title">Pendientes</div>
</div>

</div>

<div class="menu-grid">

<!-- DESTACADOS -->

<a href="Crear Usuario.php" class="menu-card featured">
    <i class="fas fa-user-plus"></i>
    <span>Crear Usuario</span>
</a>

<?php if($isSuper): ?>
<a href="Crear Administrador Ayudante.php" class="menu-card featured">
    <i class="fas fa-user-shield"></i>
    <span>Crear Admin</span>
</a>
<?php endif; ?>
<!-- ADMIN -->

<a href="Editar Administrador.php" class="menu-link">
    <div class="menu-card">
        <i class="fas fa-user-gear"></i>
        <span>Editar Administrador</span>
    </div>
</a>

<a href="Usuarios.php" class="menu-link">
    <div class="menu-card">
        <i class="fas fa-users"></i>
        <span>Usuarios</span>
    </div>
</a>

<div class="menu-card">
    <i class="fas fa-user-plus"></i>
    <span>Registro Web</span>
</div>

<?php if($isSuper): ?>
<a href="Admins.php" class="menu-card">
    <i class="fas fa-user-shield"></i>
    <span>Admins</span>
</a>
<?php endif; ?>

<!-- RESTO -->

<a href="Solicitudes.php" class="menu-link">
    <div class="menu-card">
        <i class="fas fa-envelope"></i>
        <span>Solicitudes</span>
    </div>
</a>

<?php if($isSuper): ?>

<div class="menu-card">
    <i class="fas fa-crown"></i>
    <span>Principal</span>
</div>

<?php endif; ?>

</div>

<div class="footer">
MovieTx Admin v2026
</div>

</div>

</div>

<script>

/*
MODO DEMO

SUPER:
setRole('super')

AYUDANTE:
setRole('helper')
*/

function setRole(role){

const roleText =
document.getElementById('roleText');

const helperBoxes =
document.getElementById('helperBoxes');

const stats =
document.querySelector('.stats');

if(role === 'helper'){

roleText.innerHTML =
'Administrador Ayudante';

helperBoxes.style.display='grid';

stats.innerHTML=`

<div class="stat">
<div class="stat-number">45</div>
<div class="stat-title">Usuarios</div>
</div>

<div class="stat">
<div class="stat-number">2</div>
<div class="stat-title">Pendientes</div>
</div>

`;

}

}

</script>

</body>
</html>