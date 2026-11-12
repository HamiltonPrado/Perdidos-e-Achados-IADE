# 5. Infraestrutura distribuída e tolerância a faltas

A verde estão os mecanismos que recuperam o sistema sozinhos. Os números ligam o diagrama à tabela.
Modelo de faltas: paragem com recuperação (crash-recovery); não consideramos faltas bizantinas.

```mermaid
flowchart TB
    U["Aluno / Receção<br/>(browser)"]

    subgraph PC["Um computador"]
        RS["⑤ todos os contentores<br/>restart: unless-stopped"]:::apoio
        AH["④ autoheal<br/>reinicia se «unhealthy»"]:::apoio
        NG["nginx<br/>porta de entrada · healthcheck"]
        WB["web · Apache + PHP<br/>liga ao 1.º nó sincronizado"]
        BK["⑥ backup<br/>de hora a hora · 24 cópias"]:::apoio
        AR["② controlo · árbitro<br/>reativa o cluster (15 s)"]:::apoio

        subgraph CL["Galera · quórum 2/3"]
            direction TB
            subgraph NOS[" "]
                direction LR
                G1[("① galera1")] <-. "replicação<br/>síncrona" .-> G2[("galera2")] <-. "replicação<br/>síncrona" .-> G3[("galera3")]
            end
            VE["③ estado_galera<br/>votação: quem tem a última transação refunda o cluster"]:::apoio
            NOS --- VE
        end
    end

    U -- HTTP --> NG
    NG --> WB
    AH -.-> NG
    AH -.-> WB
    WB -- SQL --> CL
    BK -- dump --> CL
    AR -.-> CL

    classDef apoio fill:#f0fdf4,stroke:#16a34a,color:#14532d;
```

| # | Falta | Como o sistema reage |
|---|---|---|
| 1 | Cai 1 nó da BD | Os 2 restantes têm quórum e continuam. O PHP passa ao nó seguinte (desiste em 2 s). Ao voltar, o nó recebe o que perdeu. |
| 2 | Caem 2 nós | O nó que sobra fica sem quórum (não escreve). O árbitro reativa-o ao fim de 15 s. |
| 3 | Param os 3 nós (ex.: desligar o PC) | Votação no volume `estado_galera`: o nó com a última transação refunda o cluster. Plano B manual: `recuperar-cluster.sh`. |
| 4 | web ou nginx doente | O healthcheck falha 3 vezes e o autoheal reinicia o contentor. |
| 5 | Contentor termina | `restart: unless-stopped` volta a arrancá-lo. A BD tem 60 s para parar sem perder o estado. |
| 6 | Dados apagados ou corrompidos | Cópia (`mariadb-dump`) de hora a hora, 24 guardadas; o `repor.sh` restaura. |

**Partição de rede:** só o lado com maioria aceita escritas (evita *split-brain*).

**Limitações (pontos únicos de falha):** uma só instância web e um só nginx (o web aceita réplicas: sessões
e fotos estão na BD); tudo corre num só computador; sem HTTPS (acesso só local, 127.0.0.1).
