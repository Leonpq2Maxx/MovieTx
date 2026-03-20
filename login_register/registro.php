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
    background:#0b0b0b;
    color:#fff;
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}

.box{
    background:#141414;
    padding:30px;
    border-radius:20px;
    width:100%;
    max-width:420px;
    text-align:center;
    box-shadow:0 10px 40px rgba(0,0,0,0.8);
}

.logo{
    width:100px;
    height:100px;
    border-radius:50%;
    margin-bottom:10px;
    border:2px solid #333;
}

h2{
    margin-bottom:20px;
}

input{
    width:100%;
    padding:14px;
    margin:10px 0;
    border:none;
    border-radius:10px;
    background:#222;
    color:white;
}

input:focus{
    outline:none;
    box-shadow:0 0 0 2px #e50914;
}

button{
    width:100%;
    padding:14px;
    border:none;
    border-radius:10px;
    background:#e50914;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#ff1f1f;
}

.msg{
    margin:10px 0;
    font-size:14px;
}

.error{
    color:#ff4d4d;
}

a{
    color:#aaa;
    font-size:14px;
}
</style>
</head>

<body>

<div class="box">

<img src="Logo Poster MovieTx PNG/Logo MovieTx.png" class="logo">

<h2>Crear cuenta</h2>

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