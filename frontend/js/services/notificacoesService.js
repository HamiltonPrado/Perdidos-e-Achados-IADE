import { api } from './api.js';
import { reclamacoesService } from './reclamacoesService.js';
import { getUser } from '../utilities/auth.js';

// As notificações do aluno vêm de duas fontes:
//  1. GET /api/claims/mine — a decisão da receção sobre cada reclamação (aprovada, recusada,
//     reaberta). O backend não grava uma notificação para isto, por isso o «lida» destas
//     fica guardado neste browser (localStorage), por utilizador.
//  2. GET /api/notifications — objetos parecidos com uma procura do aluno (correspondências).
//     Estas têm «lida» no backend (PATCH /api/notifications/:id/read).

const chaveLidas = () => `notificacoes-lidas:${getUser()?.id ?? ''}`;

function lerLidas() {
  try {
    return new Set(JSON.parse(localStorage.getItem(chaveLidas())) ?? []);
  } catch {
    return new Set();
  }
}

function guardarLidas(lidas) {
  try {
    localStorage.setItem(chaveLidas(), JSON.stringify([...lidas]));
  } catch {
  }
}

// A chave muda quando a reclamação muda de estado (ex.: reaberta e depois aprovada),
// para a nova decisão voltar a aparecer como não lida.
const chaveReclamacao = (r) => `reclamacao:${r.id}:${r.estado}`;

function daReclamacao(r, lidas) {
  const base = {
    chave: chaveReclamacao(r),
    origem: 'reclamacao',
    reclamacaoId: r.id,
    itemId: r.itemId,
    titulo: r.titulo,
    pontoRecolha: r.pontoRecolha,
    data: r.decididoEm ?? r.atualizadoEm ?? r.criadoEm,
  };

  if (r.estado === 'aprovada' && r.estadoArtigo === 'devolvido') {
    return { ...base, tipo: 'entregue', importante: false,
      assunto: 'Objeto levantado',
      texto: `Levantaste «${r.titulo}» na receção. Obrigado por usares o Perdidos & Achados.` };
  }
  if (r.estado === 'aprovada') {
    return { ...base, tipo: 'levantamento', importante: true,
      assunto: 'Levantamento autorizado',
      texto: `A tua resposta foi validada pela receção. «${r.titulo}» está pronto para levantamento.`,
      lida: lidas.has(base.chave) };
  }
  if (r.estado === 'recusada') {
    return { ...base, tipo: 'recusada', importante: true,
      assunto: 'Pedido recusado',
      texto: r.motivo
        ? `A receção não validou o teu pedido para «${r.titulo}»: ${r.motivo}`
        : `A receção não validou o teu pedido para «${r.titulo}».`,
      lida: lidas.has(base.chave) };
  }
  if (r.estado === 'reaberta') {
    return { ...base, tipo: 'reaberta', importante: true,
      assunto: 'Podes responder outra vez',
      texto: `A receção reabriu o teu pedido para «${r.titulo}». Envia uma nova resposta.`,
      lida: lidas.has(base.chave) };
  }
  if (r.estado === 'pendente') {
    return { ...base, tipo: 'pendente', importante: false, data: r.atualizadoEm ?? r.criadoEm,
      assunto: 'Pedido em análise',
      texto: `O teu pedido para «${r.titulo}» foi recebido e está em análise.` };
  }
  return null; // cancelada: não há nada a avisar
}

function daCorrespondencia(n) {
  return {
    chave: `correspondencia:${n.id}`,
    origem: 'correspondencia',
    notificacaoId: n.id,
    tipo: 'correspondencia',
    importante: true,
    lida: Boolean(Number(n.lida)),
    titulo: n.artigo_titulo,
    pontoRecolha: n.ponto_recolha,
    data: n.criado_em,
    assunto: 'Pode ser o teu objeto',
    texto: `Chegou à receção (${n.ponto_recolha}) um objeto parecido com «${n.artigo_titulo}».`,
  };
}

// Datas do MySQL ("2026-11-07 12:00:00") → Date, também em browsers que não aceitam o espaço.
export function paraData(texto) {
  return texto ? new Date(String(texto).replace(' ', 'T')) : null;
}

export const notificacoesService = {
  // Lista todas as notificações, das mais recentes para as mais antigas.
  // As «importantes» contam para o número de não lidas; as outras são só histórico.
  async getTodas() {
    const lidas = lerLidas();
    const [reclamacoes, correspondencias] = await Promise.all([
      reclamacoesService.getMinhas(),
      api.get('/notifications').then((d) => d.notificacoes ?? []).catch(() => []),
    ]);

    const lista = [
      ...reclamacoes.map((r) => daReclamacao(r, lidas)).filter(Boolean),
      ...correspondencias.map(daCorrespondencia),
    ];

    return lista
      .map((n) => ({ ...n, lida: n.importante ? Boolean(n.lida) : true }))
      .sort((a, b) => (paraData(b.data)?.getTime() ?? 0) - (paraData(a.data)?.getTime() ?? 0));
  },

  async marcarLidas(notificacoes) {
    const lidas = lerLidas();
    const pedidos = [];
    for (const n of notificacoes) {
      if (n.lida) continue;
      if (n.origem === 'reclamacao') lidas.add(n.chave);
      if (n.origem === 'correspondencia') {
        pedidos.push(api.patch(`/notifications/${n.notificacaoId}/read`).catch(() => {}));
      }
    }
    guardarLidas(lidas);
    await Promise.all(pedidos);
  },
};
