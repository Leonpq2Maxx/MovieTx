<?php
session_start();
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $password = $_POST['password'];

    // VALIDACIONES
    if (empty($name) || empty($email) || empty($telefono) || empty($password)) {
        $_SESSION['register_error'] = "Todos los campos son obligatorios";
        header("Location: registro.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Correo inválido";
        header("Location: registro.php");
        exit();
    }

    // Verificar email existente
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $_SESSION['register_error'] = "El correo ya está registrado";
        header("Location: registro.php");
        exit();
    }

    // Crear usuario
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $createdByAdmin = 1; // ID del admin principal

$stmt = $conn->prepare("
INSERT INTO users 
(name,email,password,telefono,role,status,created_by,created_by_admin,created_at)
VALUES (?,?,?,?, 'user','pending','self', ?, NOW())
");

$stmt->bind_param("ssssi", $name, $email, $hash, $telefono, $createdByAdmin);

    if ($stmt->execute()) {

        // 🔥 MENSAJE PARA LOGIN
        $_SESSION['success'] = "Cuenta creada correctamente. Iniciá sesión";

        // 🔥 REDIRECCIÓN REAL (esto es lo que te faltaba)
        header("Location: index.php");
        exit();

    } else {
        $_SESSION['register_error'] = "Error al registrar: " . $stmt->error;
        header("Location: registro.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registro - MovieTx</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    font-family:'Segoe UI',Arial;
    background:linear-gradient(135deg,#0b0b0b,#111);
    color:#fff;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
    padding:15px;
}

/* CAJA */
.box{
    background:#141414;
    padding:25px;
    border-radius:20px;
    width:100%;
    max-width:420px;
    text-align:center;
    box-shadow:0 10px 40px rgba(0,0,0,0.8);
    animation:fadeIn 0.5s ease;
}

/* ANIMACIÓN */
@keyframes fadeIn{
    from{opacity:0; transform:translateY(20px);}
    to{opacity:1; transform:translateY(0);}
}

/* LOGO */
.logo{
    width:90px;
    height:90px;
    border-radius:50%;
    margin-bottom:10px;
    border:2px solid #333;
}

/* TITULO */
h2{
    margin-bottom:15px;
}

/* INFO PLAN */
.info-plan{
    background:#1c1c1c;
    padding:15px;
    border-radius:12px;
    margin-bottom:15px;
    font-size:14px;
    border:1px solid #2a2a2a;
}

.precio{
    font-size:18px;
    font-weight:bold;
    color:#e50914;
}

.pago{
    margin-top:8px;
    color:#ccc;
}

.nota{
    margin-top:10px;
    font-size:13px;
    color:#aaa;
}

/* INPUTS */
input{
    width:100%;
    padding:14px;
    margin:8px 0;
    border:none;
    border-radius:10px;
    background:#222;
    color:white;
    font-size:15px;
}

input:focus{
    outline:none;
    box-shadow:0 0 0 2px #e50914;
}

/* BOTON */
button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:10px;
    background:#e50914;
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
    font-size:16px;
}

button:hover{
    background:#ff1f1f;
    transform:scale(1.02);
}

/* MENSAJES */
.msg{
    margin:10px 0;
    font-size:14px;
}

.error{
    color:#ff4d4d;
}

/* LINK */
a{
    color:#aaa;
    font-size:14px;
}

/* 📱 MOBILE */
@media (max-width:480px){

    .box{
        padding:20px;
        border-radius:15px;
    }

    .logo{
        width:70px;
        height:70px;
    }

    .precio{
        font-size:16px;
    }

    input{
        padding:12px;
    }

    button{
        padding:12px;
        font-size:15px;
    }
}

/* 💻 TABLET */
@media (min-width:481px) and (max-width:900px){
    .box{
        max-width:500px;
    }
}

/* 🖥️ PC */
@media (min-width:901px){
    .box{
        max-width:420px;
    }
}
</style>
</head>

<body>

<div class="box">

<img src="Logo Poster MovieTx PNG/Logo MovieTx.png" class="logo">

<h2>Crear cuenta</h2>

<div class="info-plan">
    <p class="precio">💳 Precio: $2500 / mes</p>

    <p class="pago">
        Formas de pago:<br>
        Mercado Pago • Naranja X • Transferencia
    </p>

    <p class="nota">
        ⚠️ Una vez registrado, el administrador se comunicará con vos.<br>
        Deberás enviar el comprobante de pago para activar tu cuenta.
    </p>
</div>

<?php if(isset($_SESSION['register_error'])): ?>
<div class="msg error"><?= $_SESSION['register_error'] ?></div>
<?php unset($_SESSION['register_error']); endif; ?>

<form method="POST">

<input type="text" name="name" placeholder="Nombre completo" required>
<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="text" name="telefono" placeholder="Número de teléfono" required>
<input type="password" name="password" placeholder="Contraseña" required>

<button type="submit">Registrarse</button>

</form>

<br>

<a href="index.php">Volver al login</a>

</div>

</body>
</html>