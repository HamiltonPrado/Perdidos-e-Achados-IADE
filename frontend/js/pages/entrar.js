import { url } from '../utilities/caminhos.js';
import { authService } from '../services/authService.js';
import { api } from '../services/api.js';
import { isLoggedIn, isAdmin, logout, EMAIL_IADE } from '../utilities/auth.js';
import { lerParametro } from '../utilities/tools.js';
import { logoHtml } from '../components/logo.js';

const $ = (id) => document.getElementById(id);

$('entrar-logo').innerHTML = logoHtml();

let perfil = lerParametro('perfil') === 'admin' || lerParametro('voltar')?.startsWith('admin/') ? 'admin' : 'aluno';

// Receção vai sempre para a área admin/; aluno nunca vai para lá.
function destino() {
  const voltar = lerParametro('voltar') ?? '';
  const valido = /^((aluno|admin)\/)?[a-z-]+\.html(\?[\w=&%-]*)?$/.test(voltar);
  if (isAdmin()) return url(valido && voltar.startsWith('admin/') ? voltar : 'admin/index.html');
  return url(valido && !voltar.startsWith('admin/') ? voltar : 'index.html');
}

// O browser pode ter guardado um utilizador cuja sessão no servidor já acabou.
// Só salta o login se o servidor confirmar a sessão (GET /api/me).
if (isLoggedIn()) {
  try {
    await api.get('/me');
    window.location.replace(destino());
  } catch {
    logout();
  }
}

function mostrarPerfil() {
  const admin = perfil === 'admin';
  $('titulo').textContent = admin ? 'Área da receção' : 'Bem-vindo';
  $('email').placeholder = admin ? 'nome@iade.pt' : '20202030@iade.pt';
  $('trocar-perfil').textContent = admin ? '← Sou aluno' : 'És da equipa da receção? Entra aqui →';
  $('erro-email').hidden = true;
  $('erro-entrar').hidden = true;
}

$('trocar-perfil').addEventListener('click', () => {
  perfil = perfil === 'admin' ? 'aluno' : 'admin';
  mostrarPerfil();
});

$('form-entrar').addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = $('email').value.trim();
  const password = $('password').value;

  // Aluno ou receção: qualquer email @iade.pt. É o backend (campo role) que decide o perfil.
  const erroEmail = !email
    ? 'Escreve o teu email do IADE.'
    : !EMAIL_IADE.test(email)
      ? 'Usa o teu email do IADE (termina em @iade.pt).'
      : '';
  $('erro-email').textContent = erroEmail;
  $('erro-email').hidden = !erroEmail;
  $('erro-password').hidden = !!password;
  if (erroEmail) return $('email').focus();
  if (!password) return $('password').focus();

  $('botao-entrar').disabled = true;
  $('erro-entrar').hidden = true;
  try {
    const user = await authService.entrar(email, password);
    if (perfil === 'admin' && user.role !== 'admin') {
      logout();
      throw new Error('Esta conta não tem acesso à área da receção.');
    }
    window.location.href = destino();
  } catch (erro) {
    $('erro-entrar').textContent = erro.message;
    $('erro-entrar').hidden = false;
    $('botao-entrar').disabled = false;
  }
});

mostrarPerfil();
