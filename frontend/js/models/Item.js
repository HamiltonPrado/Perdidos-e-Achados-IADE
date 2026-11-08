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

    this.respostaEsperada = data.respostaEsperada ?? '';
    this.pontoRecolha = data.pontoRecolha ?? '';
    this.localizacaoStock = data.localizacaoStock ?? '';
    this.observacoes = data.observacoes ?? '';
    this.recebidoEm = data.recebidoEm ?? null;
    this.criadoEm = data.criadoEm ?? null;
  }

  // Converte um artigo da API (snake_case, ids numéricos) para o formato usado nas páginas.
  // As fotos não vêm no artigo: o serviço junta os URLs depois.
  static doBackend(a, fotos = []) {
    return new Item({
      id: a.id,
      referencia: a.referencia,
      titulo: a.titulo,
      descricao: a.descricao,
      categoria: a.categoria,
      categoriaId: a.category_id != null ? String(a.category_id) : '',
      local: a.local,
      localId: a.location_id != null ? String(a.location_id) : '',
      dataEncontrado: a.data_ocorrencia,
      estado: estadoDoBackend(a),
      ondeEsta: a.estado_recepcao === 'por_entregar' ? 'com_quem_encontrou' : 'secretaria',
      pergunta: a.pergunta_secreta,
      // user_id só vem quando quem pede é o autor ou a equipa (reportado_por só na lista da equipa).
      publicadoPor: a.user_id ? { id: a.user_id, nome: a.reportado_por ?? '' } : null,
      pontoRecolha: a.ponto_recolha,
      criadoEm: a.criado_em,
      // Dados internos: só chegam nas rotas da equipa (/api/admin/...).
      respostaEsperada: a.resposta_esperada,
      localizacaoStock: a.localizacao_stock,
      observacoes: a.observacoes_internas,
      recebidoEm: a.recebido_em,
      fotos,
    });
  }
}

// estado (ativo/devolvido/arquivado/resolvido) + estado_recepcao → estado usado no front.
function estadoDoBackend(a) {
  if (a.estado === 'devolvido') return 'devolvido';
  if (a.estado === 'arquivado' || a.estado === 'resolvido') return 'arquivado';
  if (a.estado_recepcao === 'por_entregar') return 'por_receber';
  return 'por_reclamar';
}
