import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService } from '../services/adminService.js';
import { esc, fotoVazia, formatarData, lerParametro } from '../utilities/tools.js';
import { etiquetaObjeto, etiquetaPedido } from '../utilities/adminTools.js';

const $ = (id) => document.getElementById(id);

const TITULOS = {
  todos: 'Todos os objetos',
  rececao: 'Objetos na receção',
  receber: 'Objetos por receber',
  pedidos: 'Pedidos de confirmação',
  devolvidos: 'Objetos devolvidos',
};

const foto = (src) => `<span class="linha-lista__foto">${src ? `<img src="${esc(src)}" alt="">` : fotoVazia()}</span>`;

if (requerAdmin()) {
  montarAdminHeader(lerParametro('separador') === 'pedidos' ? 'pedidos' : 'objetos');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const [objetos, pedidos, devolucoes] = await Promise.all([
    adminService.listarObjetos(),
    adminService.listarPedidos(),
    adminService.listarDevolucoes(),
  ]);

  const grupos = {
    todos: objetos.filter((o) => o.estado !== 'arquivado'),
    rececao: objetos.filter((o) => o.estado === 'por_reclamar' || o.estado === 'reclamado'),
    receber: objetos.filter((o) => o.estado === 'por_receber'),
    pedidos,
    devolvidos: devolucoes,
  };
  document.querySelectorAll('[data-contagem]').forEach((el) => {
    const g = el.dataset.contagem;
    el.textContent = g === 'pedidos' ? pedidos.filter((p) => p.estado === 'pendente').length : grupos[g].length;
  });

  const publicado = lerParametro('publicado');
  if (publicado) {
    $('mensagem').textContent = `Objeto publicado com a referência ${publicado}. Já está visível no site.`;
    $('mensagem').hidden = false;
  }

  let atual = lerParametro('separador');
  if (!(atual in TITULOS)) atual = 'todos';

  function mostrar() {
    const termo = $('pesquisa').value.trim().toLowerCase();
    document.querySelectorAll('[data-separador]').forEach((b) => {
      b.classList.toggle('ativo', b.dataset.separador === atual);
      b.setAttribute('aria-selected', String(b.dataset.separador === atual));
    });
    $('titulo').textContent = TITULOS[atual];

    const textoDe = {
      pedidos: (p) => `${p.objeto} ${p.aluno?.nome} ${p.referencia ?? ''}`,
      devolvidos: (d) => `${d.item.titulo} ${d.entregueA?.nome} ${d.item.referencia}`,
    }[atual] ?? ((o) => `${o.titulo} ${o.referencia} ${o.publicadoPor?.nome ?? ''}`);
    const lista = grupos[atual].filter((x) => !termo || textoDe(x).toLowerCase().includes(termo));

    $('tabela').innerHTML = lista.length ? tabela(atual, lista) : '<div class="vazio"><p>Nada para mostrar aqui.</p></div>';
    $('contagem').textContent = `A mostrar ${lista.length} ${lista.length === 1 ? 'resultado' : 'resultados'}`;
  }

  document.querySelector('.separadores').addEventListener('click', (e) => {
    const botao = e.target.closest('[data-separador]');
    if (!botao) return;
    atual = botao.dataset.separador;
    history.replaceState(null, '', `objetos.html?separador=${atual}`);
    mostrar();
  });
  $('pesquisa').addEventListener('input', mostrar);
  mostrar();
}

function tabela(separador, lista) {
  if (separador === 'pedidos') {
    return `<table class="tabela">
      <thead><tr><th>Objeto · Aluno</th><th>Estado</th><th>Recebido</th><th></th></tr></thead>
      <tbody>${lista.map((p) => `
        <tr>
          <td><div class="tabela__objeto">${foto(p.foto)}<span><b>${esc(p.objeto)}</b><small>${esc(p.aluno?.nome)}${p.referencia ? ` · ${esc(p.referencia)}` : ''}</small></span></div></td>
          <td>${etiquetaPedido(p)}</td>
          <td>${esc(formatarData(p.criadoEm))}</td>
          <td class="tabela__acao"><a href="pedido.html?id=${p.id}">Ver pedido →</a></td>
        </tr>`).join('')}</tbody></table>`;
  }

  if (separador === 'devolvidos') {
    return `<table class="tabela">
      <thead><tr><th>Objeto</th><th>Entregue a</th><th>Confirmado por</th><th>Data</th></tr></thead>
      <tbody>${lista.map((d) => `
        <tr>
          <td><div class="tabela__objeto">${foto(d.item.fotos[0])}<span><b><a href="objeto.html?id=${d.itemId}">${esc(d.item.titulo)}</a></b><small>${esc(d.item.referencia)}</small></span></div></td>
          <td>${esc(d.entregueA?.nome)}</td>
          <td>${esc(d.confirmadoPor)}</td>
          <td>${esc(formatarData(d.devolvidoEm))}</td>
        </tr>`).join('')}</tbody></table>`;
  }

  return `<table class="tabela">
    <thead><tr><th>Objeto</th><th>Estado</th><th>Localização</th><th>Registado</th><th></th></tr></thead>
    <tbody>${lista.map((o) => `
      <tr>
        <td><div class="tabela__objeto">${foto(o.fotos[0])}<span><b>${esc(o.titulo)}</b><small>${esc(o.referencia)}</small></span></div></td>
        <td>${etiquetaObjeto(o.estado)}</td>
        <td>${o.estado === 'por_receber' ? '—' : esc(o.localizacaoStock || o.pontoRecolha || '—')}</td>
        <td>${esc(formatarData(o.recebidoEm ?? o.dataEncontrado))}</td>
        <td class="tabela__acao">${acaoObjeto(o)}</td>
      </tr>`).join('')}</tbody></table>`;
}

function acaoObjeto(o) {
  if (o.estado === 'por_receber') return `<a href="entrada.html?id=${o.id}">Confirmar entrada →</a>`;
  return `<a href="objeto.html?id=${o.id}">Ver detalhes →</a>`;
}
