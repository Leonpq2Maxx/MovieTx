<?php
/*
=====================================
AUTH SYSTEM - PROTECCIÓN DE PÁGINAS
=====================================
Este archivo valida:

✔ Sesión activa
✔ Usuario existente
✔ Cuenta activa
✔ Suscripción vigente
✔ Logout automático si cambia el estado
*/

session_start();
require_once "config.php";


/* =========================
   1. VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['id'];


/* =========================
   2. OBTENER USUARIO
========================= */

$stmt = $conn->prepare("
    SELECT id, name, email, foto, status, paid_until
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();


/* =========================
   3. USUARIO NO EXISTE
========================= */

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}


/* =========================
   4. CUENTA SUSPENDIDA
========================= */

if ($user['status'] !== "active") {

    session_unset();
    session_destroy();

    header("Location: index.php?suspended=1");
    exit();
}


/* =========================
   5. SUSCRIPCIÓN EXPIRADA
========================= */

if (!empty($user['paid_until'])) {

    if (strtotime($user['paid_until']) < time()) {

        $stmt = $conn->prepare("
            UPDATE users
            SET status='suspended'
            WHERE id=?
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();

        session_unset();
        session_destroy();

        header("Location: index.php?expired=1");
        exit();
    }
}


/* =========================
   6. DATOS DEL USUARIO
========================= */

$nombre = $user['name'] ?? 'Usuario';
$email  = $user['email'] ?? '';

$foto = !empty($user['foto'])
    ? $user['foto']
    : 'Logo Poster MovieTx PNG/Logo MovieTx.png';



/* =========================
   7. VERIFICACIÓN AJAX
   (para detectar suspensión en vivo)
========================= */

if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("
        SELECT status
        FROM users
        WHERE id=?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $data = $stmt->get_result()->fetch_assoc();

    if (!$data || $data['status'] !== "active") {

        session_unset();
        session_destroy();

        echo "logout";

    } else {

        echo "ok";

    }

    exit();
}
?>
