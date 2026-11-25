<?php
// destaques do período
$dest = array('volume' => null, 'csat' => null, 'rapido' => null, 'baixas' => null);
foreach ($rank as $r) {
    if (!$dest['volume'] || $r['conversas'] > $dest['volume']['conversas']) {
        $dest['volume'] = $r;
    }
    if ($r['media'] !== null && $r['respostas'] >= 10 && (!$dest['csat'] || $r['media'] > $dest['csat']['media'])) {
        $dest['csat'] = $r;
    }
    if ($r['mediana_1a'] !== null && $r['conversas'] >= 20 && (!$dest['rapido'] || $r['mediana_1a'] < $dest['rapido']['mediana_1a'])) {
        $dest['rapido'] = $r;
    }
    if ($r['baixas'] > 0 && (!$dest['baixas'] || $r['baixas'] > $dest['baixas']['baixas'])) {
        $dest['baixas'] = $r;
    }
}
$cards = array(
    array('Mais conversas', $dest['volume'], function ($r) { return num($r['conversas']) . ' conversas'; }, ''),
    array('Melhor CSAT', $dest['csat'], function ($r) { return num($r['media'], 2) . ' em ' . num($r['respostas']) . ' respostas'; }, ''),
    array('1ª resposta mais rápida', $dest['rapido'], function ($r) { return duracao($r['mediana_1a']) . ' de mediana'; }, ''),
    array('Mais notas baixas', $dest['baixas'], function ($r) { return num($r['baixas']) . ' de ' . num($r['respostas']) . ' respostas'; }, 'alerta'),
);
?>
<style>
.destaques{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:16px}
.dest{background:var(--cartao);border:1px solid var(--linha);border-radius:12px;padding:16px 18px;display:flex;gap:13px;align-items:center;text-decoration:none;color:inherit}
.dest:hover{border-color:#c6cfdc}
.dest .av{width:40px;height:40px;border-radius:50%;background:var(--pri-claro);color:var(--pri-2);display:grid;place-items:center;font-weight:600;font-size:16px;flex:none}
.dest.alerta .av{background:var(--vermelho-claro);color:var(--vermelho-2)}
.dest small{display:block;color:var(--tinta-3);font-size:12px}
.dest b{display:block;font-size:15.5px;font-weight:600;line-height:1.3}
.dest span.v{color:var(--tinta-2);font-size:12.5px}
@media (max-width:1100px){.destaques{grid-template-columns:repeat(2,minmax(0,1fr))}}

table.rank{width:100%;border-collapse:collapse;font-size:13.5px}
table.rank th{text-align:left;font-weight:500;color:var(--tinta-3);font-size:12.5px;padding:0 12px 10px;border-bottom:1px solid var(--linha);white-space:nowrap}
table.rank th button{all:unset;cursor:pointer;display:inline-flex;gap:4px;align-items:center}
table.rank th button:hover{color:var(--tinta)}
table.rank th button:focus-visible{outline:3px solid var(--pri-foco);border-radius:4px}
table.rank th[aria-sort] button{color:var(--tinta)}
table.rank th.n,table.rank td.n{text-align:right}
table.rank td{padding:12px 12px;border-bottom:1px solid var(--linha-2);font-variant-numeric:tabular-nums;vertical-align:middle}
table.rank tbody tr:hover td{background:#f9fafd}
table.rank td.nome a{color:var(--tinta);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:10px}
table.rank td.nome a:hover{color:var(--pri-2)}
table.rank .ini{width:28px;height:28px;border-radius:50%;background:var(--linha-2);display:grid;place-items:center;font-size:12px;font-weight:600;color:var(--tinta-2);flex:none}
.vol{display:flex;align-items:center;gap:10px;justify-content:flex-end}
.vol i{display:block;height:6px;border-radius:3px;background:var(--pri);opacity:.85}
.nota-media{display:inline-flex;align-items:center;gap:7px;font-weight:600}
.nota-media::before{content:"";width:8px;height:8px;border-radius:50%;background:#b4bdcb}
.nota-media.alta::before{background:var(--pri)}.nota-media.baixa::before{background:var(--vermelho)}
.zero{color:#b4bdcb}
.baixas a{color:var(--vermelho-2);font-weight:600;text-decoration:none}.baixas a:hover{text-decoration:underline}
</style>

<?php if ($rank): ?>
<section class="destaques" aria-label="Destaques do período" data-tour="1" data-tour-titulo="Destaques do período" data-tour-texto="Quem atendeu mais, quem tem o melhor CSAT, quem responde mais rápido e quem concentra mais notas baixas. Clique para aprofundar.">
  <?php foreach ($cards as $c): if (!$c[1]) continue; $r = $c[1]; $nome = $r['atendente'] ? $r['atendente'] : '#' . $r['id']; ?>
    <a class="dest <?= $c[3] ?>" href="<?= h($c[3] === 'alerta' ? url(array('p' => 'fila', 'atendente' => $r['id'], 'semana' => 'todas')) : url(array('p' => 'visao', 'atendente' => $r['id']))) ?>">
      <span class="av"><?= h(mb_substr_compat($nome)) ?></span>
      <span><small><?= h($c[0]) ?></small><b><?= h($nome) ?></b><span class="v"><?= h(call_user_func($c[2], $r)) ?></span></span>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="painel" data-tour="2" data-tour-titulo="Desempenho por atendente" data-tour-texto="Clique no título de qualquer coluna para ordenar. O nome abre o diagnóstico daquela pessoa; o número de notas baixas abre a auditoria dela.">
  <h3>Desempenho por atendente</h3>
  <p class="leg">Conversas atribuídas no período e CSAT das conversas atendidas. Ordene pelas colunas; o nome abre o diagnóstico daquela pessoa.</p>
  <?php if ($rank): ?>
  <div style="overflow-x:auto">
  <table class="rank" id="tRank">
    <thead><tr>
      <th data-t="s"><button type="button">Atendente</button></th>
      <th class="n" data-t="n" aria-sort="descending"><button type="button">Conversas</button></th>
      <th class="n" data-t="n"><button type="button">Encerradas</button></th>
      <th class="n" data-t="n"><button type="button">Reaberturas</button></th>
      <th class="n" data-t="n"><button type="button">1ª resposta</button></th>
      <th class="n" data-t="n"><button type="button">Avaliações</button></th>
      <th class="n" data-t="n"><button type="button">CSAT</button></th>
      <th class="n" data-t="n"><button type="button">Notas baixas</button></th>
      <th data-t="s"><button type="button">Motivo mais comum</button></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rank as $r): $nome = $r['atendente'] ? $r['atendente'] : '#' . $r['id']; ?>
      <tr>
        <td class="nome" data-v="<?= h($nome) ?>"><a href="<?= h(url(array('p' => 'visao', 'atendente' => $r['id']))) ?>"><span class="ini"><?= h(mb_substr_compat($nome)) ?></span><?= h($nome) ?></a></td>
        <td class="n" data-v="<?= (int)$r['conversas'] ?>"><div class="vol"><i style="width:<?= round(64 * $r['conversas'] / $maxConv) ?>px"></i><?= num($r['conversas']) ?></div></td>
        <td class="n" data-v="<?= $r['pct_resolvidas'] === null ? -1 : (float)$r['pct_resolvidas'] ?>"><?= pct($r['pct_resolvidas']) ?></td>
        <td class="n" data-v="<?= (int)$r['reaberturas'] ?>"><?= (int)$r['reaberturas'] ? num($r['reaberturas']) : '<span class="zero">0</span>' ?></td>
        <td class="n" data-v="<?= $r['mediana_1a'] === null ? 999999 : (float)$r['mediana_1a'] ?>"><?= duracao($r['mediana_1a']) ?></td>
        <td class="n" data-v="<?= (int)$r['respostas'] ?>"><?= num($r['respostas']) ?></td>
        <td class="n" data-v="<?= $r['media'] === null ? -1 : (float)$r['media'] ?>">
          <?php if ($r['media'] !== null): $m = (float)$r['media']; ?><span class="nota-media <?= $m >= 4.5 ? 'alta' : ($m < 4 ? 'baixa' : '') ?>"><?= num($m, 2) ?></span><?php else: ?><span class="zero">—</span><?php endif; ?>
        </td>
        <td class="n baixas" data-v="<?= (int)$r['baixas'] ?>"><?php if ((int)$r['baixas']): ?><a href="<?= h(url(array('p' => 'fila', 'atendente' => $r['id'], 'semana' => 'todas'))) ?>"><?= num($r['baixas']) ?></a><?php else: ?><span class="zero">0</span><?php endif; ?></td>
        <td data-v="<?= h(rotulo('CATEGORIAS', $r['motivo'])) ?>"><?= $r['motivo'] ? '<span class="tag cat">' . h(rotulo('CATEGORIAS', $r['motivo'])) . '</span>' : '<span class="zero">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><div class="vazio">Nenhuma conversa atribuída no período. Experimente um período maior.</div><?php endif; ?>
</div>
<script>
(function () {
  var t = document.getElementById('tRank'); if (!t) return;
  var ths = t.tHead.rows[0].cells;
  Array.prototype.forEach.call(ths, function (th, i) {
    th.querySelector('button').addEventListener('click', function () {
      var desc = th.getAttribute('aria-sort') !== 'descending';
      Array.prototype.forEach.call(ths, function (o) { o.removeAttribute('aria-sort'); });
      th.setAttribute('aria-sort', desc ? 'descending' : 'ascending');
      var tb = t.tBodies[0], rows = Array.prototype.slice.call(tb.rows), num = th.dataset.t === 'n';
      rows.sort(function (a, b) {
        var x = a.cells[i].dataset.v, y = b.cells[i].dataset.v;
        if (num) { x = parseFloat(x); y = parseFloat(y); return desc ? y - x : x - y; }
        return desc ? String(y).localeCompare(x, 'pt-BR') : String(x).localeCompare(y, 'pt-BR');
      });
      rows.forEach(function (r) { tb.appendChild(r); });
    });
  });
})();
</script>
