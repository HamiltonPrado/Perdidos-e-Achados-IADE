#!/bin/sh
# Recupera o cluster Galera depois de um CRASH TOTAL (os 3 nós caíram sem aviso ao mesmo tempo).
#
# Sinal de que é preciso: os nós arrancam mas ficam «health: starting» para sempre e os logs
# mostram «Non-primary view». O Galera não sabe qual nó tem os dados mais recentes e espera.
#
# O que faz:
#  1. para os 3 nós;
#  2. pergunta a cada um a última transação que gravou (mariadbd --wsrep-recover);
#  3. marca o mais avançado como seguro para fundar (safe_to_bootstrap: 1);
#  4. arranca os 3: o mais avançado refunda o cluster e os outros entram e recebem o que falta.
# Assim nenhuma transação confirmada se perde.
#
# Uso (na raiz do projeto):  sh docker/galera/recuperar-cluster.sh
set -eu
cd "$(dirname "$0")/../.."

NOS="galera1 galera2 galera3"

echo "A parar os nós…"
docker compose stop ${NOS} >/dev/null 2>&1

MELHOR=""; MELHOR_SEQNO=-2
for NO in ${NOS}; do
    POSICAO="$(docker compose run --rm --no-deps -T --entrypoint bash "${NO}" -c \
        'mariadbd --user=mysql --wsrep-recover --wsrep-cluster-address=gcomm:// --log-error=/tmp/recover.log >/dev/null 2>&1;
         grep -o "Recovered position: .*" /tmp/recover.log | tail -n 1 | sed "s/.*://"' 2>/dev/null | tr -d '\r' | tail -n 1)"
    SEQNO="${POSICAO:--1}"
    echo "  ${NO}: última transação ${SEQNO}"
    if [ "${SEQNO}" -gt "${MELHOR_SEQNO}" ]; then MELHOR="${NO}"; MELHOR_SEQNO="${SEQNO}"; fi
done

if [ -z "${MELHOR}" ] || [ "${MELHOR_SEQNO}" -lt 0 ]; then
    echo "Não consegui ler a posição de nenhum nó. Vê os logs: docker compose logs galera1" >&2
    exit 1
fi

echo "O mais avançado é o ${MELHOR} (${MELHOR_SEQNO}): vai refundar o cluster."
docker compose run --rm --no-deps -T --entrypoint bash "${MELHOR}" -c \
    'sed -i "s/^safe_to_bootstrap: *0/safe_to_bootstrap: 1/" /var/lib/mysql/grastate.dat' >/dev/null 2>&1

docker compose start "${MELHOR}" >/dev/null
until docker compose exec -T "${MELHOR}" bash /galera/saude.sh >/dev/null 2>&1; do sleep 3; done
echo "${MELHOR} pronto. A juntar os outros nós…"
docker compose start ${NOS} >/dev/null
echo "Feito. Confirma com: docker compose ps"
