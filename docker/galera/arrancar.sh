#!/bin/bash
# Arranque de um nó Galera: decide se ENTRA num cluster que já existe ou se o FUNDA.
#
# Regra de ouro: só se funda um cluster quando nenhum outro nó está a funcionar (Primary).
# Fundar um segundo cluster ao lado do primeiro dividia os dados em dois (split-brain).
#
#  - instalação nova (volume vazio): o nó fundador (galera1) funda; os outros entram;
#  - os 3 nós pararam de forma ordenada (ex.: docker compose stop): funda o nó que o Galera
#    marcou como seguro (safe_to_bootstrap: 1, o último a parar e por isso o mais atualizado);
#    os outros reiniciam até ele estar pronto e depois entram;
#  - todos os nós caíram de repente: o pc.recovery (galera.cnf) refaz o cluster sozinho
#    quando eles voltam;
#  - em qualquer outro caso: entra no cluster que está a funcionar.
set -euo pipefail

NO="${GALERA_NO:?Define GALERA_NO (ex.: galera1)}"
NOS="${GALERA_NOS:-galera1,galera2,galera3}"
FUNDADOR="${GALERA_FUNDADOR:-galera1}"
DADOS=/var/lib/mysql

log() { echo "[galera:${NO}] $*"; }

# Há algum outro nó num cluster a funcionar?
cluster_ativo() {
    local par estado
    for par in ${NOS//,/ }; do
        [ "${par}" = "${NO}" ] && continue
        estado="$(MYSQL_PWD="${MARIADB_ROOT_PASSWORD}" mariadb -h "${par}" -u root --connect-timeout=2 -N \
                  -e "SHOW STATUS LIKE 'wsrep_cluster_status'" 2>/dev/null | cut -f2 || true)"
        if [ "${estado}" = "Primary" ]; then
            log "o cluster está ativo em ${par}: vou entrar nele."
            return 0
        fi
    done
    return 1
}

ARGS=(
    --wsrep-node-name="${NO}"
    --wsrep-node-address="${NO}"
    --wsrep-cluster-address="gcomm://${NOS}"
    --wsrep-sst-auth="mariabackup:${GALERA_SST_PASSWORD:?Define GALERA_SST_PASSWORD}"
)

if cluster_ativo; then
    :
elif [ ! -d "${DADOS}/mysql" ]; then
    if [ "${NO}" = "${FUNDADOR}" ]; then
        log "instalação nova e nenhum cluster ativo: a FUNDAR o cluster."
        ARGS+=(--wsrep-new-cluster)
    else
        log "instalação nova: vou entrar no cluster (fica a tentar até o ${FUNDADOR} o fundar)."
    fi
elif grep -q '^safe_to_bootstrap: *1' "${DADOS}/grastate.dat" 2>/dev/null; then
    log "nenhum cluster ativo e este nó foi o último a parar (safe_to_bootstrap): a REFUNDAR o cluster."
    ARGS+=(--wsrep-new-cluster)
else
    log "nenhum cluster ativo: à espera do nó mais atualizado (o que tem safe_to_bootstrap) para entrar."
fi

exec docker-entrypoint.sh mariadbd "${ARGS[@]}" "$@"
