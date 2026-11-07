const RAIZ = new URL('../../', import.meta.url);

export function url(caminho) {
  return new URL(caminho, RAIZ).href;
}

export function caminhoAtual() {
  return window.location.href.slice(RAIZ.href.length);
}
