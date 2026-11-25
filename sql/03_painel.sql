-- =====================================================================
-- 03 — Views do painel de BI (public/painel.php). Idempotente.
-- =====================================================================

-- 1 linha por conversa, com tudo que o painel filtra/agrega
DROP VIEW IF EXISTS auditoria.vw_painel_conversas CASCADE;  -- recriadas por 03 e 05
CREATE VIEW auditoria.vw_painel_conversas AS
SELECT c.id                                                        AS conversa,
       c.created_at AT TIME ZONE 'America/Sao_Paulo'               AS criada_em,
       (c.created_at AT TIME ZONE 'America/Sao_Paulo')::date       AS dia,
       date_trunc('week', c.created_at AT TIME ZONE 'America/Sao_Paulo')::date AS semana,
       extract(isodow FROM c.created_at AT TIME ZONE 'America/Sao_Paulo')::int AS dia_semana,  -- 1=seg .. 7=dom
       extract(hour   FROM c.created_at AT TIME ZONE 'America/Sao_Paulo')::int AS hora,
       c.status,
       c.assignee_id, a.name AS atendente,
       c.team_id,     t.name AS time,
       c.inbox_id,    i.name AS caixa_entrada,
       (c.status = 'resolved')                                     AS resolvida,
       COALESCE(s.qtd_reaberturas, 0)                              AS reaberturas,
       (c.created_at >= auditoria.cfg('rastreio_inicio')::date)    AS rastreada,
       (c.status = 'resolved' AND COALESCE(s.qtd_reaberturas, 0) = 0) AS resolvida_sem_reabrir,
       CASE WHEN c.first_reply_created_at >= c.created_at
            THEN extract(epoch FROM c.first_reply_created_at - c.created_at) / 60.0 END AS primeira_resposta_min
FROM espelho.conversations c
LEFT JOIN espelho.agents  a ON a.id = c.assignee_id
LEFT JOIN espelho.teams   t ON t.id = c.team_id
LEFT JOIN espelho.inboxes i ON i.id = c.inbox_id
LEFT JOIN auditoria.vw_status_conversa s ON s.conversation_id = c.id;

-- 1 linha por resposta de CSAT, já com a análise da IA quando for nota baixa
DROP VIEW IF EXISTS auditoria.vw_painel_csat CASCADE;
CREATE VIEW auditoria.vw_painel_csat AS
SELECT s.id, s.conversation_id AS conversa, s.rating AS nota, s.feedback_message AS comentario,
       s.created_at AT TIME ZONE 'America/Sao_Paulo'                           AS respondido_em,
       (s.created_at AT TIME ZONE 'America/Sao_Paulo')::date                   AS dia,
       date_trunc('week', s.created_at AT TIME ZONE 'America/Sao_Paulo')::date AS semana,
       s.assigned_agent_id AS assignee_id, a.name AS atendente,
       c.team_id, c.inbox_id,
       q.id AS auditoria_id, q.status AS status_auditoria,
       q.ia_categoria, q.ia_responsavel, q.ia_gravidade, q.ia_motivo
FROM espelho.csat s
LEFT JOIN espelho.agents a ON a.id = s.assigned_agent_id
LEFT JOIN espelho.conversations c ON c.id = s.conversation_id
LEFT JOIN auditoria.csat_auditoria q ON q.csat_id = s.id;

-- Índices que ajudam os filtros por período
CREATE INDEX IF NOT EXISTS ix_csat_created ON espelho.csat (created_at);
CREATE INDEX IF NOT EXISTS ix_conv_assignee ON espelho.conversations (assignee_id);
