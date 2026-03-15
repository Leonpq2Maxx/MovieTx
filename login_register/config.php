<?php

$host = "sql204.infinityfree.com";
$user = "if0_40797000";
$password = "IzsuR1HwTq";
$database = "if0_40797000_users_db";


$conn = new mysqli($host, $user, $password, $database);


if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
}



?>