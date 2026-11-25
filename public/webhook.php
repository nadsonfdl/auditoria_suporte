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

$p = json_decode(file_get_contents('php://input'), true);
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
