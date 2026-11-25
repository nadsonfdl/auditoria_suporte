<?php
/**
 * Analisa com IA as conversas da fila de CSAT baixo que ainda não foram analisadas.
 * Cron (logo depois do sync):
 *   5,20,35,50 * * * * php /CAMINHO/auditoria_suporte/bin/analisar_ia.php >> /var/log/auditoria_ia.log 2>&1
 * Reanalisar um item:  UPDATE auditoria.csat_auditoria SET ia_analisado_em = NULL, ia_tentativas = 0 WHERE id = X;
 * Compatível com PHP 5.6+
 */
require __DIR__ . '/../src/lib.php';
require __DIR__ . '/../src/ia.php';
date_default_timezone_set('America/Sao_Paulo');

$lock = fopen(sys_get_temp_dir() . '/auditoria_ia.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    logmsg('já em execução, saindo');
    exit(0);
}

if (!cfg('ia_api_key') || !cfg('ia_model')) {
    logmsg('IA não configurada (ia_api_key / ia_model no config.php), saindo');
    exit(0);
}

$lote = cfg('ia_lote') ? (int)cfg('ia_lote') : 20;
$st = db()->prepare("SELECT id, conversation_id, rating, feedback_cliente
                     FROM auditoria.csat_auditoria
                     WHERE ia_analisado_em IS NULL AND ia_tentativas < 3
                     ORDER BY respondido_em DESC LIMIT $lote");
$st->execute();
$itens = $st->fetchAll();

$ok = 0;
$falha = 0;
foreach ($itens as $it) {
    try {
        $msgs = buscarMensagens($it['conversation_id']);
        $conv = transcricao($msgs);
        if ($conv === '') {
            throw new RuntimeException('conversa sem mensagens');
        }

        $usuario = "NOTA CSAT: {$it['rating']} (de 1 a 5)\n"
                 . "COMENTÁRIO DO CLIENTE: " . ($it['feedback_cliente'] ? mascarar($it['feedback_cliente']) : '(sem comentário)') . "\n\n"
                 . "TRANSCRIÇÃO DO ATENDIMENTO:\n" . $conv;

        $d = interpretarIA(chamarIA(promptSistema(), $usuario));

        db()->prepare("UPDATE auditoria.csat_auditoria SET
                ia_motivo = ?, ia_categoria = ?, ia_responsavel = ?, ia_gravidade = ?, ia_resumo = ?,
                ia_evidencias = ?, ia_sugestao = ?, ia_confianca = ?, ia_json = ?, ia_modelo = ?,
                ia_analisado_em = now(), ia_erro = NULL
              WHERE id = ?")
            ->execute(array(
                g($d, 'motivo_principal'), g($d, 'categoria'), g($d, 'responsavel'), g($d, 'gravidade'),
                g($d, 'resumo'), $d['evidencias_txt'], g($d, 'sugestao_melhoria'), $d['confianca'],
                j($d), cfg('ia_provider') . ':' . cfg('ia_model'), $it['id'],
            ));
        $ok++;
        logmsg("item {$it['id']} (conversa {$it['conversation_id']}): " . g($d, 'categoria'));
    } catch (Exception $e) {
        db()->prepare("UPDATE auditoria.csat_auditoria SET ia_tentativas = ia_tentativas + 1, ia_erro = ? WHERE id = ?")
            ->execute(array(substr($e->getMessage(), 0, 500), $it['id']));
        $falha++;
        logmsg("item {$it['id']} ERRO: " . $e->getMessage());
    }
    usleep(500000);
}
logmsg("IA: $ok analisados, $falha falhas, " . count($itens) . " na fila");
