# Perdidos & Achados — ligação front ↔ back

Atualizado a 2026-11-08.

O frontend já usa o backend PHP em todas as páginas, exceto o perfil. **As alterações ainda não estão commitadas**: até haver commit e push, só existem no computador onde foram feitas.

## Como pôr a correr

1. Ligar o MySQL do MAMP (a BD `perdidos_achados`; credenciais em `backend/config/credenciais.php`, copiado de `credenciais.example.php`).
2. Na raiz do projeto, arrancar o servidor do PHP com o router de desenvolvimento:

   ```bash
   /Applications/MAMP/bin/php/php8.4.17/bin/php -S localhost:8000 -t frontend backend/router-dev.php
   ```

3. Abrir `http://localhost:8000/aluno/entrar.html`.
4. Verificar o backend: `http://localhost:8000/api/health` e `/api/health/database`.

Contas de teste (podem ter sido alteradas na BD de cada um):

| Conta | Email | Password | Papel |
| --- | --- | --- | --- |
| Receção | `admin@iade.pt` | `admin123` | admin |
| Aluno 1 | `20202600@iade.pt` | `aluno123` | user |
| Aluno 2 | `2020@iade.pt` | `aluno123` | user |

## Como o front fala com o back

1. O JS chama a API através de `frontend/js/services/api.js` com `BASE_URL = '/api'` (caminho relativo).
2. O `backend/router-dev.php` recebe todos os pedidos do `php -S`: os que começam por `/api/` vão para `backend/public/index.php`; o resto é servido a partir de `frontend/`.
3. O `index.php` encaminha para as rotas em `backend/routes/grupos/`, que chamam os controllers.
4. **Front e back têm de estar na mesma origem** (`localhost:8000`): o backend usa sessão por cookie (`IADESESSID`, HttpOnly) e não tem CORS.
5. O login (`POST /api/login`) devolve o `utilizador` e um `csrf_token`. O front guarda os dois no localStorage; o token vai no cabeçalho `X-CSRF-Token` em todos os POST/PUT/PATCH/DELETE.
6. Quem decide se há sessão é o cookie no servidor; o localStorage serve só para a interface (nome, papel).
7. Os serviços convertem o formato da API (snake_case, ids numéricos) para o das páginas — ver `Item.doBackend()` em `frontend/js/models/Item.js`. Os ids de categoria e local passam a texto porque as páginas os comparam com o valor dos `<select>`.
8. Os erros da API chegam como `Error` com `erro.status` (401, 404, 422…).

Com o Apache do MAMP, em vez do `router-dev.php`, basta um `Alias /api` para `backend/public` (o `.htaccess` já existe).

## O que está ligado

| Área | Serviço JS | Rotas da API |
| --- | --- | --- |
| Login / logout | `authService.js` | `POST /api/login`, `POST /api/logout` |
| Categorias e locais | `categoriasService.js`, `locaisService.js` | `GET /api/categories`, `GET /api/locations` |
| Lista, filtros, «Carregar mais» | `itemsService.getPagina` | `GET /api/items/found?q=&categoria=&local=&ordem=&pagina=` |
| Detalhe de um objeto | `itemsService.getById` | `GET /api/items/{id}`, `GET /api/items/{id}/photos` |
| Publicar (aluno) | `itemsService.create` | `POST /api/items/found` + `POST /api/items/{id}/photos` |
| Meus anúncios, contador da navbar | `itemsService.getMine` | `GET /api/items/mine` (só os achados) |
| Reclamar, «já reclamei» | `reclamacoesService.js` | `POST /api/items/{id}/claims`, `GET /api/claims/mine` |
| Procuras (objetos perdidos) | `procurasService.js` | `POST /api/items/lost` + fotos |
| Área da receção | `adminService.js` | `/api/admin/items`, `/api/admin/claims`, `/api/admin/returns`, `/api/admin/collection-requests/{codigo}`, `/api/collection-points` |
| Perfil | `userService.js` | **sem backend** (ainda usa `USAR_MOCKS`) |

As fotos são convertidas no browser para JPEG de até 1600px antes do envio (`prepararFoto()` em `tools.js`), porque o backend só aceita JPEG/PNG até 2 MB. Datas vão sempre como `AAAA-MM-DD` (`dataParaApi()`).

## Alterações feitas no backend

| Ficheiro | Alteração | Porquê |
| --- | --- | --- |
| `routes/grupos/referencias.php` | Nova rota `GET /api/locations` | O controller já tinha `listarLocais()`, faltava a rota |
| `controllers/ItemController.php`, `models/item/ItemConsultas.php` | Filtros `q`, `categoria`, `local`, `ordem` e `paginacao.total` em `GET /api/items/found` | A página inicial filtrava só nos mocks |
| `models/item/ItemConsultas.php` | `foto_id` (1.ª foto) nas listas pública, «mine» e inventário | Mostrar foto nos cartões sem pedidos extra |
| `controllers/artigos/AdminItemRececao.php`, `models/item/ItemRegistos.php` | Publicar da receção grava `localizacao_stock` e `observacoes_internas` | A prateleira era ignorada |
| `models/Item.php`, `controllers/ItemController.php` | Referência provisória `ENT-000050` para achados por entregar | Passa a `ACH-000050` (mesmo número) quando a receção confirma a entrada |
| `router-dev.php` (novo) | Router para o `php -S` | Servir front e API na mesma origem |

O `docs/api.md` foi atualizado (39 rotas, `ENT-`).

## Decisões tomadas

| Decisão | Porquê | Para reverter |
| --- | --- | --- |
| Os mocks foram apagados (`frontend/js/mocks/`) | Já nenhuma página os usava | — |
| Página inicial e detalhe exigem login | O backend só mostra achados a quem tem sessão | Tornar as rotas públicas no backend |
| Descrição obrigatória no Publicar e nas procuras | O backend exige 1–5000 caracteres | Relaxar o `ItemValidator` |
| Retirado o campo «Fotos de prova» da reclamação | O backend não guarda fotos de reclamações | Nova tabela + rota de upload |
| Retirado o «Detalhe que só o proprietário conhece» das procuras | Não há campo; na descrição ficava visível a todos | Nova coluna só visível à equipa |
| Validação de email: qualquer `@iade.pt` | O papel (aluno/receção) vem do backend | — |
| «Entregue por» (publicar da receção) vai para as observações internas | Não há campo próprio | Nova coluna |
| Perfil sem alterações por agora | Não há rota para editar a conta nem coluna para a foto | `PATCH /api/me` |

Também foram corrigidos erros antigos: `entrar.js` e `publicar.js` escreviam em elementos que já não existiam no HTML e paravam a meio.

## Dados de teste na base de dados

| Registo | Estado |
| --- | --- |
| Objeto #46 «[TESTE] Garrafa azul» | Na receção, prateleira A1 (ACH-000046) |
| Objeto #47 «[TESTE] Chapéu preto» | Na receção, prateleira A3 (ACH-000047) |
| Procura #48 «[TESTE] Carteira castanha perdida» | Ativa (PER-000048) |
| Reclamação #7 (Aluno 1 → objeto #24) | Aprovada e entregue; o #24 ficou «devolvido» |
| Reclamação #8 (Aluno 1 → objeto #47) | Recusada |

Apagar antes de entregar o projeto.

## O que falta

- [ ] Página «As minhas reclamações» para o aluno: estado, motivo de recusa e botão «Obter código» (`POST /api/claims/{id}/collection-request`). Hoje o aluno não tem forma de obter o código de levantamento.
- [ ] Mostrar as procuras do aluno («As minhas procuras»).
- [ ] Verificar a sessão com `GET /api/me` ao abrir as páginas protegidas (sessão expira ao fim de 1h).
- [ ] Notificações (`GET /api/notifications`): o ecrã de sucesso da reclamação promete uma notificação que o front não mostra.
- [ ] Link «Denunciar anúncio» no detalhe não faz nada (`href="#"`).
- [ ] Aviso «já foi devolvido» aparece também para objetos arquivados.
- [ ] Perfil: decidir se fica só de leitura ou se se cria `PATCH /api/me`.
- [ ] Apagar os dados `[TESTE]` da BD.
