<?php
session_start();
require "../config.php";

if(!isset($_SESSION['device_id'])){
    exit;
}

$deviceId = intval($_SESSION['device_id']);

$stmt = $conn->prepare("
UPDATE dispositivos
SET last_ping = NOW(),
    is_active = 1
WHERE id=?
");

$stmt->bind_param("i", $deviceId);
$stmt->execute();