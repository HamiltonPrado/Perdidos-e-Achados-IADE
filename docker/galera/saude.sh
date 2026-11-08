#!/bin/bash
# Healthcheck de um nó Galera: só está «healthy» se estiver sincronizado com o cluster
# (wsrep_local_state = 4, «Synced») e o cluster tiver maioria (Primary).
set -eu
ESTADO="$(MYSQL_PWD="${MARIADB_ROOT_PASSWORD}" mariadb -u root -N \
          -e "SHOW STATUS WHERE Variable_name IN ('wsrep_local_state', 'wsrep_cluster_status')" | cut -f2 | tr '\n' ' ')"
[ "${ESTADO}" = "4 Primary " ]
