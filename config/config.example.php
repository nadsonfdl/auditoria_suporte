<?php
// Copie para config/config.php e preencha. NÃO versionar o config.php.
return [
    // iTalk
    'italk_url'     => 'https://SEU-DOMINIO-ITALK',   // sem barra no final
    'account_id'    => 3,                              // conta do suporte
    'api_token'     => 'TOKEN_DE_UM_ADMIN_DA_CONTA_3', // Perfil -> Token de acesso
    'webhook_token' => 'GERE_UMA_STRING_ALEATORIA',    // ex: openssl rand -hex 24
    'webhook_secret' => '',                            // secret do webhook no iTalk (GET /webhooks); vazio = não valida assinatura

    // Primeira carga busca a partir desta data
    'carga_inicio'  => '2026-10-01',

    // Banco de análise
    'db_dsn'  => 'pgsql:host=127.0.0.1;port=5432;dbname=auditoria_suporte',
    'db_user' => 'auditoria_app',
    'db_pass' => 'TROQUE',

    // IA — análise das notas baixas (bin/analisar_ia.php). Deixe ia_api_key vazio para desligar.
    'ia_provider' => 'anthropic',          // 'anthropic' ou 'openai'
    'ia_api_key'  => '',
    'ia_model'    => 'claude-haiku-5-5',   // se openai: o modelo que vocês já usam (ex.: o da Guta)
    'ia_lote'     => 20,                   // itens por execução
    // 'ia_base_url' => '',                // opcional: proxy/endpoint compatível

    // Painel de BI (public/painel.php). Gere cada linha com: php bin/criar_usuario.php nome 'senha'
    'painel_usuarios' => array(
        // 'marcelo' => '$2y$10$...',
    ),
];
