<?php
session_start();

if(!isset($_SESSION['email'])){
    header("Location: index.php");
    exit;
}

$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Renovar Plan - MovieTx</title>

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body{
    background: linear-gradient(135deg, #0f0f0f, #1c1c1c);
    color:#fff;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

/* CONTENEDOR */
.card{
    background:#181818;
    padding:40px;
    border-radius:20px;
    width:400px;
    text-align:center;
    box-shadow:0 20px 50px rgba(0,0,0,0.7);
    animation:fadeIn 0.6s ease;
}

/* TITULO */
.card h2{
    font-size:28px;
    margin-bottom:10px;
}

/* SUB */
.card p{
    color:#aaa;
    margin-bottom:25px;
}

/* PRECIO */
.price{
    font-size:32px;
    font-weight:bold;
    margin:20px 0;
    color:#e50914;
}

/* BENEFICIOS */
.benefits{
    text-align:left;
    margin:20px 0;
}

.benefits li{
    margin:10px 0;
    color:#ccc;
}

/* BOTÓN */
.btn{
    width:100%;
    padding:15px;
    font-size:18px;
    border:none;
    border-radius:10px;
    background:#e50914;
    color:#fff;
    cursor:pointer;
    transition:0.3s;
}

.btn:hover{
    background:#ff1e1e;
    transform:scale(1.05);
}

/* FOOT */
.footer{
    margin-top:20px;
    font-size:13px;
    color:#777;
}

/* ANIMACIÓN */
@keyframes fadeIn{
    from{
        opacity:0;
        transform:translateY(20px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}
</style>

</head>

<body>

<div class="card">

    <h2>Renovar Plan</h2>
    <p>Bienvenido, <?= htmlspecialchars($email) ?></p>

    <div class="price">$3000 ARS / mes</div>

    <ul class="benefits">
        <li>✔ Acceso ilimitado a todo el contenido</li>
        <li>✔ Series y películas exclusivas</li>
        <li>✔ Sin anuncios</li>
        <li>✔ Disponible en todos los dispositivos</li>
    </ul>

    <!-- 🔥 BOTÓN QUE CREA EL PAGO -->
    <form action="verificar_pago.php" method="POST">
        <button class="btn">
            Pagar con Mercado Pago
        </button>
    </form>

    <div class="footer">
        El plan se activará automáticamente después del pago ✔
    </div>

</div>

</body>
</html>