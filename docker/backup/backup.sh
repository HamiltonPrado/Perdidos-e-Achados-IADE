#!/bin/sh
# Contentor «backup»: copia a base de dados para ./backups de BACKUP_INTERVALO em BACKUP_INTERVALO
# segundos e guarda só as BACKUP_MANTER cópias mais recentes.
# Protege contra o que a replicação não protege: um DELETE errado (é replicado para todos os
# nós), dados corrompidos, volumes apagados.
#
# DB_HOST pode ter vários nós separados por vírgulas: usa o primeiro que responder.
set -eu

INTERVALO="${BACKUP_INTERVALO:-3600}"
MANTER="${BACKUP_MANTER:-24}"
DESTINO=/backups
DUMP="$(command -v mariadb-dump || command -v mysqldump)"

echo "[backup] a cada ${INTERVALO}s, mantém ${MANTER} cópias em ${DESTINO} (nós: ${DB_HOST})"

while true; do
    FICHEIRO="${DESTINO}/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql"
    FEITO=""

    for HOST in $(echo "${DB_HOST}" | tr ',' ' '); do
        # Escreve primeiro para .parcial: um backup interrompido nunca parece completo.
        if MYSQL_PWD="${DB_PASSWORD}" "${DUMP}" -h "${HOST}" -u "${DB_USER}" \
            --single-transaction --routines --no-tablespaces "${DB_NAME}" > "${FICHEIRO}.parcial" 2>/dev/null; then
            mv "${FICHEIRO}.parcial" "${FICHEIRO}"
            echo "[backup] $(date '+%F %T') criado $(basename "${FICHEIRO}") a partir do ${HOST}"
            FEITO="sim"
            break
        fi
        rm -f "${FICHEIRO}.parcial"
    done
    [ -n "${FEITO}" ] || echo "[backup] $(date '+%F %T') FALHOU: nenhum nó respondeu (${DB_HOST})" >&2

    # Apaga os mais antigos, deixando os MANTER mais recentes.
    ls -1t "${DESTINO}"/"${DB_NAME}"-*.sql 2>/dev/null | tail -n +"$((MANTER + 1))" | xargs -r rm -f

    sleep "${INTERVALO}"
done
