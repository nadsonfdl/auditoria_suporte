<?php
// Funções compartilhadas por sync_api.php e webhook.php

function cfg(?string $k = null) {
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/../config/config.php';
    return $k === null ? $c : ($c[$k] ?? null);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(cfg('db_dsn'), cfg('db_user'), cfg('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function logmsg(string $m): void { echo date('c') . " $m\n"; }

/** GET na API do iTalk (conta configurada), com retry em 429/5xx */
function api(string $path, array $q = []): array {
    $url = rtrim(cfg('italk_url'), '/') . '/api/v1/accounts/' . cfg('account_id') . $path
         . ($q ? '?' . http_build_query($q) : '');
    for ($tent = 0; $tent < 5; $tent++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['api_access_token: ' . cfg('api_token'), 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code === 200) {
            $j = json_decode($body, true);
            if (!is_array($j)) throw new RuntimeException("JSON inválido em $path");
            return $j;
        }
        if ($code === 429 || $code >= 500 || $code === 0) { sleep(2 ** $tent); continue; }
        throw new RuntimeException("API $path retornou HTTP $code: " . substr((string)$body, 0, 300) . " $err");
    }
    throw new RuntimeException("API $path falhou após várias tentativas");
}

/** Converte unix (int/float/string numérica) ou ISO para ISO-8601; null se vazio */
function ts($v): ?string {
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) return gmdate('Y-m-d\TH:i:s\Z', (int)$v);
    $t = strtotime((string)$v);
    return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : null;
}

/**
 * INSERT ... ON CONFLICT (id) DO UPDATE
 * $manterExistente = true: campos que vierem null não apagam o valor já salvo (usado pelo webhook)
 */
function upsert(string $tabela, array $row, string $chave = 'id', bool $manterExistente = false): void {
    $cols = array_keys($row);
    $set  = implode(',', array_map(
        fn($c) => $manterExistente ? "$c = COALESCE(EXCLUDED.$c, t.$c)" : "$c = EXCLUDED.$c",
        array_diff($cols, [$chave])
    ));
    $sql  = "INSERT INTO $tabela AS t (" . implode(',', $cols) . ") VALUES ("
          . implode(',', array_fill(0, count($cols), '?')) . ") ON CONFLICT ($chave) DO UPDATE SET $set";
    db()->prepare($sql)->execute(array_values($row));
}

function getControl(string $k): ?string {
    $st = db()->prepare("SELECT valor FROM auditoria.sync_control WHERE chave = ?");
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function setControl(string $k, string $v): void {
    db()->prepare("INSERT INTO auditoria.sync_control (chave, valor, atualizado_em) VALUES (?, ?, now())
                   ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = now()")
        ->execute([$k, $v]);
}

/** Lista que pode vir como [...] ou {payload: [...]} ou {data: {payload: [...]}} */
function lista(array $r): array {
    if (isset($r['data']['payload'])) return $r['data']['payload'];
    if (isset($r['payload']) && is_array($r['payload'])) return $r['payload'];
    return array_is_list($r) ? $r : [];
}

function salvarContato(?array $c): ?int {
    if (!$c || empty($c['id'])) return null;
    upsert('espelho.contacts', [
        'id' => (int)$c['id'], 'name' => $c['name'] ?? null, 'email' => $c['email'] ?? null,
        'phone_number' => $c['phone_number'] ?? null, 'raw' => json_encode($c),
    ], 'id', true);
    return (int)$c['id'];
}

/** Salva uma conversa no formato da API/webhook do Chatwoot */
function salvarConversa(array $c, bool $doWebhook = false): void {
    $m = $c['meta'] ?? [];
    $contato = salvarContato($m['sender'] ?? null);
    unset($c['messages']); // não guardar mensagens no raw (pesado e desnecessário)
    upsert('espelho.conversations', [
        'id'                     => (int)$c['id'],
        'inbox_id'               => $c['inbox_id'] ?? null,
        'team_id'                => $m['team']['id'] ?? null,
        'assignee_id'            => $m['assignee']['id'] ?? null,
        'contact_id'             => $contato,
        'status'                 => $c['status'] ?? null,
        'priority'               => $c['priority'] ?? null,
        'created_at'             => ts($c['created_at'] ?? null),
        'last_activity_at'       => ts($c['last_activity_at'] ?? $c['timestamp'] ?? null),
        'first_reply_created_at' => ts($c['first_reply_created_at'] ?? null),
        'atualizado_em'          => gmdate('c'),
        'raw'                    => json_encode($c),
    ], 'id', $doWebhook);
}
