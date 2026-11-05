export const USAR_MOCKS = true;

export const BASE_URL = 'http://localhost:3000/api';

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

export const api = {
  get: (caminho) => pedido('GET', caminho),
  post: (caminho, corpo) => pedido('POST', caminho, corpo),
  put: (caminho, corpo) => pedido('PUT', caminho, corpo),
  patch: (caminho, corpo) => pedido('PATCH', caminho, corpo),
  delete: (caminho) => pedido('DELETE', caminho),
};

export const esperar = (ms = 250) => new Promise((r) => setTimeout(r, ms));
