<?php
// séries para gráficos e minigráficos
$lbl = array(); $vol = array(); $pr = array(); $psr = array(); $p1 = array();
foreach ($sc as $r) {
    $lbl[] = rotuloBucket($r['b'], $F['grao']);
    $vol[] = (int)$r['conversas'];
    $pr[]  = $r['pct_resolvidas'] === null ? null : (float)$r['pct_resolvidas'];
    $psr[] = $r['pct_sem_reabrir'] === null ? null : (float)$r['pct_sem_reabrir'];
    $p1[]  = $r['mediana_1a'] === null ? null : (float)$r['mediana_1a'];
}
$lblS = array(); $med = array(); $resp = array();
foreach ($ss as $r) {
    $lblS[] = rotuloBucket($r['b'], $F['grao']);
    $med[]  = (float)$r['media'];
    $resp[] = (int)$r['respostas'];
}
$temSR = count(array_filter($psr, function ($x) { return $x !== null; })) > 0;
$grao  = $F['grao'] === 'dia' ? 'dia' : 'semana';

$lacuna = array(
    array('Encerradas no sistema', $taxaRes, num($kc['resolvidas']) . ' de ' . num($kc['conversas']) . ' conversas', 'l-sis'),
    array('Resolvidas sem reabrir', $kc['rastreadas'] ? $taxaSR : null, $kc['rastreadas'] ? num($kc['sem_reabrir']) . ' de ' . num($kc['rastreadas']) . ' acompanhadas' : 'em medição', 'l-real'),
    array('Clientes satisfeitos', $satisf, num($ks['satisfeitos']) . ' de ' . num($ks['respostas']) . ' avaliações com nota 4 ou 5', 'l-cli'),
);
?>
<style>
/* ---------- diagnóstico (abertura) ---------- */
.diag{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr);gap:0;background:var(--cartao);border:1px solid var(--linha);border-radius:14px;overflow:hidden;margin-bottom:16px}
.diag-texto{padding:30px 34px 28px}
.diag-texto .frase{font-family:var(--serif);font-weight:400;font-size:clamp(22px,2.15vw,29px);line-height:1.32;letter-spacing:-.25px;margin:0;max-width:30ch;text-wrap:pretty}
.diag-texto .frase + .frase{margin-top:10px}
.diag-texto ul{list-style:none;padding:0;margin:22px 0 0;display:flex;flex-direction:column;gap:7px;color:var(--tinta-2);max-width:62ch}
.diag-texto li{display:flex;gap:10px}
.diag-texto li::before{content:"";flex:none;width:5px;height:5px;border-radius:50%;background:var(--verde);margin-top:9px}
.lacuna{background:#f9fbfa;border-left:1px solid var(--linha);padding:28px 30px;display:flex;flex-direction:column}
.lacuna h2{font-size:13.5px;font-weight:600;margin:0 0 18px;color:var(--tinta-2)}
.lacuna .linha{margin-bottom:16px}
.lacuna .top{display:flex;justify-content:space-between;align-items:baseline;gap:12px}
.lacuna .top span{font-weight:500}
.lacuna .top b{font-size:22px;font-weight:600;letter-spacing:-.4px;font-variant-numeric:tabular-nums}
.lacuna .trilho{height:12px;background:#e7ecea;border-radius:6px;overflow:hidden;margin:7px 0 4px}
.lacuna .trilho i{display:block;height:100%;border-radius:6px;width:0;animation:crescer 1.1s cubic-bezier(.2,.7,.2,1) forwards}
.lacuna .l-sis i{background:#a9b4b0}.lacuna .l-real i{background:#5fae9f}.lacuna .l-cli i{background:var(--verde)}
.lacuna .linha:nth-child(3) .trilho i{animation-delay:.15s}.lacuna .linha:nth-child(4) .trilho i{animation-delay:.3s}
.lacuna small{color:var(--tinta-3);font-size:12px}
.lacuna .conclusao{margin-top:auto;padding-top:14px;border-top:1px dashed #d3dbd8;color:var(--tinta-2);font-size:13px}
.lacuna .conclusao b{color:var(--tinta)}
@keyframes crescer{to{width:var(--w)}}
@media (max-width:1100px){.diag{grid-template-columns:1fr}.lacuna{border-left:0;border-top:1px solid var(--linha)}}

/* ---------- faixa de indicadores ---------- */
.faixa{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));background:var(--cartao);border:1px solid var(--linha);border-radius:12px;margin-bottom:16px}
.ind{padding:16px 18px 14px;border-left:1px solid var(--linha);min-width:0;position:relative}
.ind:first-child{border-left:0}
.ind .rot{color:var(--tinta-2);font-size:12.5px;display:flex;gap:6px;align-items:center}
.ind .val{font-size:26px;font-weight:600;letter-spacing:-.6px;margin:4px 0 6px;font-variant-numeric:tabular-nums;white-space:nowrap}
.ind .val small{font-size:14px;color:var(--tinta-3);font-weight:500;letter-spacing:0}
.ind .baixo{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 10px;min-height:20px}
.ind .spark{position:absolute;bottom:14px;right:16px;width:64px;height:22px}
.ind .baixo{padding-right:70px}
.ind.alerta .val{color:var(--vermelho-2)}
.ind a.fila{font-size:12.5px;color:var(--vermelho-2);font-weight:500;text-decoration:none}
.ind a.fila:hover{text-decoration:underline}
.delta{font-size:12px;font-weight:500;white-space:nowrap}
.delta.bom{color:#0a6b3c}.delta.ruim{color:var(--vermelho-2)}.delta.neutro{color:var(--tinta-3)}
.ajuda{display:inline-grid;place-items:center;width:15px;height:15px;border-radius:50%;border:1px solid #c7d0cd;color:var(--tinta-3);font-size:10px;font-weight:600;cursor:help}
@media (max-width:1280px){.faixa{grid-template-columns:repeat(3,minmax(0,1fr))}.ind:nth-child(4){border-left:0}.ind:nth-child(n+4){border-top:1px solid var(--linha)}}
@media (max-width:620px){.faixa{grid-template-columns:repeat(2,minmax(0,1fr))}.ind{border-left:0;border-top:1px solid var(--linha)}.ind:nth-child(-n+2){border-top:0}.ind:nth-child(even){border-left:1px solid var(--linha)}}

/* ---------- mapa de calor ---------- */
.calor{display:grid;grid-template-columns:34px repeat(24,minmax(0,1fr));gap:3px;font-size:11px}
.calor .hd{color:var(--tinta-3);font-size:10.5px;text-align:left}
.calor .dl{color:var(--tinta-2);display:flex;align-items:center}
.calor .cel{aspect-ratio:1/1;border-radius:3px;min-height:12px}
.calor .cel:hover{outline:2px solid var(--tinta);outline-offset:-1px}
.escala{display:flex;align-items:center;gap:4px;margin-top:14px;color:var(--tinta-3);font-size:12px}
.escala i{width:18px;height:9px;border-radius:2px;display:inline-block}
.escala span{margin-left:8px}

/* ---------- casos ---------- */
.caso{display:flex;gap:14px;padding:14px 0;border-top:1px solid var(--linha-2)}
.caso:first-of-type{border-top:0;padding-top:4px}
.caso .m{font-family:var(--serif);font-size:15.5px;line-height:1.4;margin:0 0 7px}
.caso .meta{display:flex;flex-wrap:wrap;gap:6px;align-items:center;color:var(--tinta-3);font-size:12.5px}
.caso .meta a{margin-left:auto;font-weight:500;text-decoration:none}
.caso .meta a:hover{text-decoration:underline}
</style>

<section class="diag" aria-label="Diagnóstico do período">
  <div class="diag-texto">
    <p class="frase"><?= h($DIAG_1) ?></p>
    <p class="frase"><?= h($DIAG_2) ?></p>
    <?php if ($DIAG_NOTAS): ?><ul><?php foreach ($DIAG_NOTAS as $n): ?><li><?= h($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <div class="lacuna">
    <h2>Do encerramento à satisfação</h2>
    <?php foreach ($lacuna as $l): ?>
      <div class="linha <?= $l[3] ?>">
        <div class="top"><span><?= h($l[0]) ?></span><b><?= $l[1] === null ? '—' : pct($l[1]) ?></b></div>
        <div class="trilho"><i style="--w:<?= $l[1] === null ? 0 : round($l[1], 1) ?>%"></i></div>
        <small><?= h($l[2]) ?></small>
      </div>
    <?php endforeach; ?>
    <?php if ($GAP !== null): ?>
      <div class="conclusao"><b><?= number_format(abs($GAP), 1, ',', '.') ?> pontos</b> separam o que o sistema encerra do que o cliente aprova.</div>
    <?php endif; ?>
  </div>
</section>

<section class="faixa" aria-label="Indicadores">
  <div class="ind">
    <div class="rot">Conversas</div>
    <div class="val"><?= num($kc['conversas']) ?></div>
    <div class="baixo"><?= delta($kc['conversas'], $kca['conversas'], 'rel', true) ?></div><?= spark($vol, '#0f7b6c') ?>
  </div>
  <div class="ind">
    <div class="rot">Encerradas <span class="ajuda" title="Status 'resolvida' no iTalk. Inclui encerramentos automáticos e da Guta.">?</span></div>
    <div class="val"><?= pct($taxaRes) ?></div>
    <div class="baixo"><?= delta($taxaRes, $taxaResA, 'pp', true) ?></div><?= spark($pr, '#9aa6a2') ?>
  </div>
  <div class="ind">
    <div class="rot">Sem reabrir <span class="ajuda" title="Resolvidas que não voltaram a abrir. Medido pelo webhook a partir do início do rastreio.">?</span></div>
    <div class="val"><?= $kc['rastreadas'] ? pct($taxaSR) : '—' ?></div>
    <div class="baixo"><?= $kc['rastreadas'] ? delta($taxaSR, $taxaSRA, 'pp', true) . ' <span class="delta neutro">' . num($kc['reaberturas']) . ' reaberturas</span>' : '<span class="delta neutro">em medição</span>' ?></div>
  </div>
  <div class="ind">
    <div class="rot">1ª resposta <span class="ajuda" title="Mediana do tempo entre a abertura da conversa e a primeira resposta de um atendente.">?</span></div>
    <div class="val"><?= duracao($kc['mediana_1a']) ?></div>
    <div class="baixo"><?= deltaMin($kc['mediana_1a'], $kca['mediana_1a']) ?></div><?= spark($p1, '#e07a2e') ?>
  </div>
  <div class="ind">
    <div class="rot">CSAT médio</div>
    <div class="val"><?= $ks['respostas'] ? num($ks['media'], 2) : '—' ?><small> de 5</small></div>
    <div class="baixo"><?= delta($ks['media'], $ksa['media'], 'abs', true) ?></div><?= spark($med, '#0f7b6c') ?>
  </div>
  <div class="ind alerta">
    <div class="rot">Notas baixas</div>
    <div class="val"><?= num($ks['baixas']) ?><small> de <?= num($ks['respostas']) ?></small></div>
    <div class="baixo"><?= delta($ks['baixas'], $ksa['baixas'], 'rel', false) ?><?php if ($pend): ?><a class="fila" href="<?= h(url(array('p' => 'fila', 'status' => 'pendente', 'semana' => 'todas'))) ?>"><?= num($pend) ?> para avaliar</a><?php endif; ?></div>
  </div>
</section>

<div class="grade">
  <div class="painel c8">
    <h3>Volume de conversas</h3><p class="leg">Conversas abertas por <?= $grao ?></p>
    <div class="graf"><canvas id="gVolume" role="img" aria-label="Volume de conversas por <?= $grao ?>"></canvas></div>
  </div>
  <div class="painel c4">
    <h3>Taxa de resolução</h3><p class="leg">Encerradas no sistema<?= $temSR ? ' e resolvidas sem reabrir' : '' ?></p>
    <div class="graf"><canvas id="gResolucao" role="img" aria-label="Taxa de resolução"></canvas></div>
  </div>

  <div class="painel c7">
    <h3>Quando o suporte é procurado</h3><p class="leg">Conversas abertas por dia da semana e hora</p>
    <?php if ($maxCalor):
        $dias  = array(1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom');
        $rampa = array('#f1f5f4', '#d7ebe6', '#b3dbd2', '#86c5b8', '#56aa9a', '#2d917f', '#0f7b6c', '#0a5a4f'); ?>
      <div class="calor" role="img" aria-label="Mapa de calor: pico <?= h($DIAS_NOME[$pico['d']]) ?> às <?= (int)$pico['h'] ?>h">
        <div></div><?php for ($hh = 0; $hh < 24; $hh++): ?><div class="hd"><?= $hh % 3 === 0 ? $hh . 'h' : '' ?></div><?php endfor; ?>
        <?php foreach ($dias as $d => $nome): ?>
          <div class="dl"><?= $nome ?></div>
          <?php for ($hh = 0; $hh < 24; $hh++): $v = $calor[$d][$hh]; $i = $v ? min(7, 1 + (int)floor(6.999 * $v / $maxCalor)) : 0; ?>
            <div class="cel" style="background:<?= $rampa[$i] ?>" title="<?= $nome ?>, <?= $hh ?>h às <?= $hh + 1 ?>h: <?= num($v) ?> conversa<?= $v == 1 ? '' : 's' ?>"></div>
          <?php endfor; ?>
        <?php endforeach; ?>
      </div>
      <div class="escala">Menos <?php foreach ($rampa as $cor): ?><i style="background:<?= $cor ?>"></i><?php endforeach; ?> Mais <span>Pico de <?= num($maxCalor) ?> conversas em uma hora</span></div>
    <?php else: ?><div class="vazio">Sem conversas no período.</div><?php endif; ?>
  </div>
  <div class="painel c5">
    <h3>Tempo até a 1ª resposta</h3><p class="leg">Mediana por <?= $grao ?></p>
    <div class="graf"><canvas id="gPrimeira" role="img" aria-label="Tempo até a primeira resposta"></canvas></div>
  </div>

  <div class="painel c4">
    <h3>CSAT médio</h3><p class="leg">Nota média de 1 a 5 por <?= $grao ?></p>
    <div class="graf"><canvas id="gCsat" role="img" aria-label="CSAT médio"></canvas></div>
  </div>
  <div class="painel c4">
    <h3>Como os clientes avaliam</h3><p class="leg">Respostas em cada nota</p>
    <div class="graf"><canvas id="gNotas" role="img" aria-label="Distribuição das notas"></canvas></div>
  </div>
  <div class="painel c4">
    <h3>Por onde chegam</h3><p class="leg">Conversas por caixa de entrada</p>
    <?php if ($caixas): $mx = (int)$caixas[0]['n']; $tot = array_sum(array_map(function ($x) { return (int)$x['n']; }, $caixas)); ?>
      <div class="hbar">
        <?php foreach ($caixas as $x): ?>
          <div class="t" title="<?= h($x['rotulo']) ?>"><?= h($x['rotulo']) ?></div>
          <div class="b"><i style="width:<?= round(100 * (int)$x['n'] / max(1, $mx), 1) ?>%"></i></div>
          <div class="n" title="<?= num($x['n']) ?> conversas"><?= pct(100 * (int)$x['n'] / max(1, $tot), 0) ?></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="vazio">Sem conversas no período.</div><?php endif; ?>
  </div>
</div>

<div class="titulo-secao">
  <h2>O que dizem as notas baixas</h2>
  <p>Cada conversa com nota até <?= (int)$corte ?> é lida pela IA, que aponta o motivo e o responsável.</p>
</div>
<div class="grade">
  <div class="painel c5">
    <h3>Motivos</h3><p class="leg">Categoria atribuída pela IA</p>
    <?php if ($iaCat): $mx = (int)$iaCat[0]['n']; ?>
      <div class="hbar">
        <?php foreach ($iaCat as $x): ?>
          <div class="t"><?= h(rotulo('CATEGORIAS', $x['k'])) ?></div><div class="b"><i style="width:<?= round(100 * $x['n'] / $mx, 1) ?>%;background:var(--vermelho)"></i></div><div class="n"><?= num($x['n']) ?></div>
        <?php endforeach; ?>
      </div>
      <?php if ($iaResp): $mx = (int)$iaResp[0]['n']; ?>
        <h3 style="margin-top:26px">Responsável</h3><p class="leg">Onde está a causa, segundo a IA</p>
        <div class="hbar">
          <?php foreach ($iaResp as $x): ?>
            <div class="t"><?= h(rotulo('RESPONSAVEIS', $x['k'])) ?></div><div class="b"><i style="width:<?= round(100 * $x['n'] / $mx, 1) ?>%;background:var(--violeta)"></i></div><div class="n"><?= num($x['n']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?><div class="vazio">Nenhuma nota baixa analisada no período.</div><?php endif; ?>
  </div>
  <div class="painel c7">
    <h3>Casos mais graves</h3><p class="leg">Leitura da IA sobre cada atendimento. A avaliação final é do supervisor.</p>
    <?php if ($analises): foreach ($analises as $a): ?>
      <div class="caso">
        <div class="nota n<?= (int)$a['nota'] ?>" title="Nota <?= (int)$a['nota'] ?>"><?= (int)$a['nota'] ?></div>
        <div style="min-width:0;flex:1">
          <p class="m"><?= h($a['ia_motivo']) ?></p>
          <div class="meta">
            <span class="tag cat"><?= h(rotulo('CATEGORIAS', $a['ia_categoria'])) ?></span>
            <span class="tag"><?= h(rotulo('RESPONSAVEIS', $a['ia_responsavel'])) ?></span>
            <?php if ($a['ia_gravidade'] === 'alta' || $a['ia_gravidade'] === 'media'): ?><span class="tag g-<?= h($a['ia_gravidade']) ?>"><span class="pt"></span>Gravidade <?= $a['ia_gravidade'] === 'alta' ? 'alta' : 'média' ?></span><?php endif; ?>
            <span><?= h($a['atendente'] ? $a['atendente'] : 'Sem atendente') ?>, <?= date('d/m', strtotime($a['respondido_em'])) ?></span>
            <a href="<?= h(url(array('p' => 'fila', 'semana' => 'todas'))) ?>#item-<?= (int)$a['auditoria_id'] ?>">Abrir caso</a>
          </div>
        </div>
      </div>
    <?php endforeach; else: ?><div class="vazio">Nenhuma análise no período.</div><?php endif; ?>
  </div>
</div>

<script><?php readfile(__DIR__ . '/../vendor/chart.umd.min.js'); /* Chart.js 4.4.1, MIT */ ?></script>
<script>
(function () {
  var C = {verde: '#0f7b6c', verde2: '#0a5a4f', laranja: '#e07a2e', cinza: '#a9b4b0', grade: '#eef2f0', eixo: '#d3dbd8', texto: '#7c8a86'};
  Chart.defaults.font.family = '"IBM Plex Sans", system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = C.texto;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.animation.duration = 500;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  Chart.defaults.plugins.legend.labels.boxHeight = 8;
  Chart.defaults.plugins.legend.labels.color = '#4d5d58';
  var tt = Chart.defaults.plugins.tooltip;
  tt.backgroundColor = '#10231f'; tt.padding = 10; tt.cornerRadius = 8; tt.boxPadding = 4; tt.titleFont = {weight: '600'}; tt.displayColors = true;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;

  var y = function (extra) { var o = {grid: {color: C.grade, drawTicks: false}, border: {display: false}, ticks: {padding: 8}}; for (var k in extra) o[k] = extra[k]; return o; };
  var x = {grid: {display: false}, border: {color: C.eixo}, ticks: {maxRotation: 0, autoSkipPadding: 14}};
  var cruz = {mode: 'index', intersect: false};
  var br = function (v) { return v === null ? '—' : v.toLocaleString('pt-BR'); };
  var linha = function (cor, extra) { var o = {borderColor: cor, backgroundColor: cor, borderWidth: 2, pointRadius: 0, pointHoverRadius: 5, pointHitRadius: 12, tension: .3, spanGaps: true}; for (var k in extra) o[k] = extra[k]; return o; };
  var degrade = function (ctx, cor) { var g = ctx.createLinearGradient(0, 0, 0, 240); g.addColorStop(0, cor + '33'); g.addColorStop(1, cor + '00'); return g; };

  var L = <?= json_encode($lbl) ?>, LS = <?= json_encode($lblS) ?>;

  new Chart(document.getElementById('gVolume'), {
    type: 'bar',
    data: {labels: L, datasets: [{label: 'Conversas', data: <?= json_encode($vol) ?>, backgroundColor: C.verde, hoverBackgroundColor: C.verde2, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 22, categoryPercentage: .72}]},
    options: {interaction: cruz, plugins: {legend: {display: false}, tooltip: {displayColors: false, callbacks: {label: function (c) { return br(c.parsed.y) + ' conversas'; }}}},
              scales: {x: x, y: y({beginAtZero: true, ticks: {padding: 8, precision: 0}})}}
  });

  var dsRes = [linha(C.cinza, {label: 'Encerradas no sistema', data: <?= json_encode($pr) ?>})];
  <?php if ($temSR): ?>dsRes.push(linha(C.verde, {label: 'Sem reabrir', data: <?= json_encode($psr) ?>}));<?php endif; ?>
  new Chart(document.getElementById('gResolucao'), {
    type: 'line', data: {labels: L, datasets: dsRes},
    options: {interaction: cruz, plugins: {legend: {display: <?= $temSR ? 'true' : 'false' ?>, position: 'bottom', align: 'start'},
              tooltip: {callbacks: {label: function (c) { return ' ' + c.dataset.label + ': ' + (c.parsed.y === null ? '—' : c.parsed.y.toLocaleString('pt-BR') + '%'); }}}},
              scales: {x: x, y: y({suggestedMin: 60, max: 100, ticks: {padding: 8, callback: function (v) { return v + '%'; }}})}}
  });

  var cP = document.getElementById('gPrimeira');
  new Chart(cP, {
    type: 'line',
    data: {labels: L, datasets: [linha(C.laranja, {label: 'Mediana', data: <?= json_encode($p1) ?>, fill: true, backgroundColor: degrade(cP.getContext('2d'), '#e07a2e')})]},
    options: {interaction: cruz, plugins: {legend: {display: false}, tooltip: {displayColors: false, callbacks: {label: function (c) { var m = c.parsed.y; if (m === null) return '—'; return 'Mediana: ' + (m < 60 ? Math.round(m) + ' min' : Math.floor(m / 60) + 'h ' + Math.round(m % 60) + 'min'); }}}},
              scales: {x: x, y: y({beginAtZero: true, ticks: {padding: 8, callback: function (v) { return v < 60 ? v + ' min' : (v / 60).toLocaleString('pt-BR', {maximumFractionDigits: 1}) + ' h'; }}})}}
  });

  var cC = document.getElementById('gCsat');
  new Chart(cC, {
    type: 'line',
    data: {labels: LS, datasets: [linha(C.verde, {label: 'CSAT médio', data: <?= json_encode($med) ?>, respostas: <?= json_encode($resp) ?>, fill: true, backgroundColor: degrade(cC.getContext('2d'), '#0f7b6c'), pointRadius: 2.5})]},
    options: {interaction: cruz, plugins: {legend: {display: false}, tooltip: {displayColors: false, callbacks: {label: function (c) { return 'Média ' + c.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ', ' + c.dataset.respostas[c.dataIndex] + ' respostas'; }}}},
              scales: {x: x, y: y({min: 1, max: 5, ticks: {padding: 8, stepSize: 1}})}}
  });

  var dist = <?= json_encode(array_values($dist)) ?>, totD = dist.reduce(function (a, b) { return a + b; }, 0) || 1;
  new Chart(document.getElementById('gNotas'), {
    type: 'bar',
    data: {labels: ['Nota 1', 'Nota 2', 'Nota 3', 'Nota 4', 'Nota 5'], datasets: [{label: 'Respostas', data: dist, backgroundColor: ['#d6453d', '#e07b74', '#b5bfbc', '#6fb5a8', '#0f7b6c'], borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 40}]},
    options: {plugins: {legend: {display: false}, tooltip: {displayColors: false, callbacks: {label: function (c) { return br(c.parsed.y) + ' respostas (' + Math.round(100 * c.parsed.y / totD) + '%)'; }}}},
              scales: {x: x, y: y({beginAtZero: true, ticks: {padding: 8, precision: 0}})}}
  });
})();
</script>
