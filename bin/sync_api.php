<?php
/**
 * Sincroniza a conta do suporte (iTalk) via API -> banco auditoria_suporte
 * Cron:  *\/15 * * * * php /CAMINHO/auditoria_suporte/bin/sync_api.php >> /var/log/auditoria_sync.log 2>&1
 * Primeira carga: rode manualmente (pode demorar alguns minutos).
 */
require __DIR__ . '/../src/lib.php';

$lock = fopen(sys_get_temp_dir() . '/auditoria_sync_api.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) { logmsg('já em execução, saindo'); exit(0); }

$cargaInicio = strtotime(cfg('carga_inicio') ?? '2026-10-01');

// ---------- 1. Cadastros (pequenos, sempre completos) ----------
$n = 0;
foreach (lista(api('/agents')) as $a) {
    upsert('espelho.agents', ['id' => (int)$a['id'], 'name' => $a['name'] ?? null,
        'email' => $a['email'] ?? null, 'role' => $a['role'] ?? null, 'raw' => json_encode($a)]);
    $n++;
}
logmsg("agentes: $n");

$n = 0;
foreach (lista(api('/teams')) as $t) {
    upsert('espelho.teams', ['id' => (int)$t['id'], 'name' => $t['name'] ?? null, 'raw' => json_encode($t)]);
    $n++;
}
logmsg("times: $n");

$n = 0;
foreach (lista(api('/inboxes')) as $i) {
    upsert('espelho.inboxes', ['id' => (int)$i['id'], 'name' => $i['name'] ?? null,
        'channel_type' => $i['channel_type'] ?? null, 'raw' => json_encode($i)]);
    $n++;
}
logmsg("caixas de entrada: $n");

// ---------- 2. Conversas (ordenadas por última atividade, da mais recente pra trás) ----------
// Para quando chegar em conversas sem atividade desde a última sync (com 1h de margem).
$wm     = getControl('conv_ultima_atividade');
$limite = $wm ? (int)$wm - 3600 : $cargaInicio;
$maxAtv = (int)($wm ?? 0);
$total  = 0;

for ($page = 1; $page <= 5000; $page++) {
    $itens = lista(api('/conversations', ['status' => 'all', 'assignee_type' => 'all', 'page' => $page]));
    if (!$itens) break;

    $parar = false;
    db()->beginTransaction();
    foreach ($itens as $c) {
        $atv = (int)($c['last_activity_at'] ?? $c['timestamp'] ?? 0);
        if ($atv && $atv < $limite) { $parar = true; continue; }
        salvarConversa($c);
        $maxAtv = max($maxAtv, $atv);
        $total++;
    }
    db()->commit();
    if ($parar) break;
    usleep(200000); // pausa leve entre páginas (Rack::Attack: 3000 req/min por IP)
}
if ($maxAtv) setControl('conv_ultima_atividade', (string)$maxAtv);
logmsg("conversas: $total");

// ---------- 3. CSAT (filtrado por data de resposta) ----------
$wm    = getControl('csat_ultimo');
$since = $wm ? (int)$wm - 86400 : $cargaInicio;   // 1 dia de margem
$until = time() + 3600;
$total = 0;
$maxTs = (int)($wm ?? 0);

for ($page = 1; $page <= 2000; $page++) {
    $itens = lista(api('/csat_survey_responses', ['since' => $since, 'until' => $until, 'page' => $page]));
    if (!$itens) break;

    db()->beginTransaction();
    foreach ($itens as $r) {
        $contato = salvarContato($r['contact'] ?? null) ?? ($r['contact_id'] ?? null);
        $criado  = ts($r['created_at'] ?? null);
        upsert('espelho.csat', [
            'id'                => (int)$r['id'],
            'conversation_id'   => $r['conversation_id'] ?? ($r['conversation']['id'] ?? null),
            'rating'            => $r['rating'] ?? null,
            'feedback_message'  => $r['feedback_message'] ?? null,
            'assigned_agent_id' => $r['assigned_agent']['id'] ?? ($r['assigned_agent_id'] ?? null),
            'contact_id'        => $contato,
            'created_at'        => $criado,
            'raw'               => json_encode($r),
        ]);
        if ($criado) $maxTs = max($maxTs, strtotime($criado));
        $total++;
    }
    db()->commit();
}
if ($maxTs) setControl('csat_ultimo', (string)$maxTs);
logmsg("csat: $total");

// ---------- 4. Fila de auditoria ----------
$novos = db()->query("SELECT auditoria.gerar_fila_csat()")->fetchColumn();
logmsg("fila CSAT: $novos novos itens");
