# Regras para agentes (opencode, Claude, etc.)

Projeto: auditoria do suporte sobre o iTalk (Chatwoot 4.16 self-hosted), conta 3. Leia o README.md antes de começar.

- **Nunca** alterar o banco, os serviços, o `.env` ou as configs do servidor do iTalk. Lá só se usa a API e o cadastro de webhook.
- **Nunca** versionar `config/config.php`, tokens ou senhas. Não logar corpo de respostas com `secret`/token.
- Só `public/webhook.php` pode ser acessível pela web. Qualquer arquivo novo que precise ser público vai em `public/` e precisa ser liberado explicitamente no `public/.htaccess`.
- Este servidor hospeda outros projetos na mesma pasta web: não alterar sites, vhosts ou serviços que não sejam deste projeto.
- Mudanças de banco: sempre idempotentes (`IF NOT EXISTS`, `CREATE OR REPLACE`) em `sql/`, com novo arquivo numerado (`02_...sql`) para alterações posteriores.
- Respeitar o rate limit da API (3000 req/min por IP): manter pausas entre páginas.
- **O código precisa rodar em PHP 5.6** (o servidor usa 5.6 para outros sites): sem `??`, `fn`, tipos escalares/retorno, `Throwable`, `array_is_list` etc. Use `g($arr, 'a.b')` para ler campos opcionais e `array()` se preferir. PDO pgsql e curl. Sem frameworks.
- PostgreSQL ≥ 9.5 (usa `ON CONFLICT`).
