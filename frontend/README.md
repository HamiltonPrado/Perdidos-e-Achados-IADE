# Frontend

Interface web da app Perdidos e Achados IADE.

## Organização

```
frontend/
├── public/            imagens estáticas (logo, ícones)
├── src/
│   ├── pages/         um ficheiro por ecrã: Inicio, Detalhe, Publicar, MeusAnuncios, Login
│   ├── components/    peças reutilizáveis: Header, Footer, ItemCard, FiltroCategorias, UploadFotos
│   ├── services/
│   │   └── api.js     TODOS os pedidos ao backend passam por aqui
│   ├── mocks/         dados falsos enquanto o backend não está pronto
│   └── styles/        cores e fontes (variables.css)
└── .env.example       copiar para .env
```

## Regra de ouro

Nenhum componente ou página faz pedidos ao backend diretamente. Todos usam as funções de `src/services/api.js`.
Enquanto o backend não estiver pronto, `USAR_MOCKS = true` faz a app usar os dados de `src/mocks/`.

## Configuração

1. Copiar `.env.example` para `.env`.
2. Quando escolherem a framework (React, Vue…), instalá-la dentro desta pasta e manter a organização de `src/`.
