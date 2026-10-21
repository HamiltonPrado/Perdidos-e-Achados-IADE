# Frontend · Achados e Perdidos IADE

Interface web feita em **HTML, CSS e JavaScript puro**, sem frameworks e sem instalar nada. A organização das pastas segue a do projeto AquaPoint.

## Como abrir

Os ficheiros `.js` usam `import`/`export` (módulos), e por isso **não abrem com duplo clique** (`file:///…`). É preciso um servidor local:

1. No VS Code, instala a extensão **Live Server**.
2. Botão direito no `index.html` → **Open with Live Server**.
3. Abre em `http://127.0.0.1:5500`, e a página atualiza sozinha sempre que guardas um ficheiro.

## Páginas

| Ficheiro | Página | JavaScript | CSS |
|---|---|---|---|
| `index.html` | Início: lista, pesquisa, filtros | `js/pages/home.js` | `assets/css/pages/home.css` |
| `detalhe.html?id=3` | Detalhe e "É teu?" com fotos de prova | `js/pages/detalhe.js` | `assets/css/pages/detalhe.css` |
| `publicar.html` 🔒 | Publicar objeto encontrado | `js/pages/publicar.js` | `assets/css/pages/publicar.css` |
| `meus-anuncios.html` 🔒 | Os meus anúncios, aceitar e recusar | `js/pages/meusAnuncios.js` | `assets/css/pages/meus-anuncios.css` |
| `perfil.html` 🔒 | Nome e foto de perfil | `js/pages/perfil.js` | `assets/css/pages/perfil.css` |

🔒 = precisa de sessão. Sem sessão, a página volta para o `index.html`.

## Estrutura

```
frontend/
├── index.html, detalhe.html, publicar.html, meus-anuncios.html, perfil.html
│
├── assets/
│   ├── css/
│   │   ├── variables.css     cores e fontes (mudar a cor da app aqui)
│   │   ├── style.css         base, botões, formulários, mensagens
│   │   ├── components/       navbar, footer, cartão, filtro
│   │   └── pages/            um .css por página
│   └── images/               logótipos e imagens
│
└── js/
    ├── pages/                um .js por página (a lógica de cada ecrã)
    ├── components/           peças repetidas em várias páginas
    │   ├── navbar.js           escreve a barra de topo em cada página
    │   ├── footer.js           escreve o rodapé
    │   ├── itemCard.js         HTML de um cartão de objeto
    │   └── filtroCategorias.js botões de categoria
    ├── services/             ÚNICO sítio que fala com o backend
    │   ├── api.js              fetch + USAR_MOCKS + BASE_URL
    │   ├── itemsService.js
    │   ├── reclamacoesService.js
    │   ├── categoriasService.js
    │   ├── locaisService.js
    │   ├── userService.js
    │   └── authService.js
    ├── models/               formato de cada tipo de dado (Item, User, Reclamacao)
    ├── utilities/
    │   ├── auth.js             sessão (login, logout, requerLogin)
    │   └── tools.js            datas, iniciais, esc(), fotos…
    └── mocks/                dados de exemplo enquanto o backend não existe
        ├── items.js            dados iniciais
        ├── categorias.js, locais.js
        └── db.js               guarda as alterações no browser (localStorage)
```

## Como uma página funciona (exemplo: Início)

```
index.html            estrutura (título, pesquisa, selects, <div id="grelha">)
   ↓ <script type="module" src="js/pages/home.js">
js/pages/home.js      1. montarNavBar() e montarFooter()
                      2. pede os dados:  itemsService.getAll(filtros)
                      3. desenha:        grelha.innerHTML = items.map(cartaoItem)
                      4. ouve eventos:   clique num filtro → volta ao passo 2
   ↓
js/services/itemsService.js
   ├── USAR_MOCKS = true  → js/mocks/ (dados de exemplo)
   └── USAR_MOCKS = false → fetch('http://localhost:3000/api/items')
```

## Ligar ao backend

Em `js/services/api.js`:
1. `USAR_MOCKS = false`
2. `BASE_URL` com o endereço do backend (por defeito `http://localhost:3000/api`)

O backend tem de ter o **CORS ativo** (em Express: `app.use(require('cors')())`).

Endpoints usados pelo frontend (ver também `docs/api.md`):

| Pedido | Usado em |
|---|---|
| `GET /items?q=&categoria=&local=&ordem=` | Início |
| `GET /items/:id` | Detalhe |
| `POST /items` (multipart: campos + `fotos`) | Publicar |
| `PATCH /items/:id` `{ estado }` | Os meus anúncios |
| `GET /me/items` | Os meus anúncios |
| `POST /items/:id/reclamacoes` (multipart: `resposta` + `fotos`) | Detalhe |
| `GET /items/:id/reclamacoes` | Os meus anúncios |
| `GET /me/reclamacoes` | Detalhe (saber se já reclamei) |
| `PATCH /reclamacoes/:id` `{ estado: 'aceite' \| 'recusada' }` | Os meus anúncios |
| `GET /categorias`, `GET /locais` | Início, Publicar |
| `GET /me`, `PATCH /me` (multipart: `nome` + `foto`) | Perfil |
| `POST /auth/login` `{ email, password }` → `{ user, token }` | (futura página de login) |

## Dados de exemplo

Com `USAR_MOCKS = true`, a app entra automaticamente com a utilizadora de exemplo **Ana Silva**. Tudo o que publicas ou reclamas fica guardado no teu browser.

Para voltar aos dados iniciais, abre a consola (F12) e corre:

```js
localStorage.clear(); location.reload();
```

## Comparação com o AquaPoint

| AquaPoint (Vue) | Este projeto (HTML/JS) |
|---|---|
| `views/Home.vue` | `index.html` + `js/pages/home.js` + `assets/css/pages/home.css` |
| `components/NavBar.vue` | `js/components/navbar.js` + `assets/css/components/navbar.css` |
| `router/index.js` | cada página é um `.html`; os links são `<a href="…">` normais |
| `router.beforeEach` | `requerLogin()` em `js/utilities/auth.js` |
| `services/*.js` (axios) | `js/services/*.js` (fetch) |
| `models/*.js` | `js/models/*.js` |
| `utilities/useAuth.js` (Pinia) | `js/utilities/auth.js` (localStorage) |

## Por fazer

- [ ] Página de login (`login.html` + `authService.login`)
- [ ] Logótipo oficial do IADE em `assets/images/`
- [ ] Cores e fontes oficiais do IADE em `variables.css`
