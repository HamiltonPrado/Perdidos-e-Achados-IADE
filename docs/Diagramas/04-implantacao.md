# 4. Implantação (Docker)

Tudo arranca com `docker compose up -d --build`. Para fora do Docker só ficam abertos o nginx
(127.0.0.1:8080) e os três nós da base de dados (3307–3309, para ferramentas de administração).

```mermaid
flowchart TB
    PC(["Computador · 127.0.0.1:8080"]) --> NG["nginx<br/>nginx:1.28-alpine"]

    subgraph APP["Aplicação"]
        direction LR
        WB["web<br/>php:8.4-apache"]
        IA["ia<br/>python:3.13-slim + modelo"]
        WB ~~~ IA
    end

    subgraph APOIO["Apoio"]
        direction LR
        BK["backup"]
        CT["controlo"]
        AH["autoheal"]
        BK ~~~ CT ~~~ AH
    end

    subgraph GAL["MariaDB 11.4"]
        direction LR
        G1[("galera1 · 3307")]
        G2[("galera2 · 3308")]
        G3[("galera3 · 3309")]
        G1 ~~~ G2 ~~~ G3
    end

    VOL[("Volumes · dados de cada nó · ./backups")]

    NG --> APP
    APP --> GAL
    APOIO -.-> GAL
    GAL --- VOL

    classDef entrada fill:#dbeafe,stroke:#2563eb,color:#1e3a8a
    classDef app fill:#ede9fe,stroke:#7c3aed,color:#4c1d95
    classDef dados fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef ia fill:#dcfce7,stroke:#16a34a,color:#14532d
    classDef apoio fill:#f3f4f6,stroke:#9ca3af,color:#374151
    class PC,NG entrada
    class WB app
    class G1,G2,G3,VOL dados
    class IA ia
    class BK,CT,AH apoio
    style APP fill:#f9fafb,stroke:#d1d5db,color:#374151
    style APOIO fill:#f9fafb,stroke:#d1d5db,color:#374151
    style GAL fill:#f9fafb,stroke:#d1d5db,color:#374151
```
