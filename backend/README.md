# Backend

API da app Perdidos e Achados IADE. Os endpoints e formatos estão definidos em `../docs/api.md`.

## Organização

```
backend/
├── src/
│   ├── routes/        define os URLs (items, reclamacoes, auth…)
│   ├── controllers/   recebe o pedido e devolve a resposta
│   ├── services/      regras de negócio (ex.: só quem publicou aceita reclamações)
│   ├── models/        User, Item, Foto, Reclamacao, Categoria, Local
│   ├── middleware/    autenticação, upload de imagens, erros
│   └── config/        ligação à base de dados, variáveis
├── uploads/           fotos enviadas (não vão para o GitHub)
└── .env.example       copiar para .env
```

## Obrigatório para o frontend funcionar

- **CORS** ativo para o endereço do frontend (`FRONTEND_URL` no `.env`).
- Aceitar **multipart/form-data** no `POST /items` (fotos).
- Erros sempre no formato `{ "erro": "mensagem" }`.

## Configuração

1. Copiar `.env.example` para `.env` e preencher.
2. Instalar a framework escolhida (Express, Spring, Laravel…) dentro desta pasta.
