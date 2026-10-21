/*
 * components/itemCard.js — CARTÃO DE UM OBJETO NA LISTA
 * ------------------------------------------------------------
 * Igual ao FavoriteAquapointCard.vue do AquaPoint.
 * Devolve o HTML de um cartão (foto, categoria, título, local, data).
 * O cartão inteiro é um link para detalhe.html?id=…
 *
 * Uso:
 *     import { cartaoItem } from '../components/itemCard.js';
 *     grelha.innerHTML = items.map(cartaoItem).join('');
 *
 * Estilos: assets/css/components/item-card.css
 */

import { esc, formatarData, fotoVazia } from '../utilities/tools.js';

export function cartaoItem(item) {
  const foto = item.fotos[0];

  return `
    <a href="detalhe.html?id=${item.id}" class="cartao">
      <div class="cartao__foto">
        ${foto ? `<img src="${esc(foto)}" alt="">` : fotoVazia()}
        <span class="cartao__categoria">${esc(item.categoria)}</span>
      </div>

      <div class="cartao__corpo">
        <h2 class="cartao__titulo">${esc(item.titulo)}</h2>
        <p class="cartao__local">${esc(item.local)}</p>
        <div class="cartao__rodape">
          <span class="cartao__data">${formatarData(item.dataEncontrado)}</span>
          <span class="cartao__ver">Ver detalhes →</span>
        </div>
      </div>
    </a>`;
}
