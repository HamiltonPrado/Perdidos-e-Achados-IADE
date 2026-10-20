/*
 * App.jsx — ESTRUTURA GERAL E NAVEGAÇÃO (ROUTER)
 * ------------------------------------------------------------
 * Define o que aparece em TODAS as páginas (Header e Footer)
 * e qual página mostrar para cada endereço (URL).
 *
 *   /               → Início (lista de achados)
 *   /items/:id      → Detalhe de um objeto  (:id muda, ex.: /items/3)
 *   /publicar       → Publicar objeto encontrado
 *   /meus-anuncios  → Os meus anúncios
 *   qualquer outro  → Página não encontrada
 *
 * Para adicionar uma página nova:
 *   1. Cria o ficheiro em src/pages/  (ex.: Detalhe.jsx)
 *   2. Importa-o aqui em cima
 *   3. Troca o <EmConstrucao /> da rota pelo componente novo
 */

import { BrowserRouter, Routes, Route } from 'react-router-dom';

// Componentes que aparecem em todas as páginas
import Header from './components/Header';
import Footer from './components/Footer';

// Páginas (uma por ecrã)
import Inicio from './pages/Inicio';
import EmConstrucao from './pages/EmConstrucao';

export default function App() {
  return (
    // BrowserRouter "observa" o endereço do browser e
    // avisa as <Routes> quando ele muda.
    <BrowserRouter>
      <div className="app">
        {/* Topo — fixo em todas as páginas */}
        <Header />

        {/* Só UMA destas rotas é mostrada de cada vez */}
        <Routes>
          <Route path="/" element={<Inicio />} />

          {/* Páginas por fazer: trocar EmConstrucao pela página real */}
          <Route path="/items/:id" element={<EmConstrucao titulo="Detalhe do objeto" />} />
          <Route path="/publicar" element={<EmConstrucao titulo="Publicar objeto" />} />
          <Route path="/meus-anuncios" element={<EmConstrucao titulo="Os meus anúncios" />} />

          {/* "*" apanha qualquer endereço que não exista */}
          <Route path="*" element={<EmConstrucao titulo="Página não encontrada" />} />
        </Routes>

        {/* Rodapé — fixo em todas as páginas */}
        <Footer />
      </div>
    </BrowserRouter>
  );
}
