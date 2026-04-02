<?php
session_start();
include("config.php"); // tu conexión a la BD

if (!isset($_SESSION['user_id'])) {
    exit("No autorizado");
}

$user_id = $_SESSION['user_id'];
$theme = $_POST['theme'];

// Validar valores permitidos
$allowed = ['light','dark','blue','sky','red','pink'];
if (!in_array($theme, $allowed)) {
    exit("Tema inválido");
}

$stmt = $conn->prepare("UPDATE users SET theme=? WHERE idPrimaria=?");
$stmt->bind_param("si", $theme, $user_id);

if ($stmt->execute()) {
    echo "Guardado";
} else {
    echo "Error";
}
?>