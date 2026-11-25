<?php
// Aba Configurações (somente administradores). Cada seção é um formulário próprio.
function cfInicio($secao, $titulo, $desc)
{
    $GLOBALS['CF_SECAO'] = $secao;
    echo '<section class="cf-secao painel" id="' . h($secao) . '"><form method="post" autocomplete="off" id="form_' . h($secao) . '">'
       . '<input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="acao" value="salvar_config">'
       . '<input type="hidden" name="_secao" value="' . h($secao) . '">'
       . '<div class="cf-cab"><div><h2>' . h($titulo) . '</h2><p>' . $desc . '</p></div>';
}
function cfCabFim($ligar = null)
{
    if ($ligar) {
        echo cfChave($ligar, 'Ligado', true);
    }
    echo '</div><div class="cf-corpo">';
}
function cfFim($extra = '')
{
    // o rodapé fica fora do <form> para os formulários de teste não ficarem aninhados
    echo '</div></form><div class="cf-rodape"><button class="btn pri" type="submit" form="form_' . h($GLOBALS['CF_SECAO']) . '">Salvar</button>' . $extra . '</div></section>';
}
function cfChave($k, $rotulo, $grande = false)
{
    return '<label class="chave' . ($grande ? ' grande' : '') . '"><input type="hidden" name="_b[' . h($k) . ']" value="1">'
         . '<input type="checkbox" name="' . h($k) . '" value="1" ' . (confLigado($k) ? 'checked' : '') . '><span class="trilho"></span>'
         . ($grande ? '<span class="t-on">Ligado</span><span class="t-off">Desligado</span>' : '<span>' . h($rotulo) . '</span>') . '</label>';
}
function cfCampo($k, $rotulo, $ajuda = '', $tipo = 'text', $extra = '')
{
    return '<div class="campo-cf"><label for="f_' . h($k) . '">' . h($rotulo) . '</label>'
         . '<input id="f_' . h($k) . '" class="campo" type="' . $tipo . '" name="' . h($k) . '" value="' . h(conf($k)) . '" ' . $extra . '>'
         . ($ajuda ? '<small>' . $ajuda . '</small>' : '') . '</div>';
}
function cfTexto($k, $rotulo, $ajuda = '')
{
    return '<div class="campo-cf largo"><label for="f_' . h($k) . '">' . h($rotulo) . '</label>'
         . '<textarea id="f_' . h($k) . '" class="campo" name="' . h($k) . '" rows="2">' . h(conf($k)) . '</textarea>'
         . ($ajuda ? '<small>' . $ajuda . '</small>' : '') . '</div>';
}
function cfSegredo($k, $rotulo, $ajuda = '')
{
    $tem = conf($k) !== '';
    return '<div class="campo-cf"><label for="f_' . h($k) . '">' . h($rotulo) . ($tem ? ' <span class="ok-tag">configurado</span>' : '') . '</label>'
         . '<input id="f_' . h($k) . '" class="campo" type="password" name="' . h($k) . '" value="" placeholder="' . ($tem ? 'Deixe em branco para manter' : 'Não configurado') . '" autocomplete="new-password">'
         . ($tem ? '<label class="limpar"><input type="checkbox" name="_limpar[' . h($k) . ']" value="1"> apagar</label>' : '')
         . ($ajuda ? '<small>' . $ajuda . '</small>' : '') . '</div>';
}
function cfSelect($k, $rotulo, $opcoes, $ajuda = '', $extra = '')
{
    $h = '<div class="campo-cf"><label for="f_' . h($k) . '">' . h($rotulo) . '</label><select id="f_' . h($k) . '" class="campo" name="' . h($k) . '" ' . $extra . '>';
    foreach ($opcoes as $v => $t) {
        $h .= '<option value="' . h($v) . '" ' . ((string)conf($k) === (string)$v ? 'selected' : '') . '>' . h($t) . '</option>';
    }
    return $h . '</select>' . ($ajuda ? '<small>' . $ajuda . '</small>' : '') . '</div>';
}
function cfTeste($acao, $rotulo, $placeholder, $tipo = 'text')
{
    return '<form method="post" class="cf-teste"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="acao" value="' . h($acao) . '">'
         . '<input class="campo" type="' . $tipo . '" name="destino" placeholder="' . h($placeholder) . '"><button class="btn" type="submit">' . h($rotulo) . '</button></form>';
}
$dias = array(1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo');
$horas = array();
for ($i = 6; $i <= 20; $i++) {
    $horas[$i] = sprintf('%02d:00', $i);
}
$inboxOpts = array('' => 'Escolha a caixa de WhatsApp');
foreach ($OPC['caixas'] as $o) {
    $inboxOpts[$o['id']] = $o['name'];
}
$status = array(
    'E-mail (SMTP)'        => smtpConfigurado(),
    'WhatsApp'             => waConfigurado(),
    'IA'                   => (bool)cfg('ia_api_key'),
    'Alerta de nota baixa' => confLigado('alerta_ativo'),
    'Resumo semanal'       => confLigado('resumo_ativo'),
    'Auditoria por IA'     => confLigado('qa_ativo'),
);
?>
<style>
.cf-grade{display:grid;grid-template-columns:220px minmax(0,1fr);gap:24px;align-items:start}
.cf-indice{position:sticky;top:20px;display:flex;flex-direction:column;gap:2px;font-size:13.5px}
.cf-indice a{padding:7px 10px;border-radius:7px;color:var(--tinta-2);text-decoration:none}
.cf-indice a:hover{background:#e8edf4;color:var(--tinta)}
.cf-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:16px}
.cf-status div{background:var(--cartao);border:1px solid var(--linha);border-radius:10px;padding:11px 14px;font-size:13px;display:flex;align-items:center;gap:8px}
.cf-status i{width:9px;height:9px;border-radius:50%;background:#b4bdcb;flex:none}
.cf-status .on i{background:#0aac23}
.cf-status span{color:var(--tinta-3);margin-left:auto;font-size:12px}
.cf-secao{margin-bottom:16px;padding:0;scroll-margin-top:16px}
.cf-cab{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;padding:18px 22px;border-bottom:1px solid var(--linha-2)}
.cf-cab h2{font-family:var(--serif);font-weight:500;font-size:19px;margin:0 0 4px}
.cf-cab p{margin:0;color:var(--tinta-2);max-width:70ch;font-size:13.5px}
.cf-corpo{padding:18px 22px;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px 20px;align-items:start}
.cf-corpo .largo{grid-column:1/-1}
.cf-corpo .linha-chaves{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:10px 26px}
.cf-rodape{padding:14px 22px;border-top:1px solid var(--linha-2);display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#fafbfd;border-radius:0 0 12px 12px}
.campo-cf label{display:block;font-size:12.5px;font-weight:500;color:var(--tinta-2);margin-bottom:5px}
.campo-cf .campo{width:100%;max-width:none}
.campo-cf input.campo{background-image:none;padding-right:11px}
.campo-cf textarea.campo{background-image:none;padding:8px 11px;resize:vertical;font-family:var(--sans)}
.campo-cf small{display:block;color:var(--tinta-3);font-size:12px;margin-top:4px;line-height:1.45}
.campo-cf .limpar{display:inline-flex;gap:5px;align-items:center;font-weight:400;font-size:12px;color:var(--tinta-3);margin-top:4px}
.ok-tag{font-size:11px;font-weight:600;color:#0a6b3c;background:#e6f4e8;padding:1px 7px;border-radius:999px;margin-left:4px}
.chave{display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:13.5px;user-select:none}
.chave input{position:absolute;opacity:0;width:1px;height:1px}
.chave .trilho{width:36px;height:20px;border-radius:999px;background:#d3dae5;position:relative;transition:background .2s;flex:none}
.chave .trilho::after{content:"";position:absolute;top:2px;left:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.2);transition:transform .2s}
.chave input:checked + .trilho{background:var(--pri)}
.chave input:checked + .trilho::after{transform:translateX(16px)}
.chave input:focus-visible + .trilho{outline:3px solid var(--pri-foco);outline-offset:2px}
.chave.grande{font-weight:600;white-space:nowrap}
.chave .t-on{display:none}.chave input:checked ~ .t-on{display:inline}.chave input:checked ~ .t-off{display:none}.chave .t-off{color:var(--tinta-3)}
.cf-teste{display:flex;gap:8px;align-items:center;margin-left:auto;flex-wrap:wrap}
.cf-teste .campo{width:230px;max-width:none;background-image:none}
.provedor{grid-column:1/-1;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px 20px;padding:14px;border:1px dashed #d3dae5;border-radius:10px}
.provedor[hidden]{display:none}
.provedor .nota{grid-column:1/-1;font-size:12.5px;color:var(--tinta-2);margin:0}
.log{width:100%;border-collapse:collapse;font-size:12.5px}
.log th{text-align:left;color:var(--tinta-3);font-weight:500;padding:0 8px 8px;border-bottom:1px solid var(--linha)}
.log td{padding:8px;border-bottom:1px solid var(--linha-2);vertical-align:top}
.log .ok{color:#0a6b3c;font-weight:600}.log .falha{color:var(--vermelho-2);font-weight:600}
@media (max-width:980px){.cf-grade{grid-template-columns:1fr}.cf-indice{position:static;flex-direction:row;flex-wrap:wrap}}
</style>

<div class="cf-status" data-tour="1" data-tour-titulo="O que está funcionando" data-tour-texto="Resumo do que está configurado e ligado. Verde é ligado e pronto para uso.">
  <?php foreach ($status as $rot => $on): ?><div class="<?= $on ? 'on' : '' ?>"><i></i><?= h($rot) ?><span><?= $on ? 'ligado' : 'desligado' ?></span></div><?php endforeach; ?>
</div>

<div class="cf-grade">
<nav class="cf-indice" aria-label="Seções das configurações">
  <a href="#geral">Geral</a><a href="#metas">Metas</a><a href="#alerta">Alerta de nota baixa</a><a href="#resumo">Resumo semanal</a>
  <a href="#posvendas">Pós-vendas</a><a href="#qualidade">Auditoria por IA</a><a href="#temas">Problemas recorrentes</a>
  <a href="#email">E-mail (SMTP)</a><a href="#whatsapp">WhatsApp</a><a href="#envios">Histórico de envios</a>
</nav>
<div>

<?php cfInicio('geral', 'Geral', 'Endereço do painel usado nos links dos e-mails e mensagens, e o que conta como nota baixa.'); cfCabFim(); ?>
  <?= cfCampo('painel_url', 'Endereço do painel', 'Ex.: https://appiasuporte.igut.med.br/auditoria_suporte/painel.php', 'url') ?>
  <?= cfSelect('csat_nota_corte', 'Nota baixa é até', array('1' => 'Nota 1', '2' => 'Nota 2', '3' => 'Nota 3'), 'Notas até este valor entram na auditoria.') ?>
  <div class="linha-chaves"><?= cfChave('guta_ativo', 'Mostrar o indicador da Guta no Diagnóstico') ?></div>
<?php cfFim(); ?>

<?php cfInicio('metas', 'Metas', 'Cada indicador mostra se está dentro, perto (até 5% de distância) ou fora da meta.'); cfCabFim('metas_ativo'); ?>
  <?= cfCampo('meta_csat', 'CSAT médio mínimo', 'De 1 a 5. Ex.: 4,5', 'text', 'inputmode="decimal"') ?>
  <?= cfCampo('meta_1a_resposta_min', '1ª resposta máxima (minutos)', 'Mediana do tempo até a primeira resposta.', 'number', 'min="1"') ?>
  <?= cfCampo('meta_sem_reabrir_pct', 'Resolvidas sem reabrir, mínimo (%)', '', 'number', 'min="0" max="100"') ?>
  <?= cfCampo('meta_resolucao_real_pct', 'Resolvidas de fato pela IA, mínimo (%)', 'Mercado: 70% é a média, 80% é excelente.', 'number', 'min="0" max="100"') ?>
  <?= cfCampo('meta_qa', 'Nota de qualidade mínima (0 a 100)', '', 'number', 'min="0" max="100"') ?>
  <?= cfCampo('meta_notas_baixas_pct', 'Notas baixas, máximo (% das avaliações)', '', 'number', 'min="0" max="100"') ?>
<?php cfFim(); ?>

<?php cfInicio('alerta', 'Alerta de nota baixa', 'Avisa na hora (em até 5 minutos) quando um cliente dá nota baixa, com o comentário, a leitura da IA e o link da conversa. Só alerta notas que chegarem depois de ligado.'); cfCabFim('alerta_ativo'); ?>
  <?= cfSelect('alerta_nota_max', 'Alertar notas até', array('1' => 'Nota 1', '2' => 'Nota 2')) ?>
  <div class="linha-chaves"><?= cfChave('alerta_email', 'Por e-mail') ?><?= cfChave('alerta_whatsapp', 'Por WhatsApp') ?></div>
  <?= cfTexto('alerta_emails', 'E-mails que recebem', 'Separe por vírgula. Ex.: supervisor@igut.com.br, marcelo@igut.com.br') ?>
  <?= cfTexto('alerta_whatsapps', 'WhatsApps que recebem', 'Com DDD, separados por vírgula. Ex.: 11999998888') ?>
<?php cfFim(); ?>

<?php cfInicio('resumo', 'Resumo semanal', 'Toda semana, um e-mail com os números da semana anterior comparados à retrasada: conversas, resolução, 1ª resposta, CSAT, notas baixas, principais motivos e pendências.'); cfCabFim('resumo_ativo'); ?>
  <?= cfSelect('resumo_dia', 'Dia do envio', $dias) ?>
  <?= cfSelect('resumo_hora', 'A partir de', $horas) ?>
  <?= cfTexto('resumo_emails', 'E-mails que recebem', 'Ex.: marcelo@igut.com.br') ?>
  <div class="linha-chaves"><?= cfChave('resumo_whatsapp', 'Também enviar um resumo curto por WhatsApp') ?></div>
  <?= cfTexto('resumo_whatsapps', 'WhatsApps que recebem o resumo', '') ?>
<?php cfFim(cfTeste('testar_resumo', 'Enviar resumo de teste', 'e-mail para o teste', 'email')); ?>

<?php cfInicio('posvendas', 'Pós-vendas', 'Quando o supervisor clica em "Enviar ao pós-vendas" na auditoria, as notas avaliadas da semana vão por e-mail, com a planilha anexa. O pós-vendas registra o retorno de cada caso no painel.'); cfCabFim(); ?>
  <?= cfTexto('posvendas_emails', 'E-mails do pós-vendas', 'Sem e-mail configurado, o botão só marca como enviado e a planilha é baixada manualmente.') ?>
  <div class="linha-chaves"><?= cfChave('posvendas_whatsapp', 'Avisar também por WhatsApp') ?></div>
  <?= cfTexto('posvendas_whatsapps', 'WhatsApps do pós-vendas', '') ?>
<?php cfFim(); ?>

<?php cfInicio('qualidade', 'Auditoria de qualidade por IA', 'A IA lê as conversas encerradas, mesmo sem nota do cliente, e avalia resolução real, critérios de qualidade, falhas e risco de insatisfação. '
    . ($QA_STATUS['ok'] ? num($QA_STATUS['ok']) . ' conversas auditadas até agora' . ($QA_STATUS['ultima'] ? ', a última em ' . date('d/m \à\s H:i', strtotime($QA_STATUS['ultima'])) : '') . '.' : 'Nenhuma conversa auditada ainda.')); cfCabFim('qa_ativo'); ?>
  <?= cfCampo('qa_lote', 'Conversas por execução', 'O agendador roda a cada 5 minutos. 15 por execução ≈ 4.300 por dia.', 'number', 'min="1" max="100"') ?>
  <?= cfCampo('qa_amostra_pct', 'Amostra (%)', '100 = todas as conversas encerradas.', 'number', 'min="1" max="100"') ?>
  <?= cfCampo('qa_dias', 'Analisar conversas dos últimos (dias)', '', 'number', 'min="1" max="120"') ?>
  <div class="linha-chaves"><?= cfChave('qa_para_fila', 'Conversas com risco alto e sem nota entram na auditoria do supervisor') ?></div>
<?php cfFim('<form method="post" style="margin-left:auto"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="acao" value="rodar_qa"><button class="btn" type="submit">Auditar 5 conversas agora</button></form>'); ?>

<?php cfInicio('temas', 'Problemas recorrentes de produto', 'Uma vez por dia a IA agrupa as notas baixas causadas pelo sistema em temas. Cada tema pode virar uma issue no GitHub, com os casos anexados.'); cfCabFim('temas_ativo'); ?>
  <?= cfCampo('temas_janela_dias', 'Considerar os últimos (dias)', '', 'number', 'min="7" max="120"') ?>
  <?= cfCampo('github_repo', 'Repositório no GitHub', 'Formato dono/repositório. Ex.: douglaspootz/igutclinicas') ?>
  <?= cfSegredo('github_token', 'Token do GitHub', 'Token com permissão de criar issues nesse repositório.') ?>
<?php cfFim('<form method="post" style="margin-left:auto"><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="acao" value="rodar_temas"><button class="btn" type="submit">Agrupar agora</button></form>'); ?>
<?php if ($TEMAS_TODOS): ?>
  <div class="painel" style="margin:-8px 0 16px">
    <table class="log"><thead><tr><th>Tema</th><th>Casos</th><th>Situação</th><th></th></tr></thead><tbody>
    <?php foreach ($TEMAS_TODOS as $t): ?>
      <tr><td><b><?= h($t['titulo']) ?></b><br><span style="color:var(--tinta-3)"><?= h($t['descricao']) ?></span></td><td><?= (int)$t['qtd'] ?></td>
        <td><?= $t['issue_url'] ? '<a href="' . h($t['issue_url']) . '" target="_blank" rel="noopener">Issue aberta</a>' : h(ucfirst($t['status'])) ?></td>
        <td><form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="tema_status"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <?php if ($t['status'] !== 'resolvido'): ?><button class="btn" name="status" value="resolvido">Resolvido</button><?php endif; ?>
          <?php if ($t['status'] !== 'ignorado'): ?><button class="btn" name="status" value="ignorado">Ignorar</button><?php endif; ?>
          <?php if ($t['status'] === 'resolvido' || $t['status'] === 'ignorado'): ?><button class="btn" name="status" value="aberto">Reabrir</button><?php endif; ?></form></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif; ?>

<?php cfInicio('email', 'E-mail (SMTP)', 'Servidor que envia os e-mails do painel. Use os dados do provedor de e-mail da empresa.'); cfCabFim(); ?>
  <?= cfCampo('smtp_host', 'Servidor SMTP', 'Ex.: smtp.gmail.com, smtp.office365.com') ?>
  <?= cfCampo('smtp_porta', 'Porta', '587 para TLS, 465 para SSL.', 'number') ?>
  <?= cfSelect('smtp_seguranca', 'Segurança', array('tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'nenhuma' => 'Nenhuma')) ?>
  <?= cfCampo('smtp_usuario', 'Usuário', 'Normalmente o próprio e-mail.') ?>
  <?= cfSegredo('smtp_senha', 'Senha', 'No Gmail e no Microsoft 365, use uma senha de app.') ?>
  <?= cfCampo('smtp_de_email', 'Remetente (e-mail)', 'Ex.: auditoria@igut.med.br', 'email') ?>
  <?= cfCampo('smtp_de_nome', 'Remetente (nome)') ?>
<?php cfFim(cfTeste('testar_email', 'Enviar e-mail de teste', 'e-mail para o teste', 'email')); ?>

<?php cfInicio('whatsapp', 'WhatsApp', 'Como as mensagens de WhatsApp são enviadas.'); cfCabFim(); ?>
  <?= cfSelect('wa_provedor', 'Provedor', array('italk' => 'Pelo próprio iTalk', 'evolution' => 'Evolution API', 'zapi' => 'Z-API', 'webhook' => 'Webhook (n8n, Make etc.)'), '', 'data-provedor') ?>
  <div class="provedor" data-p="italk">
    <p class="nota">Usa uma caixa de WhatsApp do iTalk para mandar a mensagem. Atenção: na API oficial do WhatsApp, mensagens para quem não falou com a empresa nas últimas 24 horas exigem modelo aprovado e podem não ser entregues.</p>
    <?= cfSelect('wa_italk_inbox_id', 'Caixa de entrada do WhatsApp', $inboxOpts) ?>
  </div>
  <div class="provedor" data-p="evolution">
    <?= cfCampo('wa_evolution_url', 'Endereço da Evolution API', 'Ex.: https://evolution.suaempresa.com.br', 'url') ?>
    <?= cfCampo('wa_evolution_instancia', 'Instância') ?>
    <?= cfSegredo('wa_evolution_apikey', 'API key') ?>
  </div>
  <div class="provedor" data-p="zapi">
    <?= cfCampo('wa_zapi_instancia', 'ID da instância') ?>
    <?= cfSegredo('wa_zapi_token', 'Token da instância') ?>
    <?= cfSegredo('wa_zapi_client_token', 'Client-Token (segurança da conta)') ?>
  </div>
  <div class="provedor" data-p="webhook">
    <p class="nota">O painel envia um POST com {"para": "5511999998888", "texto": "...", "tipo": "alerta"} para o endereço abaixo.</p>
    <?= cfCampo('wa_webhook_url', 'Endereço do webhook', '', 'url') ?>
  </div>
<?php cfFim(cfTeste('testar_whatsapp', 'Enviar WhatsApp de teste', 'número com DDD')); ?>

<section class="painel" id="envios">
  <h3 class="cab-painel">Histórico de envios</h3><p class="leg">Os 25 envios mais recentes, com o motivo quando algo falha</p>
  <?php if ($ENVIOS): ?>
  <div style="overflow-x:auto"><table class="log">
    <thead><tr><th>Quando</th><th>Tipo</th><th>Canal</th><th>Para</th><th>Assunto</th><th>Resultado</th></tr></thead><tbody>
    <?php foreach ($ENVIOS as $e): ?>
      <tr><td style="white-space:nowrap"><?= date('d/m H:i', strtotime($e['criado_em'])) ?></td><td><?= h($e['tipo']) ?></td><td><?= h($e['canal']) ?></td>
        <td><?= h($e['destino']) ?></td><td><?= h($e['assunto']) ?></td>
        <td><?= ($e['ok'] === true || $e['ok'] === 't') ? '<span class="ok">Enviado</span>' : '<span class="falha">Falhou</span><br><span style="color:var(--tinta-3)">' . h($e['erro']) . '</span>' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php else: ?><div class="vazio">Nenhum envio ainda. Use os botões de teste para conferir a configuração.</div><?php endif; ?>
</section>

</div></div>
<script>
(function () {
  var sel = document.querySelector('select[data-provedor]'); if (!sel) return;
  var mostrar = function () { document.querySelectorAll('.provedor').forEach(function (p) { p.hidden = p.getAttribute('data-p') !== sel.value; }); };
  sel.addEventListener('change', mostrar); mostrar();
})();
</script>
