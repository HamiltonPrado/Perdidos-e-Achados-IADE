# Frontend

Interface web da app Perdidos e Achados IADE.

## Organização

Cada pasta tem um único tipo de ficheiro: **`.jsx` para o que aparece no ecrã, `.css` só dentro de `styles/`**.

```
frontend/
├── index.html               página base (carrega as fontes e o main.jsx)
├── package.json             dependências e comandos (npm run dev…)
├── vite.config.js           configuração do Vite
├── .env.example             copiar para .env (endereço do backend)
├── public/                  imagens estáticas (logo, ícones)
└── src/
    ├── main.jsx             ponto de entrada: arranca a app
    ├── App.jsx              Header + Footer + rotas (que página para cada URL)
    │
    ├── pages/               UM ficheiro por ecrã (só .jsx)
    │   ├── Inicio.jsx
    │   └── EmConstrucao.jsx   (temporária, para páginas por fazer)
    │
    ├── components/          peças reutilizáveis (só .jsx)
    │   ├── Header.jsx
    │   ├── Footer.jsx
    │   ├── ItemCard.jsx
    │   └── FiltroCategorias.jsx
    │
    ├── services/
    │   └── api.js           ÚNICO sítio que fala com o backend
    │
    ├── mocks/
    │   └── items.js         dados falsos enquanto o backend não está pronto
    │
    └── styles/              TODOS os .css
        ├── variables.css      cores e fontes
        ├── global.css         base, botões, campos
        ├── components/        um .css por componente (Header.css…)
        └── pages/             um .css por página (Inicio.css…)
```

### Regra dos nomes

O `.css` tem sempre o mesmo nome que o `.jsx`:

| Ficheiro | Estilos |
|---|---|
| `components/Header.jsx` | `styles/components/Header.css` |
| `pages/Inicio.jsx` | `styles/pages/Inicio.css` |

Para criar uma página nova, por exemplo Detalhe:
1. `src/pages/Detalhe.jsx`
2. `src/styles/pages/Detalhe.css`, importado no topo do `.jsx` com `import '../styles/pages/Detalhe.css';`
3. Liga a rota no `App.jsx`

As classes CSS usam o formato `bloco__parte--variante` (por exemplo `cartao__titulo` ou `botao--destaque`), para se perceber logo a que componente pertencem.

## Regra de ouro

Nenhum componente ou página faz pedidos ao backend diretamente. Todos usam as funções de `src/services/api.js`.
Enquanto o backend não estiver pronto, `USAR_MOCKS = true` faz a app usar os dados de `src/mocks/`.

## Como correr

Precisa do [Node.js](https://nodejs.org) (versão LTS) instalado.

```bash
cd frontend
npm install      # só da primeira vez (ou quando mudarem as dependências)
npm run dev      # abre em http://localhost:5173
```

Opcional: copiar `.env.example` para `.env` para mudar o endereço do backend.

## Feito até agora

- [x] Header, Footer, ItemCard, FiltroCategorias
- [x] Página Início (pesquisa, filtros por categoria, local e ordem, lista vazia)
- [ ] Página Detalhe
- [ ] Página Publicar
- [ ] Página Os meus anúncios
- [ ] Login
