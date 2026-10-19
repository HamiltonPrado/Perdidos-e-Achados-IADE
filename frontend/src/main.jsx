/*
 * main.jsx — PONTO DE ENTRADA DA APP
 * ------------------------------------------------------------
 * É o primeiro ficheiro que corre. Faz só duas coisas:
 *   1. Carrega os estilos globais (cores, fontes, botões…).
 *   2. "Desenha" o componente <App /> dentro da <div id="root">
 *      que está no index.html.
 *
 * Normalmente não precisas de mexer aqui.
 */

import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// Estilos que valem para a app toda.
// A ordem importa: primeiro as variáveis (cores/fontes), depois o resto.
import './styles/variables.css';
import './styles/global.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  // StrictMode ajuda a encontrar erros durante o desenvolvimento.
  // Não aparece nada no ecrã e não afeta a versão final.
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
