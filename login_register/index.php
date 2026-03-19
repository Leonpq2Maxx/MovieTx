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
<title>MovieTx</title>
<link rel="icon" type="image/png" href="Logo Poster MovieTx PNG/Logo MovieTx.png">
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

.expired-box {
    background: #1b1b1b;
    color: #fff;
    border: 1px solid #ff3b3b;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 14px;
}

.expired-box b {
    color: #00e5ff;
    cursor: pointer;
}

.foto-usuario {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    object-fit: cover;
    display: none; /* 👈 oculta hasta que exista foto */
    margin: 10px auto;
    border: 2px solid #444;
    background: #111;
}

.logo-box {
    text-align: center;
    margin-bottom: 10px;
}

#logoPrincipal {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    transition: 0.25s ease;
    border: 2px solid #444;
    background: #111;
}


#adminLogo {
    width: 110px;
    height: 110px;
    border-radius: 50%;      /* 🔹 hace la imagen redonda */
    object-fit: cover;       /* 🔹 evita que se deforme */
    border: 2px solid #444;  /* 🔹 borde estilo Netflix */
    background: #111;        /* 🔹 fondo oscuro si no hay imagen */
    display: block;
    margin: 0 auto 10px;     /* 🔹 centrado con margen inferior */
    transition: 0.25s ease;  /* 🔹 transición suave al cambiar imagen */
}
</style>
</head>

<body>

<div class="container">

<!-- ================= LOGIN USUARIO ================= -->
<div class="form-box show" id="login-form">
<form action="login_register.php" method="post">

<div class="logo-box">
    <img id="logoPrincipal"
         src="Logo Poster MovieTx PNG/Logo MovieTx.png"
         alt="MovieTx Logo">
</div>


<h2>Iniciar Sesión</h2>
<img id="fotoUsuario" class="foto-usuario" alt="Foto de usuario">


<?php if ($loginError): ?>
<p class="red"><?= $loginError ?></p>
<?php endif; ?>
<input type="email" id="correo" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>

<button type="submit" name="login">Ingresar</button>


<p>
Dont have an account?
<a href="registro.php" onclick="showForm('register-form')">Registrarse</a>
</p>

<button type="button" onclick="showForm('admin-login-form')">
Administrador
</button>

</form>
</div>

<?php if (isset($_GET['expired'])): ?>
<div class="expired-box">
    <strong>⚠️ Cuenta expirada</strong><br><br>
    Su cuenta de usuario expiró.<br>
    Para reactivarla debe abonar <b>$2500</b>.<br><br>

    Comuníquese al:<br>
    <b onclick="copyPhone()">3518175037</b>
</div>
<?php endif; ?>


<!-- ================= REGISTRO USUARIO ================= -->
<!-- en caso de querer activr esto para el registro
<div class="form-box" id="register-form">
<form action="login_register.php" method="post">

<div class="logo-box">
    <img src="Logo Poster MovieTx PNG/Logo MovieTx.png" alt="MovieTx Logo">
</div>

<h2>Registro Usuario</h2>

<input type="text" name="name" placeholder="Nombre completo" required>
<input type="email" name="email" placeholder="Correo electrónico"autocomplete="off"  required>
<input type="password" name="password" placeholder="Contraseña" autocomplete="new-password" required>

<button type="submit" name="register">
Registrarse
</button>

<p>
<a href="#" onclick="showForm('login-form')">Volver</a>
</p>

</form>
</div>
-->


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
    <img id="adminLogo"
         src="Logo Poster MovieTx PNG/Logo MovieTx.png"
         alt="MovieTx Logo">
</div>

<h2>Administrador</h2>

<?php if ($loginError): ?>
<p class="red"><?= $loginError ?></p>
<?php endif; ?>

<input type="email" id="adminCorreo" name="email" placeholder="Correo electrónico" autocomplete="off" required>
<input type="password" name="password" placeholder="Contraseña" autocomplete="new-password" required>

<input type="password" id="adminKey" name="admin_key"
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

<!--QUITAR ESTO SI QUIERES QUE FUNCIONE EL REGISTRO-->
<script>
(function () {
    // Forzar siempre estado actual
    history.pushState(null, "", location.href);

    window.onpopstate = function () {
        history.pushState(null, "", location.href);
        window.location.replace("index.php");
    };

    // Detectar páginas restauradas
    window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            window.location.replace("index.php");
        }
    });
})();

const correoInput = document.getElementById("correo");
const logo = document.getElementById("logoPrincipal");

const LOGO_DEFAULT = "Logo Poster MovieTx PNG/Logo MovieTx.png";

correoInput.addEventListener("input", function () {

  // Si no escribió nada → volver al logo
  if (this.value.length < 3) {
    logo.src = LOGO_DEFAULT;
    return;
  }

  fetch("buscar_foto.php?correo=" + encodeURIComponent(this.value))
    .then(r => r.json())
    .then(data => {

      if (data.foto && data.foto !== "default.png") {
        logo.src = data.foto; // 🔥 reemplaza logo por foto
      } else {
        logo.src = LOGO_DEFAULT; // vuelve al logo si no hay foto
      }

    })
    .catch(() => {
      logo.src = LOGO_DEFAULT;
    });

});



</script>

<!--FIN-->

<script>
function copyPhone() {
    navigator.clipboard.writeText("3518175037");
    alert("Número copiado: 3518175037");
}
</script>

    

<script>
function showAdminLoading() {
    const box = document.getElementById('admin-loading');
    if (box) {
        box.style.display = 'block';
    }
}
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const adminCorreo = document.getElementById("adminCorreo");
    const adminLogo   = document.getElementById("adminLogo");
    const adminKey    = document.getElementById("adminKey");
    const LOGO_DEFAULT = "Logo Poster MovieTx PNG/Logo MovieTx.png";

    let debounceTimeout;

    adminCorreo.addEventListener("input", function () {
        clearTimeout(debounceTimeout);

        if (this.value.length < 3) {
            adminLogo.src = LOGO_DEFAULT;
            adminKey.style.display = "block";
            return;
        }

        debounceTimeout = setTimeout(() => {
            fetch("buscar_foto.php?correo=" + encodeURIComponent(this.value))
                .then(r => r.json())
                .then(data => {
                    // FOTO
                    if (data.foto && data.foto !== "default.png") {
                        adminLogo.src = data.foto; // deja que el navegador use caché
                    } else {
                        adminLogo.src = LOGO_DEFAULT;
                    }

                    // TIPO DE ADMIN
                    adminKey.style.display = (data.admin_level === "normal") ? "none" : "block";
                })
                .catch(() => {
                    adminLogo.src = LOGO_DEFAULT;
                    adminKey.style.display = "block";
                });
        }, 250); // espera 250ms después de que el usuario deja de escribir
    });
});
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
