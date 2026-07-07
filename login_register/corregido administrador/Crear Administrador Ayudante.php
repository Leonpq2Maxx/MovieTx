<?php
session_start();
require_once 'config.php';

// 🔒 SOLO ADMIN PRINCIPAL
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminId    = (int)$_SESSION['id'];
$adminEmail = $_SESSION['email'] ?? 'Desconocido';
$adminLevel = $_SESSION['admin_level'];

// ❌ SOLO SUPER PUEDE CREAR AYUDANTES
if ($adminLevel !== 'super') {

    $_SESSION['msg'] = "No tienes permisos para acceder a esta sección";
    $_SESSION['msg_type'] = "error";

    header("Location: Administrador.php");
    exit();
}

// =========================
// ERRORES
// =========================
$errors = [
    'name' => '',
    'email' => '',
    'password' => '',
    'telefono' => '',
    'quota' => '',
    'profile_quota' => ''
];

// =========================
// CREAR ADMIN AYUDANTE
// =========================
if (isset($_POST['create_helper'])) {

    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $passwordPlain = $_POST['password'] ?? '';
    $telefono      = trim($_POST['telefono'] ?? '');

    $quota         = (int)($_POST['quota'] ?? 0);
    $profile_quota = (int)($_POST['profile_quota'] ?? 0);

    $hasError = false;

    /* =========================
       VALIDACIONES
    ========================= */

    if ($name === '') {
        $errors['name'] = "El nombre es obligatorio";
        $hasError = true;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email inválido";
        $hasError = true;
    }

    if ($passwordPlain === '') {
        $errors['password'] = "La contraseña es obligatoria";
        $hasError = true;
    }

    $telefono = preg_replace('/\D/', '', $telefono);

    if (strlen($telefono) < 8) {
        $errors['telefono'] = "Teléfono inválido";
        $hasError = true;
    }

    if ($quota <= 0) {
        $errors['quota'] = "Cupos inválidos";
        $hasError = true;
    }

    if ($profile_quota <= 0) {
        $errors['profile_quota'] = "Perfiles inválidos";
        $hasError = true;
    }

    /* =========================
       EMAIL DUPLICADO
    ========================= */

    $stmt = $conn->prepare("
        SELECT id
        FROM admins
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        $errors['email'] = "El email ya existe";
        $hasError = true;
    }

    /* =========================
       CREAR ADMIN
    ========================= */

    if (!$hasError) {

        $password = password_hash(
            $passwordPlain,
            PASSWORD_DEFAULT
        );

        $rutaFoto = '';

        $carpeta = "uploads/admins/";

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] === 0
        ) {

            $ext = strtolower(
                pathinfo(
                    $_FILES['foto']['name'],
                    PATHINFO_EXTENSION
                )
            );

            $nombreArchivo =
                time() . "_" .
                uniqid() . "." .
                $ext;

            $rutaFoto =
                $carpeta .
                $nombreArchivo;

            move_uploaded_file(
                $_FILES['foto']['tmp_name'],
                $rutaFoto
            );
        }

        $stmt = $conn->prepare("
            INSERT INTO admins
            (
                name,
                email,
                password,
                role,
                status,
                created_by,
                created_at,
                admin_level,
                paid,
                admin_approved,
                payment_status,
                created_by_admin,
                commission,
                is_online,
                foto,
                user_quota,
                max_perfiles,
                telefono
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'admin',
                'active',
                'admin',
                NOW(),
                'normal',
                'yes',
                'yes',
                'approved',
                ?,
                0.00,
                0,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->bind_param(
            "sssisiis",
            $name,
            $email,
            $password,
            $adminId,
            $rutaFoto,
            $quota,
            $profile_quota,
            $telefono
        );

        if ($stmt->execute()) {

            $_SESSION['msg'] =
                "Administrador ayudante creado correctamente";

            $_SESSION['msg_type'] = "success";

            header("Location: Administrador.php");
            exit();
        }

        $_SESSION['msg'] =
            "Error al crear administrador: " .
            $stmt->error;

        $_SESSION['msg_type'] = "error";

        header("Location: Crear Administrador Ayudante.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo -512x512.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Crear Administrador Ayudante | MovieTx</title>

<style>

/* =========================================
MOVIETX BASE
========================================= */

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
--glass:blur(22px);
}

*{
margin:0;
padding:0;
box-sizing:border-box;
-webkit-tap-highlight-color:transparent;
}

html{
scroll-behavior:smooth;
}

body{
font-family:"Segoe UI",system-ui,sans-serif;

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

color:var(--text);
min-height:100vh;

padding:
max(20px,env(safe-area-inset-top))
15px
max(20px,env(safe-area-inset-bottom));

overflow-x:hidden;
}

/* =========================================
CONTAINER
========================================= */

.admin-container{
width:100%;
max-width:1200px;
margin:auto;
animation:fadeIn .4s ease;
}

@keyframes fadeIn{
from{
opacity:0;
transform:translateY(15px);
}
to{
opacity:1;
transform:none;
}
}

/* =========================================
LOGO
========================================= */

.logo{
text-align:center;
font-size:clamp(2rem,4vw,3rem);
font-weight:900;
letter-spacing:2px;
color:var(--primary);

margin-bottom:25px;

text-shadow:
0 0 15px rgba(255,0,60,.4),
0 0 30px rgba(255,0,60,.25);
}

/* =========================================
CARD
========================================= */

.profile-card{
position:relative;

background:var(--card);

backdrop-filter:var(--glass);
-webkit-backdrop-filter:var(--glass);

border:1px solid var(--border);

border-radius:28px;

padding:35px;

box-shadow:var(--shadow);

overflow:hidden;
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

/* =========================================
TEXTOS
========================================= */

.role{
text-align:center;
font-size:.85rem;
letter-spacing:2px;
text-transform:uppercase;
color:#9f9f9f;
margin-bottom:10px;
}

.admin-name{
text-align:center;
font-size:clamp(1.6rem,3vw,2.2rem);
font-weight:800;
}

.admin-email{
text-align:center;
margin-top:8px;
margin-bottom:30px;
color:var(--text2);
}

/* =========================================
AVATAR
========================================= */

.avatar-area{
position:relative;

width:150px;
height:150px;

margin:auto;
margin-bottom:25px;
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

.upload-photo{
position:absolute;
right:5px;
bottom:5px;

width:42px;
height:42px;

display:flex;
align-items:center;
justify-content:center;

border-radius:50%;

background:var(--primary);

cursor:pointer;

font-size:18px;

box-shadow:
0 0 20px rgba(255,0,60,.4);

transition:.25s;
}

.upload-photo:hover{
transform:scale(1.08);
}

/* =========================================
FORMULARIO
========================================= */

.form-grid{
display:grid;
grid-template-columns:repeat(2,1fr);
gap:18px;
}

.input-group{
display:flex;
flex-direction:column;
gap:8px;
}

.input-group label{
font-size:14px;
font-weight:700;
color:#d5d5d5;
}

.input-group input{
width:100%;
height:58px;

background:
rgba(255,255,255,.04);

border:1px solid
rgba(255,255,255,.08);

border-radius:16px;

padding:0 16px;

font-size:15px;
color:white;

outline:none;

transition:.25s;
}

.input-group input:focus{
border-color:
rgba(255,0,60,.5);

box-shadow:
0 0 20px rgba(255,0,60,.18);
}

/* =========================================
PASSWORD
========================================= */

.password-box{
position:relative;
}

.password-box input{
padding-right:60px;
}

.password-box button{
position:absolute;
top:50%;
right:15px;

transform:translateY(-50%);

background:none;
border:none;

font-size:20px;
color:white;

cursor:pointer;
}

/* =========================================
BOTON
========================================= */

.create-btn{
margin-top:30px;

width:100%;
height:60px;

border:none;
border-radius:18px;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary-hover)
);

color:white;

font-size:16px;
font-weight:800;

cursor:pointer;

transition:.3s;
}

.create-btn:hover{
transform:translateY(-3px);

box-shadow:
0 15px 35px rgba(255,0,60,.35);
}

/* =========================================
ANDROID PEQUEÑOS
320px - 480px
========================================= */

@media screen and (max-width:480px){

.profile-card{
padding:18px;
border-radius:22px;
}

.logo{
font-size:28px;
}

.admin-name{
font-size:22px;
}

.admin-email{
font-size:13px;
}

.form-grid{
grid-template-columns:1fr;
gap:14px;
}

.avatar-area{
width:110px;
height:110px;
}

.input-group input{
height:52px;
font-size:14px;
}

.create-btn{
height:55px;
font-size:14px;
}

}

/* =========================================
IPHONES
481px - 767px
========================================= */

@media screen and (min-width:481px)
and (max-width:767px){

.profile-card{
padding:24px;
}

.form-grid{
grid-template-columns:1fr;
}

.avatar-area{
width:130px;
height:130px;
}

}

/* =========================================
TABLETS
768px - 1024px
========================================= */

@media screen and (min-width:768px)
and (max-width:1024px){

.profile-card{
padding:30px;
}

.form-grid{
grid-template-columns:1fr 1fr;
}

.avatar-area{
width:140px;
height:140px;
}

}

/* =========================================
PC Y NOTEBOOK
1025px - 1440px
========================================= */

@media screen and (min-width:1025px){

.profile-card{
padding:40px;
}

.form-grid{
grid-template-columns:repeat(2,1fr);
}

}

/* =========================================
MONITORES GRANDES
1441px+
========================================= */

@media screen and (min-width:1441px){

.admin-container{
max-width:1400px;
}

.profile-card{
padding:50px;
}

.logo{
font-size:3.4rem;
}

.admin-name{
font-size:2.5rem;
}

.avatar-area{
width:180px;
height:180px;
}

.input-group input{
height:62px;
font-size:16px;
}

.create-btn{
height:65px;
font-size:17px;
}

}

.toast{
    position:fixed;
    top:20px;
    right:20px;
    padding:15px 20px;
    border-radius:12px;
    font-weight:700;
    z-index:99999;
    animation:slideIn .4s ease;
    backdrop-filter: blur(10px);
}

.toast.success{
    background:rgba(0,255,120,.15);
    border:1px solid rgba(0,255,120,.4);
    color:#00ff8a;
}

.toast.error{
    background:rgba(255,0,60,.15);
    border:1px solid rgba(255,0,60,.4);
    color:#ff003c;
}

@keyframes slideIn{
    from{
        transform:translateX(120px);
        opacity:0;
    }
    to{
        transform:translateX(0);
        opacity:1;
    }
}
</style>
</head>
<body>

    <?php if (isset($_SESSION['msg'])): ?>
    <div class="toast <?= $_SESSION['msg_type'] ?>">
        <?= $_SESSION['msg'] ?>
    </div>

    <?php
        unset($_SESSION['msg']);
        unset($_SESSION['msg_type']);
    ?>
    <?php endif; ?>

<div class="admin-container">

<div class="logo">
MOVIETX
</div>

<div class="profile-card">

<div class="role">
<?= htmlspecialchars($adminEmail) ?>
</div>

<h1 class="admin-name">
Crear Administrador Ayudante
</h1>

<p class="admin-email">
Complete los datos del nuevo administrador ayudante.
</p>

<!-- FORMULARIO -->
<form id="crearAdminForm" method="POST" enctype="multipart/form-data">

<!-- FOTO PERFIL -->
<div class="avatar-area">

    <img
        id="previewFoto"
        src="https://cdn-icons-png.flaticon.com/512/149/149071.png">

    <label for="fotoInput" class="upload-photo">
        📷
    </label>

    <input
        type="file"
        id="fotoInput"
        name="foto"
        accept="image/*"
        hidden>

</div>

<div class="form-grid">

    <!-- NOMBRE -->
    <div class="input-group">
        <label>Nombre</label>
        <input type="text" name="name" required>

        <?php if (!empty($errors['name'])): ?>
            <small style="color:red;">
                <?= $errors['name'] ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- CORREO (CORREGIDO Y AGREGADO) -->
    <div class="input-group">
        <label>Correo</label>
        <input type="email" name="email" required placeholder="ejemplo@gmail.com">

        <?php if (!empty($errors['email'])): ?>
            <small style="color:red;">
                <?= $errors['email'] ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- CONTRASEÑA -->
    <div class="input-group">
        <label>Contraseña</label>

        <div class="password-box">
            <input type="password" id="password" name="password" required>
            <button type="button" id="togglePass">👁</button>
        </div>

        <?php if (!empty($errors['password'])): ?>
            <small style="color:red;">
                <?= $errors['password'] ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- CUPOS -->
    <div class="input-group">
        <label>Cupos</label>
        <input type="number" name="quota" required>

        <?php if (!empty($errors['quota'])): ?>
            <small style="color:red;">
                <?= $errors['quota'] ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- PERFILES ASIGNADOS -->
    <div class="input-group">
        <label>Perfiles Asignados</label>
        <input 
            type="number"
            name="profile_quota"
            id="profile_quota"
            min="1"
            max="50"
            required
            placeholder="Ej: 1, 5, 10, 20">

        <?php if (!empty($errors['profile_quota'])): ?>
            <small style="color:red;">
                <?= $errors['profile_quota'] ?>
            </small>
        <?php endif; ?>
    </div>

    <!-- TELEFONO -->
    <div class="input-group">
        <label>Teléfono</label>
        <input type="text" name="telefono" required>

        <?php if (!empty($errors['telefono'])): ?>
            <small style="color:red;">
                <?= $errors['telefono'] ?>
            </small>
        <?php endif; ?>
    </div>

</div>

<!-- BOTÓN SUBMIT -->
<button type="submit" name="create_helper" class="create-btn">
    CREAR ADMINISTRADOR
</button>

</form>

</div>

</div>

<!-- SCRIPT PREVIEW FOTO -->
<script>
const fotoInput = document.getElementById("fotoInput");
const previewFoto = document.getElementById("previewFoto");

fotoInput.addEventListener("change", function () {

    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        previewFoto.src = e.target.result;
    };

    reader.readAsDataURL(file);
});
</script>

<!-- SCRIPT PASSWORD -->
<script>
const password = document.getElementById("password");
const togglePass = document.getElementById("togglePass");

togglePass.addEventListener("click", () => {

    if (password.type === "password") {
        password.type = "text";
        togglePass.innerHTML = "🙈";
    } else {
        password.type = "password";
        togglePass.innerHTML = "👁";
    }

});
</script>

<!-- TOAST AUTO HIDE -->
<script>
document.addEventListener("DOMContentLoaded", () => {

    const toast = document.querySelector(".toast");

    if (toast) {

        setTimeout(() => {
            toast.style.transition = "0.5s ease";
            toast.style.opacity = "0";
            toast.style.transform = "translateX(100px)";

            setTimeout(() => {
                toast.remove();
            }, 500);

        }, 10000);

    }

});
</script>

</body>
</html>