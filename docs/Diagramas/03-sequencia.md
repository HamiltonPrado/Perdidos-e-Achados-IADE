# 3. Sequência: registar procura → reclamar → levantar

O aluno regista o que perdeu, prova que é dono do objeto e levanta-o na receção com um código.
O código nunca é guardado: a base de dados só tem o seu HMAC-SHA256.

```mermaid
sequenceDiagram
    autonumber
    actor Aluno
    participant API as Site + API
    participant BD as Base de dados
    actor Rec as Receção

    rect rgb(219, 234, 254)
    Note over Aluno,BD: 1 · Registar a procura
    Aluno->>API: descreve o que perdeu
    API->>BD: grava o perdido + pedido à IA
    API-->>Aluno: referência PER-0000NN
    end

    rect rgb(237, 233, 254)
    Note over Aluno,Rec: 2 · Reclamar
    Aluno->>API: responde à pergunta secreta do achado
    Rec->>API: aprova a reclamação
    API-->>Aluno: notificação «aprovada»
    end

    rect rgb(220, 252, 231)
    Note over Aluno,Rec: 3 · Levantar
    Aluno->>API: pede o levantamento
    API->>BD: guarda só o HMAC do código (24 h)
    API-->>Aluno: código de 8 caracteres
    Aluno->>Rec: mostra o código ao balcão
    Rec->>API: confirma a entrega
    API->>BD: objeto «devolvido»
    end
```
