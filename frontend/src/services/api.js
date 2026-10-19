/*
 * api.js — ÚNICO SÍTIO QUE FALA COM O BACKEND
 * ------------------------------------------------------------
 * As páginas e componentes NUNCA fazem fetch() diretamente.
 * Usam estas funções, por exemplo:
 *     import { listarItems } from '../services/api';
 *     const lista = await listarItems({ categoria: 'chaves' });
 *
 * Enquanto o backend não estiver pronto:
 *     USAR_MOCKS = true  → devolve os dados falsos de src/mocks/
 * Quando o backend estiver pronto:
 *     USAR_MOCKS = false → faz pedidos reais ao endereço API_URL
 *
 * Os endpoints e formatos estão combinados em docs/api.md.
 */

import * as mocks from '../mocks/items.js';

// ⚙️ Mudar para false quando o backend estiver a funcionar
const USAR_MOCKS = true;

// Endereço do backend. Vem do ficheiro .env (VITE_API_URL);
// se não existir, usa o valor por defeito.
const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:3000/api';

// Simula a demora da rede (só nos mocks), para vermos o "A carregar…"
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

/*
 * Função interna que faz o pedido HTTP ao backend.
 * - Junta o API_URL ao caminho (ex.: '/items')
 * - credentials: 'include' envia o cookie de sessão (login)
 * - Se a resposta for um erro, lança a mensagem { erro } do backend
 */
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
export async function listarItems({ q = '', categoria = '', local = '', ordem = 'recentes' } = {}) {
  if (USAR_MOCKS) {
    await esperar(300);
    const texto = q.trim().toLowerCase();
    const lista = mocks.items.filter(
      (i) =>
        i.estado === 'por_reclamar' &&
        (!categoria || i.categoriaId === categoria) &&
        (!local || i.localId === local) &&
        (!texto ||
          i.titulo.toLowerCase().includes(texto) ||
          i.descricao.toLowerCase().includes(texto))
    );
    lista.sort((a, b) =>
      ordem === 'antigos'
        ? new Date(a.dataEncontrado) - new Date(b.dataEncontrado)
        : new Date(b.dataEncontrado) - new Date(a.dataEncontrado)
    );
    return lista;
  }
  const params = new URLSearchParams({ q, categoria, local, ordem });
  return pedido(`/items?${params}`);
}

// GET /items/:id
export async function obterItem(id) {
  if (USAR_MOCKS) return mocks.items.find((i) => i.id === Number(id));
  return pedido(`/items/${id}`);
}

// GET /categorias
export async function listarCategorias() {
  if (USAR_MOCKS) return mocks.categorias;
  return pedido('/categorias');
}

// GET /locais
export async function listarLocais() {
  if (USAR_MOCKS) return mocks.locais;
  return pedido('/locais');
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
