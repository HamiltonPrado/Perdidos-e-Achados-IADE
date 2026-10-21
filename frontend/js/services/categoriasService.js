/*
 * services/categoriasService.js — CATEGORIAS (Eletrónica, Chaves…)
 * ------------------------------------------------------------
 * Igual ao typesService.js do AquaPoint.
 */

import { api, USAR_MOCKS } from './api.js';
import { categorias as mockCategorias } from '../mocks/categorias.js';

export const categoriasService = {
  // GET /categorias   → [{ id, nome }]
  async getAll() {
    if (USAR_MOCKS) return mockCategorias;
    return api.get('/categorias');
  },
};
