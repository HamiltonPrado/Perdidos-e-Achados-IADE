# Contrato da API

Este ficheiro é o acordo entre frontend e backend. Se um campo ou endpoint mudar, atualizem-no aqui **primeiro**.

URL base (desenvolvimento): `http://localhost:3000/api`

## Endpoints

| Método | Caminho | Descrição | Autenticação |
|---|---|---|---|
| POST | `/auth/login` | Entrar | Não |
| GET | `/items?q=&categoria=&local=&pagina=` | Lista de objetos com filtros | Não |
| GET | `/items/:id` | Detalhe de um objeto | Não |
| POST | `/items` | Publicar objeto (multipart, com fotos) | Sim |
| PATCH | `/items/:id` | Alterar estado (ex.: devolvido) | Sim (quem publicou) |
| GET | `/me/items` | Os meus anúncios | Sim |
| POST | `/items/:id/reclamacoes` | Reclamar objeto ("É meu") | Sim |
| GET | `/items/:id/reclamacoes` | Ver reclamações de um objeto | Sim (quem publicou) |
| PATCH | `/reclamacoes/:id` | Aceitar / recusar reclamação | Sim (quem publicou) |
| GET | `/categorias` | Lista de categorias | Não |
| GET | `/locais` | Lista de locais do campus | Não |

## Modelos de dados

### Item

```json
{
  "id": 42,
  "titulo": "Carteira de pele castanha",
  "descricao": "Carteira pequena com fecho.",
  "categoria": "Documentos",
  "local": "Biblioteca · Piso 1",
  "dataEncontrado": "2026-10-15T11:20:00Z",
  "ondeEsta": "secretaria",
  "estado": "por_reclamar",
  "fotos": ["http://localhost:3000/uploads/42-1.jpg"],
  "publicadoPor": { "id": 7, "nome": "Ana S." }
}
```

- `estado`: `por_reclamar` | `reclamado` | `devolvido`
- `ondeEsta`: `secretaria` | `com_quem_encontrou`
- A `perguntaVerificacao` e a sua resposta são guardadas no backend e **nunca** são enviadas nesta resposta.

### Reclamação

```json
{
  "id": 5,
  "itemId": 42,
  "resposta": "Tinha um cartão de estudante e 10€",
  "estado": "pendente",
  "criadoEm": "2026-10-15T14:00:00Z",
  "reclamadoPor": { "id": 9, "nome": "João M." }
}
```

- `estado`: `pendente` | `aceite` | `recusada`

## Erros

Todos os erros devolvem:

```json
{ "erro": "Mensagem legível para o utilizador" }
```

Com o código HTTP adequado: 400 (dados inválidos), 401 (sem login), 403 (sem permissão), 404 (não existe).
