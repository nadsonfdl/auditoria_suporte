-- =====================================================================
-- RODAR NO BANCO NOVO:  CREATE DATABASE auditoria_suporte;
-- espelho   = cópia dos dados da conta 3 (preenchido pelo sync.php)
-- auditoria = dados próprios do processo (fila CSAT, observações, config)
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS espelho;
CREATE SCHEMA IF NOT EXISTS auditoria;

-- ---------- ESPELHO ----------
CREATE TABLE IF NOT EXISTS espelho.conversations (
  id bigint PRIMARY KEY, display_id int, inbox_id bigint, team_id bigint,
  assignee_id bigint, contact_id bigint, status int, priority int,
  created_at timestamp, updated_at timestamp, last_activity_at timestamp,
  first_reply_created_at timestamp
);
CREATE TABLE IF NOT EXISTS espelho.csat_survey_responses (
  id bigint PRIMARY KEY, conversation_id bigint, rating int, feedback_message text,
  assigned_agent_id bigint, contact_id bigint, created_at timestamp, updated_at timestamp
);
CREATE TABLE IF NOT EXISTS espelho.reporting_events (
  id bigint PRIMARY KEY, name text, value double precision, value_in_business_hours double precision,
  inbox_id bigint, user_id bigint, conversation_id bigint,
  event_start_time timestamp, event_end_time timestamp, created_at timestamp, updated_at timestamp
);
CREATE TABLE IF NOT EXISTS espelho.users (
  id bigint PRIMARY KEY, name text, email text, role int, updated_at timestamp
);
CREATE TABLE IF NOT EXISTS espelho.teams    (id bigint PRIMARY KEY, name text, updated_at timestamp);
CREATE TABLE IF NOT EXISTS espelho.inboxes  (id bigint PRIMARY KEY, name text, channel_type text, updated_at timestamp);
CREATE TABLE IF NOT EXISTS espelho.contacts (id bigint PRIMARY KEY, name text, email text, phone_number text, updated_at timestamp);

CREATE INDEX IF NOT EXISTS ix_rep_conv_name ON espelho.reporting_events (conversation_id, name);
CREATE INDEX IF NOT EXISTS ix_conv_created  ON espelho.conversations (created_at);

-- ---------- CONTROLE / CONFIG ----------
CREATE TABLE IF NOT EXISTS auditoria.sync_control (
  tabela text PRIMARY KEY, ultimo_updated_at timestamp, ultimo_id bigint, ultima_execucao timestamptz
);
CREATE TABLE IF NOT EXISTS auditoria.config (chave text PRIMARY KEY, valor text);
INSERT INTO auditoria.config VALUES
  ('csat_nota_corte', '2'),                                   -- notas <= isso entram na fila
  ('italk_url', 'https://SEU-DOMINIO-ITALK'),                 -- sem barra no final
  ('csat_inicio', '2026-11-01')                               -- só gera fila a partir desta data
ON CONFLICT (chave) DO NOTHING;

-- ---------- FILA DE AUDITORIA CSAT ----------
CREATE TABLE IF NOT EXISTS auditoria.csat_auditoria (
  id serial PRIMARY KEY,
  csat_id bigint UNIQUE NOT NULL,
  conversation_id bigint, rating int, feedback_cliente text,
  atendente_id bigint, contato_id bigint,
  respondido_em timestamp,
  semana_ref date,                                 -- segunda-feira da semana (horário de Brasília)
  status text NOT NULL DEFAULT 'pendente'
         CHECK (status IN ('pendente','avaliado','enviado','tratado')),
  classificacao text,                              -- ex: falha atendimento / produto / regra / cliente
  observacao text,
  supervisor text,
  avaliado_em timestamptz,
  enviado_em timestamptz,
  retorno_posvendas text,
  tratado_em timestamptz
);

CREATE OR REPLACE FUNCTION auditoria.gerar_fila_csat() RETURNS int LANGUAGE sql AS $$
  WITH ins AS (
    INSERT INTO auditoria.csat_auditoria
      (csat_id, conversation_id, rating, feedback_cliente, atendente_id, contato_id, respondido_em, semana_ref)
    SELECT s.id, s.conversation_id, s.rating, s.feedback_message, s.assigned_agent_id, s.contact_id,
           s.created_at,
           date_trunc('week', (s.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Sao_Paulo')::date
    FROM espelho.csat_survey_responses s
    WHERE s.rating <= (SELECT valor::int FROM auditoria.config WHERE chave = 'csat_nota_corte')
      AND s.created_at >= (SELECT valor::date FROM auditoria.config WHERE chave = 'csat_inicio')
    ON CONFLICT (csat_id) DO NOTHING
    RETURNING 1
  ) SELECT count(*)::int FROM ins;
$$;

-- ---------- VIEWS PARA O METABASE ----------

-- 1 linha por conversa, já com flags (base para todos os indicadores)
CREATE OR REPLACE VIEW auditoria.vw_conversas AS
SELECT c.id, c.display_id,
       (c.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Sao_Paulo' AS criada_em,
       date_trunc('week', (c.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Sao_Paulo')::date AS semana,
       CASE c.status WHEN 0 THEN 'aberta' WHEN 1 THEN 'resolvida'
                     WHEN 2 THEN 'pendente' WHEN 3 THEN 'adiada' END AS status,
       u.name AS atendente, t.name AS time, i.name AS caixa_entrada,
       COALESCE(r.qtd_resolucoes, 0)                    AS qtd_resolucoes,
       (c.status = 1)                                   AS resolvida,
       (c.status = 1 AND COALESCE(r.qtd_resolucoes,0) = 1) AS resolvida_sem_reabrir,
       COALESCE(r.resolvida_bot, false)                 AS resolvida_bot,
       COALESCE(r.transferida_humano, false)            AS transferida_humano
FROM espelho.conversations c
LEFT JOIN espelho.users   u ON u.id = c.assignee_id
LEFT JOIN espelho.teams   t ON t.id = c.team_id
LEFT JOIN espelho.inboxes i ON i.id = c.inbox_id
LEFT JOIN (
  SELECT conversation_id,
         count(*) FILTER (WHERE name = 'conversation_resolved')      AS qtd_resolucoes,
         bool_or(name = 'conversation_bot_resolved')                 AS resolvida_bot,
         bool_or(name = 'conversation_bot_handoff')                  AS transferida_humano
  FROM espelho.reporting_events GROUP BY conversation_id
) r ON r.conversation_id = c.id;

-- Indicador "Auditor resolução" por semana
-- (definição provisória: resolvida e nunca reaberta / total — ajustar após alinhar com o Marcelo)
CREATE OR REPLACE VIEW auditoria.vw_indicador_resolucao_semanal AS
SELECT semana,
       count(*)                                   AS total_conversas,
       count(*) FILTER (WHERE resolvida)          AS resolvidas,
       count(*) FILTER (WHERE resolvida_sem_reabrir) AS resolvidas_na_conversa,
       round(100.0 * count(*) FILTER (WHERE resolvida_sem_reabrir) / NULLIF(count(*),0), 1) AS pct_resolucao
FROM auditoria.vw_conversas
GROUP BY semana;

-- Fila do supervisor (com link direto pra conversa no iTalk)
CREATE OR REPLACE VIEW auditoria.vw_csat_fila AS
SELECT a.id, a.semana_ref, a.status, a.rating AS nota, a.feedback_cliente,
       (a.respondido_em AT TIME ZONE 'UTC') AT TIME ZONE 'America/Sao_Paulo' AS respondido_em,
       u.name AS atendente, ct.name AS cliente, ct.phone_number AS telefone, ct.email AS email_cliente,
       (SELECT valor FROM auditoria.config WHERE chave='italk_url')
         || '/app/accounts/3/conversations/' || c.display_id AS link_conversa,
       a.classificacao, a.observacao, a.supervisor, a.avaliado_em, a.enviado_em, a.retorno_posvendas
FROM auditoria.csat_auditoria a
LEFT JOIN espelho.conversations c ON c.id = a.conversation_id
LEFT JOIN espelho.users    u  ON u.id  = a.atendente_id
LEFT JOIN espelho.contacts ct ON ct.id = a.contato_id;

-- Usuário para o Metabase (lê tudo, escreve só na fila de auditoria)
-- CREATE ROLE metabase_auditoria LOGIN PASSWORD 'TROQUE';
-- GRANT USAGE ON SCHEMA espelho, auditoria TO metabase_auditoria;
-- GRANT SELECT ON ALL TABLES IN SCHEMA espelho, auditoria TO metabase_auditoria;
-- GRANT UPDATE (status, classificacao, observacao, supervisor, avaliado_em, retorno_posvendas, tratado_em)
--   ON auditoria.csat_auditoria TO metabase_auditoria;
