/*
 * ItemCard.jsx — CARTÃO DE UM OBJETO NA LISTA
 * ------------------------------------------------------------
 * Mostra foto, categoria, título, local e data de um objeto.
 * O cartão inteiro é um link para o detalhe (/items/:id).
 *
 * Recebe por props:
 *   item → um objeto no formato definido em docs/api.md
 *
 * Exemplo de uso:
 *   <ItemCard item={item} />
 *
 * É reutilizável: serve no Início e pode servir em "Os meus anúncios".
 * Estilos: src/styles/components/ItemCard.css
 */

import { Link } from 'react-router-dom';
import '../styles/components/ItemCard.css';

// Nomes dos meses abreviados, para não depender do idioma do browser
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/*
 * Formata a data como nos mockups:
 *   hoje  → "Hoje, 11:20"
 *   ontem → "Ontem, 16:05"
 *   resto → "13 out"
 */
function formatarData(textoISO) {
  const data = new Date(textoISO);
  const hoje = new Date();
  const ontem = new Date();
  ontem.setDate(hoje.getDate() - 1);

  const hora = data.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });

  if (data.toDateString() === hoje.toDateString()) return `Hoje, ${hora}`;
  if (data.toDateString() === ontem.toDateString()) return `Ontem, ${hora}`;
  return `${data.getDate()} ${MESES[data.getMonth()]}`;
}

export default function ItemCard({ item }) {
  // Primeira foto do objeto (ou undefined se não tiver nenhuma)
  // O "?." evita erro se "fotos" não existir.
  const foto = item.fotos?.[0];

  return (
    <Link to={`/items/${item.id}`} className="cartao">

      {/* ZONA DA FOTO */}
      <div className="cartao__foto">
        {foto ? (
          // Se há foto, mostra-a
          <img src={foto} alt="" />
        ) : (
          // Se não há, mostra um retângulo com um X (placeholder)
          <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <line x1="0" y1="0" x2="100" y2="100" vectorEffect="non-scaling-stroke" />
            <line x1="100" y1="0" x2="0" y2="100" vectorEffect="non-scaling-stroke" />
          </svg>
        )}
        {/* Etiqueta branca no canto com a categoria */}
        <span className="cartao__categoria">{item.categoria}</span>
      </div>

      {/* ZONA DO TEXTO */}
      <div className="cartao__corpo">
        <h2 className="cartao__titulo">{item.titulo}</h2>
        <p className="cartao__local">{item.local}</p>

        <div className="cartao__rodape">
          <span className="cartao__data">{formatarData(item.dataEncontrado)}</span>
          <span className="cartao__ver">Ver detalhes →</span>
        </div>
      </div>
    </Link>
  );
}
