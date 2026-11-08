// Só o perfil (userService) ainda funciona sem backend: não há rota para editar a conta.
export const USAR_MOCKS = true;

// Mesma origem que o frontend (o backend usa cookie de sessão e não tem CORS).
export const BASE_URL = '/api';

async function pedido(metodo, caminho, corpo) {
  const temFicheiros = corpo instanceof FormData;

  const csrf = localStorage.getItem('csrf');

  const headers = {};
  if (csrf && metodo !== 'GET') headers['X-CSRF-Token'] = csrf;
  if (corpo && !temFicheiros) headers['Content-Type'] = 'application/json';

  const resposta = await fetch(`${BASE_URL}${caminho}`, {
    method: metodo,
    headers,
    credentials: 'include',
    body: corpo ? (temFicheiros ? corpo : JSON.stringify(corpo)) : undefined,
  });

  const dados = await resposta.json().catch(() => ({}));
  if (!resposta.ok) {
    const erro = new Error(dados.erro ?? 'Ocorreu um erro. Tenta outra vez.');
    erro.status = resposta.status; // permite distinguir 401, 404, 422…
    throw erro;
  }
  return dados;
}

export const api = {
  get: (caminho) => pedido('GET', caminho),
  post: (caminho, corpo) => pedido('POST', caminho, corpo),
  put: (caminho, corpo) => pedido('PUT', caminho, corpo),
  patch: (caminho, corpo) => pedido('PATCH', caminho, corpo),
  delete: (caminho) => pedido('DELETE', caminho),
};
