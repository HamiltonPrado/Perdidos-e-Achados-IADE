import { url, caminhoAtual } from './caminhos.js';
import { User } from '../models/User.js';

export const USER_EXEMPLO = { id: 7, nome: 'Ana Silva', email: '20242038@iade.pt', foto: null, role: 'user' };
export const ADMIN_EXEMPLO = { id: 1, nome: 'Equipa da receção', email: 'rececao@iade.pt', foto: null, role: 'admin' };

// Aluno: número de estudante (8 dígitos) + @iade.pt, ex.: 20202030@iade.pt
export const EMAIL_ALUNO = /^\d{8}@iade\.pt$/i;
// Equipa da receção: qualquer email @iade.pt
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
  return !!localStorage.getItem('token');
}

export function login(user, token) {
  localStorage.setItem('user', JSON.stringify(user));
  localStorage.setItem('token', token);
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
  window.location.replace(`${url('aluno/entrar.html')}?voltar=${encodeURIComponent(caminhoAtual())}`);
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
