function haDias(dias, hora, minuto) {
  const d = new Date();
  d.setDate(d.getDate() - dias);
  d.setHours(hora, minuto, 0, 0);
  return d.toISOString();
}

const ANA = { id: 7, nome: 'Ana S.' };
const RUI = { id: 8, nome: 'Rui F.' };
const JOAO = { id: 9, nome: 'João T.' };

export function itemsIniciais() {
  return [
    {
      id: 1,
      referencia: 'UE-0241',
      titulo: 'Auscultadores sem fios',
      descricao: 'Auscultadores pretos com estojo de carregamento, encontrados numa mesa próxima da janela.',
      categoria: 'Eletrónica', categoriaId: 'eletronica',
      local: 'Biblioteca · Piso 1', localId: 'biblioteca',
      dataEncontrado: haDias(0, 10, 40),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'Qual é a marca dos auscultadores?',
      fotos: ['assets/images/auscultadores.jpg'], publicadoPor: RUI,
    },
    {
      id: 2,
      referencia: 'UE-0242',
      titulo: 'Carteira em pele preta',
      descricao: 'Carteira pequena em pele preta, com fecho. O conteúdo não é indicado: quem a reclamar deve descrevê-lo.',
      categoria: 'Documentos', categoriaId: 'documentos',
      local: 'Cantina · Junto à janela', localId: 'bar',
      dataEncontrado: haDias(1, 16, 5),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'O que estava dentro da carteira?',
      fotos: ['assets/images/carteira.jpg'], publicadoPor: JOAO,
    },
    {
      id: 3,
      referencia: 'UE-0243',
      titulo: 'Estojo laranja',
      descricao: 'Estojo de tecido laranja, fecho preto e pequena marca no canto esquerdo.',
      categoria: 'Outros', categoriaId: 'outros',
      local: 'Bloco B · Sala 204', localId: 'salas',
      dataEncontrado: haDias(2, 14, 20),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'O que tem escrito por dentro do estojo?',
      fotos: ['assets/images/estojo-laranja.jpg'], publicadoPor: RUI,
    },
    {
      id: 4,
      referencia: 'UE-0244',
      titulo: 'Carregador portátil USB-C',
      descricao: 'Carregador branco de portátil, com cabo USB-C.',
      categoria: 'Eletrónica', categoriaId: 'eletronica',
      local: 'Laboratório · Sala 03', localId: 'laboratorios',
      dataEncontrado: haDias(3, 9, 15),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'Que marca é o carregador?',
      fotos: ['assets/images/carregador.jpg'], publicadoPor: JOAO,
    },
    {
      id: 5,
      referencia: 'UE-0245',
      titulo: 'Molho de chaves com porta-chaves azul',
      descricao: 'Três chaves num porta-chaves de borracha azul.',
      categoria: 'Chaves', categoriaId: 'chaves',
      local: 'Auditório', localId: 'auditorio',
      dataEncontrado: haDias(4, 18, 0),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'Qual é a forma do porta-chaves?',
      fotos: [], publicadoPor: ANA,
    },
    {
      id: 6,
      referencia: 'UE-0246',
      titulo: 'Casaco de ganga',
      descricao: 'Casaco de ganga azul-claro, tamanho M.',
      categoria: 'Roupa', categoriaId: 'roupa',
      local: 'Entrada principal', localId: 'entrada',
      dataEncontrado: haDias(5, 8, 30),
      ondeEsta: 'secretaria', estado: 'por_reclamar',
      pergunta: 'O que tem no bolso interior?',
      fotos: [], publicadoPor: JOAO,
    },
    {
      id: 7,
      referencia: 'UE-0247',
      titulo: 'Cartão de estudante',
      descricao: 'Cartão entregue ao dono.',
      categoria: 'Documentos', categoriaId: 'documentos',
      local: 'Cantina', localId: 'bar',
      dataEncontrado: haDias(9, 13, 0),
      ondeEsta: 'secretaria', estado: 'devolvido',
      pergunta: 'Qual é o teu curso?',
      fotos: [], publicadoPor: ANA,
    },
    {
      id: 8,
      referencia: 'UE-0248',
      titulo: 'Garrafa térmica cinzenta',
      descricao: 'Garrafa de metal com autocolantes.',
      categoria: 'Outros', categoriaId: 'outros',
      local: 'Entrada principal', localId: 'entrada',
      dataEncontrado: haDias(0, 8, 50),
      ondeEsta: 'com_quem_encontrou', estado: 'por_receber',
      pergunta: 'Que autocolantes tem a garrafa?',
      fotos: [], publicadoPor: ANA,
    },
  ];
}

export function reclamacoesIniciais() {
  return [
    {
      id: 1,
      referencia: 'UE-0241',
      itemId: 5,
      resposta: 'Tem a forma de uma estrela',
      informacao: 'Uma das chaves tem uma fita vermelha.',
      fotos: ['exemplo'],
      legendas: ['Comigo'],
      estado: 'pendente',
      criadoEm: haDias(1, 13, 0),
      reclamadoPor: JOAO,
    },
  ];
}
