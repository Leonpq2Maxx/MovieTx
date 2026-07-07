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
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="icon" type="image/png" href="Logo/Logo Nuevo.png">

<title>Renovar Plan - MovieTx</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

/* =========================================================
   🌌 ROOT
========================================================= */

:root{

--bg:#040816;
--bg2:#09111f;

--card:
rgba(14,22,38,.88);

--line:
rgba(255,255,255,.06);

--text:#ffffff;

--muted:#97a8c8;

--primary:#7b61ff;
--primary2:#9f7dff;

--success:#39d7a0;

--danger:#ff5b7d;

--radius:34px;

--shadow:
0 30px 70px rgba(0,0,0,.55);

}

/* =========================================================
   🌌 RESET
========================================================= */

*{
margin:0;
padding:0;
box-sizing:border-box;
}

html{
scroll-behavior:smooth;
-webkit-text-size-adjust:100%;
}

body{

font-family:'Inter',sans-serif;

min-height:100vh;

overflow-x:hidden;

background:

radial-gradient(
circle at top left,
rgba(123,97,255,.22),
transparent 28%
),

radial-gradient(
circle at bottom right,
rgba(57,215,160,.10),
transparent 30%
),

linear-gradient(
180deg,
var(--bg),
var(--bg2),
#040816
);

color:var(--text);

display:flex;
align-items:center;
justify-content:center;

padding:30px;

position:relative;
}

/* =========================================================
   ✨ FX
========================================================= */

body::before,
body::after{

content:"";

position:fixed;

border-radius:50%;

pointer-events:none;

filter:blur(90px);

opacity:.20;

z-index:-1;
}

body::before{

width:320px;
height:320px;

background:#7b61ff;

top:-120px;
left:-120px;
}

body::after{

width:360px;
height:360px;

background:#39d7a0;

right:-160px;
bottom:-160px;
}

/* =========================================================
   💳 CARD
========================================================= */

.card{

position:relative;

width:100%;
max-width:480px;

padding:40px 36px;

border-radius:38px;

overflow:hidden;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:1px solid var(--line);

backdrop-filter:blur(24px);

box-shadow:var(--shadow);

animation:fadeUp .5s ease;
}

.card::before{

content:"";

position:absolute;

top:-120px;
right:-120px;

width:240px;
height:240px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(123,97,255,.22),
transparent 70%
);
}

/* =========================================================
   ✨ ANIMATION
========================================================= */

@keyframes fadeUp{

from{
opacity:0;
transform:translateY(25px);
}

to{
opacity:1;
transform:translateY(0);
}

}

/* =========================================================
   🎬 TOP
========================================================= */

.top{

display:flex;
align-items:center;
justify-content:space-between;

gap:20px;

margin-bottom:34px;
}

.logo{

display:flex;
align-items:center;

gap:14px;
}

.logo img{

width:58px;
height:58px;

border-radius:20px;

object-fit:cover;
}

.logo h1{

font-size:28px;
font-weight:800;
}

.plan-badge{

padding:10px 16px;

border-radius:999px;

font-size:12px;
font-weight:700;

background:
rgba(57,215,160,.12);

border:1px solid
rgba(57,215,160,.28);

color:#7ff3c8;
}

/* =========================================================
   📝 CONTENT
========================================================= */

.content{

text-align:center;
}

.content h2{

font-size:34px;
font-weight:800;

margin-bottom:12px;
}

.content p{

font-size:14px;

line-height:1.7;

color:var(--muted);

margin-bottom:30px;
}

.user-email{

display:inline-flex;
align-items:center;
justify-content:center;

padding:12px 18px;

border-radius:18px;

background:
rgba(255,255,255,.04);

border:1px solid
rgba(255,255,255,.05);

font-size:13px;
font-weight:600;

margin-bottom:30px;

word-break:break-word;
}

/* =========================================================
   💰 PRICE
========================================================= */

.price-box{

padding:26px;

border-radius:28px;

margin-bottom:30px;

background:
linear-gradient(
135deg,
rgba(123,97,255,.14),
rgba(157,125,255,.08)
);

border:1px solid
rgba(123,97,255,.24);
}

.price-label{

font-size:13px;

letter-spacing:1px;

text-transform:uppercase;

color:#cfc7ff;

margin-bottom:12px;
}

.price{

display:flex;
align-items:flex-end;
justify-content:center;

gap:8px;
}

.price strong{

font-size:52px;
line-height:1;

font-weight:800;
}

.price span{

font-size:16px;

color:var(--muted);

margin-bottom:6px;
}

/* =========================================================
   ✅ BENEFITS
========================================================= */

.benefits{

display:flex;
flex-direction:column;

gap:14px;

margin-bottom:32px;
}

.benefit{

display:flex;
align-items:center;

gap:14px;

padding:16px 18px;

border-radius:20px;

background:
rgba(255,255,255,.04);

border:1px solid
rgba(255,255,255,.05);

text-align:left;
}

.benefit-icon{

width:42px;
height:42px;

flex-shrink:0;

display:flex;
align-items:center;
justify-content:center;

border-radius:14px;

font-size:18px;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);
}

.benefit h3{

font-size:15px;
margin-bottom:4px;
}

.benefit p{

margin:0;

font-size:12px;

line-height:1.6;

color:var(--muted);
}

/* =========================================================
   🔘 BUTTON
========================================================= */

.btn{

width:100%;

height:64px;

border:none;
outline:none;

cursor:pointer;

border-radius:22px;

font-family:inherit;

font-size:16px;
font-weight:800;

color:#fff;

background:
linear-gradient(
135deg,
var(--primary),
var(--primary2)
);

transition:
transform .25s ease,
box-shadow .25s ease,
opacity .25s ease;
}

.btn:hover{

transform:translateY(-3px);

box-shadow:
0 18px 35px rgba(123,97,255,.28);
}

.btn:active{
transform:scale(.98);
}

/* =========================================================
   🔒 FOOTER
========================================================= */

.footer{

margin-top:24px;

text-align:center;

font-size:12px;

line-height:1.7;

color:#7f8eb1;
}

/* =========================================================
   📱 ANDROID
========================================================= */

@media screen
and (max-width:920px)
and (pointer:coarse)
and (-webkit-min-device-pixel-ratio:1){

body{
padding:18px;
}

.card{

padding:30px 24px;

border-radius:30px;
}

.top{

margin-bottom:28px;
}

.logo img{

width:52px;
height:52px;
}

.logo h1{

font-size:24px;
}

.content h2{

font-size:28px;
}

.price strong{

font-size:44px;
}

.btn{

height:58px;

font-size:15px;

border-radius:18px;
}

}

/* =========================================================
   🍎 IPHONE
========================================================= */

@media only screen
and (max-width:430px)
and (-webkit-touch-callout:none){

body{

padding-top:
max(16px, env(safe-area-inset-top));

padding-bottom:
max(16px, env(safe-area-inset-bottom));

padding-left:14px;
padding-right:14px;
}

.card{

padding:26px 18px;

border-radius:26px;
}

.top{

flex-direction:column;
align-items:flex-start;

gap:18px;

margin-bottom:24px;
}

.logo{

gap:12px;
}

.logo img{

width:48px;
height:48px;

border-radius:16px;
}

.logo h1{

font-size:22px;
}

.plan-badge{

font-size:11px;

padding:8px 14px;
}

.content h2{

font-size:25px;

line-height:1.15;
}

.content p{

font-size:13px;

margin-bottom:22px;
}

.user-email{

font-size:12px;

padding:10px 14px;

border-radius:15px;
}

.price-box{

padding:20px;

border-radius:22px;
}

.price strong{

font-size:38px;
}

.price span{

font-size:14px;
}

.benefits{

gap:12px;
}

.benefit{

padding:14px;

border-radius:18px;
}

.benefit-icon{

width:38px;
height:38px;

font-size:16px;

border-radius:12px;
}

.benefit h3{

font-size:14px;
}

.benefit p{

font-size:11px;
}

.btn{

height:56px;

font-size:14px;

border-radius:18px;
}

.footer{

font-size:11px;
}

}

/* =========================================================
   🖥 PC / DESKTOP
========================================================= */

@media(min-width:1200px){

body{
padding:40px;
}

.card{

max-width:520px;

padding:46px 42px;
}

.content h2{

font-size:38px;
}

.content p{

font-size:15px;
}

.price strong{

font-size:58px;
}

.benefits{

gap:16px;
}

.benefit{

padding:18px 20px;
}

.btn{

height:66px;

font-size:17px;
}

}

/* =========================================================
   ✨ SCROLL
========================================================= */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{

background:#27365d;

border-radius:999px;
}

</style>

</head>

<body>

<div class="card">

    <div class="top">

        <div class="logo">
            <img src="Logo/Logo Nuevo.png">
            <h1>MovieTx</h1>
        </div>

        <div class="plan-badge">
            PREMIUM
        </div>

    </div>

    <div class="content">

        <h2>Renová tu plan</h2>

        <p>
            Continuá disfrutando películas, series y contenido exclusivo
            sin interrupciones en todos tus dispositivos.
        </p>

        <div class="user-email">
            <?= htmlspecialchars($email) ?>
        </div>

        <div class="price-box">

            <div class="price-label">
                Suscripción mensual
            </div>

            <div class="price">
                <strong>$3000</strong>
                <span>ARS / mes</span>
            </div>

        </div>

        <div class="benefits">

            <div class="benefit">

                <div class="benefit-icon">
                    ✔
                </div>

                <div>
                    <h3>Contenido ilimitado</h3>
                    <p>
                        Acceso completo a películas y series premium.
                    </p>
                </div>

            </div>

            <div class="benefit">

                <div class="benefit-icon">
                    🎬
                </div>

                <div>
                    <h3>Estrenos exclusivos</h3>
                    <p>
                        Mirá contenido nuevo antes que nadie.
                    </p>
                </div>

            </div>

            <div class="benefit">

                <div class="benefit-icon">
                    ⚡
                </div>

                <div>
                    <h3>Sin anuncios</h3>
                    <p>
                        Reproducción rápida y experiencia fluida.
                    </p>
                </div>

            </div>

        </div>

        <form action="verificar_pago.php" method="POST">

            <button class="btn">
                Pagar con Mercado Pago
            </button>

        </form>

        <div class="footer">
            El plan se activará automáticamente después de confirmar el pago ✔
        </div>

    </div>

</div>

</body>
</html>