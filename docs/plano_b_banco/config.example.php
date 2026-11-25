<?php
// Copie para config.php e preencha. NÃO versionar o config.php.
return [
    // Banco do Chatwoot (iTalk) — usuário somente leitura criado no 01_chatwoot_views.sql
    'src_dsn'  => 'pgsql:host=127.0.0.1;port=5432;dbname=chatwoot_production',
    'src_user' => 'auditoria_ro',
    'src_pass' => 'TROQUE',

    // Banco novo da auditoria
    'dst_dsn'  => 'pgsql:host=127.0.0.1;port=5432;dbname=auditoria_suporte',
    'dst_user' => 'auditoria_app',
    'dst_pass' => 'TROQUE',
];
