#!/bin/sh
# Depois de um failover (promover-db2.sh): volta à situação normal, sem perder dados.
#   1. copia os dados atuais do db2 (que foi principal) para o db1;
#   2. o db1 volta a ser o principal;
#   3. o db2 volta a ser réplica do db1.
#
# Uso (na raiz do projeto):  sh docker/replicacao/repor-replica.sh
set -eu
cd "$(dirname "$0")/../.."

printf 'Copiar os dados do db2 para o db1 e repor db1 = principal, db2 = réplica? [s/N] '
read -r RESPOSTA
[ "${RESPOSTA}" = "s" ] || { echo "Cancelado."; exit 0; }

DB_NAME="$(docker compose exec -T db2 sh -c 'echo "$MYSQL_DATABASE"' | tr -d '\r')"

echo "A pôr o db2 em só-leitura enquanto copia (o site fica em modo só-leitura uns segundos)…"
docker compose exec -T db2 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "SET GLOBAL read_only = ON;"'

docker compose start db1
until docker compose exec -T db1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -u root --silent' >/dev/null 2>&1; do sleep 2; done

echo "A copiar os dados do db2 para o db1…"
docker compose exec -T db2 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -u root --single-transaction --set-gtid-purged=OFF --routines --no-tablespaces --databases "$MYSQL_DATABASE"' \
  | docker compose exec -T db1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF;" && MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root'

docker compose exec -T db1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "SET PERSIST read_only = OFF; SET PERSIST super_read_only = OFF;"'

# O db2 deixa de ter dados próprios: a replicação volta a ser configurada a partir do db1.
docker compose exec -T db2 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root -e "
    SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF;
    DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\`;
    RESET MASTER;"'

docker compose run --rm replicacao
echo "Pronto: db1 é o principal e o db2 voltou a ser a sua réplica (base de dados ${DB_NAME})."
