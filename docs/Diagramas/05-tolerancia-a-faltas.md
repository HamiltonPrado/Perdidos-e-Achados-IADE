# 5. Infraestrutura distribuída e tolerância a faltas

A base de dados tem três cópias iguais e só aceita escritas com maioria (2 de 3). A verde estão os
mecanismos que recuperam o sistema sozinhos; os números ligam o diagrama à tabela
(o ⑤ vale para todos os contentores e não está desenhado).
Modelo de faltas: paragem com recuperação (crash-recovery); não consideramos faltas bizantinas.

```mermaid
flowchart TB
    U(["Browser"]) --> NG["nginx"] --> WB["web"]
    AH["④ autoheal"] -.-> WB

    WB -- "① 1.º nó disponível" --> CL

    subgraph CL["Galera · maioria 2 de 3"]
        direction LR
        G1[("galera1")]
        G2[("galera2")]
        G3[("galera3")]
        G1 ~~~ G2 ~~~ G3
    end

    subgraph REC["Recuperação automática"]
        direction LR
        AR["② árbitro"]
        VE["③ votação de arranque"]
        BK["⑥ backup"]
        AR ~~~ VE ~~~ BK
    end

    REC -.-> CL

    classDef entrada fill:#dbeafe,stroke:#2563eb,color:#1e3a8a
    classDef app fill:#ede9fe,stroke:#7c3aed,color:#4c1d95
    classDef apoio fill:#dcfce7,stroke:#16a34a,color:#14532d
    classDef dados fill:#fef3c7,stroke:#d97706,color:#78350f
    class U,NG entrada
    class WB app
    class AH,AR,VE,BK apoio
    class G1,G2,G3 dados
    style CL fill:#f9fafb,stroke:#d1d5db,color:#374151
    style REC fill:#f9fafb,stroke:#d1d5db,color:#374151
```

| # | Falta | Como o sistema reage |
|---|---|---|
| 1 | Cai 1 nó da BD | Os 2 restantes têm maioria e continuam. O PHP passa ao nó seguinte (desiste em 2 s). Ao voltar, o nó recebe o que perdeu. |
| 2 | Caem 2 nós | O nó que sobra fica sem maioria e não escreve. O árbitro reativa-o ao fim de 15 s. |
| 3 | Param os 3 nós (ex.: desligar o PC) | Votação no volume `estado_galera`: o nó com a última transação refunda o cluster. Plano B manual: `recuperar-cluster.sh`. |
| 4 | web ou nginx doente | O healthcheck falha 3 vezes e o autoheal reinicia o contentor. |
| 5 | Contentor termina | `restart: unless-stopped` volta a arrancá-lo (todos os contentores). A BD tem 60 s para parar sem perder o estado. |
| 6 | Dados apagados ou corrompidos | Cópia (`mariadb-dump`) de hora a hora, 24 guardadas; o `repor.sh` restaura. |

**Partição de rede:** só o lado com maioria aceita escritas (evita *split-brain*).

**Limitações (pontos únicos de falha):** uma só instância web e um só nginx (o web aceita réplicas: sessões
e fotos estão na BD); tudo corre num só computador; sem HTTPS (acesso só local, 127.0.0.1).
