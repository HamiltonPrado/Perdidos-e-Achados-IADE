import { url } from '../utilities/caminhos.js';
import { getUser } from '../utilities/auth.js';
import { esc, iniciais } from '../utilities/tools.js';
import { logoHtml } from './logo.js';

const LINKS = [
  ['painel', url('admin/index.html'), 'Painel'],
  ['objetos', url('admin/objetos.html'), 'Objetos'],
  ['pedidos', url('admin/objetos.html?separador=pedidos'), 'Pedidos'],
  ['publicar', url('admin/publicar.html'), 'Publicar'],
];

export function montarAdminHeader(pagina) {
  const user = getUser();
  const avatar = user?.foto ? `<img src="${esc(user.foto)}" alt="">` : esc(iniciais(user?.nome));
  const links = LINKS.map(([nome, href, texto]) =>
    `<a href="${href}" class="admin-header__link${nome === pagina ? ' ativo' : ''}"${nome === pagina ? ' aria-current="page"' : ''}>${texto}</a>`
  ).join('');

  document.getElementById('admin-header').innerHTML = `
    <div class="contentor admin-header__barra">
      <a href="${url('admin/index.html')}" class="admin-header__marca" aria-label="Painel da receção">${logoHtml()}</a>
      <nav class="admin-header__nav" aria-label="Área da receção">${links}</nav>
      <div class="admin-header__conta">
        <a href="${url('admin/perfil.html')}" class="admin-header__avatar${pagina === 'perfil' ? ' ativo' : ''}" title="${esc(user?.nome)}" aria-label="O meu perfil"${pagina === 'perfil' ? ' aria-current="page"' : ''}>${avatar}</a>
      </div>
    </div>`;
}
