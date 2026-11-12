import { api, BASE_URL } from './api.js';
import { Procura } from '../models/Procura.js';
import { Item } from '../models/Item.js';
import { prepararFoto } from '../utilities/tools.js';

// Sugestões da IA no site. Desligadas: o serviço «ia» do Docker também está desligado.
export const IA_ATIVA = false;

export const procurasService = {
  // POST /api/items/lost (JSON)  →  { item: { id, referencia: 'PER-000037', … } }
  // depois, para cada foto: POST /api/items/:id/photos (multipart, campo "foto").
  // Um perdido é visível a todos os alunos com sessão; a IA compara-o com os achados.
  async create(dados) {
    // Prepara as fotos antes de registar, para não ficar uma procura criada sem fotos
    // por causa de um ficheiro que o browser não consegue ler.
    const fotos = [];
    for (const [n, ficheiro] of dados.fotos.entries()) {
      fotos.push(await prepararFoto(ficheiro, n + 1));
    }

    const { item } = await api.post('/items/lost', {
      titulo: dados.titulo,
      descricao: dados.descricao,
      category_id: Number(dados.categoriaId),
      location_id: Number(dados.localId),
      data_ocorrencia: dados.dataPerdido,
    });

    for (const [n, foto] of fotos.entries()) {
      const formData = new FormData();
      formData.append('foto', foto, `foto-${n + 1}.jpg`);
      try {
        await api.post(`/items/${item.id}/photos`, formData);
      } catch (erro) {
        throw new Error(`A procura foi registada (${item.referencia}), mas a foto ${n + 1} não foi enviada: ${erro.message}`);
      }
    }

    return Object.assign(
      new Procura({ id: item.id, titulo: dados.titulo, categoriaId: dados.categoriaId, localId: dados.localId }),
      { referencia: item.referencia }
    );
  },

  // GET /api/items/mine  →  { artigos: [...] }; aqui só interessam os perdidos ainda ativos.
  async getMinhas() {
    const { artigos } = await api.get('/items/mine');
    return artigos
      .filter((a) => a.tipo === 'lost' && a.estado === 'ativo')
      .map((a) => Object.assign(
        new Procura({
          id: String(a.id),
          titulo: a.titulo,
          descricao: a.descricao,
          categoria: a.categoria,
          local: a.local,
          dataPerdido: a.data_ocorrencia,
          criadoEm: a.criado_em,
        }),
        { referencia: a.referencia }
      ));
  },

  // PATCH /api/items/:id/resolve  →  o perdido deixa de estar ativo e sai das comparações da IA.
  async resolver(id) {
    await api.patch(`/items/${encodeURIComponent(id)}/resolve`);
  },

  // GET /api/items/:id/matches  →  { ia_estado: 'pendente'|'analisado'|'erro', sugestoes: [...] }
  // Achados na receção que a IA achou parecidos com esta procura, do mais parecido para o menos.
  async sugestoes(id) {
    if (!IA_ATIVA) return { estadoIa: 'desativada', itens: [] };
    const { ia_estado, sugestoes } = await api.get(`/items/${encodeURIComponent(id)}/matches`);
    return {
      estadoIa: ia_estado,
      itens: sugestoes.map((a) => Object.assign(
        Item.doBackend(a, a.foto_id ? [`${BASE_URL}/photos/${a.foto_id}`] : []),
        { semelhanca: Number(a.score) }
      )),
    };
  },
};
