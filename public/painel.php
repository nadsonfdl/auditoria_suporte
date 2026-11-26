<?php
/**
 * Painel de BI — Auditoria do Suporte (iTalk, conta 3)
 * URL: https://appiasuporte.igut.med.br/auditoria_suporte/painel.php
 * Usuários em config/config.php -> 'painel_usuarios' (gere com: php bin/criar_usuario.php nome senha)
 * Compatível com PHP 5.6+
 */
ini_set('display_errors', '0'); // erros vão para o log do Apache, nunca para a tela
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/painel.php';
require __DIR__ . '/../src/notificar.php';
require __DIR__ . '/../src/automacoes.php';
require __DIR__ . '/../src/ia.php';
require __DIR__ . '/../src/qa.php';
date_default_timezone_set('America/Sao_Paulo');

// ---------------------------------------------------------------- imagens da marca (públicas, lista fechada)
// painel.php?img=logo-igut-branca.png — usadas no login, na barra lateral e nos e-mails
if (isset($_GET['img'])) {
    $IMAGENS = array('logo-igut-branca.png', 'logo-igut.png', 'logo-gc.png', 'logo-eba.png', 'simbolo-igut.png', 'favicon.png', 'apple-touch-icon.png');
    $arq = (string)$_GET['img'];
    if (!in_array($arq, $IMAGENS, true)) {
        http_response_code(404);
        exit;
    }
    $caminho = __DIR__ . '/../src/assets/' . $arq;
    $etag = '"' . md5_file($caminho) . '"';
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Length: ' . filesize($caminho));
    readfile($caminho);
    exit;
}

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
$ADMINS  = cfg('painel_admins');
$ADMIN   = !is_array($ADMINS) || !$ADMINS || in_array($USUARIO, $ADMINS, true);   // sem lista de admins, todos administram
$PAGINAS = array('visao', 'qualidade', 'conversas', 'atendentes', 'fila', 'config');
$pagina  = isset($_GET['p']) && in_array($_GET['p'], $PAGINAS, true) ? $_GET['p'] : 'visao';
if ($pagina === 'config' && !$ADMIN) {
    $pagina = 'visao';
}

function aviso($texto, $erro = false)
{
    $_SESSION['flash'] = array($texto, $erro ? 'erro' : 'ok');
}

function voltar($ancora = '')
{
    header('Location: ' . url() . $ancora);
    exit;
}

// Chaves editáveis na aba Configurações: tipo t=texto, b=liga/desliga, s=segredo (só grava se preenchido), n=número
$CHAVES_CONFIG = array(
    'painel_url' => 't', 'csat_nota_corte' => 'n',
    'guta_ativo' => 'b',
    'metas_ativo' => 'b', 'meta_csat' => 'n', 'meta_satisfeitos_pct' => 'n', 'meta_1a_resposta_min' => 'n', 'meta_sem_reabrir_pct' => 'n', 'meta_notas_baixas_pct' => 'n',
    'meta_resolucao_real_pct' => 'n', 'meta_qa' => 'n',
    'alerta_ativo' => 'b', 'alerta_nota_max' => 'n', 'alerta_email' => 'b', 'alerta_whatsapp' => 'b', 'alerta_emails' => 't', 'alerta_whatsapps' => 't',
    'resumo_ativo' => 'b', 'resumo_dia' => 'n', 'resumo_hora' => 'n', 'resumo_emails' => 't', 'resumo_whatsapp' => 'b', 'resumo_whatsapps' => 't',
    'posvendas_emails' => 't', 'posvendas_whatsapp' => 'b', 'posvendas_whatsapps' => 't',
    'temas_ativo' => 'b', 'temas_janela_dias' => 'n', 'github_repo' => 't', 'github_token' => 's',
    'qa_ativo' => 'b', 'qa_lote' => 'n', 'qa_amostra_pct' => 'n', 'qa_dias' => 'n', 'qa_para_fila' => 'b',
    'smtp_host' => 't', 'smtp_porta' => 'n', 'smtp_seguranca' => 't', 'smtp_usuario' => 't', 'smtp_senha' => 's', 'smtp_de_email' => 't', 'smtp_de_nome' => 't',
    'wa_provedor' => 't', 'wa_italk_inbox_id' => 't', 'wa_evolution_url' => 't', 'wa_evolution_instancia' => 't', 'wa_evolution_apikey' => 's',
    'wa_zapi_instancia' => 't', 'wa_zapi_token' => 's', 'wa_zapi_client_token' => 's', 'wa_webhook_url' => 't',
);

// ---------------------------------------------------------------- ações (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checaCsrf();
    $acao = isset($_POST['acao']) ? (string)$_POST['acao'] : '';
    $id   = isset($_POST['id']) && ctype_digit((string)$_POST['id']) ? (int)$_POST['id'] : 0;
    $txt  = function ($k) { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; };

    // ---- fila: avaliação do supervisor
    if ($acao === 'avaliar' && $id) {
        $class = $txt('classificacao');
        $obs   = $txt('observacao');
        db()->prepare("UPDATE auditoria.csat_auditoria
                       SET classificacao = ?, observacao = ?, supervisor = ?, avaliado_em = now(),
                           status = CASE WHEN status = 'pendente' THEN 'avaliado' ELSE status END
                       WHERE id = ?")
            ->execute(array($class !== '' ? $class : null, $obs !== '' ? $obs : null, $USUARIO, $id));
        aviso('Avaliação salva.');
        voltar('#item-' . $id);
    }
    // ---- fila: envio da semana ao pós-vendas (e-mail com planilha, se configurado)
    if ($acao === 'enviar_semana' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt('semana'))) {
        if (listaDestinos(conf('posvendas_emails')) && smtpConfigurado()) {
            list($ok, $erro, $n) = enviarSemanaPosVendas($txt('semana'), $USUARIO);
            $ok ? aviso($n . ($n == 1 ? ' nota enviada' : ' notas enviadas') . ' ao pós-vendas por e-mail.') : aviso('O e-mail não saiu: ' . $erro, true);
        } else {
            $st = db()->prepare("UPDATE auditoria.csat_auditoria SET status = 'enviado', enviado_em = now() WHERE semana_ref = ?::date AND status = 'avaliado'");
            $st->execute(array($txt('semana')));
            aviso($st->rowCount() . ' nota(s) marcada(s) como enviada(s). O e-mail ao pós-vendas ainda não está configurado: baixe a planilha e envie manualmente.');
        }
        voltar();
    }
    // ---- fila: retorno do pós-vendas
    if ($acao === 'retorno' && $id) {
        $tratado = isset($_POST['tratado']);
        db()->prepare("UPDATE auditoria.csat_auditoria SET retorno_posvendas = ?,
                         status = CASE WHEN ? THEN 'tratado' ELSE status END,
                         tratado_em = CASE WHEN ? THEN COALESCE(tratado_em, now()) ELSE tratado_em END,
                         tratado_por = CASE WHEN ? THEN ? ELSE tratado_por END
                       WHERE id = ? AND status IN ('enviado', 'tratado')")
            ->execute(array($txt('retorno') !== '' ? $txt('retorno') : null, $tratado ? 'true' : 'false', $tratado ? 'true' : 'false', $tratado ? 'true' : 'false', $USUARIO, $id));
        aviso($tratado ? 'Caso marcado como tratado.' : 'Retorno salvo.');
        voltar('#item-' . $id);
    }
    // ---- conversas: validação humana do veredito da IA (corrige o indicador e mede a precisão da IA)
    if ($acao === 'validar' && $id && in_array($txt('humano'), array('sim', 'parcial', 'nao'), true)) {
        db()->prepare("INSERT INTO auditoria.qa_validacao (conversation_id, usuario, ia_resolvido, humano_resolvido, comentario)
                       SELECT ?, ?, resolvido, ?, ? FROM auditoria.qa_conversas WHERE conversation_id = ?
                       ON CONFLICT (conversation_id) DO UPDATE SET usuario = EXCLUDED.usuario, humano_resolvido = EXCLUDED.humano_resolvido,
                         comentario = EXCLUDED.comentario, criado_em = now()")
            ->execute(array($id, $USUARIO, $txt('humano'), $txt('comentario') !== '' ? $txt('comentario') : null, $id));
        aviso('Validação registrada. O indicador já considera a sua avaliação.');
        voltar('#conv-' . $id);
    }
    if ($acao === 'desfazer_validacao' && $id) {
        db()->prepare("DELETE FROM auditoria.qa_validacao WHERE conversation_id = ?")->execute(array($id));
        aviso('Validação removida. Vale de novo o veredito da IA.');
        voltar('#conv-' . $id);
    }
    // ---- qualidade: levar uma conversa auditada pela IA para a fila do supervisor
    if ($acao === 'levar_fila' && $id) {
        db()->prepare("
          INSERT INTO auditoria.csat_auditoria (origem, conversation_id, atendente_id, contato_id, respondido_em, semana_ref,
                                                ia_motivo, ia_categoria, ia_responsavel, ia_gravidade, ia_resumo, ia_analisado_em)
          SELECT 'ia', q.conversation_id, c.assignee_id, c.contact_id, c.last_activity_at,
                 date_trunc('week', c.last_activity_at AT TIME ZONE 'America/Sao_Paulo')::date,
                 COALESCE(q.falhas->0->>'descricao', q.resumo), q.categoria_falha, q.json->>'responsavel',
                 CASE q.risco WHEN 'alto' THEN 'alta' WHEN 'medio' THEN 'media' ELSE 'baixa' END, q.resumo, q.analisado_em
          FROM auditoria.qa_conversas q JOIN espelho.conversations c ON c.id = q.conversation_id
          WHERE q.conversation_id = ? AND NOT EXISTS (SELECT 1 FROM auditoria.csat_auditoria a WHERE a.conversation_id = q.conversation_id)")
            ->execute(array($id));
        aviso('Conversa enviada para a auditoria do supervisor.');
        voltar('#conv-' . $id);
    }
    // ---- temas recorrentes
    if ($acao === 'abrir_issue' && $id && $ADMIN) {
        list($ok, $r) = abrirIssueTema($id);
        $ok ? aviso('Issue aberta no GitHub.') : aviso($r, true);
        voltar('#temas');
    }
    if ($acao === 'tema_status' && $id && $ADMIN && in_array($txt('status'), array('resolvido', 'ignorado', 'aberto'), true)) {
        db()->prepare("UPDATE auditoria.temas SET status = ? WHERE id = ?")->execute(array($txt('status'), $id));
        aviso('Tema atualizado.');
        voltar('#temas');
    }
    // ---- configurações (somente administradores)
    if ($ADMIN && $acao === 'salvar_config') {
        foreach ($CHAVES_CONFIG as $k => $tipo) {
            if (!array_key_exists($k, $_POST) && $tipo !== 'b') {
                continue;                                   // campo de outra seção do formulário
            }
            if ($tipo === 'b') {
                if (isset($_POST['_secao']) && isset($_POST['_b'][$k])) {
                    $novo = isset($_POST[$k]) ? '1' : '0';
                    if ($k === 'alerta_ativo' && $novo === '1' && conf('alerta_ativo') !== '1') {
                        confSalvar('alerta_desde', date('c'));  // só alerta notas a partir de agora
                    }
                    confSalvar($k, $novo);
                }
            } elseif ($tipo === 's') {
                if ($txt($k) !== '') {
                    confSalvar($k, $txt($k));
                }
                if (isset($_POST['_limpar'][$k])) {
                    confSalvar($k, '');
                }
            } elseif ($tipo === 'n') {
                confSalvar($k, is_numeric(str_replace(',', '.', $txt($k))) ? str_replace(',', '.', $txt($k)) : '');
            } else {
                confSalvar($k, $txt($k));
            }
        }
        conf(null);
        aviso('Configurações salvas.');
        voltar(isset($_POST['_secao']) ? '#' . preg_replace('/[^a-z_]/', '', $_POST['_secao']) : '');
    }
    if ($ADMIN && $acao === 'testar_email') {
        $dest = $txt('destino') !== '' ? $txt('destino') : conf('smtp_de_email');
        list($ok, $erro) = enviarEmail($dest, 'Teste da Auditoria do Suporte', emailLayout('E-mail de teste',
            '<p style="font-size:15px;line-height:1.5">Se você está lendo isto, o envio de e-mails da Auditoria do Suporte está funcionando.</p>'), array(), 'teste');
        $ok ? aviso('E-mail de teste enviado para ' . $dest . '.') : aviso('Falhou: ' . $erro, true);
        voltar('#email');
    }
    if ($ADMIN && $acao === 'testar_whatsapp') {
        list($ok, $erro) = enviarWhatsApp($txt('destino'), "✅ Teste da Auditoria do Suporte. O envio por WhatsApp está funcionando.", 'teste');
        $ok ? aviso('Mensagem de teste enviada.') : aviso('Falhou: ' . $erro, true);
        voltar('#whatsapp');
    }
    if ($ADMIN && $acao === 'testar_resumo') {
        $dest = $txt('destino') !== '' ? $txt('destino') : conf('resumo_emails');
        $r = processarResumoSemanal(true, $dest);
        $r[0] ? aviso('Resumo da semana passada enviado para ' . $dest . '.') : aviso('Falhou: ' . $r[1], true);
        voltar('#resumo');
    }
    if ($ADMIN && $acao === 'rodar_temas') {
        try {
            $n = processarTemas(true);
            aviso($n . ' caso(s) agrupado(s) em temas.');
        } catch (Exception $e) {
            aviso('Falhou: ' . $e->getMessage(), true);
        }
        voltar('#temas');
    }
    if ($ADMIN && $acao === 'rodar_qa') {
        try {
            $n = processarQualidade(5);
            aviso($n . ' conversa(s) auditada(s) agora. O restante segue pelo agendador.');
        } catch (Exception $e) {
            aviso('Falhou: ' . $e->getMessage(), true);
        }
        voltar('#qualidade');
    }
    voltar();
}

// ---------------------------------------------------------------- exportação CSV (pós-vendas)
if ($pagina === 'fila' && isset($_GET['csv'])) {
    $sem = isset($_GET['semana']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['semana']) ? (string)$_GET['semana'] : '';
    $stCsv = isset($_GET['status']) && in_array($_GET['status'], array('pendente', 'avaliado', 'enviado', 'tratado'), true) ? $_GET['status'] : '';
    $rows = itensFila($sem, $stCsv, '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria_csat_' . ($sem ? $sem : 'todas') . '.csv"');
    echo csvFila($rows);
    exit;
}

$flash = isset($_SESSION['flash']) ? (is_array($_SESSION['flash']) ? $_SESSION['flash'] : array($_SESSION['flash'], 'ok')) : null;
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
    $kq  = kpisQa($F, $F['ini'], $F['fim']);
    $taxaReal = taxa($kq['resolvidas'], $kq['conclusivas']);
    $GUTA = confLigado('guta_ativo') && $F['atendente'] === '';
    if ($GUTA) {
        $kg  = kpisGuta($F, $F['ini'], $F['fim']);
        $kga = kpisGuta($F, $F['ant_ini'], $F['ant_fim']);
        $sg  = serieGuta($F);
    }
    $TEMAS = confLigado('temas_ativo') ? temasAbertos(6) : array();

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
    $qaAt = qaPorAtendente($F);
    $maxConv = 1;
    foreach ($rank as $r) {
        $maxConv = max($maxConv, (int)$r['conversas']);
    }
}
if ($pagina === 'qualidade') {
    $kq   = kpisQa($F, $F['ini'], $F['fim']);
    $kqa  = kpisQa($F, $F['ant_ini'], $F['ant_fim']);
    $enc  = encerradasPeriodo($F, $F['ini'], $F['fim']);
    $sq   = serieQa($F);
    $crit = criteriosQa($F);
    $falh = falhasQa($F);
    $motc = motivosContatoQa($F);
    $risc = conversasEmRisco($F, 12);
    $taxaReal  = taxa($kq['resolvidas'], $kq['conclusivas']);
    $taxaRealA = taxa($kqa['resolvidas'], $kqa['conclusivas']);
}
if ($pagina === 'conversas') {
    $X   = filtrosConversas();
    $PG  = isset($_GET['pg']) && ctype_digit((string)$_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
    list($TOTAL_CONV, $CONVS) = listaConversas($F, $X, $PG, 30);
    $DEC = decomposicaoQa($F, $X);
    $PREC = precisaoIa();
    $kq = kpisQa($F, $F['ini'], $F['fim']);
}
if ($pagina === 'config') {
    $ENVIOS = q("SELECT * FROM auditoria.envios ORDER BY id DESC LIMIT 25");
    $TEMAS_TODOS = q("SELECT * FROM auditoria.temas ORDER BY status = 'aberto' DESC, qtd DESC LIMIT 30");
    $QA_STATUS = q1("SELECT count(*) FILTER (WHERE resolvido IS NOT NULL) AS ok, count(*) FILTER (WHERE erro IS NOT NULL) AS erros, max(analisado_em) AS ultima FROM auditoria.qa_conversas");
    $AG = array();
    foreach (q("SELECT chave, valor, atualizado_em FROM auditoria.agendador") as $x) {
        $AG[$x['chave']] = $x;
    }
}
if ($pagina === 'fila') {
    $KPV = kpisPosVendas();
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
        $FRASE_CAT = array(
            'problema_nao_resolvido' => 'um problema que não foi resolvido', 'demora_resposta' => 'demora na resposta',
            'erro_ou_bug_sistema' => 'erro ou falha do sistema', 'comunicacao_atendente' => 'falha de comunicação do atendente',
            'informacao_incorreta' => 'informação incorreta', 'transferencias_excessivas' => 'transferências demais',
            'insatisfacao_regra_ou_preco' => 'insatisfação com regra ou preço', 'atendimento_ia_guta' => 'problema no atendimento da Guta',
            'expectativa_do_cliente' => 'expectativa do cliente não atendida', 'outro' => 'outros motivos',
        );
        $DIAG_2 = 'Mas ' . ((int)$top['n'] === $totIA ? 'todas as' : num($top['n']) . ' de') . ' ' . num($totIA)
                . ' notas baixas analisadas pela IA apontam ' . (isset($FRASE_CAT[$top['k']]) ? $FRASE_CAT[$top['k']] : lcfirst(rotulo('CATEGORIAS', $top['k']))) . '.';
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
    if ((int)$kq['conclusivas'] > 0) {
        $DIAG_NOTAS[] = 'A IA leu ' . num($kq['auditadas']) . ' conversas, com e sem nota, e confirmou que ' . pct($taxaReal) . ' foram resolvidas de fato.';
    }
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
