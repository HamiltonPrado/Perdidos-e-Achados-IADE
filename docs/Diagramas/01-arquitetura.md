# 1. Arquitetura

O browser só fala com o nginx. O nginx encaminha para o backend PHP, que lê e grava num cluster MariaDB
Galera de três nós com os mesmos dados. A IA, o backup, o painel de controlo e o autoheal correm ao lado.

```mermaid
flowchart LR
    U["Aluno / Receção<br/>browser"]

    subgraph DOCKER["Docker (um computador)"]
        N["nginx<br/>porta de entrada"]
        W["web<br/>Apache + PHP<br/>frontend + API"]
        subgraph CLUSTER["Cluster MariaDB Galera"]
            G1[("galera1")]
            G2[("galera2")]
            G3[("galera3")]
        end
        B["backup<br/>de hora a hora"]
        C["controlo<br/>painel de testes + árbitro"]
        A["autoheal<br/>reinicia contentores doentes"]
        IAW["ia<br/>worker.py + modelo.py"]
    end

    U -- "HTTP" --> N
    N -- "/ e /api" --> W
    N -- "/controlo" --> C
    W -- "SQL (galera1, galera2, galera3)" --> CLUSTER
    G1 <-. "replicação síncrona" .-> G2
    G2 <-. "replicação síncrona" .-> G3
    G1 <-. "replicação síncrona" .-> G3
    B -- "mariadb-dump" --> CLUSTER
    C -- "API do Docker" --> DOCKER
    IAW -- "ia_jobs → matches" --> CLUSTER
```
