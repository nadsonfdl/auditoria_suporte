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
