import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { procurasService } from '../services/procurasService.js';
import { paraData } from '../services/notificacoesService.js';
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
  por_levantar: 'Não tens objetos por levantar. Quando a receção validar um pedido teu, o objeto aparece aqui.',
  devolvidos: 'Ainda nenhum objeto teu foi devolvido.',
};

if (requerLogin()) {
  montarNavBar('meus-anuncios');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const [meus, reclamacoes, procuras] = await Promise.all([
    itemsService.getMine(),
    // Se as reclamações ou as procuras falharem, o resto da página continua a funcionar.
    reclamacoesService.getMinhas().catch(() => []),
    carregarProcuras().catch(() => []),
  ]);

  // Arquivados pela equipa não pertencem a nenhum separador.
  const itens = meus.filter((i) => i.estado in GRUPO);

  // Por levantar: reclamações aprovadas cujo objeto ainda não foi entregue.
  const porLevantar = reclamacoes.filter((r) => r.estado === 'aprovada' && r.estadoArtigo === 'ativo');

  const contagem = { procuras: procuras.length, por_entregar: 0, na_rececao: 0, por_levantar: porLevantar.length, devolvidos: 0 };
  const comSugestoes = procuras.some((p) => p.sugestoes.length > 0);
  itens.forEach((i) => contagem[GRUPO[i.estado] ?? 'na_rececao']++);

  // Um objeto pronto a levantar vem primeiro; depois, um objeto que a IA encontrou para ti.
  const porOmissao = contagem.por_levantar > 0 ? 'por_levantar'
    : comSugestoes ? 'procuras'
      : contagem.por_entregar > 0 ? 'por_entregar'
        : contagem.na_rececao > 0 || !procuras.length ? 'na_rececao' : 'procuras';
  let atual = lerParametro('separador') || porOmissao;
  if (!(atual in contagem)) atual = 'por_entregar';

  // Foto e referência dos objetos por levantar (não vêm na reclamação); carregadas uma vez.
  const objetos = new Map();
  const aCarregar = Promise.all(porLevantar.map((r) =>
    itemsService.getById(r.itemId).then((item) => objetos.set(r.itemId, item)).catch(() => {}),
  ));

  function atualizarContagens() {
    contagem.procuras = procuras.length;
    document.querySelectorAll('[data-contagem]').forEach((el) => {
      el.textContent = contagem[el.dataset.contagem];
    });
    document.querySelector('[data-separador="procuras"]')
      .classList.toggle('separador--novidade', procuras.some((p) => p.sugestoes.length > 0));
  }
  atualizarContagens();

  async function mostrar(separador) {
    atual = separador;
    document.querySelectorAll('[data-separador]').forEach((b) => {
      const ativo = b.dataset.separador === separador;
      b.classList.toggle('ativo', ativo);
      b.setAttribute('aria-selected', String(ativo));
    });

    const aviso = document.getElementById('aviso-entregar');
    aviso.hidden = !(separador === 'por_entregar' && contagem.por_entregar > 0);
    aviso.innerHTML = `<span class="aviso__rotulo">Entrega pendente</span>
      Leva os objetos à receção (${esc(RECECAO.local)}, ${esc(RECECAO.horario)}).`;

    if (separador === 'procuras') {
      lista.innerHTML = procuras.length
        ? procuras.map(linhaProcuraHtml).join('')
        : `<div class="vazio"><a href="procurar.html" class="botao botao--contorno">Encontrar um objeto</a></div>`;
      return;
    }

    if (separador === 'por_levantar') {
      if (!porLevantar.length) {
        lista.innerHTML = `<div class="vazio"><p>${VAZIO.por_levantar}</p><a href="../index.html" class="botao botao--contorno">Explorar objetos</a></div>`;
        return;
      }
      lista.innerHTML = porLevantar.map((r) => linhaLevantarHtml(r, objetos.get(r.itemId))).join('');
      await aCarregar;
      if (atual === 'por_levantar') {
        lista.innerHTML = porLevantar.map((r) => linhaLevantarHtml(r, objetos.get(r.itemId))).join('');
      }
      return;
    }

    const doGrupo = itens.filter((i) => (GRUPO[i.estado] ?? 'na_rececao') === separador);
    lista.innerHTML = doGrupo.length
      ? doGrupo.map(linhaHtml).join('')
      : `<div class="vazio"><p>${VAZIO[separador]}</p><a href="publicar.html" class="botao botao--contorno">Registar um objeto</a></div>`;
  }

  // «Já encontrei»: o perdido fica resolvido e deixa de ser comparado pela IA.
  lista.addEventListener('click', async (e) => {
    const botao = e.target.closest('[data-resolver]');
    if (!botao) return;
    const procura = procuras.find((p) => p.id === botao.dataset.resolver);
    if (!procura || !window.confirm(`Marcar «${procura.titulo}» como encontrado? Deixas de receber sugestões para este objeto.`)) return;

    botao.disabled = true;
    try {
      await procurasService.resolver(procura.id);
      procuras.splice(procuras.indexOf(procura), 1);
      atualizarContagens();
      mostrar('procuras');
    } catch (erro) {
      botao.disabled = false;
      window.alert(erro.message);
    }
  });

  document.querySelector('.separadores').addEventListener('click', (e) => {
    const botao = e.target.closest('[data-separador]');
    if (botao) mostrar(botao.dataset.separador);
  });

  mostrar(atual);
}

// Procuras ativas do aluno, cada uma com os achados que a IA sugeriu.
async function carregarProcuras() {
  const procuras = await procurasService.getMinhas();
  await Promise.all(procuras.map(async (p) => {
    const { itens } = await procurasService.sugestoes(p.id).catch(() => ({ itens: [] }));
    p.sugestoes = itens;
  }));
  return procuras;
}

function linhaProcuraHtml(procura) {
  const n = procura.sugestoes.length;
  const etiqueta = n
    ? `<span class="etiqueta etiqueta--ok">A IA encontrou ${n === 1 ? '1 objeto parecido' : `${n} objetos parecidos`}</span>`
    : '<span class="etiqueta">À procura</span>';

  const sugestoes = n ? `
      <ul class="procura-sugestoes">
        ${procura.sugestoes.map((s) => `
        <li>
          <a href="detalhe.html?id=${encodeURIComponent(s.id)}" class="procura-sugestao">
            <span class="procura-sugestao__foto">${s.fotos[0] ? `<img src="${esc(s.fotos[0])}" alt="">` : fotoVazia()}</span>
            <span class="procura-sugestao__texto">
              <strong>${esc(s.titulo)}</strong>
              <span class="ajuda">${esc([s.referencia, s.local, formatarData(s.dataEncontrado)].filter(Boolean).join(' · '))}</span>
            </span>
            <span class="procura-sugestao__ver">Ver anúncio →</span>
          </a>
        </li>`).join('')}
      </ul>` : '';

  return `
    <article class="registo-linha registo-linha--procura">
      <div class="registo-linha__foto">${fotoVazia()}</div>
      <div class="registo-linha__texto">
        ${etiqueta}
        <h2 class="registo-linha__titulo">${esc(procura.titulo)}</h2>
        <p class="ajuda">${esc([procura.referencia, procura.local, formatarData(procura.dataPerdido)].filter(Boolean).join(' · '))}</p>
      </div>
      <div class="registo-linha__acao">
        <button type="button" class="botao botao--contorno botao--pequeno" data-resolver="${esc(procura.id)}">Já encontrei</button>
      </div>
      ${sugestoes}
    </article>`;
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

// Ecrã 10 do Figma: objeto validado pela receção, à espera de ser levantado.
function linhaLevantarHtml(reclamacao, item) {
  const foto = item?.fotos[0] ? `<img src="${esc(item.fotos[0])}" alt="">` : fotoVazia();
  const detalhes = [item?.referencia, reclamacao.pontoRecolha || RECECAO.local].filter(Boolean).join(' · ');
  const validado = paraData(reclamacao.decididoEm);

  return `
    <article class="registo-linha registo-linha--levantar">
      <div class="registo-linha__foto">${foto}</div>
      <div class="registo-linha__texto">
        <span class="etiqueta etiqueta--ok registo-linha__pronto">Pronto para levantamento</span>
        <h2 class="registo-linha__titulo">${esc(reclamacao.titulo)}</h2>
        <p class="ajuda">${esc(detalhes)}</p>
        ${validado ? `<small class="registo-linha__prazo">Validado ${esc(formatarData(validado).replace(/^(Hoje|Ontem)/, (d) => d.toLowerCase()))} · levanta em dias úteis, ${esc(RECECAO.horario)}</small>` : ''}
      </div>
      <div class="registo-linha__acao">
        <a href="levantamento.html?reclamacao=${encodeURIComponent(reclamacao.id)}" class="botao botao--preto botao--pequeno">Ver levantamento</a>
      </div>
    </article>`;
}
