import { url } from '../utilities/caminhos.js';
import { logoHtml } from './logo.js';

export function montarFooter() {
  const ano = new Date().getFullYear();

  document.getElementById('footer').innerHTML = `
    <div class="contentor footer__barra">
      <a href="${url('index.html')}" class="footer__marca" aria-label="Perdidos e Achados, página inicial">${logoHtml()}</a>
      <span>© ${ano} · IADE</span>
    </div>`;
}
