import { url } from '../utilities/caminhos.js';
import { USAR_MOCKS } from '../services/api.js';
import { authService } from '../services/authService.js';
import { isLoggedIn, isAdmin, logout, EMAIL_ALUNO, EMAIL_IADE } from '../utilities/auth.js';
import { lerParametro } from '../utilities/tools.js';
import { logoHtml } from '../components/logo.js';

const $ = (id) => document.getElementById(id);

$('entrar-logo').innerHTML = logoHtml();
$('aviso-simulado').hidden = !USAR_MOCKS;

let perfil = lerParametro('perfil') === 'admin' || lerParametro('voltar')?.startsWith('admin/') ? 'admin' : 'aluno';

// Receção vai sempre para a área admin/; aluno nunca vai para lá.
function destino() {
  const voltar = lerParametro('voltar') ?? '';
  const valido = /^((aluno|admin)\/)?[a-z-]+\.html(\?[\w=&%-]*)?$/.test(voltar);
  if (isAdmin()) return url(valido && voltar.startsWith('admin/') ? voltar : 'admin/index.html');
  return url(valido && !voltar.startsWith('admin/') ? voltar : 'index.html');
}

if (isLoggedIn()) {
  window.location.replace(destino());
}

function mostrarPerfil() {
  const admin = perfil === 'admin';
  $('titulo').textContent = admin ? 'Área da receção' : 'Bem-vindo';
  $('texto').textContent = admin
    ? 'Entra com o email do IADE da equipa da receção.'
    : 'Entra com o teu email do IADE para publicar e reclamar objetos.';
  $('email').placeholder = admin ? 'nome@iade.pt' : '20202030@iade.pt';
  $('ajuda-email').textContent = admin ? 'O email do IADE da equipa.' : 'O teu número de estudante seguido de @iade.pt.';
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

  const regra = perfil === 'admin' ? EMAIL_IADE : EMAIL_ALUNO;
  const erroEmail = !email
    ? 'Escreve o teu email do IADE.'
    : !regra.test(email)
      ? (perfil === 'admin' ? 'Usa um email @iade.pt.' : 'Usa o teu número de estudante seguido de @iade.pt (ex.: 20202030@iade.pt).')
      : '';
  $('erro-email').textContent = erroEmail;
  $('erro-email').hidden = !erroEmail;
  $('erro-password').hidden = !!password;
  if (erroEmail) return $('email').focus();
  if (!password) return $('password').focus();

  $('botao-entrar').disabled = true;
  $('erro-entrar').hidden = true;
  try {
    const user = await authService.entrar(email, password, perfil);
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
