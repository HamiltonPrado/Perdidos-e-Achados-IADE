export class User {
  constructor(data = {}) {
    this.id = data.id ?? null;
    this.nome = data.nome ?? '';
    this.email = data.email ?? '';
    this.foto = data.foto ?? null;
    this.role = data.role ?? 'user';
  }
}
