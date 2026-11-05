export class Item {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.titulo = data.titulo ?? '';
    this.descricao = data.descricao ?? '';

    this.categoria = data.categoria ?? '';
    this.categoriaId = data.categoriaId ?? '';

    this.local = data.local ?? '';
    this.localId = data.localId ?? '';

    this.dataEncontrado = data.dataEncontrado ?? null;

    this.ondeEsta = data.ondeEsta ?? 'secretaria';
    this.estado = data.estado ?? 'por_receber';
    this.referencia = data.referencia ?? '';          

    this.pergunta = data.pergunta ?? '';
    this.fotos = data.fotos ?? [];                    
    this.publicadoPor = data.publicadoPor ?? null;   
  }
}
