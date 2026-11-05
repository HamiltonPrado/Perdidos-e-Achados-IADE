import { api, USAR_MOCKS } from './api.js';
import { Reclamacao } from '../models/Reclamacao.js';
import { db, guardar, novoId } from '../mocks/db.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem, nomeCurto } from '../utilities/tools.js';

export const reclamacoesService = {
  async create(itemId, dados) {
    if (USAR_MOCKS) {
      const eu = getUser();
      const nova = {
        id: novoId(db.reclamacoes),
        itemId: Number(itemId),
        resposta: dados.resposta,
        informacao: dados.informacao ?? '',
        fotos: await Promise.all(dados.fotos.map((f) => lerImagem(f, 600))),
        estado: 'pendente',
        criadoEm: new Date().toISOString(),
        reclamadoPor: { id: eu.id, nome: nomeCurto(eu.nome) },
      };
      db.reclamacoes.push(nova);
      guardar();
      return new Reclamacao(nova);
    }

    const formData = new FormData();
    formData.append('resposta', dados.resposta);
    formData.append('informacao', dados.informacao ?? '');
    dados.fotos.forEach((f) => formData.append('fotos', f));
    return new Reclamacao(await api.post(`/items/${itemId}/reclamacoes`, formData));
  },

  // GET /items/:id/reclamacoes
  async getByItem(itemId) {
    if (USAR_MOCKS) {
      return db.reclamacoes
        .filter((r) => r.itemId === Number(itemId))
        .map((d) => Object.assign(new Reclamacao(d), { legendas: d.legendas ?? [] }));
    }
    const dados = await api.get(`/items/${itemId}/reclamacoes`);
    return dados.map((d) => new Reclamacao(d));
  },

  // GET /me/reclamacoes
  async getMinhas() {
    if (USAR_MOCKS) {
      const eu = getUser();
      return db.reclamacoes.filter((r) => r.reclamadoPor?.id === eu?.id).map((d) => new Reclamacao(d));
    }
    const dados = await api.get('/me/reclamacoes');
    return dados.map((d) => new Reclamacao(d));
  },

  async responder(id, estado) {
    if (USAR_MOCKS) {
      const r = db.reclamacoes.find((x) => x.id === Number(id));
      r.estado = estado;
      if (estado === 'aceite') {
        db.items.find((i) => i.id === r.itemId).estado = 'reclamado';
      }
      guardar();
      return new Reclamacao(r);
    }
    return new Reclamacao(await api.patch(`/reclamacoes/${id}`, { estado }));
  },
};
