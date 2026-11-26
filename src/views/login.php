<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · Auditoria do Suporte</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Serif:wght@400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{height:100%}
body{margin:0;min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr;grid-template-rows:minmax(100vh,auto);align-items:stretch;font-family:"IBM Plex Sans",system-ui,sans-serif;color:#0f1d33;background:#f4f6fa}
.lado{background:#0f1d33;color:#e6ecf5;padding:56px;min-height:100vh;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
.lado svg.pulso{position:absolute;left:0;right:0;bottom:22%;width:100%;height:120px;opacity:.5}
.marca{display:flex;align-items:center;gap:12px;font-weight:600;font-size:15px}
.marca span{width:34px;height:34px;border-radius:9px;background:#1b64be;display:grid;place-items:center}
.lado h1{font-family:"IBM Plex Serif",Georgia,serif;font-weight:400;font-size:clamp(28px,3.2vw,42px);line-height:1.15;margin:0;max-width:17ch;position:relative}
.lado p{color:#9cabc4;max-width:44ch;line-height:1.6;margin:0;position:relative}
.form{display:grid;place-items:center;padding:40px}
form{width:min(360px,100%)}
form h2{font-family:"IBM Plex Serif",Georgia,serif;font-weight:500;font-size:26px;margin:0 0 6px}
form .sub{color:#4b5a70;margin:0 0 28px}
label{display:block;font-size:13px;font-weight:500;margin:16px 0 6px;color:#0f1d33}
input{width:100%;padding:12px 14px;border:1px solid #cdd5e1;border-radius:8px;font:inherit;font-size:15px;background:#fff;color:#0f1d33}
input:focus{outline:3px solid #b6d5f8;border-color:#1b64be}
button{margin-top:26px;width:100%;padding:13px;border:0;border-radius:8px;background:#1b64be;color:#fff;font:600 15px "IBM Plex Sans",sans-serif;cursor:pointer}
button:hover{background:#14498f}button:focus-visible{outline:3px solid #b6d5f8;outline-offset:2px}
.erro{background:#fbe9e7;color:#a2312a;padding:11px 14px;border-radius:8px;font-size:14px;margin-bottom:4px}
@media (max-width:820px){body{grid-template-columns:1fr;grid-template-rows:auto 1fr}.lado{padding:32px;min-height:240px}.lado svg.pulso{bottom:0}}
</style>
</head>
<body>
<aside class="lado">
  <div class="marca"><span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg></span>Auditoria do Suporte</div>
  <svg class="pulso" viewBox="0 0 800 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 70 H260 L290 70 L310 20 L335 110 L360 45 L380 70 H800" fill="none" stroke="#1b64be" stroke-width="2.5"/></svg>
  <h1>O que acontece depois que a conversa é encerrada.</h1>
  <p>Indicadores do suporte do iTalk, notas de satisfação e a análise de cada atendimento mal avaliado.</p>
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
</main>
</body>
</html>
