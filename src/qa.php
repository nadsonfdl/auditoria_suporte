<?php
// Auditoria de qualidade por IA em todas as conversas encerradas (com ou sem nota do cliente).
// Depende de lib.php, ia.php, notificar.php (conf). Compatível com PHP 5.6+

$GLOBALS['QA_CRITERIOS'] = array(
    'entendimento'  => 'Entendeu o pedido',
    'solucao'       => 'Resolveu de fato',
    'clareza'       => 'Explicou com clareza',
    'confirmacao'   => 'Confirmou antes de encerrar',
    'agilidade'     => 'Respondeu sem demora',
    'cordialidade'  => 'Cordialidade e empatia',
    'proatividade'  => 'Proatividade',
);
$GLOBALS['MOTIVOS_CONTATO'] = array(
    'duvida_uso'        => 'Dúvida de uso',
    'erro_sistema'      => 'Erro no sistema',
    'nota_fiscal'       => 'Nota fiscal',
    'financeiro'        => 'Financeiro / cobrança',
    'acesso_login'      => 'Acesso e login',
    'agenda'            => 'Agenda',
    'prontuario'        => 'Prontuário / atendimento',
    'configuracao'      => 'Configuração',
    'integracao'        => 'Integração',
    'solicitacao'       => 'Pedido de alteração',
    'outro'             => 'Outro',
);

/** Métricas objetivas tiradas das mensagens (sem IA) */
function metricasConversa($msgs)
{
    $m = array('msgs_cliente' => 0, 'msgs_atendente' => 0, 'msgs_bot' => 0, 'teve_humano' => false,
               'maior_espera_min' => 0.0, 'cliente_sem_resposta' => false, 'duracao_min' => 0.0);
    $esperaDesde = null;
    $primeira = null;
    $ultima = null;
    $ultimoQuem = null;
    foreach ($msgs as $x) {
        $tipo = (int)g($x, 'message_type', 0);
        if ($tipo === 2 || g($x, 'private') || g($x, 'content_type') === 'input_csat') {
            continue;                                   // eventos do sistema, notas internas e pesquisa não contam
        }
        $t = strtotime(ts(g($x, 'created_at')));
        if (!$t) {
            continue;
        }
        $primeira = $primeira === null ? $t : min($primeira, $t);
        $ultima = $ultima === null ? $t : max($ultima, $t);
        if ($tipo === 0) {
            $m['msgs_cliente']++;
            if ($esperaDesde === null) {
                $esperaDesde = $t;
            }
            $ultimoQuem = 'cliente';
        } else {
            $bot = stripos((string)g($x, 'sender.type', g($x, 'sender_type', '')), 'bot') !== false || $tipo === 3;
            if ($bot) {
                $m['msgs_bot']++;
            } else {
                $m['msgs_atendente']++;
                $m['teve_humano'] = true;
            }
            if ($esperaDesde !== null) {
                $m['maior_espera_min'] = max($m['maior_espera_min'], ($t - $esperaDesde) / 60.0);
                $esperaDesde = null;
            }
            $ultimoQuem = 'suporte';
        }
    }
    $m['cliente_sem_resposta'] = $ultimoQuem === 'cliente';
    $m['duracao_min'] = $primeira !== null ? ($ultima - $primeira) / 60.0 : 0.0;
    $m['maior_espera_min'] = round($m['maior_espera_min'], 1);
    $m['duracao_min'] = round($m['duracao_min'], 1);
    return $m;
}

function promptQualidade()
{
    $crit = array();
    foreach (array_keys($GLOBALS['QA_CRITERIOS']) as $k) {
        $crit[] = "    \"$k\": 0";
    }
    $crit = implode(",\n", $crit) . "\n";
    $motivos = implode(' | ', array_keys($GLOBALS['MOTIVOS_CONTATO']));
    $listaCrit = array();
    foreach ($GLOBALS['QA_CRITERIOS'] as $k => $v) {
        $listaCrit[] = "$k ($v)";
    }
    $listaCrit = implode(', ', $listaCrit);
    $cats = implode(' | ', array_keys($GLOBALS['CATEGORIAS']));
    return <<<TXT
Você é auditor de qualidade do suporte de uma empresa de software para clínicas (sistema iGUT).
Vai receber a transcrição de um atendimento já encerrado (pode envolver a IA "Guta" e atendentes humanos) e algumas métricas objetivas.
A maioria dos clientes NÃO responde a pesquisa de satisfação: seu papel é identificar falhas e riscos mesmo sem nota.

Responda SOMENTE com JSON válido (sem comentários), neste formato:
{
  "resolvido": "sim",
  "nota_qa": 0,
  "criterios": {
$crit  },
  "falhas": [ {"criterio": "solucao", "descricao": "o que faltou, em 1 frase", "trecho": "trecho curto da conversa"} ],
  "sentimento_inicio": "neutro",
  "sentimento_fim": "neutro",
  "risco": "baixo",
  "motivo_contato": "duvida_uso",
  "assunto": "assunto do pedido em até 8 palavras",
  "categoria_falha": "outro",
  "responsavel": "indefinido",
  "resumo": "2 frases: o que o cliente queria e como terminou"
}

Valores permitidos:
- resolvido: sim | parcial | nao | incerto. O problema do cliente foi resolvido DE FATO na conversa?
- nota_qa: 0 a 100, qualidade geral do atendimento.
- criterios: nota de 0 a 10 para cada um: $listaCrit.
- falhas[].criterio: um dos critérios acima.
- sentimento_inicio e sentimento_fim: positivo | neutro | negativo.
- risco: baixo | medio | alto. Risco de o cliente estar insatisfeito ou cancelar.
- motivo_contato: $motivos
- categoria_falha (principal falha; "outro" se não houver): $cats
- responsavel pela falha: atendente | ia_guta | produto_sistema | processo_regra | cliente | indefinido

Regras:
- "resolvido": "sim" só se houver evidência de solução (cliente confirma, ou a resposta resolve claramente o pedido). Encerrar sem confirmar NÃO é resolver.
- Se a última mensagem foi do cliente e ficou sem resposta, isso é uma falha grave de "confirmacao" ou "agilidade".
- Em conversa só com a Guta, avalie os critérios sobre a Guta.
- Seja justo e específico. "falhas" vazio se o atendimento foi bom. Português do Brasil.
TXT;
}

/** Audita um lote de conversas encerradas ainda não analisadas. Retorna quantas foram auditadas com sucesso. */
function processarQualidade($limite = null)
{
    if (!confLigado('qa_ativo') || !cfg('ia_api_key') || !cfg('ia_model')) {
        return 0;
    }
    $lote = $limite !== null ? (int)$limite : max(1, (int)conf('qa_lote', '15'));
    $pct  = max(1, min(100, (int)conf('qa_amostra_pct', '100')));
    $dias = max(1, (int)conf('qa_dias', '30'));
    $conv = q("SELECT c.id FROM espelho.conversations c
               LEFT JOIN auditoria.qa_conversas q ON q.conversation_id = c.id
               WHERE c.status = 'resolved' AND c.created_at >= now() - (?::int * interval '1 day')
                 AND (c.id % 100) < ? AND (q.conversation_id IS NULL OR (q.resolvido IS NULL AND q.tentativas < 3))
               ORDER BY c.last_activity_at DESC LIMIT $lote", array($dias, $pct));
    $ok = 0;
    foreach ($conv as $c) {
        $id = (int)$c['id'];
        try {
            $msgs = buscarMensagens($id);
            $met  = metricasConversa($msgs);
            if ($met['msgs_cliente'] === 0) {         // conversa sem mensagem do cliente: nada a auditar
                gravarQa($id, $met, array('resolvido' => 'incerto', 'resumo' => 'Conversa sem mensagens do cliente.', 'risco' => 'baixo'), null);
                continue;
            }
            $usuario = "MÉTRICAS: mensagens do cliente {$met['msgs_cliente']}, do atendente {$met['msgs_atendente']}, da IA/bot {$met['msgs_bot']}; "
                     . "maior espera do cliente " . duracaoTexto($met['maior_espera_min']) . "; "
                     . ($met['cliente_sem_resposta'] ? 'a ÚLTIMA mensagem foi do cliente e ficou SEM RESPOSTA; ' : '')
                     . "duração " . duracaoTexto($met['duracao_min']) . ".\n\nTRANSCRIÇÃO:\n" . transcricao($msgs);
            $d = jsonIA(chamarIA(promptQualidade(), $usuario));
            if (!is_array($d) || !g($d, 'resolvido')) {
                throw new RuntimeException('IA não devolveu JSON válido');
            }
            gravarQa($id, $met, $d, null);
            $ok++;
        } catch (Exception $e) {
            gravarQa($id, isset($met) ? $met : array(), array(), $e->getMessage());
        }
        usleep(300000);
    }
    if ($ok && confLigado('qa_para_fila')) {
        enviarRiscosParaFila();
    }
    return $ok;
}

function duracaoTexto($min)
{
    return $min < 60 ? round($min) . ' min' : round($min / 60, 1) . ' h';
}

function normEnum($v, $permitidos, $padrao)
{
    $v = strtolower(trim((string)$v));
    return in_array($v, $permitidos, true) ? $v : $padrao;
}

function gravarQa($id, $met, $d, $erro)
{
    $crit = array();
    foreach (array_keys($GLOBALS['QA_CRITERIOS']) as $k) {
        $v = g($d, 'criterios.' . $k);
        if ($v !== null && is_numeric($v)) {
            $crit[$k] = max(0, min(10, (int)round($v)));
        }
    }
    $falhas = array();
    foreach ((array)g($d, 'falhas', array()) as $f) {
        if (is_array($f) && g($f, 'descricao')) {
            $falhas[] = array('criterio' => (string)g($f, 'criterio', ''), 'descricao' => mb_cortar(g($f, 'descricao'), 300), 'trecho' => mb_cortar(g($f, 'trecho', ''), 200));
        }
    }
    $nota = g($d, 'nota_qa');
    $s = array('positivo', 'neutro', 'negativo');
    upsert('auditoria.qa_conversas', array(
        'conversation_id'      => $id,
        'analisado_em'         => gmdate('c'),
        'modelo'               => cfg('ia_provider') . ':' . cfg('ia_model'),
        'msgs_cliente'         => g($met, 'msgs_cliente'),
        'msgs_atendente'       => g($met, 'msgs_atendente'),
        'msgs_bot'             => g($met, 'msgs_bot'),
        'teve_humano'          => isset($met['teve_humano']) ? ($met['teve_humano'] ? 'true' : 'false') : null,
        'maior_espera_min'     => g($met, 'maior_espera_min'),
        'cliente_sem_resposta' => isset($met['cliente_sem_resposta']) ? ($met['cliente_sem_resposta'] ? 'true' : 'false') : null,
        'duracao_min'          => g($met, 'duracao_min'),
        'resolvido'            => $erro ? null : normEnum(g($d, 'resolvido'), array('sim', 'parcial', 'nao', 'incerto'), 'incerto'),
        'nota_qa'              => $erro || $nota === null || !is_numeric($nota) ? null : max(0, min(100, (int)round($nota))),
        'criterios'            => $erro ? null : j($crit),
        'falhas'               => $erro ? null : j($falhas),
        'sentimento_inicio'    => $erro ? null : normEnum(g($d, 'sentimento_inicio'), $s, 'neutro'),
        'sentimento_fim'       => $erro ? null : normEnum(g($d, 'sentimento_fim'), $s, 'neutro'),
        'risco'                => $erro ? null : normEnum(g($d, 'risco'), array('baixo', 'medio', 'alto'), 'baixo'),
        'motivo_contato'       => $erro ? null : normEnum(g($d, 'motivo_contato'), array_keys($GLOBALS['MOTIVOS_CONTATO']), 'outro'),
        'assunto'              => $erro ? null : mb_cortar(g($d, 'assunto', ''), 120),
        'resumo'               => $erro ? null : mb_cortar(g($d, 'resumo', ''), 600),
        'categoria_falha'      => $erro ? null : normEnum(g($d, 'categoria_falha'), array_keys($GLOBALS['CATEGORIAS']), 'outro'),
        'json'                 => $erro ? null : j($d),
        'erro'                 => $erro ? mb_cortar($erro, 500) : null,
    ), 'conversation_id');
    if ($erro) {
        db()->prepare("UPDATE auditoria.qa_conversas SET tentativas = tentativas + 1 WHERE conversation_id = ?")->execute(array($id));
    }
}

/** Conversas com risco alto, sem nota do cliente, entram na fila de auditoria do supervisor (origem IA) */
function enviarRiscosParaFila()
{
    return db()->exec("
      INSERT INTO auditoria.csat_auditoria (csat_id, origem, conversation_id, rating, feedback_cliente, atendente_id, contato_id,
                                            respondido_em, semana_ref, ia_motivo, ia_categoria, ia_responsavel, ia_gravidade,
                                            ia_resumo, ia_evidencias, ia_sugestao, ia_confianca, ia_analisado_em)
      SELECT NULL, 'ia', q.conversation_id, NULL, NULL, c.assignee_id, c.contact_id,
             c.last_activity_at, date_trunc('week', c.last_activity_at AT TIME ZONE 'America/Sao_Paulo')::date,
             COALESCE((q.falhas->0->>'descricao'), q.resumo), q.categoria_falha, q.json->>'responsavel', 'alta',
             q.resumo, (SELECT string_agg(f->>'trecho', E'\n') FROM jsonb_array_elements(q.falhas) f WHERE f->>'trecho' <> ''),
             NULL, NULL, q.analisado_em
      FROM auditoria.qa_conversas q
      JOIN espelho.conversations c ON c.id = q.conversation_id
      WHERE q.risco = 'alto'
        AND NOT EXISTS (SELECT 1 FROM espelho.csat s WHERE s.conversation_id = q.conversation_id)
        AND NOT EXISTS (SELECT 1 FROM auditoria.csat_auditoria a WHERE a.conversation_id = q.conversation_id)
        AND c.last_activity_at >= auditoria.cfg('csat_inicio')::date
    ");
}
