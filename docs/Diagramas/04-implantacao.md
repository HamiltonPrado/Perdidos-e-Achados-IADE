# 4. Implantação (Docker)

Tudo corre com `docker compose up -d --build`. Só o nginx (127.0.0.1:8080) e os três nós da base de dados
(3307–3309, para ferramentas de administração) ficam acessíveis no computador.

```mermaid
flowchart TB
    subgraph HOST["Computador (Docker Desktop)"]
        subgraph NET["Rede interna do Docker Compose"]
            NG["nginx<br/>imagem nginx:1.28-alpine<br/>127.0.0.1:8080 → 80"]
            WB["web<br/>imagem perdidos-achados-web<br/>(php:8.4-apache)"]
            G1["galera1<br/>mariadb:11.4<br/>porta 3307"]
            G2["galera2<br/>mariadb:11.4<br/>porta 3308"]
            G3["galera3<br/>mariadb:11.4<br/>porta 3309"]
            IA["ia<br/>imagem perdidos-achados-ia<br/>(python:3.13-slim + modelo)"]
            BK["backup<br/>mariadb:11.4"]
            CT["controlo<br/>python:3.12-alpine"]
            AH["autoheal<br/>willfarrell/autoheal:1.2.0"]
        end

        V1[("dados_galera1")]
        V2[("dados_galera2")]
        V3[("dados_galera3")]
        VE[("estado_galera<br/>votação de arranque")]
        PF["./frontend e ./backend<br/>(montados no web)"]
        PB["./backups"]
        SOCK["docker.sock"]
    end

    NG --> WB
    NG --> CT
    WB --> G1
    WB --> G2
    WB --> G3
    IA --> G1
    IA --> G2
    IA --> G3
    BK --> G3
    G1 --- V1
    G2 --- V2
    G3 --- V3
    G1 --- VE
    G2 --- VE
    G3 --- VE
    WB --- PF
    BK --- PB
    CT --- SOCK
    AH --- SOCK
```
