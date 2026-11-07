export class Procura {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.titulo = data.titulo ?? '';
    this.descricao = data.descricao ?? '';
    this.categoria = data.categoria ?? '';
    this.categoriaId = data.categoriaId ?? '';
    this.local = data.local ?? '';
    this.localId = data.localId ?? '';
    this.dataPerdido = data.dataPerdido ?? null;
    this.estado = data.estado ?? 'ativa';
    this.criadoEm = data.criadoEm ?? null;
  }
}
