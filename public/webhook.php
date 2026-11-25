<?php
/**
 * Receptor de webhook do iTalk (conta 3).
 * Cadastrar no iTalk: Configurações -> Integrações -> Webhooks
 *   URL: https://SEU-SERVIDOR/auditoria_suporte/public/webhook.php?token=SEU_WEBHOOK_TOKEN
 *   Eventos: conversation_created, conversation_status_changed, conversation_updated
 * Compatível com PHP 5.6+
 */
require __DIR__ . '/../src/lib.php';
header('Content-Type: application/json');

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if (!hash_equals((string)cfg('webhook_token'), $token)) {
    http_response_code(403);
    echo '{"erro":"token"}';
    exit;
}

$corpo = file_get_contents('php://input');

// Assinatura do Chatwoot (se webhook_secret estiver configurado):
//   X-Chatwoot-Signature: sha256=HMAC_SHA256(secret, "<X-Chatwoot-Timestamp>.<corpo bruto>")
$secret = (string)cfg('webhook_secret');
if ($secret !== '') {
    $tsH  = isset($_SERVER['HTTP_X_CHATWOOT_TIMESTAMP']) ? (string)$_SERVER['HTTP_X_CHATWOOT_TIMESTAMP'] : '';
    $sigH = isset($_SERVER['HTTP_X_CHATWOOT_SIGNATURE']) ? (string)$_SERVER['HTTP_X_CHATWOOT_SIGNATURE'] : '';
    $esperado = 'sha256=' . hash_hmac('sha256', $tsH . '.' . $corpo, $secret);
    // janela larga (padrão 1 dia) para aceitar os reenvios automáticos do Chatwoot
    $janela = cfg('webhook_janela_seg') ? (int)cfg('webhook_janela_seg') : 86400;
    if ($tsH === '' || !ctype_digit($tsH) || !hash_equals($esperado, $sigH) || abs(time() - (int)$tsH) > $janela) {
        http_response_code(401);
        echo '{"erro":"assinatura"}';
        exit;
    }
}

$p = json_decode($corpo, true);
if (!is_array($p)) {
    http_response_code(400);
    echo '{"erro":"json"}';
    exit;
}

// Segurança extra: ignora qualquer evento que não seja da conta do suporte
$conta = g($p, 'account.id', g($p, 'account_id'));
if ($conta !== null && (int)$conta !== (int)cfg('account_id')) {
    echo '{"ok":"ignorado"}';
    exit;
}

$evento = (string)g($p, 'event', '');
$eventosConversa = array('conversation_created', 'conversation_status_changed', 'conversation_updated');

try {
    if (in_array($evento, $eventosConversa, true) && isset($p['id'])) {
        $payload = $p;
        unset($payload['messages']);
        db()->prepare("INSERT INTO auditoria.webhook_eventos (evento, conversation_id, status, payload)
                       VALUES (?, ?, ?, ?)")
            ->execute(array($evento, (int)$p['id'], g($p, 'status'), j($payload)));
        salvarConversa($p, true);
    }
    echo '{"ok":true}';
} catch (Exception $e) {
    error_log('webhook auditoria: ' . $e->getMessage());
    http_response_code(500);
    echo '{"erro":"interno"}';
}
