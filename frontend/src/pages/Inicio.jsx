/*
 * Inicio.jsx — PÁGINA INICIAL (lista de objetos encontrados)
 * ------------------------------------------------------------
 * Endereço: "/"
 * Mockup:   "1 · Início — lista de achados"
 *
 * O que faz:
 *   - Mostra todos os objetos por reclamar, em cartões.
 *   - Permite pesquisar por texto e filtrar por categoria, local e ordem.
 *   - Botão "Encontrei um objeto" leva à página Publicar.
 *
 * Como funciona (resumo):
 *   1. Guarda os filtros em "estado" (useState).
 *   2. Sempre que um filtro muda, pede a lista ao api.js (useEffect).
 *   3. Desenha um <ItemCard> para cada objeto recebido.
 *
 * Estilos: src/styles/pages/Inicio.css
 */

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

// Componentes reutilizáveis
import FiltroCategorias from '../components/FiltroCategorias';
import ItemCard from '../components/ItemCard';

// Funções que vão buscar dados (aos mocks ou ao backend — ver api.js)
import { listarItems, listarCategorias, listarLocais } from '../services/api';

import '../styles/pages/Inicio.css';

export default function Inicio() {
  /* =========================================================
   * ESTADO — tudo o que pode mudar no ecrã
   * Cada useState devolve [valor, funçãoParaMudarOValor]
   * ========================================================= */

  // Dados que vêm do api.js
  const [items, setItems] = useState([]);           // objetos a mostrar
  const [categorias, setCategorias] = useState([]); // opções dos botões
  const [locais, setLocais] = useState([]);         // opções do select "Local"

  // Filtros escolhidos pelo utilizador
  const [textoPesquisa, setTextoPesquisa] = useState(''); // o que está escrito na caixa
  const [pesquisa, setPesquisa] = useState('');           // o que foi pesquisado (ao carregar "Pesquisar")
  const [categoria, setCategoria] = useState('');         // '' = Todos
  const [local, setLocal] = useState('');                 // '' = Todo o campus
  const [ordem, setOrdem] = useState('recentes');         // 'recentes' ou 'antigos'

  // Estado do pedido
  const [aCarregar, setACarregar] = useState(true);
  const [erro, setErro] = useState('');

  /* =========================================================
   * EFEITOS — código que corre "sozinho" em certos momentos
   * ========================================================= */

  // Corre UMA vez, quando a página abre ([] vazio = só no início).
  // Vai buscar as opções dos filtros.
  useEffect(() => {
    listarCategorias().then(setCategorias);
    listarLocais().then(setLocais);
  }, []);

  // Corre sempre que um dos valores entre [ ] muda.
  // Pede a lista de objetos com os filtros atuais.
  useEffect(() => {
    setACarregar(true);
    setErro('');

    listarItems({ q: pesquisa, categoria, local, ordem })
      .then(setItems)                      // correu bem → guarda a lista
      .catch((e) => setErro(e.message))    // correu mal → guarda a mensagem de erro
      .finally(() => setACarregar(false)); // em qualquer caso, terminou de carregar
  }, [pesquisa, categoria, local, ordem]);

  /* =========================================================
   * FUNÇÕES DE EVENTOS — o que acontece quando o utilizador age
   * ========================================================= */

  // Ao carregar em "Pesquisar" (ou Enter na caixa)
  function submeterPesquisa(evento) {
    evento.preventDefault(); // impede o browser de recarregar a página
    setPesquisa(textoPesquisa);
  }

  // Botão "Limpar filtros" (aparece quando não há resultados)
  function limparFiltros() {
    setTextoPesquisa('');
    setPesquisa('');
    setCategoria('');
    setLocal('');
  }

  // Texto da contagem: "1 objeto" ou "6 objetos"
  const textoContagem = `${items.length} ${items.length === 1 ? 'objeto' : 'objetos'} por reclamar`;

  /* =========================================================
   * O QUE APARECE NO ECRÃ
   * ========================================================= */
  return (
    <main>

      {/* ---------- FAIXA BRANCA DO TOPO ---------- */}
      <section className="destaque">
        <div className="contentor destaque__conteudo">

          <div className="destaque__texto">
            <p className="destaque__sobretitulo">Portal do Estudante · Serviços</p>
            <h1 className="destaque__titulo">Perdeste alguma coisa no campus?</h1>

            {/* Pesquisa — onSubmit corre ao carregar no botão ou em Enter */}
            <form role="search" className="pesquisa" onSubmit={submeterPesquisa}>
              {/* Label escondida visualmente, mas lida por leitores de ecrã */}
              <label htmlFor="pesquisa" className="so-leitor">
                Pesquisar objetos
              </label>
              <input
                id="pesquisa"
                type="search"
                className="campo pesquisa__campo"
                placeholder="Ex.: carteira, chaves, auriculares…"
                value={textoPesquisa}                              // o input mostra o estado…
                onChange={(e) => setTextoPesquisa(e.target.value)} // …e atualiza-o ao escrever
              />
              <button type="submit" className="botao botao--preto">
                Pesquisar
              </button>
            </form>
          </div>

          {/* Botão azul → página Publicar */}
          <Link to="/publicar" className="botao botao--destaque">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
              <path d="M12 5v14M5 12h14" />
            </svg>
            Encontrei um objeto
          </Link>
        </div>
      </section>

      {/* ---------- FILTROS + LISTA ---------- */}
      <div className="contentor lista">

        <div className="lista__filtros">
          {/* Botões de categoria (componente reutilizável) */}
          <FiltroCategorias categorias={categorias} valor={categoria} aoMudar={setCategoria} />

          <div className="lista__selects">
            {/* Select de local — as opções vêm do api.js */}
            <label className="seletor">
              Local
              <select value={local} onChange={(e) => setLocal(e.target.value)}>
                <option value="">Todo o campus</option>
                {locais.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.nome}
                  </option>
                ))}
              </select>
            </label>

            {/* Select de ordem */}
            <label className="seletor">
              Ordenar
              <select value={ordem} onChange={(e) => setOrdem(e.target.value)}>
                <option value="recentes">Mais recentes</option>
                <option value="antigos">Mais antigos</option>
              </select>
            </label>
          </div>
        </div>

        {/* Mensagem de erro (só aparece se "erro" tiver texto) */}
        {erro && <p className="lista__mensagem" role="alert">{erro}</p>}

        {/* Contagem ou "A carregar…" — aria-live anuncia mudanças a leitores de ecrã */}
        {!erro && (
          <p className="lista__contagem" aria-live="polite">
            {aCarregar ? 'A carregar…' : textoContagem}
          </p>
        )}

        {/* Lista vazia — só quando terminou de carregar e não há resultados */}
        {!aCarregar && !erro && items.length === 0 && (
          <div className="lista__vazia">
            <p>Nenhum objeto encontrado com estes filtros.</p>
            <button type="button" className="botao botao--preto" onClick={limparFiltros}>
              Limpar filtros
            </button>
          </div>
        )}

        {/* Grelha de cartões — um ItemCard por objeto */}
        <div className="grelha">
          {items.map((item) => (
            <ItemCard key={item.id} item={item} />
          ))}
        </div>
      </div>
    </main>
  );
}
