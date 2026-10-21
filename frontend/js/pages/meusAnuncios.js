/*
 * pages/meusAnuncios.js — LÓGICA DE "OS MEUS ANÚNCIOS"
 * ------------------------------------------------------------
 * Como funciona:
 *   1. Só abre com sessão iniciada.
 *   2. Pede os meus objetos (itemsService.getMine) e, para cada um,
 *      os pedidos "É meu" (reclamacoesService.getByItem).
 *   3. Desenha a lista. Os botões Aceitar / Recusar / Marcar como
 *      devolvido mudam os dados e voltam a desenhar a lista.
 */

import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { esc, formatarData, fotoVazia } from '../utilities/tools.js';

const lista = document.getElementById('anuncios');

if (requerLogin()) {
  montarNavBar('meus-anuncios');
  montarFooter();
  carregar();
}

// Etiqueta do estado de cada anúncio
function etiquetaEstado(item, pendentes) {
  if (item.estado === 'devolvido') return '<span class="etiqueta etiqueta--ok">Devolvido</span>';
  if (item.estado === 'reclamado') return '<span class="etiqueta etiqueta--ok">Dono confirmado · combinar entrega</span>';
  if (pendentes > 0) return `<span class="etiqueta etiqueta--aviso">${pendentes} ${pendentes === 1 ? 'pedido' : 'pedidos'} por responder</span>`;
  return '<span class="etiqueta">Por reclamar</span>';
}

// HTML de um pedido "É meu" (resposta + fotos de prova + botões)
function pedidoHtml(r) {
  const fotos = r.fotos.map((src, n) =>
    src === 'exemplo'
      ? `<div class="pedido__foto">${fotoVazia()}<span class="pedido__foto-legenda">${esc(r.legendas?.[n] ?? 'Foto')}</span></div>`
      : `<div class="pedido__foto"><img src="${esc(src)}" alt="Foto de prova ${n + 1}"></div>`
  ).join('');

  return `
    <div class="pedido">
      <p class="pedido__resposta"><strong>${esc(r.reclamadoPor?.nome)}</strong> respondeu: “${esc(r.resposta)}”</p>
      <div class="pedido__fotos">
        <span class="ajuda">${r.fotos.length ? `Fotos de prova (${r.fotos.length})` : 'Sem fotos de prova'}</span>
        ${fotos ? `<div class="pedido__fotos-lista">${fotos}</div>` : ''}
      </div>
      <div class="anuncio__acoes">
        <button type="button" class="botao botao--preto botao--pequeno" data-aceitar="${r.id}">Aceitar</button>
        <button type="button" class="botao botao--contorno botao--pequeno" data-recusar="${r.id}">Recusar</button>
      </div>
    </div>`;
}

// HTML de um anúncio
function anuncioHtml(item, reclamacoes) {
  const pendentes = reclamacoes.filter((r) => r.estado === 'pendente');
  const aberto = item.estado !== 'devolvido';
  const foto = item.fotos[0];

  return `
    <article class="anuncio">
      <div class="anuncio__foto">${foto ? `<img src="${esc(foto)}" alt="">` : fotoVazia()}</div>

      <div class="anuncio__info">
        ${etiquetaEstado(item, pendentes.length)}
        <h2><a href="detalhe.html?id=${item.id}">${esc(item.titulo)}</a></h2>
        <span class="ajuda">${esc(item.local)} · ${formatarData(item.dataEncontrado)}</span>
      </div>

      ${aberto ? `<div class="anuncio__acoes">
        <button type="button" class="botao botao--contorno botao--pequeno" data-devolvido="${item.id}">Marcar como devolvido</button>
      </div>` : ''}

      ${item.estado === 'por_reclamar' ? pendentes.map(pedidoHtml).join('') : ''}
    </article>`;
}

// ---------- Carregar e desenhar ----------
async function carregar() {
  try {
    const items = await itemsService.getMine();

    if (items.length === 0) {
      lista.innerHTML = `
        <div class="vazio">
          <p>Ainda não publicaste nenhum objeto.</p>
          <a href="publicar.html" class="botao botao--preto botao--pequeno">Publicar objeto</a>
        </div>`;
      return;
    }

    // Para cada anúncio, vai buscar os pedidos (todos ao mesmo tempo)
    const reclamacoes = await Promise.all(items.map((i) => reclamacoesService.getByItem(i.id)));
    lista.innerHTML = items.map((item, n) => anuncioHtml(item, reclamacoes[n])).join('');
  } catch (erro) {
    lista.innerHTML = `<p class="erro">${esc(erro.message)}</p>`;
  }
}

// ---------- Botões (um só ouvinte para a lista toda) ----------
lista.addEventListener('click', async (e) => {
  const botao = e.target.closest('button');
  if (!botao) return;
  botao.disabled = true;

  if (botao.dataset.aceitar) await reclamacoesService.responder(botao.dataset.aceitar, 'aceite');
  if (botao.dataset.recusar) await reclamacoesService.responder(botao.dataset.recusar, 'recusada');
  if (botao.dataset.devolvido) await itemsService.update(botao.dataset.devolvido, { estado: 'devolvido' });

  carregar(); // volta a desenhar com os dados novos
});
