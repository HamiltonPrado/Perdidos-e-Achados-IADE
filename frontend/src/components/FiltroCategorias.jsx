/*
 * FiltroCategorias.jsx — BOTÕES DE CATEGORIA
 * ------------------------------------------------------------
 * Mostra "Todos", "Eletrónica", "Documentos", … como botões.
 * O botão escolhido fica preto.
 *
 * Este componente NÃO guarda qual está escolhido — quem o usa
 * (a página Início) é que guarda e passa por "props":
 *
 *   categorias → lista de { id, nome } vinda do api.js
 *   valor      → id da categoria escolhida ('' = Todos)
 *   aoMudar    → função chamada com o novo id quando se clica
 *
 * Exemplo de uso:
 *   <FiltroCategorias categorias={lista} valor={cat} aoMudar={setCat} />
 *
 * Estilos: src/styles/components/FiltroCategorias.css
 */

import '../styles/components/FiltroCategorias.css';

export default function FiltroCategorias({ categorias, valor, aoMudar }) {
  // Junta a opção "Todos" (id vazio) no início da lista
  const opcoes = [{ id: '', nome: 'Todos' }, ...categorias];

  return (
    <div role="group" aria-label="Categorias" className="filtro">
      {/* .map() cria um botão para cada categoria */}
      {opcoes.map((cat) => {
        const ativo = cat.id === valor; // este é o escolhido?

        return (
          <button
            key={cat.id || 'todos'} // o React precisa de uma "key" única em listas
            type="button"
            aria-pressed={ativo} // diz aos leitores de ecrã se está selecionado
            className={ativo ? 'filtro__botao filtro__botao--ativo' : 'filtro__botao'}
            onClick={() => aoMudar(cat.id)} // avisa a página de qual foi clicado
          >
            {cat.nome}
          </button>
        );
      })}
    </div>
  );
}
