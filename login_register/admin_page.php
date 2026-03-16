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
   ACCIONES
===================== */

// =====================
// SOLICITUDES AYUDANTE
// =====================
if ($adminLevel === 'normal') {

    if (isset($_POST['update_account'])) {
        $uid = (int)$_POST['user_id'];
        $conn->query("
            INSERT INTO admin_requests (user_id, action, requested_by)
            VALUES ($uid, 'update', $adminId)
        ");
        header("Location: admin_page.php");
        exit();
    }

    if (isset($_POST['reactivate_account'])) {
        $uid = (int)$_POST['user_id'];
        $conn->query("
            INSERT INTO admin_requests (user_id, action, requested_by)
            VALUES ($uid, 'reactivate', $adminId)
        ");
        header("Location: admin_page.php");
        exit();
    }
}

/* =====================
   APROBACIONES SUPER
===================== */
if ($adminLevel === 'super') {

    if (isset($_POST['approve_request'])) {
        $rid = (int)$_POST['request_id'];

        $req = $conn->query("
            SELECT * FROM admin_requests 
            WHERE id=$rid AND status='pending'
        ")->fetch_assoc();

        if ($req) {
            $uid = $req['user_id'];

            $conn->query("
                UPDATE users
                SET status='active',
                    paid_until=DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                WHERE id=$uid
            ");

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
// CREAR ADMIN AYUDANTE
// =====================
if (isset($_POST['create_helper']) && $adminLevel === 'super') {

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $quota = (int)$_POST['quota']; // NUEVO

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

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$check = $stmt->get_result();

    if ($check->num_rows === 0) {

        $stmt = $conn->prepare("
            INSERT INTO users
            (name,email,password,role,admin_level,status,user_quota,created_by,created_by_admin,created_at,foto)
            VALUES (?, ?, ?, 'admin', 'normal', 'active', ?, 'admin', ?, NOW(), ?)
        ");

        $stmt->bind_param("sssiss", $name, $email, $pass, $quota, $adminId, $rutaFoto);
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
            (name,email,password,role,status,created_by,created_by_admin,created_at,foto)
            VALUES (?, ?, ?, 'user', 'pending', 'admin', ?, NOW(), ?)
        ");

        $stmt->bind_param("sssis", $name, $email, $pass, $adminId, $rutaFoto);
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
            (name,email,password,role,status,created_by,created_by_admin,created_at,paid_until,foto)
            VALUES (?, ?, ?, 'user', 'active', 'admin', ?, NOW(),
                    DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?)
        ");

        $stmt->bind_param("sssis", $name, $email, $pass, $adminId, $rutaFoto);
        $stmt->execute();

        $_SESSION['msg'] = "Usuario creado con éxito";
        $_SESSION['msg_type'] = "success";
    }

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
// BORRAR USUARIO
// =====================
if (isset($_POST['delete_user']) && $adminLevel === 'super') {
    $id = (int)$_POST['user_id'];
    $conn->query("DELETE FROM users WHERE id=$id AND role='user'");
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
            SELECT r.*, u.name AS user_name
            FROM admin_requests r
            JOIN users u ON u.id = r.user_id
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
<link rel="icon" type="image/png" href="Logo Poster MovieTx PNG/Logo MovieTx.png">
<title>Panel Administrador</title>

<style>

/* ===== RESET GLOBAL ===== */
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

html{
    -webkit-tap-highlight-color:transparent;
}

body{
    font-family:Arial, sans-serif;
    background:linear-gradient(135deg,#eef2f7,#e6ebf2);
    color:#111827;
}

/* ===== HEADER ===== */
header{
    background:linear-gradient(135deg,#0f172a,#1e293b);
    color:white;
    padding:22px 15px;
    text-align:center;
    box-shadow:0 8px 24px rgba(0,0,0,.25);
}

/* ===== TITLES ===== */
h3{
    margin-bottom:14px;
    color: #ff0000;
}

/* ===== CARDS ===== */
.box{
    background:white;
    padding:22px;
    margin-bottom:26px;
    border-radius:18px;
    border:1px solid #e5e7eb;
    box-shadow:0 12px 28px rgba(0,0,0,.07);
    transition:.25s ease;
}

.box:hover{
    transform:translateY(-6px);
    box-shadow:0 20px 38px rgba(0,0,0,.12);
}

/* ===== INPUTS ===== */
input{
    width:100%;
    padding:14px;
    margin:6px 0;
    border-radius:12px;
    border:1px solid #d1d5db;
    font-size:15px;
}

input:focus{
    outline:none;
    border-color:#2563eb;
    box-shadow:0 0 0 4px rgba(37,99,235,.15);
}

/* ===== BUTTONS BASE ===== */
button{
    padding:12px 16px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:.2s;
    letter-spacing:.3px;
    white-space:nowrap;
}

button:hover{
    transform:translateY(-2px);
    opacity:.95;
}

button:active{
    transform:scale(.98);
}

button.red{
    background:linear-gradient(135deg,#ef4444,#dc2626);
}

button.gray{
    background:linear-gradient(135deg,#9ca3af,#6b7280);
}

button.green{
    background:linear-gradient(135deg,#22c55e,#16a34a);
}

/* ===== ACTION BUTTONS CONTAINER ===== */
.actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}

.actions button{
    flex:1 1 auto;
    min-width:90px;
    padding:10px 12px;
    font-size:14px;
}

/* ===== TABLE ===== */
.table-wrap{
    overflow-x:auto;
    border-radius:18px;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 10px 26px rgba(0,0,0,.08);
}

th{
    background:#0f172a;
    color:white;
    padding:14px;
    font-size:14px;
    text-align:center;
}

td{
    padding:14px;
    border-bottom:1px solid #e5e7eb;
    font-size:14px;
    vertical-align:middle;
    text-align:center;
    word-break:break-word;
}

th:nth-child(1){width:160px;}
th:nth-child(2){width:240px;}
th:nth-child(3){width:80px;}
th:nth-child(4){width:80px;}
th:nth-child(5){width:100px;}
th:nth-child(6){width:120px;}

td form{
    display:flex;
    justify-content:center;
}


tr:hover{
    background:#f8fafc;
}

/* ===== STATUS ===== */
.green-text{
    color:#16a34a;
    font-weight:bold;
}

.red-text{
    color:#dc2626;
    font-weight:bold;
}

/* ===== ADMIN HEADER ===== */
.admin-header{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:16px;
    flex-wrap:wrap;
}

.admin-logo{
    width:100px;
    height:100px;
    border-radius:50%;
    object-fit:cover;
    border:4px solid #2563eb;
    box-shadow:0 0 24px rgba(37,99,235,.5);
}

/* ===== LINKS ===== */
.link-action{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:12px 16px;
    border-radius:12px;
    font-weight:bold;
    text-decoration:none;
}

.link-back{
    background:#6b7280;
    color:white;
}

.link-logout{
    background:#dc2626;
    color:white;
}

/* ===== HAMBURGER ===== */
/* ===== BOTÓN HAMBURGUESA ===== */
.hamburger-btn{
    position:fixed;
    top:16px;
    right:16px;
    width:48px;
    height:48px;
    border-radius:50%;
    background:#0f172a;
    color:white;
    font-size:24px;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 6px 18px rgba(0,0,0,.35);
    cursor:pointer;
    z-index:10000;
    transition:0.2s;
}

.hamburger-btn:hover{
    transform:scale(1.05);
}

/* ===== BOTÓN CERRAR MENÚ ===== */
.side-menu .close-btn{
    position:absolute;
    top:16px;
    right:16px;
    width:36px;
    height:36px;
    border:none;
    border-radius:50%;
    background:#dc2626;
    color:white;
    font-size:20px;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    box-shadow:0 4px 12px rgba(0,0,0,.3);
    transition:0.2s;
    z-index:10001;
}

.side-menu .close-btn:hover{
    background:#ef4444;
    transform:scale(1.05);
}

.side-menu .close-btn:active{
    transform:scale(.95);
}

/* ===== SIDE MENU ===== */
.side-menu{
    position:fixed;
    top:0;
    right:-340px;
    width:320px;
    height:100%;
    background:linear-gradient(180deg,#020617,#0f172a);
    color:white;
    padding:26px;
    transition:.35s;
    z-index:9998;
}

.side-menu.open{
    right:0;
}

/* ===== RESPONSIVE ===== */
@media (max-width:768px){
    .hamburger-btn{ width:48px; height:48px; font-size:24px; top:8px; right:8px; }
    .side-menu{ padding-top:100px; }
    .side-menu .close-btn{ width:36px; height:36px; font-size:20px; top:12px; right:12px; }
}

@media (min-width:769px) and (max-width:1024px){
    .hamburger-btn{ width:44px; height:44px; font-size:22px; }
    .side-menu .close-btn{ width:34px; height:34px; font-size:18px; }
}

@media (min-width:1025px){
    .hamburger-btn{ width:42px; height:42px; font-size:20px; top:16px; right:16px; }
    .side-menu{ width:280px; }
    .side-menu .close-btn{ width:32px; height:32px; font-size:18px; top:16px; right:16px; }
}

/* ===== VALIDACIÓN FORM ===== */
.input-error{
    border:2px solid #ef4444 !important;
    background:#fff1f2;
}

.error-text{
    color:#dc2626;
    font-size:13px;
    margin-top:4px;
    display:none;
}

.error-text.show{
    display:block;
}


/* ===== FORMULARIOS PANEL ===== */
.form-panel{
    width:100%;
    max-width:420px;
    margin:auto;
}

/* en móviles ocupar todo el ancho */
@media (max-width:768px){
    .form-panel{
        max-width:100%;
    }
}


/* ===== TOAST MENSAJES ===== */
.toast{
    position:fixed;
    top:20px;
    left:50%;
    transform:translateX(-50%);
    padding:14px 22px;
    border-radius:14px;
    font-weight:bold;
    color:white;
    z-index:20000;
    box-shadow:0 12px 30px rgba(0,0,0,.25);
    animation:fadeSlide 0.5s ease, hideToast 4s forwards;
}

/* colores */
.toast.success{ background:linear-gradient(135deg,#22c55e,#16a34a); }
.toast.error{ background:linear-gradient(135deg,#ef4444,#dc2626); }

/* animación entrada */
@keyframes fadeSlide{
    from{ opacity:0; transform:translate(-50%,-20px); }
    to{ opacity:1; transform:translate(-50%,0); }
}

/* desaparecer automático */
@keyframes hideToast{
    0%,80%{ opacity:1; }
    100%{ opacity:0; top:0; }
}

/* ===== MENU TITLE ===== */
.menu-title{
    font-size:20px;
    font-weight:bold;
    text-align:center;
    margin-bottom:10px;
}

/* ===== MENU DIVIDER ===== */
.menu-divider{
    border:none;
    height:1px;
    background:#374151;
    margin:14px 0;
}

/* ===== MENU SECTION ===== */
.menu-section{
    font-size:12px;
    text-transform:uppercase;
    color:#9ca3af;
    margin-top:16px;
    margin-bottom:6px;
    letter-spacing:1px;
}

/* ===== MENU LINKS ===== */
.menu-link{
    display:flex;
    align-items:center;
    gap:10px;
    padding:14px 16px;
    margin:6px 0;
    background:#1e293b;
    border-radius:12px;
    color:white;
    text-decoration:none;
    font-size:14px;
    transition:0.25s;
    border:1px solid transparent;
}

/* hover */
.menu-link:hover{
    background:#2563eb;
    transform:translateX(4px);
    border-color:#3b82f6;
}

/* active click */
.menu-link:active{
    transform:scale(.96);
}

/* refresh button */
.refresh-btn{
    background:#1e90ff;
}

/* logout */
.logout-btn{
    background:#dc2626;
}

.logout-btn:hover{
    background:#ef4444;
}


/* ===== PANELS ===== */
.section-panel{
    display:none;
}

.section-panel.active{
    display:block;
}

/* ================= MOBILE ================= */
/* ================= MOBILE ================= */
@media (max-width:768px){

    /* Header centrado en columna */
    .admin-header{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        gap:10px;
    }

    /* Foto más grande y arriba */
    .admin-logo{
        width:100px;
        height:100px;
        margin-bottom:6px; /* espacio entre foto y texto */
    }

    /* Ajustar nombre y rol para que no se tapen */
    header h2, header p{
        text-align:center;
        font-size:16px;
        margin:2px 0;
        z-index:1; /* asegurar que esté sobre fondo */
    }

    /* Mover botón hamburguesa hacia la derecha sin tapar el header */
    .hamburger-btn{
        top:8px;
        right:8px;
        width:48px;
        height:48px;
        font-size:24px;
        z-index:10000; /* sobre todo */
    }

    /* Evitar que el menú tape al header */
    .side-menu{
        top:0;
        padding-top:100px; /* espacio para header */
    }
}

/* ===== TABLET ===== */
@media (min-width:769px) and (max-width:1024px){

    .box{
        padding:20px;
    }

    .actions button{
        min-width:110px;
    }
}

/* ===== DESKTOP NORMAL ===== */
@media (min-width:1025px){

    /* Reducir tamaño del botón hamburguesa en PC */
    .hamburger-btn{
        width:42px;
        height:42px;
        font-size:20px;
        top:16px;
        right:16px;
    }

    /* Reducir tamaño de los links del menú lateral en PC */
    .menu-link{
        padding:10px 14px;
        margin:8px 0;
        font-size:14px;
    }

    /* Opcional: ancho del side-menu un poco más ancho */
    .side-menu{
        width:280px;
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
onchange="document.getElementById('formFotoAdmin').submit()"
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

<label>Foto del administrador:</label>
<input type="file" name="foto" accept="image/*">
<div class="error-text">Debe subir una foto</div>

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

  <label>Foto del usuario:</label>
  <input type="file" name="foto" accept="image/*">
  <div class="error-text">Debe subir una foto</div>

  <button type="submit" name="create_user">Crear cuenta</button>
</form>
</div>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="admins">
<h3>Administradores Ayudantes</h3>
<div class="table-wrap">
<table>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Cupos</th>
<th>Usuarios</th>
<th>Comisión</th>
<th>Acción</th>
</tr>
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
<td data-label="Cupos"><?= $h['user_quota'] ?></td>
<td data-label="Usuarios"><?= $count ?></td>
<td data-label="Comisión">$<?= $count * 200 ?></td>
<td data-label="Acciones">

<div class="actions">

<form method="post">
<input type="hidden" name="helper_id" value="<?=$h['id']?>">

<button class="gray" name="toggle_helper">
<?= $h['status']==='active' ? 'Suspender' : 'Activar' ?>
</button>

</form>


<form method="post">
<input type="hidden" name="helper_id" value="<?=$h['id']?>">
<button class="red" name="delete_helper">Borrar</button>
</form>
</td>
</tr>

</div>

</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>
<?php endif; ?>

<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="addQuota">

<h3>Administrar cupos de ayudantes</h3>

<form method="post" class="form-panel">

<label>Seleccionar administrador ayudante</label>

<select name="helper_id" required style="width:100%;padding:12px;border-radius:10px;margin-bottom:12px">

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
- Cupos actuales: <?=$h['user_quota']?>
</option>

<?php endwhile; ?>

</select>

<label>Cantidad</label>

<input
type="number"
name="quota_value"
min="1"
placeholder="Ej: 5"
required
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



<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="changeUserPass">

<h3>Cambiar contraseña de usuarios</h3>

<form method="post" style="max-width:420px">

<label>Seleccionar usuario</label>

<select name="user_id" required style="width:100%;padding:12px;border-radius:10px;margin-bottom:12px">

<option value="">Seleccionar</option>

<?php
$listUsers = $conn->query("
SELECT id,name,email
FROM users
WHERE role='user'
ORDER BY name
");

while($u=$listUsers->fetch_assoc()):
?>

<option value="<?=$u['id']?>">
<?=htmlspecialchars($u['name'])?> 
(<?=htmlspecialchars($u['email'])?>)
</option>

<?php endwhile; ?>

</select>

<label>Nueva contraseña</label>

<input
type="password"
name="new_password"
placeholder="Nueva contraseña"
required
>

<button name="change_password" class="green">
Actualizar contraseña
</button>

</form>

</div>
<?php endif; ?>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="changeAdminPass">

<h3>Cambiar contraseña de administradores ayudantes</h3>

<form method="post" style="max-width:420px">

<label>Seleccionar administrador</label>

<select name="helper_id" required style="width:100%;padding:12px;border-radius:10px;margin-bottom:12px">

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
<?=htmlspecialchars($a['name'])?> 
(<?=htmlspecialchars($a['email'])?>)
</option>

<?php endwhile; ?>

</select>

<label>Nueva contraseña</label>

<input
type="password"
name="new_password"
placeholder="Nueva contraseña"
required
>

<button name="change_helper_password" class="green">
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
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Estado</th>
<th>Expira</th>
<th>Acciones</th>
</tr>

<?php while($u = $usersBySuper->fetch_assoc()): ?>
<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>
<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>
<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

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
</div>
<?php endif; ?>


<?php if ($adminLevel === 'super'): ?>
<div class="box section-panel" id="usersHelpers">
<h3>Usuarios creados por Administradores Ayudantes</h3>
<div class="table-wrap">
<table>
<tr>
<th>Nombre</th>
<th>Email</th>
<th>Creado por</th>
<th>Estado</th>
<th>Expira</th>
<th>Actualizar contraseña</th>
<th>Acciones</th>
</tr>

<?php while($u = $usersByHelpers->fetch_assoc()): ?>
<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>
<td data-label="Creado por"><?=htmlspecialchars($u['admin_name'])?></td>

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
</div>
<?php endif; ?>


<?php if ($adminLevel==='super'): ?>
<div class="box section-panel" id="requests">
<h3>Solicitudes pendientes</h3>
<table>
<tr>
<th>Usuario</th>
<th>Acción</th>
<th>Fecha</th>
<th>Opciones</th>
</tr>

<?php while($r=$requests->fetch_assoc()): ?>
<tr>
<td><?=$r['user_name']?></td>
<td><?=$r['action']?></td>
<td><?=$r['created_at']?></td>
<td>
<form method="post">
<input type="hidden" name="request_id" value="<?=$r['id']?>">
<button class="green" name="approve_request">Aceptar</button>
<button class="red" name="reject_request">Rechazar</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</table>
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
<th>Estado</th>
<th>Expira</th>
<th>Actualizar contraseña</th>
<th>Acciones</th>
</tr>

<?php while($u = $usersBySelectedHelper->fetch_assoc()): ?>
<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>

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


<div class="box section-panel" id="allUsers">
<h3>Usuarios</h3>
<div class="table-wrap">
<table>
<tr>
<th>Nombre</th>
<th>Email</th>
<?php if ($adminLevel==='super'): ?><th>Creado por</th><?php endif; ?>
<th>Estado</th>
<th>Expira</th>
<th>Actualizar contraseña</th>
<th>Acciones</th>
</tr>

<?php while($u=$users->fetch_assoc()): ?>
<tr>
<td data-label="Nombre"><?=htmlspecialchars($u['name'])?></td>
<td data-label="Email"><?=htmlspecialchars($u['email'])?></td>

<?php if ($adminLevel==='super'): ?>
<td data-label="Creado por"><?=htmlspecialchars($u['admin_name'] ?? 'Principal')?></td>
<?php endif; ?>

<td data-label="Estado" class="<?= $u['status']==='active'?'green-text':'red-text' ?>">
<?= $u['status'] ?>
</td>

<td data-label="Expira"><?= $u['paid_until'] ?? '-' ?></td>

<td data-label="Actualizar contraseña">
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<input type="password" name="new_password" placeholder="Nueva contraseña" required>
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

<?php if ($adminLevel==='super'): ?>
<form method="post">
<input type="hidden" name="user_id" value="<?=$u['id']?>">
<button class="red" name="delete_user">Borrar</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>
</div>
</div>


</div>

<script>
function validarFormulario(formId){

    const form = document.getElementById(formId);

    form.addEventListener("submit", function(e){

        let valido = true;

        const inputs = form.querySelectorAll("input[type='text'], input[type='email'], input[type='password'], input[type='file']");

        inputs.forEach(input => {

            const error = input.nextElementSibling;

            // reset
            input.classList.remove("input-error");
            if(error) error.classList.remove("show");

            // validar vacío
            if(!input.value){
                valido = false;
                input.classList.add("input-error");
                if(error) error.classList.add("show");
            }

            // validar email
            if(input.type === "email" && input.value){
                const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if(!regex.test(input.value)){
                    valido = false;
                    input.classList.add("input-error");
                    if(error) error.classList.add("show");
                }
            }
        });

        if(!valido){
            e.preventDefault();
        }
    });
}

// aplicar a ambos formularios
validarFormulario("formCreateUser");
validarFormulario("formCreateAdmin");
</script>



<script>
function toggleMenu(){
    const menu = document.getElementById("sideMenu");
    const overlay = document.getElementById("menuOverlay");
    const btn = document.getElementById("menuBtn");

    const isOpen = menu.classList.toggle("open");
    overlay.classList.toggle("show");

    // 🔄 Cambiar icono
    btn.textContent = isOpen ? "✖" : "☰";
}


function showSection(id){

    document.querySelectorAll(".section-panel")
        .forEach(sec => sec.classList.remove("active"));

    const target = document.getElementById(id);
    if(target) target.classList.add("active");

    toggleMenu(); // cerrar menú
}
window.addEventListener("DOMContentLoaded", () => {
    const first = document.querySelector(".section-panel");
    if(first) first.classList.add("active");
});

</script>

</body>
</html>