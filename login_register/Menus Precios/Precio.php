<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>MovieTx Premium</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>

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

:root{

--bg:#040816;
--bg2:#081120;

--card:
rgba(14,22,38,.86);

--cardHover:
rgba(18,28,48,.95);

--line:
rgba(255,255,255,.07);

--text:#ffffff;

--muted:#93a4c8;

--primary:#00d9ff;
--secondary:#7b61ff;
--pink:#ff0077;

--shadow:
0 25px 70px rgba(0,0,0,.45);

}

/* =========================================================
🌌 BODY
========================================================= */

body{

font-family:'Inter',sans-serif;

background:

radial-gradient(
circle at top left,
rgba(123,97,255,.18),
transparent 28%
),

radial-gradient(
circle at bottom right,
rgba(0,217,255,.14),
transparent 35%
),

linear-gradient(
180deg,
var(--bg),
var(--bg2),
#04060f
);

color:var(--text);

overflow-x:hidden;

min-height:100vh;

position:relative;
}

body.loading{
overflow:hidden;
touch-action:none;
height:100vh;
}

body::before,
body::after{

content:"";

position:fixed;

border-radius:50%;

filter:blur(100px);

pointer-events:none;

z-index:-1;

opacity:.24;
}

body::before{

width:360px;
height:360px;

background:#7b61ff;

top:-150px;
left:-150px;
}

body::after{

width:400px;
height:400px;

background:#00d9ff;

right:-180px;
bottom:-180px;
}

/* =========================================================
✨ SCROLLBAR
========================================================= */

::-webkit-scrollbar{
width:10px;
}

::-webkit-scrollbar-thumb{

background:#26385d;

border-radius:999px;
}

/* =========================================================
🌌 PREMIUM LOADER
========================================================= */

#loader-screen{

position:fixed;
inset:0;

display:flex;
align-items:center;
justify-content:center;

overflow:hidden;

padding:20px;

background:
radial-gradient(circle at top,
rgba(0,180,255,.12),
transparent 30%),

radial-gradient(circle at bottom,
rgba(255,0,128,.10),
transparent 30%),

linear-gradient(
180deg,
#070b14 0%,
#020307 45%,
#000 100%
);

z-index:999999;

transition:
opacity .8s ease,
visibility .8s ease;

isolation:isolate;
will-change:opacity;
}

#loader-screen.hidden{
opacity:0;
visibility:hidden;
pointer-events:none;
}

/* =========================================================
✨ BACKGROUND EFFECT
========================================================= */

.loader-bg{

position:absolute;
inset:-20%;

background:
conic-gradient(
from 180deg,
rgba(0,170,255,.05),
rgba(123,45,255,.04),
rgba(255,0,128,.05),
rgba(0,170,255,.05)
);

animation:
bgRotate 22s linear infinite;

will-change:transform;
transform:translateZ(0);
}

@keyframes bgRotate{

to{
transform:rotate(360deg);
}
}

/* =========================================================
✨ PARTICLES
========================================================= */

.loader-particles{

position:absolute;
inset:0;

overflow:hidden;
pointer-events:none;
}

.loader-particles::before,
.loader-particles::after{

content:"";

position:absolute;

width:220%;
height:220%;

background-image:
radial-gradient(
rgba(255,255,255,.13) 1px,
transparent 1px
);

background-size:
58px 58px;

animation:
particlesMove 22s linear infinite;

will-change:transform;
}

.loader-particles::after{
opacity:.4;
animation-duration:34s;
transform:rotate(12deg);
}

@keyframes particlesMove{

from{
transform:translate3d(0,0,0);
}

to{
transform:translate3d(-140px,-140px,0);
}
}

/* =========================================================
💡 GLOW
========================================================= */

.loader-glow{

position:absolute;

width:460px;
height:460px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(0,170,255,.16),
transparent 70%
);

filter:blur(55px);

animation:
pulseGlow 4s ease infinite;

pointer-events:none;
will-change:transform,opacity;
}

@keyframes pulseGlow{

0%,100%{
transform:scale(1);
opacity:.65;
}

50%{
transform:scale(1.15);
opacity:1;
}
}

/* =========================================================
📦 CONTENT
========================================================= */

.loader-content{

position:relative;
z-index:20;

width:min(92vw,420px);

display:flex;
flex-direction:column;
align-items:center;
justify-content:center;

text-align:center;

animation:
loaderFade .9s ease;

will-change:transform,opacity;
}

@keyframes loaderFade{

from{
opacity:0;
transform:translateY(18px);
}

to{
opacity:1;
transform:translateY(0);
}
}

/* =========================================================
🪐 LOGO
========================================================= */

.loader-circle{

position:relative;

width:170px;
height:170px;

margin:
0 auto 34px;

display:flex;
align-items:center;
justify-content:center;

isolation:isolate;
}

/* =========================================================
🌀 RINGS
========================================================= */

.circle-ring{

position:absolute;
inset:0;

border-radius:50%;

border:
1px solid rgba(255,255,255,.08);

pointer-events:none;
will-change:transform;
}

.ring1{

animation:
rotateRing 6s linear infinite;
}

.ring2{

inset:12px;

border-color:
rgba(0,180,255,.20);

animation:
rotateRingReverse 8s linear infinite;
}

@keyframes rotateRing{

to{
transform:rotate(360deg);
}
}

@keyframes rotateRingReverse{

to{
transform:rotate(-360deg);
}
}

/* =========================================================
🌟 CORE
========================================================= */

.circle-core{

position:absolute;
inset:20px;

display:flex;
align-items:center;
justify-content:center;

border-radius:50%;

overflow:hidden;

background:
linear-gradient(
145deg,
rgba(255,255,255,.08),
rgba(255,255,255,.02)
);

backdrop-filter:blur(12px);
-webkit-backdrop-filter:blur(12px);

border:
1px solid rgba(255,255,255,.08);

box-shadow:
0 0 35px rgba(0,170,255,.18),
inset 0 0 25px rgba(255,255,255,.04);

isolation:isolate;
}

.circle-core::before{

content:"";

position:absolute;
inset:0;

border-radius:50%;

padding:2px;

background:
linear-gradient(
135deg,
#00e1ff,
#7b2dff,
#ff007f
);

-webkit-mask:
linear-gradient(#fff 0 0)
content-box,
linear-gradient(#fff 0 0);

-webkit-mask-composite:xor;
mask-composite:exclude;

animation:
spinBorder 4s linear infinite;

pointer-events:none;
will-change:transform;
}

@keyframes spinBorder{

to{
transform:rotate(360deg);
}
}

/* =========================================================
🎬 LOGO IMAGE
========================================================= */

.loader-logo{

width:88px;
height:88px;

object-fit:contain;

position:relative;
z-index:2;

pointer-events:none;
user-select:none;

filter:
drop-shadow(0 0 16px rgba(0,225,255,.45));

animation:
logoFloat 3s ease infinite;

will-change:transform;
}

@keyframes logoFloat{

0%,100%{
transform:translateY(0);
}

50%{
transform:translateY(-6px);
}
}

/* =========================================================
🎬 TITLES
========================================================= */

.loader-title{

font-size:2.8rem;
font-weight:900;

line-height:1;
letter-spacing:.5px;

margin-bottom:10px;

color:#fff;

text-shadow:
0 0 20px rgba(0,225,255,.08);
}

.loader-title span{

background:
linear-gradient(
135deg,
#00e1ff,
#7b2dff,
#ff007f
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.loader-sub{

font-size:.9rem;
font-weight:600;

letter-spacing:2.5px;
text-transform:uppercase;

color:
rgba(255,255,255,.58);

margin-bottom:34px;
}

/* =========================================================
📊 PROGRESS
========================================================= */

.loader-progress{
width:100%;
}

.loader-progress-track{

position:relative;

height:9px;

overflow:hidden;

border-radius:999px;

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.06);

backdrop-filter:blur(6px);
}

.loader-progress-fill{

width:0%;
height:100%;

border-radius:999px;

background:
linear-gradient(
90deg,
#00e1ff,
#7b2dff,
#ff007f
);

box-shadow:
0 0 18px rgba(0,225,255,.45);

transition:
width .22s ease;

will-change:width;
}

.loader-shine{

position:absolute;
top:0;
left:-40%;

width:40%;
height:100%;

background:
linear-gradient(
90deg,
transparent,
rgba(255,255,255,.4),
transparent
);

animation:
shine 1.8s linear infinite;

pointer-events:none;
}

@keyframes shine{

to{
left:140%;
}
}

/* =========================================================
🔢 PERCENT
========================================================= */

.loader-percent{

margin-top:14px;

font-size:1rem;
font-weight:800;

color:#fff;

letter-spacing:.5px;
}

/* =========================================================
⚡ STATUS
========================================================= */

.loader-status{

margin-top:28px;

display:flex;
align-items:center;
justify-content:center;

gap:10px;

flex-wrap:wrap;

font-size:.84rem;

color:
rgba(255,255,255,.65);
}

.status-dot{

width:10px;
height:10px;

border-radius:50%;

background:#00e1ff;

box-shadow:
0 0 14px #00e1ff;

animation:
dotPulse 1s infinite;

flex-shrink:0;
}

@keyframes dotPulse{

0%,100%{
transform:scale(1);
opacity:1;
}

50%{
transform:scale(.65);
opacity:.45;
}
}

/* =========================================================
🔥 HEADER
========================================================= */

.header{

position:fixed;
top:0;
left:0;
right:0;

height:80px;

display:flex;
align-items:center;
justify-content:space-between;

padding:0 28px;

background:
rgba(5,8,22,.74);

backdrop-filter:blur(24px);

border-bottom:
1px solid rgba(255,255,255,.05);

z-index:300;
}

.logo{

display:flex;
align-items:center;

gap:14px;
}

.logo img{

width:56px;
height:56px;

border-radius:20px;

object-fit:cover;
}

.logo h1{

font-size:28px;
font-weight:800;
}

.back-btn{

width:50px;
height:50px;

border:none;
outline:none;

cursor:pointer;

border-radius:18px;

display:flex;
align-items:center;
justify-content:center;

background:
rgba(255,255,255,.06);

transition:
transform .25s ease,
background .25s ease;
}

.back-btn:hover{

transform:translateY(-3px);

background:
rgba(255,255,255,.12);
}

.back-btn svg{

width:26px;
height:26px;

fill:#fff;
}

/* =========================================================
📦 MAIN
========================================================= */

.main{

width:100%;
max-width:1450px;

margin:auto;

padding:
120px
28px
80px;
}

/* =========================================================
🎯 HERO
========================================================= */

.hero{

position:relative;

overflow:hidden;

padding:60px 50px;

border-radius:42px;

margin-bottom:50px;

text-align:center;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:
1px solid var(--line);

backdrop-filter:blur(24px);

box-shadow:var(--shadow);
}

.hero::before{

content:"";

position:absolute;

top:-120px;
right:-120px;

width:260px;
height:260px;

border-radius:50%;

background:
radial-gradient(
circle,
rgba(123,97,255,.28),
transparent 70%
);
}

.hero-badge{

display:inline-flex;
align-items:center;
gap:10px;

padding:
12px 18px;

border-radius:999px;

margin-bottom:24px;

background:
rgba(255,255,255,.06);

border:
1px solid rgba(255,255,255,.08);

font-size:14px;
font-weight:700;
}

.hero-badge span{

width:10px;
height:10px;

border-radius:50%;

background:#00d9ff;

box-shadow:
0 0 12px #00d9ff;
}

.hero h2{

font-size:64px;
font-weight:900;

line-height:1.05;

margin-bottom:18px;
}

.hero p{

max-width:760px;

margin:auto;

font-size:16px;

line-height:1.9;

color:var(--muted);
}

/* =========================================================
✨ BENEFITS
========================================================= */

.benefits{

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(260px,1fr));

gap:24px;

margin-bottom:55px;
}

.benefit-card{

position:relative;

overflow:hidden;

padding:28px;

border-radius:30px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.05),
rgba(255,255,255,.02)
);

border:
1px solid var(--line);

backdrop-filter:blur(20px);

transition:
transform .3s ease,
border .3s ease,
box-shadow .3s ease;
}

.benefit-card:hover{

transform:translateY(-8px);

border-color:
rgba(123,97,255,.35);

box-shadow:
0 20px 45px rgba(0,0,0,.35);

background:var(--cardHover);
}

.benefit-icon{

width:72px;
height:72px;

display:flex;
align-items:center;
justify-content:center;

margin-bottom:20px;

border-radius:24px;

font-size:32px;

background:
linear-gradient(
135deg,
#00d9ff,
#7b61ff
);

box-shadow:
0 18px 35px rgba(123,97,255,.24);
}

.benefit-card h3{

font-size:22px;
font-weight:800;

margin-bottom:10px;
}

.benefit-card p{

font-size:14px;

line-height:1.7;

color:var(--muted);
}

/* =========================================================
💎 PLAN GRID
========================================================= */

.plan-grid{

display:grid;

grid-template-columns:
repeat(auto-fit,minmax(320px,1fr));

gap:30px;

margin-bottom:55px;
}

.plan-card{

position:relative;

overflow:hidden;

padding:38px 34px;

border-radius:38px;

background:
linear-gradient(
180deg,
rgba(255,255,255,.06),
rgba(255,255,255,.02)
);

border:
1px solid rgba(255,255,255,.08);

backdrop-filter:blur(24px);

box-shadow:
0 25px 70px rgba(0,0,0,.4);

transition:
transform .3s ease,
border .3s ease,
box-shadow .3s ease;
}

.plan-card:hover{

transform:translateY(-10px);

border-color:
rgba(0,217,255,.28);

box-shadow:
0 30px 80px rgba(0,0,0,.5);
}

.plan-card.popular{

border:
1px solid rgba(0,217,255,.35);
}

.plan-top{

display:flex;
align-items:center;
justify-content:space-between;

margin-bottom:20px;
}

.plan-name{

font-size:24px;
font-weight:800;
}

.plan-badge{

padding:
8px 12px;

border-radius:999px;

font-size:11px;
font-weight:800;

background:
linear-gradient(
135deg,
#00d9ff,
#7b61ff
);
}

.plan-price{

font-size:64px;
font-weight:900;

line-height:1;

margin-bottom:8px;

background:
linear-gradient(
135deg,
#00d9ff,
#7b61ff,
#ff0077
);

-webkit-background-clip:text;
-webkit-text-fill-color:transparent;
}

.plan-price small{

font-size:18px;
font-weight:700;
}

.plan-desc{

font-size:14px;

line-height:1.8;

color:var(--muted);

margin-bottom:28px;
}

.plan-features{

display:flex;
flex-direction:column;
gap:16px;

margin-bottom:34px;
}

.plan-features li{

list-style:none;

display:flex;
align-items:center;
gap:12px;

font-size:14px;

color:#dbe4ff;
}

.plan-features li::before{

content:"✔";

width:24px;
height:24px;

display:flex;
align-items:center;
justify-content:center;

border-radius:50%;

font-size:12px;
font-weight:800;

background:
linear-gradient(
135deg,
#00d9ff,
#7b61ff
);
}

.buy-btn{

width:100%;
height:60px;

border:none;
outline:none;

cursor:pointer;

border-radius:22px;

font-size:16px;
font-weight:800;

color:#fff;

background:
linear-gradient(
135deg,
#00d9ff,
#7b61ff,
#ff0077
);

background-size:300%;

transition:
transform .25s ease,
box-shadow .25s ease,
background-position .4s ease;
}

.buy-btn:hover{

transform:translateY(-4px);

background-position:right;

box-shadow:
0 20px 40px rgba(123,97,255,.35);
}

/* =========================================================
💳 PAYMENT
========================================================= */

.payment{

padding:34px;

border-radius:34px;

text-align:center;

background:
linear-gradient(
135deg,
rgba(123,97,255,.18),
rgba(0,217,255,.12)
);

border:
1px solid rgba(255,255,255,.06);

margin-bottom:50px;
}

.payment h3{

font-size:28px;
font-weight:800;

margin-bottom:14px;
}

.payment p{

font-size:15px;

line-height:1.9;

color:#d4ddf5;
}

/* =========================================================
⚡ FOOTER
========================================================= */

.footer{

text-align:center;

font-size:14px;

color:#7383aa;
}

/* =========================================================
📱 MOBILE SMALL
========================================================= */

@media screen and (max-width:360px){

#loader-screen{
padding:14px;
}

.loader-content{
width:100%;
}

.loader-circle{
width:125px;
height:125px;
margin-bottom:24px;
}

.circle-core{
inset:16px;
}

.loader-logo{
width:58px;
height:58px;
}

.ring2{
inset:9px;
}

.loader-title{
font-size:1.8rem;
}

.loader-sub{
font-size:.68rem;
letter-spacing:1.4px;
margin-bottom:26px;
}

.loader-progress-track{
height:7px;
}

.loader-percent{
font-size:.82rem;
}

.loader-status{
font-size:.68rem;
margin-top:22px;
}

.status-dot{
width:8px;
height:8px;
}

.hero h2{
font-size:28px;
}

.plan-grid{
grid-template-columns:1fr;
}

}

/* =========================================================
📱 MOBILE
========================================================= */

@media screen and (min-width:361px)
and (max-width:600px){

.loader-content{
width:min(94vw,340px);
}

.loader-circle{
width:150px;
height:150px;
}

.circle-core{
inset:18px;
}

.loader-logo{
width:72px;
height:72px;
}

.loader-title{
font-size:2.1rem;
}

.loader-sub{
font-size:.76rem;
letter-spacing:2px;
}

.loader-progress-track{
height:8px;
}

.loader-percent{
font-size:.92rem;
}

.loader-status{
font-size:.74rem;
}

}

/* =========================================================
📱 ANDROID
========================================================= */

@media screen
and (max-width:920px)
and (pointer:coarse)
and (-webkit-min-device-pixel-ratio:1){

.header{

height:72px;

padding:0 18px;
}

.logo img{

width:50px;
height:50px;
}

.logo h1{

font-size:22px;
}

.back-btn{

width:44px;
height:44px;
}

.main{

padding:
100px
18px
45px;
}

.hero{

padding:36px 24px;

border-radius:30px;
}

.hero h2{

font-size:40px;
}

.hero p{

font-size:14px;
}

.benefits{

grid-template-columns:
1fr 1fr;

gap:16px;
}

.benefit-card{

padding:20px;

border-radius:24px;
}

.benefit-icon{

width:60px;
height:60px;

font-size:26px;
}

.plan-grid{

grid-template-columns:
1fr;

gap:22px;
}

.plan-card{

padding:28px 24px;

border-radius:28px;
}

.plan-price{

font-size:52px;
}

.buy-btn{

height:56px;

font-size:15px;
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
env(safe-area-inset-top);

padding-bottom:
env(safe-area-inset-bottom);
}

.header{

height:68px;

padding:0 14px;
}

.logo{

gap:10px;
}

.logo img{

width:46px;
height:46px;

border-radius:16px;
}

.logo h1{

font-size:20px;
}

.back-btn{

width:42px;
height:42px;

border-radius:14px;
}

.main{

padding:
90px
14px
38px;
}

.hero{

padding:28px 18px;

border-radius:24px;
}

.hero-badge{

padding:10px 14px;

font-size:12px;
}

.hero h2{

font-size:30px;
}

.hero p{

font-size:13px;

line-height:1.7;
}

.benefits{

grid-template-columns:
1fr;

gap:14px;
}

.benefit-card{

padding:18px;

border-radius:22px;
}

.benefit-icon{

width:54px;
height:54px;

font-size:22px;

border-radius:18px;
}

.benefit-card h3{

font-size:18px;
}

.benefit-card p{

font-size:12px;
}

.plan-card{

padding:24px 18px;

border-radius:24px;
}

.plan-top{

flex-direction:column;
align-items:flex-start;

gap:10px;
}

.plan-name{

font-size:20px;
}

.plan-price{

font-size:44px;
}

.plan-price small{

font-size:15px;
}

.plan-desc{

font-size:13px;
}

.plan-features li{

font-size:13px;
}

.buy-btn{

height:54px;

font-size:14px;

border-radius:18px;
}

.payment{

padding:22px;

border-radius:24px;
}

.payment h3{

font-size:22px;
}

.payment p{

font-size:13px;
}

.footer{

font-size:12px;
}

}

/* =========================================================
📱 TABLET
========================================================= */

@media screen
and (min-width:768px)
and (max-width:1023px){

.loader-content{
width:min(78vw,500px);
}

.loader-circle{
width:200px;
height:200px;
}

.circle-core{
inset:24px;
}

.loader-logo{
width:100px;
height:100px;
}

.loader-title{
font-size:3.2rem;
}

.loader-sub{
font-size:1rem;
}

.loader-progress-track{
height:11px;
}

.loader-percent{
font-size:1.15rem;
}

.loader-status{
font-size:.95rem;
}

}

/* =========================================================
💻 PC
========================================================= */

@media(min-width:1200px){

.main{

max-width:1550px;

padding:
130px
60px
90px;
}

.hero{

padding:70px 70px;

border-radius:46px;
}

.hero h2{

font-size:76px;
}

.hero p{

max-width:900px;

font-size:17px;
}

.plan-grid{

grid-template-columns:
repeat(3,1fr);

gap:34px;
}

.plan-card{

padding:44px 40px;
}

.plan-price{

font-size:74px;
}

.buy-btn{

height:64px;

font-size:18px;
}

.loader-content{
width:min(32vw,500px);
}

.loader-circle{
width:210px;
height:210px;
margin-bottom:40px;
}

.circle-core{
inset:24px;
}

.loader-logo{
width:105px;
height:105px;
}

.loader-title{
font-size:3.5rem;
letter-spacing:1px;
}

.loader-sub{
font-size:1rem;
letter-spacing:3px;
}

.loader-progress-track{
height:11px;
}

.loader-percent{
font-size:1.15rem;
}

.loader-status{
font-size:.95rem;
margin-top:32px;
}

.loader-glow{
width:620px;
height:620px;
}

}

/* =========================================================
🖥 PC GRANDES
========================================================= */

@media screen
and (min-width:1440px){

.loader-content{
width:min(28vw,560px);
}

.loader-circle{
width:240px;
height:240px;
}

.circle-core{
inset:28px;
}

.loader-logo{
width:120px;
height:120px;
}

.loader-title{
font-size:4rem;
}

.loader-sub{
font-size:1.1rem;
}

.loader-progress-track{
height:12px;
}

.loader-percent{
font-size:1.3rem;
}

.loader-status{
font-size:1rem;
}

.loader-glow{
width:760px;
height:760px;
}

}

</style>

</head>

<body>

<!-- =========================================================
🌌 PREMIUM LOADER
========================================================= -->

<div id="loader-screen">

<div class="loader-bg"></div>
<div class="loader-particles"></div>
<div class="loader-glow"></div>

<div class="loader-content">

<div class="loader-circle">

<div class="circle-ring ring1"></div>
<div class="circle-ring ring2"></div>

<div class="circle-core">

<img
src="../Logo/Logo Nuevo.png"
alt="MovieTx"
class="loader-logo"
draggable="false">

</div>

</div>

<h1 class="loader-title">
Movie<span>Tx</span>
</h1>

<p class="loader-sub">
Streaming Experience
</p>

<div class="loader-progress">

<div class="loader-progress-track">

<div
class="loader-progress-fill"
id="loading-fill">
</div>

<div class="loader-shine"></div>

</div>

<div
class="loader-percent"
id="loading-percent">
0%
</div>

</div>

<div class="loader-status">

<span class="status-dot"></span>

<span
class="loader-message"
id="loader-message">
Inicializando sistema
</span>

</div>

</div>

</div>

<!-- =========================================================
🔥 HEADER
========================================================= -->

<header class="header">

<div class="logo">

<img
src="../Logo/Logo Nuevo.png"
alt="MovieTx">

<h1>MovieTx</h1>

</div>

<button
class="back-btn"
onclick="history.back()">

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
<path d="M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
</svg>

</button>

</header>

<!-- =========================================================
📦 MAIN
========================================================= -->

<main class="main">

<section class="hero">

<div class="hero-badge">
<span></span>
Streaming Premium
</div>

<h2>
La mejor experiencia<br>
para ver películas
</h2>

<p>
Accede al catálogo completo de MovieTx con estrenos constantes,
streaming optimizado y compatibilidad total para Android,
iPhone, Smart TV y PC.
</p>

</section>

<!-- BENEFITS -->

<section class="benefits">

<div class="benefit-card">

<div class="benefit-icon">
🎬
</div>

<h3>
Estrenos semanales
</h3>

<p>
Nuevas películas y contenido actualizado constantemente.
</p>

</div>

<div class="benefit-card">

<div class="benefit-icon">
⚡
</div>

<h3>
Streaming rápido
</h3>

<p>
Carga optimizada y reproducción fluida en cualquier dispositivo.
</p>

</div>

<div class="benefit-card">

<div class="benefit-icon">
🔓
</div>

<h3>
Acceso ilimitado
</h3>

<p>
Disfruta todo el catálogo sin restricciones ni anuncios.
</p>

</div>

</section>

<!-- PLAN GRID -->

<section class="plan-grid">

<div class="plan-card">

<div class="plan-top">

<div class="plan-name">
Básico
</div>

<div class="plan-badge">
POPULAR
</div>

</div>

<div class="plan-price">
$2500
<small>ARS</small>
</div>

<div class="plan-desc">
1 mes completo + 5 días adicionales incluidos.
</div>

<ul class="plan-features">

<li>Acceso completo al catálogo</li>

<li>Películas HD y Full HD</li>

<li>Compatible con Android y iPhone</li>

<li>Estrenos semanales</li>

</ul>

<button class="buy-btn">
Comprar acceso
</button>

</div>

<div class="plan-card popular">

<div class="plan-top">

<div class="plan-name">
Estándar
</div>

<div class="plan-badge">
RECOMENDADO
</div>

</div>

<div class="plan-price">
$4500
<small>ARS</small>
</div>

<div class="plan-desc">
2 meses completos + prioridad de activación.
</div>

<ul class="plan-features">

<li>Acceso ilimitado</li>

<li>Streaming optimizado</li>

<li>Compatible con Smart TV</li>

<li>Mayor velocidad de soporte</li>

</ul>

<button class="buy-btn">
Comprar acceso
</button>

</div>

<div class="plan-card">

<div class="plan-top">

<div class="plan-name">
Premium
</div>

<div class="plan-badge">
FULL
</div>

</div>

<div class="plan-price">
$7000
<small>ARS</small>
</div>

<div class="plan-desc">
3 meses completos + beneficios exclusivos.
</div>

<ul class="plan-features">

<li>Máxima calidad disponible</li>

<li>Acceso anticipado a estrenos</li>

<li>Compatible con todos los dispositivos</li>

<li>Experiencia premium completa</li>

</ul>

<button class="buy-btn">
Comprar acceso
</button>

</div>

</section>

<!-- PAYMENT -->

<section class="payment">

<h3>
Métodos de pago
</h3>

<p>
Mercado Pago · Ualá · Rapipago · Naranja X<br>
Enviar comprobante + nombre del comprador para activar el acceso automáticamente.
</p>

</section>

<!-- FOOTER -->

<footer class="footer">

MovieTx © 2026 · Premium Streaming Experience

</footer>

</main>

<!-- =========================================================
⚡ LOADER SCRIPT
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", () => {

const loader =
document.getElementById("loader-screen");

const fill =
document.getElementById("loading-fill");

const percent =
document.getElementById("loading-percent");

const message =
document.getElementById("loader-message");

if(
!loader ||
!fill ||
!percent
) return;

/* =========================================
🔒 LOCK BODY
========================================= */

document.body.classList.add("loading");

/* =========================================
⚡ STATUS TEXTS
========================================= */

const texts = [

"Inicializando sistema",
"Cargando catálogo",
"Preparando películas",
"Optimizando experiencia",
"Finalizando carga"

];

let textIndex = 0;

const textInterval =
setInterval(() => {

textIndex =
(textIndex + 1) % texts.length;

if(message){

message.textContent =
texts[textIndex];

}

}, 900);

/* =========================================
📊 PROGRESS
========================================= */

let progress = 0;
let finished = false;

function updateProgress(value){

progress =
Math.min(100, value);

fill.style.width =
progress + "%";

percent.textContent =
Math.floor(progress) + "%";

}

/* progreso suave */

const progressInterval =
setInterval(() => {

if(progress < 88){

progress +=
Math.random() * 4;

updateProgress(progress);

}

}, 120);

/* =========================================
✅ FINISH
========================================= */

function finishLoader(){

if(finished) return;

finished = true;

clearInterval(progressInterval);
clearInterval(textInterval);

const final =
setInterval(() => {

if(progress < 100){

progress += 2.5;

updateProgress(progress);

}else{

clearInterval(final);

setTimeout(() => {

loader.classList.add("hidden");

document.body.classList.remove("loading");

setTimeout(() => {

loader.remove();

}, 900);

}, 250);

}

}, 18);

}

/* =========================================
🚀 WINDOW LOAD
========================================= */

window.addEventListener("load", () => {

setTimeout(() => {

finishLoader();

}, 300);

});

/* fallback */

setTimeout(() => {

finishLoader();

}, 5000);

});

</script>

</body>
</html>