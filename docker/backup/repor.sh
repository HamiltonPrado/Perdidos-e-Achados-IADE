#!/bin/sh
# Repõe a base de dados a partir de um backup de ./backups.
#
# Uso (na raiz do projeto):
#   sh docker/backup/repor.sh                          → repõe o backup mais recente
#   sh docker/backup/repor.sh perdidos_achados-20261108-153000.sql
#
# ATENÇÃO: substitui os dados atuais da base de dados pelos do backup.
set -eu

cd "$(dirname "$0")/../.."

FICHEIRO="${1:-$(ls -1t backups/*.sql 2>/dev/null | head -n 1)}"
FICHEIRO="backups/$(basename "${FICHEIRO}")"

if [ ! -f "${FICHEIRO}" ]; then
    echo "Não encontrei o backup ${FICHEIRO}." >&2
    exit 1
fi

printf 'Repor %s? Os dados atuais serão substituídos. [s/N] ' "${FICHEIRO}"
read -r RESPOSTA
[ "${RESPOSTA}" = "s" ] || { echo "Cancelado."; exit 0; }

docker compose exec -T db1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root "$MYSQL_DATABASE"' < "${FICHEIRO}"
echo "Base de dados reposta a partir de ${FICHEIRO}."
