import { api, USAR_MOCKS, esperar } from './api.js';
import { USER_EXEMPLO, ADMIN_EXEMPLO, EMAIL_ALUNO, login, logout } from '../utilities/auth.js';

export const authService = {
  // POST /login   { email, password }  →  { utilizador, csrf_token }
  async entrar(email, password, perfil = 'aluno') {
    if (USAR_MOCKS) {
      await esperar();
      // Na demonstração, um email de aluno nunca é da receção (no backend é o campo role que decide)
      const base = perfil === 'admin' && !EMAIL_ALUNO.test(email) ? ADMIN_EXEMPLO : USER_EXEMPLO;
      const user = { ...base, email: email.toLowerCase() };
      login(user, 'sessao-demo');
      return user;
    }
    const { utilizador, csrf_token: csrf } = await api.post('/login', { email, password });
    login(utilizador, 'sessao');
    localStorage.setItem('csrf', csrf);
    return utilizador;
  },

  // POST /logout
  async sair() {
    if (!USAR_MOCKS) await api.post('/logout').catch(() => {});
    logout();
  },
};
