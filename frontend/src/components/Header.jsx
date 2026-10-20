/*
 * Header.jsx — BARRA DE TOPO (preta)
 * ------------------------------------------------------------
 * Aparece em todas as páginas (está no App.jsx).
 * Tem: logótipo IADE (leva ao Início), menu de navegação e avatar.
 *
 * No telemóvel (ecrãs estreitos) o menu fica escondido e aparece
 * um botão ☰ para o abrir/fechar.
 *
 * Estilos: src/styles/components/Header.css
 */

import { useState } from 'react';
import { NavLink, Link } from 'react-router-dom';
import '../styles/components/Header.css';

export default function Header() {
  // ESTADO: o menu do telemóvel está aberto (true) ou fechado (false)?
  const [menuAberto, setMenuAberto] = useState(false);

  // Fecha o menu — usado quando se carrega num link,
  // para o menu não ficar aberto depois de mudar de página.
  const fechar = () => setMenuAberto(false);

  return (
    <header className="header">
      <div className="contentor header__barra">

        {/* LOGÓTIPO — Link para "/" leva sempre à página inicial */}
        <Link to="/" className="header__marca" onClick={fechar}>
          <span className="header__logo">IADE</span>
          <span className="header__nome">Achados e Perdidos</span>
        </Link>

        {/* BOTÃO ☰ — só é visível no telemóvel (ver o CSS) */}
        <button
          type="button"
          className="header__menu-botao"
          aria-label={menuAberto ? 'Fechar menu' : 'Abrir menu'} // texto para leitores de ecrã
          aria-expanded={menuAberto}
          aria-controls="menu-principal"
          onClick={() => setMenuAberto(!menuAberto)} // inverte: aberto ↔ fechado
        >
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
            {/* Mostra um X quando está aberto, e ☰ quando está fechado */}
            {menuAberto ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
          </svg>
        </button>

        {/* MENU — a classe "--aberto" faz aparecer o menu no telemóvel */}
        <nav
          id="menu-principal"
          aria-label="Principal"
          className={menuAberto ? 'header__nav header__nav--aberto' : 'header__nav'}
        >
          {/*
            NavLink é igual ao Link, mas junta a classe "active"
            quando estamos nessa página → fica sublinhado a azul.
            "end" no "/" evita que fique ativo em todas as páginas.
          */}
          <NavLink to="/" end className="header__link" onClick={fechar}>
            Achados
          </NavLink>
          <NavLink to="/publicar" className="header__link" onClick={fechar}>
            Publicar
          </NavLink>
          <NavLink to="/meus-anuncios" className="header__link" onClick={fechar}>
            Os meus anúncios
          </NavLink>

          {/* TODO: ligar ao logout do backend quando existir */}
          <button type="button" className="header__link">
            Terminar sessão
          </button>

          {/* AVATAR — por agora mostra iniciais fixas.
              TODO: usar o nome/foto do utilizador quando houver login */}
          <span className="header__avatar" role="img" aria-label="Utilizador">
            AS
          </span>
        </nav>
      </div>
    </header>
  );
}
