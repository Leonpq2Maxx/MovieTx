<?php
session_start();
require_once 'config.php';

/* ======================================================
   OBTENER TOKEN DEL DISPOSITIVO
====================================================== */
$deviceToken = $_COOKIE['device_token'] ?? null;

/* ======================================================
   MARCAR DISPOSITIVO COMO INACTIVO
====================================================== */
if (!empty($_SESSION['id']) && $deviceToken) {

    $userId = (int)$_SESSION['id'];

    $conn->query("
        UPDATE dispositivos
        SET is_active = 0,
            last_ping = NOW()
        WHERE user_id = $userId
        AND token = '$deviceToken'
    ");

    /* opcional admin */
    if (isset($_SESSION['admin_id'])) {

        $id = (int)$_SESSION['admin_id'];

        $conn->query("
            UPDATE users 
            SET is_online=0, last_logout=NOW()
            WHERE id=$id
        ");
    }
}

/* ======================================================
   BORRAR COOKIE
====================================================== */
if (isset($_COOKIE['device_token'])) {
    setcookie("device_token", "", time() - 3600, "/");
}

/* ==========================
   ADMIN OFFLINE
========================== */

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin' &&
    isset($_SESSION['id'])
) {

    $adminId = (int)$_SESSION['id'];

    $stmt = $conn->prepare("
        UPDATE admins
        SET
            is_online = 0,
            last_ping = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param("i", $adminId);
    $stmt->execute();
}

/* ======================================================
   SESIÓN
====================================================== */
session_unset();
session_destroy();

/* ======================================================
   CACHE OFF
====================================================== */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: index.php");
exit();
?>