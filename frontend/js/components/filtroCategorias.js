/*
 * components/filtroCategorias.js — BOTÕES DE CATEGORIA
 * ------------------------------------------------------------
 * Desenha "Todos", "Eletrónica", "Documentos", … dentro de um
 * elemento, e avisa quando se escolhe outra categoria.
 *
 * Uso:
 *     montarFiltroCategorias(elemento, categorias, (id) => { … });
 *       elemento   → onde pôr os botões (ex.: <div id="filtro">)
 *       categorias → lista de { id, nome } (vem do categoriasService)
 *       aoMudar    → função chamada com o id escolhido ('' = Todos)
 *
 * Devolve uma função para escolher uma categoria por código
 * (ex.: escolher('') ao limpar os filtros).
 *
 * Estilos: assets/css/components/filtro-categorias.css
 */

import { esc } from '../utilities/tools.js';

export function montarFiltroCategorias(elemento, categorias, aoMudar) {
  const opcoes = [{ id: '', nome: 'Todos' }, ...categorias];

  elemento.classList.add('filtro');
  elemento.setAttribute('role', 'group');
  elemento.setAttribute('aria-label', 'Categorias');
  elemento.innerHTML = opcoes
    .map((c) => `<button type="button" class="filtro__botao" data-categoria="${esc(c.id)}">${esc(c.nome)}</button>`)
    .join('');

  // Marca visualmente o botão escolhido (classe --ativo + aria-pressed)
  function escolher(id) {
    elemento.querySelectorAll('.filtro__botao').forEach((b) => {
      const ativo = b.dataset.categoria === id;
      b.classList.toggle('filtro__botao--ativo', ativo);
      b.setAttribute('aria-pressed', String(ativo));
    });
  }

  // Um só "ouvinte" para todos os botões (delegação de eventos)
  elemento.addEventListener('click', (e) => {
    const botao = e.target.closest('.filtro__botao');
    if (!botao) return;
    escolher(botao.dataset.categoria);
    aoMudar(botao.dataset.categoria);
  });

  escolher(''); // começa em "Todos"
  return escolher;
}
