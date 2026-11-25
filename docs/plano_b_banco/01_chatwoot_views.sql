-- =====================================================================
-- RODAR NO BANCO DO CHATWOOT (iTalk), como superusuário/owner do banco
-- Cria um "portão" que expõe SOMENTE a conta 3 (suporte).
-- O usuário auditoria_ro não enxerga nenhuma tabela real, só estas views.
-- =====================================================================

CREATE SCHEMA IF NOT EXISTS auditoria_src;

CREATE OR REPLACE VIEW auditoria_src.conversations AS
SELECT id, display_id, inbox_id, team_id, assignee_id, contact_id,
       status, priority, created_at, updated_at, last_activity_at, first_reply_created_at
FROM public.conversations
WHERE account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.csat_survey_responses AS
SELECT id, conversation_id, rating, feedback_message, assigned_agent_id,
       contact_id, created_at, updated_at
FROM public.csat_survey_responses
WHERE account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.reporting_events AS
SELECT id, name, value, value_in_business_hours, inbox_id, user_id, conversation_id,
       event_start_time, event_end_time, created_at, updated_at
FROM public.reporting_events
WHERE account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.users AS
SELECT u.id, u.name, u.email, au.role,
       GREATEST(u.updated_at, au.updated_at) AS updated_at
FROM public.users u
JOIN public.account_users au ON au.user_id = u.id
WHERE au.account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.teams AS
SELECT id, name, updated_at FROM public.teams WHERE account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.inboxes AS
SELECT id, name, channel_type, updated_at FROM public.inboxes WHERE account_id = 3;

CREATE OR REPLACE VIEW auditoria_src.contacts AS
SELECT id, name, email, phone_number, updated_at FROM public.contacts WHERE account_id = 3;

-- Usuário somente leitura (troque a senha e o nome do banco)
CREATE ROLE auditoria_ro LOGIN PASSWORD 'TROQUE_ESTA_SENHA';
GRANT CONNECT ON DATABASE chatwoot_production TO auditoria_ro;
GRANT USAGE ON SCHEMA auditoria_src TO auditoria_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA auditoria_src TO auditoria_ro;

-- Índices que ajudam o sync incremental (opcional, rodar fora do horário de pico)
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_conv_acc_upd ON public.conversations (account_id, updated_at);
-- CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_rep_acc_upd  ON public.reporting_events (account_id, updated_at);

-- Fase 0: de quando temos dados?
-- SELECT MIN(created_at) FROM auditoria_src.conversations;
-- SELECT MIN(created_at) FROM auditoria_src.csat_survey_responses;
-- SELECT name, MIN(created_at), COUNT(*) FROM auditoria_src.reporting_events GROUP BY name;
