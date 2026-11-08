#!/bin/sh
# Contentor «backup»: copia a base de dados para ./backups de BACKUP_INTERVALO em BACKUP_INTERVALO
# segundos e guarda só as BACKUP_MANTER cópias mais recentes.
# Protege contra o que a replicação não protege: um DELETE errado, dados corrompidos, um volume apagado.
set -eu

INTERVALO="${BACKUP_INTERVALO:-3600}"
MANTER="${BACKUP_MANTER:-24}"
DESTINO=/backups

echo "[backup] a cada ${INTERVALO}s, mantém ${MANTER} cópias em ${DESTINO}"

while true; do
    FICHEIRO="${DESTINO}/${DB_NAME}-$(date +%Y%m%d-%H%M%S).sql"

    # Escreve primeiro para .parcial: um backup interrompido nunca parece completo.
    if MYSQL_PWD="${DB_PASSWORD}" mysqldump -h "${DB_HOST}" -u "${DB_USER}" \
        --single-transaction --routines --no-tablespaces "${DB_NAME}" > "${FICHEIRO}.parcial"; then
        mv "${FICHEIRO}.parcial" "${FICHEIRO}"
        echo "[backup] $(date '+%F %T') criado $(basename "${FICHEIRO}")"
    else
        rm -f "${FICHEIRO}.parcial"
        echo "[backup] $(date '+%F %T') FALHOU (a base de dados está em baixo?)" >&2
    fi

    # Apaga os mais antigos, deixando os MANTER mais recentes.
    ls -1t "${DESTINO}"/"${DB_NAME}"-*.sql 2>/dev/null | tail -n +"$((MANTER + 1))" | xargs -r rm -f

    sleep "${INTERVALO}"
done
