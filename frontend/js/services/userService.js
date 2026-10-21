/*
 * services/userService.js — PERFIL DO UTILIZADOR
 * ------------------------------------------------------------
 * Igual ao userService.js do AquaPoint.
 */

import { api, USAR_MOCKS } from './api.js';
import { User } from '../models/User.js';
import { getUser } from '../utilities/auth.js';
import { lerImagem } from '../utilities/tools.js';

export const userService = {
  // GET /me   → { id, nome, email, foto }
  async getMe() {
    if (USAR_MOCKS) return getUser();
    return new User(await api.get('/me'));
  },

  /*
   * PATCH /me   → guardar nome e foto
   * "dados" = { nome, foto }
   *   foto: File (nova) · null (remover) · undefined (não mexer)
   */
  async update(dados) {
    if (USAR_MOCKS) {
      const atual = getUser();
      const foto = dados.foto instanceof File ? await lerImagem(dados.foto, 300)
        : dados.foto === null ? null
        : atual.foto;
      return new User({ ...atual, nome: dados.nome, foto });
    }

    const formData = new FormData();
    formData.append('nome', dados.nome);
    if (dados.foto instanceof File) formData.append('foto', dados.foto);
    if (dados.foto === null) formData.append('removerFoto', 'true');
    return new User(await api.patch('/me', formData));
  },
};
