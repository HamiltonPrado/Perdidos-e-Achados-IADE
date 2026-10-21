/*
 * pages/home.js — LÓGICA DA PÁGINA INICIAL (index.html)
 * ------------------------------------------------------------
 * Igual ao views/Home.vue do AquaPoint.
 *
 * Como funciona:
 *   1. Monta a navbar, o footer e os filtros.
 *   2. Guarda os filtros escolhidos no objeto "filtros".
 *   3. Sempre que um filtro muda, chama carregarLista(), que pede
 *      os objetos ao itemsService e desenha os cartões.
 */

import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { montarFiltroCategorias } from '../components/filtroCategorias.js';
import { cartaoItem } from '../components/itemCard.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc } from '../utilities/tools.js';

// ---------- Elementos do HTML que vamos usar ----------
const formPesquisa = document.getElementById('form-pesquisa');
const campoPesquisa = document.getElementById('pesquisa');
const selectLocal = document.getElementById('filtro-local');
const selectOrdem = document.getElementById('filtro-ordem');
const contagem = document.getElementById('contagem');
const listaVazia = document.getElementById('lista-vazia');
const grelha = document.getElementById('grelha');

// ---------- Estado: filtros escolhidos ----------
const filtros = { q: '', categoria: '', local: '', ordem: 'recentes' };

// ---------- Desenhar a lista ----------
async function carregarLista() {
  contagem.textContent = 'A carregar…';
  listaVazia.hidden = true;

  try {
    const items = await itemsService.getAll(filtros);

    contagem.textContent = `${items.length} ${items.length === 1 ? 'objeto' : 'objetos'} por reclamar`;
    listaVazia.hidden = items.length > 0;
    grelha.innerHTML = items.map(cartaoItem).join(''); // um cartão por objeto
  } catch (erro) {
    contagem.textContent = erro.message;
    grelha.innerHTML = '';
  }
}

// ---------- Arranque da página ----------
montarNavBar('inicio');
montarFooter();

// Botões de categoria: ao clicar, muda o filtro e recarrega a lista
const categorias = await categoriasService.getAll();
const escolherCategoria = montarFiltroCategorias(
  document.getElementById('filtro-categorias'),
  categorias,
  (id) => {
    filtros.categoria = id;
    carregarLista();
  }
);

// Opções do select "Local"
const locais = await locaisService.getAll();
selectLocal.insertAdjacentHTML(
  'beforeend',
  locais.map((l) => `<option value="${esc(l.id)}">${esc(l.nome)}</option>`).join('')
);

// ---------- Eventos ----------

// Pesquisar (botão ou Enter)
formPesquisa.addEventListener('submit', (e) => {
  e.preventDefault(); // não recarregar a página
  filtros.q = campoPesquisa.value;
  carregarLista();
});

selectLocal.addEventListener('change', () => {
  filtros.local = selectLocal.value;
  carregarLista();
});

selectOrdem.addEventListener('change', () => {
  filtros.ordem = selectOrdem.value;
  carregarLista();
});

// "Limpar filtros" (aparece quando não há resultados)
document.getElementById('limpar-filtros').addEventListener('click', () => {
  filtros.q = '';
  filtros.categoria = '';
  filtros.local = '';
  campoPesquisa.value = '';
  selectLocal.value = '';
  escolherCategoria('');
  carregarLista();
});

carregarLista();
