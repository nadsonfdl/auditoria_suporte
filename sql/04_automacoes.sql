-- =====================================================================
-- 04 — Automações: configurações, alertas, resumo semanal, pós-vendas,
--      problemas recorrentes e metas. Idempotente.
-- Todas as chaves ficam em auditoria.config e são editadas na aba
-- "Configurações" do painel. Segredos (senha SMTP, tokens) são gravados
-- aqui e nunca são exibidos de volta na tela.
-- =====================================================================

INSERT INTO auditoria.config (chave, valor) VALUES
  -- geral
  ('painel_url',              'https://appiasuporte.igut.med.br/auditoria_suporte/painel.php'),
  -- indicador da Guta
  ('guta_ativo',              '1'),
  -- metas (semáforo)
  ('metas_ativo',             '1'),
  ('meta_csat',               '4.5'),
  ('meta_satisfeitos_pct',    '90'),
  ('meta_1a_resposta_min',    '15'),
  ('meta_sem_reabrir_pct',    '90'),
  ('meta_notas_baixas_pct',   '5'),
  -- alerta imediato de nota baixa
  ('alerta_ativo',            '0'),
  ('alerta_nota_max',         '1'),
  ('alerta_email',            '1'),
  ('alerta_whatsapp',         '0'),
  ('alerta_emails',           ''),
  ('alerta_whatsapps',        ''),
  -- resumo semanal
  ('resumo_ativo',            '0'),
  ('resumo_dia',              '1'),      -- 1 = segunda ... 7 = domingo
  ('resumo_hora',             '8'),
  ('resumo_emails',           ''),
  ('resumo_whatsapp',         '0'),
  ('resumo_whatsapps',        ''),
  -- envio ao pós-vendas
  ('posvendas_emails',        ''),
  ('posvendas_whatsapp',      '0'),
  ('posvendas_whatsapps',     ''),
  -- problemas recorrentes
  ('temas_ativo',             '1'),
  ('temas_janela_dias',       '30'),
  ('github_repo',             ''),
  ('github_token',            ''),
  -- e-mail (SMTP)
  ('smtp_host',               ''),
  ('smtp_porta',              '587'),
  ('smtp_seguranca',          'tls'),     -- tls | ssl | nenhuma
  ('smtp_usuario',            ''),
  ('smtp_senha',              ''),
  ('smtp_de_email',           ''),
  ('smtp_de_nome',            'Auditoria do Suporte'),
  -- WhatsApp
  ('wa_provedor',             'italk'),   -- italk | evolution | zapi | webhook
  ('wa_italk_inbox_id',       ''),
  ('wa_evolution_url',        ''),
  ('wa_evolution_instancia',  ''),
  ('wa_evolution_apikey',     ''),
  ('wa_zapi_instancia',       ''),
  ('wa_zapi_token',           ''),
  ('wa_zapi_client_token',    ''),
  ('wa_webhook_url',          '')
ON CONFLICT (chave) DO NOTHING;

-- Alertas e pós-vendas na fila
ALTER TABLE auditoria.csat_auditoria
  ADD COLUMN IF NOT EXISTS alerta_enviado_em timestamptz,
  ADD COLUMN IF NOT EXISTS tratado_por       text,
  ADD COLUMN IF NOT EXISTS tema_id           int;

-- Log de tudo que foi enviado (e-mail, WhatsApp, GitHub)
CREATE TABLE IF NOT EXISTS auditoria.envios (
  id bigserial PRIMARY KEY,
  tipo text,            -- alerta | resumo | posvendas | teste | issue
  canal text,           -- email | whatsapp | github
  destino text,
  assunto text,
  ok boolean,
  erro text,
  criado_em timestamptz DEFAULT now()
);
CREATE INDEX IF NOT EXISTS ix_envios_criado ON auditoria.envios (criado_em DESC);

-- Problemas recorrentes de produto agrupados pela IA
CREATE TABLE IF NOT EXISTS auditoria.temas (
  id serial PRIMARY KEY,
  titulo text NOT NULL,
  descricao text,
  qtd int DEFAULT 0,
  primeiro_em timestamptz,
  ultimo_em timestamptz,
  status text NOT NULL DEFAULT 'aberto' CHECK (status IN ('aberto', 'issue_aberta', 'resolvido', 'ignorado')),
  issue_url text,
  atualizado_em timestamptz DEFAULT now()
);

-- Marcas de execução do agendador
CREATE TABLE IF NOT EXISTS auditoria.agendador (chave text PRIMARY KEY, valor text, atualizado_em timestamptz DEFAULT now());

-- Fila com campos do pós-vendas (colunas novas no fim)
DROP VIEW IF EXISTS auditoria.vw_csat_fila;
CREATE VIEW auditoria.vw_csat_fila AS
SELECT q.id, q.semana_ref, q.status, q.rating AS nota, q.feedback_cliente,
       q.respondido_em AT TIME ZONE 'America/Sao_Paulo' AS respondido_em,
       a.name AS atendente, ct.name AS cliente, ct.phone_number AS telefone, ct.email AS email_cliente,
       auditoria.cfg('italk_url') || '/app/accounts/3/conversations/' || q.conversation_id AS link_conversa,
       q.classificacao, q.observacao, q.supervisor, q.avaliado_em, q.enviado_em, q.retorno_posvendas,
       q.ia_motivo, q.ia_categoria, q.ia_responsavel, q.ia_gravidade, q.ia_resumo,
       q.ia_evidencias, q.ia_sugestao, q.ia_confianca, q.ia_analisado_em,
       q.tratado_em, q.tratado_por, q.alerta_enviado_em, q.tema_id, t.titulo AS tema
FROM auditoria.csat_auditoria q
LEFT JOIN espelho.agents   a  ON a.id  = q.atendente_id
LEFT JOIN espelho.contacts ct ON ct.id = q.contato_id
LEFT JOIN auditoria.temas  t  ON t.id  = q.tema_id;
