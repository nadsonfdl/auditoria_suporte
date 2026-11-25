<?php
/**
 * Painel de BI — Auditoria do Suporte (iTalk, conta 3)
 * URL: https://SEU-SERVIDOR/auditoria_suporte/painel.php
 * Usuários em config/config.php -> 'painel_usuarios' (gere com: php bin/criar_usuario.php nome senha)
 * Compatível com PHP 5.6+
 */
ini_set('display_errors', '0'); // erros vão para o log do Apache, nunca para a tela
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/painel.php';
date_default_timezone_set('America/Sao_Paulo');

// ---------------------------------------------------------------- sessão e segurança
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_name('auditoria_painel');
session_set_cookie_params(0, '/', '', $https, true);
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$SELF = strtok($_SERVER['REQUEST_URI'], '?');

function csrf()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
    }
    return $_SESSION['csrf'];
}

function checaCsrf()
{
    if (!isset($_POST['csrf']) || !hash_equals(csrf(), (string)$_POST['csrf'])) {
        http_response_code(400);
        exit('Sessão expirada. Volte e recarregue a página.');
    }
}

/** Monta URL do próprio painel mantendo os filtros atuais */
function url($mudar = array(), $tirar = array())
{
    global $SELF;
    $q = $_GET;
    foreach ($tirar as $k) {
        unset($q[$k]);
    }
    foreach ($mudar as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return $SELF . ($q ? '?' . http_build_query($q) : '');
}

if (isset($_GET['sair'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: ' . $SELF);
    exit;
}

// ---------------------------------------------------------------- login
if (empty($_SESSION['usuario'])) {
    $erro = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        checaCsrf();
        $usuarios = cfg('painel_usuarios');
        $u = strtolower(trim((string)(isset($_POST['usuario']) ? $_POST['usuario'] : '')));
        $s = (string)(isset($_POST['senha']) ? $_POST['senha'] : '');
        if (is_array($usuarios) && isset($usuarios[$u]) && password_verify($s, $usuarios[$u])) {
            session_regenerate_id(true);
            $_SESSION['usuario'] = $u;
            header('Location: ' . $SELF);
            exit;
        }
        sleep(2);
        $erro = 'Usuário ou senha inválidos.';
    }
    require __DIR__ . '/../src/views/login.php';
    exit;
}

$USUARIO = $_SESSION['usuario'];
$pagina  = isset($_GET['p']) && in_array($_GET['p'], array('visao', 'atendentes', 'fila'), true) ? $_GET['p'] : 'visao';

// ---------------------------------------------------------------- ações (POST) da fila
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checaCsrf();
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';
    if ($acao === 'avaliar' && isset($_POST['id']) && ctype_digit((string)$_POST['id'])) {
        $class = isset($_POST['classificacao']) ? trim((string)$_POST['classificacao']) : '';
        $obs   = isset($_POST['observacao']) ? trim((string)$_POST['observacao']) : '';
        db()->prepare("UPDATE auditoria.csat_auditoria
                       SET classificacao = ?, observacao = ?, supervisor = ?, avaliado_em = now(),
                           status = CASE WHEN status = 'pendente' THEN 'avaliado' ELSE status END
                       WHERE id = ?")
            ->execute(array($class !== '' ? $class : null, $obs !== '' ? $obs : null, $USUARIO, (int)$_POST['id']));
        $_SESSION['flash'] = 'Avaliação salva.';
        header('Location: ' . url(array(), array()) . '#item-' . (int)$_POST['id']);
        exit;
    }
    if ($acao === 'enviar_semana' && isset($_POST['semana']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['semana'])) {
        $st = db()->prepare("UPDATE auditoria.csat_auditoria SET status = 'enviado', enviado_em = now()
                             WHERE semana_ref = ?::date AND status = 'avaliado'");
        $st->execute(array($_POST['semana']));
        $_SESSION['flash'] = $st->rowCount() . ($st->rowCount() == 1 ? ' nota marcada como enviada' : ' notas marcadas como enviadas') . ' ao pós-vendas.';
        header('Location: ' . url());
        exit;
    }
}

// ---------------------------------------------------------------- exportação CSV (pós-vendas)
if ($pagina === 'fila' && isset($_GET['csv'])) {
    $sem = isset($_GET['semana']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['semana']) ? (string)$_GET['semana'] : '';
    $stCsv = isset($_GET['status']) && in_array($_GET['status'], array('pendente', 'avaliado', 'enviado', 'tratado'), true) ? $_GET['status'] : '';
    $rows = itensFila($sem, $stCsv, '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_csat_' . ($sem ? $sem : 'todas') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array('Semana', 'Respondido em', 'Nota', 'Cliente', 'Telefone', 'E-mail', 'Atendente', 'Link da conversa',
        'Comentário do cliente', 'Motivo (IA)', 'Categoria (IA)', 'Responsável (IA)', 'Gravidade (IA)', 'Sugestão (IA)',
        'Classificação do supervisor', 'Observação do supervisor', 'Supervisor', 'Status'), ';');
    foreach ($rows as $r) {
        fputcsv($out, array($r['semana_ref'], substr($r['respondido_em'], 0, 16), $r['nota'], $r['cliente'], $r['telefone'],
            $r['email_cliente'], $r['atendente'], $r['link_conversa'], $r['feedback_cliente'], $r['ia_motivo'],
            rotulo('CATEGORIAS', $r['ia_categoria']), rotulo('RESPONSAVEIS', $r['ia_responsavel']), $r['ia_gravidade'],
            $r['ia_sugestao'], rotulo('CATEGORIAS', $r['classificacao']), $r['observacao'], $r['supervisor'], $r['status']), ';');
    }
    exit;
}

$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : '';
unset($_SESSION['flash']);

// ---------------------------------------------------------------- dados
$F     = lerFiltros();
$OPC   = opcoes();
$corte = notaCorte();

function delta($atual, $anterior, $tipo, $melhorQuandoSobe)
{
    if ($atual === null || $anterior === null || $anterior === '' || ($tipo === 'rel' && (float)$anterior == 0)) {
        return '<span class="delta neutro">sem comparação</span>';
    }
    $d = $tipo === 'rel' ? 100.0 * ((float)$atual - (float)$anterior) / (float)$anterior : (float)$atual - (float)$anterior;
    if (abs($d) < 0.05) {
        return '<span class="delta neutro">estável</span>';
    }
    $bom = ($d > 0) === $melhorQuandoSobe;
    $txt = ($d > 0 ? '▲ ' : '▼ ') . number_format(abs($d), 1, ',', '.') . ($tipo === 'pp' ? ' p.p.' : ($tipo === 'abs' ? '' : '%'));
    return '<span class="delta ' . ($bom ? 'bom' : 'ruim') . '">' . $txt . '</span>';
}

function deltaMin($atual, $anterior)
{
    if ($atual === null || $anterior === null || (float)$anterior == 0) {
        return '<span class="delta neutro">sem comparação</span>';
    }
    $d = 100.0 * ((float)$atual - (float)$anterior) / (float)$anterior;
    if (abs($d) < 0.5) {
        return '<span class="delta neutro">estável</span>';
    }
    return '<span class="delta ' . ($d < 0 ? 'bom' : 'ruim') . '">' . ($d > 0 ? '▲ ' : '▼ ') . number_format(abs($d), 0, ',', '.') . '%</span>';
}

function taxa($a, $b)
{
    return ((float)$b) > 0 ? 100.0 * (float)$a / (float)$b : null;
}

function rotuloBucket($b, $grao)
{
    $t = strtotime($b);
    return $grao === 'dia' ? date('d/m', $t) : 'sem ' . date('d/m', $t);
}

if ($pagina === 'visao') {
    $kc  = kpisConversas($F, $F['ini'], $F['fim']);
    $kca = kpisConversas($F, $F['ant_ini'], $F['ant_fim']);
    $ks  = kpisCsat($F, $F['ini'], $F['fim'], $corte);
    $ksa = kpisCsat($F, $F['ant_ini'], $F['ant_fim'], $corte);
    $pend = pendentesAuditoria($F);
    $sc  = serieConversas($F);
    $ss  = serieCsat($F);
    $dist = distribuicaoNotas($F);
    $calor = mapaCalor($F);
    $caixas = porCaixa($F);
    $iaCat = porIA($F, 'ia_categoria');
    $iaResp = porIA($F, 'ia_responsavel');
    $analises = ultimasAnalises($F);

    $taxaRes  = taxa($kc['resolvidas'], $kc['conversas']);
    $taxaResA = taxa($kca['resolvidas'], $kca['conversas']);
    $taxaSR   = taxa($kc['sem_reabrir'], $kc['rastreadas']);
    $taxaSRA  = taxa($kca['sem_reabrir'], $kca['rastreadas']);
    $satisf   = taxa($ks['satisfeitos'], $ks['respostas']);
    $satisfA  = taxa($ksa['satisfeitos'], $ksa['respostas']);

    $maxCalor = 0;
    foreach ($calor as $linha) {
        $maxCalor = max($maxCalor, max($linha));
    }
}
if ($pagina === 'atendentes') {
    $rank = rankingAtendentes($F, $corte);
    $maxConv = 1;
    foreach ($rank as $r) {
        $maxConv = max($maxConv, (int)$r['conversas']);
    }
}
if ($pagina === 'fila') {
    $semanas = semanasFila();
    $semSel  = isset($_GET['semana']) ? (string)$_GET['semana'] : ($semanas ? $semanas[0]['s'] : '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $semSel)) {
        $semSel = ''; // "todas" ou valor inválido
    }
    $stSel   = isset($_GET['status']) && in_array($_GET['status'], array('pendente', 'avaliado', 'enviado', 'tratado'), true) ? $_GET['status'] : '';
    $itens   = itensFila($semSel, $stSel, $F['atendente']);
    $contagem = array('pendente' => 0, 'avaliado' => 0, 'enviado' => 0, 'tratado' => 0);
    foreach (itensFila($semSel, '', $F['atendente']) as $x) {
        $contagem[$x['status']]++;
    }
}


// ---------------------------------------------------------------- textos e derivados para as views
$DIAS_NOME = array(1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo');

if ($F['periodo'] === 'tudo') {
    $PERIODO_TXT = 'desde o início dos dados';
    $PERIODO_CURTO = 'Todo o histórico';
} elseif ($F['periodo'] === 'custom') {
    $PERIODO_TXT = 'entre ' . date('d/m', strtotime($F['ini'])) . ' e ' . date('d/m', strtotime($F['fim']));
    $PERIODO_CURTO = date('d/m/Y', strtotime($F['ini'])) . ' a ' . date('d/m/Y', strtotime($F['fim']));
} else {
    $PERIODO_TXT = 'nos últimos ' . $F['periodo'] . ' dias';
    $PERIODO_CURTO = 'Últimos ' . $F['periodo'] . ' dias';
}

/** Minigráfico de tendência em SVG (sem JS) */
function spark($valores, $cor)
{
    $v = array_values(array_filter($valores, function ($x) { return $x !== null; }));
    if (count($v) < 2) {
        return '';
    }
    $min = min($v);
    $max = max($v);
    $amp = ($max - $min) ?: 1;
    $w = 64;
    $h = 22;
    $pts = array();
    $n = count($v);
    foreach ($v as $i => $x) {
        $pts[] = round($i * $w / ($n - 1), 1) . ',' . round($h - 3 - ($x - $min) / $amp * ($h - 6), 1);
    }
    $ult = explode(',', end($pts));
    return '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true">'
         . '<polyline fill="none" stroke="' . $cor . '" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $pts) . '"/>'
         . '<circle cx="' . $ult[0] . '" cy="' . $ult[1] . '" r="2.75" fill="' . $cor . '"/></svg>';
}

if ($pagina === 'visao') {
    // pico de demanda
    $pico = array('d' => null, 'h' => null, 'n' => 0);
    foreach ($calor as $d => $linha) {
        foreach ($linha as $hh => $n) {
            if ($n > $pico['n']) {
                $pico = array('d' => $d, 'h' => $hh, 'n' => $n);
            }
        }
    }
    // diagnóstico em texto, montado a partir dos dados
    $totIA = 0;
    foreach ($iaCat as $x) {
        $totIA += (int)$x['n'];
    }
    $DIAG_1 = num($kc['conversas']) . ' conversas ' . $PERIODO_TXT . '. ' . pct($taxaRes) . ' foram encerradas no sistema.';
    if ($totIA > 0) {
        $top = $iaCat[0];
        $DIAG_2 = 'Mas ' . ((int)$top['n'] === $totIA ? 'todas as' : num($top['n']) . ' de') . ' ' . num($totIA)
                . ' notas baixas analisadas pela IA apontam ' . lcfirst(rotulo('CATEGORIAS', $top['k'])) . '.';
    } elseif ((int)$ks['baixas'] > 0) {
        $DIAG_2 = num($ks['baixas']) . ' clientes deram nota baixa e aguardam análise.';
    } else {
        $DIAG_2 = 'Nenhuma nota baixa no período.';
    }
    $respAt = 0;
    foreach ($iaResp as $x) {
        if ($x['k'] === 'atendente') {
            $respAt = (int)$x['n'];
        }
    }
    $DIAG_NOTAS = array();
    if ($totIA > 0) {
        $DIAG_NOTAS[] = 'Em ' . num($respAt) . ' delas a responsabilidade foi do atendimento; nas demais, de produto, processo ou do próprio cliente.';
    }
    if ($kc['mediana_1a'] !== null) {
        $DIAG_NOTAS[] = 'A primeira resposta leva ' . duracao($kc['mediana_1a']) . ' (mediana).';
    }
    if ($pico['d']) {
        $DIAG_NOTAS[] = 'O pico de demanda é ' . ($pico['d'] >= 6 ? 'no ' : 'na ') . $DIAS_NOME[$pico['d']] . ', entre ' . $pico['h'] . 'h e ' . ($pico['h'] + 1) . 'h.';
    }
    $GAP = $taxaRes !== null && $satisf !== null ? $taxaRes - $satisf : null;
}

$PEND_TOTAL = pendentesAuditoria(array('atendente' => '', 'time' => '', 'caixa' => ''));

require __DIR__ . '/../src/views/layout_topo.php';
require __DIR__ . '/../src/views/' . $pagina . '.php';
require __DIR__ . '/../src/views/layout_fim.php';
