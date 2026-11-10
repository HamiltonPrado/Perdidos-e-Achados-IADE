// Só o perfil (userService) ainda funciona sem backend: não há rota para editar a conta.
export const USAR_MOCKS = true;

import { logout } from '../utilities/auth.js';
import { url, caminhoAtual } from '../utilities/caminhos.js';

// Mesma origem que o frontend (o backend usa cookie de sessão e não tem CORS).
export const BASE_URL = '/api';

// A sessão no servidor acabou (expirou, browser fechado, servidor reiniciado), mas o browser
// ainda tinha o utilizador guardado: esquece-o e volta ao login, regressando depois a esta página.
function sessaoTerminada() {
  logout();
  const atual = caminhoAtual();
  if (atual.startsWith('aluno/entrar.html')) return;
  const parametros = new URLSearchParams();
  if (atual && atual !== 'index.html') parametros.set('voltar', atual);
  if (atual.startsWith('admin/')) parametros.set('perfil', 'admin');
  const consulta = parametros.toString();
  window.location.replace(`${url('aluno/entrar.html')}${consulta ? `?${consulta}` : ''}`);
}

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

  // 401 no login é só «password errada»; nas outras rotas quer dizer que a sessão acabou.
  if (resposta.status === 401 && caminho !== '/login' && caminho !== '/logout') {
    sessaoTerminada();
  }

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
