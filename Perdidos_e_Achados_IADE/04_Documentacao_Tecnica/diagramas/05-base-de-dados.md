# 5. Base de dados (modelo entidade-relação)

Modelo da base de dados `perdidos_achados` (MariaDB 11.4), igual nos três nós do cluster Galera.
A imagem está em [05-base-de-dados.png](05-base-de-dados.png); o diagrama abaixo é o mesmo, em Mermaid.

Cada tabela mostra a chave primária (PK), as chaves estrangeiras (FK) e as colunas principais.
Notação «pé de galinha»: `||` exatamente um, `|o` zero ou um, `o{` zero ou muitos.

```mermaid
erDiagram
    users ||--o{ items : "regista"
    categories ||--o{ items : "classifica"
    locations ||--o{ items : "onde foi perdido ou achado"
    pontos_recolha |o--o{ items : "guarda"
    items ||--o{ item_photos : "tem"
    items ||--o{ ia_jobs : "fila de análise"
    items ||--o{ matches : "perdido e achado"
    items ||--o{ devolucoes : "é devolvido"
    users ||--o{ devolucoes : "recebe ou confirma"
    users ||--o{ reclamacoes : "faz"
    items ||--o{ reclamacoes : "é reclamado"
    reclamacoes |o--o{ pedidos_levantamento : "gera código"
    items ||--o{ pedidos_levantamento : "a levantar"
    users ||--o{ notifications : "recebe"
    reclamacoes |o--o{ notifications : "avisa decisão"
    users |o--o{ sessions : "tem"

    users {
        int id PK
        varchar nome
        varchar email UK
        varchar password_hash "bcrypt"
        enum role "user ou admin"
        tinyint ativo
        mediumblob foto "foto de perfil"
    }
    items {
        int id PK
        int user_id FK
        enum tipo "lost ou found"
        varchar titulo
        text descricao
        int category_id FK
        int location_id FK
        int ponto_recolha_id FK
        date data_ocorrencia
        enum estado "ativo, resolvido, devolvido, arquivado"
        enum estado_recepcao "por_entregar ou na_recepcao"
        varchar pergunta_secreta
        int recebido_por FK
        int versao
    }
    categories {
        int id PK
        varchar nome UK
    }
    locations {
        int id PK
        varchar nome
    }
    pontos_recolha {
        int id PK
        varchar nome
    }
    item_photos {
        int id PK
        int item_id FK
        enum mime "JPEG ou PNG"
        mediumblob dados
    }
    ia_jobs {
        int id PK
        int item_id FK
        enum estado "pendente, em_processamento, concluido, erro"
        int tentativas
    }
    matches {
        int id PK
        int item_id FK "perdido"
        int matched_item_id FK "achado"
        decimal score "0 a 1"
        varchar modelo_ia
        enum estado "sugerido, descartado, concluido"
    }
    reclamacoes {
        int id PK
        int user_id FK
        int item_achado_id FK
        int item_perdido_id FK
        int match_id FK
        varchar resposta
        enum estado "pendente, aprovada, recusada, reaberta, cancelada"
        int decidido_por FK
    }
    pedidos_levantamento {
        int id PK
        int user_id FK
        int item_achado_id FK
        int reclamacao_id FK
        char codigo_hash "HMAC-SHA256"
        enum estado "pendente, utilizado, expirado, cancelado"
        datetime expira_em "24 h"
    }
    devolucoes {
        int id PK
        int item_achado_id FK
        int item_perdido_id FK
        int recebido_por FK "dono"
        int confirmado_por FK "equipa"
        int ponto_recolha_id FK
        timestamp devolvido_em
    }
    notifications {
        int id PK
        int user_id FK
        enum tipo "correspondencia, pedido_aprovado, ..."
        int reclamacao_id FK
        int item_id FK
        tinyint lida
    }
    sessions {
        varchar id PK
        int user_id FK
        mediumblob dados
        datetime expira_em
    }
    tentativas_login {
        bigint id PK
        varchar email
        datetime falhou_em
    }
```

## Tabelas (15)

| Tabela | O que guarda |
|---|---|
| **users** | Contas (aluno ou equipa), palavra-passe em bcrypt e fotografia de perfil. |
| **items** | Objetos perdidos (`lost`) e achados (`found`): descrição, categoria, local, estado, receção e pergunta secreta. |
| **categories** · **locations** · **pontos_recolha** | Listas de referência: categorias, locais do campus e pontos de recolha. |
| **item_photos** | Fotografias dos objetos (JPEG ou PNG, até 2 MB), guardadas na própria base de dados. |
| **reclamacoes** | Pedidos de um aluno para reaver um achado, com a resposta à pergunta secreta e a decisão da equipa. |
| **pedidos_levantamento** | Códigos de levantamento: só o HMAC-SHA256 do código, válido 24 horas. |
| **devolucoes** | Registo de cada entrega: quem recebeu, quem confirmou e onde. |
| **notifications** | Avisos ao aluno (reclamação aprovada, recusada ou reaberta; correspondência da IA). |
| **ia_jobs** | Fila de análises da IA (fase 2), com tentativas e reserva para um só trabalhador. |
| **matches** | Sugestões da IA: par perdido/achado, nota de semelhança (0 a 1) e modelo usado. |
| **sessions** | Sessões iniciadas (guardadas na BD, para valerem em qualquer réplica do web). |
| **tentativas_login** | Tentativas de login falhadas, para bloquear um email ao fim de 5 em 15 minutos. |

## Relações que o diagrama simplifica

Para o diagrama ficar legível, algumas chaves estrangeiras secundárias não têm linha própria:

- `items.recebido_por` → `users` (quem da equipa recebeu o objeto);
- `reclamacoes.item_perdido_id` → `items`, `reclamacoes.match_id` → `matches`, `reclamacoes.decidido_por` e `reaberta_por` → `users`;
- `pedidos_levantamento.user_id` → `users`, `item_perdido_id` → `items` e `match_id` → `matches`;
- `devolucoes.item_perdido_id` → `items`, `match_id` → `matches` e `ponto_recolha_id` → `pontos_recolha`;
- `notifications.item_id` → `items`, `match_id` → `matches` e `ponto_recolha_id` → `pontos_recolha`.

O esquema completo está em `backend/database/perdidos_achados.sql` (32 chaves estrangeiras).
