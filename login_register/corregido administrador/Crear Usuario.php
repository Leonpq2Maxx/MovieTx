<?php
session_start();
require_once 'config.php';

// 🔒 EVITA CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 🔐 SOLO ADMIN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminId    = (int)$_SESSION['id'];
$adminEmail = $_SESSION['email'] ?? '';
$adminLevel = $_SESSION['admin_level'] ?? 'normal';

// =====================
// CREAR USUARIO
// =====================
if (isset($_POST['create_user'])) {

    $name        = trim($_POST['name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $passwordRaw = $_POST['password'] ?? '';
    $telefono    = trim($_POST['telefono'] ?? '');
    $plan = $_POST['plan'] ?? '';

    switch ($plan) {

    case '1':
        $maxPerfiles = 1;
        $precio = 1200;
        $kids = 0; // No incluye Kids
        break;

    case 'basico':
        $maxPerfiles = 3;
        $precio = 2500;
        $kids = 1; // Incluye Kids
        break;

    case 'estandar':
        $maxPerfiles = 5;
        $precio = 3500;
        $kids = 1;
        break;

    case 'premium':
        $maxPerfiles = 7;
        $precio = 4800;
        $kids = 1;
        break;

    default:
        $_SESSION['msg'] = "Selecciona un plan.";
        $_SESSION['msg_type'] = "error";
        header("Location: Administrador.php");
        exit();
}

    // =====================
    // VALIDACIONES
    // =====================

    if (
        empty($name) ||
        empty($email) ||
        empty($passwordRaw)
    ) {

        $_SESSION['msg'] = "Completa todos los campos";
        $_SESSION['msg_type'] = "error";

        header("Location: Administrador.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $_SESSION['msg'] = "Correo inválido";
        $_SESSION['msg_type'] = "error";

        header("Location: Administrador.php");
        exit();
    }

    if ($maxPerfiles < 1 || $maxPerfiles > 50) {

        $_SESSION['msg'] =
            "Cantidad de perfiles inválida";

        $_SESSION['msg_type'] = "error";

        header("Location: Administrador.php");
        exit();
    }

    if (
        !empty($telefono) &&
        !preg_match('/^[0-9]{10}$/', $telefono)
    ) {

        $_SESSION['msg'] =
            "Teléfono inválido (10 números)";

        $_SESSION['msg_type'] = "error";

        header("Location: Administrador.php");
        exit();
    }

    // =====================
    // PASSWORD
    // =====================

    $pass = password_hash(
        $passwordRaw,
        PASSWORD_DEFAULT
    );

    // =====================
    // FOTO
    // =====================

    $rutaFoto = null;

    $carpetaFisica =
        __DIR__ . "/uploads/usuarios/";

    $carpetaBD =
        "uploads/usuarios/";

    if (!is_dir($carpetaFisica)) {

        mkdir(
            $carpetaFisica,
            0775,
            true
        );
    }

    if (
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] === UPLOAD_ERR_OK
    ) {

        $tmp =
            $_FILES['foto']['tmp_name'];

        $nameFile =
            $_FILES['foto']['name'];

        $ext = strtolower(
            pathinfo(
                $nameFile,
                PATHINFO_EXTENSION
            )
        );

        $permitidos = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        if (in_array($ext, $permitidos)) {

            $nombreArchivo =
                "user_" .
                time() .
                "_" .
                bin2hex(random_bytes(4)) .
                "." .
                $ext;

            $rutaFisica =
                $carpetaFisica .
                $nombreArchivo;

            if (
                move_uploaded_file(
                    $tmp,
                    $rutaFisica
                )
            ) {

                $rutaFoto =
                    $carpetaBD .
                    $nombreArchivo;
            }
        }
    }

    // =====================
    // EMAIL EXISTENTE
    // =====================

    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $email
    );

    $stmt->execute();

    if (
        $stmt->get_result()->num_rows > 0
    ) {

        $_SESSION['msg'] =
            "El correo ya existe";

        $_SESSION['msg_type'] =
            "error";

        header("Location: Administrador.php");
        exit();
    }

    // =====================
    // CUPOS AYUDANTE
    // =====================

    $perfilesDisponibles = 999999;

    if ($adminLevel === 'normal') {

        $stmtQuota = $conn->prepare("
            SELECT max_perfiles
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmtQuota->bind_param(
            "i",
            $adminId
        );

        $stmtQuota->execute();

        $adminResult =
            $stmtQuota->get_result();

        if (
            $adminResult->num_rows > 0
        ) {

            $adminData =
                $adminResult->fetch_assoc();

            $perfilesDisponibles =
                (int)$adminData['max_perfiles'];

            if (
                $maxPerfiles >
                $perfilesDisponibles
            ) {

                $_SESSION['msg'] =
                    "No tienes perfiles suficientes";

                $_SESSION['msg_type'] =
                    "error";

                header(
                    "Location: Administrador.php"
                );

                exit();
            }
        }
    }

    // =====================
// CUPOS AYUDANTE (user_quota)
// =====================

if ($adminLevel === 'normal') {

    // 1. Obtener cupos actuales del admin ayudante
    $stmtQuota = $conn->prepare("
        SELECT user_quota
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");

    $stmtQuota->bind_param("i", $adminId);
    $stmtQuota->execute();

    $resQuota = $stmtQuota->get_result()->fetch_assoc();

    $currentQuota = isset($resQuota['user_quota'])
        ? (int)$resQuota['user_quota']
        : 0;

    // 2. Validar si tiene cupos
    if ($currentQuota <= 0) {

        $_SESSION['msg'] = "No tienes cupos disponibles para crear usuarios";
        $_SESSION['msg_type'] = "error";

        header("Location: Administrador.php");
        exit();
    }

    // 3. Descontar 1 cupo por usuario creado
    $updateQuota = $conn->prepare("
        UPDATE admins
        SET user_quota = user_quota - 1
        WHERE id = ?
        AND user_quota > 0
    ");

    $updateQuota->bind_param("i", $adminId);
    $updateQuota->execute();
}

    // =====================
    // ESTADO SEGÚN EL TIPO
    // DE ADMIN
    // =====================

    $statusUsuario = 'active';

    if ($adminLevel === 'normal') {
        $statusUsuario = 'suspended';
    }

    // =====================
    // CREAR USUARIO
    // =====================

    $stmt = $conn->prepare("
        INSERT INTO users
(
    name,
    email,
    password,
    telefono,
    role,
    status,
    created_by_admin,
    created_at,
    foto,
    max_perfiles,
    kids,
    plan,
    precio
)
        
VALUES
(
    ?,
    ?,
    ?,
    ?,
    'user',
    ?,
    ?,
    NOW(),
    ?,
    ?,
    ?,
    ?,
    ?
)
    ");

    $stmt->bind_param(
    "sssssisiisi",
    $name,
    $email,
    $pass,
    $telefono,
    $statusUsuario,
    $adminId,
    $rutaFoto,
    $maxPerfiles,
    $kids,
    $plan,
    $precio
);


    if (!$stmt->execute()) {

        $_SESSION['msg'] =
            "Error al crear usuario";

        $_SESSION['msg_type'] =
            "error";

        header(
            "Location: Administrador.php"
        );

        exit();
    }

    // =====================
// DESCONTAR CUPOS (CORREGIDO)
// =====================

if ($adminLevel === 'normal') {

    // restar directamente en base de datos
    $update = $conn->prepare("
        UPDATE admins
        SET max_perfiles = max_perfiles - ?
        WHERE id = ?
    ");

    $update->bind_param(
        "ii",
        $maxPerfiles,
        $adminId
    );

    $update->execute();
}

    // =====================
    // MENSAJE FINAL
    // =====================

    if ($adminLevel === 'normal') {

        $_SESSION['msg'] =
            "Usuario creado y enviado para revisión del administrador principal.";

    } else {

        $_SESSION['msg'] =
            "Usuario creado correctamente.";

    }

    $_SESSION['msg_type'] = "success";

    header("Location: Administrador.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo -512x512.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Crear Usuario | MovieTx</title>

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

.input-group select{

width:100%;
height:58px;

background:rgba(255,255,255,.04);

border:1px solid rgba(255,255,255,.08);

border-radius:16px;

padding:0 16px;

font-size:15px;

color:#fff;

outline:none;

transition:.25s;

appearance:none;
-webkit-appearance:none;
-moz-appearance:none;

cursor:pointer;

}

.input-group select:focus{

border-color:rgba(255,0,60,.5);

box-shadow:0 0 20px rgba(255,0,60,.18);

}

.input-group select option{

background:#111;
color:#fff;

}

.input-group input[readonly]{

background:rgba(255,255,255,.08);

cursor:not-allowed;

opacity:.9;

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
Crear Usuario
</h1>

<p class="admin-email">
Complete los datos del nuevo Usuario.
</p>

<!-- FORMULARIO (IMPORTANTE: TODO DENTRO) -->
<form id="crearAdminForm" method="POST" enctype="multipart/form-data">

<!-- FOTO PERFIL -->
<div class="avatar-area">

    <img
        id="previewFoto"
        src="https://cdn-icons-png.flaticon.com/512/149/149071.png">

    <label for="fotoInput" class="upload-photo">
        📷
    </label>

    <!-- 🔥 CORREGIDO: ahora SÍ está dentro del form -->
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
    </div>

    <!-- CORREO -->
    <div class="input-group">
        <label>Correo</label>
        <input type="email" name="email" required>
    </div>

    <!-- CONTRASEÑA -->
    <div class="input-group">
        <label>Contraseña</label>

        <div class="password-box">
            <input type="password" id="password" name="password" required>
            <button type="button" id="togglePass">👁</button>
        </div>
    </div>

    <div class="input-group">

    <label>Plan</label>

    <select
        id="plan"
        name="plan"
        required>

        <option value="">
            Seleccionar
        </option>

        <option value="1">
            1 Usuario
        </option>

        <option value="basico">
            Básico
        </option>

        <option value="estandar">
            Estándar
        </option>

        <option value="premium">
            Premium
        </option>

    </select>

</div>

    <!-- PERFILES ASIGNADOS -->
    <div class="input-group">
        <label>Perfiles Asignados</label>
        <input
    type="number"
    id="max_perfiles"
    name="max_perfiles"
    readonly
    value=""
    placeholder="Seleccione un plan">
    </div>

    <!-- TELEFONO -->
    <div class="input-group">
        <label>Teléfono</label>
        <input type="tel" name="telefono" required>
    </div>

</div>

<div class="input-group">
    <label>Precio</label>

    <input
    type="text"
    id="precio"
    readonly
    value=""
    placeholder="Seleccione un plan">
</div>


<!-- BOTÓN SUBMIT -->
<button type="submit" name="create_user" class="create-btn">
    CREAR USUARIO
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

<script>

const plan = document.getElementById("plan");
const perfiles = document.getElementById("max_perfiles");
const precio = document.getElementById("precio");

function actualizarPlan() {

    switch (plan.value) {

        case "1":
            perfiles.value = "1";
            precio.value = "$1200";
            break;

        case "basico":
            perfiles.value = "3";
            precio.value = "$2500";
            break;

        case "estandar":
            perfiles.value = "5";
            precio.value = "$3500";
            break;

        case "premium":
            perfiles.value = "7";
            precio.value = "$4800";
            break;

        default:
            perfiles.value = "";
            precio.value = "";
            break;
    }

}

// Actualizar al cambiar el plan
plan.addEventListener("change", actualizarPlan);

// Al cargar la página dejar todo sincronizado
document.addEventListener("DOMContentLoaded", actualizarPlan);

</script>

</body>
</html>