<?php
// Automações: alerta de nota baixa, resumo semanal, envio ao pós-vendas, problemas recorrentes e issue no GitHub.
// Depende de lib.php, painel.php e notificar.php. Compatível com PHP 5.6+

function marcaLer($k)
{
    $r = q1("SELECT valor FROM auditoria.agendador WHERE chave = ?", array($k));
    return isset($r['valor']) ? $r['valor'] : null;
}

function marcaGravar($k, $v)
{
    db()->prepare("INSERT INTO auditoria.agendador (chave, valor, atualizado_em) VALUES (?, ?, now())
                   ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = now()")
        ->execute(array($k, (string)$v));
}

function linkCaso($id)
{
    $u = conf('painel_url');
    return $u . (strpos($u, '?') === false ? '?' : '&') . 'p=fila&semana=todas#item-' . (int)$id;
}

// ================================================================ 1. alerta imediato de nota baixa
/** Envia alerta para cada nota <= alerta_nota_max ainda não alertada. Só considera notas depois que o alerta foi ligado. */
function processarAlertas()
{
    if (!confLigado('alerta_ativo')) {
        return 0;
    }
    $desde = conf('alerta_desde');
    if ($desde === '') {
        $desde = date('c');
        confSalvar('alerta_desde', $desde);
        conf(null);
    }
    $itens = q("SELECT f.*, q.respondido_em AS resp_utc FROM auditoria.csat_auditoria q JOIN auditoria.vw_csat_fila f ON f.id = q.id
                WHERE q.alerta_enviado_em IS NULL AND q.rating <= ? AND q.respondido_em >= ?::timestamptz
                ORDER BY q.respondido_em LIMIT 30", array((int)conf('alerta_nota_max', '1'), $desde));
    $n = 0;
    foreach ($itens as $it) {
        $nota = (int)$it['nota'];
        $cli  = $it['cliente'] ? $it['cliente'] : 'Cliente';
        $at   = $it['atendente'] ? $it['atendente'] : 'sem atendente';
        $assunto = "Nota $nota no suporte: $cli";

        if (confLigado('alerta_email')) {
            $html = '<p style="margin:0 0 14px;font-size:15px;line-height:1.5"><b>' . h($cli) . '</b> deu nota <b style="color:#a2312a">' . $nota . '</b> '
                  . 'ao atendimento de <b>' . h($at) . '</b> em ' . date('d/m \à\s H:i', strtotime($it['respondido_em'])) . '.</p>'
                  . ($it['feedback_cliente'] ? '<p style="margin:0 0 16px;padding:10px 14px;border-left:3px solid #d6453d;background:#fbe9e7;font-family:Georgia,serif;font-style:italic;font-size:16px">' . h($it['feedback_cliente']) . '</p>' : '')
                  . ($it['ia_motivo'] ? '<p style="margin:0 0 6px;color:#5b4bc4;font-size:13px;font-weight:bold">Leitura da IA</p><p style="margin:0 0 16px;font-size:14px;line-height:1.5">' . h($it['ia_motivo']) . '</p>' : '')
                  . '<p style="margin:0 0 6px;font-size:14px"><a href="' . h($it['link_conversa']) . '" style="color:#1b64be">Abrir a conversa no iTalk</a> &nbsp;|&nbsp; '
                  . '<a href="' . h(linkCaso($it['id'])) . '" style="color:#1b64be">Abrir o caso na auditoria</a></p>';
            enviarEmail(conf('alerta_emails'), $assunto, emailLayout('Nova nota ' . $nota . ' no suporte', $html), array(), 'alerta');
        }
        if (confLigado('alerta_whatsapp')) {
            $txt = "⚠️ *Nota $nota no suporte*\n*$cli* avaliou o atendimento de *$at*.";
            if ($it['feedback_cliente']) {
                $txt .= "\n\n“" . $it['feedback_cliente'] . "”";
            }
            $txt .= "\n\nConversa: " . $it['link_conversa'] . "\nAuditoria: " . linkCaso($it['id']);
            enviarWhatsApp(conf('alerta_whatsapps'), $txt, 'alerta');
        }
        db()->prepare("UPDATE auditoria.csat_auditoria SET alerta_enviado_em = now() WHERE id = ?")->execute(array($it['id']));
        $n++;
    }
    return $n;
}

// ================================================================ 2. resumo semanal
function dadosSemana($ini, $fim)
{
    $F = array('atendente' => '', 'time' => '', 'caixa' => '');
    $corte = notaCorte();
    $kc = kpisConversas($F, $ini, $fim);
    $ks = kpisCsat($F, $ini, $fim, $corte);
    $p = array($ini, $fim);
    $mot = q("SELECT ia_categoria AS k, count(*) AS n FROM auditoria.vw_painel_csat
              WHERE dia BETWEEN ?::date AND ?::date AND ia_categoria IS NOT NULL GROUP BY 1 ORDER BY 2 DESC LIMIT 3", $p);
    $pend = q1("SELECT count(*) AS n FROM auditoria.csat_auditoria WHERE status = 'pendente'");
    return array('kc' => $kc, 'ks' => $ks, 'motivos' => $mot, 'pendentes' => (int)$pend['n'], 'corte' => $corte);
}

/** Monta e envia o resumo da semana anterior (segunda a domingo). $forcar ignora dia/hora e a marca de "já enviado". */
function processarResumoSemanal($forcar = false, $destinosEmail = null)
{
    if (!$forcar && !confLigado('resumo_ativo')) {
        return false;
    }
    $agora = time();
    $ini = date('Y-m-d', strtotime('monday last week', $agora));
    $fim = date('Y-m-d', strtotime($ini . ' +6 days'));
    if (!$forcar) {
        if ((int)date('N', $agora) !== (int)conf('resumo_dia', '1') || (int)date('G', $agora) < (int)conf('resumo_hora', '8')) {
            return false;
        }
        if (marcaLer('resumo_semana') === $ini) {
            return false;
        }
    }
    $antIni = date('Y-m-d', strtotime($ini . ' -7 days'));
    $antFim = date('Y-m-d', strtotime($ini . ' -1 day'));
    $a = dadosSemana($ini, $fim);
    $b = dadosSemana($antIni, $antFim);

    $taxaRes = taxa($a['kc']['resolvidas'], $a['kc']['conversas']);
    $satisf  = taxa($a['ks']['satisfeitos'], $a['ks']['respostas']);
    $satisfB = taxa($b['ks']['satisfeitos'], $b['ks']['respostas']);
    $var = function ($x, $y, $suf, $dec, $maiorMelhor) {
        if ($x === null || $y === null) {
            return '';
        }
        $d = (float)$x - (float)$y;
        if (abs($d) < 0.05) {
            return '<span style="color:#78859a"> estável</span>';
        }
        $bom = ($d > 0) === $maiorMelhor;
        return ' <span style="color:' . ($bom ? '#0a6b3c' : '#a2312a') . '">' . ($d > 0 ? '▲' : '▼') . ' ' . number_format(abs($d), $dec, ',', '.') . $suf . '</span>';
    };
    $linha = function ($rot, $val, $comp) {
        return '<tr><td style="padding:9px 0;border-bottom:1px solid #edf1f7;color:#4b5a70;font-size:14px">' . $rot . '</td>'
             . '<td style="padding:9px 0;border-bottom:1px solid #edf1f7;text-align:right;font-size:15px;font-weight:bold">' . $val . '<span style="font-weight:normal;font-size:12px">' . $comp . '</span></td></tr>';
    };
    $tab = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px">'
         . $linha('Conversas', num($a['kc']['conversas']), $var($a['kc']['conversas'], $b['kc']['conversas'], '', 0, true))
         . $linha('Encerradas no sistema', pct($taxaRes), '')
         . $linha('Tempo até a 1ª resposta (mediana)', duracao($a['kc']['mediana_1a']), '')
         . $linha('CSAT médio', $a['ks']['respostas'] ? num($a['ks']['media'], 2) : '—', $var($a['ks']['media'], $b['ks']['media'], '', 2, true))
         . $linha('Clientes satisfeitos (nota 4 ou 5)', pct($satisf), $var($satisf, $satisfB, ' p.p.', 1, true))
         . $linha('Notas baixas (até ' . $a['corte'] . ')', num($a['ks']['baixas']), $var($a['ks']['baixas'], $b['ks']['baixas'], '', 0, false))
         . '</table>';
    $mot = '';
    if ($a['motivos']) {
        $mot = '<p style="margin:0 0 8px;font-size:14px;font-weight:bold">Principais motivos das notas baixas, segundo a IA</p><ol style="margin:0 0 18px;padding-left:20px;font-size:14px;line-height:1.7">';
        foreach ($a['motivos'] as $m) {
            $mot .= '<li>' . h(rotulo('CATEGORIAS', $m['k'])) . ': ' . num($m['n']) . '</li>';
        }
        $mot .= '</ol>';
    }
    $pend = $a['pendentes'] ? '<p style="margin:0 0 8px;font-size:14px;color:#9a6412"><b>' . num($a['pendentes']) . '</b> notas baixas aguardam avaliação do supervisor.</p>' : '';
    $frase = num($a['kc']['conversas']) . ' conversas entre ' . date('d/m', strtotime($ini)) . ' e ' . date('d/m', strtotime($fim)) . '. '
           . pct($satisf, 0) . ' dos clientes que avaliaram saíram satisfeitos.';
    $html = '<p style="margin:0 0 16px;font-family:Georgia,serif;font-size:17px;line-height:1.45">' . h($frase) . '</p>' . $tab . $mot . $pend;
    $assunto = 'Resumo do suporte: semana de ' . date('d/m', strtotime($ini)) . ' a ' . date('d/m', strtotime($fim));

    $dest = $destinosEmail !== null ? $destinosEmail : conf('resumo_emails');
    list($ok, $erro) = enviarEmail($dest, $assunto, emailLayout($assunto, $html), array(), $forcar ? 'teste' : 'resumo');
    if (!$forcar && confLigado('resumo_whatsapp')) {
        $txt = "📊 *$assunto*\n" . $frase . "\n\nCSAT médio: " . ($a['ks']['respostas'] ? num($a['ks']['media'], 2) : '—')
             . "\nNotas baixas: " . num($a['ks']['baixas']) . ($a['pendentes'] ? "\nAguardando supervisor: " . num($a['pendentes']) : '')
             . "\n\nPainel: " . conf('painel_url');
        enviarWhatsApp(conf('resumo_whatsapps'), $txt, 'resumo');
    }
    if (!$forcar) {
        marcaGravar('resumo_semana', $ini);
    }
    return array($ok, $erro);
}

// ================================================================ 3. envio ao pós-vendas
/** Envia as notas avaliadas da semana ao pós-vendas (e-mail com CSV + WhatsApp opcional) e marca como enviadas. */
function enviarSemanaPosVendas($semana, $usuario)
{
    $rows = itensFila($semana, 'avaliado', '');
    if (!$rows) {
        return array(false, 'Nenhuma nota avaliada nesta semana para enviar.', 0);
    }
    $sem = date('d/m', strtotime($semana)) . ' a ' . date('d/m', strtotime($semana . ' +6 days'));
    $itensHtml = '';
    foreach ($rows as $r) {
        $itensHtml .= '<tr><td style="padding:12px 0;border-bottom:1px solid #edf1f7;vertical-align:top;width:34px">'
            . ($r['nota'] === null
                ? '<span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:6px;background:#5b4bc4;color:#fff;font-weight:bold;font-size:11px">IA</span></td>'
                : '<span style="display:inline-block;width:26px;height:26px;line-height:26px;text-align:center;border-radius:6px;background:#d6453d;color:#fff;font-weight:bold">' . (int)$r['nota'] . '</span></td>')
            . '<td style="padding:12px 0 12px 10px;border-bottom:1px solid #edf1f7;font-size:14px;line-height:1.5">'
            . '<b>' . h($r['cliente'] ? $r['cliente'] : 'Cliente') . '</b>' . ($r['telefone'] ? ' <span style="color:#78859a">' . h($r['telefone']) . '</span>' : '')
            . '<br><span style="color:#4b5a70">Atendente: ' . h($r['atendente'] ? $r['atendente'] : '—') . ' | ' . h(rotulo('CATEGORIAS', $r['classificacao'] ? $r['classificacao'] : $r['ia_categoria'])) . '</span>'
            . ($r['origem'] === 'ia' ? '<br><span style="color:#5b4bc4">Sem nota do cliente: sinalizado pela auditoria por IA.</span>' . ($r['ia_motivo'] ? ' ' . h($r['ia_motivo']) : '') : '')
            . ($r['feedback_cliente'] ? '<br><i>“' . h($r['feedback_cliente']) . '”</i>' : '')
            . ($r['observacao'] ? '<br><b>Supervisor:</b> ' . h($r['observacao']) : '')
            . '<br><a href="' . h($r['link_conversa']) . '" style="color:#1b64be">Abrir conversa</a></td></tr>';
    }
    $nIa = 0;
    foreach ($rows as $r) {
        if ($r['origem'] === 'ia') {
            $nIa++;
        }
    }
    $nCsat = count($rows) - $nIa;
    $html = '<p style="margin:0 0 14px;font-size:15px;line-height:1.5">' . count($rows) . (count($rows) == 1 ? ' caso avaliado' : ' casos avaliados') . ' pelo supervisor para tratamento: '
          . $nCsat . ($nCsat == 1 ? ' nota baixa' : ' notas baixas') . ($nIa ? ' e ' . $nIa . ($nIa == 1 ? ' conversa sinalizada' : ' conversas sinalizadas') . ' pela IA sem nota do cliente' : '') . '. '
          . 'A planilha completa vai em anexo. Registre o retorno de cada caso no painel.</p>'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $itensHtml . '</table>';
    $assunto = 'Casos para o pós-vendas: semana de ' . $sem;
    list($ok, $erro) = enviarEmail(conf('posvendas_emails'), $assunto, emailLayout($assunto, $html),
        array(array('nome' => 'notas_baixas_' . $semana . '.csv', 'conteudo' => csvFila($rows), 'tipo' => 'text/csv')), 'posvendas');
    if (confLigado('posvendas_whatsapp')) {
        enviarWhatsApp(conf('posvendas_whatsapps'), "📋 *$assunto*\n" . count($rows) . " casos avaliados aguardam o pós-vendas.\nDetalhes no e-mail e no painel: " . conf('painel_url') . '?p=fila&status=enviado', 'posvendas');
    }
    if (!$ok) {
        return array(false, $erro, 0);
    }
    $st = db()->prepare("UPDATE auditoria.csat_auditoria SET status = 'enviado', enviado_em = now() WHERE semana_ref = ?::date AND status = 'avaliado'");
    $st->execute(array($semana));
    return array(true, null, $st->rowCount());
}

// ================================================================ 4. problemas recorrentes
/** Agrupa, com IA, as notas baixas atribuídas a produto/sistema em temas recorrentes. Roda uma vez por dia. */
function processarTemas($forcar = false)
{
    if (!confLigado('temas_ativo') || !cfg('ia_api_key') || !cfg('ia_model')) {
        return 0;
    }
    $hoje = date('Y-m-d');
    if (!$forcar && marcaLer('temas_dia') === $hoje) {
        return 0;
    }
    if (!function_exists('chamarIA')) {
        require_once __DIR__ . '/ia.php';
    }
    $itens = q("SELECT id, ia_motivo, ia_resumo, respondido_em FROM auditoria.csat_auditoria
                WHERE tema_id IS NULL AND ia_analisado_em IS NOT NULL
                  AND (ia_responsavel = 'produto_sistema' OR ia_categoria = 'erro_ou_bug_sistema')
                  AND respondido_em >= now() - (?::int * interval '1 day')
                ORDER BY respondido_em LIMIT 60", array((int)conf('temas_janela_dias', '30')));
    marcaGravar('temas_dia', $hoje);
    if (!$itens) {
        return 0;
    }
    $temas = q("SELECT id, titulo, descricao FROM auditoria.temas WHERE status IN ('aberto', 'issue_aberta') ORDER BY id");
    $sistema = "Você agrupa reclamações de clientes de um software para clínicas (iGUT) em PROBLEMAS DE PRODUTO recorrentes.\n"
             . "Receberá temas já existentes e casos novos. Para cada caso, associe a um tema existente (pelo id) ou crie um tema novo.\n"
             . "Só crie tema novo se o problema for realmente diferente. Título curto e específico (ex.: \"Erro na emissão de NFS-e\").\n"
             . "Responda SOMENTE com JSON: {\"novos\":[{\"ref\":\"n1\",\"titulo\":\"...\",\"descricao\":\"1 frase\"}],"
             . "\"associacoes\":[{\"caso\":123,\"tema\":5}, {\"caso\":124,\"tema\":\"n1\"}]}. Use português do Brasil.";
    $usuario = "TEMAS EXISTENTES:\n";
    foreach ($temas as $t) {
        $usuario .= '- id ' . $t['id'] . ': ' . $t['titulo'] . ($t['descricao'] ? ' (' . $t['descricao'] . ')' : '') . "\n";
    }
    if (!$temas) {
        $usuario .= "(nenhum)\n";
    }
    $usuario .= "\nCASOS NOVOS:\n";
    foreach ($itens as $i) {
        $usuario .= '- caso ' . $i['id'] . ': ' . $i['ia_motivo'] . ($i['ia_resumo'] ? ' | ' . mb_cortar($i['ia_resumo'], 300) : '') . "\n";
    }
    $d = jsonIA(chamarIA($sistema, $usuario));
    if (!is_array($d)) {
        throw new RuntimeException('IA devolveu JSON inválido ao agrupar temas');
    }
    $mapa = array();
    foreach ((array)g($d, 'novos', array()) as $nv) {
        if (!g($nv, 'titulo')) {
            continue;
        }
        $st = db()->prepare("INSERT INTO auditoria.temas (titulo, descricao) VALUES (?, ?) RETURNING id");
        $st->execute(array(mb_cortar($nv['titulo'], 140), mb_cortar(g($nv, 'descricao', ''), 400)));
        $mapa[(string)g($nv, 'ref')] = (int)$st->fetchColumn();
    }
    $idsValidos = array();
    foreach ($itens as $i) {
        $idsValidos[(int)$i['id']] = true;
    }
    $n = 0;
    foreach ((array)g($d, 'associacoes', array()) as $as) {
        $caso = (int)g($as, 'caso');
        $tema = g($as, 'tema');
        $tid = is_numeric($tema) ? (int)$tema : (isset($mapa[(string)$tema]) ? $mapa[(string)$tema] : 0);
        if (!$tid || !isset($idsValidos[$caso])) {
            continue;
        }
        db()->prepare("UPDATE auditoria.csat_auditoria SET tema_id = ? WHERE id = ?")->execute(array($tid, $caso));
        $n++;
    }
    db()->exec("UPDATE auditoria.temas t SET qtd = s.n, primeiro_em = s.pri, ultimo_em = s.ult, atualizado_em = now()
                FROM (SELECT tema_id, count(*) n, min(respondido_em) pri, max(respondido_em) ult FROM auditoria.csat_auditoria
                      WHERE tema_id IS NOT NULL GROUP BY 1) s WHERE s.tema_id = t.id");
    db()->exec("DELETE FROM auditoria.temas t WHERE qtd = 0 AND NOT EXISTS (SELECT 1 FROM auditoria.csat_auditoria q WHERE q.tema_id = t.id)");
    return $n;
}

/** Abre uma issue no GitHub com os casos do tema */
function abrirIssueTema($temaId)
{
    $repo = trim(conf('github_repo'));
    $tok  = conf('github_token');
    if ($repo === '' || $tok === '') {
        return array(false, 'Configure o repositório e o token do GitHub em Configurações > Problemas recorrentes.');
    }
    $t = q1("SELECT * FROM auditoria.temas WHERE id = ?", array((int)$temaId));
    if (!$t) {
        return array(false, 'Tema não encontrado.');
    }
    $casos = q("SELECT f.* FROM auditoria.vw_csat_fila f WHERE f.tema_id = ? ORDER BY f.respondido_em DESC LIMIT 20", array((int)$temaId));
    $corpo = "**Problema recorrente identificado pela Auditoria do Suporte**\n\n" . ($t['descricao'] ? $t['descricao'] . "\n\n" : '')
           . '- Ocorrências com nota baixa: **' . (int)$t['qtd'] . "**\n"
           . '- Período: ' . date('d/m/Y', strtotime($t['primeiro_em'])) . ' a ' . date('d/m/Y', strtotime($t['ultimo_em'])) . "\n\n### Casos\n";
    foreach ($casos as $c) {
        $corpo .= '- Nota ' . (int)$c['nota'] . ', ' . date('d/m', strtotime($c['respondido_em'])) . ': ' . $c['ia_motivo']
                . ($c['feedback_cliente'] ? ' — cliente: _"' . str_replace(array("\n", '"'), ' ', $c['feedback_cliente']) . '"_' : '')
                . ' ([conversa](' . $c['link_conversa'] . "))\n";
    }
    $corpo .= "\n_Aberta pelo painel da Auditoria do Suporte._";
    list($c, $r, $b) = httpJson('POST', 'https://api.github.com/repos/' . $repo . '/issues',
        array('title' => '[Suporte] ' . $t['titulo'], 'body' => $corpo, 'labels' => array('suporte')),
        array('Authorization: Bearer ' . $tok, 'User-Agent: auditoria-suporte', 'Accept: application/vnd.github+json'));
    if ($c === 422 && strpos((string)$b, 'label') !== false) {     // repositório sem o label "suporte": tenta sem
        list($c, $r, $b) = httpJson('POST', 'https://api.github.com/repos/' . $repo . '/issues', array('title' => '[Suporte] ' . $t['titulo'], 'body' => $corpo),
            array('Authorization: Bearer ' . $tok, 'User-Agent: auditoria-suporte', 'Accept: application/vnd.github+json'));
    }
    $ok = $c === 201;
    registrarEnvio('issue', 'github', $repo, $t['titulo'], $ok, $ok ? null : "HTTP $c " . mb_cortar($b, 200));
    if (!$ok) {
        return array(false, "GitHub recusou (HTTP $c). Confira o repositório e o token.");
    }
    db()->prepare("UPDATE auditoria.temas SET status = 'issue_aberta', issue_url = ? WHERE id = ?")->execute(array(g($r, 'html_url'), (int)$temaId));
    return array(true, g($r, 'html_url'));
}
