import { User } from '../models/User.js';

export const USER_EXEMPLO = { id: 7, nome: 'Ana Silva', email: 'ana.silva@universidadeeuropeia.pt', foto: null };

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
}

export function requerLogin() {
  if (isLoggedIn()) return true;
  const aqui = window.location.pathname.split('/').pop() + window.location.search;
  window.location.replace(`entrar.html?voltar=${encodeURIComponent(aqui)}`);
  return false;
}
