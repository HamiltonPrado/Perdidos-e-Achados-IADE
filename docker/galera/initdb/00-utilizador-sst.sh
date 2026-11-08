#!/bin/bash
# Só corre no galera1, na criação do cluster (volume vazio), ANTES de importar o .sql.
# Conta que o mariadb-backup usa para copiar os dados para um nó que entra no cluster (SST).
# Fica replicada para os outros nós, por isso qualquer nó pode depois ser o doador.
set -eu
mariadb -u root -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS 'mariabackup'@'localhost' IDENTIFIED BY '${GALERA_SST_PASSWORD}';
GRANT RELOAD, PROCESS, LOCK TABLES, BINLOG MONITOR ON *.* TO 'mariabackup'@'localhost';
SQL
