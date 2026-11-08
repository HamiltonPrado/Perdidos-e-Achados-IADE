import { api } from './api.js';
import { reclamacoesService } from './reclamacoesService.js';

// As notificações do aluno vêm do backend e ficam guardadas na base de dados, por conta:
// sobrevivem a um refresh e aparecem iguais noutro browser ou dispositivo.
//  - GET /api/notifications devolve dois tipos de registo (campo `tipo`):
//      correspondencia                                   → a IA encontrou um objeto parecido com uma procura
//      pedido_aprovado · pedido_recusado · pedido_reaberto → decisão da receção sobre uma reclamação
//    O backend cria as de pedido quando a receção decide e arquiva-as quando o objeto é entregue
//    (ficam no histórico, com `arquivada_em`).
//  - PATCH /api/notifications/:id/read marca qualquer uma como lida.
//  - GET /api/claims/mine só serve para os pedidos ainda em análise (sem decisão, sem notificação)
//    e para saber se um objeto aprovado já foi levantado.

function daDecisao(n, estadoArtigo) {
  const base = {
    chave: `notificacao:${n.id}`,
    notificacaoId: n.id,
    reclamacaoId: n.reclamacao_id,
    itemId: n.item_achado_id,
    titulo: n.artigo_titulo,
    pontoRecolha: n.ponto_recolha,
    data: n.criado_em,
    lida: Boolean(Number(n.lida)),
    // Arquivada = ciclo terminado (entregue, ou substituída por uma decisão mais recente):
    // fica no histórico, mas já não conta para o número do sino.
    importante: !n.arquivada_em,
  };

  if (n.tipo === 'pedido_aprovado') {
    if (n.arquivada_em || estadoArtigo === 'devolvido') {
      return { ...base, tipo: 'entregue', importante: false,
        assunto: 'Objeto levantado',
        texto: `Levantaste «${n.artigo_titulo}» na receção. Obrigado por usares o Perdidos & Achados.` };
    }
    return { ...base, tipo: 'levantamento',
      assunto: 'Levantamento autorizado',
      texto: `A tua resposta foi validada pela receção. «${n.artigo_titulo}» está pronto para levantamento.` };
  }
  if (n.tipo === 'pedido_recusado') {
    return { ...base, tipo: 'recusada',
      assunto: 'Pedido recusado',
      texto: n.motivo_decisao
        ? `A receção não validou o teu pedido para «${n.artigo_titulo}»: ${n.motivo_decisao}`
        : `A receção não validou o teu pedido para «${n.artigo_titulo}».` };
  }
  if (n.tipo === 'pedido_reaberto') {
    return { ...base, tipo: 'reaberta',
      assunto: 'Podes responder outra vez',
      texto: `A receção reabriu o teu pedido para «${n.artigo_titulo}». Envia uma nova resposta.` };
  }
  return null;
}

function daCorrespondencia(n) {
  return {
    chave: `notificacao:${n.id}`,
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

// Pedido enviado e ainda sem decisão: só informativo (não tem notificação no backend).
function emAnalise(r) {
  return {
    chave: `reclamacao:${r.id}:pendente`,
    reclamacaoId: r.id,
    itemId: r.itemId,
    titulo: r.titulo,
    pontoRecolha: r.pontoRecolha,
    data: r.atualizadoEm ?? r.criadoEm,
    tipo: 'pendente',
    importante: false,
    lida: true,
    assunto: 'Pedido em análise',
    texto: `O teu pedido para «${r.titulo}» foi recebido e está em análise.`,
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
    const [notificacoes, reclamacoes] = await Promise.all([
      api.get('/notifications').then((d) => d.notificacoes ?? []),
      reclamacoesService.getMinhas().catch(() => []),
    ]);

    const estadoArtigo = new Map(reclamacoes.map((r) => [r.id, r.estadoArtigo]));

    const lista = [
      ...notificacoes.map((n) => (n.tipo === 'correspondencia'
        ? daCorrespondencia(n)
        : daDecisao(n, estadoArtigo.get(n.reclamacao_id)))),
      ...reclamacoes.filter((r) => r.estado === 'pendente').map(emAnalise),
    ].filter(Boolean);

    return lista
      .map((n) => ({ ...n, lida: n.importante ? n.lida : true }))
      .sort((a, b) => (paraData(b.data)?.getTime() ?? 0) - (paraData(a.data)?.getTime() ?? 0));
  },

  // PATCH /api/notifications/:id/read para cada notificação por ler (a leitura fica na BD).
  async marcarLidas(notificacoes) {
    await Promise.all(
      notificacoes
        .filter((n) => !n.lida && n.notificacaoId)
        .map((n) => api.patch(`/notifications/${n.notificacaoId}/read`).catch(() => {})),
    );
  },
};
