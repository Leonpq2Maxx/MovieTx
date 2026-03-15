<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['admin_id'])) {
    $id = (int)$_SESSION['admin_id'];

    $conn->query("
        UPDATE users 
        SET is_online=0, last_logout=NOW()
        WHERE id=$id
    ");
}

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_unset();
session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: index.php");
exit();
?>