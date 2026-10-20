// Dados falsos no mesmo formato que docs/api.md.
// Usados enquanto o backend não está pronto (ver USAR_MOCKS em services/api.js).

// Ajuda a criar datas "há X dias" para os exemplos parecerem recentes
function haDias(dias, hora, minuto) {
  const d = new Date();
  d.setDate(d.getDate() - dias);
  d.setHours(hora, minuto, 0, 0);
  return d.toISOString();
}

export const categorias = [
  { id: 'eletronica', nome: 'Eletrónica' },
  { id: 'documentos', nome: 'Documentos' },
  { id: 'chaves', nome: 'Chaves' },
  { id: 'roupa', nome: 'Roupa' },
  { id: 'outros', nome: 'Outros' },
];

export const locais = [
  { id: 'biblioteca', nome: 'Biblioteca' },
  { id: 'bar', nome: 'Bar / Cantina' },
  { id: 'auditorio', nome: 'Auditório' },
  { id: 'salas', nome: 'Salas de aula' },
  { id: 'laboratorios', nome: 'Laboratórios' },
  { id: 'entrada', nome: 'Entrada principal' },
];

export const items = [
  {
    id: 1,
    titulo: 'Carteira de pele castanha',
    descricao: 'Carteira pequena com fecho.',
    categoria: 'Documentos',
    categoriaId: 'documentos',
    local: 'Biblioteca · Piso 1',
    localId: 'biblioteca',
    dataEncontrado: haDias(0, 11, 20),
    ondeEsta: 'secretaria',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 7, nome: 'Ana S.' },
  },
  {
    id: 2,
    titulo: 'Auriculares sem fios',
    descricao: 'Estojo branco.',
    categoria: 'Eletrónica',
    categoriaId: 'eletronica',
    local: 'Bar / Cantina',
    localId: 'bar',
    dataEncontrado: haDias(1, 16, 5),
    ondeEsta: 'com_quem_encontrou',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 8, nome: 'Rui P.' },
  },
  {
    id: 3,
    titulo: 'Molho de chaves com porta-chaves azul',
    descricao: 'Três chaves e um porta-chaves de borracha azul.',
    categoria: 'Chaves',
    categoriaId: 'chaves',
    local: 'Sala [nº]',
    localId: 'salas',
    dataEncontrado: haDias(2, 9, 40),
    ondeEsta: 'secretaria',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 7, nome: 'Ana S.' },
  },
  {
    id: 4,
    titulo: 'Casaco de ganga',
    descricao: 'Casaco de ganga azul-claro, tamanho M.',
    categoria: 'Roupa',
    categoriaId: 'roupa',
    local: 'Auditório',
    localId: 'auditorio',
    dataEncontrado: haDias(3, 18, 0),
    ondeEsta: 'secretaria',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 9, nome: 'João M.' },
  },
  {
    id: 5,
    titulo: 'Carregador de portátil USB-C',
    descricao: 'Carregador preto de 65W.',
    categoria: 'Eletrónica',
    categoriaId: 'eletronica',
    local: 'Laboratório [nº]',
    localId: 'laboratorios',
    dataEncontrado: haDias(3, 14, 30),
    ondeEsta: 'com_quem_encontrou',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 8, nome: 'Rui P.' },
  },
  {
    id: 6,
    titulo: 'Garrafa térmica cinzenta',
    descricao: 'Garrafa de metal com autocolantes.',
    categoria: 'Outros',
    categoriaId: 'outros',
    local: 'Entrada principal',
    localId: 'entrada',
    dataEncontrado: haDias(5, 8, 15),
    ondeEsta: 'secretaria',
    estado: 'por_reclamar',
    fotos: [],
    publicadoPor: { id: 9, nome: 'João M.' },
  },
];
