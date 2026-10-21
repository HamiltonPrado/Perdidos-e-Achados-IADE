/*
 * services/authService.js — LOGIN
 * ------------------------------------------------------------
 * Igual ao authService.js do AquaPoint.
 * Depois do login, guardar a sessão com login(user, token) do auth.js.
 *
 * Exemplo (numa futura página login.html):
 *     const { user, token } = await authService.login(email, password);
 *     login(user, token);
 *     window.location.href = 'index.html';
 */

import { api } from './api.js';

export const authService = {
  // POST /auth/login   → { user, token }
  login: (email, password) => api.post('/auth/login', { email, password }),
};
