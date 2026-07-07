<?php

require_once 'config.php';

header('Content-Type: application/json');

$correo = trim($_GET['correo'] ?? '');

$response = [
    "foto" => "default.png",
    "admin_level" => null
];

if (!empty($correo)) {

    // =========================
    // BUSCAR EN ADMINS
    // =========================

    $stmt = $conn->prepare("
        SELECT foto, admin_level
        FROM admins
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $correo);
    $stmt->execute();

    $admin = $stmt
        ->get_result()
        ->fetch_assoc();

    if ($admin) {

        if (!empty($admin['foto'])) {
            $response['foto'] = $admin['foto'];
        }

        $response['admin_level'] =
            $admin['admin_level'];

    } else {

        // =========================
        // BUSCAR EN USERS
        // =========================

        $stmt = $conn->prepare("
            SELECT foto
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $correo);
        $stmt->execute();

        $user = $stmt
            ->get_result()
            ->fetch_assoc();

        if ($user) {

            if (!empty($user['foto'])) {
                $response['foto'] =
                    $user['foto'];
            }
        }
    }
}

echo json_encode($response);