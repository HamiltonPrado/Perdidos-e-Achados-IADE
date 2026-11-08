export class Reclamacao {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.itemId = data.itemId ?? null;
    this.titulo = data.titulo ?? '';
    this.resposta = data.resposta ?? '';
    this.informacao = data.informacao ?? '';
    this.fotos = data.fotos ?? [];
    this.estado = data.estado ?? 'pendente';
    this.motivo = data.motivo ?? '';
    this.decididoEm = data.decididoEm ?? null;
    this.criadoEm = data.criadoEm ?? null;
    this.atualizadoEm = data.atualizadoEm ?? null;
    // Estado do achado no backend (ativo, devolvido, arquivado…): diz se ainda há levantamento.
    this.estadoArtigo = data.estadoArtigo ?? null;
    this.pontoRecolha = data.pontoRecolha ?? '';
    this.reclamadoPor = data.reclamadoPor ?? null;
  }
}
