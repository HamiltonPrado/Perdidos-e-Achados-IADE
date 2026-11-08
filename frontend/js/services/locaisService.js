import { api } from './api.js';

export const locaisService = {
  // GET /api/locations  →  { locais: [{ id, nome }] }
  // O id vem como número; passa a texto porque as páginas o comparam com o valor dos <select>.
  async getAll() {
    const { locais } = await api.get('/locations');
    return locais.map((l) => ({ id: String(l.id), nome: l.nome }));
  },
};
