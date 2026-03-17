<?php
session_start();
require_once 'config.php';

/* ======================================================
   REGISTRO USUARIO NORMAL
====================================================== */
if (isset($_POST['register'])) {

    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($check->num_rows > 0) {
        $_SESSION['register_error'] = 'El correo ya está registrado';
        header("Location: index.php");
        exit();
    }

    $conn->query("
        INSERT INTO users 
        (name,email,password,role,status,created_by)
        VALUES 
        ('$name','$email','$password','user','pending','self')
    ");

    $_SESSION['success'] = 'Usuario registrado correctamente';
    header("Location: index.php");
    exit();
}



/* ======================================================
   REGISTRO ADMINISTRADOR PRINCIPAL (SOLO UNO)
====================================================== */
if (isset($_POST['register_admin'])) {

    $check = $conn->query("
        SELECT id FROM users 
        WHERE role='admin' AND admin_level='super'
    ");
    if ($check->num_rows > 0) {
        $_SESSION['login_error'] = 'El administrador principal ya existe';
        header("Location: index.php");
        exit();
    }

    $name      = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $admin_key = password_hash($_POST['admin_key'], PASSWORD_DEFAULT);

    $conn->query("
        INSERT INTO users
        (name,email,password,role,status,admin_level,admin_key,created_by)
        VALUES
        ('$name','$email','$password','admin','active','super','$admin_key','admin')
    ");

    $_SESSION['admin_register_success'] =
        'Administrador principal registrado correctamente';

    header("Location: index.php");
    exit();
}

// =====================
// LOGIN CREADOR
// =====================
if (isset($_POST['creator_login'])) {

    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    $q = $conn->query("
        SELECT * FROM users
        WHERE email='$email'
          AND role='creator'
        LIMIT 1
    ");

    if ($q->num_rows === 1) {

        $user = $q->fetch_assoc();

        if (password_verify($pass, $user['password'])) {

            session_start();
            $_SESSION['id']   = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = 'creator';

            header("Location: creator_page.php");
            exit();
        }
    }

    $_SESSION['creatorError'] = "Credenciales incorrectas";
    header("Location: index.php");
    exit();
}


/* ======================================================
   LOGIN (USUARIO / ADMIN PRINCIPAL / ADMIN AYUDANTE)
====================================================== */
if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $adminKey = $_POST['admin_key'] ?? '';

    $result = $conn->query("SELECT * FROM users WHERE email='$email'");

    if ($result->num_rows !== 1) {
        $_SESSION['login_error'] = 'Credenciales incorrectas';
        header("Location: index.php");
        exit();
    }

    $user = $result->fetch_assoc();

    /* -------- PASSWORD -------- */
    if (!password_verify($password, $user['password'])) {
        $_SESSION['login_error'] = 'Credenciales incorrectas';
        header("Location: index.php");
        exit();
    }

    /* -------- STATUS -------- */
    if ($user['status'] !== 'active') {
        $_SESSION['login_error'] = 'Cuenta pendiente o suspendida';
        header("Location: index.php");
        exit();
    }

    /* -------- ADMIN PRINCIPAL → CLAVE -------- */
    if ($user['role'] === 'admin' && $user['admin_level'] === 'super') {

        if (empty($adminKey)) {
            $_SESSION['login_error'] = 'Debe ingresar la clave administrativa';
            header("Location: index.php");
            exit();
        }

        if (!password_verify($adminKey, $user['admin_key'])) {
            $_SESSION['login_error'] = 'Clave administrativa incorrecta';
            header("Location: index.php");
            exit();
        }
    }

    /* -------- LOGIN OK -------- */
    $_SESSION['id']          = $user['id'];
    $_SESSION['name']        = $user['name'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['admin_level'] = $user['admin_level'];

    // ANTES de cualquier header()
$_SESSION['login_status'] = null;
$_SESSION['login_message'] = null;
$_SESSION['login_type'] = null;


   /* -------- REDIRECCIÓN -------- */

if ($user['role'] === 'admin') {

    header("Location: admin_page.php");
    exit();

} else {

    // Usuario normal → pantalla de perfiles
    header("Location: perfiles.php");
    exit();

}

} // ← ESTA LLAVE CIERRA if (isset($_POST['login']))

/* ======================================================
   SEGURIDAD
====================================================== */

header("Location: index.php");
exit();


