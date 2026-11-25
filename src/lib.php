<?php
// Funções compartilhadas por bin/sync_api.php e public/webhook.php
// Compatível com PHP 5.6+

/** Lê caminho "a.b.c" de um array; retorna $def se não existir ou for null */
function g($arr, $path, $def = null)
{
    $cur = $arr;
    foreach (explode('.', $path) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur) || $cur[$k] === null) {
            return $def;
        }
        $cur = $cur[$k];
    }
    return $cur;
}

function cfg($k = null)
{
    static $c = null;
    if ($c === null) {
        $c = require __DIR__ . '/../config/config.php';
    }
    if ($k === null) {
        return $c;
    }
    return isset($c[$k]) ? $c[$k] : null;
}

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(cfg('db_dsn'), cfg('db_user'), cfg('db_pass'), array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));
    }
    return $pdo;
}

function logmsg($m)
{
    echo date('c') . " $m\n";
}

/** JSON seguro para colunas jsonb (não falha com UTF-8 inválido) */
function j($v)
{
    $s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    return $s === false ? '{}' : $s;
}

/** GET na API do iTalk (conta configurada), com retry em 429/5xx */
function api($path, $q = array())
{
    $url = rtrim(cfg('italk_url'), '/') . '/api/v1/accounts/' . cfg('account_id') . $path
         . ($q ? '?' . http_build_query($q) : '');

    for ($tent = 0; $tent < 5; $tent++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => array('api_access_token: ' . cfg('api_token'), 'Accept: application/json'),
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code === 200) {
            $dados = json_decode($body, true);
            if (!is_array($dados)) {
                throw new RuntimeException("JSON inválido em $path");
            }
            return $dados;
        }
        if ($code === 429 || $code >= 500 || $code === 0) {
            sleep(pow(2, $tent));
            continue;
        }
        throw new RuntimeException("API $path retornou HTTP $code: " . substr((string)$body, 0, 300) . " $err");
    }
    throw new RuntimeException("API $path falhou após várias tentativas");
}

/** Converte unix (int/float/string numérica) ou ISO para ISO-8601 UTC; null se vazio */
function ts($v)
{
    if ($v === null || $v === '') {
        return null;
    }
    if (is_numeric($v)) {
        return gmdate('Y-m-d\TH:i:s\Z', (int)$v);
    }
    $t = strtotime((string)$v);
    return $t ? gmdate('Y-m-d\TH:i:s\Z', $t) : null;
}

/**
 * INSERT ... ON CONFLICT (id) DO UPDATE  (PostgreSQL 9.5+)
 * $manterExistente = true: campos que vierem null não apagam o valor já salvo (usado pelo webhook)
 */
function upsert($tabela, $row, $chave = 'id', $manterExistente = false)
{
    $cols = array_keys($row);
    $sets = array();
    foreach ($cols as $c) {
        if ($c === $chave) {
            continue;
        }
        $sets[] = $manterExistente ? "$c = COALESCE(EXCLUDED.$c, t.$c)" : "$c = EXCLUDED.$c";
    }
    $sql = "INSERT INTO $tabela AS t (" . implode(',', $cols) . ") VALUES ("
         . implode(',', array_fill(0, count($cols), '?')) . ") ON CONFLICT ($chave) DO UPDATE SET "
         . implode(',', $sets);
    db()->prepare($sql)->execute(array_values($row));
}

function getControl($k)
{
    $st = db()->prepare("SELECT valor FROM auditoria.sync_control WHERE chave = ?");
    $st->execute(array($k));
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function setControl($k, $v)
{
    db()->prepare("INSERT INTO auditoria.sync_control (chave, valor, atualizado_em) VALUES (?, ?, now())
                   ON CONFLICT (chave) DO UPDATE SET valor = EXCLUDED.valor, atualizado_em = now()")
        ->execute(array($k, (string)$v));
}

/** Lista que pode vir como [...] ou {payload: [...]} ou {data: {payload: [...]}} */
function lista($r)
{
    if (is_array(g($r, 'data.payload'))) {
        return $r['data']['payload'];
    }
    if (is_array(g($r, 'payload'))) {
        return $r['payload'];
    }
    return (is_array($r) && array_values($r) === $r) ? $r : array();
}

function salvarContato($c)
{
    if (!is_array($c) || empty($c['id'])) {
        return null;
    }
    upsert('espelho.contacts', array(
        'id'           => (int)$c['id'],
        'name'         => g($c, 'name'),
        'email'        => g($c, 'email'),
        'phone_number' => g($c, 'phone_number'),
        'raw'          => j($c),
    ), 'id', true);
    return (int)$c['id'];
}

/** Salva uma conversa no formato da API/webhook do Chatwoot */
function salvarConversa($c, $doWebhook = false)
{
    $contato = salvarContato(g($c, 'meta.sender'));
    unset($c['messages']); // não guardar mensagens no raw (pesado e desnecessário)

    upsert('espelho.conversations', array(
        'id'                     => (int)$c['id'],
        'inbox_id'               => g($c, 'inbox_id'),
        'team_id'                => g($c, 'meta.team.id'),
        'assignee_id'            => g($c, 'meta.assignee.id'),
        'contact_id'             => $contato,
        'status'                 => g($c, 'status'),
        'priority'               => g($c, 'priority'),
        'created_at'             => ts(g($c, 'created_at')),
        'last_activity_at'       => ts(g($c, 'last_activity_at', g($c, 'timestamp'))),
        'first_reply_created_at' => ts(g($c, 'first_reply_created_at')),
        'atualizado_em'          => gmdate('c'),
        'raw'                    => j($c),
    ), 'id', $doWebhook);
}
