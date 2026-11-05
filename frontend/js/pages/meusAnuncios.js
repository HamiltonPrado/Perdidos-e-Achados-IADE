import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { esc, formatarData, fotoVazia, lerParametro } from '../utilities/tools.js';
import { RECECAO } from '../utilities/rececao.js';

const lista = document.getElementById('registos');

const GRUPO = {
  por_receber: 'por_entregar',
  por_reclamar: 'na_rececao',
  reclamado: 'na_rececao',
  devolvido: 'devolvidos',
};

const ETIQUETA = {
  por_receber: '<span class="etiqueta etiqueta--aviso">Por entregar</span>',
  por_reclamar: '<span class="etiqueta">Na receção</span>',
  reclamado: '<span class="etiqueta">Pedido em validação</span>',
  devolvido: '<span class="etiqueta etiqueta--ok">Devolvido</span>',
};

const VAZIO = {
  por_entregar: 'Não tens objetos por entregar.',
  na_rececao: 'Nenhum objeto teu está na receção.',
  devolvidos: 'Ainda nenhum objeto teu foi devolvido.',
};

if (requerLogin()) {
  montarNavBar('meus-anuncios');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const itens = await itemsService.getMine();

  const contagem = { por_entregar: 0, na_rececao: 0, devolvidos: 0 };
  itens.forEach((i) => contagem[GRUPO[i.estado] ?? 'na_rececao']++);
  document.querySelectorAll('[data-contagem]').forEach((el) => {
    el.textContent = contagem[el.dataset.contagem];
  });

  let atual = lerParametro('separador') || (contagem.por_entregar > 0 ? 'por_entregar' : 'na_rececao');
  if (!(atual in contagem)) atual = 'por_entregar';

  function mostrar(separador) {
    atual = separador;
    document.querySelectorAll('[data-separador]').forEach((b) => {
      const ativo = b.dataset.separador === separador;
      b.classList.toggle('ativo', ativo);
      b.setAttribute('aria-selected', String(ativo));
    });

    const aviso = document.getElementById('aviso-entregar');
    aviso.hidden = !(separador === 'por_entregar' && contagem.por_entregar > 0);
    aviso.innerHTML = `<span class="aviso__rotulo">Entrega pendente</span>
      Leva os objetos à ${esc(RECECAO.responsavel)} (${esc(RECECAO.local)}, ${esc(RECECAO.horario)}) e indica a referência de cada um.`;

    const doGrupo = itens.filter((i) => (GRUPO[i.estado] ?? 'na_rececao') === separador);
    lista.innerHTML = doGrupo.length
      ? doGrupo.map(linhaHtml).join('')
      : `<div class="vazio"><p>${VAZIO[separador]}</p><a href="publicar.html" class="botao botao--contorno">Registar um objeto</a></div>`;
  }

  document.querySelector('.separadores').addEventListener('click', (e) => {
    const botao = e.target.closest('[data-separador]');
    if (botao) mostrar(botao.dataset.separador);
  });

  mostrar(atual);
}

function linhaHtml(item) {
  const foto = item.fotos[0] ? `<img src="${esc(item.fotos[0])}" alt="">` : fotoVazia();

  const acao = item.estado === 'por_receber'
    ? `<a href="registo.html?id=${item.id}" class="botao botao--destaque botao--pequeno">Ver instruções</a>`
    : item.estado === 'devolvido'
      ? `<a href="registo.html?id=${item.id}" class="botao botao--contorno botao--pequeno">Ver registo</a>`
      : `<a href="detalhe.html?id=${item.id}" class="botao botao--contorno botao--pequeno">Ver anúncio</a>`;

  return `
    <article class="registo-linha">
      <div class="registo-linha__foto">${foto}</div>
      <div class="registo-linha__texto">
        ${ETIQUETA[item.estado] ?? ''}
        <h2 class="registo-linha__titulo">${esc(item.titulo)}</h2>
        <p class="ajuda">${esc(item.referencia)} · ${esc(item.local)} · ${esc(formatarData(item.dataEncontrado))}</p>
      </div>
      <div class="registo-linha__acao">${acao}</div>
    </article>`;
}
