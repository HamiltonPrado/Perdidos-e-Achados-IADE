/*
 * mocks/items.js — OBJETOS E PEDIDOS DE EXEMPLO
 * ------------------------------------------------------------
 * Usados quando USAR_MOCKS = true (ver services/api.js).
 * Mesmo formato que docs/api.md e js/models/.
 *
 * São só os dados INICIAIS: as alterações (publicar, reclamar…)
 * ficam guardadas no browser pelo mocks/db.js.
 */

// Cria datas "há X dias" para os exemplos parecerem sempre recentes
function haDias(dias, hora, minuto) {
  const d = new Date();
  d.setDate(d.getDate() - dias);
  d.setHours(hora, minuto, 0, 0);
  return d.toISOString();
}

const ANA = { id: 7, nome: 'Ana S.' };
const RUI = { id: 8, nome: 'Rui P.' };
const JOAO = { id: 9, nome: 'João M.' };

export function itemsIniciais() {
  return [
    {
      id: 1,
      titulo: 'Carteira de pele castanha',
      descricao: 'Carteira pequena de pele castanha, com fecho. Não indiquei o conteúdo; quem a reclamar deve descrever o que tem dentro.',
      categoria: 'Documentos', categoriaId: 'documentos',
      local: 'Biblioteca · Piso 1', localId: 'biblioteca',
      dataEncontrado: haDias(0, 11, 20),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'O que estava dentro da carteira?',
      fotos: [], publicadoPor: ANA,
    },
    {
      id: 2,
      titulo: 'Auriculares sem fios',
      descricao: 'Estojo branco de carregamento com os dois auriculares.',
      categoria: 'Eletrónica', categoriaId: 'eletronica',
      local: 'Bar / Cantina', localId: 'bar',
      dataEncontrado: haDias(1, 16, 5),
      ondeEsta: 'com_quem_encontrou', estado: 'por_reclamar',
      pergunta: 'Que marca e que autocolante tem o estojo?',
      fotos: [], publicadoPor: RUI,
    },
    {
      id: 3,
      titulo: 'Molho de chaves com porta-chaves azul',
      descricao: 'Três chaves num porta-chaves de borracha azul.',
      categoria: 'Chaves', categoriaId: 'chaves',
      local: 'Sala [nº]', localId: 'salas',
      dataEncontrado: haDias(2, 9, 40),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'Qual é a forma do porta-chaves?',
      fotos: [], publicadoPor: ANA,
    },
    {
      id: 4,
      titulo: 'Casaco de ganga',
      descricao: 'Casaco de ganga azul-claro, tamanho M.',
      categoria: 'Roupa', categoriaId: 'roupa',
      local: 'Auditório', localId: 'auditorio',
      dataEncontrado: haDias(3, 18, 0),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'O que tem no bolso interior?',
      fotos: [], publicadoPor: JOAO,
    },
    {
      id: 5,
      titulo: 'Carregador de portátil USB-C',
      descricao: 'Carregador preto de 65 W com cabo.',
      categoria: 'Eletrónica', categoriaId: 'eletronica',
      local: 'Laboratório [nº]', localId: 'laboratorios',
      dataEncontrado: haDias(3, 14, 30),
      ondeEsta: 'com_quem_encontrou', estado: 'por_reclamar',
      pergunta: 'Que marca é o carregador?',
      fotos: [], publicadoPor: RUI,
    },
    {
      id: 6,
      titulo: 'Garrafa térmica cinzenta',
      descricao: 'Garrafa de metal com autocolantes.',
      categoria: 'Outros', categoriaId: 'outros',
      local: 'Entrada principal', localId: 'entrada',
      dataEncontrado: haDias(5, 8, 15),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'Que autocolantes tem a garrafa?',
      fotos: [], publicadoPor: JOAO,
    },
    {
      id: 7,
      titulo: 'Cartão de estudante',
      descricao: 'Cartão entregue ao dono.',
      categoria: 'Documentos', categoriaId: 'documentos',
      local: 'Bar / Cantina', localId: 'bar',
      dataEncontrado: haDias(9, 13, 0),
      ondeEsta: 'secretaria', estado: 'devolvido',
      pergunta: 'Qual é o teu curso?',
      fotos: [], publicadoPor: ANA,
    },
  ];
}

// Pedidos "É meu" já feitos. "exemplo" = foto de exemplo (mostra um X)
export function reclamacoesIniciais() {
  return [
    {
      id: 1,
      itemId: 1,
      resposta: 'Cartão de estudante e um bilhete de comboio',
      fotos: ['exemplo', 'exemplo'],
      legendas: ['Comigo', 'Fatura'],
      estado: 'pendente',
      criadoEm: haDias(0, 13, 0),
      reclamadoPor: JOAO,
    },
  ];
}
