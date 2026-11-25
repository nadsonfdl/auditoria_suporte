-- =====================================================================
-- 02 — Análise de IA das notas baixas de CSAT (idempotente)
--   psql -d auditoria_suporte -f sql/02_ia.sql
-- =====================================================================

ALTER TABLE auditoria.csat_auditoria
  ADD COLUMN IF NOT EXISTS ia_motivo        text,
  ADD COLUMN IF NOT EXISTS ia_categoria     text,
  ADD COLUMN IF NOT EXISTS ia_responsavel   text,
  ADD COLUMN IF NOT EXISTS ia_resumo        text,
  ADD COLUMN IF NOT EXISTS ia_evidencias    text,
  ADD COLUMN IF NOT EXISTS ia_sugestao      text,
  ADD COLUMN IF NOT EXISTS ia_gravidade     text,
  ADD COLUMN IF NOT EXISTS ia_confianca     numeric(3,2),
  ADD COLUMN IF NOT EXISTS ia_json          jsonb,
  ADD COLUMN IF NOT EXISTS ia_modelo        text,
  ADD COLUMN IF NOT EXISTS ia_analisado_em  timestamptz,
  ADD COLUMN IF NOT EXISTS ia_tentativas    int NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS ia_erro          text;

CREATE INDEX IF NOT EXISTS ix_csat_aud_ia_pend
  ON auditoria.csat_auditoria (respondido_em) WHERE ia_analisado_em IS NULL;

-- Fila do supervisor agora traz a análise da IA
DROP VIEW IF EXISTS auditoria.vw_csat_fila;
CREATE VIEW auditoria.vw_csat_fila AS
SELECT q.id, q.semana_ref, q.status, q.rating AS nota, q.feedback_cliente,
       q.respondido_em AT TIME ZONE 'America/Sao_Paulo' AS respondido_em,
       a.name AS atendente, ct.name AS cliente, ct.phone_number AS telefone, ct.email AS email_cliente,
       auditoria.cfg('italk_url') || '/app/accounts/3/conversations/' || q.conversation_id AS link_conversa,
       q.classificacao, q.observacao, q.supervisor, q.avaliado_em, q.enviado_em, q.retorno_posvendas,
       q.ia_motivo, q.ia_categoria, q.ia_responsavel, q.ia_gravidade, q.ia_resumo,
       q.ia_evidencias, q.ia_sugestao, q.ia_confianca, q.ia_analisado_em
FROM auditoria.csat_auditoria q
LEFT JOIN espelho.agents   a  ON a.id  = q.atendente_id
LEFT JOIN espelho.contacts ct ON ct.id = q.contato_id;

-- Motivos das notas baixas (para gráfico no Metabase)
CREATE OR REPLACE VIEW auditoria.vw_csat_motivos_ia AS
SELECT q.semana_ref, q.ia_categoria AS categoria, q.ia_responsavel AS responsavel,
       q.ia_gravidade AS gravidade, a.name AS atendente, count(*) AS qtd
FROM auditoria.csat_auditoria q
LEFT JOIN espelho.agents a ON a.id = q.atendente_id
WHERE q.ia_analisado_em IS NOT NULL
GROUP BY 1, 2, 3, 4, 5;
