#!/usr/bin/env bash
# Atualiza o servidor com a branch main do GitHub e aplica os SQLs (todos idempotentes).
# Uso: bash bin/atualizar.sh
# Requer: deploy key configurada (git@github.com:nadsonfdl/auditoria_suporte.git) e config/config.php preenchido.
set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"

echo "== $(date '+%F %T') atualizando $DIR"

# 1. Código: só avança se não houver conflito (nunca sobrescreve alteração local sem avisar)
# "git fetch origin" (sem refspec) para funcionar no git 1.8 do CentOS 7, que não atualiza origin/main com refspec explícito
git fetch --quiet origin
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERRO: há alterações locais não commitadas. Veja 'git status' antes de atualizar."
  exit 1
fi
ANTES=$(git rev-parse --short HEAD)
git merge --ff-only --quiet origin/main
DEPOIS=$(git rev-parse --short HEAD)
echo "código: $ANTES -> $DEPOIS"
git --no-pager log --oneline "$ANTES..$DEPOIS" || true

# 2. Sintaxe PHP (com o PHP do servidor)
for f in src/*.php bin/*.php public/*.php; do
  php -l "$f" >/dev/null || { echo "ERRO de sintaxe em $f"; exit 1; }
done
echo "php: sintaxe ok ($(php -r 'echo PHP_VERSION;'))"

# 3. Banco: aplica todos os sql/*.sql em ordem (são idempotentes)
DB_INFO=$(php -r '
  $c = require "config/config.php";
  preg_match("/host=([^;]+)/", $c["db_dsn"], $h); preg_match("/port=(\d+)/", $c["db_dsn"], $p); preg_match("/dbname=([^;]+)/", $c["db_dsn"], $d);
  echo (isset($h[1]) ? $h[1] : "127.0.0.1"), " ", (isset($p[1]) ? $p[1] : "5432"), " ", $d[1], " ", $c["db_user"], " ", $c["db_pass"];
')
read -r DBH DBP DBN DBU DBPASS <<< "$DB_INFO"
for s in sql/*.sql; do
  if ! OUT=$(PGPASSWORD="$DBPASS" psql -q -h "$DBH" -p "$DBP" -U "$DBU" -d "$DBN" -v ON_ERROR_STOP=1 -f "$s" 2>&1); then
    echo "$OUT"; echo "ERRO ao aplicar $s"; exit 1
  fi
  echo "$OUT" | grep -v NOTICE || true
  echo "sql: $s ok"
done

echo "== concluído"
