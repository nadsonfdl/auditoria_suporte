<?php
// Copie para config/config.php e preencha. NÃO versionar o config.php.
return [
    // iTalk
    'italk_url'     => 'https://SEU-DOMINIO-ITALK',   // sem barra no final
    'account_id'    => 3,                              // conta do suporte
    'api_token'     => 'TOKEN_DE_UM_ADMIN_DA_CONTA_3', // Perfil -> Token de acesso
    'webhook_token' => 'GERE_UMA_STRING_ALEATORIA',    // ex: openssl rand -hex 24

    // Primeira carga busca a partir desta data
    'carga_inicio'  => '2026-10-01',

    // Banco de análise
    'db_dsn'  => 'pgsql:host=127.0.0.1;port=5432;dbname=auditoria_suporte',
    'db_user' => 'auditoria_app',
    'db_pass' => 'TROQUE',
];
