<?php
require "config.php";

// 🔥 LOG (debug opcional)
file_put_contents("log.txt", file_get_contents("php://input") . PHP_EOL, FILE_APPEND);

// 🔥 RECIBIR NOTIFICACIÓN
$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data['data']['id'])){
    http_response_code(400);
    exit;
}

$payment_id = $data['data']['id'];

// 🔥 TOKEN REAL
$access_token = "APP_USR-XXXXXXXXXXXX";

// 🔥 CONSULTAR PAGO
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/v1/payments/$payment_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);

$response = curl_exec($ch);

if(curl_errno($ch)){
    file_put_contents("log.txt", "CURL ERROR: " . curl_error($ch) . PHP_EOL, FILE_APPEND);
    curl_close($ch);
    exit;
}

curl_close($ch);

$pago = json_decode($response, true);

// ❌ VALIDAR
if(!$pago || !isset($pago['status'])){
    file_put_contents("log.txt", "ERROR RESPUESTA: " . $response . PHP_EOL, FILE_APPEND);
    exit;
}

// 🔥 SOLO APROBADOS
if($pago['status'] === "approved"){

    $email = $pago['external_reference']; // lo usamos para encontrar el user_id
    $monto = $pago['transaction_amount'];

    // 🔥 BUSCAR USER_ID
    $stmtUser = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmtUser->bind_param("s", $email);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();

    if($resUser->num_rows === 0){
        file_put_contents("log.txt", "USER NO ENCONTRADO: $email" . PHP_EOL, FILE_APPEND);
        exit;
    }

    $user = $resUser->fetch_assoc();
    $user_id = $user['id'];

    // 🔥 EVITAR DUPLICADOS
    $stmtCheck = $conn->prepare("SELECT idPrimaria FROM pagos WHERE mp_payment_id=?");
    $stmtCheck->bind_param("s", $payment_id);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();

    if($resCheck->num_rows > 0){
        // ya procesado
        exit;
    }

    // 🔥 INSERTAR PAGO
    $stmtPago = $conn->prepare("
        INSERT INTO pagos (user_id, mp_payment_id, status, monto)
        VALUES (?, ?, ?, ?)
    ");
    $status = $pago['status'];
    $stmtPago->bind_param("issd", $user_id, $payment_id, $status, $monto);
    $stmtPago->execute();

    // 🔥 ACTUALIZAR SUSCRIPCIÓN
    $stmtUpdate = $conn->prepare("
        UPDATE users 
        SET paid_until = 
            IF(paid_until >= NOW(), 
                DATE_ADD(paid_until, INTERVAL 30 DAY),
                DATE_ADD(NOW(), INTERVAL 30 DAY)
            )
        WHERE id=?
    ");

    $stmtUpdate->bind_param("i", $user_id);
    $stmtUpdate->execute();

    file_put_contents("log.txt", "PAGO OK USER_ID: $user_id" . PHP_EOL, FILE_APPEND);
}

http_response_code(200);