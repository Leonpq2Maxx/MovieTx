<?php
session_start();
require_once "config.php";

if(!isset($_SESSION['id'])){
    header("Location:index.php");
    exit;
}

$userId = (int)$_SESSION['id'];

/* =========================================================
   👤 USUARIO
========================================================= */

$stmtUser = $conn->prepare("
SELECT
    name,
    foto,
    max_perfiles,
    kids
FROM users
WHERE id = ?
LIMIT 1
");

$stmtUser->bind_param("i",$userId);
$stmtUser->execute();


$user = $stmtUser
->get_result()
->fetch_assoc();

if(!$user){
    session_destroy();
    header("Location:index.php");
    exit;
}

$nombreUsuario =
$user['name'] ?? 'Usuario';

$fotoUsuario =
!empty($user['foto'])
? $user['foto']
: 'uploads/usuarios/default.png';

$maxPerfiles =
(int)($user['max_perfiles'] ?? 1);

$kidsDisponible =
(int)($user['kids'] ?? 0);

/* ======================
      KIDS
   ======================*/
$stmtKids = $conn->prepare("
SELECT id
FROM perfiles
WHERE user_id = ?
AND tipo = 'kids'
LIMIT 1
");

$stmtKids->bind_param("i", $userId);
$stmtKids->execute();

$tienePerfilKids =
$stmtKids->get_result()->num_rows > 0;

/* =========================================================
   👥 PERFILES
========================================================= */

$stmt = $conn->prepare("
SELECT
    id,
    nombre,
    foto,
    tipo
FROM perfiles
WHERE user_id = ?
");

$stmt->bind_param("i",$userId);
$stmt->execute();

$result =
$stmt->get_result();

$perfiles =
$result->fetch_all(MYSQLI_ASSOC);

$totalPerfiles =
count($perfiles) + 1;

/* =========================================================
   🎯 SELECCIONAR PERFIL
========================================================= */

if(isset($_GET['perfil_id'])){

    $perfilId =
    (int)$_GET['perfil_id'];

    /* PERFIL PRINCIPAL */

    if($perfilId === 0){

        unset($_SESSION['perfil_id']);
        unset($_SESSION['perfil_name']);

        header("Location:inicio.php");
        exit;
    }

    /* VALIDAR PERFIL */

    $stmtPerfil = $conn->prepare("
SELECT
    id,
    nombre,
    tipo
FROM perfiles
WHERE id = ?
AND user_id = ?
LIMIT 1
");

    $stmtPerfil->bind_param(
        "ii",
        $perfilId,
        $userId
    );

    $stmtPerfil->execute();

    $perfil =
    $stmtPerfil
    ->get_result()
    ->fetch_assoc();

    if($perfil){

    $_SESSION['perfil_id'] = $perfil['id'];
    $_SESSION['perfil_name'] = $perfil['nombre'];
    $_SESSION['perfil_tipo'] = $perfil['tipo'];

    if($perfil['tipo'] === 'kids'){
        header("Location:Inicio-Kids.php");
    }else{
        header("Location:inicio.php");
    }

    exit;
}
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0, viewport-fit=cover"
>

<title>MovieTx • Perfiles</title>

<link
href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet"
>

<link
rel="icon"
type="image/png"
href="Logo/Logo Nuevo -512x512.png"
>

<style>

/* =========================================================
   🌌 ROOT SYSTEM
========================================================= */

:root{

--bg:#040816;
--bg2:#09111f;

--card:
rgba(14,22,38,.88);

--card-hover:
rgba(18,28,48,.96);

--line:
rgba(255,255,255,.06);

--text:#ffffff;

--muted:#97a8c8;

--primary:#7b61ff;
--primary2:#9f7dff;

--success:#39d7a0;

--danger:#ff5b7d;

--radius:30px;

--shadow:
0 20px 55px rgba(0,0,0,.45);

}

/* =========================================================
   🌌 RESET
========================================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

html{
scroll-behavior:smooth;
-webkit-text-size-adjust:100%;
}

body{

font-family:'Inter',sans-serif;

min-height:100vh;

overflow-x:hidden;

background:

radial-gradient(
circle at top left,
rgba(123,97,255,.18),
transparent 28%
),

radial-gradient(
circle at bottom right,
rgba(57,215,160,.10),
transparent 32%
),

linear-gradient(
180deg,
var(--bg),
var(--bg2),
#040816
);

color:var(--text);

position:relative;
}

/* =========================================================
   ✨ CINEMATIC FX
========================================================= */

body::before,
body::after{

content:"";

position:fixed;

pointer-events:none;

z-index:-1;

border-radius:50%;

filter:blur(90px);

opacity:.18;
}

body::before{

width:300px;
height:300px;

background:#7b61ff;

top:-120px;
left:-120px;
}

body::after{

width:340px;
height:340px;

background:#39d7a0;

right:-140px;
bottom:-140px;
}

/* =========================================================
   ✨ SCROLLBAR
========================================================= */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{

background:#28385f;

border-radius:999px;
}

/* =========================================================
   🔥 HEADER
========================================================= */

.header{

position:sticky;

top:0;

z-index:100;

display:flex;
align-items:center;
justify-content:space-between;

padding:18px 28px;

background:
rgba(5,8,22,.72);

backdrop-filter:blur(20px);

border-bottom:1px solid rgba(255,255,255,.05);
}

/* =========================================================
   🌟 LOGO
========================================================= */

.logo{

display:flex;
align-items:center;

gap:14px;
}

.logo img{

width:56px;
height:56px;

border-radius:18px;

object-fit:cover;

box-shadow:
0 10px 24px rgba(0,0,0,.30);
}

.logo h1{

font-size:28px;
font-weight:800;

letter-spacing:.3px;
}

/* =========================================================
   🚪 LOGOUT
========================================================= */

.logout-btn{

height:50px;

padding:0 22px;

border:none;
outline:none;

cursor:pointer;

border-radius:16px;

font-family:inherit;

font-size:14px;
font-weight:700;

color:#fff;

background:
linear-gradient(
135deg,
#ff5b7d,
#ff7b5c
);

transition:
transform .25s ease,
box-shadow .25s ease,
opacity .25s ease;
}

.logout-btn:hover{

transform:translateY(-2px);

box-shadow:
0 14px 30px rgba(255,91,125,.24);
}

.logout-btn:active{
transform:scale(.97);
}

/* =========================================================
   📦 MAIN
========================================================= */

.main{

width:100%;
max-width:1220px;

margin:auto;

padding:
30px
24px
60px;
}

/* =========================================================
   🎯 HERO
========================================================= */

.hero{

position:relative;

overflow:hidden;

padding:38px 32px;

margin-bottom:38px;

border-radius:34px;

background:

linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:1px solid var(--line);

backdrop-filter:blur(22px);

box-shadow:var(--shadow);

text-align:center;
}

.hero::before{

content:"";

position:absolute;

top:-120px;
right:-120px;

width:240px;
height:240px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(123,97,255,.20),
transparent 70%
);
}

.hero-avatar{

position:relative;

width:110px;
height:110px;

margin:auto auto 20px;

border-radius:32px;

overflow:hidden;

border:3px solid rgba(255,255,255,.08);

box-shadow:
0 16px 40px rgba(123,97,255,.22);
}

.hero-avatar img{

width:100%;
height:100%;

object-fit:cover;
}

.hero h2{

font-size:38px;
font-weight:800;

margin-bottom:12px;
}

.hero p{

max-width:580px;

margin:auto;

font-size:14px;

line-height:1.7;

color:var(--muted);
}

/* =========================================================
   👥 GRID
========================================================= */

.perfiles-grid{

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(190px,1fr));

gap:22px;
}

/* =========================================================
   🎬 PROFILE CARD
========================================================= */

.perfil-card{

position:relative;

overflow:hidden;

padding:16px;

border-radius:28px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:1px solid var(--line);

backdrop-filter:blur(18px);

cursor:pointer;

transition:
transform .28s ease,
box-shadow .28s ease,
border .28s ease,
background .28s ease;
}

.perfil-card:hover{

transform:translateY(-5px);

background:var(--card-hover);

border-color:
rgba(123,97,255,.30);

box-shadow:
0 18px 45px rgba(0,0,0,.28);
}

.perfil-card::before{

content:"";

position:absolute;

top:-90px;
right:-90px;

width:180px;
height:180px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(123,97,255,.14),
transparent 70%
);
}

/* =========================================================
   🖼 PROFILE IMAGE
========================================================= */

.perfil-image{

position:relative;

width:100%;

aspect-ratio:1/1;

overflow:hidden;

border-radius:22px;

margin-bottom:14px;
}

.perfil-image img{

width:100%;
height:100%;

object-fit:cover;

transition:
transform .35s ease;
}

.perfil-card:hover .perfil-image img{

transform:scale(1.05);
}

/* =========================================================
   🟢 STATUS
========================================================= */

.status{

position:absolute;

top:12px;
right:12px;

width:13px;
height:13px;

border-radius:50%;

background:var(--success);

box-shadow:
0 0 14px rgba(57,215,160,.6);
}

/* =========================================================
   📝 TEXT
========================================================= */

.perfil-card h3{

font-size:18px;
font-weight:700;

margin-bottom:6px;
}

.perfil-card p{

font-size:13px;

line-height:1.6;

color:var(--muted);
}

/* =========================================================
   ➕ ADD PROFILE
========================================================= */

.add-card{

display:flex;
flex-direction:column;
align-items:center;
justify-content:center;

min-height:270px;

text-align:center;
}

.add-icon{

width:84px;
height:84px;

display:flex;
align-items:center;
justify-content:center;

margin-bottom:18px;

border-radius:24px;

font-size:48px;
font-weight:300;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

box-shadow:
0 16px 35px rgba(123,97,255,.24);
}

.add-card h3{

font-size:20px;

margin-bottom:8px;
}

.add-card p{

max-width:210px;

font-size:13px;

line-height:1.7;

color:var(--muted);
}

/* =========================================================
   📱 ANDROID
========================================================= */

@media screen
and (max-width:920px)
and (pointer:coarse)
and (-webkit-min-device-pixel-ratio:1){

.header{

padding:16px;
}

.logo img{

width:52px;
height:52px;
}

.logo h1{

font-size:23px;
}

.logout-btn{

height:46px;

padding:0 18px;

font-size:13px;
}

.main{

padding:
18px
16px
40px;
}

.hero{

padding:28px 20px;

border-radius:28px;
}

.hero-avatar{

width:96px;
height:96px;

border-radius:28px;
}

.hero h2{

font-size:30px;
}

.perfiles-grid{

grid-template-columns:
repeat(2,1fr);

gap:16px;
}

.perfil-card{

padding:14px;

border-radius:22px;
}

.perfil-image{

border-radius:18px;
}

.add-card{

min-height:220px;
}

.add-icon{

width:72px;
height:72px;

font-size:40px;
}

}

/* =========================================================
   🍎 IPHONE
========================================================= */

@media only screen
and (max-width:430px)
and (-webkit-touch-callout:none){

body{

padding-top:
env(safe-area-inset-top);

padding-bottom:
env(safe-area-inset-bottom);
}

.header{

padding:
14px
14px;
}

.logo{

gap:10px;
}

.logo img{

width:46px;
height:46px;

border-radius:16px;
}

.logo h1{

font-size:20px;
}

.logout-btn{

height:42px;

padding:0 15px;

font-size:12px;

border-radius:14px;
}

.main{

padding:
12px
12px
24px;
}

.hero{

padding:24px 16px;

border-radius:24px;

margin-bottom:28px;
}

.hero-avatar{

width:84px;
height:84px;

border-radius:24px;

margin-bottom:16px;
}

.hero h2{

font-size:25px;

line-height:1.15;
}

.hero p{

font-size:12px;

line-height:1.6;
}

.perfiles-grid{

grid-template-columns:
1fr 1fr;

gap:12px;
}

.perfil-card{

padding:12px;

border-radius:20px;
}

.perfil-image{

border-radius:16px;

margin-bottom:10px;
}

.perfil-card h3{

font-size:14px;
}

.perfil-card p{

font-size:11px;
}

.add-card{

min-height:190px;
}

.add-icon{

width:64px;
height:64px;

font-size:36px;

border-radius:20px;
}

.add-card h3{

font-size:16px;
}

.add-card p{

font-size:11px;
}

}

/* =========================================================
   🖥 PC / DESKTOP
========================================================= */

@media(min-width:1200px){

.main{

max-width:1180px;

padding:
34px
40px
70px;
}

.hero{

padding:34px 40px;

border-radius:36px;

margin-bottom:36px;
}

.hero-avatar{

width:105px;
height:105px;

border-radius:30px;
}

.hero h2{

font-size:40px;
}

.hero p{

font-size:14px;

max-width:600px;
}

.perfiles-grid{

grid-template-columns:
repeat(auto-fit,minmax(200px,240px));

justify-content:center;

gap:22px;
}

.perfil-card{

padding:15px;

border-radius:24px;
}

.perfil-image{

border-radius:20px;

margin-bottom:12px;
}

.perfil-card h3{

font-size:17px;
}

.perfil-card p{

font-size:12px;
}

.add-card{

min-height:250px;
}

.add-icon{

width:78px;
height:78px;

font-size:44px;

border-radius:22px;
}

}

/* =========================================================
   💻 ULTRA WIDE
========================================================= */

@media(min-width:1600px){

.main{
max-width:1320px;
}

.perfiles-grid{

grid-template-columns:
repeat(auto-fit,minmax(210px,230px));
}

}

</style>

</head>

<body>

<!-- ======================================================
     🔥 HEADER
====================================================== -->

<header class="header">

<div class="logo">

<img src="Logo/Logo Nuevo -512x512.png">

<h1>MovieTx</h1>

</div>

<button
class="logout-btn"
onclick="location.href='logout.php'"
>

Cerrar sesión

</button>

</header>

<!-- ======================================================
     📦 MAIN
====================================================== -->

<main class="main">

<!-- ======================================================
     🎯 HERO
====================================================== -->

<section class="hero">

<div class="hero-avatar">

<img src="<?= $fotoUsuario ?>">

</div>

<h2>¿Quién está viendo?</h2>

<p>

Selecciona un perfil para continuar disfrutando
de tu experiencia personalizada en MovieTx.

</p>

</section>

<!-- ======================================================
     👥 PERFILES
====================================================== -->

<section class="perfiles-grid">

<!-- PERFIL PRINCIPAL -->

<div
class="perfil-card"
onclick="location.href='perfiles.php?perfil_id=0'"
>

<div class="perfil-image">

<img src="<?= $fotoUsuario ?>">

<div class="status"></div>

</div>

<h3><?= htmlspecialchars($nombreUsuario) ?></h3>

<p>Perfil principal</p>

</div>

<!-- PERFILES EXTRA -->

<?php foreach($perfiles as $perfil):

$fotoPerfil =
!empty($perfil['foto'])
? 'uploads/perfiles/' . $perfil['foto']
: 'uploads/perfiles/default.png';

$esKids =
($perfil['tipo'] === 'kids');

?>

<div
class="perfil-card"
onclick="location.href='perfiles.php?perfil_id=<?= (int)$perfil['id'] ?>'"
>

<div class="perfil-image">

<img src="<?= $fotoPerfil ?>">

</div>

<h3>

<?= $esKids ? '👶 ' : '' ?>
<?= htmlspecialchars($perfil['nombre']) ?>

</h3>

<p>
<?= $esKids ? 'Perfil KIDS' : 'Perfil personalizado' ?>
</p>

</div>

<?php endforeach; ?>

<!-- AGREGAR PERFIL -->

<?php if($totalPerfiles < $maxPerfiles): ?>

<div
class="perfil-card add-card"
onclick="location.href='crear_perfil.php'"
>

<div class="add-icon">+</div>

<h3>Nuevo perfil</h3>

<p>

Agrega un nuevo perfil para otra experiencia
personalizada.

</p>

</div>

<?php endif; ?>
<?php if($kidsDisponible == 1 && !$tienePerfilKids): ?>

<div
class="perfil-card add-card"
onclick="location.href='crear_perfil_kids.php'"
>

<div class="add-icon">👶</div>

<h3>Crear Perfil KIDS</h3>

<p>
Crea el perfil infantil para los más pequeños.
</p>

</div>

<?php endif; ?>

</section>

</main>

</body>
</html>