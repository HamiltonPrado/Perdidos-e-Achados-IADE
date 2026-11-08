import { api } from './api.js';
import { Reclamacao } from '../models/Reclamacao.js';

// Converte uma reclamação da API para o formato usado nas páginas.
function doBackend(r) {
  return new Reclamacao({
    id: r.id,
    itemId: r.item_achado_id,
    resposta: r.resposta,
    informacao: r.informacao_adicional ?? '',
    estado: r.estado,
    criadoEm: r.criado_em,
  });
}

export const reclamacoesService = {
  // POST /api/items/:id/claims   { resposta, informacao_adicional }  →  { reclamacao }
  // O backend só aceita se o objeto estiver na receção e a equipa já tiver definido a pergunta.
  // Responde 409 se já existir uma reclamação desta pessoa para a mesma pergunta.
  async create(itemId, dados) {
    const { reclamacao } = await api.post(`/items/${encodeURIComponent(itemId)}/claims`, {
      resposta: dados.resposta,
      informacao_adicional: dados.informacao || null,
    });
    return doBackend({ ...reclamacao, resposta: dados.resposta, informacao_adicional: dados.informacao });
  },

  // GET /api/claims/mine  →  { reclamacoes: [...] }
  async getMinhas() {
    const { reclamacoes } = await api.get('/claims/mine');
    return reclamacoes.map(doBackend);
  },
};
