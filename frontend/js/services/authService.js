import { BASE_URL } from './api.js';
import { USER_EXEMPLO } from '../utilities/auth.js';

export const authService = {
  urlLogin(voltar) {
    return `${BASE_URL}/auth/login?voltar=${encodeURIComponent(voltar)}`;
  },

  async loginSimulado() {
    return { user: USER_EXEMPLO, token: 'token-de-exemplo' };
  },
};
