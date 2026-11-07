# Contrato da API

Este documento descreve as **38 rotas implementadas** em `backend/routes/grupos`. Os caminhos já incluem `/api`. A origem depende da configuração do Apache; por exemplo, `http://localhost:8888`. A porta do frontend não define a porta do backend.

**Só a equipa com o papel `admin` confirma a receção, define a pergunta e a resposta esperada, decide reclamações e confirma entregas.** Quem encontra o objeto apenas o regista e entrega à equipa.

## Autenticação e CSRF

A autenticação atual é local: `POST /api/login` valida uma conta em `users`, cria uma sessão na base de dados e devolve o cookie HttpOnly `IADESESSID`. A autenticação da universidade está prevista, mas ainda não está integrada. Não existe autenticação por Bearer token nem uma rota SSO implementada.

Login, com `Content-Type: application/json`:

```json
{
  "email": "aluno1@iade.pt",
  "password": "aluno123"
}
```

Resposta HTTP 200, com valores ilustrativos:

```json
{
  "estado": "ok",
  "mensagem": "Login efetuado.",
  "utilizador": {
    "id": 2,
    "nome": "Aluno 1",
    "email": "aluno1@iade.pt",
    "role": "user"
  },
  "csrf_token": "TOKEN_DEVOLVIDO_PELO_LOGIN"
}
```

Conserva o cookie e envia `X-CSRF-Token` com esse token em **todos os POST, PUT, PATCH e DELETE autenticados**, incluindo logout. O login é a exceção. `GET /api/me` consulta a conta, mas não devolve um novo token CSRF.

Exemplo no browser, na mesma origem da API:

```js
const resposta = await fetch('/api/login', {
  method: 'POST',
  credentials: 'same-origin',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
});
const sessao = await resposta.json();
if (!resposta.ok) throw new Error(sessao.erro);

const criacao = await fetch('/api/items/found', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': sessao.csrf_token
  },
  body: JSON.stringify(dadosDoObjeto)
});
```

O backend atual não configura CORS. Uma interface noutra origem precisa de integração explícita, por exemplo através de um proxy que mantenha a mesma origem. Acrescentar um Bearer token ao serviço do frontend não substitui a sessão.

## Inventário das rotas

`Sessão` significa aluno ou administrador autenticado. `Próprio` acrescenta a verificação do autor/dono da operação. Os identificadores `{id}` são inteiros positivos; `{codigo}` é um código de levantamento. Todas as escritas autenticadas exigem CSRF.

### Saúde, autenticação e referências

| Método | Caminho | Acesso | Resultado |
| --- | --- | --- | --- |
| GET | `/api/health` | Público | Estado do PHP, sem abrir a BD |
| GET | `/api/health/database` | Público | Verifica a ligação à BD |
| POST | `/api/login` | Público | Inicia sessão e devolve `csrf_token` |
| GET | `/api/me` | Sessão | `utilizador` autenticado |
| POST | `/api/logout` | Sessão | Termina a sessão |
| GET | `/api/admin/teste` | Admin | Verifica acesso da equipa |
| GET | `/api/categories` | Público | Lista `categorias` |
| GET | `/api/collection-points` | Público | Lista `pontos_recolha` |

### Artigos e fotografias

| Método | Caminho | Acesso | Resultado |
| --- | --- | --- | --- |
| POST | `/api/items/lost` | Sessão | Cria um perdido do autor |
| POST | `/api/items/found` | Sessão | Cria um achado `por_entregar` |
| GET | `/api/items/mine` | Próprio | Lista `artigos` do autor |
| GET | `/api/items/lost` | Sessão | Lista perdidos ativos |
| GET | `/api/items/found` | Sessão | Lista achados ativos na receção |
| GET | `/api/items/{id}` | Sessão, conforme âmbito | Detalhe em `artigo` |
| PATCH | `/api/items/{id}/resolve` | Autor do perdido | Marca como resolvido |
| GET | `/api/admin/items` | Admin | Inventário global em `artigos` |
| GET | `/api/admin/items/{id}` | Admin | Detalhe com dados internos |
| POST | `/api/admin/items/found` | Admin | Cria achado já na receção |
| PUT | `/api/admin/items/{id}` | Admin | Edita artigo ativo |
| PATCH | `/api/admin/items/{id}/archive` | Admin | Arquiva artigo |
| POST | `/api/admin/items/{id}/receive` | Admin | Confirma entrada e define verificação |
| POST | `/api/items/{id}/photos` | Admin ou autor autorizado | Adiciona fotografia |
| GET | `/api/items/{id}/photos` | Mesmo âmbito do artigo | Lista `fotos` |
| GET | `/api/photos/{id}` | Mesmo âmbito do artigo | Conteúdo binário da imagem |
| DELETE | `/api/photos/{id}` | Admin ou autor autorizado | Remove fotografia |

O autor pode adicionar/remover fotos dos seus perdidos ativos e dos seus achados ativos ainda `por_entregar`. Depois da receção, a alteração de fotos do achado fica reservada à equipa.

### Notificações, reclamações e entregas

| Método | Caminho | Acesso | Resultado |
| --- | --- | --- | --- |
| GET | `/api/notifications` | Próprio | Lista `notificacoes` |
| PATCH | `/api/notifications/{id}/read` | Próprio | Marca como lida |
| POST | `/api/notifications/{id}/collection-request` | Sessão | Rota antiga: devolve 409 após autenticação e CSRF |
| POST | `/api/items/{id}/claims` | Sessão | Cria ou reenvia reclamação elegível |
| GET | `/api/claims/mine` | Próprio | Lista `reclamacoes` |
| GET | `/api/admin/claims` | Admin | Lista reclamações e provas |
| PATCH | `/api/admin/claims/{id}` | Admin | Aprova ou recusa reclamação pendente |
| POST | `/api/admin/claims/{id}/reopen` | Admin | Reabre reclamação recusada elegível |
| POST | `/api/claims/{id}/collection-request` | Dono da reclamação | Emite/recupera código após aprovação |
| GET | `/api/admin/collection-requests/{codigo}` | Admin | Consulta código disponível |
| POST | `/api/admin/collection-requests/{codigo}/confirm` | Admin | Confirma entrega e utiliza código |
| POST | `/api/admin/items/{id}/return` | Admin | Regista devolução manual |
| GET | `/api/admin/returns` | Admin | Histórico em `devolucoes` |

## Artigos

### Referência de um perdido

Ao reportar uma perda, a API devolve `referencia` dentro de `item`, além do `id` que já existia. Por exemplo, um perdido com ID `37` recebe `PER-000037`:

```json
{
  "estado": "ok",
  "mensagem": "Objeto perdido registado.",
  "item": {
    "id": 37,
    "referencia": "PER-000037",
    "tipo": "lost",
    "estado": "ativo",
    "ia_estado": "pendente"
  }
}
```

O frontend pode mostrar **“Referência do teu registo: PER-000037”** na confirmação e nos detalhes do perdido. O backend fornece o campo; a interface terá de o apresentar.

A mesma referência aparece nos perdidos das listas `/api/items/mine`, `/api/items/lost` e `/api/admin/items`, nos detalhes `/api/items/{id}` e `/api/admin/items/{id}`, e ao resolver o perdido. Continua igual depois de editar ou mudar o estado. Também é calculada para registos antigos. Os achados recebidos pela equipa usam uma referência de inventário própria, descrita abaixo.

A referência é o ID com o prefixo `PER-` e pelo menos seis algarismos. IDs maiores não são cortados: `1000000` fica `PER-1000000`. Não é necessário acrescentar uma coluna à BD e o cliente não pode escolher o valor.

### Referência de inventário de um achado

Quando a equipa confirma a entrada por `POST /api/admin/items/{id}/receive`, a resposta inclui `item.referencia`, por exemplo `ACH-000242` para o achado de ID `242`. O registo direto por `POST /api/admin/items/found` também a devolve, porque o objeto já fica recebido na receção.

```json
{
  "estado": "ok",
  "mensagem": "Entrada na receção confirmada.",
  "item": {
    "id": 242,
    "referencia": "ACH-000242",
    "tipo": "found",
    "estado": "ativo",
    "estado_recepcao": "na_recepcao",
    "ponto_recolha_id": 1,
    "localizacao_stock": "Prateleira C1",
    "recebido_por": 1,
    "versao": 2,
    "ia_estado": "pendente"
  },
  "ia_agendada": true
}
```

A referência é calculada com o prefixo `ACH-` (de «achado») e pelo menos seis algarismos. IDs maiores não são cortados: `1000000` fica `ACH-1000000`. É o ID do artigo, não um contador separado de receções, pelo que pode haver intervalos na numeração.

Um achado `por_entregar` não tem este campo. Depois de ficar `na_recepcao`, a referência aparece no catálogo de achados, nos artigos próprios, no inventário da equipa e nos detalhes autorizados. Mantém-se após edição, arquivo ou devolução. Os achados antigos já marcados `na_recepcao` também a recebem, mesmo quando o registo antigo não tem a data ou o responsável pela receção preenchidos.

### Apresentar o ecrã «Objeto recebido na receção»

- Mostra o ecrã apenas depois de a operação administrativa ter sucesso. Usa `item.referencia`; se quiseres o aspeto do mockup, apresenta `ACH-000242` como **ACH · 000242**, preservando os algarismos.
- O botão «Ver objeto no inventário» usa o `item.id` numérico para abrir o detalhe administrativo. O backend não criou uma rota de pesquisa pelo texto da referência.
- Mostra a localização de stock apenas à equipa. Os dados internos continuam fora das respostas do catálogo dos alunos.
- «Disponível no site» corresponde ao achado ativo e `na_recepcao` que já pode aparecer no catálogo; não significa que já exista uma correspondência encontrada pela IA.
- Se `ia_agendada` for `true`, mostra **«Análise da IA agendada»**. O trabalho foi colocado na fila; a análise pode ainda estar pendente.
- Não marques **«Aluno notificado»** como concluído: esta operação não cria uma notificação de receção. As notificações de correspondência da IA pertencem a outro momento do processo.

**As referências PER e ACH identificam registos; não são senhas nem códigos de levantamento.** As permissões de consulta continuam a ser verificadas. As rotas continuam a receber o ID numérico, por exemplo `/api/items/37`; não foi criada uma pesquisa pelo texto `PER-000037` ou `ACH-000242`.

### Dados enviados no registo

Os POST de artigos e o PUT usam JSON. Campos básicos obrigatórios:

| Campo | Regra |
| --- | --- |
| `titulo` | Texto, 1 a 150 caracteres, uma linha |
| `descricao` | Texto, 1 a 5000 caracteres |
| `category_id` | Inteiro JSON positivo de categoria existente |
| `location_id` | Inteiro JSON positivo de local existente |
| `data_ocorrencia` | Data válida `AAAA-MM-DD`, não futura em Europe/Lisbon |

Exemplo para `POST /api/items/lost` ou `POST /api/items/found`:

```json
{
  "titulo": "Carteira preta",
  "descricao": "Carteira preta com fecho vermelho, na biblioteca.",
  "category_id": 3,
  "location_id": 2,
  "data_ocorrencia": "2026-10-30"
}
```

Os IDs são exemplos: usa os da tua BD, como números e não strings. Um perdido agenda a análise da versão 1. Um achado do aluno fica `por_entregar`, sem ponto de recolha, pergunta ou resposta esperada, e sem agendamento da IA nessa fase. Os campos de receção e verificação que o aluno tente acrescentar são ignorados.

Resposta HTTP 201 do achado do aluno:

```json
{
  "estado": "ok",
  "mensagem": "Objeto registado. Entrega-o à equipa para confirmar a entrada na receção.",
  "item": {
    "id": 38,
    "tipo": "found",
    "estado": "ativo",
    "estado_recepcao": "por_entregar"
  }
}
```

Em `POST /api/admin/items/found`, a equipa envia os campos básicos e também `ponto_recolha_id`, `pergunta_secreta` e `resposta_esperada`. O objeto entra diretamente na receção. O POST administrativo atual não guarda stock/observações; estes são preenchidos através da receção de um achado pendente ou da edição administrativa.

As listas de perdidos/achados aceitam `?pagina=1`: 20 artigos por página, página inteira entre 1 e 1000000. A resposta tem `estado`, `artigos` e `paginacao: {pagina, por_pagina}`. Pesquisa, categoria e local não são filtros implementados nestas rotas. A lista dos próprios artigos e o inventário administrativo não têm esta paginação.

Um utilizador autenticado pode consultar perdidos ativos e achados ativos já na receção. O autor e a equipa também consultam artigos fora desse catálogo dentro das suas permissões. O detalhe usa **`artigo`**; as respostas de criação usam **`item`**.

A pergunta pode aparecer como `pergunta_secreta` para o aluno responder. **`resposta_esperada`, `localizacao_stock` e `observacoes_internas` não são expostas ao aluno nas respostas públicas.** O detalhe administrativo inclui os dados internos da equipa.

### Confirmar a entrada

`POST /api/admin/items/{id}/receive`, com sessão admin e CSRF:

```json
{
  "ponto_recolha_id": 1,
  "localizacao_stock": "Prateleira A2",
  "observacoes_internas": "Objeto recebido e conferido pela equipa.",
  "pergunta_secreta": "Qual é a cor do fecho?",
  "resposta_esperada": "Vermelho"
}
```

Ponto, localização, pergunta e resposta esperada são obrigatórios. As observações são opcionais, até 1000 caracteres. A localização tem 1 a 100 caracteres numa linha; a pergunta tem 1 a 255 numa linha; a resposta esperada tem 1 a 500.

Exige achado ativo `por_entregar`. Guarda responsável/hora, passa para `na_recepcao`, incrementa a versão e agenda a IA na mesma transação. Sucesso: 200. Repetição com dados válidos: 409. Verificação em falta: 422, sem confirmar entrada.

### Editar, resolver e arquivar

`PUT /api/admin/items/{id}` exige os cinco campos básicos. Num achado exige pergunta e resposta esperada; ponto de recolha é obrigatório se já estiver na receção. Stock/observações opcionais são permitidos para achados na receção: omitir preserva o valor; `null` ou texto vazio limpa-o.

A resposta 200 inclui `alterado`, `item`, `ia_agendada`, `aprovacoes_revogadas` e `codigos_cancelados`. Repetir os mesmos dados devolve `alterado: false`. A nova versão, análise e invalidação de aprovações/códigos dependem dos campos alterados. Alterar apenas stock não equivale a alterar conteúdo analisado.

O autor resolve o seu perdido com `PATCH /api/items/{id}/resolve`. A equipa arquiva com `PATCH /api/admin/items/{id}/archive`. Não exigem um corpo JSON. Estas rotas não permitem ao aluno marcar um objeto como devolvido: esse estado resulta da entrega.

### Fotografias

`POST /api/items/{id}/photos` recebe **multipart/form-data**, com um ficheiro no campo **`foto`**. Aceita JPEG/PNG até 2 MiB. Envia cookie e CSRF; com `FormData`, deixa o browser definir o `Content-Type` e boundary.

As fotos são guardadas na BD. `GET /api/items/{id}/photos` devolve JSON; `GET /api/photos/{id}` devolve a imagem com o respetivo tipo de conteúdo. A consulta da imagem também depende da sessão e das permissões do artigo.

## Reclamações e levantamento

### Enviar e decidir

`POST /api/items/{id}/claims`, em que `{id}` é o achado:

```json
{
  "resposta": "Vermelho",
  "informacao_adicional": "Posso apresentar uma fotografia anterior do objeto.",
  "item_perdido_id": null
}
```

`resposta` é obrigatória, com 1 a 500 caracteres. `informacao_adicional` é opcional, até 1000. `item_perdido_id` é opcional e, quando preenchido, deve ser um perdido ativo do próprio aluno.

O achado precisa de estar ativo, na receção, com ponto e pergunta definidos. A primeira reclamação devolve 201 e fica `pendente`. A mesma conta não cria outra para o mesmo achado: repetir sem alterar a pergunta devolve 409, preservando a resposta original.

O mesmo endpoint permite reenviar com 200 quando a pergunta mudou numa reclamação elegível ou a equipa a reabriu. Reutiliza a reclamação e volta a colocá-la `pendente`. Uma recusa não permite tentativas livres: a equipa tem de autorizar a reabertura.

`PATCH /api/admin/claims/{id}`:

```json
{
  "estado": "aprovada",
  "motivo_decisao": "Prova conferida pela equipa."
}
```

Aceita `aprovada` ou `recusada`. O motivo tem até 1000 caracteres e é obrigatório na recusa. Só decide reclamações pendentes. A aprovação verifica disponibilidade, versão e pergunta atuais, perdido associado e ausência de outra reclamação aprovada para o achado.

`POST /api/admin/claims/{id}/reopen` reabre uma reclamação recusada elegível. O estado `reaberta` permite nova resposta do aluno, mas não constitui aprovação.

`GET /api/claims/mine` lista as reclamações da própria conta. `GET /api/admin/claims` permite à equipa comparar respostas, resposta esperada e dados atuais do objeto.

### Código e entrega

Após aprovação, o dono da reclamação chama `POST /api/claims/{id}/collection-request`, com cookie e CSRF, sem corpo obrigatório. Recebe 201 para um pedido novo ou 200 para um existente elegível:

```json
{
  "estado": "ok",
  "mensagem": "Código de levantamento disponível.",
  "pedido": {
    "id": 2,
    "reclamacao_id": 5,
    "codigo": "ABCD2345",
    "estado": "pendente",
    "expira_em": "2026-11-07 12:00:00",
    "item_achado_id": 38,
    "item_perdido_id": null,
    "ponto_recolha_id": 1
  }
}
```

Os valores são ilustrativos. O código tem 8 caracteres do conjunto `A-HJ-NP-Z2-9` e prazo de 24 horas. Repetir enquanto válido preserva código/prazo; um pedido expirado pode ser renovado se continuar elegível. Um código utilizado não é renovável.

A equipa consulta `GET /api/admin/collection-requests/{codigo}` e confirma presencialmente com `POST /api/admin/collection-requests/{codigo}/confirm`, com CSRF. A consulta aceita letras minúsculas, espaços e hífen. Formato inválido: 422; código indisponível na consulta: 404.

A confirmação válida devolve 201, marca o pedido `utilizado`, regista a devolução, marca o achado e o perdido associado, se existir, como `devolvido`, e cancela outros códigos pendentes dos artigos entregues. Perdido e correspondência de IA associados são opcionais. A segunda utilização devolve 409. As alterações são executadas numa transação.

Devolução manual: `POST /api/admin/items/{id}/return` exige `recebido_por`, identificador de uma conta ativa. `item_perdido_id` e `match_id` são opcionais; uma correspondência exige o perdido associado. Continua reservada à equipa e valida as associações antes de guardar. O histórico está em `GET /api/admin/returns`.

## Estados e erros

| Campo | Valores |
| --- | --- |
| `tipo` | `lost`, `found` |
| `estado` do artigo | `ativo`, `resolvido`, `devolvido`, `arquivado` |
| `estado_recepcao` | `por_entregar`, `na_recepcao`; `null` nos perdidos |
| `ia_estado` | `pendente`, `analisado`, `erro` |
| `estado` da reclamação | `pendente`, `aprovada`, `recusada`, `reaberta`, `cancelada` |
| `estado` do pedido | `pendente`, `utilizado`, `expirado`, `cancelado` |

`ia_estado: pendente` num achado por entregar não implica um trabalho na fila. A análise só é agendada após receção.

Erros JSON usam `{"erro": "Mensagem"}`. Verifica o código HTTP antes de tratar a resposta como sucesso.

| HTTP | Significado habitual |
| --- | --- |
| 400 | JSON inválido |
| 401 | Sessão ausente/inválida ou login incorreto |
| 403 | Papel sem permissão ou CSRF inválido |
| 404 | Rota/recurso não encontrado ou fora do âmbito |
| 409 | Estado incompatível, repetição bloqueada ou conflito |
| 415 | Tipo de conteúdo incorreto |
| 422 | Campos ou associações inválidos |
| 500 | Erro interno inesperado |
| 503 | Falha reportada no acesso à BD ou na criação de sessão |

Uma mensagem genérica de acesso à BD também pode resultar de SQL inválido. Consulta o log PHP para identificar a causa; não concluas apenas pela mensagem que o MySQL está desligado.
