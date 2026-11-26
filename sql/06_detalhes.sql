-- =====================================================================
-- 06 — Detalhamento da auditoria por IA e validação humana. Idempotente.
-- A validação humana corrige o veredito da IA no indicador e mede a precisão da IA.
-- =====================================================================

CREATE TABLE IF NOT EXISTS auditoria.qa_validacao (
  conversation_id   int PRIMARY KEY,
  usuario           text,
  ia_resolvido      text,      -- o que a IA disse no momento da validação
  humano_resolvido  text NOT NULL CHECK (humano_resolvido IN ('sim', 'parcial', 'nao')),
  comentario        text,
  criado_em         timestamptz DEFAULT now()
);

-- Base do painel de qualidade: "resolvido" passa a ser o veredito final (humano, se houver; senão IA)
DROP VIEW IF EXISTS auditoria.vw_painel_qa;
CREATE VIEW auditoria.vw_painel_qa AS
SELECT q.conversation_id, q.analisado_em, q.modelo,
       q.msgs_cliente, q.msgs_atendente, q.msgs_bot, q.teve_humano, q.maior_espera_min, q.cliente_sem_resposta, q.duracao_min,
       COALESCE(v.humano_resolvido, q.resolvido) AS resolvido,
       q.resolvido                               AS resolvido_ia,
       v.humano_resolvido, v.usuario AS validado_por, v.criado_em AS validado_em, v.comentario AS validacao_comentario,
       q.nota_qa, q.criterios, q.falhas, q.sentimento_inicio, q.sentimento_fim, q.risco, q.motivo_contato, q.assunto,
       q.resumo, q.categoria_falha, q.json,
       c.dia, c.semana, c.assignee_id, c.atendente, c.team_id, c.inbox_id, c.caixa_entrada,
       c.criada_em, c.primeira_resposta_min,
       s.nota AS nota_csat
FROM auditoria.qa_conversas q
JOIN auditoria.vw_painel_conversas c ON c.conversa = q.conversation_id
LEFT JOIN auditoria.qa_validacao v ON v.conversation_id = q.conversation_id
LEFT JOIN LATERAL (SELECT nota FROM auditoria.vw_painel_csat x WHERE x.conversa = q.conversation_id ORDER BY x.respondido_em DESC LIMIT 1) s ON true
WHERE q.resolvido IS NOT NULL;
