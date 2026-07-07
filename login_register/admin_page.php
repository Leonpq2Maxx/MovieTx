<?php
session_start();
require_once 'config.php';

// 🔒 EVITA CACHE DEL NAVEGADOR
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 🔐 VALIDACIÓN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$adminId    = (int)$_SESSION['id'];
$adminName  = $_SESSION['name'];
$adminLevel = $_SESSION['admin_level']; //super | normal

// =====================
// CUPOS DE ADMIN AYUDANTE
// =====================

$quota = null;

if ($adminLevel === 'normal') {

    $quotaData = $conn->query("
        SELECT user_quota 
        FROM users 
        WHERE id=$adminId
    ")->fetch_assoc();

    $quota = isset($quotaData['user_quota']) 
    ? (int)$quotaData['user_quota'] 
    : 0;
}


// Obtener foto del admin logueado
$adminFoto = null;

$stmt = $conn->prepare("SELECT foto FROM users WHERE id=?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!empty($res['foto'])) {
    $adminFoto = $res['foto'];
}


/* =====================
   APROBACIONES SUPER
===================== */
if ($adminLevel === 'super') {

    if (isset($_POST['approve_request'])) {

        $rid = (int)$_POST['request_id'];

        $stmt = $conn->prepare("
            SELECT * FROM admin_requests 
            WHERE id=? AND status='pending'
        ");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();

        if ($req) {

            $uid = (int)$req['user_id'];

            // 👉 ACCIONES SEGÚN TIPO
            switch ($req['action']) {

                case 'create':
                    $conn->query("
                        UPDATE users
                        SET status='active',
                            paid_until = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        WHERE id=$uid
                    ");
                break;

                case 'suspend':
                    $conn->query("
                        UPDATE users
                        SET status='suspended'
                        WHERE id=$uid
                    ");
                break;

                case 'activate':
                    $conn->query("
                        UPDATE users
                        SET status='active',
                            paid_until = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        WHERE id=$uid
                    ");
                break;

                case 'update':
                    // aquí puedes manejar cambios futuros
                break;
            }

            // ✅ IMPORTANTE: eliminar o marcar como aprobado
            $conn->query("
                UPDATE admin_requests 
                SET status='approved'
                WHERE id=$rid
            ");
        }

        header("Location: admin_page.php");
        exit();
    }

    if (isset($_POST['reject_request'])) {

        $rid = (int)$_POST['request_id'];

        $conn->query("
            UPDATE admin_requests 
            SET status='rejected'
            WHERE id=$rid
        ");

        header("Location: admin_page.php");
        exit();
    }
}

// =====================
// SOLICITUDES DE AYUDANTE
// =====================

// 🔹 Solicitar actualización
if (isset($_POST['request_update']) && $adminLevel === 'normal') {

    $uid = (int)$_POST['user_id'];

    $conn->query("
        INSERT INTO admin_requests (user_id, action, requested_by)
        VALUES ($uid, 'update', $adminId)
    ");

    $_SESSION['msg'] = "Solicitud de actualización enviada";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}


// 🔹 Solicitar activar / suspender
if (isset($_POST['request_toggle']) && $adminLevel === 'normal') {

    $uid = (int)$_POST['user_id'];

    // ver estado actual
    $current = $conn->query("
        SELECT status FROM users WHERE id=$uid
    ")->fetch_assoc();

    $action = ($current['status'] === 'active') ? 'suspend' : 'activate';

    $conn->query("
        INSERT INTO admin_requests (user_id, action, requested_by)
        VALUES ($uid, '$action', $adminId)
    ");

    $_SESSION['msg'] = "Solicitud enviada al administrador principal";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}

// =====================
// CREAR ADMIN AYUDANTE
// =====================
if (isset($_POST['create_helper']) && $adminLevel === 'super') {

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telefono = trim($_POST['telefono']);

    // 🔒 VALIDAR TELÉFONO
    if (empty($telefono) || !preg_match('/^[0-9]{10}$/', $telefono)) {
        $_SESSION['msg'] = "Teléfono inválido (10 números)";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    // ✅ SOLO VALIDAR CUPOS (NO PERFILES)
    if (!isset($_POST['quota']) || $_POST['quota'] === '') {

        $_SESSION['msg'] = "Debes ingresar la cantidad de cupos";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    $quota = (int)$_POST['quota'];

    if ($quota < 0) {
        $_SESSION['msg'] = "Los cupos no pueden ser negativos";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    $rutaFoto = null;

    $carpeta = "uploads/admins/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    if (!empty($_FILES['foto']['name'])) {
        $nombreArchivo = time() . "_" . basename($_FILES['foto']['name']);
        $rutaFoto = $carpeta . $nombreArchivo;
        move_uploaded_file($_FILES['foto']['tmp_name'], $rutaFoto);
    }

    // 🔎 Verificar email existente
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check->num_rows === 0) {

        $stmt = $conn->prepare("
            INSERT INTO users
            (name,email,password,telefono,role,admin_level,status,user_quota,created_by,created_by_admin,created_at,foto)
            VALUES (?, ?, ?, ?, 'admin', 'normal', 'active', ?, 'admin', ?, NOW(), ?)
        ");

        // 🔥 CORRECTO (7 parámetros reales)
        $stmt->bind_param("ssssiss", $name, $email, $pass, $telefono, $quota, $adminId, $rutaFoto);
        $stmt->execute();
    }

    $_SESSION['msg'] = "Administrador ayudante creado con cupos asignados";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}



// =====================
// CREAR USUARIO
// =====================
if (isset($_POST['create_user'])) {

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $telefono = trim($_POST['telefono']);

    // 🔒 VALIDAR TELÉFONO
    if (empty($telefono) || !preg_match('/^[0-9]{10}$/', $telefono)) {
        $_SESSION['msg'] = "Teléfono inválido (10 números)";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    // 🔒 VALIDAR MAX PERFILES
    if (!isset($_POST['max_perfiles']) || $_POST['max_perfiles'] === '') {

        $_SESSION['msg'] = "Debes ingresar la cantidad de perfiles";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    $maxPerfiles = (int)$_POST['max_perfiles'];

    if ($maxPerfiles < 1 || $maxPerfiles > 6) {
    $_SESSION['msg'] = "La cantidad de perfiles debe ser entre 1 y 6";
    $_SESSION['msg_type'] = "error";
    header("Location: admin_page.php");
    exit();
}

    $rutaFoto = null;

    // 📁 Carpeta de subida
    $carpeta = "uploads/usuarios/";
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    // 🖼️ Procesar imagen
    if (!empty($_FILES['foto']['name'])) {
        $nombreArchivo = time() . "_" . basename($_FILES['foto']['name']);
        $rutaFoto = $carpeta . $nombreArchivo;
        move_uploaded_file($_FILES['foto']['tmp_name'], $rutaFoto);
    }

    // 🔎 Verificar email duplicado
    $check = $conn->query("SELECT id FROM users WHERE email='$email'");

    if ($check->num_rows > 0) {

        $_SESSION['msg'] = "El correo ya está registrado";
        $_SESSION['msg_type'] = "error";
        header("Location: admin_page.php");
        exit();
    }

    // ==========================
    // ADMIN AYUDANTE
    // ==========================
    if ($adminLevel === 'normal') {

        if ($quota <= 0) {

            $_SESSION['msg'] = "No tienes cupos disponibles para crear usuarios";
            $_SESSION['msg_type'] = "error";
            header("Location: admin_page.php");
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO users
            (name,email,password,telefono,role,status,created_by,created_by_admin,created_at,paid_until,foto,max_perfiles)
            VALUES (?, ?, ?, ?, 'user', 'pending', 'admin', ?, NOW(),
                    DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?)
        ");

        // 🔥 CORRECTO (7 parámetros)
        $stmt->bind_param("ssssisi", $name, $email, $pass, $telefono, $adminId, $rutaFoto, $maxPerfiles);
        $stmt->execute();

        $newUserId = $stmt->insert_id;

        // 🔔 Crear solicitud automática
        $conn->query("
            INSERT INTO admin_requests (user_id, action, requested_by)
            VALUES ($newUserId, 'create', $adminId)
        ");

        // ➖ Descontar cupo
        $conn->query("
            UPDATE users
            SET user_quota = user_quota - 1
            WHERE id=$adminId
        ");

        $_SESSION['msg'] = "Usuario creado y enviado para aprobación";
        $_SESSION['msg_type'] = "success";
    }

    // ==========================
    // ADMIN PRINCIPAL
    // ==========================
    else {

        $stmt = $conn->prepare("
            INSERT INTO users
            (name,email,password,telefono,role,status,created_by,created_by_admin,created_at,foto,max_perfiles)
            VALUES (?, ?, ?, ?, 'user', 'active', 'admin', ?, NOW(), ?, ?)
        ");

        // 🔥 CORRECTO (7 parámetros)
        $stmt->bind_param("ssssisi", $name, $email, $pass, $telefono, $adminId, $rutaFoto, $maxPerfiles);
        $stmt->execute();

        $_SESSION['msg'] = "Usuario creado con éxito";
        $_SESSION['msg_type'] = "success";
    }

    header("Location: admin_page.php");
    exit();
}

if (isset($_POST['add_profiles'])) {

    $userId = (int)$_POST['user_id'];
    $value  = (int)$_POST['profile_value'];

    if ($value > 0) {

        // 🔍 obtener actual
        $current = $conn->query("
            SELECT max_perfiles 
            FROM users 
            WHERE id=$userId
        ")->fetch_assoc();

        $currentMax = (int)$current['max_perfiles'];

        // 🔥 nuevo valor
        $newMax = $currentMax + $value;

        // 🚫 LIMITE MÁXIMO 6
        if ($newMax > 6) {
            $newMax = 6;
        }

        $conn->query("
            UPDATE users
            SET max_perfiles = $newMax
            WHERE id = $userId
        ");
    }

    $_SESSION['msg'] = "Perfiles actualizados (máx 6)";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}
if (isset($_POST['remove_profiles'])) {

    $userId = (int)$_POST['user_id'];
    $value  = (int)$_POST['profile_value'];

    if ($value > 0) {

        // 🔥 Obtener límite actual
        $current = $conn->query("
            SELECT max_perfiles 
            FROM users 
            WHERE id=$userId
        ")->fetch_assoc();

        $currentMax = (int)$current['max_perfiles'];

        // Evitar negativos
        if ($currentMax < $value) {
            $value = $currentMax;
        }

        // 🔥 Nuevo límite
        $newMax = $currentMax - $value;

        // 1️⃣ ACTUALIZAR LIMITE
        $conn->query("
            UPDATE users
            SET max_perfiles = $newMax
            WHERE id = $userId
        ");

        // 2️⃣ CONTAR PERFILES ACTUALES (SIN CONTAR PRINCIPAL)
        $perfiles = $conn->query("
            SELECT id, foto 
            FROM perfiles 
            WHERE user_id=$userId
            ORDER BY id DESC
        ");

        $totalPerfiles = $perfiles->num_rows;

        // 🔥 Cantidad permitida real (restamos 1 por perfil principal)
        $permitidos = $newMax - 1;

        if ($permitidos < 0) $permitidos = 0;

        // 3️⃣ SI HAY MÁS PERFILES DE LOS PERMITIDOS → BORRAR
        if ($totalPerfiles > $permitidos) {

            $exceso = $totalPerfiles - $permitidos;

            while ($row = $perfiles->fetch_assoc()) {

                if ($exceso <= 0) break;

                $perfilId = (int)$row['id'];
                $foto = $row['foto'];

                // 🗑️ borrar imagen
                $ruta = "uploads/perfiles/" . $foto;

                if (!empty($foto) && $foto !== "default.png" && file_exists($ruta)) {
                    unlink($ruta);
                }

                // ❌ borrar perfil
                $conn->query("DELETE FROM perfiles WHERE id=$perfilId");

                $exceso--;
            }
        }
    }

    $_SESSION['msg'] = "Perfiles eliminados correctamente";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}

// =====================
// ACTUALIZAR DIRECTO (SUPER)
// =====================
if (isset($_POST['update_account']) && $adminLevel === 'super') {
    $id = (int)$_POST['user_id'];
    $conn->query("
        UPDATE users
        SET status='active',
            paid_until=DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        WHERE id=$id
    ");
    header("Location: admin_page.php");
    exit();
}

// =====================
// ACTIVAR / SUSPENDER
// =====================
if (isset($_POST['toggle_status']) && $adminLevel === 'super') {

    $id = (int)$_POST['user_id'];

    // Ver estado actual
    $current = $conn->query("
        SELECT status FROM users WHERE id=$id
    ")->fetch_assoc();

    if ($current['status'] === 'active') {

        // Si está activo → suspender
        $conn->query("
            UPDATE users
            SET status='suspended'
            WHERE id=$id
        ");

    } else {

        // Si NO está activo → activar y asignar vencimiento
        $conn->query("
            UPDATE users
            SET status='active',
                paid_until = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            WHERE id=$id
        ");
    }

    header("Location: admin_page.php");
    exit();
}


// =====================
// CONTRASEÑA
// =====================
if (isset($_POST['change_password'])) {
    $id = (int)$_POST['user_id'];
    $newPass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$newPass' WHERE id=$id");
    $_SESSION['msg'] = "Cambio de contraseña con éxito";
$_SESSION['msg_type'] = "success";
header("Location: admin_page.php");
exit();

}

// =====================
// CAMBIAR CONTRASEÑA ADMIN AYUDANTE
// =====================
if (isset($_POST['change_helper_password']) && $adminLevel === 'super') {

    $helperId = (int)$_POST['helper_id'];
    $newPass  = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $conn->query("
        UPDATE users
        SET password='$newPass'
        WHERE id=$helperId AND admin_level='normal'
    ");

    $_SESSION['msg'] = "Contraseña del ayudante actualizada";
    $_SESSION['msg_type'] = "success";

    header("Location: admin_page.php");
    exit();
}

// =====================
// SUSPENDER / ACTIVAR ADMIN AYUDANTE
// =====================
if (isset($_POST['toggle_helper']) && $adminLevel === 'super') {

    $helperId = (int)$_POST['helper_id'];

    $current = $conn->query("
        SELECT status FROM users
        WHERE id=$helperId AND admin_level='normal'
    ")->fetch_assoc();

    if ($current['status'] === 'active') {

        $conn->query("
            UPDATE users
            SET status='suspended'
            WHERE id=$helperId
        ");

    } else {

        $conn->query("
            UPDATE users
            SET status='active'
            WHERE id=$helperId
        ");
    }

    header("Location: admin_page.php");
    exit();
}


// =====================
// 🗑️ BORRAR USUARIO COMPLETO (FIX FINAL DEFINITIVO REAL)
// =====================
if (isset($_POST['delete_user']) && $adminLevel === 'super') {

    $userId = (int)$_POST['user_id'];

    // 🔒 Validar usuario + obtener datos
    $stmt = $conn->prepare("SELECT id, email, foto FROM users WHERE id=? AND role='user'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        header("Location: admin_page.php");
        exit();
    }

    $userEmail = $user['email'];

    $conn->begin_transaction();

    try {

        // =========================
        // 🔥 FUNCIÓN SEGURA
        // =========================
        function deleteIfColumnExists($conn, $table, $column, $type, $value) {

            $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

            if ($check && $check->num_rows > 0) {

                $sql = "DELETE FROM `$table` WHERE `$column`=?";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param($type, $value);
                    $stmt->execute();
                }
            }
        }

        // =========================
        // 🧹 TABLAS QUE USAN user_id
        // =========================
        $tablesUserId = [
            "continuar_viendo",
            "continuar_serie",
            "adultos",
            "dispositivos"
        ];

        foreach ($tablesUserId as $table) {
            deleteIfColumnExists($conn, $table, "user_id", "i", $userId);
        }

        // =========================
        // 🔥 TABLAS QUE USAN EMAIL (CLAVE REAL)
        // =========================
        deleteIfColumnExists($conn, "favoritos", "user_email", "s", $userEmail);
        deleteIfColumnExists($conn, "historial", "user_email", "s", $userEmail);
        deleteIfColumnExists($conn, "progreso_peliculas", "email", "s", $userEmail);

        // 🔴 ESTE ERA TU PROBLEMA
        deleteIfColumnExists($conn, "user_progress", "email", "s", $userEmail);

        // =========================
        // 🧑‍🎬 PERFILES
        // =========================
        $perfiles = $conn->query("SELECT id, foto FROM perfiles WHERE user_id=$userId");

        if ($perfiles) {

            while ($p = $perfiles->fetch_assoc()) {

                $perfilId = (int)$p['id'];

                $tablesPerfil = [
                    "perfiles_continuar_serie",
                    "perfiles_continuar_viendo",
                    "perfil_favorito",
                    "perfil_historial",
                    "perfil_progreso_peliculas",
                    "user_progress_perfil"
                ];

                foreach ($tablesPerfil as $table) {
                    deleteIfColumnExists($conn, $table, "perfil_id", "i", $perfilId);
                }

                // 🖼️ borrar imagen perfil
                if (!empty($p['foto']) && $p['foto'] !== "default.png") {
                    $ruta = "uploads/perfiles/" . $p['foto'];
                    if (file_exists($ruta)) {
                        unlink($ruta);
                    }
                }
            }
        }

        // ❌ borrar perfiles
        deleteIfColumnExists($conn, "perfiles", "user_id", "i", $userId);

        // =========================
        // 🖼️ FOTO USUARIO
        // =========================
        if (!empty($user['foto']) && file_exists($user['foto'])) {
            unlink($user['foto']);
        }

        // =========================
        // 🧨 BORRAR USUARIO
        // =========================
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $conn->commit();

        $_SESSION['msg'] = "Usuario eliminado completamente (100% limpio)";
        $_SESSION['msg_type'] = "success";

    } catch (Exception $e) {

        $conn->rollback();

        $_SESSION['msg'] = "Error al eliminar: " . $e->getMessage();
        $_SESSION['msg_type'] = "error";
    }

    header("Location: admin_page.php");
    exit();
}

// =====================
// AGREGAR CUPOS A AYUDANTE
// =====================
if (isset($_POST['add_quota']) && $adminLevel === 'super') {

    $helperId = (int)$_POST['helper_id'];
    $extraQuota = (int)$_POST['quota_value'];

    if ($extraQuota > 0) {

        $conn->query("
            UPDATE users
            SET user_quota = user_quota + $extraQuota
            WHERE id = $helperId
        ");

        $_SESSION['msg'] = "Cupos agregados correctamente";
        $_SESSION['msg_type'] = "success";
    }

    header("Location: admin_page.php");
    exit();
}


// =====================
// QUITAR CUPOS A AYUDANTE
// =====================
if (isset($_POST['remove_quota']) && $adminLevel === 'super') {

    $helperId = (int)$_POST['helper_id'];
    $removeQuota = (int)$_POST['quota_value'];

    if ($removeQuota > 0) {

        // Obtener cupos actuales
        $current = $conn->query("
            SELECT user_quota 
            FROM users 
            WHERE id=$helperId
        ")->fetch_assoc();

        $currentQuota = (int)$current['user_quota'];

        // Evitar números negativos
        if ($currentQuota < $removeQuota) {
            $removeQuota = $currentQuota;
        }

        $conn->query("
            UPDATE users
            SET user_quota = user_quota - $removeQuota
            WHERE id = $helperId
        ");

        $_SESSION['msg'] = "Cupos descontados correctamente";
        $_SESSION['msg_type'] = "success";
    }

    header("Location: admin_page.php");
    exit();
}



// =====================
// BORRAR AYUDANTE
// =====================
if (isset($_POST['delete_helper']) && $adminLevel === 'super') {
    $id = (int)$_POST['helper_id'];
    $conn->query("DELETE FROM users WHERE id=$id AND admin_level='normal'");
    header("Location: admin_page.php");
    exit();
}


/* =====================
   CONSULTAS
===================== */

if ($adminLevel === 'super') {
    $helpers = $conn->query("
        SELECT * FROM users
        WHERE role='admin' AND admin_level='normal'
        ORDER BY created_at DESC
    ");
}

if ($adminLevel === 'super') {
    $users = $conn->query("
        SELECT u.*, a.name AS admin_name
        FROM users u
        LEFT JOIN users a ON a.id = u.created_by_admin
        WHERE u.role='user'
        ORDER BY a.name, u.created_at DESC
    ");
} else {
    $users = $conn->query("
        SELECT *
        FROM users
        WHERE role='user' AND created_by_admin=$adminId
        ORDER BY created_at DESC
    ");
}

// =====================
// NUEVO – USUARIOS POR ORIGEN
// =====================

if ($adminLevel === 'super') {

    // Usuarios creados por el admin principal
    $usersBySuper = $conn->query("
        SELECT *
        FROM users
        WHERE role='user'
          AND created_by_admin = $adminId
        ORDER BY created_at DESC
    ");

    // Usuarios creados por administradores ayudantes
    $usersByHelpers = $conn->query("
        SELECT u.*, a.name AS admin_name
        FROM users u
        JOIN users a ON a.id = u.created_by_admin
        WHERE u.role='user'
          AND u.created_by_admin != $adminId
        ORDER BY a.name, u.created_at DESC
    ");
}


// =====================
// NOTIFICACIONES
// =====================
if ($adminLevel === 'super') {

    $checkTable = $conn->query("
        SHOW TABLES LIKE 'admin_requests'
    ");

    if ($checkTable && $checkTable->num_rows > 0) {
        $requests = $conn->query("
    SELECT 
        r.*, 
        u.name AS user_name,
        a.name AS admin_name,
        a.email AS admin_email
    FROM admin_requests r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN users a ON a.id = r.requested_by
    WHERE r.status='pending'
    ORDER BY r.created_at DESC
");
    } else {
        $requests = false;
    }
}

// =====================
// NUEVO – FILTRO POR AYUDANTE
// =====================
$selectedHelper = null;

if ($adminLevel === 'super' && isset($_GET['helper_id'])) {
    $selectedHelper = (int)$_GET['helper_id'];
}

if ($adminLevel === 'super' && $selectedHelper) {

    $usersBySelectedHelper = $conn->query("
        SELECT u.*, a.name AS admin_name
        FROM users u
        JOIN users a ON a.id = u.created_by_admin
        WHERE u.role='user'
          AND u.created_by_admin = $selectedHelper
        ORDER BY u.created_at DESC
    ");
}


// =====================
// ACTUALIZAR FOTO ADMIN PRINCIPAL
// =====================
if (isset($_POST['update_admin_photo'])) {

    if (!empty($_FILES['foto_admin']['name'])) {

        $carpeta = "uploads/admins/";

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }

        // 📌 Obtener foto actual
        $old = $conn->query("SELECT foto FROM users WHERE id=$adminId")
                    ->fetch_assoc();

        // 🗑️ Borrar foto vieja si existe
        if (!empty($old['foto']) && file_exists($old['foto'])) {
            unlink($old['foto']);
        }

        // 📸 Subir nueva foto
        $nombreArchivo = time() . "_" . basename($_FILES['foto_admin']['name']);
        $rutaFoto = $carpeta . $nombreArchivo;

        if (move_uploaded_file($_FILES['foto_admin']['tmp_name'], $rutaFoto)) {

            $stmt = $conn->prepare("UPDATE users SET foto=? WHERE id=?");
            $stmt->bind_param("si", $rutaFoto, $adminId);
            $stmt->execute();
        }
    }

    header("Location: admin_page.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo.png">
<title>Panel Administrador</title>

<style>

/* =========================================================
   🚀 MOVIETX ADMIN • NEXT UI 2026 OPTIMIZED
   Ultra fluido + rendimiento extremo
========================================================= */

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

/* =========================================================
   RESET
========================================================= */

*,
*::before,
*::after{
margin:0;
padding:0;
box-sizing:border-box;
}

:root{

--bg:#020617;
--bg-secondary:#081120;

--card:rgba(15,23,42,.72);

--card-border:
rgba(255,255,255,.07);

--text:#f8fafc;

--muted:#94a3b8;

--primary:#2563eb;
--primary-light:#60a5fa;

--green:#22c55e;
--green-dark:#16a34a;

--red:#ef4444;
--red-dark:#dc2626;

--gray:#475569;

--radius:24px;

--blur:16px;

--transition:
.22s cubic-bezier(.22,.61,.36,1);

--shadow:
0 8px 24px rgba(0,0,0,.32);

--shadow-hover:
0 18px 45px rgba(0,0,0,.42);

}

/* =========================================================
   HTML
========================================================= */

html{
scroll-behavior:smooth;
-webkit-text-size-adjust:100%;
-webkit-tap-highlight-color:transparent;
text-rendering:optimizeLegibility;
}

body{

font-family:'Inter',sans-serif;

background:
radial-gradient(circle at top left,
rgba(37,99,235,.18),
transparent 26%),

radial-gradient(circle at bottom right,
rgba(168,85,247,.12),
transparent 24%),

linear-gradient(
160deg,
#020617 0%,
#07111d 48%,
#0a1323 100%
);

color:var(--text);

min-height:100vh;

overflow-x:hidden;

position:relative;

padding-bottom:70px;

line-height:1.4;

-webkit-font-smoothing:antialiased;
-moz-osx-font-smoothing:grayscale;

}

/* =========================================================
   GRID FX (OPTIMIZED)
========================================================= */

body::before{

content:'';

position:fixed;

inset:0;

background:
linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);

background-size:42px 42px;

opacity:.26;

pointer-events:none;

z-index:-1;
}

/* =========================================================
   GPU OPTIMIZATION
========================================================= */

.box,
button,
.menu-link,
.admin-logo,
.side-menu,
header{
transform:translateZ(0);
backface-visibility:hidden;
}

/* =========================================================
   SCROLL
========================================================= */

::-webkit-scrollbar{
width:7px;
}

::-webkit-scrollbar-track{
background:#07101c;
}

::-webkit-scrollbar-thumb{

background:
linear-gradient(
180deg,
#2563eb,
#60a5fa
);

border-radius:30px;
}

/* =========================================================
   HEADER
========================================================= */

header{

position:relative;

overflow:hidden;

margin:14px;

padding:30px 22px;

border-radius:30px;

background:
linear-gradient(
145deg,
rgba(15,23,42,.90),
rgba(15,23,42,.74)
);

border:1px solid rgba(255,255,255,.06);

backdrop-filter:blur(var(--blur));
-webkit-backdrop-filter:blur(var(--blur));

box-shadow:
0 18px 45px rgba(0,0,0,.38),
inset 0 1px 0 rgba(255,255,255,.04);

contain:layout paint;
}

/* GLOW */

header::before{

content:'';

position:absolute;

width:360px;
height:360px;

right:-150px;
top:-170px;

border-radius:50%;

background:
radial-gradient(circle,
rgba(59,130,246,.26),
transparent 72%);

pointer-events:none;
}

/* =========================================================
   ADMIN
========================================================= */

.admin-header{

display:flex;

align-items:center;

justify-content:center;

gap:22px;

flex-wrap:wrap;

text-align:center;

position:relative;

z-index:2;
}

.admin-logo{

width:118px;
height:118px;

object-fit:cover;

border-radius:50%;

border:3px solid rgba(96,165,250,.85);

box-shadow:
0 0 0 7px rgba(37,99,235,.10),
0 0 28px rgba(59,130,246,.30);

transition:
transform .22s ease,
box-shadow .22s ease;

will-change:transform;
}

.admin-logo:hover{

transform:
scale(1.04);
}

header h2{

font-size:34px;

font-weight:900;

letter-spacing:-1px;

line-height:1.1;
}

header p{

margin-top:8px;

font-size:14px;

color:#cbd5e1;
}

/* =========================================================
   BOX
========================================================= */

.box{

position:relative;

overflow:hidden;

margin:16px 14px;

padding:24px;

border-radius:var(--radius);

background:var(--card);

border:1px solid var(--card-border);

backdrop-filter:blur(var(--blur));
-webkit-backdrop-filter:blur(var(--blur));

box-shadow:var(--shadow);

transition:
transform .22s ease,
box-shadow .22s ease,
border-color .22s ease;

contain:layout paint;
}

.box:hover{

transform:translateY(-3px);

border-color:
rgba(96,165,250,.22);

box-shadow:var(--shadow-hover);
}

.box::before{

content:'';

position:absolute;

inset:0;

background:
linear-gradient(
135deg,
rgba(255,255,255,.04),
transparent 42%
);

pointer-events:none;
}

/* =========================================================
   TITLES
========================================================= */

h3{

display:flex;

align-items:center;

gap:12px;

font-size:22px;

font-weight:800;

margin-bottom:22px;

letter-spacing:-.4px;
}

h3::before{

content:'';

width:7px;
height:28px;

border-radius:20px;

background:
linear-gradient(
180deg,
#60a5fa,
#2563eb
);

box-shadow:
0 0 14px rgba(59,130,246,.5);
}

/* =========================================================
   INPUTS
========================================================= */

input,
textarea,
select{

width:100%;

border:none;

outline:none;

padding:15px 17px;

margin-top:8px;

border-radius:16px;

background:
linear-gradient(
145deg,
rgba(15,23,42,.92),
rgba(30,41,59,.78)
);

border:1px solid rgba(255,255,255,.05);

font-size:15px;

color:white;

transition:
border-color .18s ease,
box-shadow .18s ease,
transform .18s ease;

box-shadow:
inset 0 1px 0 rgba(255,255,255,.03);

appearance:none;
}

input::placeholder,
textarea::placeholder{
color:#94a3b8;
}

input:focus,
textarea:focus,
select:focus{

border-color:
rgba(96,165,250,.50);

box-shadow:
0 0 0 3px rgba(59,130,246,.10);

transform:translateY(-1px);
}

/* =========================================================
   BUTTONS
========================================================= */

button{

position:relative;

overflow:hidden;

border:none;

cursor:pointer;

padding:13px 18px;

border-radius:16px;

font-size:14px;

font-weight:800;

letter-spacing:.2px;

color:white;

transition:
transform .18s ease,
opacity .18s ease,
box-shadow .18s ease;

background:
linear-gradient(
135deg,
#2563eb,
#1d4ed8
);

box-shadow:
0 10px 20px rgba(37,99,235,.26);

user-select:none;
-webkit-user-select:none;
touch-action:manipulation;
}

button:hover{

transform:
translateY(-2px);
}

button:active{

transform:scale(.97);
}

button::before{

content:'';

position:absolute;

inset:0;

background:
linear-gradient(
120deg,
transparent,
rgba(255,255,255,.16),
transparent
);

transform:translateX(-140%);

transition:transform .5s ease;
}

button:hover::before{
transform:translateX(140%);
}

/* =========================================================
   COLORS
========================================================= */

.green,
.btn-activate{

background:
linear-gradient(
135deg,
#22c55e,
#16a34a
);

box-shadow:
0 10px 20px rgba(34,197,94,.20);
}

.red,
.btn-suspend{

background:
linear-gradient(
135deg,
#ef4444,
#dc2626
);

box-shadow:
0 10px 20px rgba(239,68,68,.18);
}

.gray,
.btn-delete{

background:
linear-gradient(
135deg,
#475569,
#334155
);

box-shadow:
0 10px 20px rgba(15,23,42,.34);
}

/* =========================================================
   ACTIONS
========================================================= */

.actions{

display:flex;

gap:10px;

flex-wrap:wrap;

align-items:center;

justify-content:center;
}

.actions button{

flex:1;

min-width:125px;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrap{

overflow:auto;

border-radius:24px;

border:1px solid rgba(255,255,255,.05);

background:
rgba(15,23,42,.42);

backdrop-filter:blur(14px);

-webkit-overflow-scrolling:touch;
}

table{

width:100%;

border-collapse:collapse;
}

thead{

position:sticky;

top:0;

z-index:2;
}

th{

padding:16px;

text-align:center;

font-size:13px;

font-weight:800;

background:
linear-gradient(
135deg,
#0f172a,
#131f37
);

border-bottom:
1px solid rgba(255,255,255,.06);
}

td{

padding:15px;

text-align:center;

font-size:14px;

color:#dbeafe;

border-bottom:
1px solid rgba(255,255,255,.04);

transition:background .18s ease;
}

tr:hover td{

background:
rgba(59,130,246,.05);
}

/* =========================================================
   STATUS
========================================================= */

.green-text{
color:#4ade80;
font-weight:700;
}

.red-text{
color:#f87171;
font-weight:700;
}

/* =========================================================
   LINKS
========================================================= */

.telefono-link{

text-decoration:none;

font-weight:700;

color:#4ade80;

transition:opacity .18s ease;
}

.telefono-link:hover{

opacity:.8;

text-decoration:underline;
}

/* =========================================================
   MENU BUTTON
========================================================= */

.hamburger-btn{

position:fixed;

top:16px;
right:16px;

width:56px;
height:56px;

border-radius:18px;

display:flex;

align-items:center;

justify-content:center;

font-size:24px;

z-index:99999;

background:
linear-gradient(
145deg,
rgba(15,23,42,.94),
rgba(30,41,59,.80)
);

backdrop-filter:blur(18px);

border:1px solid rgba(255,255,255,.06);

box-shadow:
0 12px 28px rgba(0,0,0,.38);
}

/* =========================================================
   OVERLAY
========================================================= */

.menu-overlay{

position:fixed;

inset:0;

background:rgba(0,0,0,.58);

opacity:0;

visibility:hidden;

transition:
opacity .25s ease,
visibility .25s ease;

backdrop-filter:blur(5px);

z-index:9998;
}

.menu-overlay.show{

opacity:1;

visibility:visible;
}

/* =========================================================
   SIDE MENU
========================================================= */

.side-menu{

position:fixed;

top:0;
right:-360px;

width:330px;

height:100vh;

overflow-y:auto;

padding:24px;

background:
linear-gradient(
180deg,
rgba(2,6,23,.98),
rgba(15,23,42,.96)
);

border-left:
1px solid rgba(255,255,255,.06);

transition:
right .30s cubic-bezier(.22,.61,.36,1);

backdrop-filter:blur(18px);

z-index:9999;

box-shadow:
-12px 0 35px rgba(0,0,0,.38);

overscroll-behavior:contain;
}

.side-menu.open{
right:0;
}

/* =========================================================
   MENU LINKS
========================================================= */

.menu-link{

width:100%;

display:flex;

align-items:center;

gap:12px;

padding:15px 17px;

margin-bottom:10px;

border-radius:16px;

font-weight:700;

font-size:14px;

color:white;

text-decoration:none;

background:
linear-gradient(
145deg,
rgba(30,41,59,.90),
rgba(15,23,42,.76)
);

border:1px solid rgba(255,255,255,.05);

transition:
transform .18s ease,
background .18s ease;
}

.menu-link:hover{

transform:translateX(4px);

background:
linear-gradient(
135deg,
#2563eb,
#1d4ed8
);
}

.menu-link.active{

background:
linear-gradient(
135deg,
#2563eb,
#1d4ed8
);

border-color:
rgba(96,165,250,.32);

box-shadow:
0 10px 22px rgba(37,99,235,.28);
}

/* =========================================================
   TOAST
========================================================= */

.toast{

position:fixed;

top:22px;
left:50%;

transform:translateX(-50%);

padding:15px 22px;

border-radius:18px;

font-weight:800;

z-index:999999;

backdrop-filter:blur(14px);

animation:
toastIn .35s ease,
toastOut .35s ease 4s forwards;
}

.toast.success{

background:
linear-gradient(
135deg,
#22c55e,
#16a34a
);
}

.toast.error{

background:
linear-gradient(
135deg,
#ef4444,
#dc2626
);
}

@keyframes toastIn{

from{
opacity:0;
transform:translate(-50%,-16px);
}

to{
opacity:1;
transform:translate(-50%,0);
}
}

@keyframes toastOut{

to{
opacity:0;
transform:translate(-50%,-16px);
}
}

/* =========================================================
   SECTION
========================================================= */

.section-panel{
display:none;
}

.section-panel.active{

display:block;

animation:fadeSection .28s ease;
}

@keyframes fadeSection{

from{
opacity:0;
transform:translateY(8px);
}

to{
opacity:1;
transform:none;
}
}

/* =========================================================
   CUSTOM SELECT
========================================================= */

.custom-select{
position:relative;
}

.select-dropdown{

display:none;

position:absolute;

top:100%;
left:0;

width:100%;

margin-top:8px;

max-height:240px;

overflow-y:auto;

border-radius:18px;

z-index:50;

background:
linear-gradient(
145deg,
rgba(15,23,42,.98),
rgba(30,41,59,.95)
);

border:
1px solid rgba(255,255,255,.06);

box-shadow:
0 16px 34px rgba(0,0,0,.40);
}

.select-option{

padding:14px;

cursor:pointer;

transition:background .16s ease;

border-bottom:
1px solid rgba(255,255,255,.03);
}

.select-option:hover{

background:
rgba(59,130,246,.10);
}

/* =========================================================
   MODAL CROPPER
========================================================= */

#cropModal{

position:fixed;

inset:0;

display:none;

flex-direction:column;

background:rgba(0,0,0,.92);

backdrop-filter:blur(10px);

z-index:999999;
}

.crop-header,
.crop-footer{

display:flex;

align-items:center;

justify-content:space-between;

padding:16px;

background:
rgba(15,23,42,.60);

border-bottom:
1px solid rgba(255,255,255,.06);
}

.crop-container{

flex:1;

display:flex;

align-items:center;

justify-content:center;

padding:2px;
}

.crop-container img{

max-width:100%;

max-height:100%;

object-fit:contain;
}

/* =========================================================
   PREVIEW
========================================================= */

.preview-admin-container{

position:relative;

width:96px;
height:96px;

margin-top:16px;

display:none;
}

#previewAdminFoto,
#previewUsuarioFoto{

width:100%;
height:100%;

object-fit:cover;

border-radius:50%;

border:3px solid rgba(96,165,250,.8);

box-shadow:
0 0 24px rgba(59,130,246,.28);
}

.remove-preview-btn{

position:absolute;

top:-6px;
right:-6px;

width:28px;
height:28px;

padding:0;

border-radius:50%;
}

/* =========================================================
   ERRORS
========================================================= */

.input-error{

border-color:#ef4444 !important;

box-shadow:
0 0 0 3px rgba(239,68,68,.10);
}

.error-text{

display:none;

margin-top:6px;

font-size:12px;

color:#f87171;
}

.error-text.show{
display:block;
}

/* =========================================================
   PERFORMANCE MODE
========================================================= */

@media (prefers-reduced-motion:reduce){

*{
animation:none !important;
transition:none !important;
scroll-behavior:auto !important;
}

}

/* =========================================================
   ANDROID
========================================================= */

@media (max-width:768px){

body{
padding-bottom:90px;
}

header{

margin:10px;

padding:22px 16px;

border-radius:24px;
}

.admin-header{

flex-direction:column;

gap:14px;
}

.admin-logo{

width:92px;
height:92px;
}

header h2{
font-size:25px;
}

.box{

margin:12px 10px;

padding:18px;

border-radius:20px;
}

.side-menu{

width:88%;

right:-100%;
}

.hamburger-btn{

width:52px;
height:52px;

top:10px;
right:10px;
}

table,
thead,
tbody,
tr,
td{

display:block;

width:100%;
}

thead{
display:none;
}

tr{

margin-bottom:14px;

border-radius:18px;

overflow:hidden;

background:
linear-gradient(
145deg,
rgba(15,23,42,.94),
rgba(30,41,59,.82)
);

border:
1px solid rgba(255,255,255,.05);
}

td{

display:flex;

justify-content:space-between;

align-items:center;

gap:12px;

padding:14px 16px;

text-align:right;
}

td::before{

content:attr(data-label);

font-weight:700;

color:#94a3b8;

text-align:left;
}

.actions{
flex-direction:column;
}

.actions button{
width:100%;
}

}

/* =========================================================
   IPHONE
========================================================= */

@media screen and (max-width:430px){

body{
padding-bottom:110px;
}

header{
border-radius:22px;
}

.box{
padding:15px;
}

h3{
font-size:19px;
}

input,
button{
font-size:16px;
}

.menu-link{

font-size:14px;

padding:14px;
}

.hamburger-btn{

width:50px;
height:50px;

font-size:21px;

border-radius:16px;
}

}

/* =========================================================
   PC
========================================================= */

@media (min-width:1025px){

body{
padding:10px 14px 70px;
}

.box{
padding:28px;
}

.table-wrap{
border-radius:28px;
}

.side-menu{
width:320px;
}

}

</style>
</head>

<body>


<header>

<?php if(isset($_SESSION['msg'])): ?>
<div class="toast <?= $_SESSION['msg_type'] ?>">
    <?= $_SESSION['msg'] ?>
</div>
<?php 
unset($_SESSION['msg']);
unset($_SESSION['msg_type']);
endif; 
?>

    <div class="admin-header">

<?php if ($adminLevel === 'super' || $adminLevel === 'normal'): ?>
<form method="POST" enctype="multipart/form-data" id="formFotoAdmin">

<input
type="file"
name="foto_admin"
id="inputFotoAdmin"
accept="image/*"
style="display:none"
onchange="previewImage(event)"
>

<img
src="<?= $adminFoto ? htmlspecialchars($adminFoto) : 'Logo Poster MovieTx PNG/Logo MovieTx.png' ?>"
alt="Admin"
class="admin-logo"
style="cursor:pointer"
onclick="document.getElementById('inputFotoAdmin').click()"
>

<input type="hidden" name="update_admin_photo">

</form>

<?php else: ?>

<img
src="<?= $adminFoto ? htmlspecialchars($adminFoto) : 'Logo Poster MovieTx PNG/Logo MovieTx.png' ?>"
alt="Admin"
class="admin-logo"
>

<?php endif; ?>


    <h2>
        <?= $adminLevel === 'super'
            ? 'Administrador Principal'
            : 'Administrador Ayudante' ?>
    </h2>
</div>


    <p style="margin:4px 0;font-weight:bold;">
        <?= htmlspecialchars($adminName) ?>
    </p>

    <?php if ($adminLevel === 'normal'): ?>
<p style="margin:4px 0;color:#2563eb;font-weight:bold;">
Cupos disponibles: <?= $quota ?>
</p>
<?php endif; ?>


    <?php if ($adminLevel === 'super'): ?>
        <p style="margin:0;font-size:18px;color:#d3097b;">
            <?= htmlspecialchars($_SESSION['email']) ?>
        </p>
    <?php endif; ?>
</header>

<!-- BOTÓN HAMBURGUESA -->
<button class="hamburger-btn" id="menuBtn" onclick="toggleMenu()">☰</button> 

<!-- OVERLAY -->
<div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

<!-- MENÚ LATERAL -->
<div class="side-menu" id="sideMenu">

<h3 class="menu-title">Panel</h3>

<hr class="menu-divider">

<?php if ($adminLevel === 'super'): ?>
<button class="menu-link" onclick="showSection('crearAdmin')">
👤 Crear administrador
</button>
<?php endif; ?>

<button class="menu-link" onclick="showSection('crearUser')">
➕ Crear usuario
</button>


<?php if ($adminLevel === 'super'): ?>

<div class="menu-section">Administración</div>

<button class="menu-link" onclick="showSection('addQuota')">
📊 Administrar cupos
</button>
<button class="menu-link" onclick="showSection('manageProfiles')">
👤 Administrar perfiles
</button>

<button onclick="showSection('usersWeb')">
👥 Registro usuario
</button>

<button class="menu-link" onclick="showSection('admins')">
🛠 Administradores ayudantes
</button>

<button class="menu-link" onclick="showSection('usersSuper')">
👑 Usuarios del principal
</button>

<button class="menu-link" onclick="showSection('usersHelpers')">
👥 Usuarios de ayudantes
</button>

<button class="menu-link" onclick="showSection('changeUserPass')">
🔑 Cambiar contraseña usuarios
</button>

<button class="menu-link" onclick="showSection('changeAdminPass')">
🔐 Cambiar contraseña admin
</button>

<button class="menu-link" onclick="showSection('requests')">
📩 Solicitudes pendientes
</button>

<?php endif; ?>

<?php if ($adminLevel === 'normal'): ?>
<button class="menu-link" onclick="showSection('changeUserPass')">
🔑 Cambiar contraseña usuarios
</button>
<?php endif; ?>

<div class="menu-section">Sistema</div>

<button class="menu-link" onclick="showSection('allUsers')">
📋 Todos los usuarios
</button>

<button class="menu-link refresh-btn"
onclick="window.location.href=window.location.pathname">
🔄 Refrescar panel
</button>

<a href="logout.php" class="menu-link logout-btn">
🚪 Cerrar sesión
</a>

</div>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="crearAdmin">
<h3>Crear Administrador Ayudante</h3>
<form method="post" enctype="multipart/form-data" id="formCreateAdmin">

<input type="text" name="name" placeholder="Nombre">
<div class="error-text">Ingrese el nombre</div>

<input type="email" name="email" placeholder="Correo">
<div class="error-text">Ingrese un correo válido</div>

<input type="password" name="password" placeholder="Contraseña">
<div class="error-text">Ingrese una contraseña</div>

<input type="number" name="quota" placeholder="Cupos de usuarios que podrá crear" min="0">
<div class="error-text">Ingrese cantidad de cupos</div>

<input type="text" name="telefono" placeholder="Teléfono (ej: 1123456789)" maxlength="10">
<div class="error-text">Ingrese un teléfono válido</div>

<label>Foto del administrador:</label>
<input type="file" id="inputFotoAdmin" name="foto" accept="image/*" onchange="previewImage(event)">
<div class="error-text">Debe subir una foto</div>

<div id="previewAdminContainer" class="preview-admin-container">

    <img id="previewAdminFoto">

    <button type="button"
    class="remove-preview-btn"
    onclick="eliminarFotoAdmin()">
        ✕
    </button>

</div>

<button name="create_helper">Crear Administrador</button>
</form>

</div>
<?php endif; ?>
<div class="box section-panel" id="crearUser">
<h3>Crear Usuario</h3>
<form method="POST" enctype="multipart/form-data" id="formCreateUser">

  <input type="text" name="name" placeholder="Nombre">
  <div class="error-text">Ingrese el nombre</div>

  <input type="email" name="email" placeholder="Correo">
  <div class="error-text">Ingrese un correo válido</div>

  <input type="password" name="password" placeholder="Contraseña">
  <div class="error-text">Ingrese una contraseña</div>

  <input type="number" name="max_perfiles" placeholder="Cantidad de perfiles 6" min="1" max="6" required>
<div class="error-text">Ingrese cantidad de perfiles</div>

<input type="text" name="telefono" placeholder="Teléfono (ej: 1123456789)" maxlength="10">
<div class="error-text">Ingrese un teléfono válido</div>


  <label>Foto del usuario:</label>
<input type="file" id="inputFotoUsuario" name="foto" accept="image/*" onchange="previewImage(event)">
<div class="error-text">Debe subir una foto</div>

  <button type="submit" name="create_user">Crear cuenta</button>
</form>
</div>

<div class="box section-panel" id="usersWeb">
<h3>Usuarios registrados desde la web</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Teléfono</th>
<th>Estado</th>
<th>Accion</th>
</tr>
</thead>

<tbody>
<?php
$webUsers = $conn->query("
SELECT * FROM users
WHERE created_by='self'
ORDER BY created_at DESC
");

while($u = $webUsers->fetch_assoc()):
?>

<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>
<td data-label="Teléfono"><?=htmlspecialchars($u['telefono'])?></td>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Accion">
<div class="action-box">
<div class="action-title">Acción</div>
<div class="actions">

<!-- ACTIVAR / SUSPENDER -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<button 
class="<?= $u['status']==='active' ? 'btn-suspend' : 'btn-activate' ?>" 
name="toggle_status"
>
<?= $u['status']==='active' ? '⛔ Suspender' : '✅ Activar' ?>
</button>

</form>

<!-- RECHAZAR / ELIMINAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<button class="btn-delete" name="delete_user">
⛔ Rechazar
</button>

</form>

</div>
</td>

</tr>

<?php endwhile; ?>
</tbody>

</table>
</div>
</div>

<div class="box section-panel" id="manageProfiles">

<h3>Administrar perfiles de usuarios</h3>

<form method="post" class="form-panel">

<label>Seleccionar usuario</label>

<!-- SELECT REAL -->
<select name="user_id" id="realUserProfileSelect" required class="hidden-select">
<option value="">Seleccionar</option>

<?php
$listUsersProfiles = $conn->query("
    SELECT id,name,email,max_perfiles
    FROM users
    WHERE role='user'
    ORDER BY name
");

while($u=$listUsersProfiles->fetch_assoc()):
?>

<option value="<?=$u['id']?>">
<?=htmlspecialchars($u['name'])?> 
(<?=htmlspecialchars($u['email'])?>)
- Perfiles: <?=$u['max_perfiles']?>
</option>

<?php endwhile; ?>
</select>

<!-- SELECT MODERNO -->
<div class="custom-select">

<input 
type="text" 
placeholder="Buscar usuario..." 
class="select-search"
onclick="toggleUserProfileDropdown()"
onkeyup="filterUserProfiles()"
id="searchUserProfileInput"
>

<div class="select-dropdown" id="userProfileDropdown">

<?php
$listUsersProfiles->data_seek(0);
while($u=$listUsersProfiles->fetch_assoc()):
?>

<div class="select-option" 
onclick="selectUserProfile(
'<?=$u['id']?>',
'<?=htmlspecialchars($u['name'])?> (<?=htmlspecialchars($u['email'])?>) - Perfiles: <?=$u['max_perfiles']?>'
)">
<?=htmlspecialchars($u['name'])?> (<?=htmlspecialchars($u['email'])?>)
- Perfiles: <?=$u['max_perfiles']?>
</div>

<?php endwhile; ?>

</div>
</div>

<label>Cantidad</label>

<input
type="number"
name="profile_value"
min="1"
placeholder="Ej: 1"
required
>

<div style="display:flex;gap:10px;margin-top:10px">

<button class="green" name="add_profiles">
➕ Agregar perfiles
</button>

<button class="red" name="remove_profiles">
➖ Quitar perfiles
</button>

</div>

</form>

</div>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="admins">
<h3>Administradores Ayudantes</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Teléfono</th> <!-- ✅ NUEVO -->
<th>Cupos</th>
<th>Usuarios</th>
<th>Comisión</th>
<th>Acción</th>
</tr>
</thead>

<tbody>
<?php while($h=$helpers->fetch_assoc()):
$count = $conn->query("
    SELECT COUNT(*) total 
    FROM users
    WHERE role='user'
      AND created_by_admin={$h['id']}
      AND status='active'
")->fetch_assoc()['total'];
?>

<tr>
<td data-label="Nombre">
<a href="?helper_id=<?=$h['id']?>" style="color:#2563eb;font-weight:bold;text-decoration:none">
<?=htmlspecialchars($h['name'])?>
</a>
</td>

<td data-label="Email"><?=htmlspecialchars($h['email'])?></td>

<td data-label="Teléfono" class="col-telefono">

<?php if (!empty($h['telefono'])): 
    $tel = preg_replace('/[^0-9]/', '', $h['telefono']); // limpia el número
?>
<a href="https://wa.me/<?=$tel?>" target="_blank" class="telefono-link">
    📱 <?=htmlspecialchars($h['telefono'])?>
</a>
<?php else: ?>
—
<?php endif; ?>

</td>


<td data-label="Cupos"><?= $h['user_quota'] ?></td>
<td data-label="Usuarios"><?= $count ?></td>
<td data-label="Comisión">$<?= $count * 200 ?></td>

<td data-label="Acción">
<div class="actions">

<form method="post">
<input type="hidden" name="helper_id" value="<?=$h['id']?>">
<button class="btn-suspend" name="toggle_helper">
<?= $h['status']==='active' ? '⛔ Suspender' : '✅ Activar' ?>
</button>
</form>

<form method="post">
<input type="hidden" name="helper_id" value="<?=$h['id']?>">
<button class="btn-delete" name="delete_helper">
🗑️ Borrar
</button>
</form>

</div>
</td>

</tr>

<?php endwhile; ?>
</tbody>

</table>
</div>
</div>
<?php endif; ?>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="addQuota">

<h3>Administrar cupos de ayudantes</h3>

<form method="post" class="form-panel">

<label>Seleccionar administrador ayudante</label>

<!-- SELECT REAL (OCULTO) -->
<select name="helper_id" id="realHelperSelect" required class="hidden-select">
<option value="">Seleccionar</option>

<?php
$helpersList = $conn->query("
SELECT id,name,email,user_quota
FROM users
WHERE role='admin' AND admin_level='normal'
ORDER BY name
");

while($h=$helpersList->fetch_assoc()):
?>

<option value="<?=$h['id']?>">
<?=htmlspecialchars($h['name'])?> 
(<?=htmlspecialchars($h['email'])?>)
- Cupos: <?=$h['user_quota']?>
</option>

<?php endwhile; ?>
</select>

<!-- SELECT MODERNO -->
<div class="custom-select">

<input 
type="text" 
placeholder="Buscar administrador..." 
class="select-search"
onclick="toggleHelperDropdown()"
onkeyup="filterHelpers()"
id="searchHelperInput"
>

<div class="select-dropdown" id="helperDropdown">

<?php
$helpersList->data_seek(0);
while($h=$helpersList->fetch_assoc()):
?>

<div class="select-option" 
onclick="selectHelper(
'<?=$h['id']?>',
'<?=htmlspecialchars($h['name'])?> (<?=htmlspecialchars($h['email'])?>) - Cupos: <?=$h['user_quota']?>'
)">
<?=htmlspecialchars($h['name'])?> (<?=htmlspecialchars($h['email'])?>)
- Cupos: <?=$h['user_quota']?>
</div>

<?php endwhile; ?>

</div>
</div>

<label>Cantidad</label>

<input
type="number"
name="quota_value"
min="1"
placeholder="Ej: 5"
required
class="input-modern"
>

<div style="display:flex;gap:10px;margin-top:10px">

<button class="green" name="add_quota">
➕ Agregar cupos
</button>

<button class="red" name="remove_quota">
➖ Quitar cupos
</button>

</div>

</form>

</div>
<?php endif; ?>

<script>
function toggleHelperDropdown(){
    document.getElementById("helperDropdown").style.display = "block";
}

function selectHelper(id, text){
    document.getElementById("realHelperSelect").value = id;
    document.getElementById("searchHelperInput").value = text;
    document.getElementById("helperDropdown").style.display = "none";
}

function filterHelpers(){
    let input = document.getElementById("searchHelperInput").value.toLowerCase();
    let options = document.querySelectorAll("#helperDropdown .select-option");

    options.forEach(opt => {
        opt.style.display = opt.innerText.toLowerCase().includes(input) ? "block" : "none";
    });
}

/* cerrar al hacer click afuera */
document.addEventListener("click", function(e){
    if(!e.target.closest("#addQuota .custom-select")){
        let dd = document.getElementById("helperDropdown");
        if(dd) dd.style.display = "none";
    }
});
</script>


<div class="box section-panel" id="allUsers">
<h3>Usuarios</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Teléfono</th> <!-- ✅ NUEVO -->
<th>Perfiles</th>

<?php if ($adminLevel==='super'): ?>
<th>Creado por</th>
<?php endif; ?>

<th>Estado</th>
<th>Expira</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>
<?php while($u=$users->fetch_assoc()): ?>
<tr>

<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>

<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>

<!-- 📱 TELÉFONO CON WHATSAPP -->
<td data-label="Teléfono" class="col-telefono">
<?php if (!empty($u['telefono'])): 
    $tel = preg_replace('/[^0-9]/', '', $u['telefono']);
?>
<a href="https://wa.me/<?=$tel?>" target="_blank" class="telefono-link">
    📱 <?=htmlspecialchars($u['telefono'])?>
</a>
<?php else: ?>
—
<?php endif; ?>
</td>

<td data-label="Perfiles"><?= $u['max_perfiles'] ?? 0 ?></td>

<?php if ($adminLevel==='super'): ?>
<td data-label="Creado por"><?=htmlspecialchars($u['admin_name'] ?? 'Principal')?></td>
<?php endif; ?>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

<td data-label="Acciones">
<div class="actions">

<!-- ACTUALIZAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<?php if ($adminLevel==='super'): ?>
<button class="btn-activate" name="update_account">🔄 Actualizar</button>
<?php else: ?>
<button class="btn-activate" name="request_update">🔄 Solicitar</button>
<?php endif; ?>

</form>

<!-- ACTIVAR / SUSPENDER -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">

<?php if ($adminLevel==='super'): ?>
<button class="<?= $u['status']==='active' ? 'btn-suspend' : 'btn-activate' ?>" name="toggle_status">
<?= $u['status']==='active' ? '⛔ Suspender' : '✅ Activar' ?>
</button>
<?php else: ?>
<button class="btn-suspend" name="request_toggle">
<?= $u['status']==='active'?'⛔ Solicitar suspensión':'✅ Solicitar activación' ?>
</button>
<?php endif; ?>

</form>

<!-- BORRAR -->
<?php if ($adminLevel==='super'): ?>
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="btn-delete" name="delete_user">🗑️ Borrar</button>
</form>
<?php endif; ?>

</div>
</td>

</tr>
<?php endwhile; ?>
</tbody>

</table>
</div>
</div>


<?php if ($adminLevel === 'super'): ?>

<div class="box section-panel" id="requests">

<h3>Solicitudes pendientes</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Usuario</th>
<th>Acción</th>
<th>Solicitado por</th>
<th>Teléfono</th> <!-- ✅ NUEVO -->
<th>Fecha</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>
<?php if ($requests && $requests->num_rows > 0): ?>

<?php while($r = $requests->fetch_assoc()): ?>
<tr>

<td data-label="Usuario"><?= htmlspecialchars($r['user_name']) ?></td>

<td data-label="Acción">
<?php
switch($r['action']){
    case 'create': echo 'Crear usuario'; break;
    case 'suspend': echo 'Suspender'; break;
    case 'activate': echo 'Activar'; break;
    case 'update': echo 'Actualizar'; break;
}
?>
</td>

<td data-label="Solicitado por">
<?= htmlspecialchars($r['admin_name']) ?><br>
<small><?= htmlspecialchars($r['admin_email']) ?></small>
</td>

<!-- 📱 TELÉFONO -->
<td data-label="Teléfono" class="col-telefono">
<?php if (!empty($r['telefono'])): 
    $tel = preg_replace('/[^0-9]/', '', $r['telefono']);
?>
<a href="https://wa.me/<?=$tel?>" target="_blank" class="telefono-link">
    📱 <?=htmlspecialchars($r['telefono'])?>
</a>
<?php else: ?>
—
<?php endif; ?>
</td>

<td data-label="Fecha"><?= $r['created_at'] ?></td>

<td data-label="Acciones">
<div class="actions">

<form method="post">
<input type="hidden" name="request_id" value="<?= $r['id'] ?>">
<button class="btn-activate" name="approve_request">✅ Aprobar</button>
</form>

<form method="post">
<input type="hidden" name="request_id" value="<?= $r['id'] ?>">
<button class="btn-suspend" name="reject_request">⛔ Rechazar</button>
</form>

</div>
</td>

</tr>
<?php endwhile; ?>

<?php else: ?>

<tr class="no-data">
<td colspan="6">
No hay solicitudes pendientes
</td>
</tr>

<?php endif; ?>
</tbody>

</table>
</div>
</div>

<?php endif; ?>



<?php if ($adminLevel === 'super' || $adminLevel === 'normal'): ?>
<div class="box section-panel" id="changeUserPass">

<h3>Cambiar contraseña de usuarios</h3>

<form method="post" class="form-modern">

<label>Seleccionar usuario</label>

<!-- SELECT ORIGINAL (OCULTO PERO FUNCIONAL) -->
<select name="user_id" id="realSelect" required class="hidden-select">
<option value="">Seleccionar</option>

<?php
if ($adminLevel === 'super') {
    $listUsers = $conn->query("
        SELECT id,name,email
        FROM users
        WHERE role='user'
        ORDER BY name
    ");
} else {
    $listUsers = $conn->query("
        SELECT id,name,email
        FROM users
        WHERE role='user' AND created_by_admin=$adminId
        ORDER BY name
    ");
}

while($u=$listUsers->fetch_assoc()):
?>

<option value="<?=$u['id']?>">
<?=htmlspecialchars($u['name'])?> (<?=htmlspecialchars($u['email'])?>)
</option>

<?php endwhile; ?>
</select>

<!-- SELECT MODERNO -->
<div class="custom-select">

<input 
type="text" 
placeholder="Buscar usuario..." 
class="select-search"
onclick="toggleDropdown()"
onkeyup="filterUsers()"
id="searchInput"
>

<div class="select-dropdown" id="dropdown">

<?php
// volvemos a recorrer (solo visual)
$listUsers->data_seek(0);
while($u=$listUsers->fetch_assoc()):
?>

<div class="select-option" 
onclick="selectUser('<?=$u['id']?>','<?=htmlspecialchars($u['name'])?> (<?=htmlspecialchars($u['email'])?>)')">
<?=htmlspecialchars($u['name'])?> (<?=htmlspecialchars($u['email'])?>)
</div>

<?php endwhile; ?>

</div>
</div>

<label>Nueva contraseña</label>

<input
type="password"
name="new_password"
placeholder="Nueva contraseña"
required
class="input-modern"
>

<button name="change_password" class="btn-main">
Actualizar contraseña
</button>

</form>

</div>
<?php endif; ?>

<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="changeAdminPass">

<h3>Cambiar contraseña de administradores ayudantes</h3>

<form method="post" class="form-modern">

<label>Seleccionar administrador</label>

<!-- SELECT REAL (OCULTO) -->
<select name="helper_id" id="realAdminSelect" required class="hidden-select">
<option value="">Seleccionar</option>

<?php
$listAdmins = $conn->query("
SELECT id,name,email
FROM users
WHERE role='admin' AND admin_level='normal'
ORDER BY name
");

while($a=$listAdmins->fetch_assoc()):
?>

<option value="<?=$a['id']?>">
<?=htmlspecialchars($a['name'])?> (<?=htmlspecialchars($a['email'])?>)
</option>

<?php endwhile; ?>

</select>

<!-- SELECT MODERNO -->
<div class="custom-select">

<input 
type="text" 
placeholder="Buscar administrador..." 
class="select-search"
onclick="toggleAdminDropdown()"
onkeyup="filterAdmins()"
id="searchAdminInput"
>

<div class="select-dropdown" id="adminDropdown">

<?php
$listAdmins->data_seek(0);
while($a=$listAdmins->fetch_assoc()):
?>

<div class="select-option" 
onclick="selectAdmin('<?=$a['id']?>','<?=htmlspecialchars($a['name'])?> (<?=htmlspecialchars($a['email'])?>)')">
<?=htmlspecialchars($a['name'])?> (<?=htmlspecialchars($a['email'])?>)
</div>

<?php endwhile; ?>

</div>
</div>

<label>Nueva contraseña</label>

<input
type="password"
name="new_password"
placeholder="Nueva contraseña"
required
class="input-modern"
>

<button name="change_helper_password" class="btn-main">
Actualizar contraseña
</button>

</form>

</div>
<?php endif; ?>




<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="usersSuper">
<h3>Usuarios creados por el Administrador Principal</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Teléfono</th> <!-- ✅ NUEVO -->
<th>Perfiles</th>
<th>Estado</th>
<th>Expira</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>
<?php while($u = $usersBySuper->fetch_assoc()): ?>
<tr>

<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>

<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>

<!-- 📱 TELÉFONO CON WHATSAPP -->
<td data-label="Teléfono" class="col-telefono">
<?php if (!empty($u['telefono'])): 
    $tel = preg_replace('/[^0-9]/', '', $u['telefono']);
?>
<a href="https://wa.me/<?=$tel?>" target="_blank" class="telefono-link">
    📱 <?=htmlspecialchars($u['telefono'])?>
</a>
<?php else: ?>
—
<?php endif; ?>
</td>

<td data-label="Perfiles"><?= $u['max_perfiles'] ?? 0 ?></td>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

<td data-label="Acciones">
<div class="actions">

<!-- ACTUALIZAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="btn-activate" name="update_account">
🔄 Actualizar
</button>
</form>

<!-- ACTIVAR / SUSPENDER -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="<?= $u['status']==='active' ? 'btn-suspend' : 'btn-activate' ?>" name="toggle_status">
<?= $u['status']==='active' ? '⛔ Suspender' : '✅ Activar' ?>
</button>
</form>

<!-- BORRAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="btn-delete" name="delete_user">
🗑️ Borrar
</button>
</form>

</div>
</td>

</tr>
<?php endwhile; ?>
</tbody>

</table>
</div>
</div>
<?php endif; ?>



<?php if ($adminLevel === 'super'): ?>

<div class="box section-panel" id="usersHelpers">
<h3>Usuarios creados por Administradores Ayudantes</h3>

<div class="table-wrap">
<table>

<thead>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Teléfono</th> <!-- ✅ NUEVO -->
<th>Perfiles</th>
<th>Creado por</th>
<th>Estado</th>
<th>Expira</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>
<?php while($u = $usersByHelpers->fetch_assoc()): ?>
<tr>

<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>

<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>

<!-- 📱 TELÉFONO -->
<td data-label="Teléfono" class="col-telefono">
<?php if (!empty($u['telefono'])): 
    $tel = preg_replace('/[^0-9]/', '', $u['telefono']);
?>
<a href="https://wa.me/<?=$tel?>" target="_blank" class="telefono-link">
    📱 <?=htmlspecialchars($u['telefono'])?>
</a>
<?php else: ?>
—
<?php endif; ?>
</td>

<td data-label="Perfiles"><?= $u['max_perfiles'] ?? 0 ?></td>

<td data-label="Creado por"><?=htmlspecialchars($u['admin_name'])?></td>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

<td data-label="Acciones">
<div class="actions">

<!-- ACTUALIZAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="btn-activate" name="update_account">
🔄 Actualizar
</button>
</form>

<!-- ACTIVAR / SUSPENDER -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="<?= $u['status']==='active' ? 'btn-suspend' : 'btn-activate' ?>" name="toggle_status">
<?= $u['status']==='active' ? '⛔ Suspender' : '✅ Activar' ?>
</button>
</form>

<!-- BORRAR -->
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="btn-delete" name="delete_user">
🗑️ Borrar
</button>
</form>

</div>
</td>

</tr>
<?php endwhile; ?>
</tbody>

</table>
</div>
</div>

<?php endif; ?>


<?php if ($adminLevel === 'super' && $selectedHelper): ?>
<div class="box section-panel">
<h3>Usuarios creados por <?= htmlspecialchars(
    $conn->query("SELECT name FROM users WHERE id=$selectedHelper")->fetch_assoc()['name']
) ?></h3>

<div class="table-wrap">
<table>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Perfiles</th>
<th>Estado</th>
<th>Expira</th>
<th>Actualizar contraseña</th>
<th>Acciones</th>
</tr>

<?php while($u = $usersBySelectedHelper->fetch_assoc()): ?>
<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>
<td data-label="Perfiles"><?= $u['max_perfiles'] ?? 0 ?></td>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

<td data-label="Actualizar contraseña">
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<input type="password" name="new_password" required>
<button name="change_password">Actualizar</button>
</form>
</td>

<td data-label="Acciones">
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="green" name="update_account">Actualizar</button>
</form>

<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="gray" name="toggle_status">
<?= $u['status']==='active'?'Suspender':'Activar' ?>
</button>
</form>

<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="red" name="delete_user">Borrar</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>

<a href="admin_page.php" class="link-action link-back">
← Ver todos
</a>

</div>
<?php endif; ?>

<!-- =========================
MODAL CROPPER PRO
========================= -->
<div id="cropModal">

<div class="crop-header">
<button type="button" onclick="cerrarCrop()">Cancelar</button>
<h3>Ajustar foto</h3>
<button onclick="recortarImagen()">Guardar</button>
</div>

<div class="crop-container">
<img id="imageToCrop">
</div>

<div class="crop-footer">
<button onclick="zoomOut()">−</button>
<button onclick="zoomIn()">+</button>
</div>

</div>

<link href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet"/>
<script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.js"></script>

<script>

/* =========================================================
   🚀 MOVIETX OPTIMIZED SCRIPT
   Ultra fluido + menos consumo
========================================================= */

"use strict";

let cropper = null;
let imagenFinalBlob = null;
let inputActual = null;

/* =========================================================
   HELPERS
========================================================= */

const $ = (selector) => document.querySelector(selector);

const debounce = (fn, delay = 120) => {

let timeout;

return (...args) => {

clearTimeout(timeout);

timeout = setTimeout(() => {
fn(...args);
}, delay);

};

};

/* =========================================================
   CROPPER
========================================================= */

function previewImage(event){

const file = event.target.files?.[0];

if(!file) return;

inputActual = event.target;

const image = $("#imageToCrop");
const modal = $("#cropModal");

if(!image || !modal) return;

/* 🔥 liberar memoria anterior */
if(image.src.startsWith("blob:")){
URL.revokeObjectURL(image.src);
}

const imageURL = URL.createObjectURL(file);

image.src = imageURL;

modal.style.display = "flex";

/* 🔥 destruir cropper anterior */
if(cropper){

cropper.destroy();
cropper = null;

}

/* 🔥 esperar render */
requestAnimationFrame(() => {

cropper = new Cropper(image,{

aspectRatio:1,

viewMode:1,

dragMode:'move',

autoCropArea:1,

movable:true,

zoomable:true,

scalable:false,

responsive:true,

background:false,

guides:false,

center:true,

highlight:false,

toggleDragModeOnDblclick:false,

restore:false,

checkCrossOrigin:false,

checkOrientation:false,

wheelZoomRatio:0.06,

minCropBoxWidth:140,
minCropBoxHeight:140,

touchDragZoom:true,
zoomOnTouch:true,
zoomOnWheel:false

});

});

}

/* =========================================================
   ZOOM
========================================================= */

function zoomIn(){

if(cropper){
cropper.zoom(0.08);
}

}

function zoomOut(){

if(cropper){
cropper.zoom(-0.08);
}

}

/* =========================================================
   RECORTAR
========================================================= */

function recortarImagen(){

if(!cropper || !inputActual) return;

const canvas = cropper.getCroppedCanvas({

width:400,
height:400,

imageSmoothingEnabled:true,
imageSmoothingQuality:'high'

});

canvas.toBlob((blob) => {

if(!blob) return;

imagenFinalBlob = blob;

const file = new File(
[blob],
"perfil.webp",
{
type:"image/webp"
}
);

const dataTransfer = new DataTransfer();

dataTransfer.items.add(file);

inputActual.files = dataTransfer.files;

/* 🔥 previews */
actualizarPreview(inputActual.id, blob);

/* 🔥 cerrar */
cerrarCrop();

}, "image/webp", 0.92);

}

/* =========================================================
   PREVIEW
========================================================= */

function actualizarPreview(id, blob){

const isAdmin = id === "inputFotoAdmin";

const preview = isAdmin
? $("#previewAdminFoto")
: $("#previewUsuarioFoto");

const container = isAdmin
? $("#previewAdminContainer")
: $("#previewUsuarioContainer");

if(!preview || !container) return;

/* 🔥 limpiar anterior */
if(preview.dataset.url){
URL.revokeObjectURL(preview.dataset.url);
}

const blobURL = URL.createObjectURL(blob);

preview.src = blobURL;

preview.dataset.url = blobURL;

container.style.display = "block";

}

/* =========================================================
   CERRAR CROPPER
========================================================= */

function cerrarCrop(){

const modal = $("#cropModal");

if(modal){
modal.style.display = "none";
}

if(cropper){

cropper.destroy();

cropper = null;

}

}

/* =========================================================
   ELIMINAR FOTO
========================================================= */

function limpiarPreview(inputId, previewId, containerId){

const input = $(inputId);
const preview = $(previewId);
const container = $(containerId);

if(input) input.value = "";

if(preview){

if(preview.dataset.url){
URL.revokeObjectURL(preview.dataset.url);
}

preview.src = "";

}

if(container){
container.style.display = "none";
}

}

function eliminarFotoAdmin(){

limpiarPreview(
"#inputFotoAdmin",
"#previewAdminFoto",
"#previewAdminContainer"
);

}

function eliminarFotoUsuario(){

limpiarPreview(
"#inputFotoUsuario",
"#previewUsuarioFoto",
"#previewUsuarioContainer"
);

}

/* =========================================================
   DROPDOWNS
========================================================= */

function setupDropdown(config){

const {

inputSelector,
dropdownSelector,
hiddenSelector

} = config;

const input = $(inputSelector);
const dropdown = $(dropdownSelector);
const hidden = $(hiddenSelector);

if(!input || !dropdown || !hidden) return;

const options = dropdown.querySelectorAll(".select-option");

/* abrir */

input.addEventListener("focus", () => {

dropdown.style.display = "block";

});

/* filtro optimizado */

input.addEventListener("input", debounce(() => {

const value = input.value.toLowerCase();

options.forEach(opt => {

opt.style.display =
opt.innerText.toLowerCase().includes(value)
? "block"
: "none";

});

}, 80));

/* seleccionar */

options.forEach(opt => {

opt.addEventListener("click", () => {

hidden.value = opt.dataset.id || "";

input.value = opt.innerText.trim();

dropdown.style.display = "none";

});

});

}

/* =========================================================
   FORM VALIDATION
========================================================= */

function validarFormulario(formId){

const form = document.getElementById(formId);

if(!form) return;

form.addEventListener("submit", function(e){

let valido = true;

const inputs = form.querySelectorAll(
"input[type='text'], input[type='email'], input[type='password'], input[type='file'], input[type='number']"
);

inputs.forEach(input => {

const error = input.nextElementSibling;

/* reset */

input.classList.remove("input-error");

if(error){
error.classList.remove("show");
}

/* vacío */

if(!input.value.trim()){

valido = false;

input.classList.add("input-error");

if(error){
error.classList.add("show");
}

return;

}

/* number */

if(input.type === "number"){

if(parseInt(input.value) < 1){

valido = false;

input.classList.add("input-error");

if(error){
error.classList.add("show");
}

}

}

/* email */

if(input.type === "email"){

const regex =
/^[^\s@]+@[^\s@]+\.[^\s@]+$/;

if(!regex.test(input.value)){

valido = false;

input.classList.add("input-error");

if(error){
error.classList.add("show");
}

}

}

});

if(!valido){
e.preventDefault();
}

});

}

/* =========================================================
   MENU
========================================================= */

function toggleMenu(){

const menu = $("#sideMenu");
const overlay = $("#menuOverlay");
const btn = $("#menuBtn");

if(!menu || !overlay || !btn) return;

const isOpen = menu.classList.toggle("open");

overlay.classList.toggle("show");

btn.textContent = isOpen ? "✖" : "☰";

/* 🔥 bloquear scroll */
document.body.style.overflow = isOpen
? "hidden"
: "";

}

/* =========================================================
   SECTION SYSTEM
========================================================= */

function showSection(id){

const sections =
document.querySelectorAll(".section-panel");

sections.forEach(sec => {
sec.classList.remove("active");
});

const target =
document.getElementById(id);

if(target){

target.classList.add("active");

}

/* active menu */

document.querySelectorAll(".menu-link")
.forEach(link => {

link.classList.remove("active");

});

const activeBtn = document.querySelector(
`.menu-link[onclick="showSection('${id}')"]`
);

if(activeBtn){

activeBtn.classList.add("active");

}

/* cerrar menú si está abierto */

const menu = $("#sideMenu");

if(menu && menu.classList.contains("open")){
toggleMenu();
}

/* scroll */

window.scrollTo({
top:0,
behavior:'smooth'
});

}

/* =========================================================
   INIT
========================================================= */

window.addEventListener("DOMContentLoaded", () => {

/* default section */

const firstSection =
document.querySelector(".section-panel");

if(firstSection){

firstSection.classList.add("active");

const firstBtn = document.querySelector(
`.menu-link[onclick="showSection('${firstSection.id}')"]`
);

if(firstBtn){
firstBtn.classList.add("active");
}

}

/* dropdowns */

setupDropdown({
inputSelector:"#searchInput",
dropdownSelector:"#dropdown",
hiddenSelector:"#realSelect"
});

setupDropdown({
inputSelector:"#searchAdminInput",
dropdownSelector:"#adminDropdown",
hiddenSelector:"#realAdminSelect"
});

setupDropdown({
inputSelector:"#searchUserProfileInput",
dropdownSelector:"#userProfileDropdown",
hiddenSelector:"#realUserProfileSelect"
});

/* forms */

validarFormulario("formCreateUser");
validarFormulario("formCreateAdmin");

});

/* =========================================================
   CLICK OUTSIDE
========================================================= */

document.addEventListener("click", (e) => {

/* dropdowns */

document.querySelectorAll(".custom-select")
.forEach(select => {

const dropdown =
select.querySelector(".select-dropdown");

if(
dropdown &&
!select.contains(e.target)
){

dropdown.style.display = "none";

}

});

/* overlay */

if(e.target.id === "menuOverlay"){

toggleMenu();

}

});

/* =========================================================
   CLEAN MEMORY
========================================================= */

window.addEventListener("beforeunload", () => {

document.querySelectorAll("img").forEach(img => {

if(
img.src &&
img.src.startsWith("blob:")
){

URL.revokeObjectURL(img.src);

}

});

});

</script>

</body>
</html>