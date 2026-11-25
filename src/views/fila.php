<?php
$total     = array_sum($contagem);
$avaliados = $total - $contagem['pendente'];
$progresso = $total ? round(100 * $avaliados / $total) : 0;
$ROT_STATUS = array('pendente' => 'Aguardando avaliação', 'avaliado' => 'Avaliado', 'enviado' => 'Enviado ao pós-vendas', 'tratado' => 'Tratado');
?>
<style>
.resumo-fila{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center;background:var(--cartao);border:1px solid var(--linha);border-radius:12px;padding:18px 22px;margin-bottom:16px}
.resumo-fila .sel{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.resumo-fila .prog{margin-top:14px;max-width:560px}
.resumo-fila .prog .txt{display:flex;justify-content:space-between;font-size:13px;color:var(--tinta-2);margin-bottom:6px}
.resumo-fila .prog .txt b{color:var(--tinta)}
.resumo-fila .trilho{height:8px;background:var(--linha-2);border-radius:4px;overflow:hidden}
.resumo-fila .trilho i{display:block;height:100%;background:var(--verde);border-radius:4px}
.resumo-fila .acoes{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{padding:6px 12px;border-radius:999px;border:1px solid #d3dbd8;background:#fff;color:var(--tinta-2);font-size:13px;font-weight:500;text-decoration:none;display:inline-flex;gap:6px}
.chip:hover{border-color:#b5c2be;color:var(--tinta)}
.chip.on{background:var(--tinta);border-color:var(--tinta);color:#fff}
.chip .q{font-variant-numeric:tabular-nums;opacity:.75}
@media (max-width:900px){.resumo-fila{grid-template-columns:1fr}.resumo-fila .acoes{justify-content:flex-start}}

.item{background:var(--cartao);border:1px solid var(--linha);border-radius:12px;margin-bottom:14px;overflow:hidden;scroll-margin-top:20px}
.item:target{border-color:var(--verde);box-shadow:0 0 0 3px var(--verde-claro)}
.item-cab{display:flex;gap:14px;align-items:center;padding:14px 20px;border-bottom:1px solid var(--linha-2);flex-wrap:wrap}
.item-cab .quem b{display:block;font-weight:600;font-size:14.5px}
.item-cab .quem span{color:var(--tinta-3);font-size:12.5px}
.item-cab .dir{margin-left:auto;display:flex;gap:10px;align-items:center}
.estado{font-size:12.5px;font-weight:500;display:inline-flex;align-items:center;gap:6px;color:var(--tinta-2)}
.estado::before{content:"";width:8px;height:8px;border-radius:50%;background:#b5bfbc}
.estado.pendente{color:var(--ambar)}.estado.pendente::before{background:#d99a2b}
.estado.avaliado::before{background:var(--verde)}
.estado.enviado,.estado.tratado{color:var(--verde-2)}.estado.enviado::before,.estado.tratado::before{background:var(--verde-2)}
.item-corpo{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(300px,1fr)}
@media (max-width:1000px){.item-corpo{grid-template-columns:1fr}}
.analise{padding:18px 20px 20px}
.citacao{font-family:var(--serif);font-style:italic;font-size:17px;line-height:1.45;margin:0 0 18px;padding-left:14px;border-left:3px solid var(--vermelho);color:var(--tinta)}
.citacao.sem{font-style:normal;font-family:var(--sans);font-size:13.5px;color:var(--tinta-3);border-left-color:var(--linha)}
.ia-cab{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--violeta);font-weight:600;margin-bottom:6px}
.ia-cab span{color:var(--tinta-3);font-weight:400}
.ia-motivo{font-size:16px;font-weight:600;line-height:1.4;margin:0 0 10px}
.analise .tags{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.analise p.res{margin:0 0 12px;color:var(--tinta-2);line-height:1.6;max-width:70ch}
.evid{margin:0 0 14px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px}
.evid li{font-size:13px;color:var(--tinta-2);background:#f6f8f7;border-radius:7px;padding:7px 11px;font-family:var(--serif);font-style:italic}
.sugestao{background:var(--violeta-claro);border-radius:9px;padding:11px 14px;font-size:13.5px;color:#3a2e8f;line-height:1.5}
.sugestao b{font-weight:600}
.avaliacao{padding:18px 20px 20px;background:#f9fbfa;border-left:1px solid var(--linha-2);display:flex;flex-direction:column}
@media (max-width:1000px){.avaliacao{border-left:0;border-top:1px solid var(--linha-2)}}
.avaliacao label{display:block;font-size:12.5px;font-weight:500;color:var(--tinta-2);margin:0 0 6px}
.avaliacao select,.avaliacao textarea{width:100%;font:inherit;font-size:13.5px;color:var(--tinta);border:1px solid #d3dbd8;border-radius:8px;background:#fff;padding:9px 11px}
.avaliacao select{margin-bottom:14px;padding:9px 30px 9px 11px}
.avaliacao textarea{min-height:110px;resize:vertical;margin-bottom:12px;line-height:1.5}
.avaliacao select:focus,.avaliacao textarea:focus{outline:3px solid var(--verde-foco);border-color:var(--verde)}
.avaliacao .rodape-av{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.avaliacao .quem-av{font-size:12.5px;color:var(--tinta-3)}
</style>

<section class="resumo-fila">
  <div>
    <div class="sel">
      <select class="campo" onchange="location.href=this.value" aria-label="Semana">
        <option value="<?= h(url(array('semana' => 'todas'))) ?>" <?= $semSel === '' ? 'selected' : '' ?>>Todas as semanas</option>
        <?php foreach ($semanas as $s): ?>
          <option value="<?= h(url(array('semana' => $s['s']))) ?>" <?= $semSel === $s['s'] ? 'selected' : '' ?>>Semana de <?= date('d/m/Y', strtotime($s['s'])) ?><?= $s['pend'] ? ' (' . (int)$s['pend'] . ' a avaliar)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div class="chips" role="group" aria-label="Situação">
        <a class="chip <?= $stSel === '' ? 'on' : '' ?>" href="<?= h(url(array('status' => null))) ?>">Todas <span class="q"><?= $total ?></span></a>
        <?php foreach (array('pendente' => 'A avaliar', 'avaliado' => 'Avaliadas', 'enviado' => 'Enviadas', 'tratado' => 'Tratadas') as $k => $v): if ($k === 'tratado' && !$contagem[$k]) continue; ?>
          <a class="chip <?= $stSel === $k ? 'on' : '' ?>" href="<?= h(url(array('status' => $k))) ?>"><?= $v ?> <span class="q"><?= $contagem[$k] ?></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($total): ?>
    <div class="prog">
      <div class="txt"><span><b><?= $avaliados ?> de <?= $total ?></b> notas avaliadas <?= $semSel ? 'nesta semana' : 'no total' ?></span><span><?= $progresso ?>%</span></div>
      <div class="trilho"><i style="width:<?= $progresso ?>%"></i></div>
    </div>
    <?php endif; ?>
  </div>
  <div class="acoes">
    <a class="btn" href="<?= h(url(array('csv' => 1, 'status' => 'avaliado'))) ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>Baixar avaliadas (CSV)</a>
    <?php if ($semSel !== '' && $contagem['avaliado']): ?>
      <form method="post" onsubmit="return confirm('Marcar as <?= (int)$contagem['avaliado'] ?> notas avaliadas desta semana como enviadas ao pós-vendas?')">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="enviar_semana"><input type="hidden" name="semana" value="<?= h($semSel) ?>">
        <button class="btn pri" type="submit">Marcar semana como enviada</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php if (!$itens): ?>
  <div class="painel"><div class="vazio"><?= $stSel === 'pendente' ? 'Nenhuma nota aguardando avaliação. A fila está em dia.' : 'Nenhuma nota com esses filtros. Escolha outra semana ou situação.' ?></div></div>
<?php endif; ?>

<?php foreach ($itens as $it): ?>
<article class="item" id="item-<?= (int)$it['id'] ?>">
  <header class="item-cab">
    <div class="nota n<?= (int)$it['nota'] ?>" title="Nota <?= (int)$it['nota'] ?>"><?= (int)$it['nota'] ?></div>
    <div class="quem">
      <b><?= h($it['cliente'] ? $it['cliente'] : 'Cliente sem nome') ?></b>
      <span>Atendida por <?= h($it['atendente'] ? $it['atendente'] : 'ninguém') ?>, avaliou em <?= date('d/m/Y \à\s H:i', strtotime($it['respondido_em'])) ?></span>
    </div>
    <div class="dir">
      <span class="estado <?= h($it['status']) ?>"><?= h($ROT_STATUS[$it['status']]) ?></span>
      <a class="btn" href="<?= h($it['link_conversa']) ?>" target="_blank" rel="noopener">Abrir no iTalk
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg></a>
    </div>
  </header>
  <div class="item-corpo">
    <div class="analise">
      <?php if ($it['feedback_cliente']): ?>
        <blockquote class="citacao"><?= h($it['feedback_cliente']) ?></blockquote>
      <?php else: ?>
        <p class="citacao sem">O cliente não deixou comentário.</p>
      <?php endif; ?>
      <?php if ($it['ia_motivo']): ?>
        <div class="ia-cab">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z"/></svg>
          Leitura da IA<?php if ($it['ia_confianca'] !== null): ?> <span>confiança de <?= round(100 * (float)$it['ia_confianca']) ?>%</span><?php endif; ?>
        </div>
        <p class="ia-motivo"><?= h($it['ia_motivo']) ?></p>
        <div class="tags">
          <span class="tag cat"><?= h(rotulo('CATEGORIAS', $it['ia_categoria'])) ?></span>
          <span class="tag">Responsável: <?= h(rotulo('RESPONSAVEIS', $it['ia_responsavel'])) ?></span>
          <?php if ($it['ia_gravidade'] === 'alta' || $it['ia_gravidade'] === 'media'): ?><span class="tag g-<?= h($it['ia_gravidade']) ?>"><span class="pt"></span>Gravidade <?= $it['ia_gravidade'] === 'alta' ? 'alta' : 'média' ?></span><?php endif; ?>
        </div>
        <?php if ($it['ia_resumo']): ?><p class="res"><?= h($it['ia_resumo']) ?></p><?php endif; ?>
        <?php if ($it['ia_evidencias']): ?>
          <ul class="evid"><?php foreach (explode("\n", $it['ia_evidencias']) as $ev): $ev = trim($ev, " \t\"“”"); if ($ev === '') continue; ?><li><?= h($ev) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($it['ia_sugestao']): ?><div class="sugestao"><b>Sugestão:</b> <?= h($it['ia_sugestao']) ?></div><?php endif; ?>
      <?php else: ?>
        <div class="ia-cab">Leitura da IA</div>
        <p class="res">Esta conversa entra na próxima análise, que roda a cada 15 minutos.</p>
      <?php endif; ?>
    </div>
    <form class="avaliacao" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="avaliar"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
      <?php $cl = $it['classificacao'] ? $it['classificacao'] : $it['ia_categoria']; ?>
      <label for="c<?= (int)$it['id'] ?>">Classificação</label>
      <select class="campo" style="max-width:none" id="c<?= (int)$it['id'] ?>" name="classificacao">
        <option value="">Escolha uma classificação</option>
        <?php foreach ($GLOBALS['CATEGORIAS'] as $k => $v): ?>
          <option value="<?= h($k) ?>" <?= $cl === $k ? 'selected' : '' ?>><?= h($v) ?><?= (!$it['classificacao'] && $it['ia_categoria'] === $k) ? ' (sugerida pela IA)' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <label for="o<?= (int)$it['id'] ?>">Observação para o pós-vendas</label>
      <textarea id="o<?= (int)$it['id'] ?>" name="observacao" placeholder="O que aconteceu, o que já foi feito e o que o pós-vendas precisa tratar"><?= h($it['observacao']) ?></textarea>
      <div class="rodape-av">
        <button class="btn pri" type="submit"><?= $it['status'] === 'pendente' ? 'Salvar avaliação' : 'Atualizar avaliação' ?></button>
        <?php if ($it['avaliado_em']): ?><span class="quem-av">Avaliada por <?= h(ucfirst($it['supervisor'])) ?> em <?= date('d/m \à\s H:i', strtotime($it['avaliado_em'])) ?><?= $it['enviado_em'] ? ', enviada em ' . date('d/m', strtotime($it['enviado_em'])) : '' ?></span><?php endif; ?>
      </div>
    </form>
  </div>
</article>
<?php endforeach; ?>
