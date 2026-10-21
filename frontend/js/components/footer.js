/*
 * components/footer.js — RODAPÉ (preto)
 * ------------------------------------------------------------
 * Igual ao Footer.vue do AquaPoint.
 * Cada página tem <footer id="footer"></footer> e chama montarFooter().
 *
 * Estilos: assets/css/components/footer.css
 */

export function montarFooter() {
  const ano = new Date().getFullYear(); // o © nunca fica desatualizado

  document.getElementById('footer').innerHTML = `
    <div class="contentor footer__barra">
      <a href="index.html" class="footer__logo">IADE</a>
      <span>IADE © ${ano} · Achados e Perdidos · Projeto académico</span>
    </div>`;
}
