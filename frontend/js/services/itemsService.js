/*
 * services/itemsService.js — OBJETOS ENCONTRADOS
 * ------------------------------------------------------------
 * Igual ao aquapointService.js do AquaPoint.
 * Todas as funções devolvem dados já convertidos com o model Item.
 *
 * Uso numa página:
 *     import { itemsService } from '../services/itemsService.js';
 *     const lista = await itemsService.getAll({ categoria: 'chaves' });
 */

import { api, USAR_MOCKS, esperar } from './api.js';
import { Item } from '../models/Item.js';
import { db, guardar, novoId } from '../mocks/db.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem, nomeCurto } from '../utilities/tools.js';

// Pesquisa, filtros e ordem — só com mocks (com o backend, é ele que filtra)
function filtrarMocks({ q = '', categoria = '', local = '', ordem = 'recentes' }) {
  const texto = q.trim().toLowerCase();

  const lista = db.items.filter(
    (i) =>
      i.estado === 'por_reclamar' &&
      (!categoria || i.categoriaId === categoria) &&
      (!local || i.localId === local) &&
      (!texto || `${i.titulo} ${i.descricao}`.toLowerCase().includes(texto))
  );

  return lista.sort((a, b) =>
    ordem === 'antigos'
      ? new Date(a.dataEncontrado) - new Date(b.dataEncontrado)
      : new Date(b.dataEncontrado) - new Date(a.dataEncontrado)
  );
}

export const itemsService = {
  // GET /items?q=&categoria=&local=&ordem=   → lista para a página inicial
  async getAll(filtros = {}) {
    if (USAR_MOCKS) {
      await esperar();
      return filtrarMocks(filtros).map((d) => new Item(d));
    }
    const dados = await api.get(`/items?${new URLSearchParams(filtros)}`);
    return dados.map((d) => new Item(d));
  },

  // GET /items/:id   → um objeto (página Detalhe). Devolve null se não existir.
  async getById(id) {
    if (USAR_MOCKS) {
      const encontrado = db.items.find((i) => i.id === Number(id));
      return encontrado ? new Item(encontrado) : null;
    }
    return new Item(await api.get(`/items/${id}`));
  },

  // GET /me/items   → os meus anúncios
  async getMine() {
    if (USAR_MOCKS) {
      const eu = getUser();
      return db.items
        .filter((i) => i.publicadoPor?.id === eu?.id)
        .sort((a, b) => new Date(b.dataEncontrado) - new Date(a.dataEncontrado))
        .map((d) => new Item(d));
    }
    const dados = await api.get('/me/items');
    return dados.map((d) => new Item(d));
  },

  /*
   * POST /items   → publicar um objeto
   * "dados" = { titulo, descricao, categoriaId, localId, dataEncontrado,
   *             ondeEsta, pergunta, fotos: [File, …] }
   */
  async create(dados, categorias = [], locais = []) {
    if (USAR_MOCKS) {
      const eu = getUser();
      const novo = {
        ...dados,
        id: novoId(db.items),
        categoria: categorias.find((c) => c.id === dados.categoriaId)?.nome ?? '',
        local: locais.find((l) => l.id === dados.localId)?.nome ?? '',
        estado: 'por_reclamar',
        publicadoPor: { id: eu.id, nome: nomeCurto(eu.nome) },
        fotos: await Promise.all(dados.fotos.map((f) => lerImagem(f))),
      };
      db.items.unshift(novo);
      guardar();
      return new Item(novo);
    }

    // Com o backend: os ficheiros vão num FormData (multipart)
    const formData = new FormData();
    Object.entries(dados).forEach(([chave, valor]) => {
      if (chave !== 'fotos') formData.append(chave, valor);
    });
    dados.fotos.forEach((f) => formData.append('fotos', f));
    return new Item(await api.post('/items', formData));
  },

  // PATCH /items/:id   → mudar o estado (ex.: { estado: 'devolvido' })
  async update(id, alteracoes) {
    if (USAR_MOCKS) {
      const item = db.items.find((i) => i.id === Number(id));
      Object.assign(item, alteracoes);
      guardar();
      return new Item(item);
    }
    return new Item(await api.patch(`/items/${id}`, alteracoes));
  },
};
