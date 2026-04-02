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
    * {
    box-sizing: border-box;
}

/* ===== BASE ===== */
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: radial-gradient(circle at top, #141414, #000);
    color: #fff;

    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;

    padding: 15px;
}


/* ===== CONTENEDOR ===== */
.container {
    width: 100%;
    max-width: 420px;
    padding: 15px;
}

/* ===== FORM BOX ===== */
.form-box {
    display: none;
    width: 100%;
    background: rgba(20, 20, 20, 0.9);
    backdrop-filter: blur(15px);
    border-radius: 18px;
    padding: 25px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.7);
    transition: all 0.3s ease;
}

.form-box.show {
    display: block;
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px);}
    to { opacity: 1; transform: translateY(0);}
}

/* ===== LOGO ===== */
.logo-box {
    text-align: center;
    margin-bottom: 15px;
}

#logoPrincipal,
#adminLogo {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    border: 2px solid #333;
    transition: 0.3s;
}

/* ===== TITULOS ===== */
h2 {
    text-align: center;
    margin-bottom: 15px;
    font-size: 22px;
}

/* ===== INPUTS ===== */
input {
    width: 100%;
    padding: 14px;
    margin: 8px 0;
    border-radius: 10px;
    border: 1px solid #333;
    background: #111;
    color: #fff;
    font-size: 16px;
    outline: none;
    transition: 0.25s;
}

input:focus {
    border-color: #e50914;
    box-shadow: 0 0 10px rgba(229,9,20,0.7);
}

/* ===== BOTONES ===== */
button {
    width: 100%;
    padding: 14px;
    margin-top: 10px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(45deg, #e50914, #ff2a2a);
    color: #fff;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    transform: scale(1.04);
    box-shadow: 0 0 15px rgba(255,0,0,0.7);
}

/* BOTÓN SECUNDARIO */
button[type="button"] {
    background: #222;
}

button[type="button"]:hover {
    background: #333;
}

/* ===== LINKS ===== */
a {
    color: #00e5ff;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

/* ===== MENSAJES ===== */
.green {
    color: #00ff99;
    text-align: center;
}

.red {
    color: #ff4d4d;
    text-align: center;
}

/* ===== CAJA EXPIRADA ===== */
.expired-box {
    background: rgba(255,0,0,0.15);
    border: 1px solid #ff3b3b;
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    margin-top: 15px;
    font-size: 14px;
}

/* ===== FOTO USUARIO ===== */
.foto-usuario {
    width: 75px;
    height: 75px;
    border-radius: 50%;
    margin: 10px auto;
    display: block;
    border: 2px solid #333;
}

/* ===== TEXTO ===== */
p {
    text-align: center;
    font-size: 14px;
    opacity: 0.85;
}

/* ===== RESPONSIVE ===== */

/* 📱 CELULARES PEQUEÑOS */
@media (max-width: 360px) {
    .form-box {
        padding: 18px;
    }

    input, button {
        padding: 12px;
        font-size: 14px;
    }

    h2 {
        font-size: 20px;
    }
}

/* 📱 MÓVILES (iPhone / Android) */
@media (max-width: 768px) {
    body {
        align-items: center;   /* 🔥 centrado vertical */
        justify-content: center;
        padding: 20px 10px;
    }

    .container {
        padding: 0;
    }
}

/* 💻 TABLETS / PC */
@media (min-width: 769px) {
    .container {
        max-width: 450px;
    }

    .form-box {
        padding: 30px;
    }
}

/* 🖥️ PANTALLAS GRANDES */
@media (min-width: 1200px) {
    body {
        background: linear-gradient(to bottom, #000, #111);
    }

    .container {
        max-width: 500px;
    }
}

/* 🍎 SOPORTE IPHONE NOTCH */
@supports (padding: env(safe-area-inset-top)) {
    body {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
    }
}


/* CONTENEDOR DEL PASSWORD */
.password-box {
    position: relative;
    width: 100%;
}

/* INPUT CON ESPACIO PARA EL ICONO */
.password-box input {
    width: 100%;
    padding-right: 45px; /* espacio para el icono */
}

/* ICONO */
.toggle-pass {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
}

/* SVG RESPONSIVE */
.toggle-pass svg {
    width: 22px;
    height: 22px;
    color: #aaa;
    transition: 0.2s;
}

.toggle-pass:hover svg {
    color: #fff;
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


<?php if ($loginError): ?>
<p class="red"><?= $loginError ?></p>
<?php endif; ?>
<input type="email" id="correo" name="email" placeholder="Correo electrónico" required>
<div class="password-box">
    <input type="password" name="password" placeholder="Contraseña" required>
    
    <span class="toggle-pass">
        <svg viewBox="0 0 24 24">
            <path fill="currentColor"
            d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5c-1.7-4.4-6-7.5-11-7.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5z"/>
        </svg>
    </span>
</div>

<button type="submit" name="login">Ingresar</button>


<p style="text-align:center; font-size:14px; opacity:0.8;">
¿No tienes cuenta?
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

<input type="hidden" name="login_type" value="admin">

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
<div class="password-box">
    <input type="password" name="password" placeholder="Contraseña" required>
    
    <span class="toggle-pass">
        <svg viewBox="0 0 24 24">
            <path fill="currentColor"
            d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5c-1.7-4.4-6-7.5-11-7.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5z"/>
        </svg>
    </span>
</div>


<div class="password-box">
    <input type="password" id="adminKey" name="admin_key"
    placeholder="Clave única (solo administrador principal)">

    <span class="toggle-pass">
        <svg viewBox="0 0 24 24">
            <path fill="currentColor"
            d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5c-1.7-4.4-6-7.5-11-7.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5z"/>
        </svg>
    </span>
</div>


<button type="submit" name="login">
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
<div class="password-box">
    <input type="password" id="adminKey" name="admin_key"
    placeholder="Clave única (solo administrador principal)">
    <span class="toggle-pass">👁️</span>
</div>

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

<script>
document.querySelectorAll("input").forEach(input => {
    input.addEventListener("focus", () => {
        input.style.transform = "scale(1.02)";
    });
    input.addEventListener("blur", () => {
        input.style.transform = "scale(1)";
    });
});
</script>



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
document.querySelectorAll(".toggle-pass").forEach(btn => {

    btn.addEventListener("click", function() {

        const input = this.parentElement.querySelector("input");
        const svg = this.querySelector("svg");

        if (input.type === "password") {
            input.type = "text";

            svg.innerHTML = `
            <path fill="currentColor"
            d="M3 3l18 18M10.58 10.58A3 3 0 0012 15a3 3 0 002.42-4.42M9.88 5.09A9.77 9.77 0 0112 4.5c5 0 9.3 3.1 11 7.5a11.8 11.8 0 01-2.07 3.36M6.1 6.1A11.8 11.8 0 001 12c1.7 4.4 6 7.5 11 7.5a9.77 9.77 0 003.91-.8"/>
            `;
        } else {
            input.type = "password";

            svg.innerHTML = `
            <path fill="currentColor"
            d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5c-1.7-4.4-6-7.5-11-7.5zm0 12.5c-2.8 0-5-2.2-5-5s2.2-5 5-5 5 2.2 5 5-2.2 5-5 5z"/>
            `;
        }

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
