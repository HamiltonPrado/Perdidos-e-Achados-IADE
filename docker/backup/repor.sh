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

# Repõe num nó sincronizado do cluster; o Galera replica a reposição para os outros.
for NO in galera1 galera2 galera3; do
    if docker compose exec -T "${NO}" bash -c "tr -d '\\r' < /galera/saude.sh | bash" >/dev/null 2>&1; then
        docker compose exec -T "${NO}" sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -u root "$MARIADB_DATABASE"' < "${FICHEIRO}"
        echo "Reposto através do ${NO}."
        break
    fi
done
echo "Base de dados reposta a partir de ${FICHEIRO}."
