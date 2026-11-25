<?php
/**
 * Sincroniza a conta do suporte (iTalk) via API -> banco auditoria_suporte
 * Cron:  *\/15 * * * * php /CAMINHO/auditoria_suporte/bin/sync_api.php >> /var/log/auditoria_sync.log 2>&1
 * Primeira carga: rode manualmente (pode demorar alguns minutos).
 * Compatível com PHP 5.6+
 */
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/sync.php';

$lock = fopen(sys_get_temp_dir() . '/auditoria_sync_api.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    logmsg('já em execução, saindo');
    exit(0);
}

sincronizarCadastros();
sincronizarConversas();
sincronizarCsat();
