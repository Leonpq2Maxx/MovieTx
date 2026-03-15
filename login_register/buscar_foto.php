<?php
require_once 'config.php';

header('Content-Type: application/json');

$correo = $_GET['correo'] ?? '';

$response = [
    "foto" => "default.png",
    "admin_level" => null
];

if ($correo) {
    $stmt = $conn->prepare("SELECT foto, admin_level FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        if (!empty($res['foto'])) {
            $response["foto"] = "/" . $res['foto'];
        }

        $response["admin_level"] = $res["admin_level"];
    }
}

echo json_encode($response);

?>