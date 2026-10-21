/*
 * models/Item.js — FORMATO DE UM OBJETO ENCONTRADO
 * ------------------------------------------------------------
 * Igual ao models/AquaPoint.js do AquaPoint.
 * Garante que todos os objetos têm sempre os mesmos campos,
 * mesmo que o backend não envie algum (fica com o valor por defeito).
 *
 * Uso:   const item = new Item(dadosDoBackend);
 *
 * Os campos estão combinados em docs/api.md.
 */

export class Item {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.titulo = data.titulo ?? '';
    this.descricao = data.descricao ?? '';

    this.categoria = data.categoria ?? '';   // nome a mostrar, ex.: "Chaves"
    this.categoriaId = data.categoriaId ?? ''; // id para filtrar, ex.: "chaves"

    this.local = data.local ?? '';           // ex.: "Biblioteca · Piso 1"
    this.localId = data.localId ?? '';

    this.dataEncontrado = data.dataEncontrado ?? null; // texto ISO, ex.: "2026-10-15T11:20:00Z"

    this.ondeEsta = data.ondeEsta ?? 'secretaria';    // 'secretaria' | 'com_quem_encontrou'
    this.estado = data.estado ?? 'por_reclamar';      // 'por_reclamar' | 'reclamado' | 'devolvido'

    this.pergunta = data.pergunta ?? '';             // pergunta de verificação (a resposta nunca vem do backend)
    this.fotos = data.fotos ?? [];                    // lista de URLs
    this.publicadoPor = data.publicadoPor ?? null;    // { id, nome }
  }
}
