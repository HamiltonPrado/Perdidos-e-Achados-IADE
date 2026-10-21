/*
 * services/api.js — LIGAÇÃO BASE AO BACKEND
 * ------------------------------------------------------------
 * Igual ao services/api.js do AquaPoint (lá usam axios; aqui usamos
 * o fetch, que já vem no browser e não precisa de instalar nada).
 *
 * Os outros services (itemsService, userService…) usam isto:
 *     api.get('/items')
 *     api.post('/items', dados)
 *
 * As páginas NUNCA usam o api.js diretamente — usam os services.
 */

// ⚙️ true  → os services usam os dados de exemplo de js/mocks/
//    false → os services fazem pedidos reais ao backend
export const USAR_MOCKS = true;

// ⚙️ Endereço do backend do teu colega.
//    O backend tem de ter o CORS ativo (ver backend/README.md).
export const BASE_URL = 'http://localhost:3000/api';

/*
 * Faz um pedido HTTP e devolve os dados (JSON) da resposta.
 * - Envia o token de sessão (se existir) no cabeçalho Authorization.
 * - Se o corpo for FormData (com fotos), envia-o tal como está.
 * - Se o backend responder com erro, lança a mensagem { erro } dele.
 */
async function pedido(metodo, caminho, corpo) {
  const token = localStorage.getItem('token');
  const temFicheiros = corpo instanceof FormData;

  const headers = {};
  if (token) headers.Authorization = `Bearer ${token}`;
  if (corpo && !temFicheiros) headers['Content-Type'] = 'application/json';

  const resposta = await fetch(`${BASE_URL}${caminho}`, {
    method: metodo,
    headers,
    body: corpo ? (temFicheiros ? corpo : JSON.stringify(corpo)) : undefined,
  });

  const dados = await resposta.json().catch(() => ({}));
  if (!resposta.ok) throw new Error(dados.erro ?? 'Ocorreu um erro. Tenta outra vez.');
  return dados;
}

// Atalhos — mesmo estilo do axios usado no AquaPoint
export const api = {
  get: (caminho) => pedido('GET', caminho),
  post: (caminho, corpo) => pedido('POST', caminho, corpo),
  put: (caminho, corpo) => pedido('PUT', caminho, corpo),
  patch: (caminho, corpo) => pedido('PATCH', caminho, corpo),
  delete: (caminho) => pedido('DELETE', caminho),
};

// Só para os mocks: simula a demora da rede (para se ver o "A carregar…")
export const esperar = (ms = 250) => new Promise((r) => setTimeout(r, ms));
