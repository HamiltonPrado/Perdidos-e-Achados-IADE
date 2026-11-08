import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { montarFiltroCategorias } from '../components/filtroCategorias.js';
import { cartaoItem } from '../components/itemCard.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc } from '../utilities/tools.js';
import { requerLogin } from '../utilities/auth.js';

const formPesquisa = document.getElementById('form-pesquisa');
const campoPesquisa = document.getElementById('pesquisa');
const selectLocal = document.getElementById('filtro-local');
const selectOrdem = document.getElementById('filtro-ordem');
const contagem = document.getElementById('contagem');
const listaVazia = document.getElementById('lista-vazia');
const grelha = document.getElementById('grelha');

const botaoMais = document.getElementById('carregar-mais');

const filtros = { q: '', categoria: '', local: '', ordem: 'recentes' };

// Paginação: o backend devolve 20 objetos de cada vez e o total com os filtros atuais.
const lista = { pagina: 0, total: 0, mostrados: 0 };
// Cada novo pedido invalida os anteriores (ex.: mudar o filtro enquanto «Carregar mais» ainda carrega).
let pedidoAtual = 0;

function atualizarContagem() {
  const { total, mostrados } = lista;
  const texto = `${total} ${total === 1 ? 'objeto' : 'objetos'} por reclamar`;
  contagem.textContent = mostrados < total ? `${texto} · a mostrar ${mostrados}` : texto;
  botaoMais.hidden = mostrados >= total;
}

async function carregarLista() {
  const este = ++pedidoAtual;
  contagem.textContent = 'A carregar…';
  listaVazia.hidden = true;
  botaoMais.hidden = true;

  try {
    const { itens, pagina, total } = await itemsService.getPagina(filtros, 1);
    if (este !== pedidoAtual) return;

    Object.assign(lista, { pagina, total, mostrados: itens.length });
    listaVazia.hidden = itens.length > 0;
    grelha.innerHTML = itens.map(cartaoItem).join('');
    atualizarContagem();
  } catch (erro) {
    if (este !== pedidoAtual) return;
    contagem.textContent = erro.message;
    grelha.innerHTML = '';
  }
}

async function carregarMais() {
  const este = ++pedidoAtual;
  botaoMais.disabled = true;
  botaoMais.textContent = 'A carregar…';

  try {
    const { itens, pagina, total } = await itemsService.getPagina(filtros, lista.pagina + 1);
    if (este !== pedidoAtual) return;

    grelha.insertAdjacentHTML('beforeend', itens.map(cartaoItem).join(''));
    // Se não vier nada (ex.: objetos reclamados entretanto), deixa de pedir mais páginas.
    Object.assign(lista, { pagina, total: itens.length ? total : lista.mostrados, mostrados: lista.mostrados + itens.length });
    atualizarContagem();
  } catch (erro) {
    if (este !== pedidoAtual) return;
    contagem.textContent = erro.message;
  } finally {
    botaoMais.disabled = false;
    botaoMais.textContent = 'Carregar mais';
  }
}

// A lista e o detalhe dos objetos só estão disponíveis com sessão iniciada.
if (requerLogin()) {
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

  botaoMais.addEventListener('click', carregarMais);

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
}
