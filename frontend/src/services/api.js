// Todos os pedidos ao backend passam por este ficheiro.
// Enquanto o backend não estiver pronto, deixa USAR_MOCKS = true.

import { items as mockItems } from '../mocks/items.js';

const USAR_MOCKS = true;
const API_URL = import.meta.env?.VITE_API_URL ?? 'http://localhost:3000/api';

async function pedido(caminho, opcoes = {}) {
  const resposta = await fetch(`${API_URL}${caminho}`, {
    credentials: 'include',
    ...opcoes,
  });
  const dados = await resposta.json().catch(() => ({}));
  if (!resposta.ok) throw new Error(dados.erro ?? 'Ocorreu um erro.');
  return dados;
}

// GET /items
export async function listarItems({ q = '', categoria = '', local = '', pagina = 1 } = {}) {
  if (USAR_MOCKS) {
    return mockItems.filter(
      (i) =>
        (!categoria || i.categoria === categoria) &&
        (!q || i.titulo.toLowerCase().includes(q.toLowerCase()))
    );
  }
  const params = new URLSearchParams({ q, categoria, local, pagina });
  return pedido(`/items?${params}`);
}

// GET /items/:id
export async function obterItem(id) {
  if (USAR_MOCKS) return mockItems.find((i) => i.id === Number(id));
  return pedido(`/items/${id}`);
}

// POST /items  (formData com titulo, descricao, categoria, local, fotos…)
export async function publicarItem(formData) {
  if (USAR_MOCKS) return { id: Date.now(), ...Object.fromEntries(formData) };
  return pedido('/items', { method: 'POST', body: formData });
}

// POST /items/:id/reclamacoes
export async function reclamarItem(id, resposta) {
  if (USAR_MOCKS) return { id: 1, itemId: id, resposta, estado: 'pendente' };
  return pedido(`/items/${id}/reclamacoes`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ resposta }),
  });
}
