#!/bin/sh
# FAILOVER: usar quando o db1 (principal) caiu e não volta.
# Transforma o db2 (réplica) em base de dados principal. O PHP liga-se sozinho à BD que
# aceita escritas, por isso o site volta a funcionar por inteiro sem mexer no web.
#
# Uso (na raiz do projeto):  sh docker/replicacao/promover-db2.sh
#
# Depois disto, NÃO voltes a usar o db1 tal como está: ficou com dados desatualizados.
# Para regressar à situação normal (db1 principal, db2 réplica): sh docker/replicacao/repor-replica.sh
set -eu
cd "$(dirname "$0")/../.."

printf 'Promover o db2 a base de dados principal? [s/N] '
read -r RESPOSTA
[ "${RESPOSTA}" = "s" ] || { echo "Cancelado."; exit 0; }

# Impede que um db1 que volte entretanto receba escritas ao mesmo tempo que o db2.
docker compose stop db1 >/dev/null 2>&1 || true

docker compose exec -T db2 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "
    STOP REPLICA;
    RESET REPLICA ALL;
    SET PERSIST super_read_only = OFF;
    SET PERSIST read_only = OFF;
    SELECT @@hostname AS principal, @@GLOBAL.read_only AS so_leitura;"'

echo "O db2 é agora a base de dados principal. O db1 ficou parado."
