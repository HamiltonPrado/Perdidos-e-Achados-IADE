import { getUser, isLoggedIn, logout } from '../utilities/auth.js';
import { esc, iniciais } from '../utilities/tools.js';
import { logoHtml } from './logo.js';
import { itemsService } from '../services/itemsService.js';

const LINKS = [
  ['inicio', 'index.html', 'Explorar'],
  ['procurar', 'procurar.html', 'Encontrar'],
  ['publicar', 'publicar.html', 'Publicar'],
  ['meus-anuncios', 'meus-anuncios.html', 'Meus anúncios'],
];

const ICONE_MENU = 'M4 7h16M4 12h16M4 17h16';
const ICONE_FECHAR = 'M6 6l12 12M18 6L6 18';

function paginaAtual() {
  return window.location.pathname.split('/').pop() + window.location.search;
}

export function montarNavBar(pagina) {
  const user = getUser();

  const links = LINKS.map(([nome, ficheiro, texto]) => {
    const ativo = nome === pagina;
    const bolinha = nome === 'meus-anuncios' ? '<span class="navbar__bolinha" id="navbar-bolinha" hidden></span>' : '';
    return `<a href="${ficheiro}" class="navbar__link${ativo ? ' ativo' : ''}"${ativo ? ' aria-current="page"' : ''}>${texto}${bolinha}</a>`;
  }).join('');

  const avatar = user?.foto ? `<img src="${esc(user.foto)}" alt="">` : esc(iniciais(user?.nome));

  const zonaSessao = isLoggedIn()
    ? `<a href="perfil.html" class="navbar__link navbar__perfil${pagina === 'perfil' ? ' ativo' : ''}">Perfil</a>
       <button type="button" class="navbar__link" id="navbar-sair">Terminar sessão</button>
       <a href="perfil.html" class="navbar__avatar" aria-label="O meu perfil">${avatar}</a>`
    : `<a href="entrar.html?voltar=${encodeURIComponent(paginaAtual())}" class="navbar__entrar">Entrar</a>`;

  document.getElementById('navbar').innerHTML = `
    <div class="contentor navbar__barra">
      <a href="index.html" class="navbar__marca" aria-label="Perdidos e Achados, página inicial">${logoHtml()}</a>

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

  const botaoMenu = document.getElementById('navbar-menu-botao');
  botaoMenu.addEventListener('click', () => {
    const aberto = document.getElementById('navbar-menu').classList.toggle('navbar__nav--aberto');
    botaoMenu.setAttribute('aria-expanded', String(aberto));
    botaoMenu.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    document.getElementById('navbar-menu-icone').setAttribute('d', aberto ? ICONE_FECHAR : ICONE_MENU);
  });

  document.getElementById('navbar-sair')?.addEventListener('click', () => {
    logout();
    window.location.href = 'index.html';
  });

  if (isLoggedIn()) mostrarPorEntregar();
}

async function mostrarPorEntregar() {
  try {
    const meus = await itemsService.getMine();
    const n = meus.filter((i) => i.estado === 'por_receber').length;
    const bolinha = document.getElementById('navbar-bolinha');
    if (!bolinha || n === 0) return;
    bolinha.textContent = n;
    bolinha.setAttribute('aria-label', `${n} ${n === 1 ? 'objeto' : 'objetos'} por entregar na receção`);
    bolinha.title = 'Por entregar na receção';
    bolinha.hidden = false;
  } catch {
  }
}
