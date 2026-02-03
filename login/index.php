<?php
session_start();

//prueba


// 🚨 Si un admin vuelve al index, cerrar sesión automáticamente
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    session_unset();
    session_destroy();
}

//fin

/* Mensajes */
$loginError  = $_SESSION['login_error'] ?? '';
$adminSuccess = $_SESSION['admin_register_success'] ?? '';
$showAdminRegister = $_SESSION['show_admin_register'] ?? true;

/* Limpiar mensajes */
unset(
    $_SESSION['login_error'],
    $_SESSION['admin_register_success'],
    $_SESSION['show_admin_register']
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sistema de Acceso</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<link rel="stylesheet" href="style.css">

<style>
.form-box { display:none; }
.form-box.show { display:block; }
input, textarea, select {
  font-size: 16px;
}
.container { max-width:420px; margin:40px auto; }
input, button { width:100%; padding:10px; margin:8px 0; }
button { cursor:pointer; }
.green { color:green; font-weight:bold; }
.red { color:red; }
hr { margin:15px 0; }
</style>
</head>

<body>

<div class="container">

<!-- ================= LOGIN USUARIO ================= -->
<div class="form-box show" id="login-form">
<form action="login_register.php" method="post">

<div class="logo-box">
    <img src="Logo Poster MovieTx PNG/Logo MovieTx.png" alt="MovieTx Logo">
</div>

<h2>Iniciar Sesión</h2>

<?php if ($loginError): ?>
<p class="red"><?= $loginError ?></p>
<?php endif; ?>

<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>

<button type="submit" name="login">Ingresar</button>

<p>
Dont have an account?
<a href="#" onclick="showForm('register-form')">Registrarse</a>
</p>

<button type="button" onclick="showForm('admin-login-form')">
Administrador
</button>

</form>
</div>

<!-- ================= REGISTRO USUARIO ================= -->
<div class="form-box" id="register-form">
<form action="login_register.php" method="post">

<div class="logo-box">
    <img src="Logo Poster MovieTx PNG/Logo MovieTx.png" alt="MovieTx Logo">
</div>

<h2>Registro Usuario</h2>

<input type="text" name="name" placeholder="Nombre completo" required>
<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>

<button type="submit" name="register">
Registrarse
</button>

<p>
<a href="#" onclick="showForm('login-form')">Volver</a>
</p>

</form>
</div>


<?php
$adminMsg = '';
$adminClass = '';

if (isset($_SESSION['login_type']) && $_SESSION['login_type'] === 'admin') {

    if ($_SESSION['login_status'] === 'error') {
        $adminMsg = $_SESSION['login_message'];
        $adminClass = 'login-msg error';
    }

    if ($_SESSION['login_status'] === 'success') {
        $adminMsg = $_SESSION['login_message'];
        $adminClass = 'login-msg success';
    }

    // limpiar para que no se repita
    unset($_SESSION['login_status'], $_SESSION['login_message'], $_SESSION['login_type']);

    unset($_SESSION['login_status']);
unset($_SESSION['login_message']);
unset($_SESSION['login_type']);
}
?>


<!-- ================= LOGIN ADMIN ================= -->

<div class="form-box" id="admin-login-form">
<form action="login_register.php" method="post">

<div class="logo-box">
    <img src="Logo Poster MovieTx PNG/Logo MovieTx.png" alt="MovieTx Logo">
</div>

<h2>Administrador</h2>

<?php if ($loginError): ?>
<p class="red"><?= $loginError ?></p>
<?php endif; ?>

<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>

<input type="password" name="admin_key"
placeholder="Clave única (solo administrador principal)">

<!-- 🔴 FIX IMPORTANTE: name="login" -->
<button type="submit" name="login" onclick="showAdminLoading()">
Iniciar sesión
</button>

<div id="admin-loading" class="login-msg success" style="display:none;">
Verificando cuenta en la base de datos, espere un momento...
</div>


<?php if ($showAdminRegister): ?>
<hr>
<button type="button" onclick="showForm('admin-register-form')">
Registrarse como Administrador Principal
</button>
<?php endif; ?>

<p>
<a href="#" onclick="showForm('login-form')">Volver atrás</a>
</p>

</form>
</div>

<!-- ================= REGISTRO ADMIN PRINCIPAL ================= -->
<div class="form-box" id="admin-register-form">
<form action="login_register.php" method="post">

<h2>Administrador Principal</h2>

<input type="text" name="name" placeholder="Nombre completo" required>
<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>
<input type="password" name="admin_key"
placeholder="Clave única secreta" required>

<button type="submit" name="register_admin">
Registrar Administrador Principal
</button>

<?php if ($adminSuccess): ?>
<p class="green"><?= $adminSuccess ?></p>
<?php endif; ?>

<p>
<a href="#" onclick="showForm('admin-login-form')">Volver</a>
</p>

</form>
</div>

</div>


<!-- ================= JS ================= -->
    

<script>
function showAdminLoading() {
    const box = document.getElementById('admin-loading');
    if (box) {
        box.style.display = 'block';
    }
}
</script>

<script>
function showForm(id){
    document.querySelectorAll('.form-box')
        .forEach(f => f.classList.remove('show'));
    document.getElementById(id).classList.add('show');
}
</script>

</body>
</html>
