-- =====================================================================
-- BANCO DE ANÁLISE (separado do iTalk)
--   CREATE DATABASE auditoria_suporte;
--   \c auditoria_suporte
--   \i 01_schema.sql
-- Alimentado por API (sync_api.php) + webhook (webhook.php). O banco do iTalk não é tocado.
-- Obs.: na API do Chatwoot o "id" da conversa é o display_id (o número que aparece na tela).
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS espelho;
CREATE SCHEMA IF NOT EXISTS auditoria;

-- ---------- ESPELHO (dados vindos da API) ----------
CREATE TABLE IF NOT EXISTS espelho.conversations (
  id int PRIMARY KEY,                 -- display_id
  inbox_id bigint, team_id bigint, assignee_id bigint, contact_id bigint,
  status text,                        -- open / resolved / pending / snoozed
  priority text,
  created_at timestamptz, last_activity_at timestamptz, first_reply_created_at timestamptz,
  atualizado_em timestamptz DEFAULT now(),
  raw jsonb
);
CREATE INDEX IF NOT EXISTS ix_conv_created ON espelho.conversations (created_at);

CREATE TABLE IF NOT EXISTS espelho.csat (
  id bigint PRIMARY KEY,
  conversation_id int,                -- display_id
  rating int, feedback_message text,
  assigned_agent_id bigint, contact_id bigint,
  created_at timestamptz,
  raw jsonb
);

CREATE TABLE IF NOT EXISTS espelho.agents   (id bigint PRIMARY KEY, name text, email text, role text, raw jsonb);
CREATE TABLE IF NOT EXISTS espelho.teams    (id bigint PRIMARY KEY, name text, raw jsonb);
CREATE TABLE IF NOT EXISTS espelho.inboxes  (id bigint PRIMARY KEY, name text, channel_type text, raw jsonb);
CREATE TABLE IF NOT EXISTS espelho.contacts (id bigint PRIMARY KEY, name text, email text, phone_number text, raw jsonb);

-- ---------- EVENTOS DO WEBHOOK ----------
CREATE TABLE IF NOT EXISTS auditoria.webhook_eventos (
  id bigserial PRIMARY KEY,
  evento text, conversation_id int, status text,
  recebido_em timestamptz DEFAULT now(),
  payload jsonb
);
CREATE INDEX IF NOT EXISTS ix_wh_conv ON auditoria.webhook_eventos (conversation_id, recebido_em);

-- ---------- CONTROLE / CONFIG ----------
CREATE TABLE IF NOT EXISTS auditoria.sync_control (chave text PRIMARY KEY, valor text, atualizado_em timestamptz DEFAULT now());

CREATE TABLE IF NOT EXISTS auditoria.config (chave text PRIMARY KEY, valor text);
INSERT INTO auditoria.config VALUES
  ('csat_nota_corte', '2'),                          -- notas <= isso entram na fila
  ('italk_url',       'https://SEU-DOMINIO-ITALK'),  -- sem barra no final (usado no link da fila)
  ('csat_inicio',     '2026-11-01'),                 -- só gera fila de CSAT a partir desta data
  ('rastreio_inicio', '2026-11-26')                  -- dia em que o webhook foi ligado (reaberturas medidas a partir daqui)
ON CONFLICT (chave) DO NOTHING;

-- ---------- FILA DE AUDITORIA CSAT ----------
CREATE TABLE IF NOT EXISTS auditoria.csat_auditoria (
  id serial PRIMARY KEY,
  csat_id bigint UNIQUE NOT NULL,
  conversation_id int, rating int, feedback_cliente text,
  atendente_id bigint, contato_id bigint,
  respondido_em timestamptz,
  semana_ref date,                                   -- segunda-feira da semana (Brasília)
  status text NOT NULL DEFAULT 'pendente'
         CHECK (status IN ('pendente','avaliado','enviado','tratado')),
  classificacao text, observacao text, supervisor text,
  avaliado_em timestamptz, enviado_em timestamptz,
  retorno_posvendas text, tratado_em timestamptz
);

CREATE OR REPLACE FUNCTION auditoria.cfg(k text) RETURNS text LANGUAGE sql STABLE AS
$$ SELECT valor FROM auditoria.config WHERE chave = k $$;

CREATE OR REPLACE FUNCTION auditoria.gerar_fila_csat() RETURNS int LANGUAGE sql AS $$
  WITH ins AS (
    INSERT INTO auditoria.csat_auditoria
      (csat_id, conversation_id, rating, feedback_cliente, atendente_id, contato_id, respondido_em, semana_ref)
    SELECT s.id, s.conversation_id, s.rating, s.feedback_message, s.assigned_agent_id, s.contact_id,
           s.created_at, date_trunc('week', s.created_at AT TIME ZONE 'America/Sao_Paulo')::date
    FROM espelho.csat s
    WHERE s.rating <= auditoria.cfg('csat_nota_corte')::int
      AND s.created_at >= auditoria.cfg('csat_inicio')::date
    ON CONFLICT (csat_id) DO NOTHING
    RETURNING 1
  ) SELECT count(*)::int FROM ins;
$$;

-- ---------- VIEWS PARA O METABASE ----------

-- Resoluções e reaberturas por conversa (vindas do webhook)
CREATE OR REPLACE VIEW auditoria.vw_status_conversa AS
WITH ev AS (
  SELECT conversation_id, status,
         lag(status) OVER (PARTITION BY conversation_id ORDER BY recebido_em, id) AS status_anterior
  FROM auditoria.webhook_eventos
  WHERE evento = 'conversation_status_changed'
)
SELECT conversation_id,
       count(*) FILTER (WHERE status = 'resolved')                               AS qtd_resolucoes,
       count(*) FILTER (WHERE status = 'open' AND status_anterior = 'resolved')  AS qtd_reaberturas
FROM ev GROUP BY conversation_id;

-- 1 linha por conversa (base de todos os indicadores; filtros por atendente/time/caixa no Metabase)
CREATE OR REPLACE VIEW auditoria.vw_conversas AS
SELECT c.id AS conversa,
       c.created_at AT TIME ZONE 'America/Sao_Paulo'                         AS criada_em,
       date_trunc('week', c.created_at AT TIME ZONE 'America/Sao_Paulo')::date AS semana,
       c.status, a.name AS atendente, t.name AS time, i.name AS caixa_entrada,
       COALESCE(s.qtd_resolucoes, 0)  AS qtd_resolucoes,
       COALESCE(s.qtd_reaberturas, 0) AS qtd_reaberturas,
       (c.created_at >= auditoria.cfg('rastreio_inicio')::date)              AS rastreada,
       (c.status = 'resolved')                                               AS resolvida,
       (c.status = 'resolved' AND COALESCE(s.qtd_reaberturas, 0) = 0)        AS resolvida_sem_reabrir
FROM espelho.conversations c
LEFT JOIN espelho.agents  a ON a.id = c.assignee_id
LEFT JOIN espelho.teams   t ON t.id = c.team_id
LEFT JOIN espelho.inboxes i ON i.id = c.inbox_id
LEFT JOIN auditoria.vw_status_conversa s ON s.conversation_id = c.id;

-- Indicador "Auditor resolução" por semana
--   pct_resolvidas          = resolvidas / total (vale para todo o histórico)
--   pct_resolucao_conversa  = resolvidas sem reabrir / total (só conversas criadas após ligar o webhook)
CREATE OR REPLACE VIEW auditoria.vw_indicador_resolucao_semanal AS
SELECT semana,
       count(*)                                          AS total_conversas,
       count(*) FILTER (WHERE resolvida)                 AS resolvidas,
       round(100.0 * count(*) FILTER (WHERE resolvida) / NULLIF(count(*), 0), 1) AS pct_resolvidas,
       count(*) FILTER (WHERE rastreada)                 AS total_rastreadas,
       count(*) FILTER (WHERE rastreada AND resolvida_sem_reabrir) AS resolvidas_na_conversa,
       round(100.0 * count(*) FILTER (WHERE rastreada AND resolvida_sem_reabrir)
             / NULLIF(count(*) FILTER (WHERE rastreada), 0), 1) AS pct_resolucao_conversa
FROM auditoria.vw_conversas
GROUP BY semana;

-- Fila do supervisor / lista do pós-vendas (com link direto pra conversa)
-- (DROP antes porque o 02_ia.sql acrescenta colunas; assim rodar todos os SQLs de novo nunca quebra)
DROP VIEW IF EXISTS auditoria.vw_csat_fila;
CREATE VIEW auditoria.vw_csat_fila AS
SELECT q.id, q.semana_ref, q.status, q.rating AS nota, q.feedback_cliente,
       q.respondido_em AT TIME ZONE 'America/Sao_Paulo' AS respondido_em,
       a.name AS atendente, ct.name AS cliente, ct.phone_number AS telefone, ct.email AS email_cliente,
       auditoria.cfg('italk_url') || '/app/accounts/3/conversations/' || q.conversation_id AS link_conversa,
       q.classificacao, q.observacao, q.supervisor, q.avaliado_em, q.enviado_em, q.retorno_posvendas
FROM auditoria.csat_auditoria q
LEFT JOIN espelho.agents   a  ON a.id  = q.atendente_id
LEFT JOIN espelho.contacts ct ON ct.id = q.contato_id;

-- Usuário do Metabase (lê tudo, escreve só nos campos de avaliação)
-- CREATE ROLE metabase_auditoria LOGIN PASSWORD 'TROQUE';
-- GRANT USAGE ON SCHEMA espelho, auditoria TO metabase_auditoria;
-- GRANT SELECT ON ALL TABLES IN SCHEMA espelho, auditoria TO metabase_auditoria;
-- GRANT EXECUTE ON FUNCTION auditoria.cfg(text) TO metabase_auditoria;
-- GRANT UPDATE (status, classificacao, observacao, supervisor, avaliado_em, retorno_posvendas, tratado_em)
--   ON auditoria.csat_auditoria TO metabase_auditoria;
