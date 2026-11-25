<?php
// Configurações editáveis (auditoria.config), envio de e-mail (SMTP) e WhatsApp, e log de envios.
// Compatível com PHP 5.6+

require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';

// ---------------------------------------------------------------- configurações

/** Lê uma configuração do banco (com cache por requisição) */
function conf($chave, $padrao = '')
{
    static $cache = null;
    if ($chave === null) {      // conf(null) limpa o cache depois de salvar
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = array();
        foreach (db()->query("SELECT chave, valor FROM auditoria.config") as $r) {
            $cache[$r['chave']] = $r['valor'];
        }
    }
    return isset($cache[$chave]) && $cache[$chave] !== null ? $cache[$chave] : $padrao;
}

function confLigado($chave)
{
    return conf($chave, '0') === '1';
}

function confSalvar($chave, $valor)
{
    db()->prepare("INSERT INTO auditoria.config (chave, valor) VALUES (?, ?)
                   ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor")
        ->execute(array($chave, (string)$valor));
}

/** Lista de destinos a partir de texto separado por vírgula, ponto e vírgula ou quebra de linha */
function listaDestinos($texto)
{
    $itens = preg_split('/[\s,;]+/', (string)$texto);
    return array_values(array_unique(array_filter(array_map('trim', $itens), 'strlen')));
}

function registrarEnvio($tipo, $canal, $destino, $assunto, $ok, $erro = null)
{
    try {
        db()->prepare("INSERT INTO auditoria.envios (tipo, canal, destino, assunto, ok, erro) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute(array($tipo, $canal, $destino, mb_cortar($assunto, 250), $ok ? 'true' : 'false', $erro ? mb_cortar($erro, 500) : null));
    } catch (Exception $e) {
        error_log('auditoria: falha ao registrar envio: ' . $e->getMessage());
    }
}

function mb_cortar($s, $n)
{
    $s = (string)$s;
    return strlen($s) > $n ? substr($s, 0, $n) : $s;
}

// ---------------------------------------------------------------- e-mail

function smtpConfigurado()
{
    return conf('smtp_host') !== '' && conf('smtp_de_email') !== '';
}

/**
 * Envia e-mail HTML. $anexos = array(array('nome' => 'x.csv', 'conteudo' => '...', 'tipo' => 'text/csv'))
 * Retorna array(ok, erro)
 */
function enviarEmail($para, $assunto, $html, $anexos = array(), $tipo = 'teste')
{
    $para = is_array($para) ? $para : listaDestinos($para);
    if (!$para) {
        return array(false, 'Nenhum destinatário de e-mail configurado.');
    }
    if (!smtpConfigurado()) {
        foreach ($para as $d) {
            registrarEnvio($tipo, 'email', $d, $assunto, false, 'SMTP não configurado');
        }
        return array(false, 'SMTP ainda não configurado. Preencha em Configurações > E-mail.');
    }
    $m = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = conf('smtp_host');
        $m->Port       = (int)conf('smtp_porta', '587');
        $seg = conf('smtp_seguranca', 'tls');
        if ($seg === 'ssl') {
            $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($seg === 'tls') {
            $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $m->SMTPSecure = '';
            $m->SMTPAutoTLS = false;
        }
        $m->SMTPAuth   = conf('smtp_usuario') !== '';
        $m->Username   = conf('smtp_usuario');
        $m->Password   = conf('smtp_senha');
        $m->Timeout    = 20;
        $m->CharSet    = 'UTF-8';
        $m->setFrom(conf('smtp_de_email'), conf('smtp_de_nome', 'Auditoria do Suporte'));
        foreach ($para as $d) {
            $m->addAddress($d);
        }
        $m->isHTML(true);
        $m->Subject = $assunto;
        $m->Body    = $html;
        $m->AltBody = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        foreach ($anexos as $a) {
            $m->addStringAttachment($a['conteudo'], $a['nome'], 'base64', isset($a['tipo']) ? $a['tipo'] : 'application/octet-stream');
        }
        $m->send();
        foreach ($para as $d) {
            registrarEnvio($tipo, 'email', $d, $assunto, true);
        }
        return array(true, null);
    } catch (Exception $e) {
        $erro = $m->ErrorInfo ? $m->ErrorInfo : $e->getMessage();
        foreach ($para as $d) {
            registrarEnvio($tipo, 'email', $d, $assunto, false, $erro);
        }
        return array(false, $erro);
    }
}

// ---------------------------------------------------------------- WhatsApp

function waConfigurado()
{
    $p = conf('wa_provedor', 'italk');
    if ($p === 'italk') {
        return conf('wa_italk_inbox_id') !== '';
    }
    if ($p === 'evolution') {
        return conf('wa_evolution_url') !== '' && conf('wa_evolution_instancia') !== '' && conf('wa_evolution_apikey') !== '';
    }
    if ($p === 'zapi') {
        return conf('wa_zapi_instancia') !== '' && conf('wa_zapi_token') !== '';
    }
    return conf('wa_webhook_url') !== '';
}

/** Deixa só dígitos e garante DDI 55 em números brasileiros */
function normalizarFone($f)
{
    $d = preg_replace('/\D+/', '', (string)$f);
    if (strlen($d) === 10 || strlen($d) === 11) {
        $d = '55' . $d;
    }
    return $d;
}

function httpJson($metodo, $url, $corpo, $cabecalhos = array())
{
    $ch = curl_init($url);
    $h = array_merge(array('Content-Type: application/json', 'Accept: application/json'), $cabecalhos);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $h,
    ));
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, j($corpo));
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return array($code, json_decode((string)$resp, true), $resp, $err);
}

/** Envio pelo próprio iTalk: acha/cria o contato e abre uma conversa na caixa de WhatsApp escolhida */
function waItalk($fone, $texto)
{
    $inbox = (int)conf('wa_italk_inbox_id');
    $base  = rtrim(cfg('italk_url'), '/') . '/api/v1/accounts/' . (int)cfg('account_id');
    $tok   = array('api_access_token: ' . cfg('api_token'));
    $e164  = '+' . $fone;

    list($c, $r) = httpJson('GET', $base . '/contacts/search?q=' . urlencode($e164), null, $tok);
    $contato = null;
    foreach ((array)g($r, 'payload', array()) as $x) {
        if (normalizarFone(g($x, 'phone_number')) === $fone) {
            $contato = $x;
            break;
        }
    }
    if (!$contato) {
        list($c, $r, $bruto) = httpJson('POST', $base . '/contacts', array('inbox_id' => $inbox, 'name' => 'Auditoria ' . $e164, 'phone_number' => $e164), $tok);
        if ($c >= 300) {
            return array(false, "iTalk: não criou o contato (HTTP $c) " . mb_cortar($bruto, 200));
        }
        $contato = g($r, 'payload.contact', g($r, 'payload'));
    }
    $sourceId = null;
    foreach ((array)g($contato, 'contact_inboxes', array()) as $ci) {
        if ((int)g($ci, 'inbox.id') === $inbox) {
            $sourceId = g($ci, 'source_id');
        }
    }
    $corpo = array('inbox_id' => $inbox, 'contact_id' => (int)g($contato, 'id'), 'message' => array('content' => $texto));
    if ($sourceId) {
        $corpo['source_id'] = $sourceId;
    }
    list($c, $r, $bruto) = httpJson('POST', $base . '/conversations', $corpo, $tok);
    if ($c >= 300) {
        return array(false, "iTalk: não enviou (HTTP $c) " . mb_cortar($bruto, 200));
    }
    return array(true, null);
}

function enviarWhatsApp($destinos, $texto, $tipo = 'teste')
{
    $destinos = is_array($destinos) ? $destinos : listaDestinos($destinos);
    if (!$destinos) {
        return array(false, 'Nenhum número de WhatsApp configurado.');
    }
    $assunto = mb_cortar(strtok($texto, "\n"), 120);
    if (!waConfigurado()) {
        foreach ($destinos as $d) {
            registrarEnvio($tipo, 'whatsapp', $d, $assunto, false, 'WhatsApp não configurado');
        }
        return array(false, 'WhatsApp ainda não configurado. Preencha em Configurações > WhatsApp.');
    }
    $prov = conf('wa_provedor', 'italk');
    $falhas = array();
    foreach ($destinos as $d) {
        $fone = normalizarFone($d);
        try {
            if ($prov === 'italk') {
                list($ok, $erro) = waItalk($fone, $texto);
            } elseif ($prov === 'evolution') {
                list($c, $r, $b) = httpJson('POST', rtrim(conf('wa_evolution_url'), '/') . '/message/sendText/' . rawurlencode(conf('wa_evolution_instancia')),
                    array('number' => $fone, 'text' => $texto), array('apikey: ' . conf('wa_evolution_apikey')));
                $ok = $c >= 200 && $c < 300;
                $erro = $ok ? null : "Evolution HTTP $c " . mb_cortar($b, 200);
            } elseif ($prov === 'zapi') {
                $h = conf('wa_zapi_client_token') !== '' ? array('Client-Token: ' . conf('wa_zapi_client_token')) : array();
                list($c, $r, $b) = httpJson('POST', 'https://api.z-api.io/instances/' . rawurlencode(conf('wa_zapi_instancia')) . '/token/' . rawurlencode(conf('wa_zapi_token')) . '/send-text',
                    array('phone' => $fone, 'message' => $texto), $h);
                $ok = $c >= 200 && $c < 300;
                $erro = $ok ? null : "Z-API HTTP $c " . mb_cortar($b, 200);
            } else {
                list($c, $r, $b) = httpJson('POST', conf('wa_webhook_url'), array('para' => $fone, 'texto' => $texto, 'tipo' => $tipo));
                $ok = $c >= 200 && $c < 300;
                $erro = $ok ? null : "Webhook HTTP $c " . mb_cortar($b, 200);
            }
        } catch (Exception $e) {
            $ok = false;
            $erro = $e->getMessage();
        }
        registrarEnvio($tipo, 'whatsapp', $fone, $assunto, $ok, $erro);
        if (!$ok) {
            $falhas[] = $fone . ': ' . $erro;
        }
    }
    return $falhas ? array(false, implode(' | ', $falhas)) : array(true, null);
}

// ---------------------------------------------------------------- modelo visual dos e-mails

/** Envolve o conteúdo no layout padrão de e-mail (tabelas + estilos inline, compatível com Outlook/Gmail) */
function emailLayout($titulo, $conteudoHtml, $rodape = '')
{
    $url = h(conf('painel_url'));
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6fa;font-family:Arial,Helvetica,sans-serif;color:#0f1d33">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fa;padding:24px 12px"><tr><td align="center">'
        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border:1px solid #e2e7ef;border-radius:12px">'
        . '<tr><td style="background:#0f1d33;border-radius:12px 12px 0 0;padding:18px 24px;color:#ffffff;font-size:15px;font-weight:bold">Auditoria do Suporte'
        . '<span style="color:#8b9bb5;font-weight:normal"> &nbsp;iTalk, conta Suporte</span></td></tr>'
        . '<tr><td style="padding:26px 24px 8px"><h1 style="font-family:Georgia,serif;font-weight:normal;font-size:22px;line-height:1.3;margin:0 0 14px">' . h($titulo) . '</h1>'
        . $conteudoHtml . '</td></tr>'
        . '<tr><td style="padding:8px 24px 26px"><a href="' . $url . '" style="display:inline-block;background:#1b64be;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:11px 18px;border-radius:8px">Abrir o painel</a></td></tr>'
        . '<tr><td style="border-top:1px solid #e2e7ef;padding:14px 24px;color:#78859a;font-size:12px">'
        . ($rodape !== '' ? $rodape : 'Mensagem automática da Auditoria do Suporte. Para mudar quem recebe, acesse Configurações no painel.')
        . '</td></tr></table></td></tr></table></body></html>';
}
