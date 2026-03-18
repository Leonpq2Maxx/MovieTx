<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   VALIDAR SESIÓN
========================= */
if(!isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No session"]);
    exit;
}

/* =========================
   VALIDAR POST
========================= */
if(!isset($_POST['movie_id'], $_POST['imagen'], $_POST['progreso'], $_POST['archivo'])){
    echo json_encode(["status"=>"error","msg"=>"Faltan datos POST"]);
    exit;
}

$email = $_SESSION['email'];
$perfilId = isset($_SESSION['perfil_id']) ? intval($_SESSION['perfil_id']) : 0;

/* =========================
   OBTENER NOMBRE USUARIO
========================= */
$userName = "Usuario";

$stmtUser = $conn->prepare("SELECT name FROM users WHERE email=? LIMIT 1");
if($stmtUser){
    $stmtUser->bind_param("s",$email);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result()->fetch_assoc();
    if($resUser){
        $userName = $resUser['name'];
    }
}

/* =========================
   OBTENER NOMBRE PERFIL
========================= */
$perfilName = "Principal";

if($perfilId > 0){
    $stmtPerfil = $conn->prepare("SELECT nombre FROM perfiles WHERE id=? LIMIT 1");
    if($stmtPerfil){
        $stmtPerfil->bind_param("i",$perfilId);
        $stmtPerfil->execute();
        $resPerfil = $stmtPerfil->get_result()->fetch_assoc();
        if($resPerfil){
            $perfilName = $resPerfil['nombre'];
        }
    }
}

/* =========================
   DATOS
========================= */
$movie_id = $_POST['movie_id'];
$titulo = $_POST['titulo'] ?? "";
$tipo = $_POST['tipo'] ?? "pelicula";
$archivo = $_POST['archivo'];
$imagen = $_POST['imagen'];
$progreso = floatval($_POST['progreso']);

/* limpiar */
if(!$titulo || $titulo == "undefined"){
    $titulo = str_replace("_"," ",$movie_id);
}
if(!$tipo || $tipo == "undefined"){
    $tipo = "pelicula";
}

/* =========================
   DEBUG (IMPORTANTE)
========================= */
if(!$movie_id || !$archivo){
    echo json_encode([
        "status"=>"error",
        "msg"=>"movie_id o archivo vacío",
        "data"=>$_POST
    ]);
    exit;
}

/* =========================
   VERIFICAR EXISTE
========================= */
$check = $conn->prepare("SELECT id FROM historial WHERE user_email=? AND perfil_id=? AND movie_id=?");

if(!$check){
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

$check->bind_param("sis",$email,$perfilId,$movie_id);
$check->execute();
$result = $check->get_result();

/* =========================
   UPDATE
========================= */
if($result->num_rows > 0){

    $update = $conn->prepare("
        UPDATE historial
        SET progreso=?, visto_en=NOW(), user_name=?, perfil_name=?
        WHERE user_email=? AND perfil_id=? AND movie_id=?
    ");

    if(!$update){
        echo json_encode(["status"=>"error","msg"=>$conn->error]);
        exit;
    }

    $update->bind_param("dsssis",$progreso,$userName,$perfilName,$email,$perfilId,$movie_id);

    if(!$update->execute()){
        echo json_encode(["status"=>"error","msg"=>$update->error]);
        exit;
    }

    echo json_encode(["status"=>"updated"]);
    exit;
}

/* =========================
   INSERT
========================= */
$stmt = $conn->prepare("
INSERT INTO historial 
(user_email, user_name, perfil_id, perfil_name, movie_id, titulo, tipo, imagen, progreso, archivo, visto_en)
VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
");

if(!$stmt){
    echo json_encode(["status"=>"error","msg"=>$conn->error]);
    exit;
}

$stmt->bind_param(
    "ssisssssds",
    $email,
    $userName,
    $perfilId,
    $perfilName,
    $movie_id,
    $titulo,
    $tipo,
    $imagen,
    $progreso,
    $archivo
);

if(!$stmt->execute()){
    echo json_encode([
        "status"=>"error",
        "msg"=>$stmt->error
    ]);
    exit;
}

/* =========================
   LIMITE HISTORIAL
========================= */
$limite = 15;

$delete = $conn->prepare("
DELETE FROM historial 
WHERE user_email=? AND perfil_id=?
AND id NOT IN (
    SELECT id FROM (
        SELECT id FROM historial
        WHERE user_email=? AND perfil_id=?
        ORDER BY visto_en DESC
        LIMIT $limite
    ) AS temp
)
");

if($delete){
    $delete->bind_param("siss",$email,$perfilId,$email,$perfilId);
    $delete->execute();
}

echo json_encode([
    "status"=>"new",
    "debug"=>[
        "email"=>$email,
        "perfil_id"=>$perfilId,
        "user"=>$userName,
        "perfil"=>$perfilName,
        "movie"=>$movie_id
    ]
]);
?>
