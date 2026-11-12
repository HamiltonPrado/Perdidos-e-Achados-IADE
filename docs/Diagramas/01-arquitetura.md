# 1. Arquitetura

O browser só fala com o nginx. O nginx passa os pedidos à aplicação PHP, que guarda tudo num cluster
MariaDB Galera de três nós com os mesmos dados. A IA lê e escreve na mesma base de dados.

```mermaid
flowchart LR
    U(["Browser<br/>aluno · receção"])
    N["nginx"]
    W["web<br/>PHP · site + API"]
    BD[("MariaDB Galera<br/>3 nós")]
    IA["ia<br/>compara perdidos e achados"]

    U --> N --> W --> BD
    IA --> BD

    subgraph APOIO["Apoio"]
        direction TB
        B["backup"]
        C["controlo"]
        A["autoheal"]
    end
    APOIO -.- BD

    classDef entrada fill:#dbeafe,stroke:#2563eb,color:#1e3a8a
    classDef app fill:#ede9fe,stroke:#7c3aed,color:#4c1d95
    classDef dados fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef ia fill:#dcfce7,stroke:#16a34a,color:#14532d
    classDef apoio fill:#f3f4f6,stroke:#9ca3af,color:#374151
    class U,N entrada
    class W app
    class BD dados
    class IA ia
    class B,C,A apoio
    style APOIO fill:#f9fafb,stroke:#d1d5db,color:#374151
```

| Peça | O que faz |
|---|---|
| nginx | Única porta de entrada (127.0.0.1:8080). |
| web | Páginas do site e API REST em PHP. |
| MariaDB Galera | Três cópias iguais da base de dados; continua a funcionar se cair uma. |
| ia | Compara cada perdido com os achados e grava as sugestões. |
| backup · controlo · autoheal | Cópias de hora a hora · painel de testes e árbitro do cluster · reinicia contentores doentes. |
