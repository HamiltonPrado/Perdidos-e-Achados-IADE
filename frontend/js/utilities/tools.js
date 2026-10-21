/*
 * utilities/tools.js — FUNÇÕES PEQUENAS REUTILIZÁVEIS
 * ------------------------------------------------------------
 * Funções que não pertencem a nenhuma página em particular.
 * Uso:   import { formatarData, esc } from '../utilities/tools.js';
 */

const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

// "09:05" — horas com dois dígitos
function hora(data) {
  return `${String(data.getHours()).padStart(2, '0')}:${String(data.getMinutes()).padStart(2, '0')}`;
}

/*
 * formatarData — data curta, como nos mockups
 *   hoje → "Hoje, 11:20" · ontem → "Ontem, 16:05" · resto → "13 out"
 */
export function formatarData(textoISO) {
  const data = new Date(textoISO);
  const hoje = new Date();
  const ontem = new Date();
  ontem.setDate(hoje.getDate() - 1);

  if (data.toDateString() === hoje.toDateString()) return `Hoje, ${hora(data)}`;
  if (data.toDateString() === ontem.toDateString()) return `Ontem, ${hora(data)}`;
  return `${data.getDate()} ${MESES[data.getMonth()]}`;
}

// formatarDataLonga — "15 out 2026, 11:20" (página Detalhe)
export function formatarDataLonga(textoISO) {
  const data = new Date(textoISO);
  return `${data.getDate()} ${MESES[data.getMonth()]} ${data.getFullYear()}, ${hora(data)}`;
}

// iniciais — "Ana Silva" → "AS" (avatar sem foto)
export function iniciais(nome = '') {
  const partes = nome.trim().split(/\s+/);
  const primeira = partes[0]?.charAt(0) ?? '';
  const ultima = partes.length > 1 ? partes[partes.length - 1].charAt(0) : '';
  return (primeira + ultima).toUpperCase() || '?';
}

// nomeCurto — "Ana Silva" → "Ana S." (nome público nos anúncios)
export function nomeCurto(nome = '') {
  const partes = nome.trim().split(/\s+/);
  return partes.length > 1 ? `${partes[0]} ${partes[partes.length - 1].charAt(0)}.` : partes[0];
}

/*
 * esc — "escapa" texto antes de o pôr em innerHTML.
 * IMPORTANTE: usar sempre que o texto vem do utilizador ou do backend
 * (títulos, descrições, nomes…). Impede que alguém escreva <script>
 * num título e esse código corra no browser dos outros.
 */
export function esc(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);
}

/*
 * lerParametro — lê um valor do endereço da página.
 * Ex.: em "detalhe.html?id=3", lerParametro('id') → "3"
 */
export function lerParametro(nome) {
  return new URLSearchParams(window.location.search).get(nome);
}

// fotoVazia — devolve o HTML do retângulo com X (quando não há foto)
export function fotoVazia() {
  return `<svg class="foto-vazia" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <line x1="0" y1="0" x2="100" y2="100" vector-effect="non-scaling-stroke"></line>
    <line x1="100" y1="0" x2="0" y2="100" vector-effect="non-scaling-stroke"></line>
  </svg>`;
}

// Ícone ✕ (usado nos botões de remover foto)
export const ICONE_FECHAR = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>`;

/*
 * lerImagem — transforma um ficheiro de imagem num texto "data:image/…"
 * que dá para pôr num <img src="…">. Também reduz a imagem (máx. 900px)
 * para não ocupar demasiado espaço.
 * Uso:   const src = await lerImagem(input.files[0]);
 */
export function lerImagem(ficheiro, tamanhoMax = 900) {
  return new Promise((resolve, reject) => {
    const leitor = new FileReader();
    leitor.onerror = () => reject(new Error('Não foi possível ler a imagem.'));
    leitor.onload = () => {
      const img = new Image();
      img.onerror = () => resolve(leitor.result); // não é imagem "desenhável": devolve como está
      img.onload = () => {
        const escala = Math.min(1, tamanhoMax / Math.max(img.width, img.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * escala);
        canvas.height = Math.round(img.height * escala);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        resolve(canvas.toDataURL('image/jpeg', 0.8));
      };
      img.src = leitor.result;
    };
    leitor.readAsDataURL(ficheiro);
  });
}

/*
 * seletorDeFotos — liga um <input type="file"> a uma zona de miniaturas.
 * Usado no Publicar (até 5 fotos) e no "É teu?" (até 3 fotos de prova).
 *
 *   input       → o <input type="file">
 *   zona        → o elemento onde aparecem as miniaturas
 *   maximo      → número máximo de fotos
 *   aoPassar    → função chamada se tentarem pôr fotos a mais
 *
 * Devolve um objeto com:
 *   .ficheiros  → lista dos ficheiros escolhidos (File)
 *   .limpar()   → remove todas
 */
export function seletorDeFotos(input, zona, maximo, aoPassar = () => {}) {
  const estado = { ficheiros: [], previews: [] };

  function desenhar() {
    zona.innerHTML = estado.previews.map((src, n) => `
      <div class="miniatura-upload">
        <img src="${src}" alt="Foto ${n + 1}">
        <button type="button" class="miniatura-upload__remover" data-remover="${n}" aria-label="Remover foto ${n + 1}">${ICONE_FECHAR}</button>
      </div>`).join('');
  }

  input.addEventListener('change', async () => {
    const novos = Array.from(input.files);
    const livres = maximo - estado.ficheiros.length;
    if (novos.length > livres) aoPassar();

    for (const ficheiro of novos.slice(0, Math.max(livres, 0))) {
      estado.ficheiros.push(ficheiro);
      estado.previews.push(await lerImagem(ficheiro, 300));
      desenhar();
    }
    input.value = ''; // permite voltar a escolher o mesmo ficheiro
  });

  // Clique no ✕ de uma miniatura
  zona.addEventListener('click', (e) => {
    const botao = e.target.closest('[data-remover]');
    if (!botao) return;
    const n = Number(botao.dataset.remover);
    estado.ficheiros.splice(n, 1);
    estado.previews.splice(n, 1);
    desenhar();
  });

  return {
    get ficheiros() { return estado.ficheiros; },
    limpar() { estado.ficheiros = []; estado.previews = []; desenhar(); },
  };
}
