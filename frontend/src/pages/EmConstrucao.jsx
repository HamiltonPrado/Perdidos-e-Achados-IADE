/*
 * EmConstrucao.jsx — PÁGINA TEMPORÁRIA
 * ------------------------------------------------------------
 * Usada no App.jsx para as páginas que ainda não foram feitas.
 * Quando fizeres uma página nova, troca-a no App.jsx e
 * esta deixa de ser usada para essa rota.
 *
 * Recebe por props:
 *   titulo → texto do título (ex.: "Publicar objeto")
 */

import { Link } from 'react-router-dom';

export default function EmConstrucao({ titulo }) {
  return (
    <main className="contentor em-construcao">
      <h1>{titulo}</h1>
      <p>Esta página ainda está em construção.</p>
      <Link to="/">← Voltar aos achados</Link>
    </main>
  );
}
