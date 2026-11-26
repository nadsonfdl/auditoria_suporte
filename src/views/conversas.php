<?php
$ROT_RES = array('sim' => 'Resolvida', 'parcial' => 'Em parte', 'nao' => 'Não resolvida', 'incerto' => 'Incerta');
$COR_RES = array('sim' => 'var(--pri)', 'parcial' => '#d99a2b', 'nao' => 'var(--vermelho)', 'incerto' => '#b4bdcb');
$T = $DEC['total'];
$conclusivas = $T['sim'] + $T['parcial'] + $T['nao'];
$taxa = $conclusivas ? 100.0 * $T['sim'] / $conclusivas : null;
$taxaComParcial = $conclusivas ? 100.0 * ($T['sim'] + $T['parcial']) / $conclusivas : null;
$temFiltro = count(array_filter($X, 'strlen')) > 0;

/** Barra 100% empilhada com os quatro vereditos; cada parte filtra a lista */
function barraRes($d, $extra = array(), $alta = false)
{
    global $ROT_RES, $COR_RES;
    $tot = max(1, $d['sim'] + $d['parcial'] + $d['nao'] + $d['incerto']);
    $h = '<div class="pilha' . ($alta ? ' alta' : '') . '">';
    foreach (array('sim', 'parcial', 'nao', 'incerto') as $k) {
        if (!$d[$k]) {
            continue;
        }
        $pc = 100.0 * $d[$k] / $tot;
        $h .= '<a href="' . h(url(array_merge($extra, array('res' => $k, 'pg' => null)))) . '#lista" style="width:' . round($pc, 2) . '%;background:' . $COR_RES[$k] . '" '
            . 'title="' . h($ROT_RES[$k]) . ': ' . num($d[$k]) . ' (' . pct($pc) . ')">' . ($pc >= 9 ? '<span>' . pct($pc, 0) . '</span>' : '') . '</a>';
    }
    return $h . '</div>';
}
function taxaRes($d)
{
    $c = $d['sim'] + $d['parcial'] + $d['nao'];
    return $c ? 100.0 * $d['sim'] / $c : null;
}
?>
<style>
.volta{display:inline-flex;gap:6px;align-items:center;color:var(--tinta-2);text-decoration:none;font-size:13px;margin:-8px 0 14px}
.volta:hover{color:var(--tinta)}
.intro{font-family:var(--serif);font-size:clamp(19px,1.7vw,23px);line-height:1.4;margin:0 0 18px;max-width:62ch}
.pilha{display:flex;height:14px;border-radius:7px;overflow:hidden;background:var(--linha-2);gap:2px}
.pilha.alta{height:34px;border-radius:9px}
.pilha a{display:flex;align-items:center;justify-content:center;min-width:3px;color:#fff;font-size:12px;font-weight:600;text-decoration:none;transition:filter .15s}
.pilha a:hover{filter:brightness(1.1)}
.leg-res{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:16px}
.leg-res a{display:block;text-decoration:none;color:inherit;padding:10px 12px;border-radius:10px;border:1px solid var(--linha);background:#fff}
.leg-res a:hover{border-color:#c6cfdc}
.leg-res a.on{border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-claro)}
.leg-res .t{display:flex;align-items:center;gap:7px;font-weight:600;font-size:13px}
.leg-res .t i{width:10px;height:10px;border-radius:3px;flex:none}
.leg-res .v{font-size:22px;font-weight:600;letter-spacing:-.4px;margin:4px 0 2px;font-variant-numeric:tabular-nums}
.leg-res .v small{font-size:13px;color:var(--tinta-3);font-weight:500}
.leg-res p{margin:0;font-size:12.5px;color:var(--tinta-2);line-height:1.45}
.nota-conta{margin-top:14px;padding:11px 14px;border-radius:10px;background:var(--pri-claro);color:var(--pri-2);font-size:13.5px}
.linhas-pilha{display:flex;flex-direction:column;gap:14px}
.linha-pilha .cab{display:flex;justify-content:space-between;gap:10px;font-size:13px;margin-bottom:6px}
.linha-pilha .cab b{font-weight:600}
.linha-pilha .cab span{color:var(--tinta-3);font-variant-numeric:tabular-nums}
.precisao .grande{font-size:30px;font-weight:600;letter-spacing:-.6px}
.chips-f{display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;margin:4px 0 14px}
.chips-f .grupo{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.chips-f .rot{font-size:12px;color:var(--tinta-3);margin-right:2px}
.chip{padding:5px 11px;border-radius:999px;border:1px solid #d3dae5;background:#fff;color:var(--tinta-2);font-size:12.5px;font-weight:500;text-decoration:none;white-space:nowrap}
.chip:hover{border-color:#b4bfce;color:var(--tinta)}
.chip.on{background:var(--tinta);border-color:var(--tinta);color:#fff}
.conv{background:var(--cartao);border:1px solid var(--linha);border-radius:12px;padding:16px 18px;margin-bottom:12px;scroll-margin-top:80px}
.conv:target{border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-claro)}
.conv-cab{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap}
.conv-cab h4{margin:0;font-size:15px;font-weight:600;flex:1;min-width:200px}
.res-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.res-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.res-sim{background:var(--pri-claro);color:var(--pri-2)}.res-parcial{background:var(--ambar-claro);color:var(--ambar)}.res-nao{background:var(--vermelho-claro);color:var(--vermelho-2)}.res-incerto{background:var(--linha-2);color:var(--tinta-2)}
.conv .meta{display:flex;flex-wrap:wrap;gap:6px 12px;color:var(--tinta-3);font-size:12.5px;margin:6px 0 10px}
.conv .resumo{margin:0 0 8px;color:var(--tinta-2);line-height:1.55;max-width:90ch}
.conv .falha{font-size:13px;color:var(--tinta-2);margin:4px 0}
.conv .falha i{font-family:var(--serif)}
.conv-pe{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--linha-2)}
.validar{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.validar span{font-size:12.5px;color:var(--tinta-2);margin-right:4px}
.validar button{padding:6px 11px;font-size:12.5px}
.validar button.on{background:var(--tinta);color:#fff;border-color:var(--tinta)}
.validado{font-size:12.5px;color:#0a6b3c}
.paginas{display:flex;gap:8px;align-items:center;justify-content:center;margin:18px 0}
.paginas span{color:var(--tinta-3);font-size:13px}
@media (max-width:900px){.leg-res{grid-template-columns:repeat(2,minmax(0,1fr))}.conv-pe{flex-direction:column;align-items:flex-start}}
</style>

<a class="volta" href="<?= h(url(array('p' => 'qualidade'), array('res', 'risco', 'quem', 'nota', 'semresp', 'validado', 'motivo', 'falha', 'pg'))) ?>">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 6l-6 6 6 6"/></svg>Voltar para Qualidade</a>

<?php if (!$T['total']): ?>
  <div class="painel"><div class="vazio">Nenhuma conversa auditada com esses filtros. Escolha um período maior ou limpe os filtros.</div></div>
<?php else: ?>

<p class="intro">
  <?php if ($temFiltro): ?>Nesta seleção, <?php endif; ?>
  A IA leu <?= num($T['total']) ?> conversas e conseguiu concluir <?= num($conclusivas) ?>.
  <?= num($T['sim']) ?> tiveram evidência clara de solução (<?= pct($taxa) ?>)<?php if ($T['parcial']): ?>, e outras <?= num($T['parcial']) ?> foram respondidas, mas sem confirmação de que o problema acabou<?php endif; ?>.
</p>

<div class="grade">
  <div class="painel c12" data-tour="1" data-tour-titulo="De onde vem o número" data-tour-texto="Cada conversa recebe um de quatro vereditos. Só as resolvidas contam a favor; as incertas ficam fora da conta. Clique em qualquer parte para ver as conversas.">
    <h3 class="cab-painel">Como chegamos aos <?= pct($taxa) ?> <?= explica('resolucao_real') ?></h3>
    <p class="leg">Resolvidas de fato ÷ conversas que a IA conseguiu concluir (resolvidas + em parte + não resolvidas)</p>
    <?= barraRes($T, array(), true) ?>
    <div class="leg-res">
      <?php
      $desc = array(
        'sim'     => 'Há evidência de solução: o cliente confirmou ou a resposta resolve claramente o pedido.',
        'parcial' => 'O suporte respondeu, mas o cliente não confirmou ou ficou faltando parte do pedido. Conta como não resolvida.',
        'nao'     => 'O problema ficou sem solução: encerrada sem retorno, cliente sem resposta ou pedido não atendido.',
        'incerto' => 'Não dá para concluir pela conversa (muito curta, sem pedido claro). Fica fora da conta.',
      );
      foreach (array('sim', 'parcial', 'nao', 'incerto') as $k): $pc = $T['total'] ? 100.0 * $T[$k] / $T['total'] : 0; ?>
        <a href="<?= h(url(array('res' => $X['res'] === $k ? null : $k, 'pg' => null))) ?>#lista" class="<?= $X['res'] === $k ? 'on' : '' ?>">
          <div class="t"><i style="background:<?= $COR_RES[$k] ?>"></i><?= $ROT_RES[$k] ?></div>
          <div class="v"><?= num($T[$k]) ?> <small><?= pct($pc, 0) ?></small></div>
          <p><?= h($desc[$k]) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($T['parcial'] && $taxaComParcial !== null): ?>
      <div class="nota-conta">Se as conversas respondidas sem confirmação ("em parte") contassem como resolvidas, a taxa seria <b><?= pct($taxaComParcial) ?></b>. A diferença mostra quanto do número depende de o cliente confirmar a solução.</div>
    <?php endif; ?>
  </div>

  <div class="painel c6" data-tour="2" data-tour-titulo="Cliente x IA" data-tour-texto="Compara a nota que o cliente deu com o veredito da IA. Se muitas conversas com nota 4 ou 5 aparecem como não resolvidas, a régua da IA está rígida demais e vale validar algumas.">
    <h3 class="cab-painel">O que o cliente disse e o que a IA concluiu <?= explica('cliente_ia') ?></h3>
    <p class="leg">Veredito da IA conforme a nota do cliente</p>
    <div class="linhas-pilha">
      <?php foreach (array('boa' => array('Cliente deu nota 4 ou 5', 'com'), 'baixa' => array('Cliente deu nota 1 a 3', 'com'), 'sem' => array('Cliente não avaliou', 'sem')) as $g => $info):
        if (empty($DEC['nota'][$g])) continue; $d = $DEC['nota'][$g]; ?>
        <div class="linha-pilha">
          <div class="cab"><b><?= $info[0] ?></b><span><?= num($d['total']) ?> conversas, <?= pct(taxaRes($d)) ?> resolvidas</span></div>
          <?= barraRes($d, array('nota' => $info[1])) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="painel c6">
    <h3 class="cab-painel">Quem atendeu <?= explica('guta_vs_humano') ?></h3>
    <p class="leg">Veredito conforme a conversa teve ou não atendente humano</p>
    <div class="linhas-pilha">
      <?php foreach (array('guta' => 'Só a Guta', 'humano' => 'Com atendente') as $g => $rot): if (empty($DEC['quem'][$g])) continue; $d = $DEC['quem'][$g]; ?>
        <div class="linha-pilha">
          <div class="cab"><b><?= $rot ?></b><span><?= num($d['total']) ?> conversas, <?= pct(taxaRes($d)) ?> resolvidas</span></div>
          <?= barraRes($d, array('quem' => $g)) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="painel c7">
    <h3 class="cab-painel">Por assunto <?= explica('motivo_contato') ?></h3>
    <p class="leg">Onde a resolução é mais baixa</p>
    <div class="linhas-pilha">
      <?php $i = 0; foreach ($DEC['assuntos'] as $m => $d): if (++$i > 8) break; ?>
        <div class="linha-pilha">
          <div class="cab"><b><?= h(isset($GLOBALS['MOTIVOS_CONTATO'][$m]) ? $GLOBALS['MOTIVOS_CONTATO'][$m] : ucfirst((string)$m)) ?></b><span><?= num($d['total']) ?> conversas, <?= pct(taxaRes($d)) ?> resolvidas</span></div>
          <?= barraRes($d, array('motivo' => $m)) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="painel c5">
    <h3 class="cab-painel">Por que não foram resolvidas <?= explica('falhas') ?></h3>
    <p class="leg">Principal falha nas conversas não resolvidas ou resolvidas em parte</p>
    <?php if ($DEC['falhas']): $mx = (int)$DEC['falhas'][0]['n']; ?>
      <div class="hbar">
        <?php foreach ($DEC['falhas'] as $f): ?>
          <div class="t"><a href="<?= h(url(array('falha' => $f['k'], 'pg' => null))) ?>#lista" style="color:inherit;text-decoration:none"><?= h(rotulo('CATEGORIAS', $f['k'])) ?></a></div>
          <div class="b"><i style="width:<?= round(100 * $f['n'] / $mx, 1) ?>%;background:var(--vermelho)"></i></div><div class="n"><?= num($f['n']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?><div class="vazio">Sem falhas registradas.</div><?php endif; ?>

    <div class="precisao" style="margin-top:22px;padding-top:18px;border-top:1px solid var(--linha-2)" data-tour="3" data-tour-titulo="Precisão da IA" data-tour-texto="Quando alguém valida uma conversa, a avaliação humana passa a valer no indicador. Aqui aparece quanto a IA acerta.">
      <h3 class="cab-painel">Precisão da IA <?= explica('precisao_ia') ?></h3>
      <?php if ((int)$PREC['n']): ?>
        <div class="grande"><?= pct(100 * $PREC['concorda'] / $PREC['n'], 0) ?></div>
        <p class="leg" style="margin:2px 0 8px">de acerto em <?= num($PREC['n']) ?> conversas validadas por pessoas.
          <?php if ((int)$PREC['ia_dura']): ?> Em <?= num($PREC['ia_dura']) ?> a IA foi mais rígida que a pessoa.<?php endif; ?>
          <?php if ((int)$PREC['ia_branda']): ?> Em <?= num($PREC['ia_branda']) ?> foi mais branda.<?php endif; ?></p>
        <a class="chip" href="<?= h(url(array('validado' => 'discorda', 'pg' => null))) ?>#lista">Ver discordâncias</a>
      <?php else: ?>
        <p class="leg" style="margin:4px 0 10px">Ninguém validou conversas ainda. Valide de 20 a 30 (de preferência as não resolvidas) para medir se a IA está acertando.</p>
      <?php endif; ?>
      <a class="chip" href="<?= h(url(array('validado' => 'nao', 'res' => 'nao', 'pg' => null))) ?>#lista">Validar conversas não resolvidas</a>
    </div>
  </div>
</div>

<h2 class="titulo-secao" id="lista" style="margin-top:30px"><span style="font-family:var(--serif);font-weight:500;font-size:21px">Conversas</span>
  <span style="font-size:13px;color:var(--tinta-3);font-family:var(--sans)"><?= num($TOTAL_CONV) ?> com esses filtros</span></h2>
<div class="chips-f">
  <div class="grupo"><span class="rot">Resultado</span>
    <a class="chip <?= $X['res'] === '' ? 'on' : '' ?>" href="<?= h(url(array('res' => null, 'pg' => null))) ?>#lista">Todos</a>
    <?php foreach ($ROT_RES as $k => $v): ?><a class="chip <?= $X['res'] === $k ? 'on' : '' ?>" href="<?= h(url(array('res' => $k, 'pg' => null))) ?>#lista"><?= $v ?></a><?php endforeach; ?>
  </div>
  <div class="grupo"><span class="rot">Atendimento</span>
    <a class="chip <?= $X['quem'] === '' ? 'on' : '' ?>" href="<?= h(url(array('quem' => null, 'pg' => null))) ?>#lista">Todos</a>
    <a class="chip <?= $X['quem'] === 'guta' ? 'on' : '' ?>" href="<?= h(url(array('quem' => 'guta', 'pg' => null))) ?>#lista">Só a Guta</a>
    <a class="chip <?= $X['quem'] === 'humano' ? 'on' : '' ?>" href="<?= h(url(array('quem' => 'humano', 'pg' => null))) ?>#lista">Com atendente</a>
  </div>
  <div class="grupo"><span class="rot">Nota</span>
    <a class="chip <?= $X['nota'] === '' ? 'on' : '' ?>" href="<?= h(url(array('nota' => null, 'pg' => null))) ?>#lista">Todas</a>
    <a class="chip <?= $X['nota'] === 'com' ? 'on' : '' ?>" href="<?= h(url(array('nota' => 'com', 'pg' => null))) ?>#lista">Com nota</a>
    <a class="chip <?= $X['nota'] === 'sem' ? 'on' : '' ?>" href="<?= h(url(array('nota' => 'sem', 'pg' => null))) ?>#lista">Sem nota</a>
  </div>
  <div class="grupo"><span class="rot">Mais</span>
    <a class="chip <?= $X['semresp'] ? 'on' : '' ?>" href="<?= h(url(array('semresp' => $X['semresp'] ? null : '1', 'pg' => null))) ?>#lista">Cliente sem resposta</a>
    <a class="chip <?= $X['risco'] === 'alto' ? 'on' : '' ?>" href="<?= h(url(array('risco' => $X['risco'] === 'alto' ? null : 'alto', 'pg' => null))) ?>#lista">Risco alto</a>
    <a class="chip <?= $X['validado'] === 'nao' ? 'on' : '' ?>" href="<?= h(url(array('validado' => $X['validado'] === 'nao' ? null : 'nao', 'pg' => null))) ?>#lista">Ainda não validadas</a>
    <?php if ($temFiltro): ?><a class="chip" href="<?= h(url(array(), array('res', 'risco', 'quem', 'nota', 'semresp', 'validado', 'motivo', 'falha', 'pg'))) ?>#lista">Limpar filtros</a><?php endif; ?>
  </div>
</div>

<?php foreach ($CONVS as $c):
  $falhas = json_decode((string)$c['falhas'], true);
  $guta = ($c['teve_humano'] === false || $c['teve_humano'] === 'f');
  $semResp = ($c['cliente_sem_resposta'] === true || $c['cliente_sem_resposta'] === 't'); ?>
  <article class="conv" id="conv-<?= (int)$c['conversation_id'] ?>">
    <div class="conv-cab">
      <h4><?= h($c['assunto'] ? $c['assunto'] : 'Conversa ' . $c['conversation_id']) ?></h4>
      <span class="res-badge res-<?= h($c['resolvido']) ?>"><?= h($ROT_RES[$c['resolvido']]) ?><?= $c['humano_resolvido'] ? ' (validado)' : '' ?></span>
    </div>
    <div class="meta">
      <span>#<?= (int)$c['conversation_id'] ?></span>
      <span><?= date('d/m/Y H:i', strtotime($c['criada_em'])) ?></span>
      <span><?= $guta ? 'Só a Guta' : h($c['atendente'] ? $c['atendente'] : 'Atendente') ?></span>
      <span><?= h(isset($GLOBALS['MOTIVOS_CONTATO'][$c['motivo_contato']]) ? $GLOBALS['MOTIVOS_CONTATO'][$c['motivo_contato']] : '') ?></span>
      <span>Qualidade <?= $c['nota_qa'] === null ? '—' : (int)$c['nota_qa'] ?></span>
      <span><?= $c['nota_csat'] === null ? 'Sem nota do cliente' : 'Nota do cliente: ' . (int)$c['nota_csat'] ?></span>
      <?php if ($semResp): ?><span style="color:var(--vermelho-2)">Cliente ficou sem resposta</span><?php endif; ?>
      <?php if ($c['maior_espera_min'] !== null && (float)$c['maior_espera_min'] >= 30): ?><span>Maior espera <?= duracao($c['maior_espera_min']) ?></span><?php endif; ?>
    </div>
    <?php if ($c['resumo']): ?><p class="resumo"><?= h($c['resumo']) ?></p><?php endif; ?>
    <?php if (is_array($falhas)): foreach (array_slice($falhas, 0, 2) as $f): ?>
      <div class="falha"><b><?= h(isset($GLOBALS['QA_CRITERIOS'][$f['criterio']]) ? $GLOBALS['QA_CRITERIOS'][$f['criterio']] : ucfirst((string)$f['criterio'])) ?>:</b> <?= h($f['descricao']) ?><?= !empty($f['trecho']) ? ' <i>“' . h($f['trecho']) . '”</i>' : '' ?></div>
    <?php endforeach; endif; ?>
    <div class="conv-pe">
      <a class="btn" href="<?= h(rtrim(conf('italk_url'), '/') . '/app/accounts/3/conversations/' . (int)$c['conversation_id']) ?>" target="_blank" rel="noopener">Abrir no iTalk</a>
      <form class="validar" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="validar"><input type="hidden" name="id" value="<?= (int)$c['conversation_id'] ?>">
        <span><?= $c['humano_resolvido'] ? 'Validado por ' . h(ucfirst($c['validado_por'])) . '. A IA disse "' . h(strtolower($ROT_RES[$c['resolvido_ia']])) . '". Corrigir:' : 'A IA está certa? Para você, foi:' ?></span>
        <?php foreach (array('sim' => 'Resolvida', 'parcial' => 'Em parte', 'nao' => 'Não resolvida') as $k => $v): ?>
          <button class="btn <?= ($c['humano_resolvido'] ? $c['humano_resolvido'] === $k : false) ? 'on' : '' ?>" name="humano" value="<?= $k ?>"><?= $v ?></button>
        <?php endforeach; ?>
      </form>
      <?php if ($c['humano_resolvido']): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="desfazer_validacao"><input type="hidden" name="id" value="<?= (int)$c['conversation_id'] ?>">
          <button class="btn" style="padding:6px 11px;font-size:12.5px">Desfazer</button></form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>

<?php $paginas = (int)ceil($TOTAL_CONV / 30); if ($paginas > 1): ?>
  <nav class="paginas" aria-label="Páginas">
    <?php if ($PG > 1): ?><a class="btn" href="<?= h(url(array('pg' => $PG - 1))) ?>#lista">Anteriores</a><?php endif; ?>
    <span>Página <?= $PG ?> de <?= $paginas ?></span>
    <?php if ($PG < $paginas): ?><a class="btn" href="<?= h(url(array('pg' => $PG + 1))) ?>#lista">Próximas</a><?php endif; ?>
  </nav>
<?php endif; ?>
<?php endif; ?>
