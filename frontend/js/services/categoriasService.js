import { api, USAR_MOCKS } from './api.js';
import { categorias as mockCategorias } from '../mocks/categorias.js';

export const categoriasService = {
  // GET /categorias
  async getAll() {
    if (USAR_MOCKS) return mockCategorias;
    return api.get('/categorias');
  },
};
