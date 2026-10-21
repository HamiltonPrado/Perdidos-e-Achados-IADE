/*
 * mocks/db.js — "BASE DE DADOS" DE EXEMPLO, GUARDADA NO BROWSER
 * ------------------------------------------------------------
 * Como cada página é um .html diferente, os dados de exemplo
 * têm de sobreviver à mudança de página. Por isso ficam no
 * localStorage do browser.
 *
 * Só é usado quando USAR_MOCKS = true. Com o backend real,
 * quem guarda os dados é a base de dados do backend.
 *
 * Para voltar aos dados iniciais, corre na consola do browser (F12):
 *     localStorage.removeItem('achados-mock-db'); location.reload();
 */

import { itemsIniciais, reclamacoesIniciais } from './items.js';

const CHAVE = 'achados-mock-db';

function carregar() {
  try {
    const guardado = JSON.parse(localStorage.getItem(CHAVE));
    if (guardado) return guardado;
  } catch {
    // dados estragados → começa de novo
  }
  return { items: itemsIniciais(), reclamacoes: reclamacoesIniciais() };
}

// Os dados — os services leem e alteram isto diretamente
export const db = carregar();

// Guarda as alterações no browser (chamar depois de mudar o db)
export function guardar() {
  try {
    localStorage.setItem(CHAVE, JSON.stringify(db));
  } catch {
    // O localStorage tem pouco espaço (~5 MB). Com muitas fotos pode encher.
    console.warn('Não foi possível guardar os dados de exemplo: espaço cheio. Usa fotos mais pequenas.');
  }
}

// Gera um id novo (o maior + 1)
export function novoId(lista) {
  return lista.reduce((maior, x) => Math.max(maior, x.id), 0) + 1;
}
