# 1. Infraestrutura distribuída

O sistema corre em contentores Docker ligados por uma rede interna. A base de dados está distribuída
por três nós MariaDB Galera iguais, cada um no seu contentor e com o seu volume de dados. Para fora
só ficam abertos o nginx (127.0.0.1:8080) e os três nós (3307–3309, para ferramentas de administração).

```mermaid
flowchart TB
    U(["Browser · 127.0.0.1:8080"]) --> NG["nginx<br/>porta de entrada"]

    subgraph APP["Aplicação"]
        direction LR
        WB["web<br/>PHP · site + API"]
        IA["ia<br/>sugestões"]
        WB ~~~ IA
    end

    subgraph GAL["Cluster MariaDB Galera"]
        direction LR
        G1[("galera1<br/>:3307")] <--> G2[("galera2<br/>:3308")] <--> G3[("galera3<br/>:3309")]
    end

    subgraph APOIO["Apoio"]
        direction LR
        BK["backup"]
        CT["controlo<br/>árbitro"]
        AH["autoheal"]
        BK ~~~ CT ~~~ AH
    end

    NG --> APP
    APP --> GAL
    APOIO -.-> GAL

    classDef entrada fill:#dbeafe,stroke:#2563eb,color:#1e3a8a
    classDef app fill:#ede9fe,stroke:#7c3aed,color:#4c1d95
    classDef dados fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef apoio fill:#f3f4f6,stroke:#9ca3af,color:#374151
    class U,NG entrada
    class WB,IA app
    class G1,G2,G3 dados
    class BK,CT,AH apoio
    style APP fill:#f9fafb,stroke:#d1d5db,color:#374151
    style GAL fill:#f9fafb,stroke:#d1d5db,color:#374151
    style APOIO fill:#f9fafb,stroke:#d1d5db,color:#374151
```

| Nó | Função |
|---|---|
| nginx | Única porta de entrada; passa os pedidos ao web. |
| web | Site e API REST em PHP. Não guarda estado (sessões e fotos estão na BD). |
| ia | Compara perdidos com achados e grava as sugestões. |
| galera1, galera2, galera3 | Três nós da base de dados, cada um com uma cópia completa dos dados. |
| backup · controlo · autoheal | Cópias de hora a hora · árbitro do cluster · reinicia contentores doentes. |
