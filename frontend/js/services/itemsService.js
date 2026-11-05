import { api, USAR_MOCKS, esperar } from './api.js';
import { Item } from '../models/Item.js';
import { db, guardar, novoId } from '../mocks/db.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem, nomeCurto } from '../utilities/tools.js';

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
  // GET /items?q=&categoria=&local=&ordem=
  async getAll(filtros = {}) {
    if (USAR_MOCKS) {
      await esperar();
      return filtrarMocks(filtros).map((d) => new Item(d));
    }
    const dados = await api.get(`/items?${new URLSearchParams(filtros)}`);
    return dados.map((d) => new Item(d));
  },

  // GET /items/:id
  async getById(id) {
    if (USAR_MOCKS) {
      const encontrado = db.items.find((i) => i.id === Number(id));
      return encontrado ? new Item(encontrado) : null;
    }
    return new Item(await api.get(`/items/${id}`));
  },

  // GET /me/items
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

  async create(dados, categorias = [], locais = []) {
    if (USAR_MOCKS) {
      const eu = getUser();
      const id = novoId(db.items);
      const novo = {
        ...dados,
        id,
        referencia: `UE-${String(240 + id).padStart(4, '0')}`,
        ondeEsta: 'com_quem_encontrou',
        categoria: categorias.find((c) => c.id === dados.categoriaId)?.nome ?? '',
        local: locais.find((l) => l.id === dados.localId)?.nome ?? '',
        estado: 'por_receber',
        publicadoPor: { id: eu.id, nome: nomeCurto(eu.nome) },
        fotos: await Promise.all(dados.fotos.map((f) => lerImagem(f))),
      };
      db.items.unshift(novo);
      guardar();
      return new Item(novo);
    }

    const formData = new FormData();
    Object.entries(dados).forEach(([chave, valor]) => {
      if (chave !== 'fotos') formData.append(chave, valor);
    });
    dados.fotos.forEach((f) => formData.append('fotos', f));
    return new Item(await api.post('/items', formData));
  },

  // PATCH /items/:id
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
