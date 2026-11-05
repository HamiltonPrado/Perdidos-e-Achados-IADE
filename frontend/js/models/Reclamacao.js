export class Reclamacao {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.itemId = data.itemId ?? null;
    this.resposta = data.resposta ?? '';
    this.informacao = data.informacao ?? '';
    this.fotos = data.fotos ?? [];
    this.estado = data.estado ?? 'pendente';
    this.criadoEm = data.criadoEm ?? null;
    this.reclamadoPor = data.reclamadoPor ?? null;
  }
}
