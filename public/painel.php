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
    ?><!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoria do Suporte · Entrar</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,system-ui,sans-serif;background:#0e3d7e;background:radial-gradient(1200px 600px at 20% 0%,#1b64be 0%,#0e3d7e 55%,#0a2a57 100%);color:#191919}
.box{width:min(380px,92vw);background:#fff;border-radius:16px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.marca{display:flex;align-items:center;gap:10px;margin-bottom:22px}.logo{width:36px;height:36px;border-radius:10px;background:#257de0;display:grid;place-items:center;color:#fff;font-weight:700}
h1{font-size:18px;margin:0}small{color:#666}label{display:block;font-size:13px;font-weight:500;margin:14px 0 6px}
input{width:100%;padding:11px 12px;border:1px solid #d6d6d6;border-radius:10px;font:inherit}input:focus{outline:2px solid #84b9f3;border-color:#257de0}
button{margin-top:20px;width:100%;padding:12px;border:0;border-radius:10px;background:#257de0;color:#fff;font:600 15px Inter,sans-serif;cursor:pointer}button:hover{background:#1b64be}
.erro{background:#fdecec;color:#a32020;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:6px}
</style></head><body>
<form class="box" method="post">
  <div class="marca"><div class="logo">iT</div><div><h1>Auditoria do Suporte</h1><small>iTalk · conta Suporte</small></div></div>
  <?php if ($erro): ?><div class="erro"><?= h($erro) ?></div><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="login" value="1">
  <label for="u">Usuário</label><input id="u" name="usuario" autocomplete="username" required autofocus>
  <label for="s">Senha</label><input id="s" name="senha" type="password" autocomplete="current-password" required>
  <button type="submit">Entrar</button>
</form></body></html><?php
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
        $_SESSION['flash'] = $st->rowCount() . ' item(ns) marcado(s) como enviado(s) ao pós-vendas.';
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
        return '<span class="delta neutro">sem base de comparação</span>';
    }
    $d = $tipo === 'rel' ? 100.0 * ((float)$atual - (float)$anterior) / (float)$anterior : (float)$atual - (float)$anterior;
    if (abs($d) < 0.05) {
        return '<span class="delta neutro">= estável</span>';
    }
    $bom = ($d > 0) === $melhorQuandoSobe;
    $txt = ($d > 0 ? '▲ ' : '▼ ') . number_format(abs($d), 1, ',', '.') . ($tipo === 'pp' ? ' p.p.' : ($tipo === 'abs' ? '' : '%'));
    return '<span class="delta ' . ($bom ? 'bom' : 'ruim') . '">' . $txt . '</span>';
}

function deltaMin($atual, $anterior)
{
    if ($atual === null || $anterior === null || (float)$anterior == 0) {
        return '<span class="delta neutro">sem base de comparação</span>';
    }
    $d = 100.0 * ((float)$atual - (float)$anterior) / (float)$anterior;
    if (abs($d) < 0.5) {
        return '<span class="delta neutro">= estável</span>';
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

$ROTULO_PERIODO = $F['periodo'] === 'tudo' ? 'Todo o histórico'
    : ($F['periodo'] === 'custom' ? date('d/m/Y', strtotime($F['ini'])) . ' a ' . date('d/m/Y', strtotime($F['fim']))
    : 'Últimos ' . $F['periodo'] . ' dias');
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoria do Suporte · iTalk</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<?php if ($pagina === 'visao'): ?><script><?php readfile(__DIR__ . '/../src/vendor/chart.umd.min.js'); /* Chart.js 4.4.1, MIT */ ?></script><?php endif; ?>
<style>
:root{
  --plano:#f4f6f9; --superficie:#ffffff; --tinta:#191919; --tinta-2:#666666; --linha:#e7e7e7; --eixo:#c2c2c2;
  --borda:rgba(11,11,11,.08); --s1:#257de0; --s2:#f47230; --s1-escuro:#1b64be; --navy:#0e3d7e;
  --bom:#006c10; --bom-bg:#e6f4e8; --ruim:#b3261e; --ruim-bg:#fdecec; --alerta:#8a5a00; --alerta-bg:#fff4d6;
  --raio:14px; --sombra:0 1px 2px rgba(16,24,40,.04),0 1px 3px rgba(16,24,40,.06);
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,system-ui,-apple-system,sans-serif;background:var(--plano);color:var(--tinta);font-size:14px;-webkit-font-smoothing:antialiased}
a{color:var(--s1-escuro);text-decoration:none}a:hover{text-decoration:underline}
/* topo */
.topo{background:linear-gradient(120deg,#0a2a57 0%,var(--navy) 45%,#1757ac 100%);color:#fff}
.topo-in{max-width:1400px;margin:0 auto;padding:18px 28px 0}
.linha-topo{display:flex;align-items:center;justify-content:space-between;gap:16px}
.marca{display:flex;align-items:center;gap:12px}.logo{width:38px;height:38px;border-radius:11px;background:rgba(255,255,255,.14);display:grid;place-items:center;font-weight:700;letter-spacing:-.5px}
.marca h1{font-size:17px;margin:0;font-weight:650;letter-spacing:-.2px}.marca small{color:rgba(255,255,255,.7);font-size:12px}
.usuario{font-size:13px;color:rgba(255,255,255,.85)}.usuario a{color:#fff;margin-left:12px;opacity:.85}
.abas{display:flex;gap:4px;margin-top:18px}
.abas a{color:rgba(255,255,255,.75);padding:11px 16px;border-radius:10px 10px 0 0;font-weight:500;text-decoration:none}
.abas a:hover{color:#fff;background:rgba(255,255,255,.08)}
.abas a.ativa{background:var(--plano);color:var(--tinta);font-weight:600}
.abas .cont{display:inline-block;min-width:20px;padding:1px 7px;margin-left:6px;border-radius:99px;background:#ec4647;color:#fff;font-size:11px;font-weight:700}
/* corpo */
.corpo{max-width:1400px;margin:0 auto;padding:22px 28px 60px}
.filtros{display:flex;flex-wrap:wrap;gap:10px;align-items:center;background:var(--superficie);border:1px solid var(--borda);border-radius:var(--raio);padding:12px 14px;box-shadow:var(--sombra);margin-bottom:20px}
.seg{display:inline-flex;background:#f0f2f5;border-radius:10px;padding:3px}
.seg a{padding:7px 12px;border-radius:8px;color:var(--tinta-2);font-weight:500;font-size:13px;text-decoration:none}
.seg a.on{background:#fff;color:var(--tinta);box-shadow:0 1px 2px rgba(0,0,0,.08);font-weight:600}
.filtros select,.filtros input[type=date]{padding:8px 10px;border:1px solid #dcdfe4;border-radius:9px;font:inherit;font-size:13px;background:#fff;color:var(--tinta);max-width:200px}
.filtros .sep{width:1px;height:26px;background:var(--linha)}
.filtros .resumo{margin-left:auto;color:var(--tinta-2);font-size:12.5px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:9px;border:1px solid #dcdfe4;background:#fff;font:600 13px Inter,sans-serif;color:var(--tinta);cursor:pointer;text-decoration:none}
.btn:hover{background:#f6f7f9;text-decoration:none}.btn.pri{background:var(--s1);border-color:var(--s1);color:#fff}.btn.pri:hover{background:var(--s1-escuro)}
.flash{background:var(--bom-bg);color:var(--bom);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-weight:500}
/* KPIs */
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px}
.kpi{background:var(--superficie);border:1px solid var(--borda);border-radius:var(--raio);padding:16px 18px;box-shadow:var(--sombra);position:relative}
.kpi .rot{font-size:12.5px;color:var(--tinta-2);font-weight:500;display:flex;align-items:center;gap:6px}
.kpi .val{font-size:30px;font-weight:700;letter-spacing:-.8px;margin:6px 0 4px;font-variant-numeric:tabular-nums}
.kpi .val small{font-size:15px;color:var(--tinta-2);font-weight:500;letter-spacing:0}
.kpi .sub{font-size:12px;color:var(--tinta-2);display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.kpi.destaque{background:linear-gradient(135deg,#fff 0%,#f3f8ff 100%);border-color:#cce3fd}
.kpi.acao{border-color:#f6c2c2;background:linear-gradient(135deg,#fff 0%,#fff6f6 100%)}
.delta{font-weight:600;font-size:12px;padding:2px 7px;border-radius:99px}
.delta.bom{color:var(--bom);background:var(--bom-bg)}.delta.ruim{color:var(--ruim);background:var(--ruim-bg)}.delta.neutro{color:var(--tinta-2);background:#f0f2f5;font-weight:500}
.info{display:inline-grid;place-items:center;width:15px;height:15px;border-radius:50%;background:#eceff3;color:#666;font-size:10px;font-weight:700;cursor:help}
/* cartões */
.grade{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
.c12{grid-column:span 12}.c8{grid-column:span 8}.c7{grid-column:span 7}.c6{grid-column:span 6}.c5{grid-column:span 5}.c4{grid-column:span 4}
@media (max-width:1100px){.c8,.c7,.c6,.c5,.c4{grid-column:span 12}}
.cartao{background:var(--superficie);border:1px solid var(--borda);border-radius:var(--raio);padding:18px 20px;box-shadow:var(--sombra);min-width:0}
.cartao h3{margin:0;font-size:14.5px;font-weight:650;letter-spacing:-.1px}
.cartao .desc{margin:3px 0 14px;color:var(--tinta-2);font-size:12.5px}
.graf{position:relative;height:250px}.graf.alto{height:300px}
.secao{display:flex;align-items:baseline;gap:10px;margin:28px 0 12px}.secao h2{font-size:16px;margin:0;font-weight:650}.secao span{color:var(--tinta-2);font-size:13px}
.vazio{color:var(--tinta-2);font-size:13px;padding:30px 0;text-align:center}
/* barras horizontais em HTML */
.hbar{display:grid;grid-template-columns:minmax(120px,42%) 1fr auto;gap:8px 10px;align-items:center;font-size:13px}
.hbar .t{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--tinta)}
.hbar .b{height:12px;background:#f0f2f5;border-radius:4px;overflow:hidden}
.hbar .b i{display:block;height:100%;background:var(--s1);border-radius:0 4px 4px 0}
.hbar .n{font-variant-numeric:tabular-nums;color:var(--tinta-2);font-size:12.5px;min-width:52px;text-align:right}
/* mapa de calor */
.calor{display:grid;grid-template-columns:38px repeat(24,1fr);gap:2px;font-size:11px}
.calor .hd{color:var(--tinta-2);text-align:center;font-size:10.5px}
.calor .dl{color:var(--tinta-2);display:flex;align-items:center;font-weight:500}
.calor .cel{aspect-ratio:1/1;border-radius:3px;min-height:14px;cursor:default}
.calor .cel:hover{outline:2px solid var(--tinta);outline-offset:-1px}
.legenda-calor{display:flex;align-items:center;gap:6px;margin-top:12px;color:var(--tinta-2);font-size:11.5px}
.legenda-calor i{width:22px;height:10px;border-radius:2px;display:inline-block}
/* análises IA */
.analise{display:grid;grid-template-columns:auto 1fr;gap:12px;padding:12px 0;border-top:1px solid var(--linha)}
.analise:first-of-type{border-top:0;padding-top:2px}
.nota{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;font-weight:700;font-size:15px;color:#fff;background:#ec4647}
.nota.n2{background:#f08a8b}.nota.n3{background:#9a9a9a}.nota.n4{background:#84b9f3}.nota.n5{background:#257de0}
.analise .m{font-weight:500;line-height:1.4}.analise .meta{color:var(--tinta-2);font-size:12px;margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.tag{display:inline-block;padding:2px 8px;border-radius:99px;background:#eef4fd;color:#1757ac;font-size:11.5px;font-weight:600}
.tag.cinza{background:#f0f2f5;color:#555}.tag.alta{background:var(--ruim-bg);color:var(--ruim)}.tag.media{background:var(--alerta-bg);color:var(--alerta)}.tag.baixa{background:#f0f2f5;color:#555}
/* tabela */
table.rank{width:100%;border-collapse:collapse;font-size:13px}
table.rank th{text-align:left;font-weight:600;color:var(--tinta-2);font-size:12px;padding:10px 10px;border-bottom:1px solid var(--linha);cursor:pointer;user-select:none;white-space:nowrap}
table.rank th:hover{color:var(--tinta)}table.rank th.num,table.rank td.num{text-align:right}
table.rank td{padding:11px 10px;border-bottom:1px solid #f1f1f1;font-variant-numeric:tabular-nums;vertical-align:middle}
table.rank tr:hover td{background:#fafbfc}
.mini{display:flex;align-items:center;gap:8px;justify-content:flex-end}.mini b{display:block;height:8px;border-radius:0 4px 4px 0;background:var(--s1);min-width:2px}
.csat{font-weight:700}.csat.alto{color:var(--bom)}.csat.baixo{color:var(--ruim)}
/* fila */
.status-chips{display:flex;gap:8px;flex-wrap:wrap}
.chip{padding:7px 12px;border-radius:99px;border:1px solid #dcdfe4;background:#fff;color:var(--tinta);font-size:13px;font-weight:500;text-decoration:none}
.chip:hover{text-decoration:none;background:#f6f7f9}.chip.on{background:var(--tinta);color:#fff;border-color:var(--tinta)}.chip b{margin-left:4px}
.item{background:var(--superficie);border:1px solid var(--borda);border-radius:var(--raio);box-shadow:var(--sombra);margin-bottom:14px;overflow:hidden;scroll-margin-top:16px}
.item-top{display:flex;gap:14px;align-items:center;padding:14px 18px;border-bottom:1px solid var(--linha);flex-wrap:wrap}
.item-top .quem{font-weight:600}.item-top .det{color:var(--tinta-2);font-size:12.5px}
.item-top .dir{margin-left:auto;display:flex;gap:8px;align-items:center}
.st{font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:99px;text-transform:uppercase;letter-spacing:.3px}
.st.pendente{background:var(--alerta-bg);color:var(--alerta)}.st.avaliado{background:#eef4fd;color:#1757ac}.st.enviado,.st.tratado{background:var(--bom-bg);color:var(--bom)}
.item-corpo{display:grid;grid-template-columns:1.25fr 1fr;gap:0}
@media (max-width:1000px){.item-corpo{grid-template-columns:1fr}}
.ia{padding:16px 18px;border-right:1px solid var(--linha)}
.ia .com{background:#f7f8fa;border-left:3px solid var(--eixo);padding:9px 12px;border-radius:0 8px 8px 0;margin-bottom:14px;color:#333;font-style:italic}
.ia .mot{font-size:15px;font-weight:600;line-height:1.4;margin:6px 0 8px}
.ia .rot-ia{font-size:11.5px;font-weight:700;color:#6a4ce2;letter-spacing:.4px;text-transform:uppercase;display:flex;gap:6px;align-items:center}
.ia p{margin:8px 0;line-height:1.5;color:#333}
.ia ul{margin:6px 0 0;padding-left:18px;color:#444}.ia li{margin:3px 0;font-size:13px}
.ia .sug{background:#f3f0fe;border-radius:8px;padding:9px 12px;margin-top:12px;font-size:13px;color:#3d2a99}
.aval{padding:16px 18px;background:#fbfcfd}
.aval label{display:block;font-size:12px;font-weight:600;color:var(--tinta-2);margin:0 0 5px}
.aval select,.aval textarea{width:100%;padding:9px 10px;border:1px solid #dcdfe4;border-radius:9px;font:inherit;font-size:13px;background:#fff}
.aval textarea{min-height:96px;resize:vertical;margin-bottom:10px}
.aval select{margin-bottom:12px}
.aval .quem-aval{font-size:12px;color:var(--tinta-2);margin-top:8px}
.barra-acoes{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.barra-acoes .esq{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.barra-acoes .dir{margin-left:auto;display:flex;gap:8px}
.rodape{margin-top:36px;color:var(--tinta-2);font-size:12px;text-align:center}
</style>
</head>
<body>
<header class="topo"><div class="topo-in">
  <div class="linha-topo">
    <div class="marca"><div class="logo">iT</div><div><h1>Auditoria do Suporte</h1><small>iTalk · conta Suporte · <?= h($ROTULO_PERIODO) ?></small></div></div>
    <div class="usuario">Olá, <b><?= h(ucfirst($USUARIO)) ?></b><a href="<?= h($SELF) ?>?sair=1">Sair</a></div>
  </div>
  <nav class="abas">
    <a href="<?= h(url(array('p' => 'visao'), array('semana', 'status'))) ?>" class="<?= $pagina === 'visao' ? 'ativa' : '' ?>">Visão geral</a>
    <a href="<?= h(url(array('p' => 'atendentes'), array('semana', 'status'))) ?>" class="<?= $pagina === 'atendentes' ? 'ativa' : '' ?>">Atendentes</a>
    <?php $pendTot = pendentesAuditoria(array('atendente' => '', 'time' => '', 'caixa' => '')); ?>
    <a href="<?= h(url(array('p' => 'fila'))) ?>" class="<?= $pagina === 'fila' ? 'ativa' : '' ?>">Auditoria CSAT<?php if ($pendTot): ?><span class="cont"><?= (int)$pendTot ?></span><?php endif; ?></a>
  </nav>
</div></header>

<main class="corpo">
<?php if ($flash): ?><div class="flash">✓ <?= h($flash) ?></div><?php endif; ?>

<form class="filtros" method="get" id="filtros">
  <input type="hidden" name="p" value="<?= h($pagina) ?>">
  <?php if ($pagina !== 'fila'): ?>
  <div class="seg">
    <?php foreach (array('7' => '7 dias', '30' => '30 dias', '90' => '90 dias', 'tudo' => 'Tudo') as $k => $v): ?>
      <a href="<?= h(url(array('periodo' => $k), array('ini', 'fim'))) ?>" class="<?= $F['periodo'] === (string)$k ? 'on' : '' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>
  <input type="date" name="ini" value="<?= h($F['ini']) ?>" title="Início"> <input type="date" name="fim" value="<?= h($F['fim']) ?>" title="Fim">
  <input type="hidden" name="periodo" value="custom" id="perCustom" disabled>
  <div class="sep"></div>
  <?php endif; ?>
  <select name="atendente" onchange="this.form.submit()">
    <option value="">Todos os atendentes</option>
    <?php foreach ($OPC['atendentes'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['atendente'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($pagina !== 'fila'): ?>
  <select name="time" onchange="this.form.submit()">
    <option value="">Todos os times</option>
    <?php foreach ($OPC['times'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['time'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="caixa" onchange="this.form.submit()">
    <option value="">Todas as caixas</option>
    <?php foreach ($OPC['caixas'] as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $F['caixa'] === (int)$o['id'] ? 'selected' : '' ?>><?= h($o['name']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($F['periodo'] !== 'custom'): ?><input type="hidden" name="periodo" value="<?= h($F['periodo']) ?>"><?php endif; ?>
  <span class="resumo"><?= date('d/m/Y', strtotime($F['ini'])) ?> – <?= date('d/m/Y', strtotime($F['fim'])) ?> · comparado a <?= date('d/m', strtotime($F['ant_ini'])) ?>–<?= date('d/m', strtotime($F['ant_fim'])) ?></span>
  <?php else: ?>
  <input type="hidden" name="semana" value="<?= h($semSel === '' ? 'todas' : $semSel) ?>">
  <?php if ($stSel): ?><input type="hidden" name="status" value="<?= h($stSel) ?>"><?php endif; ?>
  <?php endif; ?>
</form>

<?php if ($pagina === 'visao'): ?>
<!-- ============================================================ VISÃO GERAL -->
<section class="kpis">
  <div class="kpi">
    <div class="rot">Conversas</div>
    <div class="val"><?= num($kc['conversas']) ?></div>
    <div class="sub"><?= delta($kc['conversas'], $kca['conversas'], 'rel', true) ?> <span><?= num($kc['sem_atendente']) ?> sem atendente</span></div>
  </div>
  <div class="kpi">
    <div class="rot">Resolvidas no sistema <span class="info" title="Conversas com status 'resolvida' no iTalk. Inclui encerramentos automáticos e da Guta — por isso tende a ser alto.">i</span></div>
    <div class="val"><?= pct($taxaRes) ?></div>
    <div class="sub"><?= delta($taxaRes, $taxaResA, 'pp', true) ?> <span><?= num($kc['resolvidas']) ?> conversas</span></div>
  </div>
  <div class="kpi destaque">
    <div class="rot">Resolvidas sem reabrir <span class="info" title="Resolvidas que não voltaram a abrir. Medido pelo webhook, para conversas criadas a partir do início do rastreio.">i</span></div>
    <div class="val"><?= $kc['rastreadas'] ? pct($taxaSR) : '—' ?></div>
    <div class="sub"><?php if ($kc['rastreadas']): ?><?= delta($taxaSR, $taxaSRA, 'pp', true) ?> <span><?= num($kc['reaberturas']) ?> reaberturas</span><?php else: ?><span>rastreio começou agora</span><?php endif; ?></div>
  </div>
  <div class="kpi">
    <div class="rot">Tempo até 1ª resposta <span class="info" title="Mediana do tempo entre a abertura da conversa e a primeira resposta de um atendente.">i</span></div>
    <div class="val"><?= duracao($kc['mediana_1a']) ?></div>
    <div class="sub"><?= deltaMin($kc['mediana_1a'], $kca['mediana_1a']) ?> <span>mediana</span></div>
  </div>
  <div class="kpi">
    <div class="rot">CSAT médio</div>
    <div class="val"><?= $ks['respostas'] ? num($ks['media'], 2) : '—' ?><small> / 5</small></div>
    <div class="sub"><?= delta($ks['media'], $ksa['media'], 'abs', true) ?> <span><?= pct($satisf, 0) ?> satisfeitos · <?= num($ks['respostas']) ?> respostas</span></div>
  </div>
  <a class="kpi acao" href="<?= h(url(array('p' => 'fila', 'status' => 'pendente', 'semana' => 'todas'))) ?>" style="text-decoration:none;color:inherit">
    <div class="rot">Notas baixas (≤ <?= $corte ?>)</div>
    <div class="val"><?= num($ks['baixas']) ?></div>
    <div class="sub"><?= delta($ks['baixas'], $ksa['baixas'], 'rel', false) ?> <span><b><?= num($pend) ?></b> aguardando supervisor →</span></div>
  </a>
</section>

<div class="grade">
  <div class="cartao c7">
    <h3>Volume de conversas</h3><p class="desc">Conversas abertas por <?= $F['grao'] === 'dia' ? 'dia' : 'semana' ?></p>
    <div class="graf"><canvas id="gVolume" aria-label="Volume de conversas"></canvas></div>
  </div>
  <div class="cartao c5">
    <h3>Taxa de resolução</h3><p class="desc">% resolvidas no sistema vs. % resolvidas sem reabrir</p>
    <div class="graf"><canvas id="gResolucao" aria-label="Taxa de resolução"></canvas></div>
  </div>

  <div class="cartao c7">
    <h3>Quando o suporte é procurado</h3><p class="desc">Conversas abertas por dia da semana e hora</p>
    <?php if ($maxCalor): $dias = array(1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom');
        $rampa = array('#f3f6fa', '#cce3fd', '#9dc8f7', '#6aabf1', '#338ced', '#2370cd', '#1757ac', '#0e3d7e'); ?>
    <div class="calor">
      <div></div><?php for ($hh = 0; $hh < 24; $hh++): ?><div class="hd"><?= $hh % 3 === 0 ? $hh . 'h' : '' ?></div><?php endfor; ?>
      <?php foreach ($dias as $d => $nome): ?>
        <div class="dl"><?= $nome ?></div>
        <?php for ($hh = 0; $hh < 24; $hh++): $v = $calor[$d][$hh]; $i = $v ? min(7, 1 + (int)floor(6.999 * $v / $maxCalor)) : 0; ?>
          <div class="cel" style="background:<?= $rampa[$i] ?>" title="<?= $nome ?>, <?= $hh ?>h–<?= $hh + 1 ?>h: <?= num($v) ?> conversa(s)"></div>
        <?php endfor; ?>
      <?php endforeach; ?>
    </div>
    <div class="legenda-calor">menos <?php foreach ($rampa as $cor): ?><i style="background:<?= $cor ?>"></i><?php endforeach; ?> mais · pico: <?= num($maxCalor) ?> conversas/hora</div>
    <?php else: ?><div class="vazio">Sem conversas no período.</div><?php endif; ?>
  </div>
  <div class="cartao c5">
    <h3>Tempo até a 1ª resposta</h3><p class="desc">Mediana em minutos por <?= $F['grao'] === 'dia' ? 'dia' : 'semana' ?></p>
    <div class="graf"><canvas id="gPrimeira" aria-label="Tempo até primeira resposta"></canvas></div>
  </div>

  <div class="cartao c4">
    <h3>CSAT médio</h3><p class="desc">Nota média (1 a 5) por <?= $F['grao'] === 'dia' ? 'dia' : 'semana' ?></p>
    <div class="graf"><canvas id="gCsat" aria-label="CSAT médio"></canvas></div>
  </div>
  <div class="cartao c4">
    <h3>Distribuição das notas</h3><p class="desc">Quantas respostas em cada nota</p>
    <div class="graf"><canvas id="gNotas" aria-label="Distribuição das notas"></canvas></div>
  </div>
  <div class="cartao c4">
    <h3>Volume por caixa de entrada</h3><p class="desc">Canais com mais conversas</p>
    <?php if ($caixas): $mx = (int)$caixas[0]['n']; $tot = array_sum(array_map(function ($x) { return (int)$x['n']; }, $caixas)); ?>
    <div class="hbar">
      <?php foreach ($caixas as $x): ?>
        <div class="t" title="<?= h($x['rotulo']) ?>"><?= h($x['rotulo']) ?></div>
        <div class="b"><i style="width:<?= round(100 * (int)$x['n'] / max(1, $mx), 1) ?>%"></i></div>
        <div class="n"><?= num($x['n']) ?> · <?= pct(100 * (int)$x['n'] / max(1, $tot), 0) ?></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><div class="vazio">Sem dados.</div><?php endif; ?>
  </div>
</div>

<div class="secao"><h2>✦ Por que as notas são baixas</h2><span>análise de IA de cada conversa com nota ≤ <?= $corte ?></span></div>
<div class="grade">
  <div class="cartao c4">
    <h3>Motivos</h3><p class="desc">Categoria atribuída pela IA</p>
    <?php if ($iaCat): $mx = (int)$iaCat[0]['n']; ?>
    <div class="hbar">
      <?php foreach ($iaCat as $x): ?>
        <div class="t"><?= h(rotulo('CATEGORIAS', $x['k'])) ?></div><div class="b"><i style="width:<?= round(100 * $x['n'] / $mx, 1) ?>%"></i></div><div class="n"><?= num($x['n']) ?></div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><div class="vazio">Nenhuma nota baixa analisada no período.</div><?php endif; ?>
    <?php if ($iaResp): $mx = (int)$iaResp[0]['n']; ?>
    <h3 style="margin-top:22px">De quem é a responsabilidade</h3><p class="desc">Segundo a análise da IA</p>
    <div class="hbar">
      <?php foreach ($iaResp as $x): ?>
        <div class="t"><?= h(rotulo('RESPONSAVEIS', $x['k'])) ?></div><div class="b"><i style="width:<?= round(100 * $x['n'] / $mx, 1) ?>%;background:#6a4ce2"></i></div><div class="n"><?= num($x['n']) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="cartao c8">
    <h3>Casos que pedem atenção</h3><p class="desc">Análises mais graves do período · <a href="<?= h(url(array('p' => 'fila', 'semana' => 'todas'))) ?>">abrir fila de auditoria →</a></p>
    <?php if ($analises): foreach ($analises as $a): ?>
      <div class="analise">
        <div class="nota n<?= (int)$a['nota'] ?>"><?= (int)$a['nota'] ?></div>
        <div>
          <div class="m"><?= h($a['ia_motivo']) ?></div>
          <div class="meta">
            <span class="tag"><?= h(rotulo('CATEGORIAS', $a['ia_categoria'])) ?></span>
            <span class="tag cinza"><?= h(rotulo('RESPONSAVEIS', $a['ia_responsavel'])) ?></span>
            <?php if ($a['ia_gravidade']): ?><span class="tag <?= h($a['ia_gravidade']) ?>">gravidade <?= h($a['ia_gravidade']) ?></span><?php endif; ?>
            <span><?= h($a['atendente'] ? $a['atendente'] : 'sem atendente') ?> · <?= date('d/m H:i', strtotime($a['respondido_em'])) ?></span>
            <a href="<?= h(url(array('p' => 'fila', 'semana' => 'todas'))) ?>#item-<?= (int)$a['auditoria_id'] ?>">ver →</a>
          </div>
        </div>
      </div>
    <?php endforeach; else: ?><div class="vazio">Nenhuma análise no período.</div><?php endif; ?>
  </div>
</div>

<?php
$lbl = array(); $vol = array(); $pr = array(); $psr = array(); $p1 = array();
foreach ($sc as $r) {
    $lbl[] = rotuloBucket($r['b'], $F['grao']);
    $vol[] = (int)$r['conversas'];
    $pr[]  = $r['pct_resolvidas'] === null ? null : (float)$r['pct_resolvidas'];
    $psr[] = $r['pct_sem_reabrir'] === null ? null : (float)$r['pct_sem_reabrir'];
    $p1[]  = $r['mediana_1a'] === null ? null : (float)$r['mediana_1a'];
}
$lblS = array(); $med = array(); $resp = array();
foreach ($ss as $r) {
    $lblS[] = rotuloBucket($r['b'], $F['grao']);
    $med[]  = (float)$r['media'];
    $resp[] = (int)$r['respostas'];
}
$temSR = count(array_filter($psr, function ($x) { return $x !== null; })) > 0;
?>
<script>
(function(){
  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = '#666666';
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.boxHeight = 10;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.tooltip.backgroundColor = '#191919';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
  Chart.defaults.plugins.tooltip.titleFont = {weight: '600'};
  Chart.defaults.maintainAspectRatio = false;
  var grade = {color: '#e7e7e7', drawTicks: false}, borda = {display: false};
  var eixoX = {grid: {display: false}, border: {color: '#c2c2c2'}, ticks: {maxRotation: 0, autoSkipPadding: 12}};
  var cruz = {mode: 'index', intersect: false};
  var fmt = function(v){ return v === null ? '—' : v.toLocaleString('pt-BR'); };

  var L = <?= json_encode($lbl) ?>, LS = <?= json_encode($lblS) ?>;

  new Chart(document.getElementById('gVolume'), {
    type: 'bar',
    data: {labels: L, datasets: [{label: 'Conversas', data: <?= json_encode($vol) ?>, backgroundColor: '#257de0', hoverBackgroundColor: '#1b64be', borderRadius: {topLeft: 4, topRight: 4}, borderSkipped: 'bottom', maxBarThickness: 26, categoryPercentage: .8}]},
    options: {plugins: {legend: {display: false}, tooltip: {callbacks: {label: function(c){ return ' ' + fmt(c.parsed.y) + ' conversas'; }}}},
              interaction: cruz, scales: {x: eixoX, y: {beginAtZero: true, grid: grade, border: borda, ticks: {precision: 0}}}}
  });

  var dsRes = [{label: 'Resolvidas no sistema', data: <?= json_encode($pr) ?>, borderColor: '#257de0', backgroundColor: '#257de0', borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .25, spanGaps: true}];
  <?php if ($temSR): ?>
  dsRes.push({label: 'Resolvidas sem reabrir', data: <?= json_encode($psr) ?>, borderColor: '#f47230', backgroundColor: '#f47230', borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .25, spanGaps: true});
  <?php endif; ?>
  new Chart(document.getElementById('gResolucao'), {
    type: 'line', data: {labels: L, datasets: dsRes},
    options: {interaction: cruz, plugins: {legend: {display: <?= $temSR ? 'true' : 'false' ?>, position: 'top', align: 'start'},
              tooltip: {callbacks: {label: function(c){ return ' ' + c.dataset.label + ': ' + (c.parsed.y === null ? '—' : c.parsed.y.toLocaleString('pt-BR') + '%'); }}}},
              scales: {x: eixoX, y: {suggestedMin: 50, max: 100, grid: grade, border: borda, ticks: {callback: function(v){ return v + '%'; }}}}}
  });

  new Chart(document.getElementById('gPrimeira'), {
    type: 'line',
    data: {labels: L, datasets: [{label: 'Mediana (min)', data: <?= json_encode($p1) ?>, borderColor: '#257de0', backgroundColor: 'rgba(37,125,224,.08)', fill: true, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .25, spanGaps: true}]},
    options: {interaction: cruz, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function(c){ var m = c.parsed.y; if (m === null) return ' —'; return ' ' + (m < 60 ? Math.round(m) + ' min' : Math.floor(m/60) + 'h ' + Math.round(m%60) + 'min'); }}}},
              scales: {x: eixoX, y: {beginAtZero: true, grid: grade, border: borda, ticks: {callback: function(v){ return v < 60 ? v + ' min' : (v/60).toLocaleString('pt-BR', {maximumFractionDigits: 1}) + 'h'; }}}}}
  });

  new Chart(document.getElementById('gCsat'), {
    type: 'line',
    data: {labels: LS, datasets: [{label: 'CSAT médio', data: <?= json_encode($med) ?>, respostas: <?= json_encode($resp) ?>, borderColor: '#257de0', backgroundColor: '#257de0', borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .25}]},
    options: {interaction: cruz, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function(c){ return ' Média ' + c.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' · ' + c.dataset.respostas[c.dataIndex] + ' respostas'; }}}},
              scales: {x: eixoX, y: {min: 1, max: 5, grid: grade, border: borda, ticks: {stepSize: 1}}}}
  });

  var dist = <?= json_encode(array_values($dist)) ?>, totD = dist.reduce(function(a, b){ return a + b; }, 0) || 1;
  new Chart(document.getElementById('gNotas'), {
    type: 'bar',
    data: {labels: ['1', '2', '3', '4', '5'], datasets: [{label: 'Respostas', data: dist, backgroundColor: ['#ec4647', '#f08a8b', '#bdbdbd', '#84b9f3', '#257de0'], borderRadius: {topLeft: 4, topRight: 4}, borderSkipped: 'bottom', maxBarThickness: 46}]},
    options: {plugins: {legend: {display: false}, tooltip: {callbacks: {title: function(i){ return 'Nota ' + i[0].label; }, label: function(c){ return ' ' + fmt(c.parsed.y) + ' respostas (' + Math.round(100 * c.parsed.y / totD) + '%)'; }}}},
              scales: {x: {grid: {display: false}, border: {color: '#c2c2c2'}, title: {display: true, text: 'Nota'}}, y: {beginAtZero: true, grid: grade, border: borda, ticks: {precision: 0}}}}
  });
})();
</script>

<?php elseif ($pagina === 'atendentes'): ?>
<!-- ============================================================ ATENDENTES -->
<div class="cartao">
  <h3>Desempenho por atendente</h3>
  <p class="desc">Conversas atribuídas no período e CSAT das conversas atendidas. Clique no cabeçalho para ordenar e no nome para filtrar o painel.</p>
  <?php if ($rank): ?>
  <div style="overflow-x:auto">
  <table class="rank" id="tRank">
    <thead><tr>
      <th data-t="s">Atendente</th>
      <th class="num" data-t="n">Conversas</th>
      <th class="num" data-t="n">% resolvidas</th>
      <th class="num" data-t="n">Reaberturas</th>
      <th class="num" data-t="n">1ª resposta (mediana)</th>
      <th class="num" data-t="n">Respostas CSAT</th>
      <th class="num" data-t="n">CSAT médio</th>
      <th class="num" data-t="n">Notas baixas</th>
      <th data-t="s">Motivo mais comum (IA)</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rank as $r): ?>
      <tr>
        <td data-v="<?= h($r['atendente']) ?>"><a href="<?= h(url(array('p' => 'visao', 'atendente' => $r['id']))) ?>"><b><?= h($r['atendente'] ? $r['atendente'] : '#' . $r['id']) ?></b></a></td>
        <td class="num" data-v="<?= (int)$r['conversas'] ?>"><div class="mini"><b style="width:<?= round(70 * $r['conversas'] / $maxConv) ?>px"></b><?= num($r['conversas']) ?></div></td>
        <td class="num" data-v="<?= $r['pct_resolvidas'] === null ? -1 : (float)$r['pct_resolvidas'] ?>"><?= pct($r['pct_resolvidas']) ?></td>
        <td class="num" data-v="<?= (int)$r['reaberturas'] ?>"><?= num($r['reaberturas']) ?></td>
        <td class="num" data-v="<?= $r['mediana_1a'] === null ? 999999 : (float)$r['mediana_1a'] ?>"><?= duracao($r['mediana_1a']) ?></td>
        <td class="num" data-v="<?= (int)$r['respostas'] ?>"><?= num($r['respostas']) ?></td>
        <td class="num" data-v="<?= $r['media'] === null ? -1 : (float)$r['media'] ?>">
          <?php if ($r['media'] !== null): ?><span class="csat <?= (float)$r['media'] >= 4.5 ? 'alto' : ((float)$r['media'] < 4 ? 'baixo' : '') ?>"><?= num($r['media'], 2) ?></span><?php else: ?>—<?php endif; ?>
        </td>
        <td class="num" data-v="<?= (int)$r['baixas'] ?>"><?php if ((int)$r['baixas']): ?><a href="<?= h(url(array('p' => 'fila', 'atendente' => $r['id'], 'semana' => 'todas'))) ?>"><b style="color:var(--ruim)"><?= num($r['baixas']) ?></b></a><?php else: ?><span style="color:#aaa">0</span><?php endif; ?></td>
        <td data-v="<?= h(rotulo('CATEGORIAS', $r['motivo'])) ?>"><?= $r['motivo'] ? '<span class="tag">' . h(rotulo('CATEGORIAS', $r['motivo'])) . '</span>' : '<span style="color:#aaa">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><div class="vazio">Sem dados no período.</div><?php endif; ?>
</div>
<script>
(function(){
  var t = document.getElementById('tRank'); if (!t) return;
  var ths = t.querySelectorAll('th'), dir = {};
  ths.forEach(function(th, i){
    th.addEventListener('click', function(){
      var tb = t.tBodies[0], rows = Array.prototype.slice.call(tb.rows), num = th.dataset.t === 'n';
      dir[i] = !dir[i];
      rows.sort(function(a, b){
        var x = a.cells[i].dataset.v, y = b.cells[i].dataset.v;
        if (num) { x = parseFloat(x); y = parseFloat(y); return dir[i] ? y - x : x - y; }
        return dir[i] ? String(x).localeCompare(y, 'pt-BR') : String(y).localeCompare(x, 'pt-BR');
      });
      rows.forEach(function(r){ tb.appendChild(r); });
    });
  });
})();
</script>

<?php else: ?>
<!-- ============================================================ FILA DE AUDITORIA -->
<div class="barra-acoes">
  <div class="esq">
    <select onchange="location.href=this.value" class="btn" style="font-weight:500">
      <option value="<?= h(url(array('semana' => 'todas'))) ?>" <?= $semSel === '' ? 'selected' : '' ?>>Todas as semanas</option>
      <?php foreach ($semanas as $s): ?>
        <option value="<?= h(url(array('semana' => $s['s']))) ?>" <?= $semSel === $s['s'] ? 'selected' : '' ?>>Semana de <?= date('d/m/Y', strtotime($s['s'])) ?> (<?= (int)$s['n'] ?><?= $s['pend'] ? ', ' . (int)$s['pend'] . ' pendentes' : '' ?>)</option>
      <?php endforeach; ?>
    </select>
    <div class="status-chips">
      <a class="chip <?= $stSel === '' ? 'on' : '' ?>" href="<?= h(url(array('status' => null))) ?>">Todos<b><?= array_sum($contagem) ?></b></a>
      <?php foreach (array('pendente' => 'Pendentes', 'avaliado' => 'Avaliados', 'enviado' => 'Enviados', 'tratado' => 'Tratados') as $k => $v): ?>
        <a class="chip <?= $stSel === $k ? 'on' : '' ?>" href="<?= h(url(array('status' => $k))) ?>"><?= $v ?><b><?= $contagem[$k] ?></b></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="dir">
    <a class="btn" href="<?= h(url(array('csv' => 1, 'status' => 'avaliado'))) ?>">⤓ CSV para o pós-vendas</a>
    <?php if ($semSel !== '' && $contagem['avaliado']): ?>
    <form method="post" onsubmit="return confirm('Marcar os <?= (int)$contagem['avaliado'] ?> itens avaliados desta semana como enviados ao pós-vendas?')">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="enviar_semana"><input type="hidden" name="semana" value="<?= h($semSel) ?>">
      <button class="btn pri" type="submit">✓ Marcar semana como enviada</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!$itens): ?><div class="cartao"><div class="vazio">Nenhum item com esses filtros. 🎉</div></div><?php endif; ?>
<?php foreach ($itens as $it): ?>
<article class="item" id="item-<?= (int)$it['id'] ?>">
  <div class="item-top">
    <div class="nota n<?= (int)$it['nota'] ?>"><?= (int)$it['nota'] ?></div>
    <div>
      <div class="quem"><?= h($it['cliente'] ? $it['cliente'] : 'Cliente') ?></div>
      <div class="det">Atendente: <b><?= h($it['atendente'] ? $it['atendente'] : '—') ?></b> · respondido em <?= date('d/m/Y H:i', strtotime($it['respondido_em'])) ?> · semana de <?= date('d/m', strtotime($it['semana_ref'])) ?></div>
    </div>
    <div class="dir">
      <span class="st <?= h($it['status']) ?>"><?= h($it['status']) ?></span>
      <a class="btn" href="<?= h($it['link_conversa']) ?>" target="_blank" rel="noopener">Abrir conversa ↗</a>
    </div>
  </div>
  <div class="item-corpo">
    <div class="ia">
      <div class="com"><?= $it['feedback_cliente'] ? '“' . h($it['feedback_cliente']) . '”' : 'Cliente não deixou comentário.' ?></div>
      <?php if ($it['ia_motivo']): ?>
        <div class="rot-ia">✦ Análise da IA <?php if ($it['ia_confianca'] !== null): ?><span style="color:#999;font-weight:500;text-transform:none;letter-spacing:0">· confiança <?= round(100 * (float)$it['ia_confianca']) ?>%</span><?php endif; ?></div>
        <div class="mot"><?= h($it['ia_motivo']) ?></div>
        <div class="meta" style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="tag"><?= h(rotulo('CATEGORIAS', $it['ia_categoria'])) ?></span>
          <span class="tag cinza">Responsável: <?= h(rotulo('RESPONSAVEIS', $it['ia_responsavel'])) ?></span>
          <?php if ($it['ia_gravidade']): ?><span class="tag <?= h($it['ia_gravidade']) ?>">gravidade <?= h($it['ia_gravidade']) ?></span><?php endif; ?>
        </div>
        <?php if ($it['ia_resumo']): ?><p><?= h($it['ia_resumo']) ?></p><?php endif; ?>
        <?php if ($it['ia_evidencias']): ?><ul><?php foreach (explode("\n", $it['ia_evidencias']) as $ev): if (trim($ev) === '') continue; ?><li>“<?= h(trim($ev, " \"“”")) ?>”</li><?php endforeach; ?></ul><?php endif; ?>
        <?php if ($it['ia_sugestao']): ?><div class="sug"><b>Sugestão:</b> <?= h($it['ia_sugestao']) ?></div><?php endif; ?>
      <?php else: ?>
        <div class="rot-ia">✦ Análise da IA</div><p style="color:#888">Ainda não analisada — a IA roda a cada 15 minutos.</p>
      <?php endif; ?>
    </div>
    <form class="aval" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="acao" value="avaliar"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
      <label>Classificação do supervisor</label>
      <?php $cl = $it['classificacao'] ? $it['classificacao'] : $it['ia_categoria']; ?>
      <select name="classificacao">
        <option value="">— selecione —</option>
        <?php foreach ($GLOBALS['CATEGORIAS'] as $k => $v): ?><option value="<?= h($k) ?>" <?= $cl === $k ? 'selected' : '' ?>><?= h($v) ?><?= (!$it['classificacao'] && $it['ia_categoria'] === $k) ? ' (sugerido pela IA)' : '' ?></option><?php endforeach; ?>
      </select>
      <label>Observação para o pós-vendas</label>
      <textarea name="observacao" placeholder="O que aconteceu, o que já foi feito e o que o pós-vendas deve tratar…"><?= h($it['observacao']) ?></textarea>
      <button class="btn pri" type="submit"><?= $it['status'] === 'pendente' ? '✓ Salvar avaliação' : 'Atualizar avaliação' ?></button>
      <?php if ($it['avaliado_em']): ?><div class="quem-aval">Avaliado por <b><?= h($it['supervisor']) ?></b> em <?= date('d/m/Y H:i', strtotime($it['avaliado_em'])) ?><?= $it['enviado_em'] ? ' · enviado em ' . date('d/m/Y', strtotime($it['enviado_em'])) : '' ?></div><?php endif; ?>
    </form>
  </div>
</article>
<?php endforeach; ?>
<?php endif; ?>

<div class="rodape">Dados do iTalk sincronizados a cada 15 min · análise de IA via OpenRouter · Auditoria do Suporte</div>
</main>
<script>
// datas personalizadas: ao mudar início/fim, aplica período "custom"
(function(){
  var f = document.getElementById('filtros'); if (!f) return;
  f.querySelectorAll('input[type=date]').forEach(function(el){
    el.addEventListener('change', function(){
      f.querySelectorAll('input[name=periodo]').forEach(function(p){ p.disabled = true; });
      var c = document.getElementById('perCustom'); if (c) c.disabled = false;
      f.submit();
    });
  });
})();
</script>
</body>
</html>
