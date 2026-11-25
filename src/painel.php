<?php
// Camada de dados e utilidades do painel de BI (public/painel.php)
// Compatível com PHP 5.6+

$GLOBALS['CATEGORIAS'] = array(
    'problema_nao_resolvido'      => 'Problema não resolvido',
    'demora_resposta'             => 'Demora na resposta',
    'erro_ou_bug_sistema'         => 'Erro / bug do sistema',
    'comunicacao_atendente'       => 'Comunicação do atendente',
    'informacao_incorreta'        => 'Informação incorreta',
    'transferencias_excessivas'   => 'Transferências excessivas',
    'insatisfacao_regra_ou_preco' => 'Regra / preço',
    'atendimento_ia_guta'         => 'Atendimento da Guta',
    'expectativa_do_cliente'      => 'Expectativa do cliente',
    'outro'                       => 'Outro',
);
$GLOBALS['RESPONSAVEIS'] = array(
    'atendente'       => 'Atendente',
    'produto_sistema' => 'Produto / sistema',
    'processo_regra'  => 'Processo / regra',
    'ia_guta'         => 'IA Guta',
    'cliente'         => 'Cliente',
    'indefinido'      => 'Indefinido',
);

function rotulo($mapa, $k)
{
    $m = $GLOBALS[$mapa];
    if ($k === null || $k === '') {
        return '—';
    }
    return isset($m[$k]) ? $m[$k] : ucfirst(str_replace('_', ' ', $k));
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function num($n, $dec = 0)
{
    return $n === null ? '—' : number_format((float)$n, $dec, ',', '.');
}

function pct($n, $dec = 1)
{
    return $n === null ? '—' : number_format((float)$n, $dec, ',', '.') . '%';
}

function duracao($min)
{
    if ($min === null) {
        return '—';
    }
    $min = (float)$min;
    if ($min < 1) {
        return '< 1 min';
    }
    if ($min < 60) {
        return round($min) . ' min';
    }
    if ($min < 1440) {
        $h = floor($min / 60);
        $m = round($min - $h * 60);
        return $h . 'h' . ($m ? ' ' . $m . 'min' : '');
    }
    $d = floor($min / 1440);
    $h = round(($min - $d * 1440) / 60);
    return $d . 'd' . ($h ? ' ' . $h . 'h' : '');
}

function q($sql, $params = array())
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1($sql, $params = array())
{
    $r = q($sql, $params);
    return $r ? $r[0] : array();
}

function notaCorte()
{
    $r = q1("SELECT auditoria.cfg('csat_nota_corte')::int AS c");
    return isset($r['c']) ? (int)$r['c'] : 2;
}

// ---------------------------------------------------------------- filtros

/** Lê os filtros da URL e calcula o período atual e o anterior (mesmo tamanho) */
function lerFiltros()
{
    $hoje = date('Y-m-d');
    $per  = isset($_GET['periodo']) ? (string)$_GET['periodo'] : '30';
    $ini  = isset($_GET['ini']) ? (string)$_GET['ini'] : '';
    $fim  = isset($_GET['fim']) ? (string)$_GET['fim'] : '';
    $valida = function ($d) {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
    };

    if ($per === 'custom' && $valida($ini) && $valida($fim)) {
        if ($ini > $fim) {
            $t = $ini; $ini = $fim; $fim = $t;
        }
    } elseif ($per === 'tudo') {
        $r = q1("SELECT min(dia)::text AS d FROM auditoria.vw_painel_conversas");
        $ini = !empty($r['d']) ? $r['d'] : $hoje;
        $fim = $hoje;
    } else {
        if (!in_array($per, array('7', '30', '90'), true)) {
            $per = '30';
        }
        $fim = $hoje;
        $ini = date('Y-m-d', strtotime("-" . ((int)$per - 1) . " days"));
    }

    $dias   = (int)round((strtotime($fim) - strtotime($ini)) / 86400) + 1;
    $antFim = date('Y-m-d', strtotime($ini . ' -1 day'));
    $antIni = date('Y-m-d', strtotime($antFim . ' -' . ($dias - 1) . ' days'));

    return array(
        'periodo'   => $per,
        'ini'       => $ini, 'fim' => $fim, 'dias' => $dias,
        'ant_ini'   => $antIni, 'ant_fim' => $antFim,
        'atendente' => isset($_GET['atendente']) && ctype_digit((string)$_GET['atendente']) ? (int)$_GET['atendente'] : '',
        'time'      => isset($_GET['time']) && ctype_digit((string)$_GET['time']) ? (int)$_GET['time'] : '',
        'caixa'     => isset($_GET['caixa']) && ctype_digit((string)$_GET['caixa']) ? (int)$_GET['caixa'] : '',
        'grao'      => $dias <= 45 ? 'dia' : 'semana',
    );
}

/** WHERE comum às duas views do painel (dia + atendente/time/caixa) */
function where($F, $ini, $fim, &$params, $comPeriodo = true)
{
    $w = array('1=1');
    if ($comPeriodo) {
        $w[] = 'dia BETWEEN ?::date AND ?::date';
        $params[] = $ini;
        $params[] = $fim;
    }
    if ($F['atendente'] !== '') {
        $w[] = 'assignee_id = ?';
        $params[] = $F['atendente'];
    }
    if ($F['time'] !== '') {
        $w[] = 'team_id = ?';
        $params[] = $F['time'];
    }
    if ($F['caixa'] !== '') {
        $w[] = 'inbox_id = ?';
        $params[] = $F['caixa'];
    }
    return implode(' AND ', $w);
}

// ---------------------------------------------------------------- consultas

function kpisConversas($F, $ini, $fim)
{
    $p = array();
    $w = where($F, $ini, $fim, $p);
    return q1("SELECT count(*) AS conversas,
                      count(*) FILTER (WHERE resolvida) AS resolvidas,
                      count(*) FILTER (WHERE rastreada) AS rastreadas,
                      count(*) FILTER (WHERE rastreada AND resolvida_sem_reabrir) AS sem_reabrir,
                      COALESCE(sum(reaberturas), 0) AS reaberturas,
                      count(*) FILTER (WHERE assignee_id IS NULL) AS sem_atendente,
                      percentile_cont(0.5) WITHIN GROUP (ORDER BY primeira_resposta_min) AS mediana_1a
               FROM auditoria.vw_painel_conversas WHERE $w", $p);
}

function kpisCsat($F, $ini, $fim, $corte)
{
    $p = array($corte);
    $w = where($F, $ini, $fim, $p);
    return q1("SELECT count(*) AS respostas, avg(nota) AS media,
                      count(*) FILTER (WHERE nota >= 4) AS satisfeitos,
                      count(*) FILTER (WHERE nota <= ?) AS baixas
               FROM auditoria.vw_painel_csat WHERE $w", $p);
}

function pendentesAuditoria($F)
{
    $p = array();
    $w = where($F, null, null, $p, false);
    $r = q1("SELECT count(*) AS n FROM auditoria.vw_painel_csat WHERE status_auditoria = 'pendente' AND $w", $p);
    return isset($r['n']) ? (int)$r['n'] : 0;
}

function serieConversas($F)
{
    $g = $F['grao'];
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT $g::text AS b, count(*) AS conversas,
                     round(100.0 * count(*) FILTER (WHERE resolvida) / NULLIF(count(*), 0), 1) AS pct_resolvidas,
                     round(100.0 * count(*) FILTER (WHERE rastreada AND resolvida_sem_reabrir)
                           / NULLIF(count(*) FILTER (WHERE rastreada), 0), 1) AS pct_sem_reabrir,
                     round((percentile_cont(0.5) WITHIN GROUP (ORDER BY primeira_resposta_min))::numeric, 1) AS mediana_1a
              FROM auditoria.vw_painel_conversas WHERE $w GROUP BY 1 ORDER BY 1", $p);
}

function serieCsat($F)
{
    $g = $F['grao'];
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT $g::text AS b, round(avg(nota), 2) AS media, count(*) AS respostas
              FROM auditoria.vw_painel_csat WHERE $w GROUP BY 1 ORDER BY 1", $p);
}

function distribuicaoNotas($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    $r = q("SELECT nota, count(*) AS n FROM auditoria.vw_painel_csat WHERE $w AND nota BETWEEN 1 AND 5 GROUP BY 1", $p);
    $d = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
    foreach ($r as $x) {
        $d[(int)$x['nota']] = (int)$x['n'];
    }
    return $d;
}

function mapaCalor($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    $r = q("SELECT dia_semana, hora, count(*) AS n FROM auditoria.vw_painel_conversas WHERE $w GROUP BY 1, 2", $p);
    $m = array();
    for ($d = 1; $d <= 7; $d++) {
        $m[$d] = array_fill(0, 24, 0);
    }
    foreach ($r as $x) {
        $m[(int)$x['dia_semana']][(int)$x['hora']] = (int)$x['n'];
    }
    return $m;
}

function porCaixa($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT COALESCE(caixa_entrada, '(sem caixa)') AS rotulo, count(*) AS n
              FROM auditoria.vw_painel_conversas WHERE $w GROUP BY 1 ORDER BY 2 DESC LIMIT 8", $p);
}

function porIA($F, $coluna)
{
    $coluna = $coluna === 'ia_responsavel' ? 'ia_responsavel' : 'ia_categoria';
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT $coluna AS k, count(*) AS n FROM auditoria.vw_painel_csat
              WHERE $w AND $coluna IS NOT NULL GROUP BY 1 ORDER BY 2 DESC", $p);
}

function ultimasAnalises($F, $lim = 6)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT auditoria_id, nota, atendente, ia_categoria, ia_responsavel, ia_gravidade, ia_motivo, respondido_em
              FROM auditoria.vw_painel_csat WHERE $w AND ia_motivo IS NOT NULL
              ORDER BY CASE ia_gravidade WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END, respondido_em DESC
              LIMIT " . (int)$lim, $p);
}

function rankingAtendentes($F, $corte)
{
    $p1 = array();
    $w1 = where($F, $F['ini'], $F['fim'], $p1);
    $p2 = array($corte);
    $w2 = where($F, $F['ini'], $F['fim'], $p2);
    return q("WITH c AS (
                SELECT assignee_id, count(*) AS conversas,
                       round(100.0 * count(*) FILTER (WHERE resolvida) / NULLIF(count(*), 0), 1) AS pct_resolvidas,
                       sum(reaberturas) AS reaberturas,
                       percentile_cont(0.5) WITHIN GROUP (ORDER BY primeira_resposta_min) AS mediana_1a
                FROM auditoria.vw_painel_conversas WHERE $w1 AND assignee_id IS NOT NULL GROUP BY 1
              ), s AS (
                SELECT assignee_id, count(*) AS respostas, round(avg(nota), 2) AS media,
                       count(*) FILTER (WHERE nota <= ?) AS baixas,
                       mode() WITHIN GROUP (ORDER BY ia_categoria) AS motivo
                FROM auditoria.vw_painel_csat WHERE $w2 AND assignee_id IS NOT NULL GROUP BY 1
              )
              SELECT COALESCE(c.assignee_id, s.assignee_id) AS id, a.name AS atendente,
                     COALESCE(c.conversas, 0) AS conversas, c.pct_resolvidas, COALESCE(c.reaberturas, 0) AS reaberturas,
                     c.mediana_1a, COALESCE(s.respostas, 0) AS respostas, s.media, COALESCE(s.baixas, 0) AS baixas, s.motivo
              FROM c FULL JOIN s ON s.assignee_id = c.assignee_id
              LEFT JOIN espelho.agents a ON a.id = COALESCE(c.assignee_id, s.assignee_id)
              ORDER BY conversas DESC", array_merge($p1, $p2));
}

function opcoes()
{
    return array(
        'atendentes' => q("SELECT id, name FROM espelho.agents ORDER BY name"),
        'times'      => q("SELECT id, name FROM espelho.teams ORDER BY name"),
        'caixas'     => q("SELECT id, name FROM espelho.inboxes ORDER BY name"),
    );
}

// ---------------------------------------------------------------- fila de auditoria

function semanasFila()
{
    return q("SELECT semana_ref::text AS s, count(*) AS n,
                     count(*) FILTER (WHERE status = 'pendente') AS pend
              FROM auditoria.csat_auditoria GROUP BY 1 ORDER BY 1 DESC");
}

function itensFila($semana, $status, $atendente)
{
    $w = array('1=1');
    $p = array();
    if ($semana !== '') {
        $w[] = 'q.semana_ref = ?::date';
        $p[] = $semana;
    }
    if ($status !== '') {
        $w[] = 'q.status = ?';
        $p[] = $status;
    }
    if ($atendente !== '') {
        $w[] = 'q.atendente_id = ?';
        $p[] = $atendente;
    }
    return q("SELECT f.*, q.atendente_id
              FROM auditoria.csat_auditoria q JOIN auditoria.vw_csat_fila f ON f.id = q.id
              WHERE " . implode(' AND ', $w) . "
              ORDER BY CASE q.status WHEN 'pendente' THEN 0 WHEN 'avaliado' THEN 1 ELSE 2 END,
                       CASE q.ia_gravidade WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END,
                       q.respondido_em DESC", $p);
}

function ultimaSync()
{
    $r = q1("SELECT max(atualizado_em) AS t FROM auditoria.sync_control");
    return !empty($r['t']) ? $r['t'] : null;
}

/** Inicial do nome (UTF-8, sem depender da extensão mbstring) */
function mb_substr_compat($s)
{
    return preg_match('/^./u', trim((string)$s), $m) ? strtoupper($m[0]) : '?';
}

// ---------------------------------------------------------------- CSV da fila (painel e e-mail ao pós-vendas)
function csvFila($rows)
{
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Semana', 'Respondido em', 'Nota', 'Cliente', 'Telefone', 'E-mail', 'Atendente', 'Link da conversa',
        'Comentário do cliente', 'Motivo (IA)', 'Categoria (IA)', 'Responsável (IA)', 'Gravidade (IA)', 'Sugestão (IA)',
        'Classificação do supervisor', 'Observação do supervisor', 'Supervisor', 'Status', 'Retorno do pós-vendas', 'Origem'), ';');
    foreach ($rows as $r) {
        fputcsv($out, array($r['semana_ref'], substr($r['respondido_em'], 0, 16), $r['nota'], $r['cliente'], $r['telefone'],
            $r['email_cliente'], $r['atendente'], $r['link_conversa'], $r['feedback_cliente'], $r['ia_motivo'],
            rotulo('CATEGORIAS', $r['ia_categoria']), rotulo('RESPONSAVEIS', $r['ia_responsavel']), $r['ia_gravidade'],
            $r['ia_sugestao'], rotulo('CATEGORIAS', $r['classificacao']), $r['observacao'], $r['supervisor'], $r['status'],
            isset($r['retorno_posvendas']) ? $r['retorno_posvendas'] : '',
            isset($r['origem']) && $r['origem'] === 'ia' ? 'Auditoria por IA (sem nota)' : 'Nota do cliente'), ';');
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return $csv;
}

// ---------------------------------------------------------------- Guta (IA de atendimento)
// Definição provisória: conversa sem atendente humano atribuído = atendida só pela Guta.
function kpisGuta($F, $ini, $fim)
{
    $p = array();
    $w = where(array('atendente' => '', 'time' => $F['time'], 'caixa' => $F['caixa']), $ini, $fim, $p);
    return q1("SELECT count(*) AS conversas,
                      count(*) FILTER (WHERE assignee_id IS NULL) AS so_guta,
                      count(*) FILTER (WHERE assignee_id IS NULL AND resolvida) AS resolvidas_guta,
                      count(*) FILTER (WHERE assignee_id IS NOT NULL) AS com_humano
               FROM auditoria.vw_painel_conversas WHERE $w", $p);
}

function serieGuta($F)
{
    $g = $F['grao'];
    $p = array();
    $w = where(array('atendente' => '', 'time' => $F['time'], 'caixa' => $F['caixa']), $F['ini'], $F['fim'], $p);
    return q("SELECT $g::text AS b,
                     round(100.0 * count(*) FILTER (WHERE assignee_id IS NULL AND resolvida) / NULLIF(count(*), 0), 1) AS pct_guta,
                     round(100.0 * count(*) FILTER (WHERE assignee_id IS NOT NULL) / NULLIF(count(*), 0), 1) AS pct_humano
              FROM auditoria.vw_painel_conversas WHERE $w GROUP BY 1 ORDER BY 1", $p);
}

// ---------------------------------------------------------------- metas (semáforo)
/** Retorna array(classe, texto) — classe: ok | atencao | fora | '' (sem meta/valor) */
function semaforo($valor, $meta, $maiorMelhor, $fmtMeta)
{
    if ($valor === null || $meta === '' || $meta === null || !confLigado('metas_ativo')) {
        return array('', '');
    }
    $v = (float)$valor;
    $m = (float)$meta;
    $folga = abs($m) * 0.05;
    if ($maiorMelhor) {
        $cl = $v >= $m ? 'ok' : ($v >= $m - $folga ? 'atencao' : 'fora');
    } else {
        $cl = $v <= $m ? 'ok' : ($v <= $m + $folga ? 'atencao' : 'fora');
    }
    $txt = array('ok' => 'Dentro da meta', 'atencao' => 'Perto da meta', 'fora' => 'Fora da meta');
    return array($cl, $txt[$cl] . ' (' . $fmtMeta . ')');
}

// ---------------------------------------------------------------- pós-vendas
function kpisPosVendas()
{
    return q1("SELECT count(*) FILTER (WHERE status IN ('enviado','tratado')) AS enviados,
                      count(*) FILTER (WHERE status = 'tratado') AS tratados,
                      percentile_cont(0.5) WITHIN GROUP (ORDER BY extract(epoch FROM tratado_em - enviado_em) / 3600.0)
                        FILTER (WHERE status = 'tratado' AND enviado_em IS NOT NULL) AS horas_ate_tratar
               FROM auditoria.csat_auditoria");
}

// ---------------------------------------------------------------- temas recorrentes
function temasAbertos($lim = 6)
{
    return q("SELECT * FROM auditoria.temas WHERE status IN ('aberto', 'issue_aberta') ORDER BY qtd DESC, ultimo_em DESC LIMIT " . (int)$lim);
}

function taxa($a, $b)
{
    return ((float)$b) > 0 ? 100.0 * (float)$a / (float)$b : null;
}

// ---------------------------------------------------------------- qualidade (IA em todas as conversas)
function kpisQa($F, $ini, $fim)
{
    $p = array();
    $w = where($F, $ini, $fim, $p);
    return q1("SELECT count(*) AS auditadas,
                      count(*) FILTER (WHERE resolvido = 'sim') AS resolvidas,
                      count(*) FILTER (WHERE resolvido = 'parcial') AS parciais,
                      count(*) FILTER (WHERE resolvido = 'nao') AS nao_resolvidas,
                      count(*) FILTER (WHERE resolvido IN ('sim','parcial','nao')) AS conclusivas,
                      round(avg(nota_qa), 1) AS nota_media,
                      count(*) FILTER (WHERE risco = 'alto') AS risco_alto,
                      count(*) FILTER (WHERE risco = 'alto' AND nota_csat IS NULL) AS risco_sem_nota,
                      count(*) FILTER (WHERE cliente_sem_resposta) AS sem_resposta,
                      count(*) FILTER (WHERE nota_csat IS NULL) AS sem_nota,
                      count(*) FILTER (WHERE sentimento_fim = 'negativo') AS termina_negativo,
                      count(*) FILTER (WHERE sentimento_inicio = 'negativo' AND sentimento_fim <> 'negativo') AS recuperados,
                      percentile_cont(0.5) WITHIN GROUP (ORDER BY maior_espera_min) AS espera_mediana,
                      count(*) FILTER (WHERE NOT teve_humano) AS so_guta,
                      count(*) FILTER (WHERE NOT teve_humano AND resolvido = 'sim') AS so_guta_resolvidas,
                      count(*) FILTER (WHERE teve_humano) AS com_humano,
                      count(*) FILTER (WHERE teve_humano AND resolvido = 'sim') AS com_humano_resolvidas
               FROM auditoria.vw_painel_qa WHERE $w", $p);
}

/** Conversas encerradas no período (denominador da cobertura da auditoria por IA) */
function encerradasPeriodo($F, $ini, $fim)
{
    $p = array();
    $w = where($F, $ini, $fim, $p);
    $r = q1("SELECT count(*) AS n FROM auditoria.vw_painel_conversas WHERE $w AND resolvida", $p);
    return (int)$r['n'];
}

function serieQa($F)
{
    $g = $F['grao'];
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT $g::text AS b, round(avg(nota_qa), 1) AS nota,
                     round(100.0 * count(*) FILTER (WHERE resolvido = 'sim') / NULLIF(count(*) FILTER (WHERE resolvido IN ('sim','parcial','nao')), 0), 1) AS pct_resolvido,
                     count(*) AS n
              FROM auditoria.vw_painel_qa WHERE $w GROUP BY 1 ORDER BY 1", $p);
}

function criteriosQa($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT e.key AS k, round(avg(e.value::numeric), 1) AS media, count(*) AS n
              FROM auditoria.vw_painel_qa v, jsonb_each_text(v.criterios) e
              WHERE $w AND e.value ~ '^[0-9.]+$' GROUP BY 1 ORDER BY 2", $p);
}

function falhasQa($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT f->>'criterio' AS k, count(*) AS n
              FROM auditoria.vw_painel_qa v, jsonb_array_elements(v.falhas) f
              WHERE $w AND jsonb_typeof(v.falhas) = 'array' GROUP BY 1 ORDER BY 2 DESC LIMIT 8", $p);
}

function motivosContatoQa($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT motivo_contato AS k, count(*) AS n,
                     round(100.0 * count(*) FILTER (WHERE resolvido = 'sim') / NULLIF(count(*) FILTER (WHERE resolvido IN ('sim','parcial','nao')), 0), 0) AS pct_resolvido,
                     round(avg(nota_qa)) AS nota
              FROM auditoria.vw_painel_qa WHERE $w GROUP BY 1 ORDER BY 2 DESC", $p);
}

function conversasEmRisco($F, $lim = 12)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    return q("SELECT v.conversation_id, v.assunto, v.resumo, v.atendente, v.nota_qa, v.resolvido, v.risco, v.nota_csat,
                     v.cliente_sem_resposta, v.maior_espera_min, v.falhas, v.criada_em, v.teve_humano,
                     EXISTS (SELECT 1 FROM auditoria.csat_auditoria a WHERE a.conversation_id = v.conversation_id) AS na_fila
              FROM auditoria.vw_painel_qa v
              WHERE $w AND (v.risco = 'alto' OR v.resolvido = 'nao')
              ORDER BY (v.risco = 'alto') DESC, (v.nota_csat IS NULL) DESC, v.nota_qa ASC NULLS LAST, v.criada_em DESC
              LIMIT " . (int)$lim, $p);
}

function qaPorAtendente($F)
{
    $p = array();
    $w = where($F, $F['ini'], $F['fim'], $p);
    $r = q("SELECT assignee_id AS id, count(*) AS auditadas, round(avg(nota_qa)) AS nota_qa,
                   round(100.0 * count(*) FILTER (WHERE resolvido = 'sim') / NULLIF(count(*) FILTER (WHERE resolvido IN ('sim','parcial','nao')), 0), 0) AS pct_real
            FROM auditoria.vw_painel_qa WHERE $w AND assignee_id IS NOT NULL GROUP BY 1", $p);
    $m = array();
    foreach ($r as $x) {
        $m[(int)$x['id']] = $x;
    }
    return $m;
}

/** Botão "i" que abre a explicação do indicador (textos em src/views/explicacoes.php) */
function explica($chave)
{
    return '<button type="button" class="explica" data-explica="' . h($chave) . '" aria-label="O que é este indicador?" aria-expanded="false">i</button>';
}

/** Etiqueta de meta (semáforo) com texto, nunca só cor */
function metaHtml($valor, $meta, $maiorMelhor, $fmtMeta)
{
    list($cl, $txt) = semaforo($valor, $meta, $maiorMelhor, 'meta ' . $fmtMeta);
    return $cl ? ' <span class="meta ' . $cl . '">' . h($txt) . '</span>' : '';
}
