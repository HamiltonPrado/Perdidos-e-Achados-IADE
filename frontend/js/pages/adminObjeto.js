import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService } from '../services/adminService.js';
import { esc, fotoVazia, formatarDataLonga, iniciais, lerParametro } from '../utilities/tools.js';
import { etiquetaObjeto, etiquetaPedido } from '../utilities/adminTools.js';

const $ = (id) => document.getElementById(id);

if (requerAdmin()) {
  montarAdminHeader('objetos');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const item = await adminService.getObjeto(lerParametro('id'));
  if (!item) {
    $('nao-encontrado').hidden = false;
    return;
  }

  const [pedidos, devolucoes] = await Promise.all([adminService.listarPedidos(), adminService.listarDevolucoes()]);
  const doObjeto = pedidos.filter((p) => p.itemId === item.id);
  const devolucao = devolucoes.find((d) => d.itemId === item.id);
  const recebido = item.estado !== 'por_receber';

  document.title = `${item.titulo} · Receção · Perdidos & Achados`;
  $('referencia').textContent = `Objeto · ${item.referencia}`;
  $('titulo').textContent = item.titulo;
  $('estado').innerHTML = etiquetaObjeto(item.estado);
  if (!recebido) {
    $('botao-entrada').href = `entrada.html?id=${item.id}`;
    $('botao-entrada').hidden = false;
  }

  $('foto').innerHTML = item.fotos[0] ? `<img src="${esc(item.fotos[0])}" alt="Foto do objeto">` : fotoVazia();
  $('descricao').textContent = item.descricao || 'Sem descrição.';
  $('categoria').textContent = item.categoria || '—';
  $('encontrado').innerHTML = `${esc(item.local)}<span>${esc(formatarDataLonga(item.dataEncontrado))}</span>`;
  $('registado-por').textContent = item.publicadoPor?.nome ?? '—';

  const guardado = recebido && item.estado !== 'devolvido';
  $('prateleira').textContent = guardado ? item.localizacaoStock || '—' : item.estado === 'devolvido' ? 'Libertada (devolvido)' : 'Ainda não entregue';
  $('ponto').textContent = recebido ? item.pontoRecolha || '—' : '—';
  $('recebido').textContent = recebido && item.recebidoEm ? formatarDataLonga(item.recebidoEm) : '—';
  $('pergunta').textContent = item.pergunta || 'Ainda sem pergunta: é definida ao confirmar a entrada.';
  $('resposta').innerHTML = item.respostaEsperada ? `Resposta esperada: <b>${esc(item.respostaEsperada)}</b>` : '';
  $('observacoes').textContent = item.observacoes || '—';

  $('pedidos').innerHTML = doObjeto.length
    ? doObjeto.map((p) => `
      <a href="pedido.html?id=${p.id}" class="linha-lista">
        <span class="avatar">${esc(iniciais(p.aluno?.nome))}</span>
        <span class="linha-lista__texto"><b>${esc(p.aluno?.nome)}</b><span>${esc(formatarDataLonga(p.criadoEm))}</span></span>
        ${etiquetaPedido(p)}
      </a>`).join('')
    : '<p class="ajuda">Ainda ninguém pediu este objeto.</p>';

  if (devolucao) {
    $('devolucao').innerHTML = `
      <div><dt class="rotulo-pequeno">Entregue a</dt><dd>${esc(devolucao.entregueA?.nome)}<span>${esc(devolucao.entregueA?.email ?? '')}</span></dd></div>
      <div><dt class="rotulo-pequeno">Data</dt><dd>${esc(formatarDataLonga(devolucao.devolvidoEm))}<span>Confirmado por ${esc(devolucao.confirmadoPor)}</span></dd></div>`;
    $('bloco-devolucao').hidden = false;
  }

  $('conteudo').hidden = false;
}
