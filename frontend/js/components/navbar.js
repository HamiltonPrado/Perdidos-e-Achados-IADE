/*
 * components/navbar.js — BARRA DE NAVEGAÇÃO (topo preto)
 * ------------------------------------------------------------
 * Igual ao NavBar.vue do AquaPoint.
 * Em vez de copiar o mesmo HTML para as 5 páginas, cada página tem
 * só <header id="navbar"></header> e chama montarNavBar(), que
 * escreve lá dentro o logótipo, os links e o avatar.
 *
 * Uso (no js de cada página):
 *     import { montarNavBar } from '../components/navbar.js';
 *     montarNavBar('inicio');   // nome da página atual, para a sublinhar
 *
 * Estilos: assets/css/components/navbar.css
 */

import { getUser, isLoggedIn, login, logout, USER_EXEMPLO } from '../utilities/auth.js';
import { USAR_MOCKS } from '../services/api.js';
import { esc, iniciais } from '../utilities/tools.js';

// Links do menu: [nome da página, ficheiro, texto]
const LINKS = [
  ['inicio', 'index.html', 'Achados'],
  ['publicar', 'publicar.html', 'Publicar'],
  ['meus-anuncios', 'meus-anuncios.html', 'Os meus anúncios'],
];

const ICONE_MENU = 'M4 7h16M4 12h16M4 17h16';
const ICONE_FECHAR = 'M6 6l12 12M18 6L6 18';

export function montarNavBar(paginaAtual) {
  const user = getUser();
  const comSessao = isLoggedIn();

  // Cada link recebe a classe "ativo" se for a página atual
  const links = LINKS.map(([nome, ficheiro, texto]) => {
    const ativo = nome === paginaAtual;
    return `<a href="${ficheiro}" class="navbar__link${ativo ? ' ativo' : ''}"${ativo ? ' aria-current="page"' : ''}>${texto}</a>`;
  }).join('');

  // Avatar: foto, ou as iniciais se não houver foto
  const avatar = user?.foto ? `<img src="${user.foto}" alt="">` : esc(iniciais(user?.nome));
  const perfilAtivo = paginaAtual === 'perfil';

  // Parte da direita: com sessão → Perfil + Terminar sessão + avatar; sem sessão → Entrar
  const zonaSessao = comSessao
    ? `<a href="perfil.html" class="navbar__link navbar__perfil${perfilAtivo ? ' ativo' : ''}">Perfil</a>
       <button type="button" class="navbar__link" id="navbar-sair">Terminar sessão</button>
       <a href="perfil.html" class="navbar__avatar" aria-label="O meu perfil">${avatar}</a>`
    : `<button type="button" class="navbar__link" id="navbar-entrar">Entrar</button>`;

  document.getElementById('navbar').innerHTML = `
    <div class="contentor navbar__barra">
      <a href="index.html" class="navbar__marca">
        <span class="navbar__logo">IADE</span>
        <span class="navbar__nome">Achados e Perdidos</span>
      </a>

      <button type="button" class="navbar__menu-botao" id="navbar-menu-botao"
              aria-label="Abrir menu" aria-expanded="false" aria-controls="navbar-menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path id="navbar-menu-icone" d="${ICONE_MENU}"></path>
        </svg>
      </button>

      <nav class="navbar__nav" id="navbar-menu" aria-label="Principal">
        ${links}
        ${zonaSessao}
      </nav>
    </div>`;

  // --- Botão ☰ (telemóvel): abre e fecha o menu ---
  const botaoMenu = document.getElementById('navbar-menu-botao');
  botaoMenu.addEventListener('click', () => {
    const aberto = document.getElementById('navbar-menu').classList.toggle('navbar__nav--aberto');
    botaoMenu.setAttribute('aria-expanded', String(aberto));
    botaoMenu.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    document.getElementById('navbar-menu-icone').setAttribute('d', aberto ? ICONE_FECHAR : ICONE_MENU);
  });

  // --- Terminar sessão → volta à página inicial ---
  document.getElementById('navbar-sair')?.addEventListener('click', () => {
    logout();
    window.location.href = 'index.html';
  });

  // --- Entrar ---
  // TODO: quando existir login.html, trocar por: window.location.href = 'login.html'
  document.getElementById('navbar-entrar')?.addEventListener('click', () => {
    if (USAR_MOCKS) {
      login(USER_EXEMPLO, 'token-de-exemplo'); // entra com a Ana Silva de exemplo
      window.location.reload();
    }
  });
}
