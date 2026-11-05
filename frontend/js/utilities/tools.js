const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

function hora(data) {
  return `${String(data.getHours()).padStart(2, '0')}:${String(data.getMinutes()).padStart(2, '0')}`;
}

export function formatarData(textoISO) {
  const data = new Date(textoISO);
  const hoje = new Date();
  const ontem = new Date();
  ontem.setDate(hoje.getDate() - 1);

  if (data.toDateString() === hoje.toDateString()) return `Hoje, ${hora(data)}`;
  if (data.toDateString() === ontem.toDateString()) return `Ontem, ${hora(data)}`;
  return `${data.getDate()} ${MESES[data.getMonth()]}`;
}

export function formatarDataLonga(textoISO) {
  const data = new Date(textoISO);
  return `${data.getDate()} ${MESES[data.getMonth()]} ${data.getFullYear()}, ${hora(data)}`;
}

export function iniciais(nome = '') {
  const partes = nome.trim().split(/\s+/);
  const primeira = partes[0]?.charAt(0) ?? '';
  const ultima = partes.length > 1 ? partes[partes.length - 1].charAt(0) : '';
  return (primeira + ultima).toUpperCase() || '?';
}

export function nomeCurto(nome = '') {
  const partes = nome.trim().split(/\s+/);
  return partes.length > 1 ? `${partes[0]} ${partes[partes.length - 1].charAt(0)}.` : partes[0];
}

export function esc(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);
}

export function lerParametro(nome) {
  return new URLSearchParams(window.location.search).get(nome);
}

export function fotoVazia() {
  return `<svg class="foto-vazia" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <line x1="0" y1="0" x2="100" y2="100" vector-effect="non-scaling-stroke"></line>
    <line x1="100" y1="0" x2="0" y2="100" vector-effect="non-scaling-stroke"></line>
  </svg>`;
}

export const ICONE_FECHAR = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>`;

export function lerImagem(ficheiro, tamanhoMax = 900) {
  return new Promise((resolve, reject) => {
    const leitor = new FileReader();
    leitor.onerror = () => reject(new Error('Não foi possível ler a imagem.'));
    leitor.onload = () => {
      const img = new Image();
      img.onerror = () => resolve(leitor.result);
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
    input.value = '';
  });

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
