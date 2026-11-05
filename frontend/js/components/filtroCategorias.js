import { esc } from '../utilities/tools.js';

export function montarFiltroCategorias(elemento, categorias, aoMudar) {
  const opcoes = [{ id: '', nome: 'Todos' }, ...categorias];

  elemento.classList.add('filtro');
  elemento.setAttribute('role', 'group');
  elemento.setAttribute('aria-label', 'Categorias');
  elemento.innerHTML = opcoes
    .map((c) => `<button type="button" class="filtro__botao" data-categoria="${esc(c.id)}">${esc(c.nome)}</button>`)
    .join('');

  function escolher(id) {
    elemento.querySelectorAll('.filtro__botao').forEach((b) => {
      const ativo = b.dataset.categoria === id;
      b.classList.toggle('filtro__botao--ativo', ativo);
      b.setAttribute('aria-pressed', String(ativo));
    });
  }

  elemento.addEventListener('click', (e) => {
    const botao = e.target.closest('.filtro__botao');
    if (!botao) return;
    escolher(botao.dataset.categoria);
    aoMudar(botao.dataset.categoria);
  });

  escolher('');
  return escolher;
}
