# 2. Mecanismos de redundância

Os dados existem em três cópias completas, mantidas iguais por replicação síncrona: uma escrita só é
confirmada quando o cluster a aceita. Quem usa a base de dados conhece os três nós e passa ao seguinte
se um falhar. Além disso, há cópias de segurança fora dos contentores.

```mermaid
flowchart TB
    subgraph CLI["Clientes da base de dados"]
        direction LR
        WB["web"]
        IA["ia"]
        BK["backup"]
        WB ~~~ IA ~~~ BK
    end

    subgraph GAL["3 cópias dos dados"]
        direction LR
        G1[("galera1")] <-- "replicação<br/>síncrona" --> G2[("galera2")] <-- "replicação<br/>síncrona" --> G3[("galera3")]
    end

    CLI -- "failover: usa o 1.º nó<br/>sincronizado que responde" --> GAL
    GAL -- "dump de hora a hora" --> CP[("./backups<br/>24 cópias")]
    VE[("estado_galera<br/>última transação de cada nó")] -.- GAL

    classDef app fill:#ede9fe,stroke:#7c3aed,color:#4c1d95
    classDef dados fill:#fef3c7,stroke:#d97706,color:#78350f
    classDef apoio fill:#dcfce7,stroke:#16a34a,color:#14532d
    class WB,IA,BK app
    class G1,G2,G3 dados
    class CP,VE apoio
    style CLI fill:#f9fafb,stroke:#d1d5db,color:#374151
    style GAL fill:#f9fafb,stroke:#d1d5db,color:#374151
```

| O que está duplicado | Como |
|---|---|
| Dados | 3 cópias completas (galera1, galera2, galera3) com replicação síncrona; nenhum nó é o «principal». |
| Acesso à base de dados | O web, a IA e o backup recebem a lista `galera1,galera2,galera3` e usam o primeiro nó sincronizado que responder (o PHP desiste de um nó em 2 s). |
| Recuperação de um nó | Um nó que volta recebe só o que perdeu (IST, histórico `gcache` de 128 MB) ou uma cópia completa (SST com `mariabackup`). |
| Estado do cluster | O volume `estado_galera` guarda a última transação de cada nó, para escolher quem refunda o cluster. |
| Cópias de segurança | `mariadb-dump` de hora a hora, 24 cópias guardadas fora dos contentores (`./backups`). |
