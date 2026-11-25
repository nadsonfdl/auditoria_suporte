<?php
/**
 * Sync incremental: iTalk (conta 3, via views auditoria_src) -> banco auditoria_suporte (schema espelho)
 * Cron sugerido:  *\/15 * * * * php /opt/auditoria/sync.php >> /var/log/auditoria_sync.log 2>&1
 */

$lock = fopen(sys_get_temp_dir() . '/auditoria_sync.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) { echo date('c') . " já em execução, saindo\n"; exit(0); }

$cfg = require __DIR__ . '/config.php';
$opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$src = new PDO($cfg['src_dsn'], $cfg['src_user'], $cfg['src_pass'], $opt);
$dst = new PDO($cfg['dst_dsn'], $cfg['dst_user'], $cfg['dst_pass'], $opt);

$tabelas = [
    'users'                 => ['id','name','email','role','updated_at'],
    'teams'                 => ['id','name','updated_at'],
    'inboxes'               => ['id','name','channel_type','updated_at'],
    'contacts'              => ['id','name','email','phone_number','updated_at'],
    'conversations'         => ['id','display_id','inbox_id','team_id','assignee_id','contact_id','status',
                                'priority','created_at','updated_at','last_activity_at','first_reply_created_at'],
    'csat_survey_responses' => ['id','conversation_id','rating','feedback_message','assigned_agent_id',
                                'contact_id','created_at','updated_at'],
    'reporting_events'      => ['id','name','value','value_in_business_hours','inbox_id','user_id',
                                'conversation_id','event_start_time','event_end_time','created_at','updated_at'],
];
$lote = 2000;

foreach ($tabelas as $t => $cols) {
    $st = $dst->prepare("SELECT ultimo_updated_at FROM auditoria.sync_control WHERE tabela = ?");
    $st->execute([$t]);
    $wm = $st->fetchColumn() ?: '1970-01-01 00:00:00';

    $lista = implode(',', $cols);
    $sel = $src->prepare("SELECT $lista FROM auditoria_src.$t
        WHERE (updated_at, id) > (?::timestamp, ?::bigint)
        ORDER BY updated_at, id LIMIT $lote");

    $ph  = implode(',', array_fill(0, count($cols), '?'));
    $set = implode(',', array_map(fn($c) => "$c = EXCLUDED.$c", array_diff($cols, ['id'])));
    $ups = $dst->prepare("INSERT INTO espelho.$t ($lista) VALUES ($ph) ON CONFLICT (id) DO UPDATE SET $set");

    // margem de 10 min para não perder registros com commit atrasado
    $curTs = date('Y-m-d H:i:s', strtotime($wm . ' -10 minutes'));
    $curId = 0;
    $total = 0;
    $maxTs = $wm;

    do {
        $sel->execute([$curTs, $curId]);
        $rows = $sel->fetchAll();
        if (!$rows) break;

        $dst->beginTransaction();
        foreach ($rows as $r) {
            $ups->execute(array_values($r));
        }
        $dst->commit();

        $ult   = end($rows);
        $curTs = $ult['updated_at'];
        $curId = $ult['id'];
        if ($curTs > $maxTs) $maxTs = $curTs;
        $total += count($rows);
    } while (count($rows) === $lote);

    $dst->prepare("INSERT INTO auditoria.sync_control (tabela, ultimo_updated_at, ultima_execucao)
                   VALUES (?, ?, now())
                   ON CONFLICT (tabela) DO UPDATE SET ultimo_updated_at = EXCLUDED.ultimo_updated_at,
                                                      ultima_execucao = now()")
        ->execute([$t, $maxTs]);

    echo date('c') . " $t: $total registros\n";
}

$novos = $dst->query("SELECT auditoria.gerar_fila_csat()")->fetchColumn();
echo date('c') . " fila CSAT: $novos novos itens\n";
