<?php

$host = "sql301.infinityfree.com";
$user = "if0_42119738";
$password = "isu5ycACrP";
$database = "if0_42119738_users_db";


$conn = new mysqli($host, $user, $password, $database);


if ($conn->connect_error) {
    die("Connection failed: ". $conn->connect_error);
}



?>