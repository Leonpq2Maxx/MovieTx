<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "msg" => "No logueado"]);
    exit;
}

$email = $_SESSION['email'];

/* =========================
   PERFIL ACTIVO
========================= */
if (!isset($_SESSION['perfil_name'])) {
    echo json_encode(["status" => "error", "msg" => "Perfil no seleccionado"]);
    exit;
}

$perfil = $_SESSION['perfil_name'];

/* =========================
   DATOS
========================= */
$movie_id = $_POST['movie_id'] ?? '';
$titulo   = $_POST['titulo'] ?? '';
$tipo     = $_POST['tipo'] ?? 'pelicula';
$imagen   = $_POST['imagen'] ?? '';
$archivo  = $_POST['archivo'] ?? '';

if (!$movie_id || !$archivo) {
    echo json_encode(["status" => "error", "msg" => "Datos incompletos"]);
    exit;
}

/* =========================
   NORMALIZAR
========================= */
if (!$titulo || $titulo === "undefined") {
    $titulo = str_replace("_", " ", $movie_id);
}

if (!$tipo || $tipo === "undefined") {
    $tipo = "pelicula";
}

/* =========================
   TRANSACCIÓN
========================= */
$conn->begin_transaction();

try {

    /* =========================
       VERIFICAR SI EXISTE
    ========================= */
    $check = $conn->prepare("
        SELECT id FROM perfil_historial 
        WHERE user_email=? AND perfil_name=? AND movie_id=?
    ");
    $check->bind_param("sss", $email, $perfil, $movie_id);
    $check->execute();
    $res = $check->get_result();

    /* =========================
       UPDATE
    ========================= */
    if ($res->num_rows > 0) {

        $update = $conn->prepare("
            UPDATE perfil_historial 
            SET visto_en=NOW()
            WHERE user_email=? AND perfil_name=? AND movie_id=?
        ");
        $update->bind_param("sss", $email, $perfil, $movie_id);
        $update->execute();

        $status = "updated";

    } else {

        /* =========================
           INSERT
        ========================= */
        $stmt = $conn->prepare("
            INSERT INTO perfil_historial
            (user_email, perfil_name, movie_id, titulo, tipo, imagen, archivo, visto_en)
            VALUES (?,?,?,?,?,?,?,NOW())
        ");

        $stmt->bind_param(
            "sssssss",
            $email,
            $perfil,
            $movie_id,
            $titulo,
            $tipo,
            $imagen,
            $archivo
        );

        $stmt->execute();

        $status = "new";
    }

    /* =========================
       LIMITE 15 POR PERFIL 🔥
    ========================= */
    $limite = 15;

    $delete = $conn->prepare("
        DELETE FROM perfil_historial 
        WHERE user_email=? AND perfil_name=?
        AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM perfil_historial
                WHERE user_email=? AND perfil_name=?
                ORDER BY visto_en DESC
                LIMIT $limite
            ) t
        )
    ");

    $delete->bind_param("ssss", $email, $perfil, $email, $perfil);
    $delete->execute();

    $conn->commit();

    echo json_encode(["status" => $status]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}
