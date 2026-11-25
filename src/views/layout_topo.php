<?php
$TITULOS = array('visao' => 'Diagnóstico do suporte', 'atendentes' => 'Atendentes', 'fila' => 'Auditoria de notas baixas');
$sync = ultimaSync();
$icone = array(
    'visao'      => '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
    'atendentes' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19c.8-3 3-4.6 5.5-4.6s4.7 1.6 5.5 4.6"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18.5 19c-.3-1.6-1-2.8-2-3.7"/>',
    'fila'       => '<path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z"/><path d="M8 6H6a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2"/><path d="m9 13 2 2 4-4"/>',
);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($TITULOS[$pagina]) ?> · Auditoria do Suporte</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Serif:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<style>
:root{
  --tinta:#0f1d33; --tinta-2:#4b5a70; --tinta-3:#78859a; --papel:#f4f6fa; --cartao:#ffffff; --linha:#e2e7ef; --linha-2:#edf1f7;
  --pri:#1b64be; --pri-2:#14498f; --pri-claro:#e8f0fb; --pri-foco:#b6d5f8;
  --vermelho:#d6453d; --vermelho-2:#a2312a; --vermelho-claro:#fbe9e7;
  --ambar:#9a6412; --ambar-claro:#fbf1dc; --laranja:#e07a2e; --violeta:#5b4bc4; --violeta-claro:#efedfb;
  --serif:"IBM Plex Serif",Georgia,"Times New Roman",serif; --sans:"IBM Plex Sans",system-ui,-apple-system,"Segoe UI",sans-serif;
}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--sans);font-size:14px;line-height:1.5;color:var(--tinta);background:var(--papel);-webkit-font-smoothing:antialiased}
a{color:var(--pri-2)}
:focus-visible{outline:3px solid var(--pri-foco);outline-offset:2px;border-radius:4px}
.num,.tab{font-variant-numeric:tabular-nums}

/* ---------- estrutura ---------- */
.app{display:grid;grid-template-columns:236px minmax(0,1fr);min-height:100vh;background:linear-gradient(to right,var(--tinta) 236px,var(--papel) 236px)}
.lateral{background:var(--tinta);color:#c6d1e3;padding:22px 16px;display:flex;flex-direction:column;gap:28px;position:sticky;top:0;height:100vh}
.marca{display:flex;gap:11px;align-items:center;padding:0 6px;color:#fff;text-decoration:none}
.marca .sel{width:34px;height:34px;border-radius:9px;background:var(--pri);display:grid;place-items:center;flex:none}
.marca b{display:block;font-weight:600;font-size:14.5px;line-height:1.25}
.marca small{display:block;color:#8b9bb5;font-size:12px}
.nav{display:flex;flex-direction:column;gap:2px}
.nav a{display:flex;align-items:center;gap:11px;padding:10px 10px;border-radius:8px;color:#c6d1e3;text-decoration:none;font-weight:500}
.nav a svg{flex:none;opacity:.8}
.nav a:hover{background:rgba(255,255,255,.06);color:#fff}
.nav a.ativa{background:rgba(51,140,237,.22);color:#fff}
.nav a.ativa svg{opacity:1}
.nav .badge{margin-left:auto;background:var(--vermelho);color:#fff;font-size:11.5px;font-weight:600;border-radius:999px;padding:1px 8px}
.lateral .pe{margin-top:auto;font-size:12.5px;color:#8b9bb5;padding:0 6px;display:flex;flex-direction:column;gap:10px}
.lateral .pe .eu{display:flex;align-items:center;gap:10px;color:#e6ecf5}
.lateral .pe .avatar{width:30px;height:30px;border-radius:50%;background:#22355a;display:grid;place-items:center;font-weight:600;font-size:13px;color:#fff}
.lateral .pe a{color:#c6d1e3;text-decoration:none}.lateral .pe a:hover{color:#fff;text-decoration:underline}
.vivo{display:inline-block;width:7px;height:7px;border-radius:50%;background:#3fc39f;margin-right:6px;box-shadow:0 0 0 3px rgba(63,195,159,.18)}

.principal{padding:28px 36px 64px;min-width:0}
.cabeca{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:18px}
.cabeca h1{font-family:var(--serif);font-weight:500;font-size:28px;letter-spacing:-.3px;margin:0;line-height:1.15}
.cabeca .periodo{color:var(--tinta-2);margin-top:4px}

/* ---------- filtros ---------- */
.filtros{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:26px;padding-bottom:18px;border-bottom:1px solid var(--linha)}
.seg{display:inline-flex;background:#e8edf4;border-radius:9px;padding:3px}
.seg a{padding:6px 12px;border-radius:7px;color:var(--tinta-2);font-weight:500;font-size:13px;text-decoration:none}
.seg a:hover{color:var(--tinta)}
.seg a.on{background:#fff;color:var(--tinta);box-shadow:0 1px 2px rgba(16,35,31,.12)}
.campo{appearance:none;-webkit-appearance:none;padding:7px 30px 7px 11px;border:1px solid #d3dae5;border-radius:8px;font:inherit;font-size:13px;color:var(--tinta);max-width:210px;
  background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' fill='none' stroke='%234d5d58' stroke-width='1.5'/%3E%3C/svg%3E") no-repeat right 11px center}
input[type=date].campo{padding-right:10px;background-image:none}
.campo:hover{border-color:#b4bfce}
.filtros .comp{margin-left:auto;color:var(--tinta-3);font-size:12.5px}
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:8px;border:1px solid #d3dae5;background:#fff;font:500 13px var(--sans);color:var(--tinta);cursor:pointer;text-decoration:none;white-space:nowrap}
.btn:hover{border-color:#b4bfce;background:#fafbfd}
.btn.pri{background:var(--pri);border-color:var(--pri);color:#fff}.btn.pri:hover{background:var(--pri-2)}
.aviso{background:var(--pri-claro);color:var(--pri-2);border-radius:9px;padding:11px 15px;margin-bottom:20px;font-weight:500;display:flex;gap:8px;align-items:center}

/* ---------- blocos ---------- */
.painel{background:var(--cartao);border:1px solid var(--linha);border-radius:12px;padding:20px 22px;min-width:0}
.painel h3{font-size:14.5px;font-weight:600;margin:0}
.painel .leg{margin:2px 0 16px;color:var(--tinta-3);font-size:12.5px}
.grade{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}
.c12{grid-column:span 12}.c8{grid-column:span 8}.c7{grid-column:span 7}.c6{grid-column:span 6}.c5{grid-column:span 5}.c4{grid-column:span 4}
.graf{position:relative;height:240px}
.vazio{color:var(--tinta-3);padding:28px 0;text-align:center}
.titulo-secao{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin:40px 0 14px;flex-wrap:wrap}
.titulo-secao h2{font-family:var(--serif);font-weight:500;font-size:21px;margin:0;letter-spacing:-.2px}
.titulo-secao p{margin:0;color:var(--tinta-3);font-size:13px}

/* barras horizontais */
.hbar{display:grid;grid-template-columns:minmax(120px,52%) 1fr 40px;gap:9px 12px;align-items:center;font-size:13px}
.hbar .t{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hbar .b{height:10px;background:var(--linha-2);border-radius:5px;overflow:hidden}
.hbar .b i{display:block;height:100%;background:var(--pri);border-radius:5px}
.hbar .n{text-align:right;color:var(--tinta-2);font-variant-numeric:tabular-nums}

.tag{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:500;background:var(--linha-2);color:var(--tinta-2);white-space:nowrap}
.tag.cat{background:var(--pri-claro);color:var(--pri-2)}
.tag.g-alta{background:var(--vermelho-claro);color:var(--vermelho-2)}
.tag.g-media{background:var(--ambar-claro);color:var(--ambar)}
.tag .pt{width:6px;height:6px;border-radius:50%;background:currentColor}
.nota{width:34px;height:34px;flex:none;border-radius:9px;display:grid;place-items:center;font-weight:600;font-size:15px;color:#fff;background:var(--vermelho)}
.nota.n2{background:#e07b74}.nota.n3{background:#9aa5b6}.nota.n4{background:#84b9f3}.nota.n5{background:var(--pri)}

@media (max-width:1180px){.c8,.c7,.c6,.c5,.c4{grid-column:span 12}}
@media (max-width:860px){
  .app{grid-template-columns:1fr;background:var(--papel)}
  .lateral{position:static;height:auto;flex-direction:row;flex-wrap:wrap;align-items:center;gap:12px;padding:14px 16px}
  .nav{flex-direction:row;flex-wrap:wrap}.lateral .pe{display:none}
  .principal{padding:20px 16px 48px}
}

/* ---------- ferramentas do cabeçalho ---------- */
.ferramentas{display:flex;gap:8px;align-items:center}
.btn.ic{padding:8px 10px}
.btn svg{flex:none}

/* ---------- entrada da página (um único momento) ---------- */
@keyframes entrar{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.anima .diag,.anima .faixa,.anima .grade>.painel,.anima .titulo-secao,.anima .destaques>*,.anima .resumo-fila,.anima .item,.anima .principal>.painel{opacity:0;animation:entrar .55s cubic-bezier(.2,.7,.2,1) forwards;animation-delay:calc(var(--i,0) * 55ms + 60ms)}
.painel,.dest,.item,.ind{transition:border-color .2s ease,box-shadow .2s ease,background-color .2s ease}
.grade>.painel:hover,.dest:hover{border-color:#c6cfdc;box-shadow:0 4px 18px -8px rgba(15,29,51,.18)}
.ind:hover{background:#fafbfd}
.btn{transition:background-color .15s ease,border-color .15s ease,transform .1s ease}
.btn:active{transform:translateY(1px)}

/* ---------- toast ---------- */
.toast{position:fixed;right:24px;bottom:24px;z-index:60;background:var(--tinta);color:#fff;border-radius:10px;padding:12px 16px;display:flex;gap:9px;align-items:center;font-weight:500;box-shadow:0 12px 32px -10px rgba(15,29,51,.45);animation:toastIn .35s cubic-bezier(.2,.7,.2,1),toastOut .4s ease 4.2s forwards}
.toast svg{color:#6ee7b7}
@keyframes toastIn{from{opacity:0;transform:translateY(12px)}}
@keyframes toastOut{to{opacity:0;transform:translateY(8px);visibility:hidden}}

/* ---------- tour guiado ---------- */
.tour-foco{position:fixed;z-index:80;border-radius:14px;box-shadow:0 0 0 4px rgba(255,255,255,.9),0 0 0 9999px rgba(15,29,51,.58);pointer-events:none;transition:top .38s cubic-bezier(.2,.7,.2,1),left .38s cubic-bezier(.2,.7,.2,1),width .38s cubic-bezier(.2,.7,.2,1),height .38s cubic-bezier(.2,.7,.2,1)}
.tour-bloqueio{position:fixed;inset:0;z-index:79}
.tour-pop{position:fixed;z-index:81;width:min(360px,calc(100vw - 32px));background:#fff;border-radius:12px;padding:18px 20px 16px;box-shadow:0 24px 60px -16px rgba(15,29,51,.5);transition:top .38s cubic-bezier(.2,.7,.2,1),left .38s cubic-bezier(.2,.7,.2,1),opacity .2s;opacity:0}
.tour-pop.on{opacity:1}
.tour-pop .passo{color:var(--tinta-3);font-size:12px;margin-bottom:4px}
.tour-pop h4{font-family:var(--serif);font-weight:500;font-size:19px;margin:0 0 6px;line-height:1.25}
.tour-pop p{margin:0 0 16px;color:var(--tinta-2);line-height:1.55}
.tour-pop .bts{display:flex;align-items:center;gap:8px}
.tour-pop .pontos{display:flex;gap:5px;margin-right:auto}
.tour-pop .pontos i{width:6px;height:6px;border-radius:3px;background:#d3dae5;transition:width .25s,background-color .25s}
.tour-pop .pontos i.on{width:18px;background:var(--pri)}
.tour-pop .pular{background:none;border:0;color:var(--tinta-3);font:inherit;font-size:13px;cursor:pointer;padding:6px}
.tour-pop .pular:hover{color:var(--tinta)}

/* ---------- modo apresentação ---------- */
body.apresentar .lateral,body.apresentar .filtros,body.apresentar .ferramentas #btTour,body.apresentar .ferramentas #btImprimir{display:none}
body.apresentar .app{grid-template-columns:1fr;background:var(--papel)}
body.apresentar .principal{max-width:1500px;margin:0 auto;padding:36px 48px}
body.apresentar .cabeca h1{font-size:34px}
body.apresentar .diag-texto .frase{font-size:clamp(26px,2.4vw,34px)}
.sair-apresentacao{display:none}
body.apresentar .sair-apresentacao{display:inline-flex}

/* ---------- impressão / PDF ---------- */
@media print{
  @page{size:A4 landscape;margin:12mm}
  body{background:#fff;font-size:12px}
  .lateral,.filtros,.ferramentas,.toast,.tour-foco,.tour-pop,.avaliacao button,.acoes,.chips,.item-cab .btn{display:none!important}
  .app{display:block;background:#fff}
  .principal{padding:0}
  .painel,.diag,.faixa,.item,.dest,.resumo-fila{break-inside:avoid;box-shadow:none!important}
  .grade{gap:10px}
  .anima *{opacity:1!important;animation:none!important}
  .lacuna .trilho i{width:var(--w)!important}
  .graf{height:200px}
  .graf canvas{max-width:100%!important}
  .titulo-secao{break-after:avoid}
  .grade>.painel.c7,.grade>.painel.c5{break-inside:auto}.caso,.hbar{break-inside:avoid}
  .c8{grid-column:span 8!important}.c7{grid-column:span 7!important}.c6{grid-column:span 6!important}.c5{grid-column:span 5!important}.c4{grid-column:span 4!important}
  .diag{grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr)!important}.lacuna{border-left:1px solid var(--linha)!important;border-top:0!important}
  .faixa{grid-template-columns:repeat(6,minmax(0,1fr))!important}.ind{border-top:0!important;border-left:1px solid var(--linha)!important}.ind:first-child{border-left:0!important}
  .destaques{grid-template-columns:repeat(4,minmax(0,1fr))!important}
  .item-corpo{grid-template-columns:minmax(0,1.3fr) minmax(300px,1fr)!important}
  a{color:inherit;text-decoration:none}
}

@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<script>if (!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) document.body.classList.add('anima');</script>
<div class="app">
<aside class="lateral">
  <a class="marca" href="<?= h(url(array('p' => 'visao'), array('semana', 'status'))) ?>">
    <span class="sel"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-6 4 12 2-6h6"/></svg></span>
    <span><b>Auditoria do Suporte</b><small>iTalk, conta Suporte</small></span>
  </a>
  <nav class="nav" aria-label="Seções" data-tour="8" data-tour-titulo="Três visões" data-tour-texto="Diagnóstico para a visão geral, Atendentes para o desempenho de cada pessoa e Auditoria CSAT para o trabalho semanal do supervisor.">
    <?php foreach (array('visao' => 'Diagnóstico', 'atendentes' => 'Atendentes', 'fila' => 'Auditoria CSAT') as $k => $v): ?>
      <a href="<?= h(url(array('p' => $k), $k === 'fila' ? array() : array('semana', 'status'))) ?>" class="<?= $pagina === $k ? 'ativa' : '' ?>" <?= $pagina === $k ? 'aria-current="page"' : '' ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $icone[$k] ?></svg>
        <?= $v ?>
        <?php if ($k === 'fila' && $PEND_TOTAL): ?><span class="badge" title="<?= (int)$PEND_TOTAL ?> notas baixas aguardando avaliação"><?= (int)$PEND_TOTAL ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="pe">
    <?php if ($sync): ?><div><span class="vivo"></span>Dados de <?= date('d/m', strtotime($sync)) ?> às <?= date('H:i', strtotime($sync)) ?></div><?php endif; ?>
    <div class="eu"><span class="avatar"><?= h(strtoupper(substr($USUARIO, 0, 1))) ?></span><span><?= h(ucfirst($USUARIO)) ?><br><a href="<?= h($SELF) ?>?sair=1">Sair</a></span></div>
  </div>
</aside>

<main class="principal">
  <header class="cabeca">
    <div>
      <h1><?= h($TITULOS[$pagina]) ?></h1>
      <div class="periodo"><?= $pagina === 'fila' ? 'Notas de 1 a ' . (int)$corte . ' para o supervisor avaliar e enviar ao pós-vendas' : h($PERIODO_CURTO) . ', de ' . date('d/m/Y', strtotime($F['ini'])) . ' a ' . date('d/m/Y', strtotime($F['fim'])) ?></div>
    </div>
    <div class="ferramentas">
      <button type="button" class="btn" id="btTour" title="Conhecer esta tela (atalho: T)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.3a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.2-2.5 3.6"/><circle cx="12" cy="17" r=".6" fill="currentColor"/></svg>Tour guiado</button>
      <?php if ($pagina !== 'fila'): ?>
      <button type="button" class="btn" id="btApresentar" title="Tela cheia para reuniões (atalho: A)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/></svg>Apresentar</button>
      <?php endif; ?>
      <button type="button" class="btn ic" id="btImprimir" title="Imprimir ou salvar em PDF" aria-label="Imprimir ou salvar em PDF">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"><path d="M7 9V4h10v5"/><rect x="4" y="9" width="16" height="7" rx="1.5"/><path d="M7 14h10v6H7z"/></svg></button>
    </div>
  </header>

  <form class="filtros" method="get" id="filtros" data-tour="4" data-tour-titulo="Filtre como quiser" data-tour-texto="Período, atendente, time e caixa de entrada. Todos os números e gráficos da tela se ajustam ao filtro.">
    <input type="hidden" name="p" value="<?= h($pagina) ?>">
    <?php if ($pagina !== 'fila'): ?>
      <div class="seg" role="group" aria-label="Período">
        <?php foreach (array('7' => '7 dias', '30' => '30 dias', '90' => '90 dias', 'tudo' => 'Tudo') as $k => $v): ?>
          <a href="<?= h(url(array('periodo' => $k), array('ini', 'fim'))) ?>" class="<?= $F['periodo'] === (string)$k ? 'on' : '' ?>"><?= $v ?></a>
        <?php endforeach; ?>
      </div>
      <input class="campo" type="date" name="ini" value="<?= h($F['ini']) ?>" aria-label="Data inicial">
      <input class="campo" type="date" name="fim" value="<?= h($F['fim']) ?>" aria-label="Data final">
      <input type="hidden" name="periodo" value="custom" id="perCustom" disabled>
      <?php if ($F['periodo'] !== 'custom'): ?><input type="hidden" name="periodo" value="<?= h($F['periodo']) ?>"><?php endif; ?>
    <?php else: ?>
      <input type="hidden" name="semana" value="<?= h($semSel === '' ? 'todas' : $semSel) ?>">
      <?php if ($stSel): ?><input type="hidden" name="status" value="<?= h($stSel) ?>"><?php endif; ?>
    <?php endif; ?>
    <select class="campo" name="atendente" onchange="this.form.submit()" aria-label="Atendente">
      <option value="">Todos os atendentes</option>
      <?php foreach ($OPC['atendentes'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['atendente'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($pagina !== 'fila'): ?>
      <select class="campo" name="time" onchange="this.form.submit()" aria-label="Time">
        <option value="">Todos os times</option>
        <?php foreach ($OPC['times'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['time'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
      </select>
      <select class="campo" name="caixa" onchange="this.form.submit()" aria-label="Caixa de entrada">
        <option value="">Todas as caixas</option>
        <?php foreach ($OPC['caixas'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['caixa'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
      </select>
      <span class="comp">Comparado a <?= date('d/m', strtotime($F['ant_ini'])) ?>–<?= date('d/m', strtotime($F['ant_fim'])) ?></span>
    <?php endif; ?>
  </form>

  <?php if ($flash): ?><div class="toast" role="status"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 5 5 9-10"/></svg><?= h($flash) ?></div><?php endif; ?>
