<?php
session_start();
require_once 'config.php';

/* =========================
   VALIDAR SESIÓN
========================= */

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$userId = (int) $_SESSION['id'];

/* =========================
   OBTENER USUARIO COMPLETO
========================= */

$stmt = $conn->prepare("SELECT id, name, email, foto, status, paid_until, theme FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$theme = $user['theme'] ?? 'light';

// 🚨 Si el usuario NO existe en base
if (!$user) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

/* =========================
   VALIDAR ESTADO
========================= */

if ($user['status'] !== 'active') {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

/* =========================
   🔥 VALIDAR PLAN (FIX TOTAL)
========================= */

// ❌ PLAN CANCELADO (paid_until NULL)
if (empty($user['paid_until'])) {

    $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?plan=cancelado");
    exit();
}

// ❌ PLAN VENCIDO
if (strtotime($user['paid_until']) < time()) {

    $stmt = $conn->prepare("UPDATE users SET status='suspended' WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php?expired=1");
    exit();
}

/*=======================
  CONTINUAR VIENDO PELÍCULAS
========================*/
$stmt_peliculas = $conn->prepare("
    SELECT pelicula_id AS id, titulo, imginicio AS imagen, progreso, fecha, 'pelicula' AS tipo
    FROM continuar_viendo 
    WHERE user_id=? 
");
$stmt_peliculas->bind_param("i", $userId);
$stmt_peliculas->execute();
$result_peliculas = $stmt_peliculas->get_result();

$stmt_series = $conn->prepare("
    SELECT serie_id AS id, titulo, imgserie AS imagen, progreso, fecha, 'serie' AS tipo
    FROM continuar_serie
    WHERE user_id=? 
");
$stmt_series->bind_param("i", $userId);
$stmt_series->execute();
$result_series = $stmt_series->get_result();

$items = [];

// meter series
while($row = $result_series->fetch_assoc()){
    $items[] = $row;
}

// meter peliculas
while($row = $result_peliculas->fetch_assoc()){
    $items[] = $row;
}

// ordenar por fecha DESC
usort($items, function($a, $b){
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// limitar a 20
$items = array_slice($items, 0, 20);

/* =========================
   DATOS DEL USUARIO
========================= */

$nombre = $user['name'] ?? 'Usuario';
$email  = $user['email'] ?? '';
$foto   = !empty($user['foto']) ? $user['foto'] : 'Logo Poster MovieTx PNG/Logo MovieTx.png';

/* =========================
   PERFIL SELECCIONADO
========================= */

// 🔥 PERFIL ACTIVO
if(isset($_SESSION['perfil_id'])){

    $perfilId = $_SESSION['perfil_id'];

    $stmtPerfil = $conn->prepare("SELECT nombre, foto FROM perfiles WHERE id=? AND user_id=?");
    $stmtPerfil->bind_param("ii", $perfilId, $userId);
    $stmtPerfil->execute();
    $resPerfil = $stmtPerfil->get_result();

    if($resPerfil->num_rows > 0){
        $perfil = $resPerfil->fetch_assoc();
        $nombre = $perfil['nombre'];
        $foto   = "uploads/perfiles/".$perfil['foto'];
    } else {
        unset($_SESSION['perfil_id']);
    }

}

/* =========================
   VERIFICACIÓN AJAX (FIX)
========================= */

if (isset($_GET['check_status'])) {

    $stmt = $conn->prepare("SELECT status, paid_until FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (
        !$data ||
        $data['status'] !== 'active' ||
        empty($data['paid_until']) ||
        strtotime($data['paid_until']) < time()
    ) {
        session_unset();
        session_destroy();
        echo "logout";
    } else {
        echo "ok";
    }

    exit();
}

/* =========================
   GUARDAR TEMA (AJAX)
========================= */

if (isset($_POST['theme'])) {

    $theme = $_POST['theme'];

    $allowedThemes = ['light', 'dark', 'blue', 'sky', 'red', 'pink'];

    if (in_array($theme, $allowedThemes)) {

        $stmt = $conn->prepare("UPDATE users SET theme=? WHERE id=?");
        $stmt->bind_param("si", $theme, $userId);
        $stmt->execute();

        echo "ok";
    } else {
        echo "error";
    }

    exit();
}

/* =========================
   CAMBIAR CONTRASEÑA
========================= */

if (isset($_POST['change_password'])) {

    $newPass = $_POST['new_password'] ?? '';

    if (strlen($newPass) >= 6) {

        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hash, $userId);
        $stmt->execute();

        header("Location: inicio.php?pass=ok");
        exit();
    }
}

/* =========================
   ELIMINAR CUENTA
========================= */

if (isset($_POST['delete_account'])) {

    $fotoActual = $user['foto'] ?? '';

    if (!empty($fotoActual) && $fotoActual !== 'Logo Poster MovieTx PNG/Logo MovieTx.png') {
        if (file_exists($fotoActual)) {
            unlink($fotoActual);
        }
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

/* =========================
   ACTUALIZAR FOTO (PRO)
========================= */

if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {

    $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $nombreArchivo = uniqid() . "." . $extension;

    if (isset($_SESSION['perfil_id'])) {

        $perfilId = $_SESSION['perfil_id'];

        $stmt = $conn->prepare("SELECT foto FROM perfiles WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $perfilId, $userId);
        $stmt->execute();
        $perfil = $stmt->get_result()->fetch_assoc();

        if ($perfil) {

            $fotoActual = "uploads/perfiles/" . $perfil['foto'];

            if (!empty($perfil['foto']) && file_exists($fotoActual)) {
                unlink($fotoActual);
            }

            if (!is_dir("uploads/perfiles/")) {
                mkdir("uploads/perfiles/", 0755, true);
            }

            $rutaDestino = "uploads/perfiles/" . $nombreArchivo;

            move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);

            $stmt = $conn->prepare("UPDATE perfiles SET foto=? WHERE id=? AND user_id=?");
            $stmt->bind_param("sii", $nombreArchivo, $perfilId, $userId);
            $stmt->execute();
        }

    } else {

        $fotoActual = $user['foto'] ?? '';

        if (!empty($fotoActual) && $fotoActual !== 'Logo Poster MovieTx PNG/Logo MovieTx.png') {
            if (file_exists($fotoActual)) {
                unlink($fotoActual);
            }
        }

        if (!is_dir("uploads/usuarios/")) {
            mkdir("uploads/usuarios/", 0755, true);
        }

        $rutaDestino = "uploads/usuarios/" . $nombreArchivo;

        move_uploaded_file($_FILES['foto']['tmp_name'], $rutaDestino);

        $stmt = $conn->prepare("UPDATE users SET foto=? WHERE id=?");
        $stmt->bind_param("si", $rutaDestino, $userId);
        $stmt->execute();
    }

    header("Location: inicio.php");
    exit();
}

?>

<!DOCTYPE html>

<html dir="ltr" lang="en">
<head>
<title>MovieTx- Inicio</title>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1, user-scalable=1, minimum-scale=1, maximum-scale=5" name="viewport"/>
<meta content="IE=edge" http-equiv="X-UA-Compatible"/>
<meta content="max-image-preview:large" name="robots"/>
<link href="" rel="stylesheet"/>
<script src="https://code.iconify.design/2/2.1.0/iconify.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=PT+Sans&amp;display=swap" rel="stylesheet"/>
<link rel="icon" type="image/png" href="Logo Poster MovieTx PNG/Logo MovieTx.png">
<link href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" rel="stylesheet"/>
<style>
    .alignright,
.alignleft {
    position: relative;
    padding: 6px 14px;
    border-radius: 14px;
    cursor: pointer;
    overflow: hidden;
    z-index: 1;
    color: var(--text);
}

/* TEXTO */
.alignright span,
.alignleft span {
    position: relative;
    z-index: 3;
}

/* 🌈 BORDE ARCOIRIS SUAVE */
.alignright::before,
.alignleft::before {
    content: "";
    position: absolute;
    inset: -2px;
    border-radius: 14px;

    background: conic-gradient(
        from 0deg,
        #ff0000,
        #ff7300,
        #fffb00,
        #48ff00,
        #00f7ff,
        #0066ff,
        #a200ff,
        #ff0000
    );

    animation: giroRGB 6s linear infinite;
    z-index: 0;
}

/* FONDO INTERNO (LIMPIO) */
.alignright::after,
.alignleft::after {
    content: "";
    position: absolute;
    inset: 2px;
    background: var(--bg);
    border-radius: 12px;
    z-index: 1;
}

/* HOVER SUAVE */
.alignright:hover,
.alignleft:hover {
    transform: scale(1.05);
}

/* ANIMACIÓN SUAVE */
@keyframes giroRGB {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

        </style>
<style>
    /* Variable color */
:root{
    --headC: #000000 ;
    --bodyC: #000000 ;
    --bodyCa: #767676 ;
    --bodyB: #fffdfc ;
    --linkC: #0b57cf ;
    --linkB: #0b57cf ;
    --iconC: #000000 ;
    --iconCa: #000000 ;
    --iconCs: #767676 ;
    --headerC: #000000 ;
    --headerT: 16px ;
    --headerW: 400 ; /* write 400(normal) or 700(bold) */
    --headerB: #fffdfc ;
    --headerL: 1px ;
    --headerI: #000000 ;
    --headerH: 60px ;
    --headerHi: -60px ;
    --headerHm: 60px ;
    --notifH: 53px ;
    --notifU: #e8f0fe ;
    --notifC: #000000 ;
    --notifL: #0b57cf ;
    --contentB: #fffdfc ;
    --contentL: #e6e6e6 ;
    --contentW: 1280px ;
    --sideW: 300px ;
    --transB: rgba(0,0,0,.05);
    --pageW: 780px ;
    --pageW: 780px ;
    --postT: 36px ;
    --postF: 16px ;
    --postTm: 28px ;
    --postFm: 15px ;
    --widgetT: 15px ;
    --widgetTw: 400 ; /* write 400(normal) or 700(bold) */
    --widgetTa: 25px ;
    --widgetTac: #989b9f;
    --navW: 230px ;
    --navT: #000000 ;
    --navI: #000000 ;
    --navB: #fffdfc ;
    --navL: 1px ;
    --srchI: #08102b ;
    --srchB: #fffdfc ;
    --mobT: #000000 ;
    --mobHv: #f1f1f0 ;
    --mobB: #fffdfc ;
    --mobL: 0px ;
    --mobBr: 12px ;
    --fotT: #000000 ;
    --fotB: #fffdfc ;
    --fotL: 1px ;
    --fontH: 'Noto Sans', sans-serif ;
    --fontB: 'Noto Sans', sans-serif ;
    --fontBa: 'Noto Sans', sans-serif ;
    --fontC: 'Fira Mono', monospace ;
    --trans-1: all .1s ease ;
    --trans-2: all .2s ease ;
    --trans-4: all .4s ease ;
    --synxBg: #f6f6f6 ;
    --synxC: #000000 ;
    --synxOrange: #b75501 ;
    --synxBlue: #015692 ;
    --synxGreen: #54790d ;
    --synxRed: #f15a5a ;
    --synxGray: #656e77 ;
    --darkT: #fffdfc ;
    --darkTa: #989b9f ;
    --darkU: #000000 ;
    --darkB: #000000 ;
    --darkBa: #000000 ;
    --darkBs: #000000 ;
    }
    
    /* Font Body and Heading */ @font-face{font-family: 'Noto Sans';font-style: italic;font-weight: 400;font-display: swap;src: url(https://fonts.gstatic.com/s/notosans/v11/o-0OIpQlx3QUlC5A4PNr4ARMQ_m87A.woff2) format('woff2'), url(https://fonts.gstatic.com/s/notosans/v11/o-0OIpQlx3QUlC5A4PNr4DRG.woff) format('woff')} @font-face{font-family: 'Noto Sans';font-style: italic;font-weight: 700;font-display: swap;src: url(https://fonts.gstatic.com/s/notosans/v11/o-0TIpQlx3QUlC5A4PNr4Az5ZuyDzW0.woff2) format('woff2'), url(https://fonts.gstatic.com/s/notosans/v11/o-0TIpQlx3QUlC5A4PNr4Az5ZtyH.woff) format('woff')} @font-face{font-family: 'Noto Sans';font-style: normal;font-weight: 400;font-display: swap;src: url(https://fonts.gstatic.com/s/notosans/v11/o-0IIpQlx3QUlC5A4PNr5TRA.woff2) format('woff2'), url(https://fonts.gstatic.com/s/notosans/v11/o-0IIpQlx3QUlC5A4PNb4Q.woff) format('woff')} @font-face{font-family: 'Noto Sans';font-style: normal;font-weight: 700;font-display: swap;src: url(https://fonts.gstatic.com/s/notosans/v11/o-0NIpQlx3QUlC5A4PNjXhFVZNyB.woff2) format('woff2'), url(https://fonts.gstatic.com/s/notosans/v11/o-0NIpQlx3QUlC5A4PNjXhFlYA.woff) format('woff')}
    
    /* Source Code Font */ @font-face {font-family: 'Fira Mono';font-style: normal;font-weight: 400;font-display: swap;src: local('Fira Mono Regular'), local('FiraMono-Regular'), url(https://fonts.gstatic.com/s/firamono/v9/N0bX2SlFPv1weGeLZDtQIg.woff) format('woff'), url(https://fonts.gstatic.com/s/firamono/v9/N0bX2SlFPv1weGeLZDtgJv7S.woff2) format('woff2')}
    
    /* Standar CSS */ ::selection{color:#fff;background:var(--linkC)} *, ::after, ::before{-webkit-box-sizing:border-box;box-sizing:border-box} h1, h2, h3, h4, h5, h6{margin:0;font-weight:700;font-family:var(--fontH);color:var(--headC)} h1{font-size:1.9rem} h2{font-size:1.7rem} h3{font-size:1.5rem} h4{font-size:1.4rem} h5{font-size:1.3rem} h6{font-size:1.2rem} a{color:var(--linkC);text-decoration:none} a:hover{opacity:.9;transition:opacity .1s} table{border-spacing:0} iframe{max-width:100%;border:0;margin-left:auto;margin-right:auto} input, button, select, textarea{font:inherit;font-size:100%;color:inherit;line-height:normal} input::placeholder{color:rgba(0,0,0,.5)} img{display:block;position:relative;max-width:100%;height:auto} svg{width:22px;height:22px;fill:var(--iconC)} svg.line, svg .line{fill:none!important;stroke:var(--iconC);stroke-linecap:round;stroke-linejoin:round; stroke-width:1} svg.c-1{fill:var(--iconCa)} svg.c-2{fill:var(--iconCs); opacity:.4} .hidden{display:none} .invisible{visibility:hidden} .clear{width:100%;display:block;margin:0;padding:0;float:none;clear:both} .fCls{display:block;position:fixed;top:0;left:0;right:0;bottom:0;z-index:1;transition:var(--trans-1);background:transparent;opacity:0;visibility:hidden} .free::after, .new::after{display:inline-block;content:'Free!';color:var(--linkC);font-size:12px;font-weight:400;margin:0 5px} .new::after{content:'New!'}
    
    /* Main Element */ html{scroll-behavior:smooth;overflow-x:hidden} body{position:relative;margin:0;padding:0!important;width:100%;font-family:var(--fontB);font-size:14px;color:var(--bodyC);background:var(--bodyB);-webkit-font-smoothing: antialiased;} .secIn{margin:0 auto;padding-left:20px;padding-right:20px;max-width:var(--contentW)} /* Notif Section */ .ntfC{display:flex;align-items:center;position:relative;min-height:var(--notifH); background:var(--notifU);color:var(--notifC); padding:10px 25px; font-size:13px; transition:var(--trans-1);overflow:hidden} .ntfC .secIn{width:100%; position:relative} .ntfC .c{display:flex;align-items:center} .ntfT{width:100%; padding-right: 15px; text-align:center} .ntfT a{color:var(--notifL); font-weight:700} .ntfI:checked ~ .ntfC{height:0;min-height:0; padding:0; opacity:0;visibility:hidden} .ntfA{display:inline-flex;align-items:center;justify-content:center;text-align:initial} .ntfA >a{flex-shrink:0;white-space:nowrap;display:inline-block; margin:0 10px;padding:8px 12px;border-radius:3px; background:var(--notifL);color:#fffdfc; font-size:12px;font-weight:400; box-shadow:0 10px 8px -8px rgb(0 0 0 / 12%);text-decoration:none} /* Fixed/Pop-up Element */ .fixL{display:flex;align-items:center;position:fixed;left:0;right:0;bottom:0;margin-bottom:-100%;z-index:20;transition:var(--trans-1);width:100%;height:100%;opacity:0;visibility:hidden} .fixLi, .fixL .cmBri{width:100%;max-width:680px;max-height:calc(100% - 60px);border-radius:12px;transition:inherit;z-index:3;display:flex;overflow:hidden;position:relative;margin:0 auto;box-shadow:0 5px 30px 0 rgba(0,0,0,.05)} .fixLs{padding:60px 20px 20px;overflow-y:scroll;overflow-x:hidden;width:100%;background:var(--contentB)} .fixH, .mnH{display:flex;background:inherit;position:absolute;top:0;left:0;right:0;padding:0 10px;z-index:2} .fixH .cl{padding:0 10px;display:flex;align-items:center;justify-content:flex-end;position:relative;flex-shrink:0;min-width:40px} .fixH .c::after, .ntfC .c::after, .mnH .c::before{content:'\2715';line-height:18px;font-size:14px} .fixT::before{content:attr(data-text);flex-grow:1;padding:16px 10px;font-size:90%;opacity:.7} .fixT .c::before, .mnH .c::after{content:attr(aria-label);font-size:11px;margin:0 8px;opacity:.6} .fixi:checked ~ .fixL, #comment:target .fixL{margin-bottom:0;opacity:1;visibility:visible} .fixi:checked ~ .fixL .fCls, #comment:target .fixL .fCls, .BlogSearch input:focus ~ .fCls{opacity:1;visibility:visible;background:rgba(0,0,0,.2); -webkit-backdrop-filter:saturate(180%) blur(10px); backdrop-filter:saturate(180%) blur(10px)} .shBri{max-width:520px} /* display:flex */ .headI, .bIc{display:flex;align-items:center}
    
    /* Header Section */ header{width:100%;z-index:10; position:-webkit-sticky;position:sticky;top:0; border-bottom:var(--headerL) solid var(--contentL)} header a{display:block;color:inherit} header svg{width:20px;height:20px;fill:var(--headerI); opacity:.8} header svg.line{fill:none;stroke:var(--headerI)} .headCn{position:relative;height:var(--headerH);color:var(--headerC);background:var(--headerB); display:flex} .headL{display:flex;align-items:center;width: var(--navW) ; /* change var(--navW) to increase header title width */ padding:0 0 0 20px; transition:var(--trans-1)} .headL .headIc{flex:0 0 30px} .headL .headN{width:calc(100% - 30px); padding:0 0 0 5px} .headR{padding:0 25px; flex-grow:1; transition:var(--trans-1)} .headI .headP{display:flex;justify-content:flex-end;position:relative} .headI .headS{} .headI{height:100%; justify-content:space-between; position:relative;width:calc(100% + 15px);left:-7.5px;right:-7.5px} .headI >*{margin:0 7.5px} .headIc{font-size:11px;display:flex;list-style:none;margin:0;padding:0} .headIc >*{position:relative} .headIc svg{z-index:1} .headIc .isSrh{display:none} ul.headIc{position:relative;width:calc(100% + 14px);left:-7px;right:-7px;justify-content:flex-end} ul.headIc li{margin:0 2px} .Header{background-repeat:no-repeat;background-size:100%;background-position:center} .Header img{max-width:160px;max-height:45px} .Header .headH{display:block;color:inherit;font-size:var(--headerT); font-weight:var(--headerW)} .Header .headTtl{overflow:hidden;white-space:nowrap;text-overflow:ellipsis; display:block} /* Icon */ .tIc{width:30px;height:30px;justify-content:center} .tIc::after{content:'';background:var(--transB);border-radius:12px;position:absolute;left:0;right:0;top:0;bottom:0;transition:var(--trans-1);opacity:0;visibility:hidden} .tIc:hover::after{opacity:1;visibility:visible;transform:scale(1.3,1.3)} .tDL .d2, .drK .tDL .d1{display:none} /* mainIn Section */ .blogCont{flex-grow:1;padding:20px 0 0;position:relative;transition:var(--trans-1)} .blogCont .section:not(.no-items), .blogCont .widget:not(:first-child){margin-top:40px} .blogCont .section:first-child, .blogCont footer .widget:not(:first-child), .blogCont .section.mobMn{margin-top:0} .blogAd .section:not(.no-items){margin-bottom:40px} .blogM{flex-wrap:wrap;justify-content:center;padding-bottom:40px} .sidebar{max-width:500px;margin:50px auto 0} .sideSticky{position:-webkit-sticky;position:sticky;top:calc(var(--headerH) + 10px)} .onPs .blogM .mainbar{max-width:var(--pageW)} .onPg .blogM .mainbar{max-width:var(--pageW)}
    
    /* mainNav */ .mnBrs{background:var(--contentB)} .mnBr a{color:inherit} .mnBr ul{list-style:none;margin:0;padding:0} .mnMob{align-self:flex-end;position:absolute;left:0;right:0;bottom:0;background:inherit;padding:15px 20px 20px;z-index:1} .mnMob .mSoc{display:flex;position:relative;width:calc(100% + 14px);left:-7px;right:-7px;margin-top:5px} .mnMob:not(.no-items) + .mnMen{padding-bottom:100px} .mnMen{padding:20px 15px} .mMenu{margin-bottom:10px} .mMenu >*{display:inline} .mMenu >*:not(:last-child)::after{content:'\00B7';font-size:90%;opacity:.6} .mMenu a:hover{text-decoration:underline} .mSoc >*{position:relative} .mSoc svg{z-index:1} .mSoc svg, .mnMn svg{width:20px;height:20px;opacity:.8} .mSoc span, .mMenu span{opacity:.7} .mNav{display:none;position:relative;max-width:30px} .mNav svg{height:18px;opacity:.7;z-index:1} .mnMn >li{position:relative} .mnMn >li.br::after{content:'';display:block;border-bottom:1px solid var(--contentL);margin:12px 5px} .mnMn li:not(.mr) .a:hover, .mnMn ul li >*:hover{background:var(--transB)} .mnMn li:not(.mr) .a:hover, .mnMn ul li a:hover{color:var(--linkC)} .mnMn li:not(.mr) ul{padding-left:30px} .mnMn li ul{display:none;opacity:0;visibility:hidden} .mnMn ul li >*, .mnMn .a{display:flex;align-items:center;padding:10px 5px;position:relative;width:calc(100% + 10px);left:-5px;right:-5px;border-radius:8px;transition:var(--trans-1)} .mnMn ul li >*{padding:10px} .mnMn .a >*{margin:0 5px} .mnMn .a:hover svg:not(.d){fill:var(--linkC)} .mnMn .a:hover svg.line:not(.d){fill:none;stroke:var(--linkC)} .mnMn .n, .mnMn ul li >*{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1 0 calc(100% - 64px)} .mnMn svg{flex-shrink:0} .mnMn svg.d{width:14px;height:14px} .mnMn .drp.mr .a{font-size:13px;padding-bottom:0;opacity:.7} .mnMn .drp.mr svg.d{display:none} .mnMn .drpI:checked ~ .a svg.d{transform:rotate(180deg)} .mnMn .drpI:checked ~ ul{display:block;opacity:1;visibility:visible} /* Mobile Menu */ .mobMn{position:fixed;left:0;right:0;bottom:0; border-top:1px solid var(--mobL);border-radius:var(--mobBr) var(--mobBr) 0 0;background:var(--mobB);color:var(--mobT);padding:0 20px;box-shadow:0 -10px 25px -5px rgba(0,0,0,.1);z-index:2;font-size:12px} .mobMn svg.line{stroke:var(--mobT);opacity:.8} .mobMn ul{height:55px;display:flex;align-items:center;justify-content:center;list-style:none;margin:0;padding:0} .mobMn li{display:flex;justify-content:center;flex:1 0 20%} .mobMn li >*{display:inline-flex;align-items:center;justify-content:center;min-width:35px;height:35px;border-radius:20px;padding:0 8px;transition:var(--trans-1);color:inherit} .mobMn li svg{margin:0 3px;flex-shrink:0} .mobMn li >*::after{content:attr(data-text);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:0;margin:0;transition:inherit;opacity:.7} .mobMn li >*:hover::after{max-width:70px;margin:0 3px} .mobMn .nmH{opacity:.7} .mobMn li >*:hover{background:var(--mobHv)} .mobMn li >*:hover svg.line{fill:var(--mobT) !important;opacity:.5} /* Style 2 */ .MN-2 .mobMn{font-size:10px} .mobS .mobMn li >*{flex-direction:column;position:relative} .mobS .mobMn li >*:hover{background:transparent} .MN-2 .mobMn li >*::after{max-width:none} /* Style 3 */ .MN-3 .mobMn li >*::after{content:'';width:4px;height:4px;border-radius:50%;position:absolute;bottom:-2px;opacity:0} .MN-3 .mobMn li >*:hover::after{background:var(--linkB);opacity:.7} .MN-3 .mobMn li >*:hover svg.line{stroke:var(--linkB);fill:var(--linkB) !important;opacity:.7} /* Footer */ footer{font-size:97%;line-height:1.8em; padding:30px 0; border-top:var(--fotL) solid var(--contentL); color:var(--fotT); background:var(--fotB)} .cdtIn{display:flex;align-items:baseline;justify-content:space-between; position:relative;width:calc(100% + 20px);left:-10px;right:-10px} .cdtIn >*{margin:0 10px} .cdtIn .HTML{overflow:hidden;white-space:nowrap;text-overflow:ellipsis} .footCdt{display:inline-flex} .footCdt .creator{opacity:0} .tTop svg{width:20px;height:20px;stroke:var(--fotT)} .toTop{display:flex;align-items:center; white-space:nowrap} .toTop::before{content:attr(data-text); opacity:.7;margin:0 5px} .toTopF{display:flex;align-items:center;justify-content:center;width:45px;height:45px;border-radius:50%;background:var(--linkB);position:fixed;bottom:20px;right:20px} .toTopF svg{stroke:#fffdfc;stroke-width:2}
    
    /* Article Section */ .onIndx .blogPts, .itemFt .itm{display:flex;flex-wrap:wrap;align-items:center;position:relative; width:calc(100% + 20px);left:-10px;right:-10px} .onIndx .blogPts >*, .itemFt .itm >*{flex:0 0 calc(50% - 20px);width:calc(50% - 20px); margin-bottom:0;margin-left:10px;margin-right:10px} .onIndx .blogPts >*{margin-bottom:40px; padding-bottom:35px;position:relative} .onIndx .blogPts .pTag{padding-bottom:0} .onIndx .pTag .pInf{display:none} .onIndx .blogPts .pInf{position:absolute;bottom:0;left:0;right:0} .onIndx .blogPts{align-items:stretch} .onIndx .blogPts.mty{display:block;width:100%;left:0;right:0} .onIndx .blogPts.mty .noPosts{width:100%;margin:0} .onIndx .blogPts div.ntry{padding-bottom:0;flex:0 0 calc(100% - 20px)} .blogPts .ntry.noAd .widget, .Blog ~ .HTML{display:none} /* Blog title */ .blogTtl{font-size:14px; margin:0 0 30px;width:calc(100% + 16px);display:flex;justify-content:space-between;position:relative;left:-8px;right:-8px} .blogTtl .t, .blogTtl.hm .title{margin:0 8px;flex-grow:1} .blogTtl .t span{font-weight:400;font-size:90%; opacity:.7} .blogTtl .t span::before{content:attr(data-text)} .blogTtl .t span::after{content:''; margin:0 4px} .blogTtl .t span.hm::after{content:'/'; margin:0 8px} /* Thumbnail */ .pThmb{flex:0 0 calc(50% - 12.5px);overflow:hidden;position:relative;border-radius:3px; margin-bottom:20px; background:var(--transB)} .pThmb .thmb{display:block;position:relative;padding-top:52.335%; color:inherit; transition:var(--trans-1)} .pThmb .thmb amp-img{position:absolute;top:50%;left:50%;min-width:100%;min-height:100%;max-height:108%;text-align:center;transform:translate(-50%, -50%)} .pThmb div.thmb span::before{content:attr(data-text); opacity:.7; white-space:nowrap} .pThmb:not(.nul)::before{position:absolute;top:0;right:0;bottom:0;left:0; transform:translateX(-100%); background-image:linear-gradient(90deg, rgba(255,255,255,0) 0, rgba(255,255,255,.3) 20%, rgba(255,255,255,.6) 60%, rgba(255,255,255, 0)); animation:shimmer 2s infinite;content:''} .pThmb.iyt:not(.nul) .thmb::after{content:'';position:absolute;top:0;left:0;right:0;bottom:0; background:rgba(0,0,0,.4) url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'><path d='M4 11.9999V8.43989C4 4.01989 7.13 2.2099 10.96 4.4199L14.05 6.1999L17.14 7.9799C20.97 10.1899 20.97 13.8099 17.14 16.0199L14.05 17.7999L10.96 19.5799C7.13 21.7899 4 19.9799 4 15.5599V11.9999Z'/></svg>") center / 35px no-repeat; opacity:0;transition:var(--trans-1)} .pThmb.iyt:not(.nul):hover .thmb::after{opacity:1} /* Sponsored */ .iFxd{display:flex;justify-content:flex-end;position:absolute;top:0;left:0;right:0;padding:10px 6px;font-size:13px;line-height:16px} .iFxd >*{display:flex;align-items:center;margin:0 5px;padding:5px 2.5px;border-radius:8px;background:var(--contentB);color:inherit;box-shadow:0 8px 25px 0 rgba(0,0,0,.1)} .iFxd >* svg{width:16px;height:16px;stroke-width:1.5;margin:0 2.5px;opacity:.7} .iFxd .cmnt{padding:5px;color:var(--bodyC)} .iFxd .cmnt::after{content:attr(data-text);margin:0 2.5px;opacity:.7} .drK .iFxd >* svg.line{stroke:var(--iconC)} /* Label */ .pLbls::before, .pLbls >*::before{content:attr(data-text)} .pLbls::before{opacity:.7} .pLbls a:hover{text-decoration:underline} .pLbls >*{color:inherit;display:inline} .pLbls >*:not(:last-child)::after{content:'/'} /* Profile Images and Name */ .im{width:35px;height:35px;border-radius:16px; background-color:var(--transB);background-size:100%;background-position:center;background-repeat:no-repeat;display:flex;align-items:center;justify-content:center} .im svg{width:18px;height:18px;opacity:.4} .nm::after{content:attr(data-text)} /* Title and Entry */ .pTtl{font-size:1.1rem;line-height:1.5em} .pTtl.sml{font-size:1rem} .pTtl.itm{font-size:var(--postT);font-family:var(--fontBa);font-weight:900; line-height:1.3em} .pTtl.itm.nSpr{margin-bottom:30px} .aTtl a:hover{color:var(--linkC)} .aTtl a, .pSnpt{color:inherit; display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden} .pEnt{margin-top:40px; font-size:var(--postF);font-family:var(--fontBa); line-height:1.8em} /* Snippet, Description, Headers and Info */ .pHdr{margin-bottom:8px} .pHdr .pLbls{white-space:nowrap;overflow:hidden;text-overflow:ellipsis; opacity:.8} .pSml{font-size:93%} .pSnpt{-webkit-line-clamp:2;margin:12px 0 0;font-family:var(--fontBa);font-size:14px;line-height:1.5em; opacity:.8} .pSnpt.nTag{color:var(--linkC);opacity:1} .pDesc{font-size:16px;line-height:1.5em;margin:8px 0 25px;opacity:.7} .pInf{display:flex;align-items:baseline;justify-content:space-between; margin-top:15px} .pInf.nTm{margin:0} .pInf.nSpr .pJmp{opacity:1} .pInf.nSpr .pJmp::before{content:attr(aria-label)} .pInf.ps{justify-content:flex-start;align-items:center; margin-top:25px; position:relative;left:-4px;right:-4px;width:calc(100% + 8px)} .pInf.ps .pTtmp{opacity:1} .pInf.ps .pTtmp::before{content:attr(data-date) ' '} .pInf.ps .pTtmp::after{display:inline} .pInf.ps.nul{display:none} .pInf .pIm{flex-shrink:0; margin:0 4px} .pInf .pNm{flex-grow:1;width:calc(100% - 108px);display:inline-flex;flex-wrap:wrap;align-items:baseline} .pInf .pNm.l{display:none} .pInf .pCm{flex-shrink:0;max-width:24px;margin:0 2px} .pInf .pCm.l{max-width:58px} .pInf .pIc{display:inline-flex;justify-content:flex-end;position:relative;width:calc(100% + 10px);left:-5px;right:-5px} .pInf .pIc >*{display:flex;align-items:center;justify-content:center;width:30px;height:30px;position:relative;margin:0 2px;color:inherit} .pInf .pIc svg{width:20px;height:20px;opacity:.8;z-index:1} .pInf .pIc .cmnt::before{content:attr(data-text);font-size:11px;line-height:18px;padding:0 5px;border-radius:10px;background:#e6e6e6;color:var(--bodyC);position:absolute;top:-5px;right:0;z-index:2} .pInf .pDr{opacity:.7;display:inline-block;margin:0 4px;white-space:nowrap;text-overflow:ellipsis;overflow:hidden;max-width:100%} .pInf .pDr >*:not(:first-child)::before{content:'\00B7';margin:0 5px} .pInf .pIn{display:inline} .pInf .nm{margin:0 4px} .pInf .n .nm::before{content:attr(data-write) ' ';opacity:.7} .pInf .im{width:28px;height:28px} .aTtmp{opacity:.8} .aTtmp, .pJmp{overflow:hidden} .pTtmp::after, .pJmp::before, .iTtmp::before{content:attr(data-text); display:block;line-height:18px; white-space:nowrap;text-overflow:ellipsis;overflow:hidden} .pJmp{display:inline-flex;align-items:center; opacity:0; transition:var(--trans-2)} .pJmp::before{content:attr(aria-label)} .pJmp svg{height:18px;width:18px;stroke:var(--linkC); flex-shrink:0} .ntry:hover .pJmp, .itm:hover .pJmp{opacity:1} /* Product view */ .pTag .pPad{padding:10px 0} .pTag .pPric{font-size:20px;color:var(--linkC);padding-top:20px} .pTag .pPric::before, .pTag .pInfo small{content:attr(data-text);font-size:small;opacity:.8;display:block;line-height:1.5em;color:var(--bodyC)} .pTag .pInfo{font-size:14px;line-height:1.6em} .pTag .pInfo:not(.o){position:relative;width:calc(100% + 20px);left:-10px;right:-10px;display:flex} .pTag .pInfo:not(.o) >*{width:50%;padding:0 10px} .pTag .pMart{margin:10px 0 12px;display:flex;flex-wrap:wrap;line-height:1.6em; position:relative;width:calc(100% + 8px);left:-4px;right:-4px} .pTag .pMart >*{margin:0 4px} .pTag .pMart small{width:calc(100% - 8px);margin-bottom:10px} .pTag .pMart a{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid var(--contentL);border-radius:12px;margin-bottom:8px} .pTag .pMart img{width:20px;display:block} /* Blog pager */ .blogPg{display:flex;flex-wrap:wrap;justify-content:space-between; font-size:90%;font-family:var(--fontB);line-height:20px; color:#fffdfc; margin:30px 0 50px; max-width:100%} .blogPg >*{display:flex;align-items:center; padding:10px 13px;margin-bottom:10px; color:inherit;background:var(--linkB); border-radius:3px} .blogPg >* svg{width:18px;height:18px; stroke:var(--darkT); stroke-width:1.5} .blogPg >*::before{content:attr(data-text)} .blogPg .jsLd{margin-left:auto;margin-right:auto} .blogPg .nwLnk::before, .blogPg .jsLd::before{display:none} .blogPg .nwLnk::after, .blogPg .jsLd::after{content:attr(data-text); margin:0 8px} .blogPg .olLnk::before{margin:0 8px} .blogPg .nPst, .blogPg .current{background:var(--contentL); color:var(--bodyCa)} .blogPg .nPst.jsLd svg{fill:var(--darkTa);stroke:var(--darkTa)} .blogPg .nPst svg.line{stroke:var(--darkTa)} /* Breadcrumb */ .brdCmb{margin-bottom:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap} .brdCmb a{color:inherit} .brdCmb >*:not(:last-child)::after{content:'/'; margin:0 4px;font-size:90%;opacity:.6} .brdCmb >*{display:inline} .brdCmb .tl::before{content:attr(data-text)} .brdCmb .hm a{font-size:90%;opacity:.7}
    
    /* Article Style */ .pS h1, .pS h2, .pS h3, .pS h4, .pS h5, .pS h6{margin:1.5em 0 18px; font-family:var(--fontBa);font-weight:900; line-height:1.5em} .pS h1:target, .pS h2:target, .pS h3:target, .pS h4:target, .pS h5:target, .pS h6:target{padding-top:var(--headerH);margin-top:0} /* Paragraph */ .pS p{margin:1.7em 0} .pIndent{text-indent:2.5rem} .onItm:not(.Rtl) .dropCap{float:left;margin:4px 8px 0 0; font-size:55px;line-height:45px;opacity:.8} .pS hr{margin:3em 0; border:0} .pS hr::before{content:'\2027 \2027 \2027'; display:block;text-align:center; font-size:24px;letter-spacing:0.6em;text-indent:0.6em;opacity:.8;clear:both} .pRef{display:block;font-size:14px;line-height:1.5em; opacity:.7; word-break:break-word} /* Img and Ad */ .pS img{display:inline-block;border-radius:3px;height:auto !important} .pS img.full{display:block !important; margin-bottom:10px; position:relative; width:100%;max-width:none} .pS .widget, .ps .pAd >*{margin:40px 0} /* Note */ .note{position:relative;padding:16px 20px 16px 50px; background:#e1f5fe;color:#3c4043; font-size:.85rem;font-family:var(--fontB);line-height:1.6em;border-radius:10px;overflow:hidden} .note::before{content:'';width:60px;height:60px;background:#81b4dc;display:block;border-radius:50%;position:absolute;top:-12px;left:-12px;opacity:.1} .note::after{content:'\002A';position:absolute;left:18px;top:16px; font-size:20px; min-width:15px;text-align:center} .note.wr{background:#ffdfdf;color:#48525c} .note.wr::before{background:#e65151} .note.wr::after{content:'\0021'} /* Ext link */ .extL::after{content:''; width:14px;height:14px; display:inline-block;margin:0 5px; background: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23989b9f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M13 11L21.2 2.80005'/><path d='M22 6.8V2H17.2'/><path d='M11 2H9C4 2 2 4 2 9V15C2 20 4 22 9 22H15C20 22 22 20 22 15V13'/></svg>") center / 14px no-repeat} /* Scroll img */ .psImg{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:center; margin:2em 0; position:relative;left:-7px;right:-7px; width:calc(100% + 14px)} .psImg >*{width:calc(50% - 14px); margin:0 7px 14px; position:relative} .psImg img{display:block} .scImg >*{width:calc(33.3% - 14px); margin:0 7px} .btImg label{position:absolute;top:0;left:0;right:0;bottom:0; border-radius:3px; display:flex;align-items:center;justify-content:center; background:rgba(0,0,0,.6); transition:var(--trans-1); -webkit-backdrop-filter:saturate(180%) blur(10px); backdrop-filter:saturate(180%) blur(10px); color:var(--darkT); font-size:13px;font-family:var(--fontB)} .hdImg .shImg{width:100%;margin:0; left:0;right:0; transition:var(--trans-1); max-height:0;opacity:0;visibility:hidden} .inImg:checked ~ .hdImg .shImg{max-height:1000vh;opacity:1;visibility:visible} .inImg:checked ~ .hdImg .btImg label{opacity:0;visibility:hidden} /* Post related */ .pRelate{margin:40px 0;padding:20px 0; border:1px solid #989b9f;border-left:0;border-right:0; font-size:14px;line-height:1.8em} .pRelate b{font-weight:400; margin:0;opacity:.8} .pRelate ul, .pRelate ol{margin:8px 0 0;padding:0 20px} /* Blockquote */ blockquote, .cmC i[rel=quote]{position:relative;font-size:.97rem; opacity:.8;line-height:1.6em;margin-left:0;margin-right:0;padding:5px 20px;border-left:2px solid var(--contentL)} blockquote.s-1, details.sp{font-size:.93rem; padding:25px 25px 25px 45px; border:1px solid #989b9f;border-left:0;border-right:0;line-height:1.7em} blockquote.s-1::before{content:'\201D';position:absolute;top:10px;left:0; font-size:60px;line-height:normal;opacity:.5} /* Table */ .ps table{margin:0 auto; font-size:14px;font-family:var(--fontB)} .ps table:not(.tr-caption-container){min-width:90%;border:1px solid var(--contentL);border-radius:3px;overflow:hidden} .ps table:not(.tr-caption-container) td{padding:16px} .ps table:not(.tr-caption-container) tr:not(:last-child) td{border-bottom:1px solid var(--contentL)} .ps table:not(.tr-caption-container) tr:nth-child(2n+1) td{background:rgba(0,0,0,.01)} .ps table th{padding:16px; text-align:inherit; border-bottom:1px solid var(--contentL)} .ps .table{display:block; overflow-y:hidden;overflow-x:auto;scroll-behavior:smooth} /* Img caption */ figure{margin-left:0;margin-right:0} .ps .tr-caption, .psCaption, figcaption{display:block; font-size:14px;line-height:1.6em; font-family:var(--fontB);opacity:.7} /* Syntax */ .pre{background:var(--synxBg);color:var(--synxC); direction: ltr} .pre:not(.tb){position:relative;border-radius:3px;overflow:hidden;margin:1.7em auto;font-family:var(--fontC)} .pre pre{margin:0;color:inherit;background:inherit} .pre:not(.tb)::before, .cmC i[rel=pre]::before{content:'</>';display:flex;justify-content:flex-end;position:absolute;right:0;top:0;width:100%;background:inherit;color:var(--synxGray);font-size:10px;padding:0 10px;z-index:2;line-height:30px} .pre:not(.tb).html::before{content:'.html'} .pre:not(.tb).css::before{content:'.css'} .pre:not(.tb).js::before{content:'.js'} pre, .cmC i[rel=pre]{display:block;position:relative;font-family:var(--fontC);font-size:13px;line-height:1.6em;border-radius:3px;background:var(--synxBg);color:var(--synxC);padding:30px 20px 20px;margin:1.7em auto; -moz-tab-size:2;tab-size:2;-webkit-hyphens:none;-moz-hyphens:none;-ms-hyphens:none;hyphens:none; overflow:auto;direction:ltr;white-space:pre} pre i{color:var(--synxBlue);font-style:normal} pre i.block{color:#fff;background:var(--synxBlue)} pre i.green{color:var(--synxGreen)} pre i.gray{color:var(--synxGray)} pre i.red{color:var(--synxOrange)} pre i.blue{color:var(--synxBlue)} code{display:inline;padding:5px;font-size:14px;border-radius:3px;line-height:inherit;color:var(--synxC);background:#f2f3f5;font-family:var(--fontC)} /* Multi syntax */ .pre.tb{border-radius:5px} .pre.tb pre{margin:0;background:inherit} .pre.tb .preH{font-size:13px;border-color:rgba(0,0,0,.05);margin:0} .pre.tb .preH >*{padding:13px 20px} .pre.tb .preH::after{content:'</>';font-size:10px;font-family:var(--fontC);color:var(--synxGray);padding:15px;margin-left:auto} .pre.tb >:not(.preH){display:none} .pS input[id*="1"]:checked ~ div[class*="C-1"], .pS input[id*="2"]:checked ~ div[class*="C-2"], .pS input[id*="3"]:checked ~ div[class*="C-3"], .pS input[id*="4"]:checked ~ div[class*="C-4"]{display:block} /* ToC */ .pS details summary{list-style:none;outline:none} .pS details summary::-webkit-details-marker{display:none} details.sp{padding:20px 15px} details.sp summary{display:flex;justify-content:space-between;align-items:baseline} details.sp summary::after{content:attr(data-show);font-size:12px; opacity:.7;cursor:pointer} details.sp[open] summary::after{content:attr(data-hide)} details.toc a:hover{text-decoration:underline} details.toc ol, details.toc ul{padding:0 20px; list-style-type:decimal} details.toc li ol, details.toc li ul{margin:5px 0 10px; list-style-type:lower-alpha} /* Accordion */ .showH{margin:1.7em 0;font-size:.93rem;font-family:var(--fontB);line-height:1.7em} details.ac{padding:18px 0;border-bottom:1px solid var(--contentL)} details.ac:first-child{border-top:1px solid var(--contentL)} details.ac summary{font-weight:700;cursor:default; display:flex;align-items:baseline; transition:var(--trans-1)} details.ac summary::before{content:'\203A'; flex:0 0 25px;display:flex;align-items:center;justify-content:flex-start;padding:0 5px; font-weight:400;font-size:1.33rem;color:inherit} details.ac[open] summary{color:var(--linkC)} details.ac:not(.alt)[open] summary::before{transform:rotate(90deg);padding:0 0 0 5px;justify-content:center} details.ac.alt summary::before{content:'\002B'; padding:0 2px} details.ac.alt[open] summary::before{content:'\2212'} details.ac .aC{padding:0 25px;opacity:.9} /* Tabs */ .tbHd{display:flex; border-bottom:1px solid var(--contentL);margin-bottom:30px;font-size:14px;font-family:var(--fontB);line-height:1.6em; overflow-x:scroll;overflow-y:hidden;scroll-behavior:smooth;scroll-snap-type:x mandatory; -ms-overflow-style:none;-webkit-overflow-scrolling:touch} .tbHd >*{padding:12px 15px; border-bottom:1px solid transparent; transition:var(--trans-1);opacity:.6;white-space:nowrap; scroll-snap-align:start} .tbHd >*::before{content:attr(data-text)} .tbCn >*{display:none;width:100%} .tbCn >* p:first-child{margin-top:0} .pS input[id*="1"]:checked ~ .tbHd label[for*="1"], .pS input[id*="2"]:checked ~ .tbHd label[for*="2"], .pS input[id*="3"]:checked ~ .tbHd label[for*="3"], .pS input[id*="4"]:checked ~ .tbHd label[for*="4"]{border-color:var(--linkB);opacity:1} .pS input[id*="1"]:checked ~ .tbCn div[class*="Text-1"], .pS input[id*="2"]:checked ~ .tbCn div[class*="Text-2"], .pS input[id*="3"]:checked ~ .tbCn div[class*="Text-3"], .pS input[id*="4"]:checked ~ .tbCn div[class*="Text-4"]{display:block} .tbHd.stick{position:-webkit-sticky;position:sticky;top:var(--headerH);background:var(--bodyB)} /* Split */ .ps .blogPg{font-size:13px; justify-content:center; position:relative;width:calc(100% + 8px);left:-4px;right:-4px} .ps .blogPg >*{padding:8px 15px;margin:0 4px 8px} /* Youtube fullpage */ .videoYt{position:relative;padding-bottom:56.25%; overflow:hidden;border-radius:5px} .videoYt iframe{position:absolute;width:100%;height:100%;left:0;right:0} /* Lazy Youtube */ .lazyYt{background:var(--synxBg);position:relative;overflow:hidden;padding-top:56.25%;border-radius:5px} .lazyYt img{width:100%;top:-16.84%;left:0;opacity:.95} .lazyYt img, .lazyYt iframe, .lazyYt .play{position:absolute} .lazyYt iframe{width:100%;height:100%;bottom:0;right:0} .lazyYt .play{top:50%;left:50%; transform:translate3d(-50%,-50%,0); transition:all .5s ease;display:block;width:70px;height:70px;z-index:1} .lazyYt .play svg{width:inherit;height:inherit; fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;stroke-width:8} .lazyYt .play .c{stroke:rgba(255,255,255,.85);stroke-dasharray:650;stroke-dashoffset:650; transition:all .4s ease-in-out; opacity:.3} .lazyYt .play .t{stroke:rgba(255,255,255,.75);stroke-dasharray:240;stroke-dashoffset:480; transition:all .6s ease-in-out; transform:translateY(0)} .lazyYt .play:hover .t{animation:nudge .6s ease-in-out;-webkit-animation:nudge .6s ease-in-out} .lazyYt .play:hover .t, .lazyYt .play:hover .c{stroke-dashoffset:0; opacity:.7;stroke:#FF0000} .nAmp .lazyYt{display:none} /* Button */ .button{display:inline-flex;align-items:center; margin:10px 0;padding:12px 15px;outline:0;border:0; border-radius:3px;line-height:20px; color:#fffdfc; background:var(--linkB); font-size:14px;font-family:var(--fontB); white-space:nowrap;overflow:hidden;max-width:320px} .button.ln{color:inherit;background:transparent; border:1px solid var(--bodyCa)} .button.ln:hover{border-color:var(--linkB);box-shadow:0 0 0 1px var(--linkB) inset} .btnF{display:flex;justify-content:center; margin:10px 0;width:calc(100% + 12px);left:-6px;right:-6px;position:relative} .btnF >*{margin:0 6px} /* Download btn */ .dlBox{max-width:500px;background:#f1f1f0;border-radius:10px;padding:12px;margin:1.7em 0; display:flex;align-items:center; font-size:14px} .dlBox .fT{flex-shrink:0;display:flex;align-items:center;justify-content:center; width:45px;height:45px; padding:10px; background:rgba(0,0,0,.1);border-radius:5px} .dlBox .fT::before{content:attr(data-text);opacity:.7} .dlBox a{flex-shrink:0;margin:0;padding:10px 12px;border-radius:5px;font-size:13px} .dlBox a::after{content:attr(aria-label)} .dlBox .fN{flex-grow:1; width:calc(100% - 200px);padding:0 15px} .dlBox .fN >*{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis} .dlBox .fS{line-height:16px;font-size:12px;opacity:.8} /* Icon btn */ .icon{flex-shrink:0;display:inline-flex} .icon::before{content:'';width:18px;height:18px;background-size:18px;background-repeat:no-repeat;background-position:center} .icon::after{content:'';padding:0 6px} .icon.dl::before, .drK .button.ln .icon.dl::before{background-image:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fefefe' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5'><polyline points='8 17 12 21 16 17'/><line x1='12' y1='12' x2='12' y2='21'/><path d='M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29'/></svg>")} .icon.demo::before{background-image:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fefefe' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5'><path d='M7.39999 6.32003L15.89 3.49003C19.7 2.22003 21.77 4.30003 20.51 8.11003L17.68 16.6C15.78 22.31 12.66 22.31 10.76 16.6L9.91999 14.08L7.39999 13.24C1.68999 11.34 1.68999 8.23003 7.39999 6.32003Z'/><path d='M10.11 13.6501L13.69 10.0601'/></svg>")} .button.ln .icon.dl::before{background-image:url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2308102b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5'><polyline points='8 17 12 21 16 17'/><line x1='12' y1='12' x2='12' y2='21'/><path d='M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29'/></svg>")} /* Lightbox image */ .zmImg.s{position:fixed;top:0;left:0;bottom:0;right:0;width:100%;margin:0;background:rgba(0,0,0,.75); display:flex;align-items:center;justify-content:center;z-index:999; -webkit-backdrop-filter:saturate(180%) blur(15px); backdrop-filter:saturate(180%) blur(15px)} .zmImg.s img{display:block;max-width:92%;max-height:92%;width:auto;margin:auto;border-radius:10px;box-shadow:0 5px 30px 0 rgba(0,0,0,.05)} .zmImg.s img.full{left:auto;right:auto;border-radius:10px;width:auto} .zmImg::after{content:'\2715';line-height:16px;font-size:14px;color:#fffdfc;background:var(--linkB); position:fixed;bottom:-20px;right:-20px; display:flex;align-items:center;justify-content:center;width:45px;height:45px;border-radius:50%; transition:var(--trans-1);opacity:0;visibility:hidden} .zmImg.s::after{bottom:20px;right:20px;opacity:1;visibility:visible;cursor:pointer}
    
    /* Article Style Responsive */ @media screen and (max-width: 640px){.pS img.full{width:calc(100% + 40px);left:-20px;right:-20px; border-radius:0} .note{font-size:13px} .scImg{flex-wrap:nowrap;justify-content:flex-start;position:relative;width:calc(100% + 40px);left:-20px;right:-20px;padding:0 13px; overflow-y:hidden;overflow-x:scroll;scroll-behavior:smooth;scroll-snap-type:x mandatory; -ms-overflow-style:none;-webkit-overflow-scrolling:touch} .scImg >*{flex:0 0 80%;scroll-snap-align:center} .ps .table{position:relative; width:calc(100% + 40px);left:-20px;right:-20px;padding:0 20px; display:flex}} @media screen and (max-width:500px){.hdImg{width:100%;left:0;right:0} .hdImg >*, .shImg >*{width:100%;margin:0 0 16px} .ps .tr-caption, .psCaption, figcaption{font-size:13px} .btnF >*{flex-grow:1;justify-content:center}.btnF >*:first-child{flex:0 0 auto} .dlBox a{width:42px;height:42px;justify-content:center} .dlBox a::after, .dlBox .icon::after{display:none}}
    
    /* Author profile */ .admPs{display:flex; max-width:480px;margin:30px 0; padding:12px 12px 15px; background:var(--contentB);border-radius:8px; box-shadow:0 10px 25px -3px rgba(0,0,0,.1)} .admIm{flex-shrink:0; padding:5px 0 0} .admIm .im{width:34px;height:34px} .admI{flex-grow:1; width:calc(100% - 34px);padding:0 12px} .admN::before{content:attr(data-write) ' '; opacity:.7;font-size:90%} .admN::after{content:attr(data-text)} .admA{margin:5px 0 0; font-size:90%; opacity:.9;line-height:1.5em; /*display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden*/} /* Share btn */ .pSh{margin:15px 0;padding:18px 0;border-bottom:1px solid rgba(0,0,0,.05)} .pShc{display:flex;align-items:center;flex-wrap:wrap; position:relative;width:calc(100% + 18px);left:-9px;right:-9px;font-size:13px} .pShc::before{content:attr(data-text);margin:0 9px;flex-shrink:0} .pShc >*{margin:0 5px; display:flex;align-items:center; color:inherit;padding:12px;border-radius:3px;background:#f1f1f0} .pShc .c{color:#fffdfc} .pShc .c svg{fill:#fffdfc} .pShc .c::after{content:attr(aria-label)} .pShc .fb{background:#1778F2} .pShc .wa{background:#128C7E} .pShc .tw{background:#1DA1F2} .pShc a::after{content:attr(data-text);margin:0 3px} .pShc svg, .cpL svg{width:18px;height:18px; margin:0 3px} .shL{position:relative;width:calc(100% + 20px);left:-10px;right:-10px;margin-bottom:20px;display:flex;flex-wrap:wrap;justify-content:center} .shL >*{margin:0 10px 20px;text-align:center} .shL >*::after{content:attr(data-text);font-size:90%;opacity:.7;display:block} .shL a{display:flex;align-items:center;justify-content:center;flex-wrap:wrap; width:65px;height:65px; color:inherit;margin:0 auto 5px;padding:8px;border-radius:26px;background:#f1f1f0} .shL svg{opacity:.8} .cpL{padding-bottom:15px} .cpL::before{content:attr(data-text);display:block;margin:0 0 15px;opacity:.8} .cpL svg{margin:0 4px;opacity:.7} .cpL input{border:0;outline:0; background:transparent;color:rgba(8,16,43,.4); padding:18px 8px;flex-grow:1} .cpL label{color:var(--linkC);display:flex;align-items:center;align-self:stretch; flex-shrink:0;padding:0 8px} .cpLb{display:flex;align-items:center;position:relative;background:#f1f1f0;border-radius:4px 4px 0 0;border-bottom:1px solid rgba(0,0,0,.25); padding:0 8px} .cpLb:hover{border-color:rgba(0,0,0,.42);background:#ececec} .cpLn span{display:block;padding:5px 14px 0;font-size:90%;color:#2e7b32; transition:var(--trans-1);animation:fadein 2s ease forwards; opacity:0;height:22px} /* Comments */ .pCmnts{margin-top:50px} .cmDis{text-align:center;margin-top:20px;opacity:.7} .cmMs{margin-bottom:20px} .cm iframe{width:100%} .cm:not(.cmBr) .cmBrs{background:transparent;position:relative;padding:60px 20px 0;width:calc(100% + 40px);left:-20px;right:-20px} .cmH h3.title{margin:0;flex-grow:1;padding:16px 10px} .cmH .s{margin:0 14px} .cmH .s::before{content:attr(data-text);margin:0 6px;opacity:.7;font-size:90%} .cmH .s::after{content:'\296E';line-height:18px;font-size:17px} .cmAv .im{width:35px;height:35px;border-radius:50%;position:relative} .cmBd.del .cmCo{font-style:italic;font-size:90%;line-height:normal;border:1px dashed rgba(0,0,0,.2);border-radius:3px;margin:.5em 0;padding:15px;opacity:.7; overflow:hidden;text-overflow:ellipsis;white-space:nowrap} .cmHr{line-height:24px; overflow:hidden;text-overflow:ellipsis;white-space:nowrap} .cmHr .d{font-size:90%;opacity:.7} .cmHr .d::before{content:'\00B7';margin:0 7px} .cmHr.a .n{display:inline-flex;align-items:center} .cmHr.a .n::after{content:'\2714';display:flex;align-items:center;justify-content:center;width:14px;height:14px;font-size:8px;background:#519bd6;color:#fefefe;border-radius:50%;margin:0 3px} .cmCo{line-height:1.6em;opacity:.9} .cmC i[rel=image]{font-size:90%; display:block;position:relative; min-height:50px; overflow:hidden;text-overflow:ellipsis;white-space:nowrap; margin:1em auto} .cmC i[rel=image]::before{content:'This feature isn\0027t available!';border:1px dashed rgba(0,0,0,.2);border-radius:3px;padding:10px;display:flex;align-items:center;justify-content:center;position:absolute;top:0;left:0;bottom:0;right:0;background:var(--contentB)} .cmC i[rel=pre], .cmC i[rel=quote]{margin-top:1em;margin-bottom:1em; font-style:normal;line-height:inherit;padding:20px} .cmC i[rel=pre]::before{display:block;width:auto} .cmC i[rel=quote]{display:block;font-style:italic;font-size:inherit;padding:5px 15px} .cmCo img{margin-top:1em;margin-bottom:1em} .cmAc{margin-top:10px} .cmAc a{font-size:90%;color:inherit;opacity:.7;display:inline-flex} .cmAc a::before{content:'\2934';line-height:18px;font-size:16px;transform:rotate(90deg)} .cmAc a::after{content:attr(data-text);margin:0 6px} .cmR{margin:10px 40px 0} .cmRp ~ .cmAc, .cmBd.del ~ .cmAc, .onItm:not(.Rtl) .cmHr .date{display:none} .cmRi:checked ~ .cmRp .thTg{margin-bottom:0} .cmRi:checked ~ .cmRp .thTg::after{content:attr(aria-label)} .cmRi:checked ~ .cmRp .thCh, .cmRi:checked ~ .cmRp .cmR{display:none} .cmAl:checked ~ .cm .cmH .s::before{content:attr(data-new)} .cmAl:checked ~ .cm .cmCn >ol{flex-direction:column-reverse} .thTg{display:inline-flex;align-items:center;margin:15px 0 18px;font-size:90%} .thTg::before{content:'';width:28px;border-bottom:1px solid var(--widgetTac);opacity:.5} .thTg::after{content:attr(data-text);margin:0 12px;opacity:.7} .cmCn ol{list-style:none;margin:0;padding:0;display:flex;flex-direction:column} .cmCn li{margin-bottom:18px;position:relative} .cmCn li .cmRbox{margin-top:20px} .cmCn li li{display:flex;flex-wrap:wrap;width:calc(100% + 12px);left:-6px;right:-6px} .cmCn li li:last-child{margin-bottom:0} .cmCn li li .cmAv{flex:0 0 28px;margin:0 6px} .cmCn li li .cmAv .im{width:28px;height:28px} .cmCn li li .cmIn{width:calc(100% - 52px);margin:0 6px} .cmHl >li{padding-left:17.5px} .cmHl >li >.cmAv{position:absolute;left:0;top:12px} .cmHl >li >.cmIn{padding:12px 15px 12px 28px;border:1px solid var(--contentL);border-radius:12px;box-shadow:0 10px 8px -10px rgba(0,0,0,.1)} /* Comments Show/Hide */ #comment:target{margin:0;padding-top:60px} .cmSh:checked ~ .cmShw, .cmShw ~ .cm:not(.cmBr), #comment:target .cmShw, #comment:target .cmSh:checked ~ .cm:not(.cmBr){display:none} .cmSh:checked ~ .cm:not(.cmBr), #comment:target .cm:not(.cmBr), #comment:target .cmSh:checked ~ .cmShw{display:block} .cmBtn{display:block;padding:20px;text-align:center;max-width:100%} .cmBtn.ln:hover{color:var(--linkB)} /* Comments Pop-up */ #comment:target .cmSh:checked ~ .cm.cmBr{bottom:-100%;opacity:0;visibility:hidden} #comment:target .cmSh:checked ~ .cm.cmBr .fCls{opacity:0;visibility:hidden}
    
    /* Widget Style */ .widget .imgThm{display:block;position:absolute;top:50%;left:50%;max-width:none;max-height:108%; font-size:12px;text-align:center; transform:translate(-50%, -50%)} .widget .title{margin:0 0 25px; font-size:var(--widgetT);font-weight:var(--widgetTw);position:relative} .widget .title::after{content:'';display:inline-block;vertical-align:middle; width:var(--widgetTa); margin:0 10px;border-bottom:1px solid var(--widgetTac); opacity:.5} .widget input[type=text], .widget input[type=email], .widget textarea{display:block;width:100%;outline:0;border:0;border-bottom:1px solid rgba(0,0,0,.25);border-radius:4px 4px 0 0;background:#f3f3f4; padding:25px 16px 8px 16px; line-height:1.6em; transition:var(--trans-1)} .widget input[type=text]:hover, .widget input[type=email]:hover, .widget textarea:hover{border-color:rgba(0,0,0,.42);background:#ececec} .widget input[type=text]:focus, .widget input[type=email]:focus, .widget textarea:focus, .widget input[data-text=fl], .widget textarea[data-text=fl]{border-color:var(--linkB);background:#ececec} .widget input[type=button], .widget input[type=submit]{display:inline-flex;align-items:center; padding:12px 30px; outline:0;border:0;border-radius:4px; color:#fffdfc; background:var(--linkB); font-size:14px; white-space:nowrap;overflow:hidden;max-width:100%} .widget input[type=button]:hover, .widget input[type=submit]:hover{opacity:.7} /* Widget BlogSearch */ .BlogSearch{position:fixed;top:0;left:0;right:0;z-index:12} .BlogSearch form{position:relative;min-width:320px} .BlogSearch input{position:relative;display:block;background:var(--srchB);border:0;outline:0;margin-top:-100%;padding:10px 55px;width:100%;height:72px;transition:var(--trans-1);z-index:2;border-radius:0 0 12px 12px} .BlogSearch input:focus{margin-top:0;box-shadow:0 10px 40px rgba(0,0,0,.2)} .BlogSearch input:focus ~ button.sb{opacity:.9} .BlogSearch .sb{position:absolute;left:0;top:0;display:flex;align-items:center;padding:0 20px;z-index:3;opacity:.7;height:100%;background:transparent;border:0;outline:0} .BlogSearch .sb svg{width:18px;height:18px;stroke:var(--srchI)} .BlogSearch button.sb{left:auto;right:0;opacity:0;font-size:13px} .BlogSearch button.sb::before{content:'\2715'} @media screen and (min-width:897px){header .BlogSearch{position:static;z-index:1} header .BlogSearch input{margin-top:0;padding:12px 42px;height:auto;font-size:13px;border-radius:12px;background:transparent; width:calc(100% + 26px);left:-13px;right:-13px;transition:var(--trans-2)} header .BlogSearch input:hover{background:var(--transB)} header .BlogSearch input:focus{box-shadow:none;margin-top:0; background:var(--transB)} header .BlogSearch .sb{padding:0} header .BlogSearch .fCls{display:none}} /* Widget Profile */ .prfI:checked ~ .mainWrp .wPrf{top:0;opacity:1;visibility:visible} .prfI:checked ~ .mainWrp .wPrf ~ .fCls{z-index:3;opacity:1;visibility:visible} .wPrf{display:flex;position:absolute;top:-5px;right:0;background:var(--contentB);border-radius:16px 5px 16px 16px;width:260px;max-height:400px;box-shadow:0 10px 25px -3px rgba(0,0,0,.1);transition:var(--trans-1);z-index:4;opacity:0;visibility:hidden;overflow:hidden} .wPrf .prfS{background:inherit} .wPrf.tm .im{width:39px;height:39px;flex-shrink:0} .wPrf.sl .im{width:60px;height:60px;border-radius:26px;margin:0 auto} .wPrf.sl .prfC{text-align:center} .prfH .c{display:none} .prfL{display:flex;align-items:center;position:relative;width:calc(100% + 16px);left:-8px;right:-8px;border-radius:8px;padding:8px 0;transition:var(--trans-1)} .prfL::after{content:attr(data-text);margin:0 2px} .prfL >*{margin:0 8px;flex-shrink:0} a.prfL:hover{background:var(--transB)} .sInf{margin-bottom:0} .sInf .sDt .l{display:inline-flex;align-items:center} .sInf .sTxt{margin:5px auto 0;max-width:320px;font-size:93%;opacity:.9;line-height:1.5em} .sInf .sTxt a{text-decoration:underline} .sInf .lc{display:flex;justify-content:center;margin:10px 0 0;opacity:.8;font-size:90%} .sInf .lc svg{width:16px;height:16px} .sInf .lc::after{content:attr(data-text);margin:0 4px} /* Widget FeaturedPost */ .itemFt .itm >*{flex:0 0 310px;width:310px} .itemFt .itm >*:last-child{flex:1 0 calc(100% - 310px - 40px);width:calc(100% - 310px - 40px)} /* Widget ToC */ .tocL{position:fixed;top:0;bottom:0;right:-280px;width:280px;transition:var(--trans-1);z-index:5} .tocLi{width:100%;height:100%;position:relative;background:var(--contentB);box-shadow:0 5px 30px 0 rgba(0,0,0,.05);z-index:2;border-radius:12px 0 0 12px} .tocLs{position:relative;top:20px;background:inherit} .tocIn{padding:60px 0 0;overflow-y:scroll;overflow-x:hidden;width:100%;height:calc(100vh - var(--headerH))} .tocC{position:absolute;left:-45px;top:105px;transition:var(--trans-1)} .tocC span{display:flex;align-items:center;justify-content:center;width:45px;height:40px;border-radius:20px 0 0 20px;background:var(--contentB);transition:inherit;z-index:1;box-shadow:0 5px 20px 0 rgba(0,0,0,.1)} .tocL svg.rad{width:20px;height:20px;position:absolute;right:-2px;top:-19px;fill:var(--contentB);transform:rotate(92deg);transition:inherit} .tocL svg.rad.in{top:auto;bottom:-19px;transform:rotate(-2deg)} .tocC span svg{opacity:.8} .tocT{display:flex;width:100%} .tocL ol{margin:0;padding-inline-start:35px;line-height:1.6em} .tocL li ol{margin:5px 0 10px;list-style:lower-roman} .tocL a{color:inherit;opacity:.8} .tocL a:hover{text-decoration:underline} .tocI:checked ~ .tocL{right:0;z-index:10} .tocI:checked ~ .tocL .tocC{opacity:0;visibility:hidden} .tocI:checked ~ .tocL .fCls{background:rgba(0,0,0,.25);opacity:1;visibility:visible} /* Widget PopularPosts */ .itemPp{counter-reset:p-cnt} .itemPp .iCtnt{display:flex} .itemPp >*:not(:last-child){margin-bottom:25px} .itemPp .iCtnt::before{flex-shrink:0;content:'0' counter(p-cnt);counter-increment:p-cnt;width:25px;opacity:.6;font-size:85%;line-height:1.8em} .iInr{flex:1 0;width:calc(100% - 25px)} .iTtl{font-size:.95rem;font-weight:700;line-height:1.5em} .iTtmp{display:inline-flex} .iTtmp::after{content:'\2014';margin:0 5px; color:var(--widgetTac);opacity:.7} .iInf{margin:0 25px 8px; overflow:hidden;white-space:nowrap;text-overflow:ellipsis} .iInf .pLbls{display:inline;opacity:.8} /* Widget Label */ /* List Label */ .wL ul{display:flex;flex-wrap:wrap; list-style:none;margin:0;padding:0; position:relative;width:calc(100% + 30px);left:-15px;right:-15px; font-size:13px} .wL li{width:calc(50% - 10px); margin:0 5px} .wL li >*{display:flex;align-items:baseline;justify-content:space-between; color:inherit;width:100%; padding:8px 10px;border-radius:4px;line-height:20px} .wL li >* svg{width:18px;height:18px;opacity:.8} .wL li >*:hover svg, .wL li >div svg{fill:var(--linkC) !important;stroke:var(--linkC)} .wL li >*:hover .lbC, .wL li >div .lbC{color:var(--linkC)} .wL .lbR{display:inline-flex;align-items:center} .wL .lbR .lbC{margin:0 5px} .wL .lbAl{max-height:0; overflow:hidden; transition:var(--trans-4)} .wL .lbM{display:inline-block; margin-top:10px;line-height:20px; color:var(--linkC)} .wL:not(.cl) .lbM{font-size:90%} .wL .lbM::before{content:attr(data-show)} .wL .lbM::after, .wL .lbC::after{content:attr(data-text)} .wL .lbM::after{margin:0 8px} .wL .lbT{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;opacity:.7} .wL .lbC, .wL .lbM::after{flex-shrink:0;font-size:12px;opacity:.7} .lbIn:checked ~ .lbAl{max-height:1000vh} .lbIn:checked ~ .lbM::before{content:attr(data-hide)} .lbIn:checked ~ .lbM::after{visibility:hidden} .wL.bg ul{width:calc(100% + 10px);left:-5px;right:-5px} .wL.bg li{margin-bottom:10px} .wL.bg li >*{background:#f6f6f6} /* Cloud Label */ .wL.cl{display:flex;flex-wrap:wrap} .wL.cl >*, .wL.cl .lbAl >*{display:block;max-width:100%} .wL.cl .lbAl{display:flex;flex-wrap:wrap} .wL.cl .lbC::before{content:'';margin:0 4px;flex:0 0} .wL.cl .lbN{display:flex;justify-content:space-between; margin:0 0 8px;padding:9px 13px; border:1px solid var(--contentL);border-radius:3px; color:inherit;line-height:20px} .wL.cl .lbN:hover .lbC, .wL.cl div.lbN .lbC{color:var(--linkB); opacity:1} .wL.cl .lbN:not(div):hover, .wL.cl div.lbN{border-color:var(--linkB)} .wL.cl .lbSz{display:flex} .wL.cl .lbSz::after{content:'';margin:0 4px;flex:0 0} /* Widget ContactForm */ .ContactForm{max-width:500px; font-family:var(--fontB);font-size:14px} .cArea:not(:last-child){margin-bottom:25px} .cArea label{display:block;position:relative} .cArea label .n{display:block;position:absolute;left:0;right:0;top:0; color:rgba(8,16,43,.4);line-height:1.6em;padding:15px 16px 0;border-radius:4px 4px 0 0;transition:var(--trans-1)} .cArea label .n.req::after{content:'*';font-size:85%} .cArea textarea{height:100px} .cArea textarea:focus, .cArea textarea[data-text=fl]{height:200px} .cArea input:focus ~ .n, .cArea textarea:focus ~ .n, .cArea input[data-text=fl] ~ .n, .cArea textarea[data-text=fl] ~ .n{padding-top:5px;color:rgba(8,16,43,.7);font-size:90%;background:#ececec} .cArea .h{display:block;font-size:90%;padding:5px 16px 0;opacity:.7;line-height:normal} .nArea .contact-form-error-message-with-border{color:#d32f2f} .nArea .contact-form-success-message-with-border{color:#2e7b32} /* Widget Sliders */ .sldO{position:relative;display:flex;overflow-y:hidden;overflow-x:scroll; scroll-behavior:smooth;scroll-snap-type:x mandatory;list-style:none;margin:0;padding:0; -ms-overflow-style: none} .sldO.no-items{display:none} .sldO.no-items + .section{margin-top:0} .sldO .widget:not(:first-child){margin-top:0} .sldO .widget{position:relative;flex:0 0 100%;width:100%;background:transparent; outline:0;border:0} .sldC{position:relative} .sldS{position:absolute;top:0;left:0;width:100%;height:100%;scroll-snap-align:center;z-index:-1} .sldIm{background-repeat:no-repeat;background-size:cover;background-position:center;background-color:var(--transB);display:block;padding-top:40%;border-radius:3px;color:#fffdfc;font-size:13px} .sldT{position:absolute;bottom:0;left:0;right:0;display:block;padding:20px; background:linear-gradient(0deg, rgba(30,30,30,.1) 0%, rgba(30,30,30,.05) 60%, rgba(30,30,30,0) 100%); border-radius:0 0 3px 3px} .sldS{animation-name:tonext, snap;animation-timing-function:ease;animation-duration:4s;animation-iteration-count:infinite} .sldO .widget:last-child .sldS{animation-name:tostart, snap} .Rtl .sldS{animation-name:tonext-rev, snap} .Rtl .sldO .widget:last-child .sldS{animation-name:tostart-rev, snap} .sldO:hover .widget .sldS, .Rtl .sldO:hover .widget .sldS, .sldO:focus-within .widget .sldS, .Rtl .sldO:focus-within .widget .sldS{animation-name:none} @media (prefers-reduced-motion:reduce){.sldS, .Rtl .sldS{animation-name:none}} @media screen and (max-width:640px){.sldO{width:calc(100% + 40px);left:-20px;right:-20px;padding:0 12.5px 10px} .sldO .widget{flex:0 0 90%;width:90%;margin:0 7.5px; box-shadow:0 10px 8px -8px rgb(0 0 0 / 12%)} .sldT{padding:10px 15px} .sldIm{font-size:12px}}
    
    
    /* Sticky Ad */
    .stickAd{position:fixed;bottom:0;left:0;right:0;width:100%;min-height:70px;max-height:200px;padding:5px 5px;box-shadow:0 -6px 18px 0 rgba(9,32,76,.1);-webkit-transition:var(--transition-1);transition:var(--transition-1);display:flex;align-items:center;justify-content:center;background-color:#fefefe;z-index:50}
    .stickAdclose{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:12px 0 0;position:absolute;right:0;top:-30px;background-color:inherit}
    .stickAdcontent{flex-grow:1;overflow:hidden;display:block;position:relative}
    .stickAdin:checked ~ .stickAd{padding:0;min-height:0}
    .stickAdin:checked ~ .stickAd .stickAdcontent{display:none}
    .darkMode .stickAd{background-color:var(--dark-bgAlt)}
    
     /* Error Page */ .erroP{display:flex;align-items:center;justify-content:center;height:100vh;text-align:center;padding:0} .erroC{width:calc(100% - 40px);max-width:450px;margin:auto;font-family:var(--fontBa)} .erroC h3{font-size:1.414rem;font-family:inherit} .erroC h3 span{display:block;font-size:140px;line-height:.8;margin-bottom:-1rem;color:#ebebf0} .erroC p{margin:30px 5%;line-height:1.6em;opacity:.7} .erroC .button{margin:0;padding-left:2em;padding-right:2em;font-size:14px}
    
    /* Responsive */
    @media screen and (min-width:897px){/* mainIn */ .mainIn, .blogM{display:flex} .blogMn{width:var(--navW);flex-shrink:0;position:relative;transition:var(--trans-1);z-index:1} .blogCont{padding-top:30px} .blogCont::before{content:'';position:absolute;top:var(--headerHi);left:0;height:calc(100% + var(--headerH));border-right:var(--navL) solid var(--contentL)} .blogCont{width:calc(100% - var(--navW))} .blogCont .secIn{padding-left:25px;padding-right:25px} .mainbar{flex:1 0 calc(100% - var(--sideW) - 25px);width:calc(100% - var(--sideW) - 25px)} .sidebar{display:flex;flex:0 0 calc(var(--sideW) + 25px);width:calc(var(--sideW) + 25px); margin:0} .sidebar::before{content:'';flex:0 0 25px} .sidebar .sideIn{width:calc(100% - 25px)} /* mainNav */ .mnBr{position:sticky;position:-webkit-sticky;top:var(--headerH)} .mnBrs{display:flex;height:calc(100vh - var(--headerH));font-size:13px;position:relative} .mnBrs >*:not(.mnMob){width:100%} .mnMen{padding:20px;overflow-y:hidden;overflow-x:hidden} .mnMen:hover{overflow-y:scroll} .mnMob{position:fixed;width:var(--navW)} .mnH, .mobMn{display:none} .bD:not(.hdMn) .navI:checked ~ .mainWrp .blogMn, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMob, .hdMn .navI:not(:checked) ~ .mainWrp .blogMn, .hdMn .navI:not(:checked) ~ .mainWrp .mnMob{width:75px} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn a:hover, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn a:hover{opacity:1;color:inherit} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn .a, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn .a{max-width:40px; border-radius:15px} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn .drp.mr, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn svg.d, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMob .PageList, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMob .mSoc, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn .drp.mr, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn svg.d, .hdMn .navI:not(:checked) ~ .mainWrp .mnMob .PageList, .hdMn .navI:not(:checked) ~ .mainWrp .mnMob .mSoc{display:none} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMob .mNav, .hdMn .navI:not(:checked) ~ .mainWrp .mnMob .mNav{display:flex} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn >li.br::after, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn >li.br::after{max-width:20px} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMen, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMen:hover, .hdMn .navI:not(:checked) ~ .mainWrp .mnMen, .hdMn .navI:not(:checked) ~ .mainWrp .mnMen:hover{overflow-y:visible;overflow-x:visible} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn .n, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.mr) ul, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn .n, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.mr) ul{position:absolute;left:35px;top:3px;margin:0 5px;padding:8px 10px;border-radius:5px 16px 16px 16px;max-width:160px;background:var(--contentB);color:var(--bodyC);opacity:0;visibility:hidden;box-shadow:0 5px 20px 0 rgba(0,0,0,.1);z-index:1} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.mr) ul, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.mr) ul{padding:0 5px;margin:0;overflow:hidden;display:block} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.mr):last-child ul, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.mr):last-child ul{top:auto;bottom:3px;border-radius:16px 16px 16px 5px} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.drp) .a:hover .n, .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.mr):hover ul, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.drp) .a:hover .n, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.mr):hover ul{opacity:1;visibility:visible} .bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn ul li >*, .hdMn .navI:not(:checked) ~ .mainWrp .mnMn ul li >*{border-radius:0} /* Article */ .onIndx.onHm .blogPts >*{flex:0 0 calc(33.33% - 20px);width:calc(33.33% - 20px)} .onIndx.onMlt .blogPts >*{flex:0 0 calc(25% - 20px);width:calc(25% - 20px)} /* Widget ToC */ .tocL{position:absolute;z-index:2} .tocLi::before{content:'';border-left:1px solid var(--contentL);position:absolute;top:0;bottom:0;left:0;z-index:1} .tocLs{position:-webkit-sticky;position:sticky;top:var(--headerH)} .tocC{top:40px} .tocI:checked ~ .tocL{z-index:2} .tocI:checked ~ .tocL .fCls{background:transparent}}
    @media screen and (min-width:768px){::-webkit-scrollbar{-webkit-appearance:none;width:4px;height:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:10px}::-webkit-scrollbar-thumb:hover{background:rgba(0,0,0,.35)}::-webkit-scrollbar-thumb:active{background:rgba(0,0,0,.35)}}
    @media screen and (max-width:1100px){/* Article */ .onIndx.onHm .blogPts >*{flex:0 0 calc(50% - 20px);width:calc(50% - 20px)} .onIndx.onMlt .blogPts >*{flex:0 0 calc(33.33% - 20px);width:calc(33.33% - 20px)} /* Widget */ .itemFt .itm >*, .itemFt .itm >*:last-child{flex:0 0 calc(50% - 20px);width:calc(50% - 20px)} .itemFt .itm >*:last-child{flex-grow:1} .itemFt .pSnpt{display:none}}
    @media screen and (max-width:896px){/* Header */ .ntfC{padding-left:20px;padding-right:20px} /* Remove this to keep header floating */ header{position:relative;border:0} .headL{padding:0 0 0 15px;flex-grow:1;width:50%} .headR{padding:0 20px 0 0;flex-grow:0} .headIc .isSrh{display:block} .headI .headS{margin:0} /* mainNav */ .blogMn{display:flex;justify-content:flex-start;position:fixed;left:0;top:0;bottom:0;margin-left:-100%;z-index:20;transition:var(--trans-1);width:100%;height:100%} .mnBr{width:85%;max-width:480px;height:100%;border-radius:0 12px 12px 0;transition:inherit;z-index:3;overflow:hidden;position:relative;box-shadow:0 5px 30px 0 rgba(0,0,0,.05)} .mnBrs{padding:60px 0 0;overflow-y:scroll;overflow-x:hidden;width:100%;height:100%} .mnH{padding:0 15px} .mnH label{padding:15px 10px} .mnH .c::after{margin:0 13px} .mnMen{padding-top:0} .navI:checked ~ .mainWrp .blogMn{margin-left:0} .navI:checked ~ .mainWrp .blogMn .fCls{opacity:1;visibility:visible;background:rgba(0,0,0,.2); -webkit-backdrop-filter:saturate(180%) blur(10px); backdrop-filter:saturate(180%) blur(10px)} /* Article */ .onIndx.onHm .blogPts >*{flex:0 0 calc(33.33% - 20px);width:calc(33.33% - 20px)} /* Widget */ .itemFt .pSnpt{display:-webkit-box} .mobMn:not(.no-items) + footer{padding-bottom:calc(55px + 30px)}}
    @media screen and (max-width:768px){/* Article */ .onIndx.onHm .blogPts >*, .onIndx.onMlt .blogPts >*{flex:0 0 calc(50% - 20px);width:calc(50% - 20px)}}
    @media screen and (max-width:640px){/* Header */ .headCn{height:var(--headerHm)} /* Pop-up */ .fixL{align-items:flex-end} .fixL .fixLi, .fixL .cmBri{border-radius:12px 12px 0 0; max-width:680px} .fixL .cmBri:not(.mty){border-radius:0;height:100%;max-height:100%}}
    @media screen and (max-width:500px){/* Font and Blog */ .iFxd, .crdtIn{font-size:12px} .brdCmb{font-size:13px} .pDesc{font-size:14px} .pEnt{font-size:var(--postFm)} .pTtl.itm{font-size:var(--postTm)} .pInf.ps .pTtmp::after{content:attr(data-time)} .pInf.ps .pDr{font-size:12px} /* Article */ .onIndx:not(.oneGrd) .blogPts{width:calc(100% + 15px);left:-7.5px;right:-7.5px} .onIndx:not(.oneGrd) .blogPts >*{flex:0 0 calc(50% - 15px);width:calc(50% - 15px);margin-left:7.5px;margin-right:7.5px} .onIndx:not(.oneGrd) .blogPts div.ntry{flex:0 0 calc(100% - 15px)} .onIndx:not(.oneGrd) .ntry .pSml{font-size:12px} .onIndx:not(.oneGrd) .ntry .pTtl{font-size:.9rem} .onIndx:not(.oneGrd) .ntry:not(.pTag) .pSnpt, .onIndx:not(.oneGrd) .ntry .pInf:not(.nSpr) .pJmp, .onIndx:not(.oneGrd) .ntry .iFxd .spnr{display:none} .onIndx:not(.oneGrd) .ntry .iFxd{padding:8px 3px} .onIndx:not(.oneGrd) .ntry .iFxd .cmnt{padding:3px} .onIndx:not(.oneGrd) .ntry .iFxd >* svg{padding:1px} .onIndx.oneGrd .blogPts >*{flex:0 0 calc(100% - 20px);width:calc(100% - 20px)} /* Share */ .pShc{width:calc(100% + 10px);left:-5px;right:-5px} .pShc::before{width:calc(100% - 10px);margin:0 5px 12px} .pShc .wa::after, .pShc .tw::after{display:none} /* Widget */ .prfI:checked ~ .mainWrp .wPrf{top:auto;bottom:0} .prfI:checked ~ .mainWrp .Profile .fCls{background:rgba(0,0,0,.2); -webkit-backdrop-filter:saturate(180%) blur(10px); backdrop-filter:saturate(180%) blur(10px)} .prfH .c{display:flex} .wPrf{position:fixed;top:auto;left:0;right:0;bottom:-100%;width:100%;max-height:calc(100% - var(--headerH));border-radius:12px 12px 0 0} .itemFt .itm{padding-bottom:80px} .itemFt .itm >*{flex:0 0 calc(100% - 20px);width:calc(100% - 20px)} .itemFt .itm .iCtnt{flex:0 0 calc(100% - 42px);width:calc(100% - 42px);margin:0 auto;position:absolute;left:0;right:0;bottom:0;padding:13px;background:rgba(255,253,252,.92);border-radius:10px;box-shadow:0 10px 20px -5px rgba(0,0,0,.1); -webkit-backdrop-filter:saturate(180%) blur(10px); backdrop-filter:saturate(180%) blur(10px)} .itemFt .pTtl{font-size:1rem} .itemFt .pSnpt{font-size:93%}
    }
    
    /* Keyframes Animation */ @keyframes shimmer{100%{transform:translateX(100%)}} @keyframes fadein{50%{opacity:1}80%{opacity:1;padding-top:5px;height:22px}100%{opacity:0;padding-top:0;height:0}} @keyframes nudge{0%{transform:translateX(0)}30%{transform:translateX(-5px)}50%{transform:translateX(5px)}70%{transform:translateX(-2px)}100%{transform:translateX(0)}} @keyframes tonext{ 75%{left:0} 95%{left:100%} 98%{left:100%} 99%{left:0}} @keyframes tostart{ 75%{left:0} 95%{left:-300%} 98%{left:-300%} 99%{left:0}} @keyframes tonext-rev{ 75%{right:0} 95%{right:100%} 98%{right:100%} 99%{right:0}} @keyframes tostart-rev{ 75%{right:0} 95%{right:-300%} 98%{right:-300%} 99%{right:0}} @keyframes snap{ 96%{scroll-snap-align:center} 97%{scroll-snap-align:none} 99%{scroll-snap-align:none} 100%{scroll-snap-align:center}} @-webkit-keyframes fadein{50%{opacity:1}80%{opacity:1;padding-top:5px;height:22px}100%{opacity:0;padding-top:0;height:0}} @-webkit-keyframes nudge{0%{transform:translateX(0)}30%{transform:translateX(-5px)}50%{transform:translateX(5px)}70%{transform:translateX(-2px)}100%{transform:translateX(0)}} @-webkit-keyframes tonext{ 75%{left:0} 95%{left:100%} 98%{left:100%} 99%{left:0}} @-webkit-keyframes tostart{ 75%{left:0} 95%{left:-300%} 98%{left:-300%} 99%{left:0}} @-webkit-keyframes tonext-rev{ 75%{right:0} 95%{right:100%} 98%{right:100%} 99%{right:0}} @-webkit-keyframes tostart-rev{ 75%{right:0} 95%{right:-300%} 98%{right:-300%} 99%{right:0}} @-webkit-keyframes snap{ 96%{scroll-snap-align:center} 97%{scroll-snap-align:none} 99%{scroll-snap-align:none} 100%{scroll-snap-align:center}}
    
    /* Noscript Option */ .lazy:not([lazied]){display:none} .noJs{display:flex;justify-content:flex-end;align-items:center;position:fixed;top:20px;left:20px;right:20px;z-index:99;max-width:640px;border-radius:12px;margin:auto;padding:10px 5px;background:#ffdfdf;font-size:13px;box-shadow:0 10px 20px -10px rgba(0,0,0,.1);color:#48525c} .noJs::before{content:attr(data-text);padding:0 10px;flex-grow:1} .noJs label{flex-shrink:0;padding:10px} .noJs label::after{content:'\2715';line-height:18px;font-size:14px} .nJs:checked ~ .noJs{display:none}
    
    /* Hide Scroll */ .scrlH::-webkit-scrollbar{width:0;height:0} .scrlH::-webkit-scrollbar-track{background:transparent} .scrlH::-webkit-scrollbar-thumb{background:transparent;border:none}
    
    /* CSS Safelink #204ecf */
    .wcSafeShow{position:relative;width:35px;height:35px;display:flex;margin:auto} /* atur margin untuk mengubah posisi icon */
    .safeWrap{position:fixed;top:0;left:0;bottom:0;right:0;background:rgba(0,0,0,.5);z-index:999999;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}.panel-primary{background:#fff;text-align:center;display:block;overflow:hidden;width:100%;max-width:80%;padding:0 0 25px 0;border-radius:5px;margin:15% auto;box-shadow:0 1px 3px rgba(0,0,0,0.12),0 1px 2px rgba(0,0,0,0.24)}.panel-body{position:relative;margin:0 25px}.panel-heading h2{background:#204ecf;color:#fff;margin:0 auto 25px auto;font-weight:400;padding:15px;font-size:20px}.panel-body input{background:rgba(0,0,0,0.04);width:100%;padding:15px;border-radius:5px;border:1px solid transparent;font-size:16px;color:#000;outline:none;text-indent:60px;transition:all .3s}.panel-body input:focus{background:#fff;color:#000;border-color:#204ecf;outline:none;box-shadow:0 0 5px rgba(0,0,0,0.1)}.panel-body .input-group-btn{position:absolute;top:0;right:0}.panel-body button{border-radius:0 5px 5px 0;background:#204ecf;color:#fff;border:0;padding:17px 52px;font-weight:500;outline:none;transition:all .3s}.panel-body button:hover,.panel-body button:focus{background:#204ecf;outline:none}#generatelink{margin:20px auto 0 auto}#generatelink button{background:#204ecf;border-radius:5px;font-size:14px;padding:14px 32px}#generatelink button:hover,#generatelink button:focus{background:#204ecf;border-radius:5px;font-size:14px}#generatelink input{background:rgba(0,0,0,0.05);text-indent:0}#generatelink input:hover,#generatelink input:focus{background:#204ecf;border-color:transparent;box-shadow:none}#generateloading{margin:20px auto 0 auto;font-size:20px;color:#204ecf;font-weight:normal}
    .panel-body:before{content:'\279C';background:rgba(0,0,0,0.05);position:absolute;left:0;top:0;color:#888;padding:17px 20px;border-radius:5px 0 0 5px;border-right:1px solid transparent;transition:all .6s}.panel-body:focus-within:before{content:'\279C';background:#204ecf;color:#fff}.bt-success{display:inline-flex;align-items:center;margin:15px 15px;padding:10px 20px;outline:0;border:0;border-radius:2px;color:#fefefe;background-color:#204ecf;font-size:14px;white-space:nowrap;overflow:hidden;max-width:100%;line-height:2em}.bt-success:hover{color:#204ecf;background-color:transparent;border:1px solid #204ecf}.hidden,.bt-success.hidden{display:none}.wcSafeClose{display:inline-flex;align-items:center;margin:15px auto -15px;padding:5px 15px;outline:0;border:0;border-radius:2px;color:#fefefe;background-color:#204ecf;font-size:14px;white-space:nowrap;overflow:hidden;max-width:100%;line-height:2em}.copytoclipboard{margin:10px auto 5px}
    #timer{margin:0 auto 20px auto;width:80px;text-align:center}.pietimer{position:relative;font-size:200px;width:1em;height:1em}.pietimer > .percent{position:absolute;top:25px;left:12px;width:3.33em;font-size:18px;text-align:center;display:none}.pietimer > .slice{position:absolute;width:1em;height:1em;clip:rect(0px,1em,1em,0.5em)}.pietimer >.slice.gt50{clip:rect(auto,auto,auto,auto)}.pietimer > .slice > .pie{border:0.06em solid #c0c0c0;position:absolute;width:1em;height:1em;clip:rect(0em,0.5em,1em,0em);border-radius:0.5em}.pietimer > .slice > .pie.fill{-moz-transform:rotate(180deg)!important;-webkit-transform:rotate(180deg)!important;-o-transform:rotate(180deg)!important;transform:rotate(180deg)!important}.pietimer.fill > .percent{display:none}.pietimer.fill > .slice > .pie{border:transparent;background-color:#c0c0c0;width:1em;height:1em}
    .wcSafeShow svg{fill:none!important;stroke:#48525c;stroke-linecap:round;stroke-linejoin:round;stroke-width:1;width:22px;height:22px}
    #generateloading svg{width:22px;height:22px;fill:#204ecf}
    .btn-primary svg,.darkMode .btn-primary svg{fill:none;stroke:#fff;stroke-width:1.5;width:22px;height:22px;vertical-align:-5px;margin-right:10px}
    @media screen and (max-width:768px){.panel-body .input-group-btn{display:block;position:relative;overflow:hidden;margin:20px auto 0 auto}.panel-body button{border-radius:5px;width:100%}}
    @media screen and (max-width:480px){.panel-primary{margin-top:30%}}
    
    @keyframes fade-in-up {
    0% {
    opacity: 0;
    }
    100% {
    transform: translateY(0);
    opacity: 1;
    }
    }
    .video iframe {
    max-width: 100%;
    max-height: 100%;
    }
    .video.stuck {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 260px;
    height: 145px;
    transform: translateY(100%);
    animation: fade-in-up 0.75s ease forwards;
    z-index: 1;
    }
    
    /* Dark Mode */ 
    .drK .tDL .d2{display:block} .drK .tDL::after{content:attr(data-light)} .drK .tDL svg .f{stroke:none;fill:var(--darkT)}  .drK .pThmb:not(.nul)::before{background-image:linear-gradient(90deg, rgba(0,0,0,0) 0, rgba(0,0,0,.07) 20%, rgba(0,0,0,.1) 60%, rgba(0,0,0,0))} .drK input::placeholder, .drK .cpL input, .drK .cArea label .n{color:rgba(255,255,255,.25)} .drK .nArea .contact-form-error-message-with-border{color:#f94f4f} .drK .cmC i[rel=image]::before, .drK .widget input[type=text], .drK .widget input[type=email], .drK .widget textarea{background:var(--darkBs);border-color:rgba(255,255,255,.15)} .drK .erroC h3 span{color:rgba(255,255,255,.1)} .drK svg, .drK svg.c-1{fill:var(--darkT)} .drK svg.line{fill:none;stroke:var(--darkT)} .drK svg.c-2{fill:var(--darkTalt); opacity:.4} .drK, .drK .headCn, .drK .mnBrs{background:var(--darkB);color:var(--darkT)} .drK .ntfC, .drK .mobMn{background:var(--darkBa);color:var(--darkTa)} .drK header, .drK .mnMn >li.br::after, .drK .blogCont::before, .drK .tbHd, .drK .cmHl >li >.cmIn, .drK .pTag .pMart a, .drK .pRelate, .drK blockquote, .drK .cmC i[rel=quote], .drK blockquote.s-1, .drK details.sp, .drK .ps table:not(.tr-caption-container), .drK .ps table th, .drK .ps table:not(.tr-caption-container) tr:not(:last-child) td, .drK .pre.tb .preH, .drK details.ac, .drK .ancrA, .drK .ancrC{border-color:rgba(255,255,255,.15)} .drK .pre{background:var(--darkBs);color:var(--darkTa)} .drK footer{background:transparent;border-color:rgba(255,255,255,.15)} .drK .tIc::after, .drK .mnMn li:not(.mr) .a:hover, .drK .mnMn ul li >*:hover, .drK .wL.bg li >*, .drK .mobMn li >*:hover, .drK .shL a, .drK .cpLb{background:rgba(0,0,0,.15)} .drK .wPrf{background:var(--darkBa);box-shadow:0 10px 40px rgba(0,0,0,.2)} .drK h1, .drK h2, .drK h3, .drK h4, .drK h5, .drK h6, .drK footer, .drK .button{color:var(--darkT)} .drK .admPs, .drK .dlBox, .drK .fixLs, .drK .cArea input:focus ~ .n, .drK .cArea textarea:focus ~ .n, .drK .cArea input[data-text=fl] ~ .n, .drK .cArea textarea[data-text=fl] ~ .n{background:var(--darkBs)} .drK .tocLi{background:var(--darkB)} .drK .tocC span, .drK .pShc >*:not(.c), .drK .ancrA, .drK .BlogSearch input{background:var(--darkBa)} .drK .tocL svg.rad{fill:var(--darkBa)} .drK .mobMn li >*:hover svg.line{fill:var(--darkT) !important} .drK.mobS .mobMn li >*:hover, .drK .button.ln{background:transparent} .drK .pTag .pPric::before, .drK .pTag .pInfo small{color:var(--darkTa)} .drK::selection, .drK a, .drK .free::after, .drK .new::after, .drK .mnMn li:not(.mr) .a:hover, .drK .mnMn ul li a:hover, .drK .aTtl a:hover, .drK .pSnpt.nTag, .drK .pTag .pPric, .drK details.ac[open] summary, .drK .cpL label, .drK .wL li >*:hover .lbC, .drK .wL li >div .lbC, .drK .wL .lbM, .drK .cmBtn.ln:hover, .drK .wL.cl .lbN:hover .lbC, .drK .wL.cl div.lbN .lbC{color:var(--darkU)} .drK .mnMn .a:hover svg:not(.d){fill:var(--darkU)} .drK .mnMn .a:hover svg.line:not(.d), .drK .pJmp svg{fill:none;stroke:var(--darkU)} .drK .wL li >*:hover svg, .drK .wL li >div svg{fill:var(--darkU) !important;stroke:var(--darkU)} .drK.MN-3 .mobMn li >*:hover::after, .drK .toTopF, .drK .blogPg >*, .drK .button, .drK .zmImg::after, .drK .widget input[type=button], .drK .widget input[type=submit]{background:var(--darkU)} .drK.MN-3 .mobMn li >*:hover svg.line{stroke:var(--darkU);fill:var(--darkU) !important} .drK.MN-3 .mobMn li >*:hover svg .f{fill:var(--darkU)} .drK .pS input[id*="1"]:checked ~ .tbHd label[for*="1"], .drK .pS input[id*="2"]:checked ~ .tbHd label[for*="2"], .drK .pS input[id*="3"]:checked ~ .tbHd label[for*="3"], .drK .pS input[id*="4"]:checked ~ .tbHd label[for*="4"], .drK .widget input[type=text]:focus, .drK .widget input[type=email]:focus, .drK .widget textarea:focus, .drK .widget input[data-text=fl], .drK .widget textarea[data-text=fl], .drK .wL.cl .lbN:not(div):hover, .drK .wL.cl div.lbN{border-color:var(--darkU)} .drK .button.ln:hover{border-color:var(--darkU);box-shadow:0 0 0 1px var(--darkU) inset} .drK header a, .drK .mnBr a, .drK .pLbls >*, .drK .aTtl a, .drK .blogPg >*, .drK .brdCmb a, .drK .wL li >*, .drK .mobMn li >*, .drK .cmAc a{color:inherit} .drK .blogPg .nPst, .drK .blogPg .current{background:var(--contentL);color:var(--bodyCa)} @media screen and (min-width:897px){.drK header .BlogSearch input{background:transparent} .drK header .BlogSearch input:focus, .drK header .BlogSearch input:hover{background:var(--darkBs)} .drK.bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn .n, .drK.bD:not(.hdMn) .navI:checked ~ .mainWrp .mnMn li:not(.mr) ul, .drK.hdMn .navI:not(:checked) ~ .mainWrp .mnMn .n, .drK.hdMn .navI:not(:checked) ~ .mainWrp .mnMn li:not(.mr) ul{background:var(--darkBa);box-shadow:0 10px 40px rgba(0,0,0,.2);color:var(--darkTa)} .drK.LS-2 .blogCont::before{background:var(--darkBs)} .drK.LS-3 .headCn{background:transparent} .drK.LS-3 .headL, .drK.LS-3 .mnBrs{background:var(--darkBs)} .drK.LS-3 .headR{background:var(--darkB)} .drK .tocLi::before{border-color:rgba(255,255,255,.15)}} @media screen and (max-width:500px){.drK .itemFt .itm .iCtnt{background:var(--darkBa)}}
    
    #adblockbyspider{backdrop-filter: blur(5px);background:rgba(0,0,0,0.25);padding:20px 19px;border:1px solid #ebeced;border-radius:10px;color:#ebeced;overflow:hidden;position:fixed;margin:auto;left:10;right:10;top:0;width:100%;height:100%;overflow:auto;z-index:999999}#adblockbyspider .inner{background:#f5f2f2;color:#000;box-shadow:0 5px 20px rgba(0,0,0,0.1);text-align:center;width:600px;padding:40px;margin:80px auto}#adblockbyspider button{padding:10px 20px;border:0;background:#e9e9e9;margin:20px;box-shadow:0 5px 10px rgba(0,0,0,0.3);cursor:pointer;transition:all .2s}#adblockbyspider button.active{background:#fff}#adblockbyspider .tutorial{background:#fff;text-align:left;color:#000;padding:20px;height:250px;overflow:auto;line-height:30px}#adblockbyspider .tutorial div{display:none}#adblockbyspider .tutorial div.active{display:block}#adblockbyspider ol{margin-left:20px}@media(max-width:680px){#adblockbyspider .inner{width:calc(100% - 80px);margin:auto}}
    
    
    play{background:transparent;color:#fff;left:0;right:0;height:2.5em;display:flex;flex-direction:row;align-items:center;justify-content:space-between;text-decoration:none;cursor:pointer;margin:40px;}
    .bt1{background:#222;color:#fff;padding:1px;border:0px solid #fff;border-radius:50px;width:35px;height:35px;margin:10px;}
    .bt2{background:#222;color:#fff;padding:1px;border:0px solid #fff;border-radius:50px;width:35px;height:35px;margin:10px;}
    /*card poster*/
    .alignleft{float:left;margin-left:5px; color: var(--text); font-weight:bold; font-size:16px;} 
    .alignright{float:right;margin-right:10px; color: var(--text); font-size:14px; font-weight:bold ;} 
    h4{margin-left:5px;color:#fff;font-weight:bold; font-size:15px;}
    .poster{position:relative;overflow:hidden;} 
    div.scrollmenu{background:transparent;overflow:auto;white-space:nowrap;position:relative;overflow-y:hidden;} 
    div.scrollmenu a{display:inline-block;color:#000;text-align:center;padding:4px;text-decoration:none;}
    div.scrollmenu a:hover{background:transparent;}
    
    .mnt{width:100px;border-radius:100px;height:100px;}
    #xs-header{background:transparent;left:0;position:fixed;top:-10px;width:100%;z-index:90;height:8%;}
    /*end card poster*/
    /*Carousel*/
    body{margin: 0;} 
    div#slider{overflow: hidden;} /* las imĆ�genes no van a salir del mĆ�rgen de la pantalla*/
    div#slider figure img{width: 20%; float: left;}
    div#slider figure{position: relative;width: 500%;margin: 0;left: 0;text-align: left;font-size: 0;animation: 15s slidy infinite; /*el movimiento se va a mantener de forma indefinida -infinito-*/}
    /*esta parte del codigo define el movimiento de las imagenes a la izquierda*/
    @keyframes slidy {
    0% { left: 0%; }
    20% { left: 0%; }
    30% { left: -100%; }
    40% { left: -100%; }
    50% { left: -200%; }
    60% { left: -200%; }
    70% { left: -300%; }
    80% { left: -300%; }
    90% { left: -400%; }
    100% { left: -400%; }
    }
    /*End Carousel*/
    
</style>


<!--CANDADO-->
<style>
.lock-icon {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 4px 6px;
  border-radius: 5px;
  font-size: 14px;
  z-index: 5;
  display: none;
}
</style>

<!--CANDADO-->
<style>
.lock-icon {
  font-size: 28px;
  display: inline-block;
  transition: transform 0.5s ease, color 0.5s ease;
  animation: bounceIn 1s ease;
  color: gray;
}

.lock-icon.open {
  content: '🔓';
  transform: rotate(15deg);
  color: green;
}

@keyframes bounceIn {
  0%   { transform: scale(0.3); opacity: 0; }
  50%  { transform: scale(1.05); opacity: 1; }
  70%  { transform: scale(0.9); }
  100% { transform: scale(1); }
}
</style>

<!--ROBOTE DE CANDADO-->
<style>
.lock-icon {
  display: inline-block;
  animation: pulse 1s infinite ease-in-out;
}

@keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.3); }
  100% { transform: scale(1); }
}
</style>


<!--REBOTE DE IMAGEN AL SELECCIONAR-->
<style>
@keyframes bounce-tap {
  0% { transform: scale(1); }
  25% { transform: scale(1.05); }
  50% { transform: scale(0.95); }
  75% { transform: scale(1.02); }
  100% { transform: scale(1); }
}

.xplus.tap-animate {
  animation: bounce-tap 0.5s ease;
}
</style>



</head>
<body class="<?php echo $theme; ?>">
    
    

  <div id="loader-container">
  <div class="loader">

    <div class="logo-ring">
      <img src="Logo Poster MovieTx PNG/Logo MovieTx.png" alt="MovieTx">
    </div>

    <h1 class="brand">MovieTx</h1>

    <div class="progress">
      <div class="progress-fill" id="barra"></div>
    </div>

    <div class="progress-text" id="porcentaje">0%</div>

    <p class="loading-text">Cargando contenido</p>

  </div>
</div>

<style>

/* BLOQUEO SCROLL */
body.loading {
  overflow: hidden;
}

/* CONTENEDOR */
#loader-container {
  position: fixed;
  inset: 0;
  background: radial-gradient(circle at center, #0a0a0a 0%, #000 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
  font-family: 'Poppins', sans-serif;
}

/* CONTENIDO */
.loader {
  text-align: center;
  animation: fadeIn 0.8s ease;
}

/* LOGO + ARO */
.logo-ring {
  width: 130px;
  height: 130px;
  border-radius: 50%;
  margin: 0 auto 20px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* aro animado limpio */
.logo-ring::before {
  content: "";
  position: absolute;
  inset: -4px;
  border-radius: 50%;
  background: conic-gradient(
    #00ffe1,
    #00aaff,
    #7a00ff,
    #00ffe1
  );
  animation: spin 1.8s linear infinite;
  filter: blur(6px);
  opacity: 0.8;
}

/* centro */
.logo-ring::after {
  content: "";
  position: absolute;
  inset: 6px;
  background: #000;
  border-radius: 50%;
}

/* logo */
.logo-ring img {
  width: 65px;
  z-index: 2;
  filter: drop-shadow(0 0 10px rgba(0,255,225,0.5));
}

/* título */
.brand {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 20px;
  color: white;
  letter-spacing: 1px;
}

/* barra */
.progress {
  width: 240px;
  height: 6px;
  background: rgba(255,255,255,0.1);
  border-radius: 20px;
  overflow: hidden;
  margin: 0 auto;
}

.progress-fill {
  width: 0%;
  height: 100%;
  background: linear-gradient(90deg, #00ffe1, #00aaff);
  border-radius: 20px;
  box-shadow: 0 0 10px rgba(0,255,225,0.6);
  transition: width 0.3s ease;
}

/* porcentaje */
.progress-text {
  margin-top: 8px;
  font-size: 1rem;
  color: #00ffe1;
  font-weight: 600;
}

/* texto */
.loading-text {
  margin-top: 25px;
  font-size: 0.9rem;
  color: #aaa;
}

/* animaciones */
@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(15px); }
  to { opacity: 1; transform: translateY(0); }
}

/* 📱 RESPONSIVE */
@media (max-width: 600px) {

  .logo-ring {
    width: 100px;
    height: 100px;
  }

  .logo-ring img {
    width: 55px;
  }

  .brand {
    font-size: 1.6rem;
  }

  .progress {
    width: 180px;
  }

}

</style>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const loader = document.getElementById("loader-container");
  const barra = document.getElementById("barra");
  const porcentaje = document.getElementById("porcentaje");

  if (!loader) return;

  document.body.classList.add("loading");

  let progress = 0;
  let finished = false;

  function update(val){
    progress = Math.min(100, val);
    barra.style.width = progress + "%";
    porcentaje.textContent = Math.floor(progress) + "%";
  }

  function finish(){
    if(finished) return;
    finished = true;

    update(100);

    setTimeout(() => {
      loader.style.opacity = "0";
      loader.style.transition = "opacity 0.6s ease";

      setTimeout(() => {
        loader.remove();
        document.body.classList.remove("loading");
      }, 600);

    }, 200);
  }

  // progreso suave
  const interval = setInterval(() => {
    if(progress < 90){
      update(progress + 1.2);
    }
  }, 70);

  // carga real
  window.addEventListener("load", () => {
    clearInterval(interval);
    finish();
  });

  // fallback
  setTimeout(() => {
    clearInterval(interval);
    finish();
  }, 4000);

});
</script>


<script>

setInterval(function(){

fetch("inicio.php?check_status=1")
.then(res => res.text())
.then(data => {

if(data.trim() === "logout"){

alert("Tu cuenta fue suspendida por el administrador");

window.location.href = "index.php";

}

});

}, 5000); // revisa cada 5 segundos

</script>

  <!-- FIN -->
  
    <!-- Script de actualizar pagina -->
    
    <script>
document.addEventListener('DOMContentLoaded', () => {
  const refreshBtn = document.getElementById('refreshBtn');

  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      // 🔄 Refresca la página directamente desde el servidor (sin cache)
      location.reload(true);
    });
  }
});
</script>

<!-- ESTILO DE DE REFRECO-->
    <style>
    #refreshBtn svg {
  transition: transform 0.5s ease;
  cursor: pointer;
}

#refreshBtn:active svg {
  transform: rotate(360deg);
}

    </style>
    
    <!-- fin -->


<!--CODIGO BLOQUEAR RETROCESO-->
<script>
(function() {
  // ✅ Bloquea retroceso sin generar cargas fantasmas
  let ignorePop = false;

  function lockBack() {
    if (!ignorePop) {
      ignorePop = true;
      history.pushState(null, null, location.href);
      setTimeout(() => (ignorePop = false), 50);
    }
  }

  // Inicial: empuja una sola vez
  history.pushState(null, null, location.href);

  // Captura el retroceso y evita recarga visual
  window.addEventListener("popstate", (event) => {
    lockBack();
  });

  // Evita que al volver desde caché se vea como recarga
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      lockBack();
    }
  });

  // Bloquea tecla "Atrás" física en Android y backspace fuera de inputs
  document.addEventListener("keydown", (e) => {
    const tag = e.target.tagName.toLowerCase();
    if (e.key === "Backspace" && tag !== "input" && tag !== "textarea") {
      e.preventDefault();
    }
  });
})();
</script>





    
<script>/*<![CDATA[*/ (localStorage.getItem('mode')) === 'darkmode' ? document.querySelector('#mainCont').classList.add('drK') : document.querySelector('#mainCont').classList.remove('drK') /*]]>*/</script>
<input class="prfI hidden" id="offPrf" type="checkbox"/>
<input class="navI hidden" id="offNav" type="checkbox"/>
<header class="app-header">

<header class="app-header">

  <div class="header-container">

    <div class="brand-box">
      <h1 class="brand-title">MovieTx</h1>
    </div>

    <div class="actions-box">

      <button class="icon-btn" id="reloadPage">
        <svg viewBox="0 0 24 24">
          <path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v6h-6"/>
        </svg>
      </button>

      <button class="icon-btn" onclick="openThemeMenu()">
        <svg viewBox="0 0 24 24">
          <path d="M12 3a9 9 0 0 0 0 18c3.9 0 7.2-2.5 8.4-6a7 7 0 0 1-9.4-9.4A9 9 0 0 0 12 3z"/>
        </svg>
      </button>

      <a href="Mostras Mas/Busqueda.php" class="icon-btn">
        <svg viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="7"/>
          <line x1="20" y1="20" x2="16.5" y2="16.5"/>
        </svg>
      </a>

      <a href="http://action_exit" class="icon-btn">
        <svg viewBox="0 0 24 24">
          <path d="M10 17l5-5-5-5M15 12H3"/>
        </svg>
      </a>

    </div>

  </div>
</header>

<!-- MODAL -->
<div id="themeModal" class="theme-modal">
  <div class="theme-box">

    <h3>Seleccionar tema</h3>

    <div class="theme-grid">
      <button onclick="setTheme('light')" class="theme-btn light"></button>
      <button onclick="setTheme('dark')" class="theme-btn dark"></button>
      <button onclick="setTheme('blue')" class="theme-btn blue"></button>
      <button onclick="setTheme('sky')" class="theme-btn sky"></button>
      <button onclick="setTheme('red')" class="theme-btn red"></button>
      <button onclick="setTheme('pink')" class="theme-btn pink"></button>
    </div>

    <button class="close-btn" onclick="closeThemeMenu()">Cerrar</button>

  </div>
</div>
<style>
:root {
  --bg: #ffffff;
  --card: #f5f5f5;

  /* 🔥 AUTOMÁTICO */
  --text: #000;
  --text-inverse: #fff;
}

/* TEMAS */
/* TEMAS LIMPIOS Y ESTABLES */
body.light {
  --bg:#ffffff;
  --card:#f5f5f5;
  --text:#000;
}

body.dark {
  --bg:#0a0a0a;
  --card:#141414;
  --text:#ffffff;
}

.theme-box h3 {
  color: var(--text); /* ahora seguirá el tema */
}
.close-btn {
  background: var(--text);
  color: var(--bg);
}
.theme-btn {
  outline: none;
  border: 2px solid transparent;
  transition: all 0.2s;
}
.theme-btn.active {
  border-color: var(--text);
}

body.blue {
  --bg:#0d1b2a;
  --card:#1b263b;
  --text:#e0e1dd;
}

body.sky {
  --bg:#caf0f8;
  --card:#90e0ef;
  --text:#03045e;
}

body.red {
  --bg:#1a0000;
  --card:#3b0000;
  --text:#ffffff;
}

body.pink {
  --bg:#ffe4ec;
  --card:#ffb3c6;
  --text:#590d22;
}

/* BASE */
body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  transition: 0.3s;
  font-family: system-ui, -apple-system, sans-serif;
}

/* BOTON ACTIVO */
.theme-btn.active {
  outline: 3px solid var(--text);
  transform: scale(1.1);
}

/* EN TEMAS OSCUROS */
body.dark .theme-btn.active,
body.blue .theme-btn.active,
body.red .theme-btn.active {
  outline: 3px solid #fff;
}

/* HEADER */
.app-header {
  position: sticky;
  top: 0;
  background: var(--card);
  border-bottom: 1px solid rgba(0,0,0,0.1);
  z-index: 100;
}

.header-container {
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding: 10px clamp(10px, 4vw, 20px);
}

/* LOGO */
.brand-title {
  font-size: clamp(16px, 4vw, 22px);
  color: var(--text);
}

/* ACCIONES */
.actions-box {
  display: flex;
  gap: clamp(6px, 2vw, 12px);
}

/* BOTONES */
.icon-btn {
  width: clamp(36px, 9vw, 42px);
  height: clamp(36px, 9vw, 42px);
  background: var(--card);
  border-radius: 12px;
  display:flex;
  align-items:center;
  justify-content:center;
  cursor:pointer;
  transition:0.2s;
}

.icon-btn:active {
  transform: scale(0.9);
}

.icon-btn svg {
  width: clamp(18px, 4vw, 22px);
  height: clamp(18px, 4vw, 22px);
  stroke: var(--text);
}

/* MODAL BASE */
.theme-modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  display: none;
  z-index: 999;
}

.theme-modal.active {
  display: flex;
  justify-content: center;
  align-items: center;
}

/* CAJA */
.theme-box {
  background: var(--card);
  padding: 20px;
  border-radius: 18px;
  width: 90%;
  max-width: 360px;
  text-align: center;
  animation: fadeIn 0.3s ease;
}

/* GRID */
.theme-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin: 20px 0;
}

/* BOTONES DE COLOR */
.theme-btn {
  height: 45px;
  border-radius: 12px;
  border: none;
  cursor: pointer;
}

/* COLORES */
.theme-btn.light { background:#fff; border:1px solid #ccc; }
.theme-btn.dark { background:#000; }
.theme-btn.blue { background:#1b263b; }
.theme-btn.sky { background:#48cae4; }
.theme-btn.red { background:#9d0208; }
.theme-btn.pink { background:#ff4d6d; }

/* CERRAR */
.close-btn {
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: var(--text);
  color: var(--bg);
}

/* 📱 MOBILE (clave) */
@media (max-width: 600px) {

  .theme-modal {
    align-items: flex-end;
  }

  .theme-box {
    width: 100%;
    max-width: 100%;
    border-radius: 20px 20px 0 0;
    animation: slideUp 0.3s ease;
  }

}

/* ANIMACIONES */
@keyframes fadeIn {
  from { opacity:0; transform:scale(0.95); }
  to { opacity:1; transform:scale(1); }
}

@keyframes slideUp {
  from { transform:translateY(100%); }
  to { transform:translateY(0); }
}
</style>

<script>
// RECARGAR
document.getElementById("reloadPage").onclick = () => location.reload();

// MODAL
function openThemeMenu() {
  document.getElementById("themeModal").classList.add("active");
}

function closeThemeMenu() {
  document.getElementById("themeModal").classList.remove("active");
}

// TEMA
function setTheme(theme) {
  document.body.className = theme;


  // 💥 MARCAR ACTIVO
  marcarTemaActivo(theme);

  closeThemeMenu();
}

function marcarTemaActivo(theme) {
  const buttons = document.querySelectorAll(".theme-btn");

  buttons.forEach(btn => {
    btn.classList.remove("active");

    if (btn.classList.contains(theme)) {
      btn.classList.add("active");
    }
  });
}
</script>


<!-- AGREGAR ESTO ANTES DE </body> -->
<script>
/* Función para alternar modo oscuro/claro */
function darkMode() {
  try {
    const root = document.getElementById('mainCont') || document.documentElement;
    const btn = document.querySelector('.tDark') || document.querySelector('[aria-label="Dark"]');

    // Alterna la clase que ya usas para modo oscuro
    const isDark = root.classList.toggle('drK');

    // Guarda elección
    localStorage.setItem('mode', isDark ? 'darkmode' : 'lightmode');

    // Para accesibilidad: indica estado pulsado
    if (btn) btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');

    // Pequeña animación al icono (giro)
    const svg = btn && btn.querySelector('svg');
    if (svg) {
      svg.style.transition = 'transform 300ms ease';
      svg.style.transform = 'rotate(20deg)';
      setTimeout(() => { svg.style.transform = ''; }, 300);
    }
  } catch (e) {
    console.error('darkMode error:', e);
  }
}

/* Asegurar que el atributo aria-pressed refleje el estado al cargar */
document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('mainCont') || document.documentElement;
  const btn = document.querySelector('.tDark') || document.querySelector('[aria-label="Dark"]');
  const isDarkStored = localStorage.getItem('mode') === 'darkmode';
  if (isDarkStored) root.classList.add('drK'); else root.classList.remove('drK');
  if (btn) btn.setAttribute('aria-pressed', isDarkStored ? 'true' : 'false');

  // También permite cambiar con la tecla Enter/Espacio cuando el span tiene role="button"
  if (btn) {
    btn.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' || ev.key === ' ') {
        ev.preventDefault();
        darkMode();
      }
    });
  }
});
</script>

</header>


</div>

<!-- MENU PRO -->
<div class="menu-pro">
  <a class="menu-pro-item active" id="inicio" href="#"><span>Inicio</span></a>
  <a class="menu-pro-item" id="peliculas" href="#"><span>Peliculas</span></a>
  <a class="menu-pro-item" id="series" href="#"><span>Series</span></a>
  <a class="menu-pro-item" id="trailers" href="#"><span>Trailers</span></a>
</div>
<!--MENU SUPERIOR COLORES Y-->
<style>
/* VARIABLES EXTRA */
:root {
  --border: rgba(0,0,0,0.1);
  --hover: rgba(0,0,0,0.06);

  --menu-bg: var(--card);
  --menu-text: var(--text);
  --menu-active-bg: var(--text);
  --menu-active-text: var(--bg);
}

/* AJUSTE PARA TEMAS OSCUROS */
body.dark,
body.blue,
body.red {
  --border: rgba(255,255,255,0.1);
  --hover: rgba(255,255,255,0.08);
}

/* CONTENEDOR */
.menu-pro {
  display: flex;
  overflow-x: auto;
  gap: 10px;
  padding: 10px 15px;

  /* 🔥 CLAVE: usar directamente las variables base */
  background: var(--card);

  border-bottom: 1px solid rgba(0,0,0,0.1);
}

.menu-pro::-webkit-scrollbar {
  display: none;
}

.menu-pro-item {
  position: relative;
  color: var(--text);
  text-decoration: none;
  padding: 7px 14px;
  border-radius: 20px;
  transition: 0.25s;
  overflow: hidden;
  z-index: 1;
}

.menu-pro-item:hover {
  background: rgba(0,0,0,0.06);
}

/* 🌙 ARREGLA HOVER EN OSCURO */
body.dark .menu-pro-item:hover,
body.blue .menu-pro-item:hover,
body.red .menu-pro-item:hover {
  background: rgba(255, 255, 255, 0.15);
}

:root {
  --accent: #000;        /* default light */
  --accent-text: #fff;
}

/* TEMAS */
body.dark {
  --accent: #fff;
  --accent-text: #000;
}

body.blue {
  --accent: #2196f3;
  --accent-text: #fff;
}

body.red {
  --accent: #e53935;
  --accent-text: #fff;
}

.menu-pro-item {
  position: relative;
  color: var(--text);
  text-decoration: none;
  padding: 7px 14px;
  border-radius: 20px;
  transition: 0.25s;
  overflow: hidden;
  z-index: 1;
}

/* TEXTO ENCIMA */
.menu-pro-item span {
  position: relative;
  z-index: 2;
}

/* ITEM ACTIVO */
.menu-pro-item.active {
  color: var(--text);
  font-weight: 600;
}

/* 🌈 BORDE ARCOIRIS */
.menu-pro-item.active::before {
  content: "";
  position: absolute;
  inset: -2px;
  border-radius: 20px;

  background: conic-gradient(
    #ff0000,
    #ff7300,
    #fffb00,
    #48ff00,
    #00f7ff,
    #0066ff,
    #a200ff,
    #ff0000
  );

  animation: giroMenu 6s linear infinite;
  z-index: 0;
}

/* FONDO INTERNO */
.menu-pro-item.active::after {
  content: "";
  position: absolute;
  inset: 2px;
  background: var(--card);
  border-radius: 18px;
  z-index: 1;
}

/* HOVER NORMAL */
.menu-pro-item:hover {
  transform: scale(1.05);
}

/* ANIMACIÓN */
@keyframes giroMenu {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}


/* MOBILE */
@media (max-width: 600px) {
  .menu-pro-item {
    font-size: 13px;
    padding: 6px 12px;
  }
}
</style>

<script>
// ===============================
// MENU PRO LIMPIO
// ===============================

const menuItems = document.querySelectorAll(".menu-pro-item");

function activarMenu(id) {
  menuItems.forEach(item => item.classList.remove("active"));
  const el = document.getElementById(id);
  if (el) el.classList.add("active");
}

// CLICK
menuItems.forEach(item => {
  item.addEventListener("click", () => {
    activarMenu(item.id);
  });
});

// INICIO SIEMPRE
window.addEventListener("DOMContentLoaded", () => {
  activarMenu("inicio");

  if (typeof loadContent === "function") {
    loadContent("inicio.html");
  }
});
</script>

<!-- ======= NUEVO CARRUSEL INTEGRADO (us_carousel) ======= -->
<div class="us_carousel_wrap" id="us_carousel_wrap" aria-label="Carrusel de imágenes">
  <div class="us_carousel" id="us_carousel">
    <div class="us_track" id="us_track">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/m33dIcuCsuJiHZkiiArv41oXuWX.jpg" alt="Slide 1">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/32y5F0HdA4mzgcG9eiHrlYz7jSu.jpg" alt="Slide 2">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/k3wtmkySU8pHSCfjNqbp5f81DS0.jpg" alt="Slide 3">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/5YI2iRIoR3PuoxBnPEbauLZYu3k.jpg" alt="Slide 4">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/7rouRlEltLF6QehIFwO08g3zF3b.jpg" alt="Slide 5">
      <img class="us_slide" loading="lazy" src="https://image.tmdb.org/t/p/w780/4jrdU5p1K8H61Fgmk6pCHEb5LBf.jpg" alt="Slide 6">
    </div>
    <div class="us_dots" id="us_dots" aria-hidden="false"></div>
  </div>
</div>

<!-- =======  ENLACES DE PELICULAS Y SERIES DESDE EL CARRUSEL. ======= -->
<script>
/*
  Script: us_carousel -> click/tap-to-navigate by alt text
  - No cambia la estructura del carrusel.
  - Usa mappingAltToUrl para asignar alt -> destino.
  - Si la imagen tiene atributo data-link se usa ese primero.
*/

(function(){
  // Mapeo: ajustar aquí los targets según tus alt exactos
  const mappingAltToUrl = {
    "Slide 1": "View Peliculas/Reproductor Universal.php?id=super_mario_bros_2",
    "Slide 2": "View Peliculas/Reproductor Universal Series.php?id=from",
    "Slide 3": "View Peliculas/Reproductor Universal.php?id=depredador_tierras_salvajes",
    "Slide 4": "View Peliculas/Reproductor Universal.php?id=encerrado_2025",
    "Slide 5": "View Peliculas/Reproductor Universal.php?id=la_conexion_sueca",
    "Slide 6": "View Peliculas/Reproductor Universal.php?id=love_me_love_me"
  };

  // Selector de slides - mantiene compatibilidad con tu carrusel
  const slides = Array.from(document.querySelectorAll('.us_slide'));

  if(!slides.length) return;

  slides.forEach(img => {
    // Si prefieres, agrega data-link directamente al <img> y se usará
    // <img class="us_slide" data-link="View Peliculas/..." ...>

    // --- Click (ratón) ---
    img.addEventListener('click', (e) => {
      // Si el evento fue parte de un arrastre (drag), cancela.
      // (no queremos interferir con el swipe del carousel)
      if (img._wasDragged) {
        img._wasDragged = false;
        return;
      }
      handleNavigate(img);
    });

    // --- Touch (para móviles): detecta swipe vs tap ---
    let startX = 0, startY = 0, startT = 0;
    const MAX_MOVE = 20; // px tolerancia para considerar un "tap"
    const MAX_TIME = 400; // ms tolerancia temporal para tap

    img.addEventListener('touchstart', (ev) => {
      const t = ev.touches[0];
      startX = t.clientX;
      startY = t.clientY;
      startT = Date.now();
      img._wasDragged = false;
    }, {passive:true});

    img.addEventListener('touchmove', (ev) => {
      const t = ev.touches[0];
      const dx = Math.abs(t.clientX - startX);
      const dy = Math.abs(t.clientY - startY);
      if (dx > MAX_MOVE || dy > MAX_MOVE) {
        // marcar que hubo movimiento para que click/tap no dispare navegación
        img._wasDragged = true;
      }
    }, {passive:true});

    img.addEventListener('touchend', (ev) => {
      const dt = Date.now() - startT;
      if (img._wasDragged) {
        // si se arrastró, no navegar — pero dejamos el flag para el click handler también
        return;
      }
      if (dt <= MAX_TIME) {
        // Es un tap corto -> navegar
        handleNavigate(img);
      }
    });

    // --- evita que clicks por ejemplo en elementos internos hagan doble-navegación ---
    img.addEventListener('auxclick', (e) => {
      // evita middle-clicks inesperados (opcional)
      e.preventDefault();
    });
  });

  // Navegación centralizada
  function handleNavigate(img){
    // Prioridad: data-link en la propia imagen
    const dl = img.getAttribute('data-link');
    if (dl && dl.trim() !== "") {
      navigateTo(dl);
      return;
    }

    // Usa el alt como identificador
    const alt = (img.getAttribute('alt') || "").trim();
    if (alt && mappingAltToUrl.hasOwnProperty(alt)) {
      navigateTo(mappingAltToUrl[alt]);
      return;
    }

    // Si no hay mapping, opcional: intentar buscar por texto en aria-label o title
    const title = (img.getAttribute('title') || "").trim();
    if (title && mappingAltToUrl.hasOwnProperty(title)) {
      navigateTo(mappingAltToUrl[title]);
      return;
    }

    // fallback: si no hay destino definido, no hace nada (o abrir en nuevo tab)
    console.warn('us_carousel: no hay destino definido para imagen con alt="' + alt + '". Añade data-link o actualiza mappingAltToUrl.');
  }

  // Navegación segura (puedes cambiar _blank a same-window si querés)
  function navigateTo(href){
    if(!href) return;
    // Si es relativo y quieres mismo comportamiento que un <a>, usamos location.href
    window.location.href = href;
    // Si preferís abrir en nueva pestaña:
    // window.open(href, '_blank');
  }

})();
</script>



<!-- ======= FIN NUEVO CARRUSEL ======= -->
</div></div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
  const imgs = document.querySelectorAll("img[data-src]");

  const cargar = (img) => {
    img.src = img.dataset.src;
    img.removeAttribute("data-src");
  };

  const obs = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        cargar(entry.target);
        observer.unobserve(entry.target);
      }
    });
  });

  imgs.forEach((img) => obs.observe(img));
});

</script>

<!--CANDADO DE IMAGEN CUANDO NO PONGAS EL ENLACE ESTE NO SE QUITARA, CUANDO LO PONGAS ESTA DESAPARECERA (CSS)-->
<style>

/* =====================================================
   RESET SOLO PARA EL CARRUSEL (no global)
===================================================== */
.us_carousel,
.us_carousel * {
  box-sizing: border-box;
}

/* ================== CAROUSEL ================== */
.us_carousel {
  position: relative;
  overflow: hidden;
  margin: 10px;
  border-radius: 12px;
}

/* TRACK */
.us_track {
  display: flex;
  transition: transform .5s ease;
}

/* SLIDE */
.us_slide {
  min-width: 100%;
  width: 100%;
  display: block;
  object-fit: cover;
  user-select: none;
  -webkit-user-drag: none;
  cursor: pointer;
}

/* ================== DOTS ================== */
.us_dots {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 8px;
}

.us_dots button {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  border: none;
  background: rgba(255,255,255,.5);
  cursor: pointer;
}


.us_dots button.active,
.us_dots button[aria-pressed="true"],
.us_dots button.is-active {
  background: rgb(255, 0, 119);
  transform: scale(1.3);
}

/* Evita congelamientos durante drag */
.us_track.is-dragging {
  transition: none !important;
}


/* ================== RESPONSIVE ================== */
@media (min-width: 768px) {
  .us_carousel {
    max-width: 1200px;
    margin: 20px auto;
  }

  .us_slide {
    height: 420px;
  }
}

@media (max-width: 767px) {
  .us_slide {
    height: 220px;
  }
}

.lock-icon {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(0,0,0,0.7);
  color: white;
  padding: 4px 6px;
  border-radius: 5px;
  font-size: 14px;
  z-index: 5;
  display: none; /* Oculto por defecto */
}

</style>


<style>
  /* 🔥 IMÁGENES RESPONSIVE PRO */
img {
  max-width: 100%;
  height: auto;
  display: block;
}

/* TARJETAS */
/* CONTENEDOR DE CARD */
.xplus {
  position: relative;
  width: 100%;
  max-width: 180px; /* tamaño en PC */
  margin: auto;
}

/* IMAGEN RESPONSIVE */
.xaviec {
  width: 100%;
  height: auto;
  aspect-ratio: 2 / 3; /* formato tipo poster */
  object-fit: cover;
  border-radius: 10px;
  display: block;
}

/* TEXTO */
.xplus i {
  display: block;
  width: 100%;              /* 🔥 CLAVE */
  max-width: 100%;          /* 🔥 evita desbordes */
  
  font-size: 14px;
  margin-top: 6px;
  color: var(--text);
  text-align: center;

  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ICONO LOCK */
.lock-icon {
  position: absolute;
  top: 6px;
  right: 6px;
  font-size: 14px;
}

@media (max-width: 768px) {
  .xplus {
    max-width: 120px;
  }

  .xplus i {
    font-size: 11px;        /* 🔥 más pequeño */
    padding: 0 4px;         /* 🔥 aire a los lados */
    
    line-height: 1.2;       /* 🔥 mejora lectura */
  }
}

.seguir-viendo {
    padding: 15px;
}

.seguir-viendo h3 {
    margin-bottom: 10px;
}

/* CONTENEDOR SCROLL */
.scroll-horizontal {
    display: flex;
    overflow-x: auto;
    gap: 15px;
    padding-bottom: 10px;
    scroll-behavior: smooth;
}

.scroll-horizontal::-webkit-scrollbar {
    display: none;
}

/* ITEM */
.item {
    position: relative;
    min-width: 300px;   /* 🔥 más ancho */
    height: 170px;      /* 🔥 más alto */
    flex: 0 0 auto;
    border-radius: 14px;
    overflow: hidden;
    text-decoration: none;
}

/* IMAGEN */
.item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* 🔥 PROGRESO (ESTILO NETFLIX) */
.barra {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 6px;

    background: rgba(0,0,0,0.6);

    z-index: 4; /* 🔥 MÁS ALTO QUE .info */
}

/* 🔥 PROGRESO (BIEN VISIBLE) */
.progreso {
    height: 100%;

    background: linear-gradient(90deg, #00ff0d, #00ff88);

    box-shadow: 
        0 0 10px rgba(0,255,100,0.9),
        0 0 20px rgba(0,255,100,0.6);

    border-radius: 0 4px 4px 0;

    transition: width 0.4s ease;
}

/* TEXTO SOBRE IMAGEN */
.item .info {
    position: absolute;
    bottom: 6px; /* 🔥 deja espacio para la barra */
    width: 100%;
    padding: 8px;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
    z-index: 3;
}

.item .info p {
    color: #fff;
    font-size: 12px;
    margin: 0;
}

/* 📱 RESPONSIVE */
@media (max-width: 768px) {
    .item {
        min-width: 220px;  /* antes 180 */
        height: 130px;     /* antes 110 */
    }
}

.eliminar {
    position: absolute;
    top: 6px;
    right: 6px;

    width: 20px;        /* 🔥 más chico */
    height: 20px;

    background: rgba(0,0,0,0.65);
    color: #fff;

    border-radius: 50%;
    font-size: 12px;    /* 🔥 X más chica */
    font-weight: bold;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;
    z-index: 5;

    transition: all 0.2s ease;
}

.eliminar:hover {
    background: rgba(255, 0, 0, 0.9);
    transform: scale(1.15);
}

@media (max-width: 768px) {
    .eliminar {
        opacity: 1;
    }
}
</style>

<script>
document.addEventListener("click", function(e){

  if(e.target.classList.contains("eliminar")){

    e.preventDefault();
    e.stopPropagation(); // 🔥 clave

    const id = e.target.dataset.id;

    fetch("View Peliculas/eliminar_inicio.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "id=" + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {

      if(data.status === "ok"){
        e.target.closest(".item").remove(); // 🔥 borrar visual
      }

    });

  }

});
</script>

<script>
document.addEventListener("click", function(e){

  if(e.target.classList.contains("eliminar")){
    
    e.preventDefault();
    e.stopPropagation();

    const id = e.target.getAttribute("data-id");

    fetch("View Peliculas/eliminar_serie.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "id=" + encodeURIComponent(id)
    })
    .then(res => res.json())
    .then(data => {
      if(data.status === "ok"){
        // 🔥 eliminar visualmente
        e.target.closest(".item").remove();
      } else {
        alert("Error al eliminar");
      }
    });

  }

});
</script>


<!--FUNCIONAMIENTO DEL CANDADO NO BORRAR-->
<script>
document.querySelectorAll('.card-link').forEach(link => {
  const realHref = link.getAttribute('href');
  const dataHref = link.getAttribute('data-href');

  if (!dataHref || dataHref.trim() === "") {
    // No hay HTML cargado aún
    link.querySelector('.lock-icon').style.display = 'block';
    link.setAttribute('href', 'javascript:void(0);'); // evita redirección
    link.style.pointerEvents = 'none'; // no clickeable
    link.style.opacity = '0.5'; // visual desactivado
  } else {
    // HTML cargado
    link.setAttribute('href', dataHref);
  }
});
</script>
<script>
  window.addEventListener("load", function () {
    const candado = document.getElementById("candado");

    // Simulamos carga o validación
    setTimeout(() => {
      if (candado) {
        candado.textContent = "🔓"; // cambia el ícono
        candado.classList.remove("cerrado");
        candado.classList.add("abierto");
      }
    }, 2000); // Espera 2 segundos antes de "abrir"
  });
</script>

<script>
document.addEventListener("click", function(e){

    if(e.target.classList.contains("eliminar")){

        const btn = e.target;
        const item = btn.closest(".item");

        // 🔥 eliminar visualmente
        item.remove();

        // 🔥 verificar si ya no quedan items
        const contenedor = document.getElementById("seguirViendo");

        if(contenedor.querySelectorAll(".item").length === 0){
            contenedor.remove(); // 💀 desaparece todo el bloque
        }

        // 🔥 OPCIONAL: enviar al backend (si ya tenés PHP para borrar)
        /*
        fetch("eliminar.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "id=" + btn.dataset.id
        });
        */
    }

});
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

  document.querySelectorAll(".eliminar").forEach(btn => {

    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation(); // 🔥 evita que abra el link

      const movieId = btn.dataset.id;
      const item = btn.closest(".item");

      if (!movieId) return;

      fetch(" View Peliculas/eliminar_historial.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: "movie_id=" + encodeURIComponent(movieId)
      })
      .then(res => res.json())
      .then(data => {

        if (data.status === "success") {

          // 🔥 ANIMACIÓN (opcional pero pro)
          item.style.transition = "0.3s";
          item.style.opacity = "0";
          item.style.transform = "scale(0.9)";

          setTimeout(() => {
            item.remove();

            // 🔥 si ya no hay items, ocultar todo el bloque
            const container = document.querySelectorAll(".item");
            if (container.length === 0) {
              document.getElementById("seguirViendo")?.remove();
            }

          }, 300);

        } else {
          console.log("Error:", data.msg);
        }

      })
      .catch(err => console.log("Fetch error:", err));

    });

  });

});
</script>


<!--TECLADOOOOO-->
<style>
.card-link {
  outline: none;
  position: relative;
  z-index: 1;
}

/* contenedor controla overflow */
.xplus {
  position: relative;
  overflow: hidden;
  border-radius: 10px;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
}

/* 🔥 ACTIVO (corregido) */
.active-card {
  z-index: 10; /* se eleva sobre las demás */
}

.active-card .xplus {
  transform: scale(1.08); /* 👈 más controlado */
  border: 2px solid #00ffe1;

  box-shadow: 
    0 0 10px rgba(0,255,225,0.6),
    0 0 20px rgba(0,255,225,0.4);
}

/* 🔥 CLAVE: evitar que tape texto de otras */
.scrollmenu {
  padding: 20px 0; /* 👈 espacio vertical para crecer */
}

/* separa cards para que no se pisen */
.card-link {
  margin: 0 6px;
}

/* hover suave */
.card-link:hover .xplus {
  transform: scale(1.06);
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const rows = document.querySelectorAll(".scrollmenu");

  let currentRow = 0;
  let currentIndex = 0;

  function getCards(row) {
    return rows[row].querySelectorAll(".card-link");
  }

  function updateFocus() {

    // limpiar todo
    document.querySelectorAll(".card-link").forEach(c => {
      c.classList.remove("active-card");
    });

    const cards = getCards(currentRow);
    if (!cards.length) return;

    // evitar overflow
    currentIndex = Math.min(currentIndex, cards.length - 1);

    const active = cards[currentIndex];
    active.classList.add("active-card");

    // 🔥 scroll horizontal PERFECTO
    active.scrollIntoView({
      behavior: "smooth",
      inline: "center",
      block: "nearest"
    });

    // 🔥 scroll vertical suave
    rows[currentRow].scrollIntoView({
      behavior: "smooth",
      block: "center"
    });
  }

  updateFocus();

  document.addEventListener("keydown", (e) => {

    const cards = getCards(currentRow);

    switch (e.key) {

      case "ArrowRight":
        currentIndex = Math.min(currentIndex + 1, cards.length - 1);
        break;

      case "ArrowLeft":
        currentIndex = Math.max(currentIndex - 1, 0);
        break;

      case "ArrowDown":
        if (currentRow < rows.length - 1) {
          currentRow++;
        }
        break;

      case "ArrowUp":
        if (currentRow > 0) {
          currentRow--;
        }
        break;

      case "Enter":
        const active = getCards(currentRow)[currentIndex];
        const href = active.getAttribute("data-href");
        if (href) window.location.href = href;
        return;

      default:
        return;
    }

    e.preventDefault(); // 🔥 evita scroll raro del navegador
    updateFocus();
  });

});
</script>

<br>

<!-- ========== CONTENIDO ORIGINAL DEL INDEX ========== -->
<div id="contenido-orig">

<?php if (!empty($items)): ?>

<div class="seguir-viendo" id="seguirViendo">
    <div class="poster">
        <h4 class="alignleft"><span>Seguir viendo</span></h4>
    </div>
    <br>

    <div class="scroll-horizontal">

        <?php foreach($items as $row): ?>

            <?php 
                $duracion = ($row['tipo'] == 'serie') ? 2700 : 5400;

                $porcentaje = isset($row['progreso']) 
                    ? min(100, ($row['progreso'] / $duracion) * 100) 
                    : 0;

                $link = ($row['tipo'] == 'serie') 
                    ? "View Peliculas/Reproductor Universal Series.php?id=".$row['id']
                    : "View Peliculas/Reproductor Universal.php?id=".$row['id'];
            ?>

            <a href="<?= $link ?>" class="item">

                <img src="<?= $row['imagen'] ?>" alt="<?= $row['titulo'] ?>">

                <span class="eliminar" data-id="<?= $row['id'] ?>">✕</span>

                <?php if ($porcentaje > 0): ?>
                <div class="barra">
                    <div class="progreso" style="width: <?= $porcentaje ?>%"></div>
                </div>
                <?php endif; ?>

                <div class="info">
                    <p><?= $row['titulo'] ?></p>
                </div>

            </a>

        <?php endforeach; ?>

    </div>
</div>

<?php endif; ?>


  <!-- Ejemplo: aquí debe ir TODO el contenido original del index -->

  <div class="poster">
    <h4 class="alignleft"><span>Pronto</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/zCgPbsPJ7d1qlXVn1cKvTlcob1H.jpg"/>
        <i>Invencible</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/gNobl6shHGj6cJ209qcV2pKkfOk.jpg"/>
        <i>Amor animal</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/sq7dGBq8yqtEouPyyMDfz2HFwjO.jpg"/>
        <i>56 días</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/pLYjbFYHOX1SrHs5BQsGlmv83lZ.jpg"/>
        <i>Dragon Ball GT</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/spMIIipBp3sz24zIG1oXgGFfcNZ.jpg"/>
        <i>Moisés y los Diez...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/mGEeh9Vy0fQwtkGP8JoteePKamv.jpg"/>
        <i>Elite</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/fDdIlvGhBNnljro1ON6T9Q3hRpq.jpg"/>
        <i>Steven Universe: Futuro</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/zAh9J37vxWhdwIBGp9yFFe1p0Cx.jpg"/>
        <i>Steven Universe</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vHtH4xdcTbaCVftGwaeGFHfOB3p.jpg"/>
        <i>Avatar 3: fuegos y Ceniza</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uOOtwVbSr4QDjAGIifLDwpb2Pdl.jpg"/>
        <i>Stranger Things</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/53P8oHo9cfOsgb1cLxBi4pFY0ja.jpg"/>
        <i>The Good Doctor</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/3QXYOlcWzasduW3eBd7rg2qBcZg.jpg"/>
        <i>La Gran Muralla</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Series Completas</span></h4><a href="Mostras Mas/Series.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=en_el_barro">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vQANo4LO7Hi57XxQqhRWeAZkD5h.jpg"/>
        <i>En el barro</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=baki_dou_el_samurai_invencible">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/vIbiGAJR69775GHFlYlPFG4GSpb.jpg"/>
        <i>Baki-Dou: El samurái invencible</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=it_bienvenido_a_derry">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg"/>
        <i>IT: Bienvenidos a Derry</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    
    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=from">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cjXLrg4R7FRPFafvuQ3SSznQOd9.jpg"/>
        <i>FROM</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=baki_2018">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg"/>
        <i>Baki</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>2026</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=super_mario_bros_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" data-src="https://image.tmdb.org/t/p/w300/4Js0gYWxuvTN6b8iAaSF1cSQzBs.jpg"/>
        <i>Super Mario Bros 2: Galaxy</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_conexion_sueca">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/snlnvSB232OZwPCuO8zkWYJ6P7j.jpg"/>
        <i>El vínculo sueca</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=love_me_love_me">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg"/>
        <i>Love me, Love me</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_empleada">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg"/>
        <i>La empleada</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Series Incompletas</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>
    
    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=avenida_brasil">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/jgd86jJQGAl1GYThvd8oHLIy5AG.jpg"/>
        <i>Avenida Brasil</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=genesis">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/8hUZa9LzC4vyQiwX8KadKLIBXWg.jpg"/>
        <i>Genesis</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=una_buena_familia_americana">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/aIAdaQ0R9G75h3iCckaoxQrHRH.jpg"/>
        <i>Una buena familia americana</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=chespitiro_sin_querer_queriendo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bLyhzXAWvOn0L17NbCYP2aZ4sPt.jpg"/>
        <i>Chespirito: Sin querer queriendo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href= "View Peliculas/Reproductor Universal Series.php?id=blue_lock_2022">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/1DFhWgHKzzlzAvrmK8ZzLx4XcTY.jpg"/>
        <i>Blue lock</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=agatha">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/nbkbguUUNWQZygVJKjODyELBQk9.jpg"/>
        <i>Agatha ¿Quien si no?</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    
    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=el_juego_del_calamar">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg"/>
        <i>El juego del calamar</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal Series.php?id=the_walking_dead">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/9iYinsg30olSCuDoH8VxtRN5gZx.jpg"/>
        <i>The Walking Dead</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
  

    <!-- <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/"/>
        <i></i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>-->

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Agregado HOY</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
  </div>

  <div class="scrollmenu">
    <br>

    <!--
    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/"/>
        <i></i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/"/>
        <i></i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=super_mario_bros">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/k36QyeVsy851npTUQL08jO8hqip.jpg"/>
        <i>Super Mario Bros: La pelicula</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/"/>
        <i>Thor 4: Amor y trueno</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/"/>
        <i></i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    
    <a class="card-link" data-href="">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/1D2R2wIgbTyXTPzmyJIKSbVN9wG.jpg"/>
        <i>El telefono negro</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    
    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=un_gran_dinosaurio">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kRN9FCa5dQ0niViuCwHSs5fJWqt.jpg"/>
        <i>Un gran dinosaurio</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mala_influencia">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/oogmlZekRCHP0JDhHKDZIyDIfpP.jpg"/>
        <i>Mala influencia</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=metegol">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/lypC1Hi5H6jNCidQU5NG5bE7jrT.jpg"/>
        <i>Metegol</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    -->

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=super_mario_bros_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/4Js0gYWxuvTN6b8iAaSF1cSQzBs.jpg"/>
        <i>Super Mario Bros 2: Galaxy</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/"/>
        <i></i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=thunderbolts">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/aPVAwfxJc77qGrS2rzhNkJ4VnUB.jpg"/>
        <i>Thunderbolts*</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=todo_bien">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/arVt18It7zOpOa2WZTzMiBxmyrY.jpg"/>
        <i>¿Todo bien?</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=ricky_el_impostor">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/oJQdLfrpl4CQsHAKIxd3DJqYTVq.jpg"/>
        <i>Ricky Stanicky: El Impostor</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=romper_el_circulo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/e0S9UXyuHE1JAoHZmyqRJISpyoS.jpg"/>
        <i>Romper el circulo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=salve_maria">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/c1vxdtbIyKE31mX9znwIsrHJ30S.jpg"/>
        <i>Salve María</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mufasa_el_rey_leon">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/lk4NNdeQrb6zbRSogDSdE6qmjk8.jpg"/>
        <i>Mufasa: El rey león</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=no_me_la_toquen">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/yEsYJyBsnDdMUbsehxIofMa9Oh7.jpg"/>
        <i>No me las toquen</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=nahir">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"/>
        <i>Nahir</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=pablo_el_apostol_de_cristo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/xgLSFfBfQVHmy8CrU3nGxb7ZLzm.jpg"/>
        <i>Pablo, el apóstol de Cristo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=shrek">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5G1RjHMSt7nYONqCqSwFlP87Ckk.jpg"/>
        <i>Shrek</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=peter_pan_la_gran_aventura">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/xtJoP8pppOqT4rECg3E8VkvFkCj.jpg"/>
        <i>Peter Pan: La gran aventura</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=peter_pan_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kkFeLiMeih9jgXatztoloOyGSbc.jpg"/>
        <i>Peter Pan 2: En Regreso al País de Nunca Jamás</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=peter_pan">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/tDvGRWSdqT31ADijJf9OhbTbQ77.jpg"/>
        <i>Peter Pan</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=plankton">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/fCvwQJVcbjNub2PiKzZmQXR7i1I.jpg"/>
        <i>Plankton: La pelicula</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=pocahontas_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/ttjEx1Wo3QOxsgKDhDCB2GzHdWk.jpg"/>
        <i>Pocahontas 2: Viaje a un nuevo mundo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=pocahontas">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/ilPqjOxheKo8TVA80oMnQWKrJf4.jpg"/>
        <i>Pocahontas</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=presencia">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kc7YIx6KNiXm1dpqlhqdX3eTL7a.jpg"/>
        <i>Presencia</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=rehen">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/xduStDf3yiIL6WDIuhIdLX5rRGv.jpg"/>
        <i>¡Rehén!</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=robot_salvaje">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/dE8Cwtnb31637ygPHTVDxFkg8K4.jpg"/>
        <i>Robot salvaje</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=minions_el_origen_de_gru">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/h4cuPo1iZAxdNNA6OUS2OoDYZjF.jpg"/>
        <i>Minions: El origen de Gru</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mientras_duermes">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/aDi56oSNirZStVwgl8R12nkQrIk.jpg"/>
        <i>Mientras duermes</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mickey_donald_y_goofy_los_tres_mosqueteros">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/gknRvWOe1vypDJfFA4jnprCoK0T.jpg"/>
        <i>Mickey, Donald y Goofy: Los tres mosqueteros</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mi_lista_de_deseos">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/a3IFeDHSCIkMLmoBzaOcD60BOoR.jpg"/>
        <i>Mi lista de deseos</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=megan_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/6tPr2pXIpqIldCSTKUt6GCSyvnf.jpg"/>
        <i>M3GAN 2.0</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=megamente_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/jdXLCBv0oFjWbTtQTuoJFXVPsbd.jpg"/>
        <i>Megamente 2: Contra el sindicato del mal</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=martyrs">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5IG3StXtcMDP1hrMFACeEpNVPbt.jpg"/>
        <i>Martyrs</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=maria_magdalena">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/i2IqYxpXbVa0LcrIxCK9c0h5bYK.jpg"/>
        <i>María Magdalena</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=maligno">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/gijtUdVH3M6KbWnuSFmiI9MvxJ6.jpg"/>
        <i>Maligno</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=madagascar_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/l7d5JCkwvGrqiQcppobohXYnjxt.jpg"/>
        <i>Madagascar 3: De marcha por Europa</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=madagascar_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/zYbvSjajQrb2jU9rUo5Mt06stPd.jpg"/>
        <i>Madagascar 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_prisioneros_del_cielo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/gNq4Uo2KDPDTvAuixQALpsSFvPu.jpg"/>
        <i>Los siete pecados capitales: Prisioneros del cielo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_la_maldicion_de_la_luz">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/w6U2pGQokqWh2wJLRaXi0bVd3zF.jpg"/>
        <i>Los siete pecados capitales: La maldición de la luz</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_siete_pecados_capitales_el_rencor_1">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/VWKjOfMDisBDPJy1Dj5wxYLYTp.jpg"/>
        <i>Los siete pecados capitales: El rencor de Edimburgo - Parte 1</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_increibles_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bJjc0217DuipdwJ0wyi3I4j6soR.jpg"/>
        <i>Los Increíbles 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_increibles">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/1Clex17991DCM7uRkAClq52UULM.jpg"/>
        <i>Los Increíbles</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=juguete_diabolico_imaginario">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/jPhol6mXdnXYimRAgf3vlN9ZUZF.jpg"/>
        <i>Imaginario: Juguete diabólico</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hijo_de_dios">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/pnORCAOUW0JKR84ueMap8GiBAoA.jpg"/>
        <i>Hijo de Dios</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hercules">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/hdOS8bvta2DmDF8NHcgKWQDx0OX.jpg"/>
        <i>Hércules</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=harta">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/4d2PJ6QLAVd9w66E918JSWjkgs7.jpg"/>
        <i>Harta</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hablame">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/hQpcO9OIGXEZtm7KfUEMtZxXukI.jpg"/>
        <i>Háblame</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hotel_transylvania_4">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/xNF8AxJc966FWk4SYqXxGHaZLHZ.jpg"/>
        <i>Hotel Transilvania 4: Transformanía</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hotel_transylvania_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/p5eBnMRoFWjSua4DYdiKjmHP3H5.jpg"/>
        <i>Hotel Transilvania 3: Unas vacaciones monstruosas</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hotel_transylvania_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/3nFnrivNgipSKZ8LZJJbRSlAcTR.jpg"/>
        <i>Hotel Transilvania 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hotel_transylvania">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/zvWlwBGQWuJ0wog65q1uS37BApC.jpg"/>
        <i>Hotel Transilvania</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
    
    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=pantera_negra">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/4KQkyaSfh0uHRDMvY5XADzfmx5k.jpg"/>
        <i>Pantera negra</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=leroy_y_stitch">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/1RjvpZMAFZlnbLvrRYWEb2tzEyC.jpg"/>
        <i>Leroy y Stitch</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=liga_de_la_justicia_crisis_en_tierras_infinitas_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/aOT8n3YOOkInZ5VHJN4FffHrm43.jpg"/>
        <i>Liga de la Justicia: Crisis en Tierras Infinitas - Parte 2 </i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lo_que_le_falta_a_esta_estrella">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/6AmW8DglQ5VnOfW1lSMSOyfcwmW.jpg"/>
        <i>Lo que le falta a esta estrella</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_croods_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5uMWKEmegf5aTJnp1u98JF4QerP.jpg"/>
        <i>Los Croods 2: Una nueva era</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_croods">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/3X3qtBTgKt5mCB30RJwbIjgjzdw.jpg"/>
        <i>Los Croods</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lilo_y_stitch_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/hs3POL17wfJkrQpwM3e76YCQTNA.jpg"/>
        <i>Lilo & Stitch 2: El efecto del defecto</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lilo_y_stitch">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/dTYyAszU6NWbmWGvhqLZpZTdS5T.jpg"/>
        <i>Lilo y Stitch</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lilo_y_stitch_2025">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/yrZqrGVbmoYZJdncnx60JUhzsGm.jpg"/>
        <i>Lilo y Stitch CAM</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_rosa_de_versalles">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/F4OILPPbBfCYkWoW5be1UZnmJq.jpg"/>
        <i>La rosa de Versalles</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_primera_profecia">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kJkrr39cjRcfz3jR6XcGa8wSkyl.jpg"/>
        <i>La primera profecía</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_resurrección_de_cristo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/lkfXzT5T5cQO9UpknSAEdq8Lvvd.jpg"/>
        <i>La resurrección de cristo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_sirenita_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/oP09KA2lP5SluKVf8AmRsf38X7q.jpg"/>
        <i>La sirenita 3: Los comienzos de Ariel</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_sirenita_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/fresAluIWfBRwdQOaVcM4i5uGsP.jpg"/>
        <i>La sirenita 2: Regreso al mar</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_sirenita">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/muTcgTmuyvXQldGNnCzen9FgDfW.jpg"/>
        <i>La sirenita</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_sustancia">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/cQD1qEnPOKUPHAui0okOLZSgitu.jpg"/>
        <i>La sustancia</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_vieja_guardia_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/6eGyuK8bHMAB34AIIQOL3wZw8sn.jpg"/>
        <i>La vieja guardia 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_viuda_negra">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/uuabL0qp3zygLDEjImbPiWR9j2e.jpg"/>
        <i>La viuda negra</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_cenicienta_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/hnu7CGMc1zQejwjUIEGcSikdhmV.jpg"/>
        <i>La Cenicienta 3: Qué pasaría si…</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_cenicienta_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/2EoH5WWtDYuQLYVLHeJxfvbSRyK.jpg"/>
        <i>La Cenicienta 2: ¡La magia no termina a media noche!</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_cenicienta">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/doN9cNyfpcX1DPBNmjJW8eBgcAf.jpg"/>
        <i>La cenicienta</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_calle_del_terror">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kYeTcmPmuMvBgmwOdOtR5fUwRuH.jpg"/>
        <i>La calle del terror: La reina del baile</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_acompañante">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/nyloao2GWttUvS7KVcEM2eSDwUn.jpg"/>
        <i>La acompañante</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=k_o">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/qcM2sUiAeP4zXwx4ADSvgc9S58k.jpg"/>
        <i>K.O</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_evaluacion">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bf2S5XoGlthesPmfZLxLp9qJPNM.jpg"/>
        <i>La evaluación</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_fuente_de_la_juventud">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/nJ9qnZLhmj6wD3NgOe6lKoXJQMx.jpg"/>
        <i>La fuente de la juventud</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_joven_y_el_mar">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/n3KE8fbiOCr6qktIpE52wWErBMi.jpg"/>
        <i>La joven y el mar</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_leyenda_de_ochi">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/uyz9qcZdIrUqVrwly3KB5oPUKZO.jpg"/>
        <i>La leyenda de Ochi</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_mitad_de_ana">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/c24RWnJzwAtWZ039o9u6K7c8jyw.jpg"/>
        <i>La mitad de ana</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=karol_g">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5aXoQYwaQ7JJVUWclHAEXJgiS2M.jpg"/>
        <i>Karol G: Mañana fue muy bonito</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=karate_kid_4">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5LB5GJzcaEBEb3IhjqnYNsqY5Zs.jpg"/>
        <i>Karate Kid 4: Legends</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=iron_man_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/2ZZhlnlkYIMHXsjaHH7ywNVy89k.jpg"/>
        <i>Iron-Man 3</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_monja">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/q2JFJ8x0IWligHyuLJbBjqNsySf.jpg"/>
        <i>La monja</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=Turno_nocturno">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/iSSx9Bys64vlOkvkyKXtp19P7Re.jpg"/>
        <i>Turno nocturno</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=un_jefe_en_pañales_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/85J1DwZowIlKFOF7jllgCD3iHhx.jpg"/>
        <i>Un jefe en pañales 2: Negocios de familia</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=twisters">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/4UWDVI6IleoKl9T6wHbHcqd5zAX.jpg"/>
        <i>Twisters</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=un_ladron_romantico">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/nif2JUyqNQBBmMYrDfmpTgwleOJ.jpg"/>
        <i>Un ladron romantico</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=un_lugar_en_silencio">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/hE51vC3iZJCqFecLzIO1Q4eYXqK.jpg"/>
        <i>Un lugar en silencio</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=uncharted">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/rJHC1RUORuUhtfNb4Npclx0xnOf.jpg"/>
        <i>Uncharted: Fuera del mapa</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=trolls_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/9GdgycCYq3vnxLHw5Ldah8JEjH4.jpg"/>
        <i>Trolls 2: Gira mundial</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=trolls">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/5nDbnZ9UssqVoVRggQOb2icL9Pb.jpg"/>
        <i>Trolls</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>


    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=trollhunters_el_despertar_de_los_titanes">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/fhhjAX2iDmnZksQWsJ8DdAcDBc5.jpg"/>
        <i>Trollhunters: El despertar de los titanes</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=viaje_de_fin_de_curso">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/A8E8EqXqETV8ggPiOkHjaBU8H9N.jpg"/>
        <i>Viaje de fin de curso: Mallorca</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=warfare_tiempo_de_guerra">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/fkVpNJugieKeTu7Se8uQRqRag2M.jpg"/>
        <i>Warfare. Tiempo de guerra</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=gran_turismo">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/tETqYkrxTAbLjisBmzFof7jhxt3.jpg"/>
        <i>Gran turismo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=frida">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/yeWgiZhSUC7XKEPT1EzXP6E9xta.jpg"/>
        <i>Frida</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=extraterritorial">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"/>
        <i>Extraterritorial</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=estragos">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"/>
        <i>Estragos</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Mundo Marvel</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=venom2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/kviQ0gYXjBug7JEYteV8IbZzE3l.jpg"/>
        <i>Venom 2: Carnage liberado</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=venom">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg"/>
        <i>Venom</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=capitan_america1">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/82ucHZ4ioVGiweT1XMl1mUZaodq.jpg"/>
        <i>Capitán América: El...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=venom_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bHB8Fv28cOk5sNxRwWaLoT6Pnrv.jpg"/>
        <i>Venom 3: El último...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=spiderman_man_cruzando_el_multi_verso_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/37WcNMgNOMxdhT87MFl7tq7FM1.jpg"/>
        <i>Spider-Man: Cruzando...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_vengadores_infinity_war">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/z58HrY2Hd9PlSpBTsZuoavfDavd.jpg"/>
        <i>Los Vengadores: Infi...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=thor">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/prSfAi1xGrhLQNxVSUFh61xQ4Qy.jpg"/>
        <i>Thor</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=iron_man_1">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/bFj7XRg5avQDvuvWaag3IttjEAw.jpg"/>
        <i>Iron-Man</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>


  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Disney</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=zootopia_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/3Wg1LBCiTEXTxRrkNKOqJyyIFyF.jpg"/>
        <i>Zootopia 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=blancanieves">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"/>
        <i>Blancanieves</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=intensamente_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/4HEJdpcmTGm3BWWic31G4aCnuC6.jpg"/>
        <i>Intensamente 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_de_osos_2">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/iiRaRi7SFCawo6lieWi3Ntcy936.jpg"/>
        <i>Tierra de osos 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_de_osos">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/xoEY7339ewJ4jvDZZqM3FKVJb8r.jpg"/>
        <i>Tierra de osos</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=cars_3">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/ucGU1HyLfxoQwuq22VWwq55m0cH.jpg"/>
        <i>Cars 3</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_croods">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/27zvjVOtOi5ped1HSlJKNsKXkFH.jpg"/>
        <i>Los Croods</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tarzan">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/u9WgwjFpBWc3eQxddUFSicH2K6p.jpg"/>
        <i>Tarzan</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Anime</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=naruto_the_last">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/p7cf5jz1BQ8OdwUMKN8q8uWpi3J.jpg"/>
        <i>Naruto The Last: La...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Series/Baki (2018).html">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/j4bL0G8h8k49MuXKYfZqhXqk2rI.jpg"/>
        <i>Baki</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

   <div class="poster">
    <h4 class="alignleft"><span>Animacion</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=kung_fu_panda_4">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"/>
        <i>Kung Fu Panda 4</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=sing_cantar_2">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aWeKITRFbbwY8txG5uCj4rMCfSP.jpg"/>
        <i>Sing 2: Cantar</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=sing_cantar">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/sMCdqRia4H5WNZe9jgf37ZnUDlw.jpg"/>
        <i>Sing: Cantar</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hotel_transylvania">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/eJGvzGrsfe2sqTUPv5IwLWXjVuR.jpg"/>
        <i>Hotel Transylvania</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=intensamente_2">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg"/>
        <i>Intensamente</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=Turbo">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ysNUm2zWPkJQKa3Op0N4EmqrZ0h.jpg"/>
        <i>Turbo</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>

  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Terror</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=encerrado_2025">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/wlo2rGpjjHh3X8XImBdeUayKJ6g.jpg"/>
        <i>Encerrado</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=five_night_at_freddys">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rgUhzpzK9uozbXdGMscN4DmqmAv.jpg"/>
        <i>Five nights at Freddy's 2</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_conjuro_4">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg"/>
        <i>El Conjuro 4: El ulti...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=juguete_diabolico_imaginario">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/jPhol6mXdnXYimRAgf3vlN9ZUZF.jpg"/>
        <i>Imaginario: Juguete...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=hablame">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hQpcO9OIGXEZtm7KfUEMtZxXukI.jpg"/>
        <i>Háblame</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=until_dawn_noche_de_terror">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="placeholder.jpg" data-src="https://image.tmdb.org/t/p/w300/vAYTXSUnQjmTFcm97BhROQav1wF.jpg"/>
        <i>Until Dawn: Noche...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=winnie_the_pooh">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cUXqVDrHaOGEJD1clvVd7ucAHdt.jpg"/>
        <i>Winnie the Pooh: San...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=guerra_mundial_z">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/9Sd2zBbi8hlcc6p6hGV3Qfj39jl.jpg"/>
        <i>Guerra mundial z</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=martyrs">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5IG3StXtcMDP1hrMFACeEpNVPbt.jpg"/>
        <i>Martyrs</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>
  <br><br>

  <div class="poster">
    <h4 class="alignleft"><span>Trailers</span></h4><a href="#"></a>
  </div>

  <div class="scrollmenu">
    <br>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://imgs.search.brave.com/wL_zD4jMSbWlQUzFzIXV4WnXFsX6aYf9kV_1d1xiM74/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9zdGF0/aWMud2lraWEubm9j/b29raWUubmV0L3dp/a2ktZG9ibGFqZS1l/c3BhbmEvaW1hZ2Vz/LzkvOWUvQmFraS1E/b3VfLV9FbF9TYW11/ciVDMyVBMWlfSW52/ZW5jaWJsZV8tX1Bv/c3Rlci5qcGcvcmV2/aXNpb24vbGF0ZXN0/L3NjYWxlLXRvLXdp/ZHRoLWRvd24vMjY4/P2NiPTIwMjYwMjA0/MjAyNTM3JnBhdGgt/cHJlZml4PWVz"/>
        <i>Baki-Dou: El samurái...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

    <a class="card-link" data-href="">
      <div class="xplus">
        <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6eB2oh1SplddsZYCdayrIdrIGLd.jpg"/>
        <i>Los vengadores: Doo...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>

  </div>
  <br>

</div>
<!-- ========== CONTENIDO DINÁMICO ========== -->
<div id="contenido-dinamico" style="display:none;"></div>

<script>

/* ================= CONTENIDO DINÁMICO ================= */
const data = {
  "peliculas": `

    <div class="poster">
      <h4 class="alignleft"><span>Peliculas <br/>Agregadas</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=five_nigth_at_freddys_2">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rgUhzpzK9uozbXdGMscN4DmqmAv.jpg"/>
          <i>Five nights at Freddy's 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fTpbUIwdsfyIldzYvzQi2K4Icws.jpg"/>
          <i>Cómo entrenar a tu...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=megan_2">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fBgyUqyu3ioTSQjJJE5RFs2EG3B.jpg"/>
          <i>M3GAN 2.0</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=karate_kid_legends">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5LB5GJzcaEBEb3IhjqnYNsqY5Zs.jpg"/>
          <i>Karate Kid: Legends</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=thunderbolts">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hBH50Mkcrc4m8x73CovLmY7vBx1.jpg"/>
          <i>Thunderbolts*</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=echo_valley">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/1E4WCgTodyS7zo8pSp1gZlPO0th.jpg"/>
          <i>Echo Valley</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=argen_1978_a">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/iyKixwGhGRas1ppAih8E7SG5QDZ.jpg"/>
          <i>1978</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=amateur">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xzM5pMCIyp8jkGtsFBGcPlRhVBc.jpg"/>
          <i>Amateur</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_ladron_de_joyas">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hzuus3qrQct2JeoAs2AGMYzKzjZ.jpg"/>
          <i>El ladron de joyas</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_maravilloso_mago_de_oz">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ruMUv9mtcUoiUWoZmLBBTDbn11J.jpg"/>
          <i>El maravilloso ma...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=estragos">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"/>
          <i>Estragos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=g20">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xihssRPgRDZ7xwIjx3xuPTnqPfU.jpg"/>
          <i>G20</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=novocaine">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6YbTJhN5GJQOlZ1IyRiCyhKSiJE.jpg"/>
          <i>Novocaine: Sin dolor</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_leyenda_de_ochi">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uyz9qcZdIrUqVrwly3KB5oPUKZO.jpg"/>
          <i>La leyenda de ochi</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=otro_pequeño_favor">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pZr2QCUbsekpiLnZ7788twcLpSn.jpg"/>
          <i>Otro pequeño favor</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=poseida">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg"/>
          <i>Poseida</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Acción</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vHtH4xdcTbaCVftGwaeGFHfOB3p.jpg"/>
          <i>Avatar 3: fuegos y Ceniza</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5LB5GJzcaEBEb3IhjqnYNsqY5Zs.jpg"/>
          <i>Karate Kid: Legends</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6YbTJhN5GJQOlZ1IyRiCyhKSiJE.jpg"/>
          <i>Novocaine: Sin dolor</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xihssRPgRDZ7xwIjx3xuPTnqPfU.jpg"/>
          <i>G20</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/gL6puhup6PXqrKqItWbGA8LF529.jpg"/>
          <i>Los pecadores</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/C4V4XW2igocPP54wqufQKSVQuq.jpg"/>
          <i>K.O</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=alarum_codigo_letal">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/d3QFYKpEY2LSSTh70C227Z2mlwB.jpg"/>
          <i>Código letal</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_fuente_de_la_eterna_juventud">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/nJ9qnZLhmj6wD3NgOe6lKoXJQMx.jpg"/>
          <i>La fuente de la juventud</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=karate_kid_$">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/qU6SAj6arAaEgnrtn4WgIL2l3LS.jpg"/>
          <i>Karate Kid 4: Legends</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=bala_perdida_3">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"/>
          <i>Bala perdida 3</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=extraterritorial">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7tWkxxiqraVx1IzYd4DHv6FIvhS.jpg"/>
          <i>Extraterritorial</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=bad_boys_4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/25PVk2NFoZoCnaqxb4nSQqwxNd7.jpg"/>
          <i>Bad Boys 4: Hasta la...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=detonantes">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/mOXgCNK2PKf7xlpsZzybMscFsqm.jpg"/>
          <i>Detonantes</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_planeta_de_los_simios_4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/8lfjj21m4vsFbuVrx8y49WNdHR0.jpg"/>
          <i>El planeta de los...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kdefKfrKIqwfwUWuPmTY4fMEr1Q.jpg"/>
          <i>El duro</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=secenta_minutos">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cND79ZWPFINDtkA8uwmQo1gnPPE.jpg"/>
          <i>60 Minutos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=uncharted">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/77dlklwA1VJOLCqIhhmkmS39BLH.jpg"/>
          <i>Uncharted: Fuera del...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Artes-Marciales</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/w77jjsOaMHFurNdKqgpJIAJkE3E.jpg"/>
          <i>Su ultimmo combate</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/60 Minutos (2024).html">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cND79ZWPFINDtkA8uwmQo1gnPPE.jpg"/>
          <i>60 Minutos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kdefKfrKIqwfwUWuPmTY4fMEr1Q.jpg"/>
          <i>El duro</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cvsXj3I9Q2iyyIo95AecSd1tad7.jpg"/>
          <i>Creed 3</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Rendirse Jamas 1.html">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/nas9XShlxUZrNZCyBdf4AAXpRiq.jpg"/>
          <i>Rendirse jamas</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Invicto VI (2016).html">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7pheng7VQANWXePPBQuLgHuKTns.jpg"/>
          <i>Invicto IV</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Animación</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=como_entrenar_a_tu_dragon_3">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rBQ9RVg6Zpo5aasWWOWmjET5Hah.jpg"/>
          <i>Cómo entrenar a tu...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=trollhunters_el_despertar_de_los_titanes">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fhhjAX2iDmnZksQWsJ8DdAcDBc5.jpg"/>
          <i>Trollhunters: El des...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_rosa_de_versalles">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/F4OILPPbBfCYkWoW5be1UZnmJq.jpg"/>
          <i>La rosa de Versalles</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lo_que_le_falta_a_esta_estrella">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6AmW8DglQ5VnOfW1lSMSOyfcwmW.jpg"/>
          <i>Lo que le falta a esta...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=depredador_tierras_salvajes">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/qHDsrBZJRx6ZCO4tocFh3gnbosU.jpg"/>
          <i>predator: Asesino...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=lilo_y_stitch_2025">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"/>
          <i>Lilo y Stitch</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=sonic_3">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/j1O319PWd4OdrpqPY4uzFNh2JC.jpg"/>
          <i>Sonic 3: La Pelicula</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Mufasa El rey león (2024).html">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/lk4NNdeQrb6zbRSogDSdE6qmjk8.jpg"/>
          <i>Múfasa: El rey León</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=kung_fu_panda_4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xHeK1mttldtCEyWbPZbo9bSKUqd.jpg"/>
          <i>Kung fu panda 4</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=moana_2">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/9yfI8gGG96Dgf9bf7VT3XCRX30T.jpg"/>
          <i>Moana 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=intensamente_2">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/4HEJdpcmTGm3BWWic31G4aCnuC6.jpg"/>
          <i>Intensamente 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=turbo">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rJPEcuMyjjKd9Tg3mO1K4a9iAi9.jpg"/>
          <i>Turbo</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/gNRQdU3KEsYTIl4y9Xte3onUSsx.jpg"/>
          <i>Spirit: El corcel...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Anime Pelicula</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Demon Slayer Kimetsu no Yaiba Infinity Castle (2025).html">
      <div class="xplus">
        <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fWVSwgjpT2D78VUh6X8UBd2rorW.jpg"/>
        <i>Demon Slayer: Kimetsu...</i>
        <span class="lock-icon">🔒</span>
      </div>
    </a>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=baki_hanma_vs_kengan_ashura">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/sXybjRq6BsCkWcDBfNphSH9biqn.jpg"/>
          <i>Baki Hanma vs Ken...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=naruto_the_last">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/1C9RkmyRAwP9IXOtIWM5O4h0Qk9.jpg"/>
          <i>Naruto The Last: La pelicula</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/by5ZMxYI4RD4CzKMJhX6X74JhFl.jpg"/>
          <i>Los siete pecados: El...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=boruto_2015">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/t9F4Yzi8rZO8Rn55ceyQPAofrI9.jpg"/>
          <i>Boruto: La pelicula</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=dragon_ball_super_broly">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5vx8zZ7fDsA8KMO6fiVs3pZmNWQ.jpg"/>
          <i>Dragon Ball Super: Broly</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=dragon_ball_super_super_hero">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/amSIghYUItDQBlVm8TyFK7YaBje.jpg"/>
          <i>Dragon ball super: Hero</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_resurreccion_de_freezer">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6QUqAk7quyFIOZoFJmRp14eiorv.jpg"/>
          <i>Dragon ball z: La...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=dragon_ball_z_la_batalla_de_los_dioses">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cIyPFIeSKNTiWU9Zny0c0IVPQRY.jpg"/>
          <i>Dragon ball z: La...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=steven_universe_la_pelicula">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/iVa7A7priXkDMspXAIFVsHbL016.jpg"/>
          <i>Steven Universe</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Comedia</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>  

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=minecraft">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rZYYmjgyF5UP1AVsvhzzDOFLCwG.jpg"/>
          <i>Minecraft: La pelicula</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_rosa_de_versalles">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6YsEHhqgT6c8nJlS1TL1Zyrxwgw.jpg"/>
          <i>Nonnas</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=ricky_el_impostor">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/oJQdLfrpl4CQsHAKIxd3DJqYTVq.jpg"/>
          <i>Rick Stanicky: El im...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=diario_de_mi_vagina">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hyFKdAN5Dl93mt2JHfcfvIyf38g.jpg"/>
          <i>Diario de mi vagina</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=barbie">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg"/>
          <i>Barbie</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/j741ip55NOzabrllOXHJv2vpag7.jpg"/>
          <i>¡Hasta la madre!</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/mNkAOFyb4TV2gTSc92jx2O9evtj.jpg"/>
          <i>Doblemente embarazo 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/liLN69YgoovHVgmlHJ876PKi5Yi.jpg"/>
          <i>Ghosted</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mi_abuelo_es_un_peligro">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7r9pn1g3lY95DjiwzxpmNqlJzeO.jpg"/>
          <i>Mi abuelo es un peligro</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=onde_esta_el_fantasma">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pAVGfrADDvKMgoZnJLSCiLBCCiG.jpg"/>
          <i>¿Donde esta el fantasma?</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Crimen</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=rehen">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"/>
          <i>¡Rehén!</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_guason_2">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fCQyAQ2K1N1RM5n79ZyCLRSgZuz.jpg"/>
          <i>El Guasón 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=asesino_serial">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/gs9GQ9n95BdVE8Uv1ZKNS1bSwCf.jpg"/>
          <i>Asesino serial</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mara">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/gQDmXAef1Oc1SXci5mui2x5DJwt.jpg"/>
          <i>Mara</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5wY5mqmwIu5XOhDBhoBY9SStjR8.jpg"/>
          <i>Tiempos de huracan</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_guason">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/2cta3k9kgsgweUTY2LvMSFjuB6e.jpg"/>
          <i>El Guasón</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Deporte</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=a_ganar">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6GVYL9K2IBFrfIqwwFqMPu5DdC5.jpg"/>
          <i>¡A Ganar!</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=yo_tonya">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aVWX0t95Igd8kKC3ejmtHCy1vX6.jpg"/>
          <i>Yo, Tonya</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Drama</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_empleada">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cFnGVbQQPhhq7wJsAczJt48MsiS.jpg"/>
          <i>La empleada</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_maravilloso_mago_de_oz">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ruMUv9mtcUoiUWoZmLBBTDbn11J.jpg"/>
          <i>El maravilloso ma...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=harta">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/4d2PJ6QLAVd9w66E918JSWjkgs7.jpg"/>
          <i>Harta</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=rehen">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/oogRn4KOse6OhRUhxvfLiCpz2d5.jpg"/>
          <i>¡Rehén!</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_baja">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"/>
          <i>Tierra baja</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/La evaluación (2025).html">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rCGwGWI4a2EaNQCyTe4vDfoiMtk.jpg"/>
          <i>La evaluación</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mala_influencia">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/oogmlZekRCHP0JDhHKDZIyDIfpP.jpg"/>
          <i>Mala influencia</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=desaparecidos_en_la_noche">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uyEFqfRezkNrxh9Lg8fj8IcbkHx.jpg"/>
          <i>Desaparecidos en...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=todo_bien">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/mKdRfCpWkcH0wEEg6yO4a8ES4TX.jpg"/>
          <i>¿Todo bien?</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_joven_y_el_mar">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/n3KE8fbiOCr6qktIpE52wWErBMi.jpg"/>
          <i>La joven y el mar</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=millers_girl">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/qz7BADRc32DYQCmgooJwI8UWRRC.jpg"/>
          <i>Miller's girl</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=nahir">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/w4TcFexTfo5X7NkvNSeTrRSu9Sj.jpg"/>
          <i>Nahir</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="Reproductor Universal.php?id=gran_turismo">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cCHhhS8mYPlp4AQUSpdiZPGOiwR.jpg"/>
          <i>Gran turismo</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Disney</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=blancanieves">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7FZhpH4YasGdvY4FUGQJhCusLeg.jpg"/>
          <i>Blancanieves</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/9NXAlFEE7WDssbXSMgdacsUD58Y.jpg"/>
          <i>Pitter pan y Wendy</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=peter_pan">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tDvGRWSdqT31ADijJf9OhbTbQ77.jpg"/>
          <i>Peter pan</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=toy_story_4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pTTYykZZwYhj9qpAqiFxtUAamLI.jpg"/>
          <i>Toy Story 4</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vHbizQYlkL8MwtOdsdWaPuR5N5w.jpg"/>
          <i>La dama y el vaga...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=intensamente">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ewEX6VcVohyrQ52usZb1XovN1Bj.jpg"/>
          <i>Intensamente</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=encanto">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/ybXzCBc9x9bcM1ukmwhErutRiLO.jpg"/>
          <i>Encanto</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=belleza_negra">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/d3wE2OAmWsuuE4IOp6i8iSeRYy4.jpg"/>
          <i>Belleza negra</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fuKMIP50VbVwZWFuO49iqvMk9v0.jpg"/>
          <i>La dama y el vaga...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/90Shq6yU7vqXskmZbX2AEf57ddy.jpg"/>
          <i>Bambi</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6ZXiWy6NSgBTDP4yAd5eWTBZg9z.jpg"/>
          <i>La aristogatos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_de_osos_2">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/iiRaRi7SFCawo6lieWi3Ntcy936.jpg"/>
          <i>Tierra de osos 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_de_osos">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6kGf1Nm99GKtyOCrxmNs6thHmdW.jpg"/>
          <i>Tierra de osos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
  
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Guerra</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_conexion_sueca">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/snlnvSB232OZwPCuO8zkWYJ6P7j.jpg"/>
          <i>El vínculo sueca</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=contraataque">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kxnFdLJhi37ZVFDCL1ka0yeQVU5.jpg"/>
          <i>Contraataque</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=warfare_tiempo_de_guerra">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fkVpNJugieKeTu7Se8uQRqRag2M.jpg"/>
          <i>Tiempo de guerra</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=heroico">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tRD18JW9iKqmwkQKvzPYDQetRoI.jpg"/>
          <i>Heroico</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/zoo5k1Rsx4Bel0ng9G8yRwku2ND.jpg"/>
          <i>Napoleon</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=corazones_de_acero">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kbtH5G8L8REzy72LkLmKYoBVaGv.jpg"/>
          <i>Corazones de...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/v5ZCVgxlFmlpFnR9DWVUkOVw4hW.jpg"/>
          <i>Hasta el ultimo...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/dqu7nUtKTLdpM7DaJvD4zcSXhn1.jpg"/>
          <i>Malditos bastar...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aMbZHpLbZfRqjMnGb7LJCjBaGdU.jpg"/>
          <i>El francotirador</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Marvel</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hBH50Mkcrc4m8x73CovLmY7vBx1.jpg"/>
          <i>Thunderbolts*</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=capitan_america4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pVMSRyAiye7gZ8NtuCt1qgbspY9.jpg"/>
          <i>Capitan America: El...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=deadpool_y_wolverine">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hAn57Hu13UU2Klw5wZszNlWngQr.jpg"/>
          <i>Deadpool y Wolverine</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id="venom3">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/wWTxMbNpAILRYQXw7orNMpmJmui.jpg"/>
          <i>Venom 3: El último baile</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=venom2">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kviQ0gYXjBug7JEYteV8IbZzE3l.jpg"/>
          <i>Venom 2: Carnage liberado</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=venom">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/bURIWlkMbzT8RdpemzCmQECo2Uh.jpg"/>
          <i>Venom</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=viuda_negra">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tvl0OXmNQtLrPk7fJ8UHvLrD37R.jpg"/>
          <i>Viuda negra</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=thor_4">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pASJkJfb5tPXn3AW2aAFLMn6Afu.jpg"/>
          <i>Thor 4: Amor y trueno</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=doctor_strange2">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xu0RftYPT4crY4ZSf9SMa5UM8dr.jpg"/>
          <i>Doctor Strange: Mul...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=spider_man3">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rkLhaNa37IwzWis8rzWMAYTCdIK.jpg"/>
          <i>Spider-Man 3: Sin cami...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=los_vengadores_endgame">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/br6krBFpaYmCSglLBWRuhui7tPc.jpg"/>
          <i>Los vengadores endgame</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="Reproductor Universal.php?id=doctor_strange">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/zdZ2ZykDKGkUuGgOszqrt1fn3cx.jpg"/>
          <i>Doctor Strange</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Musica</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=banger">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/x2pegSby27ebOwW361GJb1aKcxa.jpg"/>
          <i>Banger</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=karol_g">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5aXoQYwaQ7JJVUWclHAEXJgiS2M.jpg"/>
          <i>Karol G</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=cantar_desnuda" data-adulto="adulto" onclick="handleAdultLinkClick(event)">
         <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://cinepelayo.com/wp-content/uploads/2025/01/cartel-cantar-desnuda.jpg"/>
          <i>Cantar desnuda</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>


      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=freestyle">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/8jwbiJB8Am1N9OsqaJs9vrGerlG.jpg"/>
          <i>Freestyle</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=cato">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/lTCsGvAjqBbqp7T5ziK28SeDfVT.jpg"/>
          <i>CATO</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Romance</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=love_me_love_me">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/jfwHKRHRE2X4NTexdzblaioHH51.jpg"/>
          <i>Love me, Love me</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mi_lista_de_deseos">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/r6K6spYEWf7aDGsY4EdkScZW1Hl.jpg"/>
          <i>Mi lista de deseos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=corazon_delator">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5XgEqq8KJVW0R0NhDZCdBV2Pjr0.jpg"/>
          <i>Corazón delator</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=tierra_baja">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/7c6HPcnw0oaO8H2vBwSLqTtFYx9.jpg"/>
          <i>Tierra baja</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=pideme_lo_que_quieras">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5rtaLwyKAjbceww4J1ro8aA8BNB.jpg"/>
          <i>Pídeme lo que quieras</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=almas_marcadas">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/6rFgrN5k4c1HrVoyr0zNDdH4bK5.jpg"/>
          <i>Almas marcadas</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=mala_influencia">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/oogmlZekRCHP0JDhHKDZIyDIfpP.jpg"/>
          <i>Mala influencia</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=anora">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tZCrWnyN4zEtJiFem5TFoYT8nxI.jpg"/>
          <i>Anora</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=suggar_baby">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uLbDZIDAbN6SIiBr7Z2eMZ9212S.jpg"/>
          <i>Suggar baby</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=desafiante_rivales">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/Aiqfn4XtXUPr7QNsDsAKNQ1aOKV.jpg"/>
          <i>Desafiante rivales</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=after_2019">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5kZxlS9vLExy3hZA5GfNFg8oJgZ.jpg"/>
          <i>After: Aqui empieza...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="Reproductor Universal.php?id=dias_365">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/jz8T3hrU6GuMqSuQ4Rbd4MJUeaq.jpg"/>
          <i>365 Dias</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Suspenso</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=amateur">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xzM5pMCIyp8jkGtsFBGcPlRhVBc.jpg"/>
          <i>El amateur</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=estragos">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/tbsDLmo2Ej8YFM0HKcOGfNMTlyJ.jpg"/>
          <i>Estragos</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_ladron_de_joyas">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hzuus3qrQct2JeoAs2AGMYzKzjZ.jpg"/>
          <i>El ladron de joyas</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=otro_pequeño_favor">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pZr2QCUbsekpiLnZ7788twcLpSn.jpg"/>
          <i>Otro pequeño favor</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_viuda_negra">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uuabL0qp3zygLDEjImbPiWR9j2e.jpg"/>
          <i>La viuda negra</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_ultimo_respiro">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/lhRLEa7xMw8yy4JDTQthQMFij93.jpg"/>
          <i>El último respiro</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=contraataque">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kxnFdLJhi37ZVFDCL1ka0yeQVU5.jpg"/>
          <i>Contraataque</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

    </div>

    <br><br>

   <div class="poster">
      <h4 class="alignleft"><span>Terror</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=encerrado_2025">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/wlo2rGpjjHh3X8XImBdeUayKJ6g.jpg"/>
          <i>Encerrado</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=terrifier_3">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"/>
          <i>Terrifier 3</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=five_night_at_freddys_2">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vMU4TTPcnwtbJMFKfAEkDcDXb3l.jpg"/>
          <i>Five nights at Freddy's 2</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=megan_2">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fBgyUqyu3ioTSQjJJE5RFs2EG3B.jpg"/>
          <i>M3GAN 2.0</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/gL6puhup6PXqrKqItWbGA8LF529.jpg"/>
          <i>Los pecadores</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=Poseída">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/t9MqBGo9BWainDLms66YLiDr5aS.jpg"/>
          <i>Poseida</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=la_calle_del_terror">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kYeTcmPmuMvBgmwOdOtR5fUwRuH.jpg"/>
          <i>La calle del terror: La...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
    </div>
    
    <br>
  `,
  

  "series": `
    <div class="poster">
      <h4 class="alignleft"><span>Disponible</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy"  src="https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg"/>
          <i>El juego Del calamar</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Animes</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/jiYAZkeh6M7Slsil6nPtMKNlGlu.jpg"/>
          <i>One punch man</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uJw6nLCzQ8SftuCUJQNXTrvjlm4.jpg"/>
          <i>Dragon Ball Daima</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
        <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/8PT42NbjTZzYzCnPzg4NZzSW97n.jpg"/>
          <i>Dragon Ball Z </i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/3V7kzJX7hvF0H9CDJsgcWKXTSsR.jpg"/>
          <i>Naruto Shippuden</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Acción</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="Rosario Tijeras.jpg"/>
          <i>Rosario Tijeras</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Biblico</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/8hUZa9LzC4vyQiwX8KadKLIBXWg.jpg"/>
          <i>Genesis</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/spMIIipBp3sz24zIG1oXgGFfcNZ.jpg"/>
          <i>Moisés y los Diez Man...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Drama</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kKi1YdzQNM87Mcz7WxxclHbevwr.jpg"/>
          <i>En el barro</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/rKPONjvDSn63blDzYrPOWvjWLoy.jpg"/>
          <i>Chespirito: Sin querer...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg"/>
          <i>El juego Del calamar</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aIAdaQ0R9G75h3iCckaoxQrHRH.jpg"/>
          <i>Una buena familia...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/cZdsqlIqhRbYNo8ttxb2ThC09Wa.jpg"/>
          <i>Adolescencia</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Marvel</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/nbkbguUUNWQZygVJKjODyELBQk9.jpg"/>
          <i>Agatha, ¿quién si no?</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/hEb0uSHvhSwsMMRUGUttxqtHKnZ.jpg"/>
          <i>Bruja Escarlata y Vi...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Terror</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
       <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/vC6LSYC8uhZPkPM01L6HKrr1lMD.jpg"/>
          <i>It: Bienvenidos a Derry</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/yft2a3vnCblf6wmF6gTqgdKZUMi.jpg"/>
          <i>The Walking Dead: The...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
      
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/uYFK6OpX4HseiMPKYPznVXPwZX9.jpg"/>
          <i>The Walking Dead</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>
    </div>

    <br>

  `,

  "trailers": `
    <div class="poster">
      <h4 class="alignleft"><span>2025</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg"/>
          <i>El Conjuro 4: El ulti...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fTpbUIwdsfyIldzYvzQi2K4Icws.jpg"/>
          <i>Trailer Cómo entrenar...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fBgyUqyu3ioTSQjJJE5RFs2EG3B.jpg"/>
          <i>Trailer M3GAN 2.0</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5LB5GJzcaEBEb3IhjqnYNsqY5Zs.jpg"/>
          <i>Trailer Karate Kid...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg"/>
          <i>El juego Del calamar</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"/>
          <i>Trailer: Lilo y Stitch</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"/>
          <i>Trailer: Destino Final 6</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"/>
          <i>Trailer: La bala perdida</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aPVAwfxJc77qGrS2rzhNkJ4VnUB.jpg"/>
          <i>Trailer: Thunderbolts*</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Acción</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fTpbUIwdsfyIldzYvzQi2K4Icws.jpg"/>
          <i>Trailer Cómo entrenar...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fBgyUqyu3ioTSQjJJE5RFs2EG3B.jpg"/>
          <i>Trailer M3GAN 2.0</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/5LB5GJzcaEBEb3IhjqnYNsqY5Zs.jpg"/>
          <i>Trailer Karate Kid...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/bSGXolaGLJZxueTXxEE2WsgEoNh.jpg"/>
          <i>Trailer: La bala perdida</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Animación</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/kceHm889ylKW7uTs6mEOYXNeTQ9.jpg"/>
          <i>Trailer: Lilo y Stitch</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Series</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/xNvlt4jn2KbuKJoZ9UiVpm7lYjr.jpg"/>
          <i>El juego Del calamar</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Marvel</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/aPVAwfxJc77qGrS2rzhNkJ4VnUB.jpg"/>
          <i>Trailer: Thunderbolts*</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 
    </div>

    <br><br>

    <div class="poster">
      <h4 class="alignleft"><span>Terror</span></h4><a href="Mostras Mas/Agregado hoy.php"><h4 class="alignright"><span>Ver Todo</span></h4></a>
    </div>

    <div class="scrollmenu">
      <br>
    
      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=terrifier_3">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/63xYQj1BwRFielxsBDXvHIJyXVm.jpg"/>
          <i>Terrifier 3</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="View Peliculas/Reproductor Universal.php?id=el_conjuro_4">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/dyW5mX4wwDoZWgTYObx6pg9V0i9.jpg"/>
          <i>El Conjuro 4: El ulti...</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/fBgyUqyu3ioTSQjJJE5RFs2EG3B.jpg"/>
          <i>Trailer M3GAN 2.0</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a>

      <a class="card-link" data-href="">
        <div class="xplus">
          <img alt="" class="xaviec" loading="lazy" src="https://image.tmdb.org/t/p/w300/pKaSLXmpT6oSRjnnFzGECPt0BRx.jpg"/>
          <i>Trailer: Destino Final 6</i>
          <span class="lock-icon">🔒</span>
        </div>
      </a> 

    </div>

    <br>
  `,
};


/* ================= CONTROL DE CONTENIDO ================= */

const orig = document.getElementById("contenido-orig");
const dyn  = document.getElementById("contenido-dinamico");

function showIndex() {
  orig.style.display = "block";
  dyn.style.display  = "none";
  dyn.innerHTML = "";
}

function showDynamic(html) {
  orig.style.display = "none";
  dyn.style.display  = "block";
  dyn.innerHTML = html;

  // ⬇️ Ejecutar candados en el contenido cargado
  activarCandados();
}


/* ================= CLICK EN MENÚ ================= */

document.querySelectorAll(".menu-pro-item").forEach(btn => {
  btn.addEventListener("click", e => {
    e.preventDefault();

    const id = btn.id;
    if (id === "inicio") {
      showIndex();
      return;
    }

    showDynamic(data[id] || "<p>No hay contenido.</p>");
  });
});


/* ================= FUNCIÓN PARA ACTIVAR CANDADOS ================= */
function activarCandados() {
  document.querySelectorAll('.card-link').forEach(link => {
    const dataHref = link.getAttribute('data-href');

    if (!dataHref || dataHref.trim() === "" || dataHref === "locked") {

      // Mostrar candado
      const lock = link.querySelector('.lock-icon');
      if (lock) lock.style.display = 'block';

      // Desactivar click
      link.setAttribute('href', 'javascript:void(0)');
      link.style.pointerEvents = 'none';
      link.style.opacity = '0.5';

    } else {
      // Enlaces normales
      link.setAttribute('href', dataHref);
      link.style.pointerEvents = 'auto';
      link.style.opacity = '1';
    }
  });
}

// Ejecutar candados del contenido original
activarCandados();

</script>

<style>
/* Fondo semi-transparente cuando está bloqueado */
.card-link[style*="opacity: 0.5"] img {
  filter: brightness(40%);
}

/* Candado */
.lock-icon {
  font-size: 18px;
  margin-left: 6px;
  display: none;
}

/* Cuando data-href está vacío o es locked, mostrar candado */
.card-link[data-href=""] .lock-icon,
.card-link[data-href="locked"] .lock-icon {
  display: inline;
}
</style>


<!--VERIFICACION DE EDAD-->

<!-- Modal flotante de edad + clave -->
<div id="ageModal" class="age-modal hidden">
  <div class="age-modal-content">
    <span class="close-button" onclick="closeModal()">×</span>

    <h2>Verificación de Edad</h2>

    <label for="birthyear">Año de nacimiento:</label>
    <input type="number" id="birthyear" placeholder="Año" min="1900" max="2030" />

    <label for="age">Edad actual:</label>
    <input type="number" id="age" placeholder="----" min="1" max="120" />

    <label for="clave">Clave de acceso (solo adultos):</label>
    <input type="password" id="clave" placeholder="Clave secreta" />

    <button id="resetClaveBtn" style="background:#444;margin-top:10px;">
      Olvidé mi clave
    </button>


    <button id="confirmAgeBtn">Validar acceso</button>

    <p id="result-message"></p>
  </div>
</div>

<!-- MODAL RESET CLAVE -->
<div id="resetModal" class="modal">
  <div class="modal-content">
    <h2>Restablecer clave</h2>
    <p>¿Deseás borrar tu clave y crear una nueva?</p>
    <div class="modal-buttons">
      <button id="cancelReset">Cancelar</button>
      <button id="confirmReset">Confirmar</button>
    </div>
  </div>
</div>

<!-- MODAL ALERTA -->
<div id="alertModal" class="modal">
  <div class="modal-content">
    <p id="alertTexto"></p>
    <button id="closeAlert">Aceptar</button>
  </div>
</div>


<style>

/*MODAL DE VLAVE*/

.modal {
  display:none;
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  justify-content:center;
  align-items:center;
  z-index:9999;
}

.modal-content {
  background:#121212;
  color:#fff;
  padding:25px;
  border-radius:12px;
  width:90%;
  max-width:350px;
  text-align:center;
  box-shadow:0 0 15px rgba(0,0,0,.5);
}

.modal-buttons {
  margin-top:15px;
  display:flex;
  justify-content:space-between;
}

.modal-buttons button,
#closeAlert {
  padding:8px 14px;
  border:none;
  border-radius:6px;
  cursor:pointer;
  background:#333;
  color:#fff;
}

#confirmReset {
  background:#d63030;
}

.modal-buttons button:hover,
#closeAlert:hover {
  opacity:.85;
}


/*FIN*/

  .age-modal {
    position: fixed;
    z-index: 9999;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(6px);
    display: flex;
    justify-content: center;
    align-items: center;
    animation: fadeInBg 0.4s ease;
  }

  .age-modal-content {
    width: 320px;
    background: #141414;
    padding: 25px;
    border-radius: 14px;
    text-align: center;
    box-shadow: 0 0 25px rgba(255,0,0,0.25);
    color: white;
    position: relative;
    animation: popup 0.35s ease;
  }

  @keyframes popup {
    from { transform: scale(0.85); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
  @keyframes fadeInBg {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .age-modal-content h2 {
    margin-bottom: 15px;
    font-size: 22px;
    color: #ff3c3c;
  }

  .age-modal-content label {
    text-align: left;
    display: block;
    margin: 10px 0 5px;
    font-size: 14px;
    opacity: 0.9;
  }

  .age-modal-content input {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    background: #1f1f1f;
    border: 1px solid #333;
    color: white;
    outline: none;
    font-size: 15px;
    transition: 0.2s;
  }

  .age-modal-content input:focus {
    border-color: #ff3c3c;
    box-shadow: 0 0 5px rgba(255,60,60,0.6);
  }

  .age-modal-content button {
    width: 100%;
    margin-top: 15px;
    padding: 12px;
    background: #ff3c3c;
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 16px;
    transition: 0.2s ease;
  }

  .age-modal-content button:hover {
    background: #ff5555;
    transform: scale(1.03);
  }

  .close-button {
    position: absolute;
    right: 14px;
    top: 10px;
    font-size: 22px;
    cursor: pointer;
    color: #bbb;
  }

  .close-button:hover {
    color: white;
  }

  #result-message {
    margin-top: 12px;
    font-size: 14px;
    min-height: 20px;
  }

  .hidden {
    display: none;
  }
</style>

<script>
const resetModal = document.getElementById("resetModal");
const alertModal = document.getElementById("alertModal");
const alertTexto = document.getElementById("alertTexto");

document.getElementById("resetClaveBtn").addEventListener("click", () => {
  resetModal.style.display = "flex";
});

document.getElementById("cancelReset").onclick = () => {
  resetModal.style.display = "none";
};

document.getElementById("confirmReset").onclick = () => {
  localStorage.removeItem("claveAdultos");
  claveGuardada = null;

  resetModal.style.display = "none";
  showAlert("Clave eliminada. Ahora podés crear una nueva.");
  openModal();
};

document.getElementById("closeAlert").onclick = () => {
  alertModal.style.display = "none";
};

function showAlert(msg) {
  alertTexto.textContent = msg;
  alertModal.style.display = "flex";
}


</script>


<script>
let pendingRedirect = null;

// 🔎 Revisamos si ya existe una clave guardada
let claveGuardada = localStorage.getItem("claveAdultos") || null;

function verificarAcceso(url, requiereEdad) {
  if (requiereEdad) {
    pendingRedirect = url;
    openModal();
  } else {
    location.assign(url);
  }
}

function openModal() {
  document.getElementById("ageModal").classList.remove("hidden");
  document.getElementById("result-message").innerText = "";
  document.getElementById("birthyear").value = "";
  document.getElementById("age").value = "";
  document.getElementById("clave").value = "";

  // Si ya existe clave → cambia texto del label
  if (claveGuardada) {
    document.querySelector("label[for='clave']").innerText = "Ingresa tu clave:";
    document.getElementById("clave").placeholder = "Clave guardada";
  } else {
    document.querySelector("label[for='clave']").innerText = "Crea una clave:";
    document.getElementById("clave").placeholder = "Nueva clave";
  }
}

function closeModal() {
  document.getElementById("ageModal").classList.add("hidden");
  pendingRedirect = null;
}

document.getElementById("confirmAgeBtn").addEventListener("click", function () {
  const birthYear = parseInt(document.getElementById("birthyear").value);
  const enteredAge = parseInt(document.getElementById("age").value);
  const claveIngresada = document.getElementById("clave").value;
  const currentYear = new Date().getFullYear();
  const calculatedAge = currentYear - birthYear;
  const result = document.getElementById("result-message");

  result.style.color = "red";

  if (!birthYear || !enteredAge || !claveIngresada) {
    result.textContent = "Completa todos los campos.";
    return;
  }

  if (enteredAge !== calculatedAge) {
    result.textContent = "La edad no coincide con el año de nacimiento.";
    return;
  }

  if (enteredAge < 18) {
    result.textContent = "Debes ser mayor de edad.";
    return;
  }

  // 🔐 Si NO hay clave guardada ⇒ Crear clave nueva
  if (!claveGuardada) {
    localStorage.setItem("claveAdultos", claveIngresada);
    claveGuardada = claveIngresada;

    result.style.color = "lime";
    result.textContent = "Clave creada y acceso autorizado. Entrando...";

    setTimeout(() => {
      if (pendingRedirect) window.location.href = pendingRedirect;
    }, 1200);
    return;
  }

  // 🔐 Si SÍ existe clave ⇒ Comprobar
  if (claveIngresada !== claveGuardada) {
    result.textContent = "Clave incorrecta.";
    return;
  }

  // ✔ Clave correcta y edad OK → entra
  result.style.color = "lime";
  result.textContent = "Acceso autorizado. Entrando...";

  setTimeout(() => {
    if (pendingRedirect) window.location.href = pendingRedirect;
  }, 1200);
});
</script>

<script>
  function normalizar(str) {
    if (!str) return "";
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
  }

  let favoritos = JSON.parse(localStorage.getItem('favoritos_detalles') || '[]');
  let multiMode = false;
  let seleccionados = [];
  let indexAEliminar = null;

  function asegurarTimestamp() {
  let base = Date.now() - favoritos.length * 1000;

  favoritos.forEach((item, i) => {
    if (!item.timestamp) {
      item.timestamp = base + i * 1000; // escalonado real
    }
  });

  localStorage.setItem("favoritos_detalles", JSON.stringify(favoritos));
}

  function renderFavoritos() {
    if (openOrderMenu.innerText.includes("recientes")) {
  favoritos.sort((a, b) => b.timestamp - a.timestamp);
} else {
  favoritos.sort((a, b) => a.timestamp - b.timestamp);
}
    const container = document.getElementById("favoritos-container");
    /*container.innerHTML = "";*/
    container.replaceChildren(fragment);

    favoritos.forEach((item, index) => {
      let extraInfo = "";

      // TIEMPO REAL
const ahora = Date.now();
const edad = ahora - item.timestamp;

// Recién agregado = menos de 24 horas
if (edad < 24 * 60 * 60 * 1000) {
  extraInfo = "Reciente";
}

// Más antiguo = más de 15 días
if (edad > 15 * 24 * 60 * 60 * 1000) {
  extraInfo = "Antiguo";
}


      const div = document.createElement("div");
      div.className = "item";
      const tipo = item.tipo ? item.tipo.toLowerCase() : "pelicula";

div.innerHTML = `
  <div class="selector"></div>

  <img src="${item.imagen}">
  
  <div class="category-badge category-${tipo}">
    ${tipo.toUpperCase()}
  </div>

  <div class="item-title">${item.titulo}</div>
  <div class="item-info">${extraInfo}</div>
  <button class="delete-btn" onclick="event.stopPropagation(); eliminarFavorito(${index})">×</button>

`;


      div.onclick = (e) => {
        if (multiMode) {
          div.classList.toggle("selected");
          if (div.classList.contains("selected")) seleccionados.push(index);
          else seleccionados = seleccionados.filter(i => i !== index);
        } else {
          openPreview(item);
        }
      };

      div.oncontextmenu = (e) => {
        e.preventDefault();
        activarSeleccionMultiple();
      };

      container.appendChild(div);
    });
  }

  buscar.oninput = e => {
  const term = normalizar(e.target.value);
  let visibles = 0;

  document.querySelectorAll(".item").forEach(card => {
    const title = normalizar(card.querySelector(".item-title").innerText);

    if (title.includes(term)) {
      card.style.display = "";
      visibles++;
    } else {
      card.style.display = "none";
    }
  });

  document.getElementById("noResultsMsg").style.display =
    visibles === 0 ? "block" : "none";
};


  openOrderMenu.onclick = () => orderModal.style.display = "flex";
  document.querySelector(".close-order").onclick = () => orderModal.style.display = "none";

  document.querySelectorAll(".order-option").forEach(btn => {
  btn.onclick = () => {

    // Quitar la selección actual
    document.querySelectorAll(".order-option").forEach(o =>
      o.classList.remove("active")
    );

    // Activar la opción pulsada
    btn.classList.add("active");

    const value = btn.dataset.value;

    if (value === "recientes") {
      favoritos.sort((a, b) => b.timestamp - a.timestamp);
      openOrderMenu.innerText = "Ordenar: Más recientes";
    } else {
      favoritos.sort((a, b) => a.timestamp - b.timestamp);
      openOrderMenu.innerText = "Ordenar: Más antiguos";
    }

    renderFavoritos();
    orderModal.style.display = "none";
  };
});


  function eliminarFavorito(i) {
    indexAEliminar = i;
    deleteImg.src = favoritos[i].imagen;
    deleteTitle.innerText = favoritos[i].titulo;
    deleteModal.style.display = "flex";
  }

  cancelDelete.onclick = () => {
    indexAEliminar = null;
    deleteModal.style.display = "none";
  };

  confirmDelete.onclick = () => {
    if (indexAEliminar !== null) {
      favoritos.splice(indexAEliminar, 1);
      localStorage.setItem("favoritos_detalles", JSON.stringify(favoritos));
      renderFavoritos();
    }
    deleteModal.style.display = "none";
  };

  function activarSeleccionMultiple() {
  multiMode = true;

  // Clase global visual
  document.body.classList.add("multi-select-active");

  document.querySelectorAll(".item").forEach(item => {
    item.classList.remove("selected");
  });

  document.querySelectorAll(".selector").forEach(s => s.style.display = "block");

  document.getElementById("multiDeleteBtn").style.display = "inline-block";
  document.getElementById("cancelSelectBtn").style.display = "inline-block";

  seleccionados = [];
}


  function openPreview(item) {
  if (!item.archivo) {
    alert("Archivo no encontrado");
    return;
  }

  // ➤ Si es contenido adulto → activar verificación
  if (item.adulto === true) {
    verificarAcceso(item.archivo, true);
    return;
  }

  // ➤ Si NO es adulto → entra directo
  window.location.href = item.archivo;
}

  function closePreview() {}

  asegurarTimestamp();
favoritos.sort((a, b) => b.timestamp - a.timestamp); // ✅ ordenar al cargar
renderFavoritos();
// Marcar opción por defecto
document.querySelector('.order-option[data-value="recientes"]')
  .classList.add("active");

</script>

<script>
function handleAdultLinkClick(e) {
    e.preventDefault();

    // si tu tarjeta usa data-href
    const url = e.currentTarget.getAttribute("data-href") 
                || e.currentTarget.getAttribute("href");

    // usar tu sistema de verificación real
    verificarAcceso(url, true);
}
</script>


<!--fin-->
<br/>

<br/><br/>

<script>
document.getElementById("formFoto").addEventListener("submit", function(e){
    if(!permitirEnvio){
        e.preventDefault();
    }
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>

<div id="cropModal">

<div class="crop-header">
<button type="button" onclick="cerrarCrop()">Cancelar</button>
<h3>Ajustar foto</h3>
<button type="button" onclick="recortarImagen()">Guardar</button>
</div>

<div class="crop-container">
<img id="imageToCrop">
</div>

<div class="crop-footer">
<button type="button" onclick="zoomOut()">−</button>
<button type="button" onclick="zoomIn()">+</button>
</div>

</div>

<style>
#cropModal{
position:fixed;
inset:0;
background:#000;
z-index:99999;
display:none;
flex-direction:column;
}

/* HEADER */
.crop-header{
display:flex;
justify-content:space-between;
align-items:center;
padding:15px;
color:white;
background:rgba(0,0,0,0.6);
}

.crop-header h3{
margin:0;
font-size:16px;
}

.crop-header button{
background:none;
border:none;
color:#00d4ff;
font-size:15px;
cursor:pointer;
}

/* CONTENEDOR */
.crop-container{
flex:1;
display:flex;
align-items:center;
justify-content:center;
overflow:hidden;
}

/* FOOTER */
.crop-footer{
display:flex;
justify-content:center;
gap:20px;
padding:15px;
background:rgba(0,0,0,0.6);
}

.crop-footer button{
background:#111;
color:white;
border:none;
padding:10px 20px;
border-radius:10px;
font-size:18px;
cursor:pointer;
}

/* BOTÓN GUARDAR */
.crop-header button:last-child{
color:#00ff88;
font-weight:bold;
}
</style>

<link href="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet"/>
<script src="https://unpkg.com/cropperjs@1.6.1/dist/cropper.min.js"></script>

<script>
let permitirEnvio = false;
let cropper;
let inputActual = null;

/* =========================
ABRIR CROPPER
========================= */
function previewImage(event){

const file = event.target.files[0];
if(!file) return;

inputActual = event.target;

const reader = new FileReader();

reader.onload = function(){

document.getElementById("imageToCrop").src = reader.result;
document.getElementById("cropModal").style.display = "flex";

if(cropper){
    cropper.destroy();
}

cropper = new Cropper(document.getElementById("imageToCrop"),{
    aspectRatio:1,
    viewMode:1,
    dragMode:'move',
    autoCropArea:1,
    responsive:true,
    background:false,

    cropBoxMovable:true,
    cropBoxResizable:false,
    toggleDragModeOnDblclick:false
});

}

reader.readAsDataURL(file);
}

/* =========================
ZOOM
========================= */
function zoomIn(){ if(cropper) cropper.zoom(0.1); }
function zoomOut(){ if(cropper) cropper.zoom(-0.1); }

/* =========================
GUARDAR
========================= */
function recortarImagen(){

if(!cropper) return;

const canvas = cropper.getCroppedCanvas({
    width:400,
    height:400
});

canvas.toBlob(function(blob){

    const file = new File([blob], "perfil.png", {type:"image/png"});

    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);

    inputActual.files = dataTransfer.files;

    // ✅ permitir envío
    permitirEnvio = true;

    // ✅ enviar form
    document.getElementById("formFoto").submit();

    // ✅ cerrar DESPUÉS
    cerrarCrop();

});
}

/* =========================
CANCELAR
========================= */
function cerrarCrop(){

document.getElementById("cropModal").style.display = "none";

if(cropper){
    cropper.destroy();
    cropper = null;
}

if(inputActual){
    inputActual.value = "";
    inputActual = null;
}

}

</script>

<script>
document.getElementById("btnCambiarFoto").addEventListener("click", () => {
    document.getElementById("inputFoto").click();
});
</script>

<!-- Menú Inferior Fijo -->
<!-- MODAL AJUSTES -->
<div id="modalAjustes" class="modal-ajustes">
  <div class="ajustes-contenido">
    
    <span class="cerrar-ajustes" id="cerrarAjustes">&times;</span>

    <div class="perfil-ajustes">

      <!-- FORM PARA SUBIR FOTO -->
      <form id="formFoto" method="POST" action="inicio.php" enctype="multipart/form-data">

        <div class="foto-container">
          <img src="<?= htmlspecialchars($foto) ?>" id="fotoPerfil">
          <div class="overlay-plus" id="btnCambiarFoto">+</div>

          <input 
type="file" 
name="foto" 
id="inputFoto" 
accept="image/*"
onchange="previewImage(event)"
hidden>
        </div>

      </form>

      <h3 id="nombreUsuario"><?= htmlspecialchars($nombre) ?></h3>

      <p class="correo-usuario">
        <?= htmlspecialchars($email) ?>
      </p>

    </div>


    <!-- OPCIONES DEL USUARIO -->
    <div class="opciones-ajustes">

      <button onclick="location.href='cuentas.php'">
        <i class="fas fa-user"></i> Cuenta
      </button>

      <button onclick="location.href='perfiles.php'">
        <i class="fas fa-user-friends"></i> Perfiles
      </button>
      
      <!--
      <button onclick="location.href='View Peliculas/favoritos.php'">
        <i class="fas fa-heart"></i> Favoritos
      </button>

      <button onclick="location.href='View Peliculas/historial_usuario.php'">
        <i class="fas fa-history"></i> Historial
      </button>  
      -->

      <button onclick="location.href='Menus Precios/Compartir.php'">
        <i class="fas fa-share-alt"></i> Compartir
      </button>

      <button class="danger" onclick="darDeBaja()">
        <i class="fas fa-user-times"></i> Dar de baja cuenta
      </button>

      <button class="cerrar" onclick="cerrarSesion()">
        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
      </button>

    </div>

  </div>
</div>


<style>

.correo-usuario{
    font-size:14px;
    background:linear-gradient(90deg,#00e5ff,#ff00c8);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    font-weight:600;
}

.correo-usuario:hover{
    color:#ffffff;          /* se ilumina al pasar el mouse */
}

  .modal-ajustes{
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(10px);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
}

.ajustes-contenido{
  background: #111;
  width: 90%;
  max-width: 400px;
  border-radius: 20px;
  padding: 25px;
  animation: aparecer .3s ease;
  box-shadow: 0 0 40px rgba(0,0,0,.8);
  position: relative;
}

@keyframes aparecer{
  from{ transform: scale(.8); opacity:0 }
  to{ transform: scale(1); opacity:1 }
}

.cerrar-ajustes{
  position: absolute;
  top: 15px;
  right: 20px;
  font-size: 24px;
  cursor: pointer;
  color: #aaa;
}

.perfil-ajustes{
  text-align: center;
  margin-bottom: 20px;
}

.perfil-ajustes img{
  width: 90px;
  height: 90px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e50914;
  margin-bottom: 10px;
}

.perfil-ajustes h3{
  color: #fff;
  margin: 0;
}

.opciones-ajustes button{
  width: 100%;
  margin: 8px 0;
  padding: 12px;
  border-radius: 12px;
  border: none;
  background: #1c1c1c;
  color: #fff;
  font-size: 14px;
  cursor: pointer;
  transition: .2s;
  display: flex;
  align-items: center;
  gap: 10px;
}

.opciones-ajustes button:hover{
  background: #e50914;
}

.opciones-ajustes .danger{
  background: #3a0000;
}

.opciones-ajustes .danger:hover{
  background: #b00000;
}

.opciones-ajustes .cerrar{
  background: #222;
}

.opciones-ajustes .cerrar:hover{
  background: #444;
}
.foto-container{
  position: relative;
  width: 100px;
  height: 100px;
  margin: 0 auto 10px auto;
}

.foto-container img{
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid #e50914;
  background: #222;
}

.overlay-plus{
  position: absolute;
  bottom: 0;
  right: 0;
  width: 30px;
  height: 30px;
  background: #e50914;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 18px;
  cursor: pointer;
  border: 2px solid #111;
  transition: .2s;
}

.overlay-plus:hover{
  transform: scale(1.1);
  background: #ff2a2a;
}

.mensaje-box{
  text-align: center;
}

.mensaje-box h3{
  margin-bottom: 10px;
  color: #fff;
}

.mensaje-box p{
  color: #bbb;
  font-size: 14px;
  margin-bottom: 20px;
}

.botones-mensaje{
  display: flex;
  gap: 10px;
}

.botones-mensaje button{
  flex: 1;
  padding: 10px;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  font-weight: bold;
  transition: .2s;
}

.botones-mensaje .cancelar{
  background: #333;
  color: white;
}

.botones-mensaje .cancelar:hover{
  background: #555;
}

.botones-mensaje .confirmar{
  background: #e50914;
  color: white;
}

.botones-mensaje .confirmar:hover{
  background: #ff2a2a;
}

.input-wrapper{
    position:relative;
    width:100%;
    margin-top:10px;
}

.input-pass{
    width:100%;
    padding:12px 40px 12px 12px;
    border-radius:10px;
    border:1px solid #444;
    background:#1e1e1e;
    color:#fff;
    font-size:14px;
    outline:none;
    transition:0.3s ease;
}

.input-pass::placeholder{
    color:#888;
}

.input-pass:focus{
    border-color:#00e5ff;
    box-shadow:0 0 8px rgba(0,229,255,0.6);
}

.toggle-pass{
    position:absolute;
    right:12px;
    top:50%;
    transform:translateY(-50%);
    cursor:pointer;
    color:#aaa;
    transition:0.3s;
}

.toggle-pass:hover{
    color:#fff;
}

</style>

<script>
document.addEventListener("DOMContentLoaded", function(){

  /* =========================
     MODAL AJUSTES
  ==========================*/
  const modal = document.getElementById("modalAjustes");
  const btn = document.getElementById("btnAjustes");
  const cerrar = document.getElementById("cerrarAjustes");

  btn.addEventListener("click", function(e){
    e.preventDefault();
    modal.style.display = "flex";
  });

  cerrar.addEventListener("click", function(){
    modal.style.display = "none";
  });

  window.addEventListener("click", function(e){
    if(e.target === modal){
      modal.style.display = "none";
    }
  });


  /* =========================
   FOTO DE PERFIL
==========================*/
const fotoPerfil = document.getElementById("fotoPerfil");
const inputFoto = document.getElementById("inputFoto");
const btnCambiarFoto = document.getElementById("btnCambiarFoto");

// Solo abrir selector
btnCambiarFoto.addEventListener("click", () => {
  inputFoto.click();
});





window.darDeBaja = function(){

  tituloMensaje.innerText = "Eliminar cuenta";

  textoMensaje.innerHTML = `
      <p style="margin-top:10px;">
        ¿Estás seguro que deseas eliminar tu cuenta?<br>
        Esta acción no se puede deshacer.
      </p>
  `;

  btnConfirmar.innerText = "Eliminar";
  btnConfirmar.style.background = "#ff3b3b";

  btnConfirmar.onclick = function(){

      const form = document.createElement("form");
      form.method = "POST";
      form.action = "inicio.php";

      const action = document.createElement("input");
      action.type = "hidden";
      action.name = "delete_account";
      action.value = "1";

      form.appendChild(action);
      document.body.appendChild(form);
      form.submit();
  };

  modalMensaje.style.display = "flex";
}


  /* =========================
     MODAL MENSAJES PREMIUM
  ==========================*/
  const modalMensaje = document.getElementById("modalMensaje");
  const tituloMensaje = document.getElementById("tituloMensaje");
  const textoMensaje = document.getElementById("textoMensaje");
  const btnCancelar = document.getElementById("btnCancelar");
  const btnConfirmar = document.getElementById("btnConfirmar");

  btnCancelar.addEventListener("click", () => {
    modalMensaje.style.display = "none";
  });

  window.solicitarCambioPass = function(){

  tituloMensaje.innerText = "Cambiar contraseña";

  textoMensaje.innerHTML = `
      <div class="input-wrapper">
      <input 
        type="password" 
        id="newPass" 
        class="input-pass"
        placeholder="Nueva contraseña">
      <span class="toggle-pass" id="togglePass">
        <i class="fas fa-eye"></i>
      </span>
  </div>
  `;

  setTimeout(() => {
    const toggle = document.getElementById("togglePass");
    const input = document.getElementById("newPass");

    toggle.addEventListener("click", function(){
        if(input.type === "password"){
            input.type = "text";
            toggle.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            input.type = "password";
            toggle.innerHTML = '<i class="fas fa-eye"></i>';
        }
    });
}, 0);
  

  btnConfirmar.innerText = "Guardar";

  btnConfirmar.onclick = function(){

      const newPass = document.getElementById("newPass").value;

      if(newPass.length < 6){
          alert("La contraseña debe tener mínimo 6 caracteres");
          return;
      }

      const form = document.createElement("form");
      form.method = "POST";
      form.action = "inicio.php";

      const input = document.createElement("input");
      input.type = "hidden";
      input.name = "new_password";
      input.value = newPass;

      const action = document.createElement("input");
      action.type = "hidden";
      action.name = "change_password";
      action.value = "1";

      form.appendChild(input);
      form.appendChild(action);

      document.body.appendChild(form);
      form.submit();
  };

  modalMensaje.style.display = "flex";
}

  function mostrarExito(texto){

    tituloMensaje.innerText = "✔ Éxito";
    textoMensaje.innerText = texto;

    btnCancelar.style.display = "none";
    btnConfirmar.innerText = "Cerrar";

    btnConfirmar.onclick = function(){
      modalMensaje.style.display = "none";
      btnCancelar.style.display = "block";
      btnConfirmar.innerText = "Confirmar";
    };

    modalMensaje.style.display = "flex";
  }

});


/* =========================
   CERRAR SESIÓN
==========================*/
function cerrarSesion(){
  localStorage.clear();
  window.location.href = "index.php";
}
</script>


<!-- MODAL MENSAJES -->
<div id="modalMensaje" class="modal-ajustes">
  <div class="ajustes-contenido mensaje-box">

    <h3 id="tituloMensaje">Título</h3>
    <p id="textoMensaje">Texto</p>

    <div class="botones-mensaje">
      <button id="btnCancelar" class="cancelar">Cancelar</button>
      <button id="btnConfirmar" class="confirmar">Confirmar</button>
    </div>

  </div>
</div>



<nav class="menu-inferior">

  <a href="Menus Precios/Precio.php">
    <i class="fas fa-dollar-sign"></i>
    <span>Precio</span>
  </a>

  <a href="View Peliculas/historial_usuario.php">
    <i class="fas fa-history"></i>
    <span>Historial</span>
  </a>

  <a href="View Peliculas/favoritos.php">
    <i class="fas fa-heart"></i>
    <span>Favoritos</span>
  </a>

  <a href="#" id="btnAjustes">
    <i class="fas fa-cog"></i>
    <span>Ajustes</span>
  </a>

</nav>
<style>

/* CONTENEDOR */
.menu-inferior {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;

  height: 70px;
  display: flex;
  justify-content: space-around;
  align-items: center;

  background: rgba(10,10,10,0.8);
  backdrop-filter: blur(12px);

  border-top: 1px solid rgba(255,255,255,0.08);

  z-index: 9999;

  animation: slideUp 0.5s ease forwards;
}

/* ITEMS */
.menu-inferior a {
  flex: 1;
  text-align: center;
  color: #aaa;
  text-decoration: none;

  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;

  font-size: 11px;

  transition: all 0.25s ease;
  position: relative;
}

/* ICONOS */
.menu-inferior a i {
  font-size: 20px;
  margin-bottom: 4px;
  transition: transform 0.25s ease, color 0.25s ease;
}

/* TEXTO */
.menu-inferior a span {
  font-size: 11px;
  opacity: 0.7;
  transition: opacity 0.25s ease;
}

/* 🔥 ACTIVO (manual o con JS) */
.menu-inferior a.active {
  color: #00ffe1;
}

.menu-inferior a.active i {
  transform: translateY(-2px) scale(1.15);
}

.menu-inferior a.active span {
  opacity: 1;
}

/* 🔥 INDICADOR */
.menu-inferior a.active::before {
  content: "";
  position: absolute;
  top: 4px;
  width: 6px;
  height: 6px;
  background: #00ffe1;
  border-radius: 50%;
  box-shadow: 0 0 8px #00ffe1;
}

/* HOVER / TOUCH */
.menu-inferior a:active {
  transform: scale(0.9);
}

/* animación entrada */
@keyframes slideUp {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

/* 📱 MOBILE SAFE AREA (iphone) */
@supports (padding: env(safe-area-inset-bottom)) {
  .menu-inferior {
    padding-bottom: env(safe-area-inset-bottom);
  }
}

</style>

<script>
document.addEventListener("DOMContentLoaded", () => {

  const links = document.querySelectorAll(".menu-inferior a");
  const current = window.location.href;

  links.forEach(link => {
    if (current.includes(link.getAttribute("href"))) {
      link.classList.add("active");
    }
  });

});
</script>

<!--COLOR SEMI OSCURO DE LA IMAGEN DEL CANDADO-->
<script>
document.querySelectorAll('.card-link').forEach(link => {
  const dataHref = link.getAttribute('data-href');
  if (!dataHref || dataHref.trim() === "") {
    const lock = link.querySelector('.lock-icon');
    if (lock) lock.style.display = 'block';
    link.setAttribute('href', 'javascript:void(0);');
    link.style.pointerEvents = 'none';
    link.style.opacity = '0.5';
  } else {
    link.setAttribute('href', dataHref);
  }
});
</script>
<script>
  window.addEventListener("load", function () {
    setTimeout(() => {
      const lock = document.querySelector(".lock-icon");
      if (lock) {
        lock.textContent = "🔓";
        lock.classList.add("open");
      }
    }, 2000);
  });
</script>

<!--REBOTE DE IMAGEN AL SELECCIONAR-->
<script>
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".card-link .xplus").forEach(el => {
    el.addEventListener("touchstart", () => {
      
      el.classList.remove("tap-animate");
      void el.offsetWidth;
      el.classList.add("tap-animate");
    });
  });
});
</script>


<script>

/* ======= JS NAMESPACE: us_carousel (no conflict) ======= */
(function(){
  const track = document.getElementById('us_track');
  if(!track) return;
  let slides = Array.from(track.querySelectorAll('.us_slide'));
  let isMoving = false;

  // create clones for infinite effect
  const firstClone = slides[0].cloneNode(true);
  const lastClone = slides[slides.length -1].cloneNode(true);
  track.appendChild(firstClone);
  track.insertBefore(lastClone, slides[0]);
  slides = Array.from(track.querySelectorAll('.us_slide'));

  let index = 1;
  track.style.transform = `translateX(-${index * 100}%)`;

  // setup dots based on real slides count (exclude clones)
  const realCount = slides.length - 2;
  const dotsContainer = document.getElementById('us_dots');
  for(let i=0;i<realCount;i++){
    const btn = document.createElement('button');
    btn.setAttribute('aria-label', 'ir a slide ' + (i+1));
    btn.dataset.index = i;
    btn.setAttribute('aria-pressed', i===0 ? 'true' : 'false');
    btn.addEventListener('click', ()=> {
      index = i + 1; // compensate clones
      track.style.transition = 'transform 0.6s ease';
      track.style.transform = `translateX(-${index * 100}%)`;
      updateDots();
      resetAutoplay();
    });
    dotsContainer.appendChild(btn);
  }

  const dots = Array.from(dotsContainer.querySelectorAll('button'));
  function updateDots(){
    const realIndex = (index - 1 + realCount) % realCount;
    dots.forEach((d, i)=> d.setAttribute('aria-pressed', i===realIndex ? 'true' : 'false'));
  }

  // autoplay
  let auto = true;
  let interval = setInterval(nextSlide, 4000);
  function nextSlide(){
    if(!auto || isMoving) return;
    isMoving = true;

    index++;
    track.style.transition = 'transform 0.6s ease';
    track.style.transform = `translateX(-${index * 100}%)`;
    updateDots();
  }
  function resetAutoplay(){
    clearInterval(interval);
    interval = setInterval(nextSlide, 4000);
  }

  // transitionend correction for clones
  track.addEventListener('transitionend', ()=>{
    if (slides[index].isEqualNode(firstClone)) {
      track.style.transition = 'none';
      index = 1;
      track.style.transform = `translateX(-${index * 100}%)`;
      setTimeout(()=> track.style.transition = '', 20);
    }
    if (slides[index].isEqualNode(lastClone)) {
      track.style.transition = 'none';
      index = slides.length - 2;
      track.style.transform = `translateX(-${index * 100}%)`;
      setTimeout(()=> track.style.transition = '', 20);
    }
    isMoving = false;
});
// touch controls
  let startX = 0;
  track.addEventListener('touchstart', (e)=>{
    auto = false;
    clearInterval(interval);
    startX = e.touches[0].clientX;
  }, {passive:true});

  track.addEventListener('touchend', (e)=>{
    if (isMoving) return;

    const moveX = e.changedTouches[0].clientX - startX;
    isMoving = true;

    if(moveX < -50) index++;
    else if(moveX > 50) index--;

    track.style.transition = 'transform 0.6s ease';
    track.style.transform = `translateX(-${index * 100}%)`;

    updateDots();
    auto = true;
    resetAutoplay();
});
// On resize ensure transform matches slide width units (works with percent)
  window.addEventListener('resize', ()=>{
    track.style.transition = 'none';
    track.style.transform = `translateX(-${index * 100}%)`;
    setTimeout(()=> track.style.transition = 'transform 0.6s ease', 20);
  });
})();

</script>

<script>
function setTheme(theme) {

  // Aplicar visual inmediato
  document.body.className = theme;

  marcarTemaActivo(theme);

  // 🔥 GUARDAR EN BD
  fetch("", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: "theme=" + theme
  });

  closeThemeMenu();
}

</script>

</body>
</html>
