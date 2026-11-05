import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { montarFiltroCategorias } from '../components/filtroCategorias.js';
import { cartaoItem } from '../components/itemCard.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc } from '../utilities/tools.js';

const formPesquisa = document.getElementById('form-pesquisa');
const campoPesquisa = document.getElementById('pesquisa');
const selectLocal = document.getElementById('filtro-local');
const selectOrdem = document.getElementById('filtro-ordem');
const contagem = document.getElementById('contagem');
const listaVazia = document.getElementById('lista-vazia');
const grelha = document.getElementById('grelha');

const filtros = { q: '', categoria: '', local: '', ordem: 'recentes' };

async function carregarLista() {
  contagem.textContent = 'A carregar…';
  listaVazia.hidden = true;

  try {
    const items = await itemsService.getAll(filtros);

    contagem.textContent = `${items.length} ${items.length === 1 ? 'objeto' : 'objetos'} por reclamar`;
    listaVazia.hidden = items.length > 0;
    grelha.innerHTML = items.map(cartaoItem).join('');
  } catch (erro) {
    contagem.textContent = erro.message;
    grelha.innerHTML = '';
  }
}

montarNavBar('inicio');
montarFooter();

const categorias = await categoriasService.getAll();
const escolherCategoria = montarFiltroCategorias(
  document.getElementById('filtro-categorias'),
  categorias,
  (id) => {
    filtros.categoria = id;
    carregarLista();
  }
);

const locais = await locaisService.getAll();
selectLocal.insertAdjacentHTML(
  'beforeend',
  locais.map((l) => `<option value="${esc(l.id)}">${esc(l.nome)}</option>`).join('')
);

formPesquisa.addEventListener('submit', (e) => {
  e.preventDefault();
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
