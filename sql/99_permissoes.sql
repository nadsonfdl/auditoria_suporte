-- =====================================================================
-- 99 — Permissões do Metabase (roda por último; refaz grants de views recriadas)
-- Se o usuário metabase_auditoria ainda não existir, não faz nada.
-- =====================================================================
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'metabase_auditoria') THEN
    GRANT USAGE ON SCHEMA espelho, auditoria TO metabase_auditoria;
    GRANT SELECT ON ALL TABLES IN SCHEMA espelho, auditoria TO metabase_auditoria;
    GRANT EXECUTE ON FUNCTION auditoria.cfg(text) TO metabase_auditoria;
    GRANT UPDATE (status, classificacao, observacao, supervisor, avaliado_em, retorno_posvendas, tratado_em)
      ON auditoria.csat_auditoria TO metabase_auditoria;
  END IF;
END $$;
