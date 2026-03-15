<?php

session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uer page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background: #fff">
    <div class="box">
        <h1>Walcome, <span><?= $_SESSION['name']; ?></span></h1>
        <p>this is an <span>user</span> page</p>
        <button onclick="window.location.href='logount.php'">Logount</button>
    </div>
</body>
</html>