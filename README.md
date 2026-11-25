# Auditoria Suporte — iTalk (conta 3)

Indicadores e auditoria de CSAT do suporte, alimentados pela **API e webhook do iTalk** (Chatwoot 4.16, self-hosted).
O banco do iTalk **não é alterado**: tudo fica num banco de análise separado (`auditoria_suporte`), consumido pelo Metabase.

## Estrutura

```
auditoria_suporte/
├── .htaccess              # bloqueia o projeto inteiro na web
├── public/
│   ├── .htaccess          # libera SOMENTE webhook.php e painel.php
│   ├── webhook.php        # recebe eventos de status das conversas (tempo real)
│   └── painel.php         # painel de BI (login, visão geral, atendentes, fila de auditoria)
├── bin/
│   ├── sync_api.php       # sincronização via API (cron a cada 15 min)
│   ├── analisar_ia.php    # análise de IA das notas baixas (cron)
│   ├── agendador.php      # alertas, resumo semanal, temas e auditoria de qualidade (cron 5 min)
│   ├── criar_usuario.php  # gera usuário/senha do painel
│   └── atualizar.sh       # atualiza o servidor pela main
├── src/
│   ├── lib.php            # funções compartilhadas (API, banco, mapeamento)
│   ├── ia.php             # transcrição, máscara de dados pessoais, chamada à IA
│   ├── painel.php         # consultas do painel de BI
│   ├── sync.php           # sincronização via API (funções)
│   ├── notificar.php      # configurações, e-mail (SMTP) e WhatsApp
│   ├── automacoes.php     # alerta, resumo semanal, pós-vendas, temas, issue no GitHub
│   ├── qa.php             # auditoria de qualidade por IA de todas as conversas
│   ├── views/             # telas do painel (login, diagnóstico, atendentes, fila)
│   └── vendor/            # Chart.js 4.4.1 (MIT), embutido na página — sem CDN
├── sql/
│   ├── 01_schema.sql      # tabelas, fila CSAT e views do Metabase
│   ├── 02_ia.sql          # colunas da análise de IA + view de motivos
│   ├── 03_painel.sql      # views do painel de BI
│   ├── 04_automacoes.sql  # configurações, envios, temas, pós-vendas
│   ├── 05_qualidade.sql   # auditoria de qualidade por IA
│   └── 99_permissoes.sql  # grants do Metabase (roda por último)
├── config/
│   ├── config.example.php # modelo
│   └── config.php         # (não versionado) token, senha, etc.
└── docs/plano_b_banco/    # alternativa lendo direto do banco do Chatwoot (não usada)
```

## Arquitetura

```
iTalk (Chatwoot)  ── banco NÃO é alterado
   │  API (token de admin da conta 3)          → bin/sync_api.php (cron)
   │  Webhook da conta 3 (status de conversa)  → public/webhook.php
   ▼
Banco auditoria_suporte
   ├─ espelho.*    → conversas, CSAT, agentes, times, caixas, contatos (só conta 3)
   └─ auditoria.*  → eventos do webhook, fila CSAT, observações, config, views
   ▼
Metabase → indicador de resolução + fila CSAT editável + e-mail semanal ao pós-vendas
```

Detalhes do iTalk (validados em 25/11/2026):
- Na API, `id` da conversa = número exibido na tela (display_id). O CSAT usa o mesmo número em `conversation_id`.
- Datas das conversas e do CSAT vêm em unix (segundos).
- Rack::Attack ativo: 3000 req/min por IP (o sync faz pausa entre páginas). Cloudflare na frente.
- Já existe o webhook "IA Guta" na conta 3; o da auditoria é um segundo webhook, independente.

## Requisitos

- **PHP 5.6+** com `pdo_pgsql` e `curl` (o código é compatível com 5.6, verificado com PHPCompatibility).
- **PostgreSQL 9.5+** para o banco de análise.
  Se o PHP 5.6 não conseguir autenticar no Postgres (erro de SCRAM), o `libpq` dele é antigo: crie a senha do usuário com `md5`
  (`SET password_encryption = 'md5'; ALTER ROLE auditoria_app PASSWORD '...';`) e use `md5` no `pg_hba.conf` para esse usuário.
- Apache com `AllowOverride All` (ou `AuthConfig`) na pasta do projeto.

## Instalação

1. **Código**: clonar dentro da pasta web (`.../code/auditoria_suporte`).
   O Apache precisa de `AllowOverride All` (ou `AuthConfig`) nessa pasta para os `.htaccess` valerem.
2. **Banco**:
   ```bash
   sudo -u postgres psql -c "CREATE ROLE auditoria_app LOGIN PASSWORD 'TROQUE';"
   sudo -u postgres psql -c "CREATE DATABASE auditoria_suporte OWNER auditoria_app;"
   psql -h 127.0.0.1 -U auditoria_app -d auditoria_suporte -f sql/01_schema.sql
   ```
   Ajustar `auditoria.config`: `italk_url`, `csat_nota_corte`, `csat_inicio`, `rastreio_inicio` (= dia em que o webhook for ligado).
3. **Config**: `cp config/config.example.php config/config.php` → preencher; `webhook_token` com `openssl rand -hex 24`; `chmod 640`.
4. **Primeira carga**: `php bin/sync_api.php` (busca desde `carga_inicio`).
5. **Cron**:
   ```
   */15 * * * * php /CAMINHO/auditoria_suporte/bin/sync_api.php >> /var/log/auditoria_sync.log 2>&1
   5,20,35,50 * * * * php /CAMINHO/auditoria_suporte/bin/analisar_ia.php >> /var/log/auditoria_ia.log 2>&1
   */5 * * * * php /CAMINHO/auditoria_suporte/bin/agendador.php >> /var/log/auditoria_agendador.log 2>&1
   ```
6. **Conferir segurança** (obrigatório antes de cadastrar o webhook):
   ```bash
   curl -I https://appiasuporte.igut.med.br/auditoria_suporte/config/config.php   # 403
   curl -I https://appiasuporte.igut.med.br/auditoria_suporte/src/lib.php         # 403
   curl -X POST "https://appiasuporte.igut.med.br/auditoria_suporte/webhook.php?token=TOKEN" -d '{}'   # {"ok":true}
   curl -X POST "https://appiasuporte.igut.med.br/auditoria_suporte/webhook.php?token=errado" -d '{}'  # 403
   ```
7. **Webhook** (no servidor do iTalk, via API ou tela Configurações → Integrações → Webhooks da conta 3):
   ```bash
   curl -s -X POST -H "api_access_token: TOKEN" -H "Content-Type: application/json" \
     "https://ITALK/api/v1/accounts/3/webhooks" \
     -d '{"webhook":{"url":"https://appiasuporte.igut.med.br/auditoria_suporte/webhook.php?token=WEBHOOK_TOKEN","subscriptions":["conversation_created","conversation_status_changed","conversation_updated"]}}'
   ```
   Conferir: `SELECT * FROM auditoria.webhook_eventos ORDER BY id DESC LIMIT 5;`
   **Assinatura**: copie o `secret` do webhook (GET `/api/v1/accounts/3/webhooks`) para `webhook_secret` no `config.php`.
   Com ele preenchido, o endpoint só aceita eventos com `X-Chatwoot-Signature` válida (HMAC-SHA256 de `"<timestamp>.<corpo>"`), além do token.
8. **Metabase** (usuário `metabase_auditoria`, ver fim do `sql/01_schema.sql`):
   - **Auditor resolução** → `auditoria.vw_indicador_resolucao_semanal`
   - Detalhe por atendente/time/caixa → `auditoria.vw_conversas`
   - **Fila CSAT** → Model sobre `auditoria.csat_auditoria` + *Model actions* (Update: `status, classificacao, observacao, supervisor, avaliado_em`). Metabase ≥ 46.
   - **E-mail pós-vendas** → pergunta em `auditoria.vw_csat_fila` com `status = 'avaliado'` da semana anterior + *Subscription* semanal.

## Atualização (fluxo git)

Desenvolvimento acontece na branch `main` do GitHub. No servidor:
```bash
cd /var/www/webroot/ROOT/auditoria_suporte && bash bin/atualizar.sh
```
O script faz `fetch` + `merge --ff-only` da `main` (recusa se houver alteração local no servidor), valida a sintaxe
PHP com o PHP do servidor e reaplica `sql/*.sql` em ordem (todos idempotentes; `99_permissoes.sql` refaz os grants do Metabase).
Alterações de banco novas = novo arquivo `sql/NN_descricao.sql`. Nunca editar código direto no servidor.

## Painel de BI

`https://appiasuporte.igut.med.br/auditoria_suporte/painel.php` — login próprio (sessão + CSRF).

| Aba | O que mostra |
|---|---|
| **Diagnóstico** | Frase-resumo gerada dos dados; "do encerramento à satisfação" (encerradas → sem reabrir → **resolvidas de fato pela IA** → satisfeitos); KPIs com comparação e **semáforo de metas**; volume, resolução, mapa de calor, 1ª resposta, CSAT, notas, canais; **Guta** (contenção e resolução); motivos das notas baixas; **problemas recorrentes de produto** com issue no GitHub |
| **Qualidade (IA)** | Auditoria de **todas** as conversas encerradas, com ou sem nota: resolução real, nota de qualidade por critério, falhas com trecho da conversa, risco de insatisfação sem nota, cliente sem resposta, maior espera, motivos de contato, Guta × equipe, conversas que pedem atenção |
| **Atendentes** | Destaques e ranking ordenável, incluindo qualidade e resolução real pela IA |
| **Auditoria CSAT** | Fluxo da diretoria: notas baixas + conversas de risco da IA → avaliação do supervisor → **e-mail ao pós-vendas com planilha** → retorno e "tratado" do pós-vendas |
| **Configurações** | Só administradores (`painel_admins`). Liga/desliga cada recurso, metas, destinatários, SMTP, WhatsApp, botões de teste e histórico de envios |

Todo indicador e gráfico tem um botão **i** com: o que é, como é calculado, como ler e referência de mercado.
Também: tour guiado (tecla T), modo apresentação (tecla A), impressão/PDF e animações sutis (respeitam "reduzir movimento").

Criar usuário:
```bash
php bin/criar_usuario.php marcelo 'SenhaForte123'   # cola a linha gerada em 'painel_usuarios' no config/config.php
```

## Automações (bin/agendador.php, cron a cada 5 min)

```
*/5 * * * * php /CAMINHO/auditoria_suporte/bin/agendador.php >> /var/log/auditoria_agendador.log 2>&1
```
Cada execução, conforme o que estiver ligado em Configurações:

| Recurso | Como funciona |
|---|---|
| **Alerta de nota baixa** | Busca CSAT novo e avisa por e-mail e/ou WhatsApp em até 5 min (só notas recebidas depois de ligado; nunca repete) |
| **Resumo semanal** | No dia/hora configurados, e-mail com a semana anterior × retrasada (+ resumo curto no WhatsApp, opcional) |
| **Problemas recorrentes** | 1×/dia a IA agrupa notas baixas com causa em produto em temas; botão abre issue no GitHub |
| **Auditoria de qualidade** | A IA audita um lote de conversas encerradas; risco alto sem nota entra na fila do supervisor |

Envio ao pós-vendas: botão na Auditoria CSAT. Com e-mails do pós-vendas + SMTP configurados, envia e-mail com a planilha
e marca como enviado; sem isso, só marca e a planilha é baixada manualmente.

**E-mail:** PHPMailer (vendorizado em `src/vendor/PHPMailer`), SMTP com TLS/SSL. **WhatsApp:** pelo próprio iTalk (caixa
de WhatsApp da conta), Evolution API, Z-API ou webhook genérico (n8n/Make). Segredos ficam no banco e nunca voltam à tela.
Tudo que é enviado fica em `auditoria.envios` (aba Configurações > Histórico).

## Análise de IA das notas baixas

Para cada item da fila de CSAT baixo, `bin/analisar_ia.php`:
1. busca a conversa completa pela API (`/conversations/{id}/messages`, paginando);
2. monta a transcrição (cliente / Guta / atendente / notas internas / eventos do sistema) e **mascara e-mail, telefone, CPF e CNPJ**;
3. envia ao modelo de IA configurado (Anthropic ou OpenAI) junto com a nota e o comentário do cliente;
4. grava em `auditoria.csat_auditoria`: `ia_motivo`, `ia_categoria`, `ia_responsavel`, `ia_gravidade`, `ia_resumo`, `ia_evidencias`, `ia_sugestao`, `ia_confianca`.

O supervisor vê a análise pronta na fila (`vw_csat_fila`) e só confirma/ajusta a `classificacao` e a `observacao`.
`auditoria.vw_csat_motivos_ia` agrega por categoria/responsável/atendente para o gráfico "Por que as notas são baixas".

Instalação:
```bash
psql -h 127.0.0.1 -U auditoria_app -d auditoria_suporte -f sql/02_ia.sql
# config/config.php: ia_provider, ia_api_key, ia_model
php bin/analisar_ia.php
# cron, logo depois do sync:
5,20,35,50 * * * * php /CAMINHO/auditoria_suporte/bin/analisar_ia.php >> /var/log/auditoria_ia.log 2>&1
```
Até 3 tentativas por item (erro fica em `ia_erro`). Reanalisar: `UPDATE auditoria.csat_auditoria SET ia_analisado_em = NULL, ia_tentativas = 0 WHERE id = X;`
O servidor precisa de saída HTTPS para `api.anthropic.com` ou `api.openai.com`.

## Indicador

| Coluna | Cálculo | Vale para |
|---|---|---|
| `pct_resolvidas` | resolvidas / total | todo o histórico |
| `pct_resolucao_conversa` | resolvidas **sem reabrir** / total | conversas criadas após ligar o webhook |

Reabertura = evento `open` logo após um `resolved` (vindo do webhook). A API não guarda histórico de status.

## Pendências com o Marcelo
- Definição de "resolvido na conversa" (sem reabrir? sem escalar? resolvido pela Guta?)
- Nota de corte do CSAT (≤2 ou ≤3)
- Supervisor por time ou geral; prazo semanal
- Pós-vendas só recebe ou também registra o tratamento

## Próximas fases
- Tela PHP própria para o supervisor (no lugar do Actions do Metabase)
- Marcar `enviado_em` automaticamente; retorno do pós-vendas
- Indicador de resolução da Guta (IA)
- Validar assinatura do webhook (secret do Chatwoot) além do token na URL
