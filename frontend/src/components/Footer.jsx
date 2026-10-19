/*
 * Footer.jsx — RODAPÉ (preto)
 * ------------------------------------------------------------
 * Aparece em todas as páginas (está no App.jsx).
 * Estilos: src/styles/components/Footer.css
 */

import { Link } from 'react-router-dom';
import '../styles/components/Footer.css';

export default function Footer() {
  // Ano atual, para o © nunca ficar desatualizado
  const ano = new Date().getFullYear();

  return (
    <footer className="footer">
      <div className="contentor footer__barra">
        {/* Logótipo também leva ao Início */}
        <Link to="/" className="footer__logo">IADE</Link>
        <span>IADE © {ano} · Achados e Perdidos · Projeto académico</span>
      </div>
    </footer>
  );
}
