<?php
// Traçado de ECG (onda P, complexo QRS e onda T), dois batimentos, linha de base em y=118
$ECG = 'M0 118 H210 C222 118 228 102 240 102 C252 102 258 118 270 118 H296 L308 130 L326 26 L346 172 L360 118 H392 '
     . 'C412 118 420 92 442 92 C464 92 472 118 492 118 H720 C732 118 738 102 750 102 C762 102 768 118 780 118 H806 '
     . 'L818 130 L836 26 L856 172 L870 118 H902 C922 118 930 92 952 92 C974 92 982 118 1002 118 H1200';
$URL_IMG = strtok($_SERVER['REQUEST_URI'], '?') . '?img=';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0a1c2c">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Auditoria">
<link rel="apple-touch-icon" href="<?= h($URL_IMG) ?>apple-touch-icon.png">
<title>Entrar · Auditoria do Suporte · iGUT Clínicas</title>
<link rel="icon" type="image/png" href="<?= h($URL_IMG) ?>favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Serif:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr;grid-template-rows:minmax(100vh,auto);align-items:stretch;font-family:"IBM Plex Sans",system-ui,sans-serif;color:#0c2133;background:#f4f6fa}

/* ---------- lado do monitor ---------- */
.lado{position:relative;overflow:hidden;min-height:100vh;padding:52px 56px 48px;display:flex;flex-direction:column;color:#e6ecf5;
  background:
    radial-gradient(900px 520px at 18% 62%, rgba(34,184,207,.16), transparent 62%),
    radial-gradient(700px 500px at 100% 0%, rgba(30,111,166,.35), transparent 60%),
    #0a1c2c}
/* grade de papel de eletrocardiograma, bem sutil */
.lado::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.55;
  background-image:
    linear-gradient(rgba(116,186,210,.07) 1px, transparent 1px),
    linear-gradient(90deg, rgba(116,186,210,.07) 1px, transparent 1px),
    linear-gradient(rgba(116,186,210,.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(116,186,210,.035) 1px, transparent 1px);
  background-size:80px 80px,80px 80px,16px 16px,16px 16px;
  -webkit-mask-image:radial-gradient(120% 90% at 30% 60%, #000 40%, transparent 85%);mask-image:radial-gradient(120% 90% at 30% 60%, #000 40%, transparent 85%)}
.topo{position:relative;display:flex;flex-direction:column;gap:14px}
.topo img{height:42px;width:auto;align-self:flex-start}
.produto{display:inline-flex;align-items:center;gap:9px;font-size:13px;color:#9cb6c9;letter-spacing:.2px}
.produto i{width:7px;height:7px;border-radius:50%;background:#22b8cf;box-shadow:0 0 0 0 rgba(34,184,207,.6);animation:vivo 2.4s ease-out infinite}
@keyframes vivo{0%{box-shadow:0 0 0 0 rgba(34,184,207,.55)}70%{box-shadow:0 0 0 9px rgba(34,184,207,0)}100%{box-shadow:0 0 0 0 rgba(34,184,207,0)}}
.lado h1{position:relative;margin:auto 0 0;font-family:"IBM Plex Serif",Georgia,serif;font-weight:400;font-size:clamp(30px,3.3vw,46px);line-height:1.13;letter-spacing:-.4px;max-width:15ch}
.lado p{position:relative;margin:18px 0 0;color:#9cb6c9;max-width:46ch;line-height:1.6}

/* ---------- traçado do ECG ---------- */
.ecg{position:relative;margin:34px -56px 0;height:190px}
.ecg svg{position:absolute;inset:0;width:100%;height:100%;overflow:visible}
.ecg .base{fill:none;stroke:rgba(116,186,210,.26);stroke-width:1.6;stroke-linejoin:round;stroke-linecap:round}
.ecg .rastro{fill:none;stroke:#22b8cf;stroke-width:7;stroke-linejoin:round;stroke-linecap:round;filter:url(#brilho);opacity:.75}
.ecg .meio{fill:none;stroke:#5fd6e6;stroke-width:3;stroke-linejoin:round;stroke-linecap:round;opacity:.9}
.ecg .nucleo{fill:none;stroke:#e9fcff;stroke-width:1.8;stroke-linejoin:round;stroke-linecap:round}
.ecg .ponta{fill:#f2feff}
.ecg .halo{fill:url(#halo)}

/* ---------- formulário ---------- */
.form{display:flex;flex-direction:column;justify-content:center;align-items:center;padding:40px 40px 28px;position:relative}
form{width:min(360px,100%);margin:auto 0}
form h2{font-family:"IBM Plex Serif",Georgia,serif;font-weight:500;font-size:28px;margin:0 0 6px;letter-spacing:-.2px}
form .sub{color:#4b5a70;margin:0 0 28px}
label{display:block;font-size:13px;font-weight:500;margin:16px 0 6px;color:#0c2133}
input{width:100%;padding:12px 14px;border:1px solid #cdd5e1;border-radius:9px;font:inherit;font-size:15px;background:#fff;color:#0c2133;transition:border-color .15s, box-shadow .15s}
input:hover{border-color:#b4bfce}
input:focus{outline:none;border-color:#1e6fa6;box-shadow:0 0 0 4px rgba(34,184,207,.18)}
button{margin-top:26px;width:100%;padding:13px;border:0;border-radius:9px;font:600 15px "IBM Plex Sans",sans-serif;color:#fff;cursor:pointer;
  background:linear-gradient(135deg,#1e6fa6 0%,#1a85b5 100%);box-shadow:0 8px 20px -10px rgba(30,111,166,.8);transition:transform .12s, box-shadow .2s, filter .2s}
button:hover{filter:brightness(1.06);box-shadow:0 10px 24px -10px rgba(30,111,166,.9)}
button:active{transform:translateY(1px)}
button:focus-visible{outline:3px solid #b2dbea;outline-offset:3px}
.erro{background:#fbe9e7;color:#a2312a;padding:11px 14px;border-radius:9px;font-size:14px;margin-bottom:4px;animation:tremer .35s}
@keyframes tremer{20%,60%{transform:translateX(-4px)}40%,80%{transform:translateX(4px)}}

/* ---------- marcas ---------- */
.marcas{display:flex;align-items:center;gap:26px;margin-top:36px}
.marcas img{display:block;width:auto;filter:grayscale(1);opacity:.55;transition:filter .25s, opacity .25s}
.marcas img:hover{filter:none;opacity:1}
.marcas .igut{height:26px}.marcas .gc{height:26px}.marcas .eba{height:30px}
.marcas .sep{width:1px;height:22px;background:#d3dae5}

@media (max-width:860px){
  body{grid-template-columns:1fr;grid-template-rows:auto 1fr}
  .lado{min-height:0;padding:calc(24px + env(safe-area-inset-top)) 24px 0}
  input{font-size:16px}
  .lado h1{margin-top:22px;font-size:28px}
  .lado p{display:none}
  .ecg{margin:8px -24px 0;height:120px}
  .form{padding:28px 24px}
}
@media (prefers-reduced-motion:reduce){.produto i{animation:none}.erro{animation:none}}
</style>
</head>
<body>
<aside class="lado">
  <div class="topo">
    <img src="<?= h($URL_IMG) ?>logo-igut-branca.png" alt="iGUT Clínicas" width="144" height="42">
    <span class="produto"><i aria-hidden="true"></i>Auditoria do Suporte</span>
  </div>
  <h1>O que acontece depois que a conversa é encerrada.</h1>
  <p>Indicadores do suporte do iTalk, satisfação dos clientes e a leitura de cada atendimento pela IA.</p>
  <div class="ecg" aria-hidden="true">
    <svg id="ecg" viewBox="0 0 1200 200" preserveAspectRatio="xMidYMid slice">
      <defs>
        <filter id="brilho" x="-20%" y="-60%" width="140%" height="220%"><feGaussianBlur stdDeviation="5"/></filter>
        <radialGradient id="halo"><stop offset="0" stop-color="#9ff0fb" stop-opacity=".95"/><stop offset=".35" stop-color="#22b8cf" stop-opacity=".45"/><stop offset="1" stop-color="#22b8cf" stop-opacity="0"/></radialGradient>
      </defs>
      <path class="base" d="<?= $ECG ?>"/>
      <!-- rastro: três camadas com a mesma ponta e comprimentos diferentes (pathLength 1000) -->
      <path class="rastro" d="<?= $ECG ?>" pathLength="1000" stroke-dasharray="230 1000" stroke-dashoffset="230">
        <animate attributeName="stroke-dashoffset" values="230;-840" dur="4.2s" repeatCount="indefinite"/></path>
      <path class="meio" d="<?= $ECG ?>" pathLength="1000" stroke-dasharray="120 1000" stroke-dashoffset="120">
        <animate attributeName="stroke-dashoffset" values="120;-950" dur="4.2s" repeatCount="indefinite"/></path>
      <path class="nucleo" d="<?= $ECG ?>" pathLength="1000" stroke-dasharray="46 1000" stroke-dashoffset="46">
        <animate attributeName="stroke-dashoffset" values="46;-1024" dur="4.2s" repeatCount="indefinite"/></path>
      <!-- ponta luminosa acompanhando a frente do pulso -->
      <g>
        <circle class="halo" r="22"/><circle class="ponta" r="3.2"/>
        <animateMotion dur="4.2s" repeatCount="indefinite" calcMode="linear" keyPoints="0;1;1" keyTimes="0;0.9346;1" path="<?= $ECG ?>"/>
        <animate attributeName="opacity" values="1;1;0;0" keyTimes="0;0.92;0.935;1" dur="4.2s" repeatCount="indefinite"/>
      </g>
    </svg>
  </div>
</aside>
<main class="form">
  <form method="post">
    <h2>Entrar</h2>
    <p class="sub">Use o acesso criado para você.</p>
    <?php if ($erro): ?><div class="erro" role="alert"><?= h($erro) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="login" value="1">
    <label for="u">Usuário</label><input id="u" name="usuario" autocomplete="username" required autofocus>
    <label for="s">Senha</label><input id="s" name="senha" type="password" autocomplete="current-password" required>
    <button type="submit">Entrar</button>
  </form>
  <div class="marcas">
    <img class="igut" src="<?= h($URL_IMG) ?>logo-igut.png" alt="iGUT Clínicas" width="89" height="26">
    <span class="sep" aria-hidden="true"></span>
    <img class="gc" src="<?= h($URL_IMG) ?>logo-gc.png" alt="GC" width="43" height="26">
    <span class="sep" aria-hidden="true"></span>
    <img class="eba" src="<?= h($URL_IMG) ?>logo-eba.png" alt="EBA" width="26" height="30">
  </div>
</main>
<script>
// Quem pede menos movimento no sistema vê o traçado parado, sem o pulso
if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
  var e = document.getElementById('ecg');
  if (e && e.pauseAnimations) { e.pauseAnimations(); }
  Array.prototype.forEach.call(document.querySelectorAll('.ecg .rastro,.ecg .meio,.ecg .nucleo,.ecg g'), function (n) { n.style.display = 'none'; });
}
</script>
</body>
</html>
