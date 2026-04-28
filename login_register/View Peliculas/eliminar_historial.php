<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   DETECTAR PERFIL
========================= */
$esPerfil = isset($_SESSION['perfil_id']);
$perfilId = $_SESSION['perfil_id'] ?? null;

/* =========================
   VALIDAR SESIÓN
========================= */
if(!$esPerfil && !isset($_SESSION['email'])){
    echo json_encode(["status"=>"error","msg"=>"No logueado"]);
    exit;
}

$email = $_SESSION['email'] ?? null;
$movie_id = $_POST['movie_id'] ?? '';
$tipo = $_POST['tipo'] ?? ''; // 🔥 IMPORTANTE

if(!$movie_id){
    echo json_encode(["status"=>"error","msg"=>"Sin ID"]);
    exit;
}

/* =========================
   🔥 TRANSACCIÓN
========================= */
$conn->begin_transaction();

try {

    /* =========================================================
       🔥 PERFIL
    ========================================================= */
    if($esPerfil){

        if($tipo === "serie"){

            $stmtPerfil = $conn->prepare("
                DELETE FROM perfiles_continuar_serie 
                WHERE perfil_id=? AND serie_id=?
            ");

            if(!$stmtPerfil) throw new Exception("Error perfil serie: ".$conn->error);

            $stmtPerfil->bind_param("is", $perfilId, $movie_id);

            if(!$stmtPerfil->execute()){
                throw new Exception("Execute perfil serie: ".$stmtPerfil->error);
            }

        } elseif($tipo === "pelicula"){

            $stmtPerfil = $conn->prepare("
                DELETE FROM perfiles_continuar_viendo 
                WHERE perfil_id=? AND pelicula_id=?
            ");

            if(!$stmtPerfil) throw new Exception("Error perfil pelicula: ".$conn->error);

            $stmtPerfil->bind_param("is", $perfilId, $movie_id);

            if(!$stmtPerfil->execute()){
                throw new Exception("Execute perfil pelicula: ".$stmtPerfil->error);
            }

        } else {
            throw new Exception("Tipo no válido");
        }

    } else {

        /* =========================
           🗑️ BORRAR HISTORIAL
        ========================= */
        $stmt = $conn->prepare("DELETE FROM historial WHERE user_email=? AND movie_id=?");
        if(!$stmt) throw new Exception("Error historial: ".$conn->error);

        $stmt->bind_param("ss",$email,$movie_id);
        if(!$stmt->execute()) throw new Exception("Execute historial: ".$stmt->error);


        /* =========================
           🔥 BORRAR PROGRESO PELÍCULAS
        ========================= */
        $stmt2 = $conn->prepare("DELETE FROM progreso_peliculas WHERE email=? AND movie_id=?");
        if(!$stmt2) throw new Exception("Error progreso_peliculas: ".$conn->error);

        $stmt2->bind_param("ss",$email,$movie_id);
        if(!$stmt2->execute()) throw new Exception("Execute progreso_peliculas: ".$stmt2->error);


        /* =========================
           🔥 BORRAR PROGRESO SERIES
        ========================= */
        $stmt3 = $conn->prepare("DELETE FROM user_progress WHERE email=? AND movie_id=?");
        if(!$stmt3) throw new Exception("Error user_progress: ".$conn->error);

        $stmt3->bind_param("ss",$email,$movie_id);
        if(!$stmt3->execute()) throw new Exception("Execute user_progress: ".$stmt3->error);


        /* =========================
           🔥 BORRAR SEGUIR VIENDO SERIES
        ========================= */
        $stmt4 = $conn->prepare("DELETE FROM continuar_serie WHERE user_email=? AND serie_id=?");
        if(!$stmt4) throw new Exception("Error continuar_serie: ".$conn->error);

        $stmt4->bind_param("ss",$email,$movie_id);
        if(!$stmt4->execute()) throw new Exception("Execute continuar_serie: ".$stmt4->error);


        /* =========================
           🔥 BORRAR SEGUIR VIENDO PELÍCULAS
        ========================= */
        $stmt5 = $conn->prepare("DELETE FROM continuar_viendo WHERE user_email=? AND pelicula_id=?");
        if(!$stmt5) throw new Exception("Error continuar_viendo: ".$conn->error);

        $stmt5->bind_param("ss",$email,$movie_id);
        if(!$stmt5->execute()) throw new Exception("Execute continuar_viendo: ".$stmt5->error);

    }

    $conn->commit();

    echo json_encode([
        "status"=>"success",
        "msg"=>"Eliminado correctamente"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status"=>"error",
        "msg"=>$e->getMessage()
    ]);
}
?>
