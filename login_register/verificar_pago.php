<?php
session_start();
require "config.php";

if(!isset($_SESSION['email'])){
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];

// 🔥 TU ACCESS TOKEN REAL
$access_token = "APP_USR-XXXXXXXXXXXX"; // ← PONÉ EL TUYO

// 🔥 DATOS DEL PAGO
$data = [
    "items" => [[
        "title" => "Suscripción MovieTx",
        "quantity" => 1,
        "currency_id" => "ARS",
        "unit_price" => 3000
    ]],
    "payer" => [
        "email" => $email
    ],
    "external_reference" => $email,
    "back_urls" => [
        "success" => "http://localhost/exito.php",
        "failure" => "http://localhost/error.php",
        "pending" => "http://localhost/pendiente.php"
    ],
    "auto_return" => "approved"
];

// 🔥 CURL
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com/checkout/preferences");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);

// ❌ ERROR CURL
if(curl_errno($ch)){
    echo "Error CURL: " . curl_error($ch);
    exit;
}

// 🔍 STATUS HTTP
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// ❌ ERROR API
if($http_code != 201){
    echo "<h3>Error Mercado Pago</h3>";
    echo "<pre>$response</pre>";
    exit;
}

// 🔥 RESPUESTA
$result = json_decode($response, true);

// ❌ SI FALLA
if(!isset($result['init_point'])){
    echo "<h3>Error creando pago</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    exit;
}

// ✅ REDIRECCIÓN AUTOMÁTICA AL PAGO
header("Location: " . $result['init_point']);
exit;