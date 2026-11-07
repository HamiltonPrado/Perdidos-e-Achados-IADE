import { api, USAR_MOCKS, esperar, BASE_URL } from './api.js';
import { Item } from '../models/Item.js';
import { db, guardar, novoId } from '../mocks/db.js';
import { pontosRecolha as mockPontos } from '../mocks/pontosRecolha.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem } from '../utilities/tools.js';
import { PRATELEIRAS, OCUPA_PRATELEIRA } from '../utilities/stock.js';

const normalizar = (texto = '') =>
  texto.normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();

export function respostaCoincide(resposta, esperada) {
  return !!esperada && normalizar(resposta) === normalizar(esperada);
}

function estadoDoBackend(artigo) {
  if (artigo.estado === 'devolvido') return 'devolvido';
  if (artigo.estado === 'arquivado') return 'arquivado';
  if (artigo.estado_recepcao === 'por_entregar') return 'por_receber';
  return 'por_reclamar';
}

function itemDoBackend(a) {
  return new Item({
    id: a.id,
    referencia: a.referencia,
    titulo: a.titulo,
    descricao: a.descricao,
    categoria: a.categoria,
    categoriaId: a.category_id,
    local: a.local,
    localId: a.location_id,
    dataEncontrado: a.data_ocorrencia,
    estado: estadoDoBackend(a),
    pergunta: a.pergunta_secreta,
    publicadoPor: a.reportado_por ? { nome: a.reportado_por } : null,
    respostaEsperada: a.resposta_esperada,
    localizacaoStock: a.localizacao_stock,
    observacoes: a.observacoes_internas,
    pontoRecolha: a.ponto_recolha,
    recebidoEm: a.recebido_em,
    criadoEm: a.criado_em,
  });
}

function pedidoDoBackend(r) {
  return {
    id: r.id,
    itemId: r.item_achado_id,
    objeto: r.achado_titulo,
    aluno: { id: r.user_id, nome: r.aluno_nome, email: r.aluno_email },
    pergunta: r.pergunta_secreta,
    resposta: r.resposta,
    respostaEsperada: r.resposta_esperada,
    informacao: r.informacao_adicional ?? '',
    fotos: [],
    estado: r.estado,
    motivo: r.motivo_decisao ?? '',
    pontoRecolha: r.ponto_recolha,
    criadoEm: r.criado_em,
  };
}

function pedidoDosMocks(r) {
  const item = db.items.find((i) => i.id === r.itemId) ?? {};
  return {
    id: r.id,
    itemId: r.itemId,
    objeto: item.titulo,
    referencia: item.referencia,
    foto: item.fotos?.[0] ?? null,
    local: item.local,
    aluno: r.reclamadoPor,
    pergunta: item.pergunta,
    resposta: r.resposta,
    respostaEsperada: item.respostaEsperada,
    informacao: r.informacao,
    fotos: (r.fotos ?? []).filter((f) => f.startsWith('data:') || f.includes('/assets/')),
    estado: r.estado,
    motivo: r.motivo ?? '',
    codigo: r.codigo ?? null,
    pontoRecolha: item.pontoRecolha,
    criadoEm: r.criadoEm,
  };
}

function devolucaoDosMocks(d) {
  const item = db.items.find((i) => i.id === d.itemId) ?? {};
  return { ...d, item: new Item(item) };
}

function gerarCodigo() {
  const letras = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  return Array.from({ length: 8 }, () => letras[Math.floor(Math.random() * letras.length)]).join('');
}

export const adminService = {
  // GET /admin/items
  async listarObjetos() {
    if (USAR_MOCKS) {
      await esperar();
      return [...db.items]
        .sort((a, b) => new Date(b.recebidoEm ?? b.dataEncontrado) - new Date(a.recebidoEm ?? a.dataEncontrado))
        .map((d) => new Item(d));
    }
    const { artigos } = await api.get('/admin/items');
    return artigos.filter((a) => a.tipo === 'found').map(itemDoBackend);
  },

  // GET /admin/items/:id
  async getObjeto(id) {
    if (USAR_MOCKS) {
      const item = db.items.find((i) => i.id === Number(id));
      return item ? new Item(item) : null;
    }
    const { artigo } = await api.get(`/admin/items/${id}`);
    const { fotos } = await api.get(`/items/${id}/photos`).catch(() => ({ fotos: [] }));
    const item = itemDoBackend(artigo);
    item.fotos = fotos.map((f) => `${BASE_URL}/photos/${f.id}`);
    return item;
  },

  // Procura pela referência que o aluno indica na receção (ex.: UE-0248).
  // GET /admin/items/:id  (o número da referência é o id do objeto no backend)
  async procurarPorReferencia(referencia) {
    const numero = Number(referencia.replace(/\D/g, ''));
    if (!numero) return null;
    if (USAR_MOCKS) {
      const item = db.items.find((i) => Number((i.referencia ?? '').replace(/\D/g, '')) === numero);
      return item ? new Item(item) : null;
    }
    return this.getObjeto(numero).catch(() => null);
  },

  // Prateleiras sem nenhum objeto guardado. Um objeto devolvido deixa de ocupar a sua.
  async prateleirasLivres() {
    const objetos = await this.listarObjetos();
    const ocupadas = new Set(
      objetos.filter((o) => OCUPA_PRATELEIRA.includes(o.estado)).map((o) => o.localizacaoStock)
    );
    return PRATELEIRAS.filter((p) => !ocupadas.has(p));
  },

  // GET /collection-points
  async listarPontosRecolha() {
    if (USAR_MOCKS) return mockPontos;
    const { pontos_recolha: pontos } = await api.get('/collection-points');
    return pontos;
  },

  // POST /admin/items/:id/receive
  async confirmarEntrada(id, { pontoRecolhaId, localizacaoStock, observacoes, pergunta, respostaEsperada }) {
    if (USAR_MOCKS) {
      await esperar();
      const item = db.items.find((i) => i.id === Number(id));
      if (!item || item.estado !== 'por_receber') throw new Error('O objeto já foi recebido ou não está por entregar.');
      Object.assign(item, {
        estado: 'por_reclamar',
        ondeEsta: 'secretaria',
        pontoRecolha: mockPontos.find((p) => p.id === Number(pontoRecolhaId))?.nome ?? '',
        localizacaoStock,
        observacoes,
        pergunta,
        respostaEsperada,
        recebidoEm: new Date().toISOString(),
      });
      guardar();
      return new Item(item);
    }
    const { item } = await api.post(`/admin/items/${id}/receive`, {
      ponto_recolha_id: Number(pontoRecolhaId),
      localizacao_stock: localizacaoStock,
      observacoes_internas: observacoes || null,
      pergunta_secreta: pergunta,
      resposta_esperada: respostaEsperada,
    });
    return this.getObjeto(item.id);
  },

  // POST /admin/items/found  (+ POST /items/:id/photos para cada foto)
  async publicarObjeto(dados, categorias, locais) {
    if (USAR_MOCKS) {
      await esperar();
      const id = novoId(db.items);
      const novo = {
        id,
        referencia: `UE-${String(240 + id).padStart(4, '0')}`,
        titulo: dados.titulo,
        descricao: dados.descricao,
        categoriaId: dados.categoriaId,
        categoria: categorias.find((c) => c.id === dados.categoriaId)?.nome ?? '',
        localId: dados.localId,
        local: locais.find((l) => l.id === dados.localId)?.nome ?? '',
        dataEncontrado: dados.dataEncontrado,
        pergunta: dados.pergunta,
        respostaEsperada: dados.respostaEsperada,
        pontoRecolha: mockPontos.find((p) => p.id === Number(dados.pontoRecolhaId))?.nome ?? '',
        localizacaoStock: dados.localizacaoStock,
        ondeEsta: 'secretaria',
        estado: 'por_reclamar',
        recebidoEm: new Date().toISOString(),
        publicadoPor: { id: getUser()?.id, nome: dados.entreguePor || 'Receção' },
        fotos: await Promise.all(dados.fotos.map((f) => lerImagem(f))),
      };
      db.items.unshift(novo);
      guardar();
      return new Item(novo);
    }
    const { item } = await api.post('/admin/items/found', {
      titulo: dados.titulo,
      descricao: dados.descricao,
      category_id: Number(dados.categoriaId),
      location_id: Number(dados.localId),
      data_ocorrencia: dados.dataEncontrado,
      ponto_recolha_id: Number(dados.pontoRecolhaId),
      pergunta_secreta: dados.pergunta,
      resposta_esperada: dados.respostaEsperada,
      localizacao_stock: dados.localizacaoStock || null,
    });
    for (const foto of dados.fotos) {
      const formData = new FormData();
      formData.append('foto', foto);
      await api.post(`/items/${item.id}/photos`, formData);
    }
    return this.getObjeto(item.id);
  },

  // GET /admin/claims
  async listarPedidos() {
    if (USAR_MOCKS) {
      await esperar();
      return [...db.reclamacoes]
        .sort((a, b) => new Date(b.criadoEm) - new Date(a.criadoEm))
        .map(pedidoDosMocks);
    }
    const { reclamacoes } = await api.get('/admin/claims');
    return reclamacoes.map(pedidoDoBackend);
  },

  async getPedido(id) {
    const pedidos = await this.listarPedidos();
    return pedidos.find((p) => p.id === Number(id)) ?? null;
  },

  // PATCH /admin/claims/:id   { estado: 'aprovada' | 'recusada', motivo_decisao }
  async decidirPedido(id, estado, motivo = '') {
    if (USAR_MOCKS) {
      await esperar();
      const pedido = db.reclamacoes.find((r) => r.id === Number(id));
      if (!pedido || pedido.estado !== 'pendente') throw new Error('Só é possível decidir pedidos pendentes.');
      const item = db.items.find((i) => i.id === pedido.itemId);
      pedido.estado = estado;
      pedido.motivo = motivo;
      pedido.decididoEm = new Date().toISOString();
      if (estado === 'aprovada') {
        pedido.codigo = gerarCodigo();
        if (item) item.estado = 'reclamado';
      }
      guardar();
      return pedidoDosMocks(pedido);
    }
    await api.patch(`/admin/claims/${id}`, { estado, motivo_decisao: motivo || null });
    return this.getPedido(id);
  },

  // GET /admin/collection-requests/:codigo
  async consultarCodigo(codigo) {
    const limpo = codigo.replace(/[\s-]/g, '').toUpperCase();
    if (USAR_MOCKS) {
      await esperar();
      const pedido = db.reclamacoes.find((r) => r.codigo === limpo && r.estado === 'aprovada');
      if (!pedido) throw new Error('Código inválido, expirado ou já utilizado.');
      const item = db.items.find((i) => i.id === pedido.itemId);
      return { codigo: limpo, aluno: pedido.reclamadoPor, objeto: item?.titulo, referencia: item?.referencia, pontoRecolha: item?.pontoRecolha, prateleira: item?.localizacaoStock };
    }
    const { pedido } = await api.get(`/admin/collection-requests/${encodeURIComponent(limpo)}`);
    const achado = await this.getObjeto(pedido.achado.id).catch(() => null);
    return { codigo: pedido.codigo, aluno: pedido.aluno, objeto: pedido.achado.titulo, pontoRecolha: pedido.ponto_recolha.nome, prateleira: achado?.localizacaoStock };
  },

  // POST /admin/collection-requests/:codigo/confirm
  async confirmarLevantamento(codigo) {
    const limpo = codigo.replace(/[\s-]/g, '').toUpperCase();
    if (USAR_MOCKS) {
      await esperar();
      const pedido = db.reclamacoes.find((r) => r.codigo === limpo && r.estado === 'aprovada');
      if (!pedido) throw new Error('Código inválido, expirado ou já utilizado.');
      const item = db.items.find((i) => i.id === pedido.itemId);
      pedido.estado = 'concluida';
      if (item) item.estado = 'devolvido';
      db.devolucoes ??= [];
      const devolucao = {
        id: novoId(db.devolucoes),
        itemId: pedido.itemId,
        entregueA: pedido.reclamadoPor,
        confirmadoPor: getUser()?.nome ?? 'Receção',
        pontoRecolha: item?.pontoRecolha ?? '',
        devolvidoEm: new Date().toISOString(),
      };
      db.devolucoes.push(devolucao);
      guardar();
      return devolucao.id;
    }
    const { devolucao } = await api.post(`/admin/collection-requests/${encodeURIComponent(limpo)}/confirm`);
    return devolucao.id;
  },

  // GET /admin/returns
  async listarDevolucoes() {
    if (USAR_MOCKS) {
      await esperar();
      return [...(db.devolucoes ?? [])]
        .sort((a, b) => new Date(b.devolvidoEm) - new Date(a.devolvidoEm))
        .map(devolucaoDosMocks);
    }
    const { devolucoes } = await api.get('/admin/returns');
    return devolucoes.map((d) => ({
      id: d.id,
      itemId: d.item_achado_id,
      item: new Item({ id: d.item_achado_id, titulo: d.achado_titulo, referencia: `ACH-${String(d.item_achado_id).padStart(6, '0')}` }),
      entregueA: { id: d.recebido_por, nome: d.recebido_por_nome },
      confirmadoPor: d.confirmado_por_nome,
      pontoRecolha: d.ponto_recolha,
      devolvidoEm: d.devolvido_em,
    }));
  },
};
