/*
 * models/Reclamacao.js — PEDIDO DE "É MEU" FEITO POR UM UTILIZADOR
 * ------------------------------------------------------------
 * Uso:   const r = new Reclamacao(dadosDoBackend);
 */

export class Reclamacao {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.itemId = data.itemId ?? null;
    this.resposta = data.resposta ?? '';       // resposta à pergunta de verificação
    this.fotos = data.fotos ?? [];             // fotos de prova (até 3)
    this.estado = data.estado ?? 'pendente';   // 'pendente' | 'aceite' | 'recusada'
    this.criadoEm = data.criadoEm ?? null;
    this.reclamadoPor = data.reclamadoPor ?? null; // { id, nome }
  }
}
