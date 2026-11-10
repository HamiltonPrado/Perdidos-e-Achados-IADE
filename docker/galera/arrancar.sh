#!/bin/bash
# Arranque de um nó Galera: decide se ENTRA num cluster que já existe ou se o FUNDA.
#
# Regra de ouro: só se funda um cluster quando nenhum outro nó está a funcionar (Primary).
# Fundar um segundo cluster ao lado do primeiro dividia os dados em dois (split-brain).
#
#  - instalação nova (volume vazio): o nó fundador (galera1) funda; os outros entram;
#  - nenhum nó está a funcionar mas um foi marcado pelo Galera como seguro (safe_to_bootstrap: 1,
#    o último a parar quando param um a um): esse funda e os outros entram;
#  - nenhum nó está a funcionar e nenhum está marcado (docker compose stop pára os 3 ao mesmo
#    tempo; fechar o Docker Desktop ou desligar o PC é um crash): cada nó escreve a sua última
#    transação no volume partilhado /galera-estado; quando os 3 responderam, o mais atualizado
#    funda (em empate, o primeiro de GALERA_NOS) e os outros entram e recebem o que lhes falta.
#    Se algum nó não responde, ninguém funda: podia ser precisamente o que tem os dados mais
#    recentes. Nesse caso: sh docker/galera/recuperar-cluster.sh;
#  - em qualquer outro caso: entra no cluster que está a funcionar.
set -euo pipefail

NO="${GALERA_NO:?Define GALERA_NO (ex.: galera1)}"
NOS="${GALERA_NOS:-galera1,galera2,galera3}"
FUNDADOR="${GALERA_FUNDADOR:-galera1}"
DADOS=/var/lib/mysql
ESTADO=/galera-estado                       # volume partilhado pelos 3 nós
ESPERA_VOTOS="${GALERA_ESPERA_VOTOS:-90}"   # segundos à espera de que todos os nós respondam
VALIDADE_VOTO=20                            # um voto mais antigo é de um nó que já não está a votar

log() { echo "[galera:${NO}] $*"; }

# Windows: os ficheiros montados chegam com permissões 777 (o MariaDB IGNORA configurações que
# todos podem alterar) e às vezes com fins de linha CRLF. Por isso a configuração e os scripts
# de criação são copiados para dentro do contentor, sem «\r» e com permissões normais.
tr -d '\r' < /galera/galera.cnf > /etc/mysql/conf.d/galera.cnf
chmod 644 /etc/mysql/conf.d/galera.cnf
if [ "${NO}" = "${FUNDADOR}" ]; then
    for SCRIPT in /galera/initdb/*.sh; do
        DESTINO="/docker-entrypoint-initdb.d/$(basename "${SCRIPT}")"
        tr -d '\r' < "${SCRIPT}" > "${DESTINO}"
        # Executável: o MariaDB corre-o à parte. Sem isto, «carregava-o» no próprio shell e o
        # set -eu do script fazia rebentar o arranque.
        chmod 755 "${DESTINO}"
    done
fi

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

NULO=00000000-0000-0000-0000-000000000000

# Posição deste nó: «história:transação» (uuid:seqno). Cada vez que um cluster é FUNDADO de
# raiz nasce uma história nova; só faz sentido comparar transações da mesma história.
# Numa paragem ordenada a posição está no grastate.dat; depois de um crash (ou de uma paragem
# que o Docker teve de forçar) o seqno fica -1 e é preciso perguntá-la ao InnoDB.
posicao_local() {
    local uuid seqno posicao
    uuid="$(sed -n 's/^uuid: *//p' "${DADOS}/grastate.dat" 2>/dev/null || true)"
    seqno="$(sed -n 's/^seqno: *//p' "${DADOS}/grastate.dat" 2>/dev/null || true)"
    if [[ "${uuid}" =~ ^[0-9a-f-]{36}$ ]] && [[ "${seqno}" =~ ^[0-9]+$ ]]; then
        echo "${uuid}:${seqno}"
        return
    fi
    rm -f /tmp/recover.log
    mariadbd --user=mysql --wsrep-recover --wsrep-cluster-address=gcomm:// \
             --log-error=/tmp/recover.log >/dev/null 2>&1 || true
    posicao="$(grep -o 'Recovered position: .*' /tmp/recover.log 2>/dev/null | tail -n 1 | sed 's/^Recovered position: *//' || true)"
    [[ "${posicao}" =~ ^[0-9a-f-]{36}:-?[0-9]+$ ]] && echo "${posicao}" || echo "${NULO}:-1"
}

# Todos os nós votam com a sua posição; devolve 0 se ESTE nó deve refundar o cluster.
# Os que não fundam esperam que o fundador fique pronto e devolvem 1 (entram no cluster).
decidir_fundador() {
    local limite agora par valor idade uuid seq historia diferentes melhor melhor_seqno faltam
    mkdir -p "${ESTADO}"
    log "nenhum cluster ativo e nenhum nó marcado como seguro. Posição deste nó: ${POSICAO}. A comparar com os outros…"

    limite=$(( $(date +%s) + ESPERA_VOTOS ))
    while [ "$(date +%s)" -lt "${limite}" ]; do
        echo "${POSICAO}" > "${ESTADO}/${NO}"   # renova o voto (a data do ficheiro conta)
        cluster_ativo && return 1

        agora="$(date +%s)"; melhor=""; melhor_seqno=-2; faltam=""; historia=""; diferentes=""
        for par in ${NOS//,/ }; do
            idade=$(( agora - $(stat -c %Y "${ESTADO}/${par}" 2>/dev/null || echo 0) ))
            valor="$(cat "${ESTADO}/${par}" 2>/dev/null || true)"
            if [ "${idade}" -gt "${VALIDADE_VOTO}" ] || ! [[ "${valor}" =~ ^[0-9a-f-]{36}:-?[0-9]+$ ]]; then
                faltam="${faltam} ${par}"
                continue
            fi
            uuid="${valor%%:*}"; seq="${valor##*:}"
            # Um nó sem história (a meio de uma cópia, por ex.) não conta para a comparação.
            if [ "${uuid}" != "${NULO}" ] && [ "${seq}" -ge 0 ]; then
                if [ -z "${historia}" ]; then historia="${uuid}"
                elif [ "${uuid}" != "${historia}" ]; then diferentes=1
                fi
            fi
            # Em empate fica o primeiro da lista (o fundador): todos chegam à mesma escolha.
            if [ "${seq}" -gt "${melhor_seqno}" ]; then melhor="${par}"; melhor_seqno="${seq}"; fi
        done

        if [ -z "${faltam}" ] && [ -n "${diferentes}" ]; then
            log "os nós têm histórias diferentes (o cluster foi fundado de raiz mais de uma vez): não sei comparar. Usa: sh docker/galera/recuperar-cluster.sh"
            return 1
        fi

        if [ -z "${faltam}" ]; then
            if [ "${melhor}" = "${NO}" ]; then
                log "todos os nós responderam e este é o mais atualizado (${POSICAO}): a REFUNDAR o cluster."
                sed -i 's/^safe_to_bootstrap: *0/safe_to_bootstrap: 1/' "${DADOS}/grastate.dat"
                return 0
            fi
            log "todos os nós responderam: o ${melhor} é o mais atualizado (transação ${melhor_seqno}). À espera de que ele refunde o cluster…"
            while [ "$(date +%s)" -lt "${limite}" ]; do
                echo "${POSICAO}" > "${ESTADO}/${NO}"
                cluster_ativo && return 1
                sleep 2
            done
            log "o ${melhor} não refundou o cluster a tempo. Vou tentar de novo."
            return 1
        fi
        sleep 2
    done

    log "sem resposta de:${faltam}. Ninguém refunda sem saber a posição de todos os nós. Vou tentar de novo; se um nó não puder voltar: sh docker/galera/recuperar-cluster.sh"
    return 1
}

ARGS=(
    --wsrep-node-name="${NO}"
    --wsrep-node-address="${NO}"
    --wsrep-cluster-address="gcomm://${NOS}"
    --wsrep-sst-auth="mariabackup:${GALERA_SST_PASSWORD:?Define GALERA_SST_PASSWORD}"
)

# Sem esta posição o MariaDB do Docker arrancava depois de um crash como se não tivesse história
# (00000000…:-1): o fundador criava uma história nova e os outros tinham de receber uma cópia
# completa (SST) em vez de só o que lhes falta (IST). Fora do Docker é o mysqld_safe que faz isto.
POSICAO=""
if [ -d "${DADOS}/mysql" ]; then
    POSICAO="$(posicao_local)"
    if ! grep -qE '^seqno: *[0-9]+' "${DADOS}/grastate.dat" 2>/dev/null && [ "${POSICAO%%:*}" != "${NULO}" ]; then
        log "posição recuperada do InnoDB: ${POSICAO}"
        ARGS+=(--wsrep-start-position="${POSICAO}")
    fi
fi

if cluster_ativo; then
    # Depois de um crash (SIGKILL) o tc.log pode ficar inconsistente e o MariaDB recusa arrancar
    # («Can't init tc log»), ficando em ciclo de reinícios. Um nó que entra num cluster ativo
    # recebe os dados dos outros (IST/SST), por isso pode descartá-lo com segurança.
    if [ -f "${DADOS}/tc.log" ]; then
        log "a entrar num cluster ativo: descarto o tc.log local (os dados vêm do cluster)."
        rm -f "${DADOS}/tc.log"
    fi
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
elif decidir_fundador; then
    ARGS+=(--wsrep-new-cluster)
fi

# Sai da votação: o voto só serve enquanto o nó está à espera (um voto esquecido podia
# enganar os outros no próximo arranque).
rm -f "${ESTADO}/${NO}" 2>/dev/null || true

exec docker-entrypoint.sh mariadbd "${ARGS[@]}" "$@"
