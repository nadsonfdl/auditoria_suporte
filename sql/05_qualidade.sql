-- =====================================================================
-- 05 — Auditoria de qualidade por IA em TODAS as conversas (com ou sem nota)
-- Idempotente.
-- =====================================================================

INSERT INTO auditoria.config (chave, valor) VALUES
  ('qa_ativo',       '1'),
  ('qa_lote',        '15'),     -- conversas por execução do agendador (a cada 5 min)
  ('qa_amostra_pct', '100'),    -- % das conversas encerradas que a IA audita
  ('qa_dias',        '30'),     -- analisa conversas criadas nos últimos N dias
  ('qa_para_fila',   '1'),      -- conversas com risco alto e sem nota entram na fila de auditoria
  ('meta_resolucao_real_pct', '80'),  -- meta de resolução real (IA)
  ('meta_qa',        '80')       -- meta da nota de qualidade (IA)
ON CONFLICT (chave) DO NOTHING;

CREATE TABLE IF NOT EXISTS auditoria.qa_conversas (
  conversation_id     int PRIMARY KEY,
  analisado_em        timestamptz DEFAULT now(),
  modelo              text,
  -- métricas calculadas das mensagens (sem IA)
  msgs_cliente        int,
  msgs_atendente      int,
  msgs_bot            int,
  teve_humano         boolean,
  maior_espera_min    numeric(10,1),   -- maior tempo que o cliente esperou por uma resposta
  cliente_sem_resposta boolean,        -- última mensagem foi do cliente e ficou sem resposta
  duracao_min         numeric(10,1),
  -- leitura da IA
  resolvido           text,            -- sim | parcial | nao | incerto
  nota_qa             int,             -- 0 a 100
  criterios           jsonb,           -- {"entendimento":0-10, ...}
  falhas              jsonb,           -- [{"criterio":"...","descricao":"...","trecho":"..."}]
  sentimento_inicio   text,            -- positivo | neutro | negativo
  sentimento_fim      text,
  risco               text,            -- baixo | medio | alto (risco de insatisfação/cancelamento)
  motivo_contato      text,            -- categoria do pedido do cliente
  assunto             text,            -- assunto em poucas palavras
  resumo              text,
  categoria_falha     text,            -- mesma lista de categorias da auditoria CSAT
  json                jsonb,
  erro                text,
  tentativas          int NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_qa_analisado ON auditoria.qa_conversas (analisado_em);

-- A fila de auditoria passa a aceitar casos vindos da IA (sem nota do cliente)
ALTER TABLE auditoria.csat_auditoria ALTER COLUMN csat_id DROP NOT NULL;
ALTER TABLE auditoria.csat_auditoria ADD COLUMN IF NOT EXISTS origem text NOT NULL DEFAULT 'csat';  -- csat | ia
CREATE UNIQUE INDEX IF NOT EXISTS ux_csat_aud_conv_ia ON auditoria.csat_auditoria (conversation_id) WHERE origem = 'ia';

-- Base do painel de qualidade
DROP VIEW IF EXISTS auditoria.vw_painel_qa;
CREATE VIEW auditoria.vw_painel_qa AS
SELECT q.*, c.dia, c.semana, c.assignee_id, c.atendente, c.team_id, c.inbox_id, c.caixa_entrada,
       c.criada_em, c.primeira_resposta_min,
       s.nota AS nota_csat
FROM auditoria.qa_conversas q
JOIN auditoria.vw_painel_conversas c ON c.conversa = q.conversation_id
LEFT JOIN LATERAL (SELECT nota FROM auditoria.vw_painel_csat x WHERE x.conversa = q.conversation_id ORDER BY x.respondido_em DESC LIMIT 1) s ON true
WHERE q.resolvido IS NOT NULL;

-- Fila com origem (colunas novas no fim)
DROP VIEW IF EXISTS auditoria.vw_csat_fila;
CREATE VIEW auditoria.vw_csat_fila AS
SELECT q.id, q.semana_ref, q.status, q.rating AS nota, q.feedback_cliente,
       q.respondido_em AT TIME ZONE 'America/Sao_Paulo' AS respondido_em,
       a.name AS atendente, ct.name AS cliente, ct.phone_number AS telefone, ct.email AS email_cliente,
       auditoria.cfg('italk_url') || '/app/accounts/3/conversations/' || q.conversation_id AS link_conversa,
       q.classificacao, q.observacao, q.supervisor, q.avaliado_em, q.enviado_em, q.retorno_posvendas,
       q.ia_motivo, q.ia_categoria, q.ia_responsavel, q.ia_gravidade, q.ia_resumo,
       q.ia_evidencias, q.ia_sugestao, q.ia_confianca, q.ia_analisado_em,
       q.tratado_em, q.tratado_por, q.alerta_enviado_em, q.tema_id, t.titulo AS tema,
       q.origem, q.conversation_id
FROM auditoria.csat_auditoria q
LEFT JOIN espelho.agents   a  ON a.id  = q.atendente_id
LEFT JOIN espelho.contacts ct ON ct.id = q.contato_id
LEFT JOIN auditoria.temas  t  ON t.id  = q.tema_id;

-- vw_painel_csat: só notas reais de clientes (exclui casos de origem IA)
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

-- vw_painel_qa depende de vw_painel_csat (recriada acima pelo CASCADE)
CREATE OR REPLACE VIEW auditoria.vw_painel_qa AS
SELECT q.*, c.dia, c.semana, c.assignee_id, c.atendente, c.team_id, c.inbox_id, c.caixa_entrada,
       c.criada_em, c.primeira_resposta_min,
       s.nota AS nota_csat
FROM auditoria.qa_conversas q
JOIN auditoria.vw_painel_conversas c ON c.conversa = q.conversation_id
LEFT JOIN LATERAL (SELECT nota FROM auditoria.vw_painel_csat x WHERE x.conversa = q.conversation_id ORDER BY x.respondido_em DESC LIMIT 1) s ON true
WHERE q.resolvido IS NOT NULL;
