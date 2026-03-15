<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['id'];

/* =========================
   CAMBIAR CONTRASEÑA
==========================*/
if (isset($_POST['change_password'])) {

    $newPass = $_POST['new_password'] ?? '';

    if (strlen($newPass) < 6) {
        die("La contraseña debe tener al menos 6 caracteres");
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $stmt->bind_param("si", $hash, $userId);
    $stmt->execute();

    header("Location: inicio.php?pass=ok");
    exit();
}

/* =========================
   ELIMINAR CUENTA
==========================*/
if (isset($_POST['delete_account'])) {

    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?deleted=1");
    exit();
}

header("Location: inicio.php");
exit();