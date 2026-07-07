<?php
session_start();
require_once 'config.php';

// 🔒 VALIDAR SESIÓN
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$adminId = (int)$_SESSION['id'];

// =========================
// OBTENER DATOS DEL ADMIN
// =========================

$stmt = $conn->prepare("
SELECT
    id,
    name,
    email,
    foto,
    password,
    role,
    admin_level
FROM admins
WHERE id=?
LIMIT 1
");

$stmt->bind_param("i", $adminId);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {

    session_destroy();

    header("Location:index.php");
    exit();
}

// =========================
// ACTUALIZAR FOTO
// =========================

if (isset($_POST['update_photo'])) {

    $carpeta = "uploads/admins/";

    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0777, true);
    }

    // =========================
    // FOTO RECORTADA (CROPPER)
    // =========================

    if (!empty($_POST['cropped_photo'])) {

        $imageData = $_POST['cropped_photo'];

        $imageData = preg_replace(
            '#^data:image/\w+;base64,#i',
            '',
            $imageData
        );

        $imageData = str_replace(
            ' ',
            '+',
            $imageData
        );

        $imageData = base64_decode($imageData);

        if ($imageData !== false) {

            $nombreArchivo =
                time() .
                "_" .
                uniqid() .
                ".jpg";

            $rutaFoto =
                $carpeta .
                $nombreArchivo;

            if (
                file_put_contents(
                    $rutaFoto,
                    $imageData
                )
            ) {

                // BORRAR FOTO ANTERIOR
                if (
                    !empty($admin['foto']) &&
                    strpos($admin['foto'], 'http') !== 0 &&
                    file_exists($admin['foto'])
                ) {
                    @unlink($admin['foto']);
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE admins
                    SET foto=?
                    WHERE id=?
                ");

                $stmtUpdate->bind_param(
                    "si",
                    $rutaFoto,
                    $adminId
                );

                if ($stmtUpdate->execute()) {

    $_SESSION['msg'] =
        "Foto actualizada correctamente";

    $_SESSION['msg_type'] =
        "success";

    header("Location: Administrador.php");
    exit();

} else {

                    $_SESSION['msg'] =
                        "Error al actualizar la base de datos";

                    $_SESSION['msg_type'] =
                        "error";
                }

            } else {

                $_SESSION['msg'] =
                    "No se pudo guardar la imagen recortada";

                $_SESSION['msg_type'] =
                    "error";
            }

        } else {

            $_SESSION['msg'] =
                "Error al procesar la imagen recortada";

            $_SESSION['msg_type'] =
                "error";
        }

    }

    // =========================
    // FOTO NORMAL
    // =========================

    elseif (
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] === 0
    ) {

        $ext = strtolower(
            pathinfo(
                $_FILES['foto']['name'],
                PATHINFO_EXTENSION
            )
        );

        $permitidas = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        if (!in_array($ext, $permitidas)) {

            $_SESSION['msg'] =
                "Formato de imagen no permitido";

            $_SESSION['msg_type'] =
                "error";

        } else {

            $nombreArchivo =
                time() .
                "_" .
                uniqid() .
                "." .
                $ext;

            $rutaFoto =
                $carpeta .
                $nombreArchivo;

            if (
                move_uploaded_file(
                    $_FILES['foto']['tmp_name'],
                    $rutaFoto
                )
            ) {

                // BORRAR FOTO ANTERIOR
                if (
                    !empty($admin['foto']) &&
                    strpos($admin['foto'], 'http') !== 0 &&
                    file_exists($admin['foto'])
                ) {
                    @unlink($admin['foto']);
                }

                $stmtUpdate = $conn->prepare("
                    UPDATE admins
                    SET foto=?
                    WHERE id=?
                ");

                $stmtUpdate->bind_param(
                    "si",
                    $rutaFoto,
                    $adminId
                );

                if ($stmtUpdate->execute()) {

    $_SESSION['msg'] =
        "Foto actualizada correctamente";

    $_SESSION['msg_type'] =
        "success";

    header("Location: Administrador.php");
    exit();

} else {

                    $_SESSION['msg'] =
                        "Error al actualizar la base de datos";

                    $_SESSION['msg_type'] =
                        "error";
                }

            } else {

                $_SESSION['msg'] =
                    "No se pudo subir la imagen";

                $_SESSION['msg_type'] =
                    "error";
            }
        }

    } else {

        $_SESSION['msg'] =
            "Seleccione una imagen";

        $_SESSION['msg_type'] =
            "error";
    }
}

$stmt->bind_param("i", $adminId);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$adminName  = $admin['name'];
$adminEmail = $admin['email'];
$adminFoto  = $admin['foto'];

if (empty($adminFoto)) {
    $adminFoto =
    "https://cdn-icons-png.flaticon.com/512/149/149071.png";
}

// =========================
// ENMASCARAR EMAIL
// =========================

function maskEmail($email)
{
    $parts = explode("@", $email);

    if (count($parts) < 2) {
        return $email;
    }

    $name   = $parts[0];
    $domain = $parts[1];

    $visible = substr($name, 0, 2);

    return
        $visible .
        str_repeat(
            "*",
            max(strlen($name) - 2, 4)
        ) .
        "@" .
        $domain;
}

$emailMask = maskEmail($adminEmail);

// =========================
// CONTRASEÑA OCULTA
// =========================

$passwordMask = str_repeat("*", 12);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="Logo/Logo Nuevo -512x512.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Editar Administrador | MovieTx</title>

<style>

/* =========================
MOVIETX ADMIN PRO 2026
========================= */

:root{
--primary:#ff003c;
--primary-hover:#ff295f;

--success:#00d65a;
--success-hover:#00f06b;

--bg:#080808;

--card:rgba(255,255,255,.05);
--card-hover:rgba(255,255,255,.08);

--border:rgba(255,255,255,.08);

--text:#fff;
--text2:#bdbdbd;

--shadow:0 15px 40px rgba(0,0,0,.45);

--glass:blur(22px);
}

*{
margin:0;
padding:0;
box-sizing:border-box;
-webkit-tap-highlight-color:transparent;
}

html{
scroll-behavior:smooth;
}

body{
font-family:"Segoe UI",system-ui,sans-serif;

background:
radial-gradient(
circle at top,
rgba(255,0,60,.18),
transparent 35%
),
linear-gradient(
180deg,
#050505 0%,
#0d0d0d 40%,
#161616 100%
);

color:var(--text);

min-height:100vh;

padding:
max(20px,env(safe-area-inset-top))
15px
max(20px,env(safe-area-inset-bottom));

overflow-x:hidden;
}

/* =========================
SCROLLBAR
========================= */

::-webkit-scrollbar{
width:8px;
}

::-webkit-scrollbar-track{
background:#111;
}

::-webkit-scrollbar-thumb{
background:var(--primary);
border-radius:20px;
}

/* =========================
CONTAINER
========================= */

.admin-container{
width:100%;
max-width:1100px;
margin:auto;
}

/* =========================
CARD
========================= */

.card{

background:var(--card);

backdrop-filter:var(--glass);
-webkit-backdrop-filter:var(--glass);

border:1px solid var(--border);

border-radius:28px;

padding:32px;

box-shadow:var(--shadow);

animation:fadeIn .4s ease;
}

@keyframes fadeIn{

from{
opacity:0;
transform:translateY(20px);
}

to{
opacity:1;
transform:none;
}

}

/* =========================
HEADER
========================= */

.admin-header{
display:flex;
justify-content:space-between;
align-items:center;
gap:20px;
flex-wrap:wrap;

margin-bottom:10px;
}

.admin-info{
display:flex;
align-items:center;
gap:18px;
}

/* FOTO ADMIN */

.admin-info img{

width:90px;
height:90px;

border-radius:50%;

border:3px solid var(--primary);

object-fit:cover;

cursor:pointer;

transition:.3s;

box-shadow:
0 0 25px rgba(255,0,60,.25);
}

.admin-info img:hover{

transform:scale(1.05);

box-shadow:
0 0 35px rgba(255,0,60,.45);

}

.admin-info h3{
font-size:23px;
font-weight:800;
margin-bottom:5px;
}

.email-mask{
font-size:14px;
color:var(--text2);
}

/* =========================
SECTIONS
========================= */

.section{

margin-top:18px;

padding:18px;

border-radius:18px;

background:
rgba(255,255,255,.03);

border:
1px solid rgba(255,255,255,.05);

display:flex;
justify-content:space-between;
align-items:center;

gap:15px;

transition:.25s;
}

.section:hover{

background:
rgba(255,255,255,.05);

transform:translateY(-2px);

}

.section span{
color:var(--text2);
}

/* =========================
BUTTONS
========================= */

.action-btn{

border:none;

padding:11px 18px;

border-radius:12px;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary-hover)
);

color:white;

font-weight:700;

cursor:pointer;

transition:.25s;
}

.action-btn:hover{

transform:translateY(-2px);

box-shadow:
0 10px 25px rgba(255,0,60,.25);

}

.save-btn{

margin-top:15px;

width:100%;
height:52px;

border:none;

border-radius:12px;

background:
linear-gradient(
135deg,
var(--success),
var(--success-hover)
);

font-weight:800;
font-size:15px;

color:white;

cursor:pointer;

transition:.25s;
}

.save-btn:hover{

transform:translateY(-2px);

box-shadow:
0 10px 25px rgba(0,214,90,.25);

}

/* =========================
MODAL
========================= */

.modal{

display:none;

position:fixed;
inset:0;

background:
rgba(0,0,0,.78);

backdrop-filter:blur(8px);

justify-content:center;
align-items:center;

padding:20px;

z-index:9999;
}

.modal-content{

width:100%;
max-width:500px;

background:#111;

border:1px solid rgba(255,255,255,.08);

border-radius:24px;

padding:25px;

position:relative;

animation:modalOpen .25s ease;

max-height:90vh;

overflow-y:auto;
}

@keyframes modalOpen{

from{
opacity:0;
transform:scale(.95);
}

to{
opacity:1;
transform:scale(1);
}

}

.close{

position:absolute;

right:18px;
top:12px;

font-size:30px;

cursor:pointer;

color:white;

transition:.2s;
}

.close:hover{
color:var(--primary);
}

/* =========================
FORM
========================= */

.modal-content h3{

margin-bottom:20px;

font-size:22px;
}

.modal-content label{

display:block;

margin-top:12px;
margin-bottom:6px;

font-size:14px;
font-weight:700;

color:#ddd;
}

.modal-content input{

width:100%;
height:52px;

padding:0 14px;

border-radius:12px;

border:1px solid rgba(255,255,255,.08);

background:#1b1b1b;

color:white;

outline:none;

transition:.25s;
}

.modal-content input:focus{

border-color:
rgba(255,0,60,.5);

box-shadow:
0 0 18px rgba(255,0,60,.18);

}

/* =========================
PASSWORD BUTTON
========================= */

.toggle-password{

width:100%;
height:45px;

margin-top:8px;

border:none;

border-radius:12px;

background:#252525;

color:white;

cursor:pointer;

transition:.25s;
}

.toggle-password:hover{
background:#303030;
}

/* =========================
EDITOR FOTO
========================= */

.editor{

display:flex;
flex-direction:column;
align-items:center;

gap:15px;
}

.editor-preview{

width:230px;
height:230px;

overflow:hidden;

border-radius:50%;

border:4px solid var(--primary);

box-shadow:
0 0 30px rgba(255,0,60,.25);
}

.editor-preview img{

width:100%;
height:100%;

object-fit:cover;

transition:.2s ease;
}

.editor input[type=file]{

padding:12px;

height:auto;

cursor:pointer;
}

/* preparado para zoom futuro */

.zoom-controls{

display:flex;
gap:10px;

width:100%;
}

.zoom-controls button{

flex:1;

height:45px;

border:none;

border-radius:12px;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary-hover)
);

color:white;

font-weight:700;

cursor:pointer;
}

/* =========================
TOAST
========================= */

.toast{

position:fixed;

top:20px;
right:20px;

padding:15px 20px;

border-radius:14px;

font-weight:700;

z-index:99999;

animation:slideIn .35s ease;
}

.toast.success{

background:
rgba(0,255,120,.15);

border:
1px solid rgba(0,255,120,.4);

color:#00ff8a;
}

.toast.error{

background:
rgba(255,0,60,.15);

border:
1px solid rgba(255,0,60,.4);

color:#ff003c;
}

@keyframes slideIn{

from{
opacity:0;
transform:translateX(120px);
}

to{
opacity:1;
transform:none;
}

}

/* =========================
MOBILE
========================= */

@media(max-width:768px){

.card{
padding:18px;
border-radius:22px;
}

.admin-header{
justify-content:center;
text-align:center;
}

.admin-info{
flex-direction:column;
}

.admin-info img{
width:75px;
height:75px;
}

.admin-info h3{
font-size:18px;
}

.email-mask{
font-size:12px;
}

.section{

flex-direction:column;

align-items:stretch;
text-align:center;
}

.action-btn{
width:100%;
}

.modal-content{

padding:20px;

border-radius:20px;
}

.editor-preview{

width:170px;
height:170px;
}

.toast{

left:10px;
right:10px;

top:10px;

text-align:center;
}

}

</style>
</head>
<body>

<?php if(isset($_SESSION['msg'])): ?>
<div class="toast <?= $_SESSION['msg_type'] ?>">
    <?= $_SESSION['msg'] ?>
</div>
<?php
unset($_SESSION['msg']);
unset($_SESSION['msg_type']);
endif;
?>

<div class="admin-container">

<div class="card">

<!-- HEADER ADMIN -->
<div class="admin-header">

<div class="admin-info">

<!-- FOTO ADMIN (AHORA ABRE MODAL AL HACER CLICK) -->
<img
src="<?= htmlspecialchars($adminFoto) ?>?v=<?= time() ?>"
id="adminPhoto"
onclick="openPhotoModal()"
style="cursor:pointer;">

<div>

<h3>
<?= htmlspecialchars($adminName) ?>
</h3>

<div class="email-mask">
<?= htmlspecialchars($emailMask) ?>
</div>

</div>
</div>
</div>

<!-- EMAIL -->
<div class="section">

<span>
Email actual:
<?= htmlspecialchars($emailMask) ?>
</span>

<button
type="button"
class="action-btn"
onclick="openEmailModal()">
Editar
</button>

</div>

<!-- PASSWORD -->
<div class="section">

<span>
Contraseña:
<?= $passwordMask ?>
</span>

<button
type="button"
class="action-btn"
onclick="openPassModal()">
Editar
</button>

</div>

<!-- FOTO -->
<div class="section">

<span>
Foto del administrador
</span>

<button
type="button"
class="action-btn"
onclick="openPhotoModal()">
Editar
</button>

</div>

</div>
</div>

<!-- =====================================
MODAL EMAIL
===================================== -->

<div class="modal" id="emailModal">

<div class="modal-content">

<span class="close" onclick="closeModal('emailModal')">×</span>

<h3>Editar Email</h3>

<form method="POST">

<label>Email actual</label>

<div style="
padding:12px;
border-radius:10px;
background:#222;
color:#999;
margin-top:10px;
margin-bottom:15px;
">
<?= htmlspecialchars($emailMask) ?>
</div>

<label>Nuevo Email</label>

<input type="email" name="new_email" placeholder="Nuevo email" required>

<label>Confirmar Email</label>

<input type="email" name="confirm_email" placeholder="Confirmar email" required>

<label>Contraseña Actual</label>

<input type="password" name="current_password" id="passInput" placeholder="Contraseña actual" required>

<button type="button" class="toggle-password" onclick="togglePass()">
Mostrar / Ocultar
</button>

<button type="submit" name="update_email" class="save-btn">
Actualizar datos
</button>

</form>

</div>
</div>

<!-- =====================================
MODAL PASSWORD
===================================== -->

<div class="modal" id="passModal">

<div class="modal-content">

<span class="close" onclick="closeModal('passModal')">×</span>

<h3>Editar Contraseña</h3>

<form method="POST">

<input type="password" name="current_password" placeholder="Contraseña actual" required>

<input type="password" name="new_password" placeholder="Nueva contraseña" required>

<input type="password" name="confirm_password" placeholder="Confirmar contraseña" required>

<button type="submit" name="update_password" class="save-btn">
Actualizar contraseña
</button>

</form>

</div>
</div>

<!-- =====================================
MODAL FOTO (AJUSTE)
===================================== -->

<div class="modal" id="photoModal">

<div class="modal-content">

<span class="close" onclick="closeModal('photoModal')">×</span>

<h3>Editar Foto</h3>

<form method="POST" enctype="multipart/form-data">

<div class="editor">

<div style="
width:220px;
height:220px;
overflow:hidden;
border-radius:50%;
border:4px solid #ff003c;
">

<img
id="preview"
src="<?= htmlspecialchars($adminFoto) ?>?v=<?= time() ?>"
style="
width:100%;
height:100%;
object-fit:cover;
transform:scale(1);
">

</div>

<input
type="file"
id="fotoInput"
name="foto"
accept="image/*"
required>

<input
type="hidden"
name="cropped_photo"
id="cropped_photo">

<button type="submit" name="update_photo" class="save-btn">
Guardar cambios
</button>

</div>

</form>

</div>
</div>

<!-- =====================================
MODAL AJUSTAR FOTO
===================================== -->

<div class="modal" id="cropModal">

<div class="modal-content">

<span
class="close"
onclick="closeModal('cropModal')">
×
</span>

<h3>Ajustar Foto</h3>

<div style="
width:100%;
height:350px;
overflow:hidden;
">

<img
id="cropImage"
style="
max-width:100%;
display:block;
">
</div>

<div class="zoom-controls">

<button
type="button"
onclick="zoomCrop(0.1)">
Zoom +
</button>

<button
type="button"
onclick="zoomCrop(-0.1)">
Zoom -
</button>

</div>

<button
type="button"
class="save-btn"
onclick="saveCrop()">
Guardar ajuste
</button>

</div>

</div>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">

<script
src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js">
</script>

<!-- =====================================
SCRIPTS
===================================== -->

<script>

function openEmailModal(){
document.getElementById("emailModal").style.display="flex";
}

function openPassModal(){
document.getElementById("passModal").style.display="flex";
}

function openPhotoModal(){
document.getElementById("photoModal").style.display="flex";
}

function closeModal(id){
document.getElementById(id).style.display="none";
}

function togglePass(){
const p = document.getElementById("passInput");
p.type = (p.type === "password") ? "text" : "password";
}

let scale = 1;

function zoom(value){
scale *= value;
document.getElementById("preview").style.transform = `scale(${scale})`;
}

const fotoInput =
document.getElementById("fotoInput");

let cropper = null;
let croppedImageData = "";

if(fotoInput){

fotoInput.addEventListener("change", function(){

const file = this.files[0];

if(!file) return;

const reader = new FileReader();

reader.onload = function(e){

document.getElementById("cropImage").src =
e.target.result;

document.getElementById("cropModal").style.display =
"flex";

setTimeout(() => {

if(cropper){
cropper.destroy();
}

cropper = new Cropper(
document.getElementById("cropImage"),
{
aspectRatio:1,
viewMode:1,
dragMode:"move",
autoCropArea:1,
background:false,
responsive:true
}
);

},100);

};

reader.readAsDataURL(file);

});

}

function zoomCrop(value){

if(!cropper) return;

cropper.zoom(value);

}

function saveCrop(){

if(!cropper) return;

const canvas =
cropper.getCroppedCanvas({
width:600,
height:600
});

croppedImageData =
canvas.toDataURL(
"image/jpeg",
0.9
);

document.getElementById("preview").src =
croppedImageData;

document.getElementById("adminPhoto").src =
croppedImageData;

document.getElementById("cropped_photo").value =
croppedImageData;

closeModal("cropModal");

}

window.onclick = function(event){

const emailModal = document.getElementById("emailModal");
const passModal = document.getElementById("passModal");
const photoModal = document.getElementById("photoModal");

if(event.target === emailModal){
emailModal.style.display = "none";
}

if(event.target === passModal){
passModal.style.display = "none";
}

if(event.target === photoModal){
photoModal.style.display = "none";
}

};

setTimeout(() => {

const toast = document.querySelector(".toast");

if(toast){
toast.style.transition = "all .5s ease";
toast.style.opacity = "0";

setTimeout(() => {
toast.remove();
}, 500);
}

}, 5000);

</script>

</body>
</html>