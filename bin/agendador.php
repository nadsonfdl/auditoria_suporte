<?php
/**
 * Agendador das automações. Um único cron a cada 5 minutos:
 *   *\/5 * * * * php /CAMINHO/auditoria_suporte/bin/agendador.php >> /var/log/auditoria_agendador.log 2>&1
 *
 * Cada execução, conforme o que estiver ligado na aba Configurações:
 *  - alerta de nota baixa: busca CSAT novo e avisa por e-mail/WhatsApp
 *  - resumo semanal: envia no dia e hora configurados (uma vez por semana)
 *  - problemas recorrentes: agrupa com IA uma vez por dia
 *  - auditoria de qualidade: analisa com IA um lote de conversas encerradas
 * Compatível com PHP 5.6+
 */
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/painel.php';
require __DIR__ . '/../src/notificar.php';
require __DIR__ . '/../src/automacoes.php';
require __DIR__ . '/../src/sync.php';
require __DIR__ . '/../src/ia.php';
require __DIR__ . '/../src/qa.php';
date_default_timezone_set('America/Sao_Paulo');

$lock = fopen(sys_get_temp_dir() . '/auditoria_agendador.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    logmsg('já em execução, saindo');
    exit(0);
}

$etapas = array(
    'alertas' => function () {
        if (!confLigado('alerta_ativo')) {
            return 'desligado';
        }
        sincronizarCsat();
        return processarAlertas() . ' alerta(s) enviado(s)';
    },
    'resumo' => function () {
        $r = processarResumoSemanal(false);
        return $r === false ? 'não é a hora' : ($r[0] ? 'enviado' : 'falhou: ' . $r[1]);
    },
    'temas' => function () {
        return processarTemas(false) . ' caso(s) agrupado(s)';
    },
    'qualidade' => function () {
        return processarQualidade() . ' conversa(s) auditada(s)';
    },
);

foreach ($etapas as $nome => $fn) {
    try {
        logmsg("$nome: " . $fn());
    } catch (Exception $e) {
        logmsg("$nome ERRO: " . $e->getMessage());
    }
}
