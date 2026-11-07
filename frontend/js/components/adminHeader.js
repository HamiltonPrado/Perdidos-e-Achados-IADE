import { url } from '../utilities/caminhos.js';
import { getUser } from '../utilities/auth.js';
import { authService } from '../services/authService.js';
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
  const links = LINKS.map(([nome, href, texto]) =>
    `<a href="${href}" class="admin-header__link${nome === pagina ? ' ativo' : ''}"${nome === pagina ? ' aria-current="page"' : ''}>${texto}</a>`
  ).join('');

  document.getElementById('admin-header').innerHTML = `
    <div class="contentor admin-header__barra">
      <a href="${url('admin/index.html')}" class="admin-header__marca" aria-label="Painel da receção">${logoHtml()}</a>
      <nav class="admin-header__nav" aria-label="Área da receção">${links}</nav>
      <div class="admin-header__conta">
        <button type="button" class="admin-header__sair" id="admin-sair">Terminar sessão</button>
        <span class="admin-header__avatar" title="${esc(user?.nome)}">${esc(iniciais(user?.nome))}</span>
      </div>
    </div>`;

  document.getElementById('admin-sair').addEventListener('click', async () => {
    await authService.sair();
    window.location.href = url('aluno/entrar.html?perfil=admin');
  });
}
