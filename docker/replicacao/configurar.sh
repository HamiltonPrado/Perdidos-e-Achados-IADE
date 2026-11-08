#!/bin/sh
# Contentor «replicacao» (corre uma vez a cada `docker compose up` e termina):
# põe o db2 a copiar em tempo real o db1 (replicação MySQL com GTID).
#
# É seguro correr várias vezes:
#  - se o db2 já está a replicar o db1, não faz nada;
#  - se estava configurado mas parado, volta a ligá-lo;
#  - se ainda não foi configurado, copia os dados atuais do db1 para o db2 e liga a replicação.
# Se o db2 foi promovido a principal (promover-db2.sh), também não mexe: ver repor-replica.sh.
set -eu

export MYSQL_PWD="${DB_ROOT_PASSWORD}"
db1() { mysql -h db1 -u root "$@"; }
db2() { mysql -h db2 -u root "$@"; }

echo "[replicacao] à espera do db1 e do db2…"
until db1 -e 'SELECT 1' >/dev/null 2>&1 && db2 -e 'SELECT 1' >/dev/null 2>&1; do sleep 2; done

ESTADO="$(db2 -N -e "SELECT CONCAT(HOST, '|', SERVICE_STATE) FROM performance_schema.replication_connection_configuration
                       JOIN performance_schema.replication_connection_status USING (CHANNEL_NAME)
                     WHERE CHANNEL_NAME = ''" 2>/dev/null || true)"

if [ "$(db2 -N -e 'SELECT @@GLOBAL.read_only')" = "0" ] && [ -z "${ESTADO}" ] \
   && [ "$(db2 -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}'")" != "0" ]; then
    echo "[replicacao] o db2 é a base de dados principal (foi promovido). Não mexo."
    echo "[replicacao] Para voltar a ter o db1 como principal: sh docker/replicacao/repor-replica.sh"
    exit 0
fi

case "${ESTADO}" in
    db1\|ON)
        echo "[replicacao] o db2 já está a replicar o db1."
        exit 0 ;;
    db1\|*)
        echo "[replicacao] replicação configurada mas parada: a ligar outra vez."
        db2 -e 'START REPLICA'
        exit 0 ;;
esac

echo "[replicacao] primeira configuração: a copiar os dados do db1 para o db2…"

# Conta que o db2 usa para ler o registo de alterações (binlog) do db1.
db1 -e "CREATE USER IF NOT EXISTS 'replicacao'@'%' IDENTIFIED BY '${REPL_PASSWORD}';
        ALTER USER 'replicacao'@'%' IDENTIFIED BY '${REPL_PASSWORD}';
        GRANT REPLICATION SLAVE ON *.* TO 'replicacao'@'%';"

# O db2 recebe a cópia: tem de aceitar escritas durante a importação e começar sem GTIDs próprios.
db2 -e "STOP REPLICA; RESET REPLICA ALL; SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF; RESET MASTER;" 2>/dev/null \
  || db2 -e "SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF; RESET MASTER;"

# Cópia consistente do db1 (inclui a posição GTID, para a replicação continuar exatamente daí).
mysqldump -h db1 -u root --single-transaction --set-gtid-purged=ON --routines --no-tablespaces \
    --databases "${DB_NAME}" | db2

db2 -e "CHANGE REPLICATION SOURCE TO
          SOURCE_HOST = 'db1',
          SOURCE_USER = 'replicacao',
          SOURCE_PASSWORD = '${REPL_PASSWORD}',
          SOURCE_AUTO_POSITION = 1,
          GET_SOURCE_PUBLIC_KEY = 1,
          SOURCE_CONNECT_RETRY = 5;
        START REPLICA;
        SET PERSIST read_only = ON;
        SET PERSIST super_read_only = ON;"

sleep 3
db2 -e "SELECT SERVICE_STATE AS ligacao_ao_db1 FROM performance_schema.replication_connection_status WHERE CHANNEL_NAME = '';
        SELECT SERVICE_STATE AS aplicacao_das_alteracoes FROM performance_schema.replication_applier_status WHERE CHANNEL_NAME = '';"
echo "[replicacao] pronto: o db2 é uma réplica só-leitura do db1."
