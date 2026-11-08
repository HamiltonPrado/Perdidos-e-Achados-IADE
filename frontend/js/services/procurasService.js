import { api } from './api.js';
import { Procura } from '../models/Procura.js';
import { prepararFoto } from '../utilities/tools.js';

export const procurasService = {
  // POST /api/items/lost (JSON)  →  { item: { id, referencia: 'PER-000037', … } }
  // depois, para cada foto: POST /api/items/:id/photos (multipart, campo "foto").
  // Um perdido é visível a todos os alunos com sessão; a IA compara-o com os achados.
  async create(dados) {
    // Prepara as fotos antes de registar, para não ficar uma procura criada sem fotos
    // por causa de um ficheiro que o browser não consegue ler.
    const fotos = [];
    for (const [n, ficheiro] of dados.fotos.entries()) {
      fotos.push(await prepararFoto(ficheiro, n + 1));
    }

    const { item } = await api.post('/items/lost', {
      titulo: dados.titulo,
      descricao: dados.descricao,
      category_id: Number(dados.categoriaId),
      location_id: Number(dados.localId),
      data_ocorrencia: dados.dataPerdido,
    });

    for (const [n, foto] of fotos.entries()) {
      const formData = new FormData();
      formData.append('foto', foto, `foto-${n + 1}.jpg`);
      try {
        await api.post(`/items/${item.id}/photos`, formData);
      } catch (erro) {
        throw new Error(`A procura foi registada (${item.referencia}), mas a foto ${n + 1} não foi enviada: ${erro.message}`);
      }
    }

    return Object.assign(
      new Procura({ id: item.id, titulo: dados.titulo, categoriaId: dados.categoriaId, localId: dados.localId }),
      { referencia: item.referencia }
    );
  },
};
