<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'creator') {
    header("Location: index.php");
    exit();
}
