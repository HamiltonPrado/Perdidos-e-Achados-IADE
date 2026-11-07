import { api, USAR_MOCKS, esperar } from './api.js';
import { Procura } from '../models/Procura.js';
import { db, guardar, novoId } from '../mocks/db.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem } from '../utilities/tools.js';

export const procurasService = {
  // POST /procuras
  async create(dados, categorias, locais) {
    if (USAR_MOCKS) {
      await esperar();
      db.procuras ??= [];
      const nova = {
        id: novoId(db.procuras),
        titulo: dados.titulo,
        descricao: dados.descricao,
        categoriaId: dados.categoriaId,
        categoria: categorias.find((c) => c.id === dados.categoriaId)?.nome ?? '',
        localId: dados.localId,
        local: locais.find((l) => l.id === dados.localId)?.nome ?? '',
        dataPerdido: dados.dataPerdido,
        detalhePrivado: dados.detalhePrivado,
        fotos: await Promise.all(dados.fotos.map((f) => lerImagem(f, 600))),
        estado: 'ativa',
        criadoEm: new Date().toISOString(),
        criadoPor: getUser()?.id ?? null,
      };
      db.procuras.push(nova);
      guardar();
      return new Procura(nova);
    }

    const formData = new FormData();
    ['titulo', 'descricao', 'categoriaId', 'localId', 'dataPerdido', 'detalhePrivado']
      .forEach((campo) => formData.append(campo, dados[campo] ?? ''));
    dados.fotos.forEach((f) => formData.append('fotos', f));
    return new Procura(await api.post('/procuras', formData));
  },
};
