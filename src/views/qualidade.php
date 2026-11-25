<?php
$cobertura = $enc ? min(100, 100.0 * (int)$kq['auditadas'] / $enc) : null;
$lbl = array(); $preal = array(); $pnota = array();
foreach ($sq as $r) {
    $lbl[]   = rotuloBucket($r['b'], $F['grao']);
    $preal[] = $r['pct_resolvido'] === null ? null : (float)$r['pct_resolvido'];
    $pnota[] = $r['nota'] === null ? null : (float)$r['nota'];
}
$critMap = array();
foreach ($crit as $c) {
    $critMap[$c['k']] = $c;
}
$maxFalha = $falh ? (int)$falh[0]['n'] : 1;
$maxMot   = $motc ? (int)$motc[0]['n'] : 1;
$realGuta = $kq['so_guta'] ? taxa($kq['so_guta_resolvidas'], $kq['so_guta']) : null;
$realHum  = $kq['com_humano'] ? taxa($kq['com_humano_resolvidas'], $kq['com_humano']) : null;
?>
<style>
.faixa{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));background:var(--cartao);border:1px solid var(--linha);border-radius:12px;margin-bottom:16px}
.ind{padding:16px 18px 14px;border-left:1px solid var(--linha);min-width:0}
.ind:first-child{border-left:0}
.ind .rot{color:var(--tinta-2);font-size:12.5px;display:flex;gap:4px;align-items:center}
.ind .val{font-size:26px;font-weight:600;letter-spacing:-.6px;margin:4px 0 6px;font-variant-numeric:tabular-nums;white-space:nowrap}
.ind .val small{font-size:14px;color:var(--tinta-3);font-weight:500;letter-spacing:0}
.ind .baixo{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 10px;min-width:0}
.ind .delta{white-space:normal}
.ind.alerta .val{color:var(--vermelho-2)}
.delta{font-size:12px;font-weight:500;white-space:nowrap}
.delta.bom{color:#0a6b3c}.delta.ruim{color:var(--vermelho-2)}.delta.neutro{color:var(--tinta-3)}
@media (max-width:1280px){.faixa{grid-template-columns:repeat(3,minmax(0,1fr))}.ind:nth-child(4){border-left:0}.ind:nth-child(n+4){border-top:1px solid var(--linha)}}
@media (max-width:620px){.faixa{grid-template-columns:repeat(2,minmax(0,1fr))}.ind{border-left:0;border-top:1px solid var(--linha)}.ind:nth-child(-n+2){border-top:0}.ind:nth-child(even){border-left:1px solid var(--linha)}}
.crit{display:grid;grid-template-columns:minmax(140px,45%) 1fr 36px;gap:10px 12px;align-items:center;font-size:13px}
.crit .b{height:10px;background:var(--linha-2);border-radius:5px;overflow:hidden}
.crit .b i{display:block;height:100%;border-radius:5px}
.crit .n{text-align:right;font-weight:600;font-variant-numeric:tabular-nums}
.tab-mot{width:100%;border-collapse:collapse;font-size:13.5px}
.tab-mot th{text-align:left;font-weight:500;color:var(--tinta-3);font-size:12.5px;padding:0 10px 9px;border-bottom:1px solid var(--linha)}
.tab-mot th.n,.tab-mot td.n{text-align:right}
.tab-mot td{padding:10px;border-bottom:1px solid var(--linha-2);font-variant-numeric:tabular-nums}
.tab-mot .vol{display:flex;align-items:center;gap:10px;justify-content:flex-end}
.tab-mot .vol i{display:block;height:6px;border-radius:3px;background:var(--pri)}
.pill{display:inline-block;min-width:44px;text-align:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}
.pill.bom{background:#e6f4e8;color:#0a6b3c}.pill.medio{background:var(--ambar-claro);color:var(--ambar)}.pill.ruim{background:var(--vermelho-claro);color:var(--vermelho-2)}
.risco{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:14px;padding:14px 0;border-top:1px solid var(--linha-2);align-items:start;scroll-margin-top:20px}
.risco:first-of-type{border-top:0;padding-top:4px}
.risco .nq{width:44px;height:44px;border-radius:10px;display:grid;place-items:center;font-weight:600;font-size:15px;background:var(--vermelho-claro);color:var(--vermelho-2)}
.risco .nq.medio{background:var(--ambar-claro);color:var(--ambar)}
.risco h4{margin:0 0 4px;font-size:14.5px;font-weight:600}
.risco p{margin:0 0 6px;color:var(--tinta-2);font-size:13px;line-height:1.5;max-width:80ch}
.risco .meta-l{display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:12.5px;color:var(--tinta-3)}
.risco .acoes-r{display:flex;flex-direction:column;gap:6px;align-items:flex-end}
.vazio-qa{text-align:center;padding:40px 20px;color:var(--tinta-2)}
.vazio-qa h3{font-family:var(--serif);font-weight:500;font-size:20px;color:var(--tinta);margin:0 0 8px}
.mini-linhas{display:flex;flex-direction:column;gap:9px}
.mini-linhas div{display:flex;justify-content:space-between;gap:12px;font-size:13px;color:var(--tinta-2)}
.mini-linhas b{color:var(--tinta);font-variant-numeric:tabular-nums}
.sub-h{margin:22px 0 10px;font-size:13.5px;font-weight:600;display:flex;align-items:center;gap:4px}
</style>

<?php if (!(int)$kq['auditadas']): ?>
  <div class="painel vazio-qa" id="qualidade">
    <h3>A IA ainda não auditou conversas deste período</h3>
    <p>A auditoria roda a cada 5 minutos e lê as conversas encerradas, com ou sem nota do cliente. Ela precisa estar ligada em Configurações.</p>
    <?php if ($ADMIN): ?>
      <form method="post" style="margin-top:14px"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="rodar_qa">
        <button class="btn pri" type="submit">Auditar 5 conversas agora</button></form>
    <?php endif; ?>
  </div>
<?php else: ?>

<section class="faixa" aria-label="Indicadores de qualidade" data-tour="1" data-tour-titulo="Qualidade de todas as conversas" data-tour-texto="A IA lê as conversas encerradas, mesmo quando o cliente não dá nota, e avalia se o problema foi resolvido de fato, a qualidade do atendimento e o risco de insatisfação.">
  <div class="ind">
    <div class="rot">Auditadas pela IA <?= explica('cobertura') ?></div>
    <div class="val"><?= num($kq['auditadas']) ?></div>
    <div class="baixo"><span class="delta neutro"><?= $cobertura !== null ? pct($cobertura, 0) . ' das encerradas' : '' ?></span></div>
  </div>
  <div class="ind">
    <div class="rot">Resolvidas de fato <?= explica('resolucao_real') ?></div>
    <div class="val"><?= pct($taxaReal) ?></div>
    <div class="baixo"><?= delta($taxaReal, $taxaRealA, 'pp', true) ?><?= metaHtml($taxaReal, conf('meta_resolucao_real_pct'), true, conf('meta_resolucao_real_pct') . '%') ?></div>
  </div>
  <div class="ind">
    <div class="rot">Nota de qualidade <?= explica('nota_qa') ?></div>
    <div class="val"><?= num($kq['nota_media']) ?><small> de 100</small></div>
    <div class="baixo"><?= delta($kq['nota_media'], $kqa['nota_media'], 'abs', true) ?><?= metaHtml($kq['nota_media'], conf('meta_qa'), true, conf('meta_qa')) ?></div>
  </div>
  <div class="ind alerta">
    <div class="rot">Risco sem nota <?= explica('risco') ?></div>
    <div class="val"><?= num($kq['risco_sem_nota']) ?></div>
    <div class="baixo"><span class="delta neutro">de <?= num($kq['sem_nota']) ?> sem nota</span></div>
  </div>
  <div class="ind">
    <div class="rot">Cliente sem resposta <?= explica('sem_resposta') ?></div>
    <div class="val"><?= num($kq['sem_resposta']) ?></div>
    <div class="baixo"><span class="delta neutro"><?= pct(taxa($kq['sem_resposta'], $kq['auditadas'])) ?> das auditadas</span></div>
  </div>
  <div class="ind">
    <div class="rot">Maior espera <?= explica('espera') ?></div>
    <div class="val"><?= duracao($kq['espera_mediana']) ?></div>
    <div class="baixo"><span class="delta neutro">mediana por conversa</span></div>
  </div>
</section>

<div class="grade">
  <div class="painel c8">
    <h3 class="cab-painel">Resolução real ao longo do tempo <?= explica('resolucao_real') ?></h3><p class="leg">% das conversas que a IA confirmou como resolvidas, por <?= $F['grao'] === 'dia' ? 'dia' : 'semana' ?></p>
    <div class="graf"><canvas id="gReal" role="img" aria-label="Resolução real ao longo do tempo"></canvas></div>
  </div>
  <div class="painel c4">
    <h3 class="cab-painel">Como terminam as conversas <?= explica('sentimento') ?></h3><p class="leg">Leitura da IA sobre o cliente e sobre quem resolve</p>
    <div class="mini-linhas">
      <div><span>Resolvidas</span><b><?= num($kq['resolvidas']) ?></b></div>
      <div><span>Resolvidas em parte</span><b><?= num($kq['parciais']) ?></b></div>
      <div><span>Não resolvidas</span><b style="color:var(--vermelho-2)"><?= num($kq['nao_resolvidas']) ?></b></div>
      <div><span>Cliente terminou insatisfeito</span><b><?= num($kq['termina_negativo']) ?></b></div>
      <div><span>Começou irritado e foi recuperado</span><b style="color:#0a6b3c"><?= num($kq['recuperados']) ?></b></div>
    </div>
    <div class="sub-h">Guta e equipe <?= explica('guta_vs_humano') ?></div>
    <div class="mini-linhas">
      <div><span>Resolução real só com a Guta</span><b><?= pct($realGuta) ?></b></div>
      <div><span>Resolução real com atendente</span><b><?= pct($realHum) ?></b></div>
    </div>
  </div>

  <div class="painel c6" data-tour="2" data-tour-titulo="Onde a qualidade falha" data-tour-texto="A nota média de cada critério e as falhas concretas que a IA encontrou, com trecho da conversa como evidência. É a pauta de treinamento da equipe.">
    <h3 class="cab-painel">Critérios de qualidade <?= explica('criterios') ?></h3><p class="leg">Nota média de 0 a 10 em cada critério</p>
    <div class="crit">
      <?php foreach ($GLOBALS['QA_CRITERIOS'] as $k => $rot): if (!isset($critMap[$k])) continue; $m = (float)$critMap[$k]['media']; $cor = $m >= 8 ? 'var(--pri)' : ($m >= 6 ? '#d99a2b' : 'var(--vermelho)'); ?>
        <div><?= h($rot) ?></div><div class="b"><i style="width:<?= round($m * 10) ?>%;background:<?= $cor ?>"></i></div><div class="n"><?= num($m, 1) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="painel c6">
    <h3 class="cab-painel">Falhas mais frequentes <?= explica('falhas') ?></h3><p class="leg">Quantas vezes a IA apontou falha em cada critério</p>
    <?php if ($falh): ?>
    <div class="hbar">
      <?php foreach ($falh as $x): $rot = isset($GLOBALS['QA_CRITERIOS'][$x['k']]) ? $GLOBALS['QA_CRITERIOS'][$x['k']] : ucfirst((string)$x['k']); ?>
        <div class="t"><?= h($rot) ?></div><div class="b"><i style="width:<?= round(100 * $x['n'] / $maxFalha, 1) ?>%;background:var(--vermelho)"></i></div><div class="n"><?= num($x['n']) ?></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><div class="vazio">Nenhuma falha apontada no período.</div><?php endif; ?>
  </div>

  <div class="painel c12" data-tour="3" data-tour-titulo="Por que os clientes procuram o suporte" data-tour-texto="Os assuntos que mais geram contato, com a resolução e a qualidade de cada um. Muito volume com pouca resolução aponta onde melhorar o produto, a base de conhecimento ou a Guta.">
    <h3 class="cab-painel">Por que os clientes procuram o suporte <?= explica('motivo_contato') ?></h3><p class="leg">Assunto de cada conversa, segundo a IA</p>
    <div style="overflow-x:auto">
    <table class="tab-mot">
      <thead><tr><th>Assunto</th><th class="n">Conversas</th><th class="n">Resolvidas de fato</th><th class="n">Nota de qualidade</th></tr></thead>
      <tbody>
      <?php foreach ($motc as $m): $pr = $m['pct_resolvido']; $cls = $pr === null ? '' : ((float)$pr >= 80 ? 'bom' : ((float)$pr >= 60 ? 'medio' : 'ruim')); ?>
        <tr>
          <td><?= h(isset($GLOBALS['MOTIVOS_CONTATO'][$m['k']]) ? $GLOBALS['MOTIVOS_CONTATO'][$m['k']] : ucfirst((string)$m['k'])) ?></td>
          <td class="n"><div class="vol"><i style="width:<?= round(90 * $m['n'] / $maxMot) ?>px"></i><?= num($m['n']) ?></div></td>
          <td class="n"><?= $pr === null ? '—' : '<span class="pill ' . $cls . '">' . num($pr) . '%</span>' ?></td>
          <td class="n"><?= num($m['nota']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="painel c12" id="qualidade" data-tour="4" data-tour-titulo="Conversas que pedem atenção" data-tour-texto="Conversas com risco alto ou não resolvidas, inclusive de clientes que não deram nota. Leve as que importam para a auditoria do supervisor.">
    <h3 class="cab-painel">Conversas que pedem atenção <?= explica('conversas_risco') ?></h3><p class="leg">Risco alto ou não resolvidas, priorizando quem não deu nota</p>
    <?php if ($risc): foreach ($risc as $r): $falhas = json_decode((string)$r['falhas'], true); $f0 = is_array($falhas) && $falhas ? $falhas[0] : null; ?>
      <div class="risco" id="conv-<?= (int)$r['conversation_id'] ?>">
        <div class="nq <?= $r['risco'] === 'alto' ? '' : 'medio' ?>" title="Nota de qualidade"><?= $r['nota_qa'] === null ? '—' : (int)$r['nota_qa'] ?></div>
        <div style="min-width:0">
          <h4><?= h($r['assunto'] ? $r['assunto'] : 'Conversa ' . $r['conversation_id']) ?></h4>
          <p><?= h($r['resumo']) ?></p>
          <?php if ($f0): ?><p><b>Falha:</b> <?= h($f0['descricao']) ?><?= !empty($f0['trecho']) ? ' <i style="font-family:var(--serif)">“' . h($f0['trecho']) . '”</i>' : '' ?></p><?php endif; ?>
          <div class="meta-l">
            <?php if ($r['risco'] === 'alto'): ?><span class="tag g-alta"><span class="pt"></span>Risco alto</span><?php endif; ?>
            <?php if ($r['resolvido'] === 'nao'): ?><span class="tag g-media">Não resolvida</span><?php endif; ?>
            <?php if ($r['cliente_sem_resposta'] === true || $r['cliente_sem_resposta'] === 't'): ?><span class="tag">Cliente sem resposta</span><?php endif; ?>
            <span class="tag"><?= $r['nota_csat'] === null ? 'Sem nota do cliente' : 'Nota do cliente: ' . (int)$r['nota_csat'] ?></span>
            <span><?= h($r['atendente'] ? $r['atendente'] : (($r['teve_humano'] === false || $r['teve_humano'] === 'f') ? 'Só a Guta' : 'Sem atendente')) ?>, <?= date('d/m', strtotime($r['criada_em'])) ?></span>
          </div>
        </div>
        <div class="acoes-r">
          <a class="btn" href="<?= h(rtrim(conf('italk_url'), '/') . '/app/accounts/3/conversations/' . (int)$r['conversation_id']) ?>" target="_blank" rel="noopener">Abrir no iTalk</a>
          <?php if ($r['na_fila'] === true || $r['na_fila'] === 't'): ?>
            <span class="tag cat">Na auditoria</span>
          <?php else: ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="levar_fila"><input type="hidden" name="id" value="<?= (int)$r['conversation_id'] ?>">
              <button class="btn pri" type="submit">Levar para a auditoria</button></form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; else: ?><div class="vazio">Nenhuma conversa em risco no período.</div><?php endif; ?>
  </div>
</div>

<script><?php readfile(__DIR__ . '/../vendor/chart.umd.min.js'); /* Chart.js 4.4.1, MIT */ ?></script>
<script>
(function () {
  Chart.defaults.font.family = '"IBM Plex Sans", system-ui, sans-serif';
  Chart.defaults.font.size = 12; Chart.defaults.color = '#78859a'; Chart.defaults.maintainAspectRatio = false;
  var tt = Chart.defaults.plugins.tooltip; tt.backgroundColor = '#0f1d33'; tt.padding = 10; tt.cornerRadius = 8;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;
  var c = document.getElementById('gReal'), g = c.getContext('2d').createLinearGradient(0, 0, 0, 240);
  g.addColorStop(0, '#1b64be33'); g.addColorStop(1, '#1b64be00');
  var notas = <?= json_encode($pnota) ?>;
  new Chart(c, {
    type: 'line',
    data: {labels: <?= json_encode($lbl) ?>, datasets: [{label: 'Resolvidas de fato', data: <?= json_encode($preal) ?>, borderColor: '#1b64be', backgroundColor: g, fill: true, borderWidth: 2, pointRadius: <?= count($lbl) < 3 ? 4 : 0 ?>, pointHoverRadius: 5, pointHitRadius: 12, tension: .3, spanGaps: true}]},
    options: {interaction: {mode: 'index', intersect: false}, plugins: {legend: {display: false}, tooltip: {displayColors: false, callbacks: {
                label: function (k) { return 'Resolvidas de fato: ' + (k.parsed.y === null ? '—' : k.parsed.y.toLocaleString('pt-BR') + '%'); },
                afterLabel: function (k) { var n = notas[k.dataIndex]; return n === null ? '' : 'Nota de qualidade: ' + n.toLocaleString('pt-BR'); }}}},
              scales: {x: {grid: {display: false}, border: {color: '#d3dae5'}, ticks: {maxRotation: 0, autoSkipPadding: 14}},
                       y: {min: 0, max: 100, grid: {color: '#edf1f7', drawTicks: false}, border: {display: false}, ticks: {padding: 8, callback: function (v) { return v + '%'; }}}}}
  });
})();
</script>
<?php endif; ?>
