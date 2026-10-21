/*
 * utilities/auth.js — SESSÃO DO UTILIZADOR (login / logout)
 * ------------------------------------------------------------
 * Igual ao utilities/useAuth.js do AquaPoint, mas sem Pinia.
 * O utilizador e o token ficam guardados no localStorage do browser,
 * por isso a sessão continua ao mudar de página ou recarregar.
 *
 * Uso:
 *     import { getUser, isLoggedIn, login, logout, requerLogin } from '../utilities/auth.js';
 *
 * ⚠️ Enquanto não há página de Login (USAR_MOCKS = true), a app entra
 *    automaticamente com um utilizador de exemplo (Ana Silva).
 */

import { USAR_MOCKS } from '../services/api.js';
import { User } from '../models/User.js';

export const USER_EXEMPLO = { id: 7, nome: 'Ana Silva', email: 'ana.silva@iade.pt', foto: null };

// Com mocks: se nunca se fez login nem logout, entra com o utilizador de exemplo
if (USAR_MOCKS && !localStorage.getItem('token') && localStorage.getItem('saiu') !== 'sim') {
  localStorage.setItem('user', JSON.stringify(USER_EXEMPLO));
  localStorage.setItem('token', 'token-de-exemplo');
}

// Utilizador com sessão iniciada (ou null)
export function getUser() {
  try {
    const guardado = JSON.parse(localStorage.getItem('user'));
    return guardado ? new User(guardado) : null;
  } catch {
    return null;
  }
}

// Há sessão iniciada?
export function isLoggedIn() {
  return !!localStorage.getItem('token');
}

// Chamar depois de o backend confirmar o login
export function login(user, token) {
  localStorage.setItem('user', JSON.stringify(user));
  localStorage.setItem('token', token);
  localStorage.removeItem('saiu');
}

// Atualizar nome/foto depois de guardar o Perfil
export function atualizarUser(user) {
  localStorage.setItem('user', JSON.stringify(user));
}

// "Terminar sessão"
export function logout() {
  localStorage.removeItem('user');
  localStorage.removeItem('token');
  localStorage.setItem('saiu', 'sim');
}

/*
 * requerLogin — chamar no topo das páginas que precisam de sessão
 * (Publicar, Os meus anúncios, Perfil). Se não houver sessão,
 * volta para a página inicial. Igual ao router.beforeEach do AquaPoint.
 */
export function requerLogin() {
  if (!isLoggedIn()) {
    window.location.replace('index.html');
    return false;
  }
  return true;
}
