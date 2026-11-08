import { url } from '../utilities/caminhos.js';
import { esc, fotoVazia, formatarData, ICONE_FECHAR } from '../utilities/tools.js';
import { RECECAO } from '../utilities/rececao.js';
import { notificacoesService, paraData } from '../services/notificacoesService.js';
import { itemsService } from '../services/itemsService.js';

// Sino da navbar + painel lateral de notificações (ecrã 23 do Figma).

const ICONE_SINO = `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
  <path d="M6 9a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9"></path><path d="M10 20a2 2 0 0 0 4 0"></path></svg>`;

const ICONES = {
  levantamento: '<path d="M5 12.5l4.5 4.5L19 7.5"></path>',
  entregue: '<path d="M5 12.5l4.5 4.5L19 7.5"></path>',
  recusada: '<path d="M7 7l10 10M17 7L7 17"></path>',
  reaberta: '<path d="M4 12a8 8 0 1 0 2.5-5.8M4 4v4h4"></path>',
  pendente: '<path d="M12 7v5l3 2"></path><circle cx="12" cy="12" r="8"></circle>',
  correspondencia: '<circle cx="11" cy="11" r="6"></circle><path d="M20 20l-4.5-4.5"></path>',
};

// Sessão do browser: o painel abre sozinho só uma vez (ecrã «Notificação ao entrar»).
const CHAVE_ABERTO = 'notificacoes-abertas-ao-entrar';

let notificacoes = [];

export function botaoNotificacoesHtml() {
  return `<button type="button" class="navbar__sino" id="navbar-sino" aria-haspopup="dialog" aria-controls="painel-notificacoes" aria-label="Notificações">
    ${ICONE_SINO}<span class="navbar__sino-numero" id="navbar-sino-numero" hidden></span>
  </button>`;
}

export async function montarNotificacoes() {
  const botao = document.getElementById('navbar-sino');
  if (!botao) return;

  criarPainel();
  botao.addEventListener('click', abrirPainel);

  try {
    notificacoes = await notificacoesService.getTodas();
  } catch {
    return; // sem sessão válida ou backend em baixo: o sino fica sem número
  }
  atualizarNumero();

  let jaAbriu = true;
  try {
    jaAbriu = sessionStorage.getItem(CHAVE_ABERTO) === '1';
    sessionStorage.setItem(CHAVE_ABERTO, '1');
  } catch {
  }
  if (!jaAbriu && naoLidas().length > 0) abrirPainel();
}

const naoLidas = () => notificacoes.filter((n) => !n.lida);

function atualizarNumero() {
  const n = naoLidas().length;
  const numero = document.getElementById('navbar-sino-numero');
  const botao = document.getElementById('navbar-sino');
  numero.textContent = n;
  numero.hidden = n === 0;
  botao.setAttribute('aria-label', n ? `Notificações, ${n} por ler` : 'Notificações');
}

function criarPainel() {
  if (document.getElementById('painel-notificacoes')) return;
  const fundo = document.createElement('div');
  fundo.className = 'notificacoes-fundo';
  fundo.id = 'notificacoes-fundo';
  fundo.hidden = true;
  fundo.addEventListener('click', fecharPainel);

  const painel = document.createElement('aside');
  painel.className = 'notificacoes';
  painel.id = 'painel-notificacoes';
  painel.setAttribute('role', 'dialog');
  painel.setAttribute('aria-modal', 'true');
  painel.setAttribute('aria-labelledby', 'notificacoes-titulo');
  painel.hidden = true;

  document.body.append(fundo, painel);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !painel.hidden) fecharPainel();
  });
}

function abrirPainel() {
  const painel = document.getElementById('painel-notificacoes');
  const porLer = naoLidas().length;

  // A notificação em destaque é o levantamento mais recente que ainda está por fazer.
  const destaque = notificacoes.find((n) => n.tipo === 'levantamento');
  const resto = notificacoes.filter((n) => n !== destaque);

  painel.innerHTML = `
    <div class="notificacoes__cabecalho">
      <div>
        <h2 id="notificacoes-titulo">Notificações</h2>
        <span class="notificacoes__contagem">${porLer ? `${porLer} ${porLer === 1 ? 'não lida' : 'não lidas'}` : 'Tudo lido'}</span>
      </div>
      <button type="button" class="notificacoes__fechar" id="notificacoes-fechar" aria-label="Fechar notificações">${ICONE_FECHAR}</button>
    </div>
    ${notificacoes.length === 0 ? '<p class="notificacoes__vazio">Ainda não tens notificações. Avisamos-te aqui quando a receção validar um pedido teu.</p>' : ''}
    ${destaque ? destaqueHtml(destaque) : ''}
    ${resto.length ? `<ul class="notificacoes__lista">${resto.map(itemHtml).join('')}</ul>` : ''}`;

  document.getElementById('notificacoes-fechar').addEventListener('click', fecharPainel);
  document.getElementById('notificacoes-fundo').hidden = false;
  painel.hidden = false;
  document.body.classList.add('sem-scroll');
  document.getElementById('notificacoes-fechar').focus();

  if (destaque) carregarObjeto(destaque);

  // Abrir o painel conta como ler: o número desaparece, mas o destaque mantém-se até fechar.
  const lidasAgora = naoLidas();
  if (lidasAgora.length) {
    notificacoesService.marcarLidas(lidasAgora);
    notificacoes = notificacoes.map((n) => ({ ...n, lida: true }));
    atualizarNumero();
  }
}

function fecharPainel() {
  document.getElementById('painel-notificacoes').hidden = true;
  document.getElementById('notificacoes-fundo').hidden = true;
  document.body.classList.remove('sem-scroll');
  document.getElementById('navbar-sino')?.focus();
}

function quando(n) {
  const data = paraData(n.data);
  if (!data || Number.isNaN(data.getTime())) return '';
  return Date.now() - data.getTime() < 5 * 60 * 1000 ? 'Agora' : formatarData(data);
}

const linkLevantamento = (n) => `${url('aluno/levantamento.html')}?reclamacao=${encodeURIComponent(n.reclamacaoId)}`;

function destaqueHtml(n) {
  return `
    <article class="notificacoes__destaque${n.lida ? '' : ' notificacoes__destaque--nova'}">
      <div class="notificacoes__item notificacoes__item--grande">
        <span class="notificacoes__icone notificacoes__icone--levantamento" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">${ICONES.levantamento}</svg></span>
        <div>
          <small class="notificacoes__quando">${esc(quando(n))}${n.lida ? '' : ' · nova'}</small>
          <h3>${esc(n.assunto)}</h3>
          <p>${esc(n.texto)}</p>
        </div>
      </div>
      <div class="resumo-objeto notificacoes__objeto">
        <div class="resumo-objeto__foto" id="notificacoes-foto">${fotoVazia()}</div>
        <div class="resumo-objeto__texto">
          <b>${esc(n.titulo)}</b>
          <span class="ajuda" id="notificacoes-referencia"></span>
        </div>
      </div>
      <dl class="notificacoes__detalhes">
        <div><dt>Local</dt><dd>${esc(n.pontoRecolha || RECECAO.local)}</dd></div>
        <div><dt>Horário</dt><dd>${esc(RECECAO.horario)}</dd></div>
      </dl>
      <p class="aviso notificacoes__lembrete">Apresenta o teu cartão de estudante no momento do levantamento.</p>
      <a href="${linkLevantamento(n)}" class="botao botao--preto notificacoes__botao">Ver instruções de levantamento →</a>
    </article>`;
}

function itemHtml(n) {
  let link = '';
  if (n.tipo === 'levantamento') link = `<a href="${linkLevantamento(n)}">Ver instruções de levantamento</a>`;
  if (n.tipo === 'reaberta') link = `<a href="${url('aluno/reclamar.html')}?id=${encodeURIComponent(n.itemId)}">Responder outra vez</a>`;

  return `
    <li class="notificacoes__item${n.lida ? '' : ' notificacoes__item--nova'}">
      <span class="notificacoes__icone notificacoes__icone--${n.tipo}" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6">${ICONES[n.tipo] ?? ''}</svg></span>
      <div>
        <small class="notificacoes__quando">${esc(quando(n))}${n.lida ? '' : ' · nova'}</small>
        <h3>${esc(n.assunto)}</h3>
        <p>${esc(n.texto)}</p>
        ${link}
      </div>
    </li>`;
}

async function carregarObjeto(n) {
  try {
    const item = await itemsService.getById(n.itemId);
    if (!item) return;
    const foto = document.getElementById('notificacoes-foto');
    const ref = document.getElementById('notificacoes-referencia');
    if (foto && item.fotos[0]) foto.innerHTML = `<img src="${esc(item.fotos[0])}" alt="">`;
    if (ref && item.referencia) ref.textContent = `Ref. ${item.referencia}`;
  } catch {
  }
}
