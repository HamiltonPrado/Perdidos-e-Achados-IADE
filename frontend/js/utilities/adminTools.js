import { respostaCoincide } from '../services/adminService.js';

export const ESTADOS_OBJETO = {
  por_receber: { texto: 'Por receber', classe: 'etiqueta--aviso' },
  por_reclamar: { texto: 'Na receção', classe: 'etiqueta--ok' },
  reclamado: { texto: 'Aguarda levantamento', classe: '' },
  devolvido: { texto: 'Devolvido', classe: 'etiqueta--escura' },
  arquivado: { texto: 'Arquivado', classe: 'etiqueta--escura' },
};

export function etiquetaObjeto(estado) {
  const e = ESTADOS_OBJETO[estado] ?? { texto: estado, classe: '' };
  return `<span class="etiqueta ${e.classe}">${e.texto}</span>`;
}

export function etiquetaPedido(pedido) {
  if (pedido.estado === 'aprovada') return '<span class="etiqueta etiqueta--ok">Aprovado</span>';
  if (pedido.estado === 'recusada') return '<span class="etiqueta etiqueta--escura">Recusado</span>';
  if (pedido.estado === 'concluida') return '<span class="etiqueta etiqueta--escura">Levantado</span>';
  return respostaCoincide(pedido.resposta, pedido.respostaEsperada)
    ? '<span class="etiqueta etiqueta--ok">Resposta coincide</span>'
    : '<span class="etiqueta etiqueta--aviso">Resposta difere</span>';
}

export function saudacao() {
  const hora = new Date().getHours();
  if (hora < 13) return 'Bom dia';
  if (hora < 20) return 'Boa tarde';
  return 'Boa noite';
}
