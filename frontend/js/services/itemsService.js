import { api, BASE_URL } from './api.js';
import { Item } from '../models/Item.js';
import { prepararFoto } from '../utilities/tools.js';

const urlFoto = (id) => `${BASE_URL}/photos/${id}`;

// Tira os filtros vazios para não enviar ?categoria=&local= à API.
function parametros(filtros) {
  const limpos = Object.entries(filtros).filter(([, v]) => v !== '' && v != null);
  return new URLSearchParams(limpos).toString();
}

export const itemsService = {
  // GET /api/items/found?q=&categoria=&local=&ordem=&pagina=
  //   →  { artigos: [...], paginacao: { pagina, por_pagina, total } }
  // Só achados já na receção (estado por_reclamar), 20 de cada vez. Exige sessão.
  async getPagina(filtros = {}, pagina = 1) {
    const { artigos, paginacao } = await api.get(`/items/found?${parametros({ ...filtros, pagina })}`);
    return {
      itens: artigos.map((a) => Item.doBackend(a, a.foto_id ? [urlFoto(a.foto_id)] : [])),
      pagina: paginacao.pagina,
      total: paginacao.total,
    };
  },

  // Só a primeira página (ex.: sugestões de objetos parecidos).
  async getAll(filtros = {}) {
    return (await this.getPagina(filtros, 1)).itens;
  },

  // GET /api/items/:id  →  { artigo }   +   GET /api/items/:id/photos  →  { fotos }
  // Devolve null se o artigo não existir ou não for visível para este utilizador.
  async getById(id) {
    try {
      const [{ artigo }, { fotos }] = await Promise.all([
        api.get(`/items/${encodeURIComponent(id)}`),
        api.get(`/items/${encodeURIComponent(id)}/photos`).catch(() => ({ fotos: [] })),
      ]);
      return Item.doBackend(artigo, fotos.map((f) => urlFoto(f.id)));
    } catch (erro) {
      if (erro.status === 404) return null;
      throw erro;
    }
  },

  // GET /api/items/mine  →  { artigos: [...] }  (perdidos e achados do utilizador)
  // Aqui só interessam os achados que a pessoa registou (Meus anúncios e contador da navbar).
  async getMine() {
    const { artigos } = await api.get('/items/mine');
    return artigos
      .filter((a) => a.tipo === 'found')
      .map((a) => Item.doBackend(a, a.foto_id ? [urlFoto(a.foto_id)] : []));
  },

  // POST /api/items/found (JSON)  →  { item: { id, referencia, … } }
  // depois, para cada foto: POST /api/items/:id/photos (multipart, campo "foto").
  // O achado fica "por_entregar" até a equipa confirmar a entrada na receção.
  async create(dados) {
    // Prepara as fotos antes de registar, para não ficar um objeto criado sem fotos
    // por causa de um ficheiro que o browser não consegue ler.
    const fotos = [];
    for (const [n, ficheiro] of dados.fotos.entries()) {
      fotos.push(await prepararFoto(ficheiro, n + 1));
    }

    const { item } = await api.post('/items/found', {
      titulo: dados.titulo,
      descricao: dados.descricao,
      category_id: Number(dados.categoriaId),
      location_id: Number(dados.localId),
      data_ocorrencia: dados.dataEncontrado,
    });

    for (const [n, foto] of fotos.entries()) {
      const formData = new FormData();
      formData.append('foto', foto, `foto-${n + 1}.jpg`);
      try {
        await api.post(`/items/${item.id}/photos`, formData);
      } catch (erro) {
        throw new Error(`O objeto foi registado (${item.referencia}), mas a foto ${n + 1} não foi enviada: ${erro.message}`);
      }
    }

    return new Item({ id: item.id, referencia: item.referencia, titulo: dados.titulo, estado: 'por_receber' });
  },
};
