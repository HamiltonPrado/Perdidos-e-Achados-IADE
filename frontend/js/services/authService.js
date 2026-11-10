import { api } from './api.js';
import { login, logout } from '../utilities/auth.js';

export const authService = {
  // POST /api/login   { email, password }  →  { utilizador, csrf_token }
  // O servidor cria a sessão e devolve o cookie IADESESSID (HttpOnly, o JS não o lê).
  // Guardamos o utilizador (para a interface) e o csrf_token (para os pedidos de escrita).
  async entrar(email, password) {
    const { utilizador, csrf_token: csrf } = await api.post('/login', { email, password });
    login(utilizador, csrf);
    return utilizador;
  },

  // GET /api/me: confirma no servidor que a sessão guardada no browser ainda é válida e só
  // depois mostra a página (ver a classe «a-verificar» no index.html). Se a sessão acabou,
  // o api.js responde ao 401 mandando para o login, e a página nunca chega a aparecer.
  async confirmarSessao() {
    try {
      await api.get('/me');
    } catch (erro) {
      if (erro.status === 401) return false;
    }
    document.documentElement.classList.remove('a-verificar');
    return true;
  },

  // POST /api/logout  (leva o X-CSRF-Token)  →  o servidor apaga a sessão e o cookie
  async sair() {
    await api.post('/logout').catch(() => {});
    logout();
  },
};
