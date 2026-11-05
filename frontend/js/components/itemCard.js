import { esc, formatarData, fotoVazia } from '../utilities/tools.js';

export function cartaoItem(item) {
  const foto = item.fotos[0];

  return `
    <a href="detalhe.html?id=${item.id}" class="cartao">
      <div class="cartao__foto">
        ${foto ? `<img src="${esc(foto)}" alt="" loading="lazy">` : fotoVazia()}
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
