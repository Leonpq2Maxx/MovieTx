<?php
session_start();

$_SESSION['perfil_id'] = $_GET['id'];

header("Location: inicio.php");
exit;
?>