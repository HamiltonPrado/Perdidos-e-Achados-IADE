import { url, caminhoAtual } from './caminhos.js';
import { User } from '../models/User.js';

// Qualquer email @iade.pt (alunos e equipa da receção).
export const EMAIL_IADE = /^[^\s@]+@iade\.pt$/i;

export function getUser() {
  try {
    const guardado = JSON.parse(localStorage.getItem('user'));
    return guardado ? new User(guardado) : null;
  } catch {
    return null;
  }
}

export function isLoggedIn() {
  return !!getUser();
}

// Depois do POST /api/login: guarda quem entrou e o token CSRF da sessão.
export function login(user, csrf) {
  localStorage.setItem('user', JSON.stringify(user));
  localStorage.setItem('csrf', csrf);
}

export function atualizarUser(user) {
  localStorage.setItem('user', JSON.stringify(user));
}

export function logout() {
  localStorage.removeItem('user');
  localStorage.removeItem('token');
  localStorage.removeItem('csrf');
}

export function isAdmin() {
  return isLoggedIn() && getUser()?.role === 'admin';
}

export function requerLogin() {
  if (isLoggedIn()) return true;
  const atual = caminhoAtual();
  const voltar = atual && atual !== 'index.html' ? `?voltar=${encodeURIComponent(atual)}` : '';
  window.location.replace(`${url('aluno/entrar.html')}${voltar}`);
  return false;
}

export function requerAdmin() {
  if (isAdmin()) return true;
  if (isLoggedIn()) {
    window.location.replace(url('index.html'));
    return false;
  }
  return requerLogin();
}
