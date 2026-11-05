import { api, USAR_MOCKS } from './api.js';
import { locais as mockLocais } from '../mocks/locais.js';

export const locaisService = {
  // GET /locais
  async getAll() {
    if (USAR_MOCKS) return mockLocais;
    return api.get('/locais');
  },
};
