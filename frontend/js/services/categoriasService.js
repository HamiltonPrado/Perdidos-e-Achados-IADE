import { api } from './api.js';

export const categoriasService = {
  // GET /api/categories  →  { categorias: [{ id, nome }] }
  // O id vem como número; passa a texto porque as páginas o comparam com o valor dos <select>.
  async getAll() {
    const { categorias } = await api.get('/categories');
    return categorias.map((c) => ({ id: String(c.id), nome: c.nome }));
  },
};
