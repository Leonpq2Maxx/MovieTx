<?php
session_start();
require "../config.php";

header("Content-Type: application/json");

/* =========================
   DETECTAR PERFIL
========================= */
$esPerfil = isset($_SESSION['perfil_id']);

$perfilName = $_SESSION['perfil_name'] ?? null;
$email = $_SESSION['email'] ?? null;
$perfilId = $_SESSION['perfil_id'] ?? 0;

/* =========================
   VALIDAR SESIÓN
========================= */
if(!$esPerfil && !isset($_SESSION['email'])){
    echo json_encode([
        "status" => "error",
        "msg" => "No logueado"
    ]);
    exit;
}

$movie_id = $_POST['movie_id'] ?? '';
$tipo = $_POST['tipo'] ?? '';

if(!$movie_id){
    echo json_encode([
        "status" => "error",
        "msg" => "Sin ID"
    ]);
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

        /* =========================
           🗑️ HISTORIAL PERFIL
        ========================= */
        $stmtHistorial = $conn->prepare("
            DELETE FROM perfil_historial 
            WHERE user_email=? 
            AND perfil_name=? 
            AND movie_id=?
        ");

        if(!$stmtHistorial){
            throw new Exception("Error perfil_historial: ".$conn->error);
        }

        $stmtHistorial->bind_param(
            "sss",
            $email,
            $perfilName,
            $movie_id
        );

        $stmtHistorial->execute();


        /* =========================
           🎬 PROGRESO PELÍCULAS
        ========================= */
        $stmt2 = $conn->prepare("
            DELETE FROM perfil_progreso_peliculas 
            WHERE perfil_id=? 
            AND movie_id=?
        ");

        if(!$stmt2){
            throw new Exception("Error perfil_progreso_peliculas: ".$conn->error);
        }

        $stmt2->bind_param(
            "is",
            $perfilId,
            $movie_id
        );

        $stmt2->execute();


        /* =========================
           📺 PROGRESO SERIES
        ========================= */
        $stmt3 = $conn->prepare("
            DELETE FROM user_progress_perfil 
            WHERE perfil_id=? 
            AND movie_id=?
        ");

        if(!$stmt3){
            throw new Exception("Error user_progress_perfil: ".$conn->error);
        }

        $stmt3->bind_param(
            "is",
            $perfilId,
            $movie_id
        );

        $stmt3->execute();


        /* =========================
           ▶️ CONTINUAR SERIE
        ========================= */
        $stmt4 = $conn->prepare("
            DELETE FROM perfiles_continuar_serie 
            WHERE perfil_id=? 
            AND serie_id=?
        ");

        if(!$stmt4){
            throw new Exception("Error perfiles_continuar_serie: ".$conn->error);
        }

        $stmt4->bind_param(
            "is",
            $perfilId,
            $movie_id
        );

        $stmt4->execute();


        /* =========================
           🎥 CONTINUAR PELÍCULA
        ========================= */
        $stmt5 = $conn->prepare("
            DELETE FROM perfiles_continuar_viendo 
            WHERE perfil_id=? 
            AND pelicula_id=?
        ");

        if(!$stmt5){
            throw new Exception("Error perfiles_continuar_viendo: ".$conn->error);
        }

        $stmt5->bind_param(
            "is",
            $perfilId,
            $movie_id
        );

        $stmt5->execute();


        /* =========================
           🔥 ELIMINAR POR NOMBRE PERFIL
           (EXTRA SEGURIDAD)
        ========================= */

        // SERIES
        $stmt6 = $conn->prepare("
            DELETE FROM perfiles_continuar_serie 
            WHERE nombre_perfil=? 
            AND serie_id=?
        ");

        if(!$stmt6){
            throw new Exception("Error perfiles_continuar_serie nombre: ".$conn->error);
        }

        $stmt6->bind_param(
            "ss",
            $perfilName,
            $movie_id
        );

        $stmt6->execute();


        // PELÍCULAS
        $stmt7 = $conn->prepare("
            DELETE FROM perfiles_continuar_viendo 
            WHERE nombre_perfil=? 
            AND pelicula_id=?
        ");

        if(!$stmt7){
            throw new Exception("Error perfiles_continuar_viendo nombre: ".$conn->error);
        }

        $stmt7->bind_param(
            "ss",
            $perfilName,
            $movie_id
        );

        $stmt7->execute();


        // PROGRESO PELÍCULAS
        $stmt8 = $conn->prepare("
            DELETE FROM perfil_progreso_peliculas 
            WHERE perfil_name=? 
            AND movie_id=?
        ");

        if(!$stmt8){
            throw new Exception("Error perfil_progreso_peliculas nombre: ".$conn->error);
        }

        $stmt8->bind_param(
            "ss",
            $perfilName,
            $movie_id
        );

        $stmt8->execute();


        // PROGRESO SERIES
        $stmt9 = $conn->prepare("
            DELETE FROM user_progress_perfil 
            WHERE perfil_name=? 
            AND movie_id=?
        ");

        if(!$stmt9){
            throw new Exception("Error user_progress_perfil nombre: ".$conn->error);
        }

        $stmt9->bind_param(
            "ss",
            $perfilName,
            $movie_id
        );

        $stmt9->execute();

    } else {

        /* =========================================================
           👤 USUARIO NORMAL (SIN PERFIL)
        ========================================================= */

        /* =========================
           🗑️ HISTORIAL
        ========================= */
        $stmt = $conn->prepare("
            DELETE FROM historial 
            WHERE user_email=? 
            AND movie_id=?
        ");

        if(!$stmt){
            throw new Exception("Error historial: ".$conn->error);
        }

        $stmt->bind_param(
            "ss",
            $email,
            $movie_id
        );

        $stmt->execute();


        /* =========================
           🎬 PROGRESO PELÍCULAS
        ========================= */
        $stmt2 = $conn->prepare("
            DELETE FROM progreso_peliculas 
            WHERE email=? 
            AND movie_id=?
        ");

        if(!$stmt2){
            throw new Exception("Error progreso_peliculas: ".$conn->error);
        }

        $stmt2->bind_param(
            "ss",
            $email,
            $movie_id
        );

        $stmt2->execute();


        /* =========================
           📺 PROGRESO SERIES
        ========================= */
        $stmt3 = $conn->prepare("
            DELETE FROM user_progress 
            WHERE email=? 
            AND movie_id=?
        ");

        if(!$stmt3){
            throw new Exception("Error user_progress: ".$conn->error);
        }

        $stmt3->bind_param(
            "ss",
            $email,
            $movie_id
        );

        $stmt3->execute();


        /* =========================
           ▶️ CONTINUAR SERIE
        ========================= */
        if($tipo === "serie"){

            $stmt4 = $conn->prepare("
                DELETE FROM continuar_serie 
                WHERE user_email=? 
                AND serie_id=?
            ");

            if(!$stmt4){
                throw new Exception("Error continuar_serie: ".$conn->error);
            }

            $stmt4->bind_param(
                "ss",
                $email,
                $movie_id
            );

            $stmt4->execute();
        }


        /* =========================
           🎥 CONTINUAR PELÍCULA
        ========================= */
        if($tipo === "pelicula"){

            $stmt5 = $conn->prepare("
                DELETE FROM continuar_viendo 
                WHERE user_email=? 
                AND pelicula_id=?
            ");

            if(!$stmt5){
                throw new Exception("Error continuar_viendo: ".$conn->error);
            }

            $stmt5->bind_param(
                "ss",
                $email,
                $movie_id
            );

            $stmt5->execute();
        }
    }

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "msg" => "Eliminado correctamente"
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}
?>