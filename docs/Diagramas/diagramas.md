# Diagramas — Perdidos & Achados IADE

Cinco vistas do sistema: arquitetura, componentes, sequência (registar procura → reclamar → levantar),
implantação em Docker e tolerância a faltas. O GitHub desenha os diagramas Mermaid diretamente nesta página.

A tracejado está o que é da **fase 2** da IA (planeado); o resto já existe.

---

## 1. Arquitetura

O browser só fala com o nginx. O nginx encaminha para o backend PHP, que lê e grava num cluster MariaDB
Galera de três nós com os mesmos dados. O backup, o painel de controlo e o autoheal correm ao lado.

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
        IAW["Trabalhador de IA<br/>worker.py (fase 2)"]:::fase2
    end

    IA["Modelo de IA<br/>ia/modelo.py (fase 1)"]

    U -- "HTTP" --> N
    N -- "/ e /api" --> W
    N -- "/controlo" --> C
    W -- "SQL (galera1, galera2, galera3)" --> CLUSTER
    G1 <-. "replicação síncrona" .-> G2
    G2 <-. "replicação síncrona" .-> G3
    G1 <-. "replicação síncrona" .-> G3
    B -- "mariadb-dump" --> CLUSTER
    C -- "API do Docker" --> DOCKER
    IAW -.-> CLUSTER
    IAW -. "usa" .-> IA

    classDef fase2 stroke-dasharray: 5 5,color:#777;
```

---

## 2. Componentes

```mermaid
flowchart TB
    subgraph FE["Frontend (HTML + JavaScript)"]
        direction LR
        P1["Páginas do aluno<br/>Explorar · Encontrar · Publicar<br/>Meus anúncios · Reclamar · Levantamento"]
        P2["Páginas da receção<br/>Painel · Objetos · Pedidos · Entrada"]
        SV["Serviços JS<br/>api.js · authService · itemsService<br/>procurasService · reclamacoesService · adminService"]
        P1 --> SV
        P2 --> SV
    end

    subgraph BE["Backend (API REST em PHP)"]
        direction TB
        IDX["public/index.php"] --> RT["routes/api.php<br/>+ grupos de rotas"]
        RT --> MW["middleware<br/>autenticação · CSRF · administrador"]
        MW --> CT["controllers<br/>Auth · Item · Photo · Reclamacao<br/>PedidoLevantamento · Devolucao · Notificacao"]
        CT --> MD["models<br/>Item · User · Reclamacao · PedidoLevantamento<br/>IaJob · Correspondencia · Notification"]
        MD --> DB_PHP["config/database.php<br/>escolhe o 1.º nó sincronizado"]
    end

    subgraph BD["Base de dados (MariaDB Galera)"]
        T1[("users · sessions")]
        T2[("items · item_photos<br/>categories · locations")]
        T3[("reclamacoes · pedidos_levantamento<br/>devolucoes · notifications")]
        T4[("ia_jobs · matches")]
    end

    subgraph IAG["Inteligência artificial"]
        MOD["modelo.py (fase 1)<br/>texto → vetor → nota 0 a 1"]
        WRK["worker.py (fase 2)<br/>fila → notas → sugestões"]:::fase2
    end

    SV -- "HTTP + JSON<br/>cookie de sessão + X-CSRF-Token" --> IDX
    DB_PHP --> BD
    WRK -.-> T4
    WRK -. "usa" .-> MOD

    classDef fase2 stroke-dasharray: 5 5,color:#777;
```

---

## 3. Sequência: registar procura → reclamar → levantar

O aluno perde um objeto, a receção recebe-o, o aluno prova que é dono e levanta-o com um código.
O código de levantamento nunca é guardado: a base de dados só tem o seu HMAC-SHA256.

```mermaid
sequenceDiagram
    autonumber
    actor Aluno
    participant FE as Frontend
    participant API as API (PHP)
    participant BD as Base de dados
    actor Rec as Receção

    rect rgb(240, 245, 255)
    Note over Aluno,BD: Registar a procura
    Aluno->>FE: preenche «Encontrar» (o que perdeu)
    FE->>API: POST /api/items/lost
    API->>BD: grava o perdido e o pedido de análise (ia_jobs), na mesma transação
    API-->>FE: 201 + referência PER-0000NN
    end

    rect rgb(245, 245, 245)
    Note over Rec,BD: Receção recebe um objeto entregue
    Rec->>API: POST /api/admin/items/:id/receive (pergunta secreta)
    API->>BD: achado fica «na receção»
    end

    rect rgb(240, 250, 240)
    Note over Aluno,Rec: Reclamar
    Aluno->>FE: vê o achado e responde à pergunta secreta
    FE->>API: POST /api/items/:id/claims
    API->>BD: reclamação «pendente»
    Rec->>API: PATCH /api/admin/claims/:id (aprovar)
    API->>BD: reclamação «aprovada» + notificação ao aluno
    end

    rect rgb(255, 248, 235)
    Note over Aluno,Rec: Levantar
    Aluno->>FE: abre «Levantamento»
    FE->>API: POST /api/claims/:id/collection-request
    API->>API: gera código de 8 caracteres
    API->>BD: guarda só o HMAC-SHA256 do código (validade 24 h)
    API-->>FE: código (mostrado só agora)
    Aluno->>Rec: mostra o código ao balcão
    Rec->>API: GET /api/admin/collection-requests/:codigo
    API->>BD: procura pelo HMAC do código
    API-->>Rec: aluno e objeto a entregar
    Rec->>API: POST /api/admin/collection-requests/:codigo/confirm
    API->>BD: devolução registada, código usado, objeto «devolvido»
    end
```

---

## 4. Implantação (Docker)

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

---

## 5. Infraestrutura distribuída e tolerância a faltas

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
