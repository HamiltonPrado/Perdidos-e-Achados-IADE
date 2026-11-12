# 3. Sequência: registar procura → reclamar → levantar

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
