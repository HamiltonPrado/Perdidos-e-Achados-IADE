/*
 * models/User.js — FORMATO DE UM UTILIZADOR
 * ------------------------------------------------------------
 * Uso:   const user = new User(dadosDoBackend);
 */

export class User {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.nome = data.nome ?? '';
    this.email = data.email ?? '';
    this.foto = data.foto ?? null; // URL da foto de perfil (ou null → mostra iniciais)
  }
}
