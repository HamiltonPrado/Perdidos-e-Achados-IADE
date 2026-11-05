export function logoHtml() {
  return `
    <span class="logo">
      <svg class="logo__icone" viewBox="0 0 48 48" aria-hidden="true">
        <circle cx="20" cy="20" r="12"></circle>
        <path d="m29 29 11 11"></path>
        <path class="logo__pin" d="M20 13a5.5 5.5 0 0 0-5.5 5.5C14.5 24 20 28 20 28s5.5-4 5.5-9.5A5.5 5.5 0 0 0 20 13Z"></path>
        <circle class="logo__ponto" cx="20" cy="18.5" r="1.8"></circle>
      </svg>
      <span class="logo__texto"><b>Lost</b><em>&amp; Found</em></span>
    </span>`;
}
