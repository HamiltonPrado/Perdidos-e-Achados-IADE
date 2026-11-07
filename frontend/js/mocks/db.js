import { itemsIniciais, reclamacoesIniciais, devolucoesIniciais } from './items.js';

const CHAVE = 'achados-mock-db-v6';

function carregar() {
  try {
    const guardado = JSON.parse(localStorage.getItem(CHAVE));
    if (guardado) return guardado;
  } catch {
  }
  return { items: itemsIniciais(), reclamacoes: reclamacoesIniciais(), devolucoes: devolucoesIniciais(), procuras: [] };
}

export const db = carregar();

export function guardar() {
  try {
    localStorage.setItem(CHAVE, JSON.stringify(db));
  } catch {
    console.warn('Não foi possível guardar os dados de exemplo: espaço cheio. Usa fotos mais pequenas.');
  }
}

export function novoId(lista) {
  return lista.reduce((maior, x) => Math.max(maior, x.id), 0) + 1;
}
