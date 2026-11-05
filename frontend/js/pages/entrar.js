import { USAR_MOCKS } from '../services/api.js';
import { authService } from '../services/authService.js';
import { login, isLoggedIn } from '../utilities/auth.js';
import { lerParametro } from '../utilities/tools.js';
import { logoHtml } from '../components/logo.js';

const $ = (id) => document.getElementById(id);

$('entrar-logo').innerHTML = logoHtml();
$('aviso-simulado').hidden = !USAR_MOCKS;

function destino() {
  const voltar = lerParametro('voltar') ?? '';
  return /^[a-z-]+\.html(\?[\w=&%-]*)?$/.test(voltar) ? voltar : 'index.html';
}

if (isLoggedIn()) {
  window.location.replace(destino());
}

const tokenRecebido = lerParametro('token');
if (tokenRecebido) {
  try {
    login(JSON.parse(lerParametro('user')), tokenRecebido);
    window.location.replace(destino());
  } catch {
    $('erro-entrar').textContent = 'Não foi possível concluir o login. Tenta outra vez.';
    $('erro-entrar').hidden = false;
  }
}

$('botao-entrar').addEventListener('click', async () => {
  if (USAR_MOCKS) {
    const { user, token } = await authService.loginSimulado();
    login(user, token);
    window.location.href = destino();
    return;
  }
  window.location.href = authService.urlLogin(destino());
});
