import { api, BASE_URL } from './api.js';
import { Item } from '../models/Item.js';
import { prepararFoto } from '../utilities/tools.js';
import { PRATELEIRAS, OCUPA_PRATELEIRA } from '../utilities/stock.js';

const normalizar = (texto = '') =>
  texto.normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();

export function respostaCoincide(resposta, esperada) {
  return !!esperada && normalizar(resposta) === normalizar(esperada);
}

const urlFoto = (id) => `${BASE_URL}/photos/${id}`;

// O inventário (/api/admin/items) traz os nomes (categoria, local, quem registou) e a 1.ª foto;
// várias funções precisam dele, por isso guarda-se o pedido durante a visita à página.
let inventario = null;
function carregarInventario() {
  inventario ??= api.get('/admin/items').then(({ artigos }) => artigos.filter((a) => a.tipo === 'found'));
  return inventario;
}
// Depois de uma alteração, o próximo pedido volta a ir ao servidor.
function esquecerInventario() {
  inventario = null;
}

function itemDoInventario(a) {
  return Item.doBackend(a, a.foto_id ? [urlFoto(a.foto_id)] : []);
}

// Reclamação da API → "pedido" usado nas páginas da receção.
// O artigo do inventário dá a referência, a foto e o local.
function pedidoDoBackend(r, artigo) {
  return {
    id: r.id,
    itemId: r.item_achado_id,
    objeto: r.achado_titulo,
    referencia: artigo?.referencia ?? null,
    foto: artigo?.foto_id ? urlFoto(artigo.foto_id) : null,
    local: artigo?.local ?? null,
    aluno: { id: r.user_id, nome: r.aluno_nome, email: r.aluno_email },
    pergunta: r.pergunta_secreta,
    resposta: r.resposta,
    respostaEsperada: r.resposta_esperada,
    informacao: r.informacao_adicional ?? '',
    fotos: [], // o backend não guarda fotos de prova
    // Depois da entrega, a reclamação continua "aprovada" mas o objeto passa a "devolvido".
    estado: r.estado === 'aprovada' && r.estado_artigo === 'devolvido' ? 'concluida' : r.estado,
    motivo: r.motivo_decisao ?? '',
    codigo: null, // o código é pedido pelo aluno depois da aprovação; a equipa não o vê aqui
    pontoRecolha: r.ponto_recolha,
    criadoEm: r.criado_em,
  };
}

export const adminService = {
  // GET /api/admin/items  →  { artigos }  (só os achados)
  async listarObjetos() {
    const artigos = await carregarInventario();
    return artigos.map(itemDoInventario);
  },

  // GET /api/admin/items/:id (pergunta, resposta, prateleira…) + inventário (nomes) + fotos.
  // Devolve null se o objeto não existir.
  async getObjeto(id) {
    try {
      const [{ artigo }, artigos, { fotos }] = await Promise.all([
        api.get(`/admin/items/${encodeURIComponent(id)}`),
        carregarInventario(),
        api.get(`/items/${encodeURIComponent(id)}/photos`).catch(() => ({ fotos: [] })),
      ]);
      if (artigo.tipo !== 'found') return null; // ex.: PER-000048 é um perdido, não um achado
      const daLista = artigos.find((a) => a.id === artigo.id) ?? {};
      return Item.doBackend({ ...daLista, ...artigo }, fotos.map((f) => urlFoto(f.id)));
    } catch (erro) {
      if (erro.status === 404) return null;
      throw erro;
    }
  },

  // A referência que o aluno indica na receção (ENT-000050) tem o mesmo número que o registo;
  // também aceita ACH-000050 ou só 50.
  async procurarPorReferencia(referencia) {
    const numero = Number(referencia.replace(/\D/g, ''));
    if (!numero) return null;
    return this.getObjeto(numero);
  },

  // Prateleiras sem nenhum objeto guardado. Um objeto devolvido deixa de ocupar a sua.
  async prateleirasLivres() {
    const objetos = await this.listarObjetos();
    const ocupadas = new Set(
      objetos.filter((o) => OCUPA_PRATELEIRA.includes(o.estado)).map((o) => o.localizacaoStock)
    );
    return PRATELEIRAS.filter((p) => !ocupadas.has(p));
  },

  // GET /api/collection-points  →  { pontos_recolha }
  async listarPontosRecolha() {
    const { pontos_recolha: pontos } = await api.get('/collection-points');
    return pontos;
  },

  // POST /api/admin/items/:id/receive — confirma a entrada e define a pergunta secreta.
  async confirmarEntrada(id, { pontoRecolhaId, localizacaoStock, observacoes, pergunta, respostaEsperada }) {
    const { item } = await api.post(`/admin/items/${encodeURIComponent(id)}/receive`, {
      ponto_recolha_id: Number(pontoRecolhaId),
      localizacao_stock: localizacaoStock,
      observacoes_internas: observacoes || null,
      pergunta_secreta: pergunta,
      resposta_esperada: respostaEsperada,
    });
    esquecerInventario();
    return this.getObjeto(item.id);
  },

  // POST /api/admin/items/found (JSON) + POST /api/items/:id/photos por cada foto.
  // O objeto fica logo "na receção" (a equipa recebe-o em mão).
  async publicarObjeto(dados) {
    const fotos = [];
    for (const [n, ficheiro] of dados.fotos.entries()) {
      fotos.push(await prepararFoto(ficheiro, n + 1));
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
      // O backend não tem campo para "entregue por": fica nas observações internas.
      observacoes_internas: dados.entreguePor ? `Entregue por: ${dados.entreguePor}` : null,
    });

    for (const [n, foto] of fotos.entries()) {
      const formData = new FormData();
      formData.append('foto', foto, `foto-${n + 1}.jpg`);
      try {
        await api.post(`/items/${item.id}/photos`, formData);
      } catch (erro) {
        throw new Error(`O objeto foi publicado (${item.referencia}), mas a foto ${n + 1} não foi enviada: ${erro.message}`);
      }
    }
    esquecerInventario();
    return new Item({ id: item.id, referencia: item.referencia, titulo: dados.titulo });
  },

  // GET /api/admin/claims  →  { reclamacoes }
  async listarPedidos() {
    const [{ reclamacoes }, artigos] = await Promise.all([api.get('/admin/claims'), carregarInventario()]);
    const porId = new Map(artigos.map((a) => [a.id, a]));
    return reclamacoes.map((r) => pedidoDoBackend(r, porId.get(r.item_achado_id)));
  },

  async getPedido(id) {
    const pedidos = await this.listarPedidos();
    return pedidos.find((p) => p.id === Number(id)) ?? null;
  },

  // PATCH /api/admin/claims/:id   { estado: 'aprovada' | 'recusada', motivo_decisao }
  async decidirPedido(id, estado, motivo = '') {
    await api.patch(`/admin/claims/${encodeURIComponent(id)}`, { estado, motivo_decisao: motivo || null });
    esquecerInventario();
    return this.getPedido(id);
  },

  // GET /api/admin/collection-requests/:codigo
  async consultarCodigo(codigo) {
    const limpo = codigo.replace(/[\s-]/g, '').toUpperCase();
    const { pedido } = await api.get(`/admin/collection-requests/${encodeURIComponent(limpo)}`);
    const achado = await this.getObjeto(pedido.achado.id).catch(() => null);
    return {
      codigo: pedido.codigo,
      aluno: pedido.aluno,
      objeto: pedido.achado.titulo,
      referencia: achado?.referencia,
      pontoRecolha: pedido.ponto_recolha.nome,
      prateleira: achado?.localizacaoStock,
    };
  },

  // POST /api/admin/collection-requests/:codigo/confirm
  async confirmarLevantamento(codigo) {
    const limpo = codigo.replace(/[\s-]/g, '').toUpperCase();
    const { devolucao } = await api.post(`/admin/collection-requests/${encodeURIComponent(limpo)}/confirm`);
    esquecerInventario();
    return devolucao.id;
  },

  // GET /api/admin/returns  →  { devolucoes }
  async listarDevolucoes() {
    const [{ devolucoes }, artigos] = await Promise.all([api.get('/admin/returns'), carregarInventario()]);
    const porId = new Map(artigos.map((a) => [a.id, a]));
    return devolucoes.map((d) => {
      const artigo = porId.get(d.item_achado_id);
      return {
        id: d.id,
        itemId: d.item_achado_id,
        item: artigo ? itemDoInventario(artigo) : new Item({ id: d.item_achado_id, titulo: d.achado_titulo }),
        entregueA: { id: d.recebido_por, nome: d.recebido_por_nome },
        confirmadoPor: d.confirmado_por_nome,
        pontoRecolha: d.ponto_recolha,
        devolvidoEm: d.devolvido_em,
      };
    });
  },
};
