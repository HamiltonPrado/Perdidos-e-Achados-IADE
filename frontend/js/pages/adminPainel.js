import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService } from '../services/adminService.js';
import { esc, fotoVazia, formatarData, iniciais } from '../utilities/tools.js';
import { etiquetaPedido } from '../utilities/adminTools.js';

const $ = (id) => document.getElementById(id);

if (requerAdmin()) {
  montarAdminHeader('painel');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  ligarReceber();
  ligarLevantamento();

  const [objetos, pedidos, devolucoes] = await Promise.all([
    adminService.listarObjetos(),
    adminService.listarPedidos(),
    adminService.listarDevolucoes(),
  ]);

  const pendentes = pedidos.filter((p) => p.estado === 'pendente');
  const porReceber = objetos.filter((o) => o.estado === 'por_receber');
  const naRececao = objetos.filter((o) => o.estado === 'por_reclamar' || o.estado === 'reclamado');

  const cartoes = [
    [naRececao.length, 'Objetos na receção', 'objetos.html?separador=rececao', false],
    [pendentes.length, 'Pedidos por validar', 'objetos.html?separador=pedidos', pendentes.length > 0],
    [porReceber.length, 'Por receber', 'objetos.html?separador=receber', porReceber.length > 0],
    [devolucoes.length, 'Devolvidos', 'objetos.html?separador=devolvidos', false],
  ];
  $('estatisticas').innerHTML = cartoes.map(([n, texto, href, destaque]) => `
    <a href="${href}" class="estatistica${destaque ? ' estatistica--destaque' : ''}">
      <strong>${n}</strong><span>${texto}</span>
    </a>`).join('');

  $('lista-pedidos').innerHTML = pendentes.length
    ? pendentes.slice(0, 4).map((p) => `
      <a href="pedido.html?id=${p.id}" class="linha-lista">
        <span class="avatar">${esc(iniciais(p.aluno?.nome))}</span>
        <span class="linha-lista__texto"><b>${esc(p.aluno?.nome)}</b><span>${esc(p.objeto)}</span></span>
        ${etiquetaPedido(p)}
      </a>`).join('')
    : '<p class="ajuda">Não há pedidos por validar.</p>';

  $('lista-receber').innerHTML = porReceber.length
    ? porReceber.slice(0, 4).map((o) => `
      <a href="entrada.html?id=${o.id}" class="linha-lista">
        <span class="linha-lista__foto">${o.fotos[0] ? `<img src="${esc(o.fotos[0])}" alt="">` : fotoVazia()}</span>
        <span class="linha-lista__texto"><b>${esc(o.titulo)}</b><span>${esc(o.publicadoPor?.nome ?? '')} · ${esc(formatarData(o.dataEncontrado))}</span></span>
        <span class="tabela__acao"><b>Confirmar →</b></span>
      </a>`).join('')
    : '<p class="ajuda">Nenhum objeto à espera de entrada.</p>';

}

function ligarReceber() {
  $('form-receber').addEventListener('submit', async (e) => {
    e.preventDefault();
    const referencia = $('ref').value.trim();
    $('erro-receber').hidden = true;
    const item = referencia ? await adminService.procurarPorReferencia(referencia) : null;
    if (item && item.estado === 'por_receber') {
      window.location.href = `entrada.html?id=${item.id}`;
      return;
    }
    $('erro-receber').textContent = !referencia
      ? 'Escreve a referência do objeto.'
      : !item
        ? `Não há nenhum objeto com a referência ${referencia}.`
        : `O objeto ${item.referencia} (${item.titulo}) já deu entrada na receção.`;
    $('erro-receber').hidden = false;
  });
}

function ligarLevantamento() {
  let codigoAtual = '';

  $('form-codigo').addEventListener('submit', async (e) => {
    e.preventDefault();
    $('erro-codigo').hidden = true;
    $('resultado-codigo').hidden = true;
    const codigo = $('codigo').value.trim();
    if (!codigo) {
      $('erro-codigo').textContent = 'Escreve o código de levantamento.';
      $('erro-codigo').hidden = false;
      return;
    }
    try {
      const pedido = await adminService.consultarCodigo(codigo);
      codigoAtual = pedido.codigo;
      $('resultado-codigo').innerHTML = `
        <div>
          <b>${esc(pedido.objeto)}</b>
          ${pedido.prateleira ? `<p class="levantamento__prateleira">Está em: <b>${esc(pedido.prateleira)}</b></p>` : ''}
          <p>${esc(pedido.aluno?.nome)} · ${esc(pedido.aluno?.email ?? '')}</p>
          <p>Confirma a identidade do aluno com o cartão de estudante.</p>
        </div>
        <button type="button" class="botao botao--destaque" id="botao-entregar">Confirmar entrega →</button>`;
      $('resultado-codigo').hidden = false;
      $('botao-entregar').addEventListener('click', entregar);
    } catch (erro) {
      $('erro-codigo').textContent = erro.message;
      $('erro-codigo').hidden = false;
    }
  });

  async function entregar() {
    $('botao-entregar').disabled = true;
    try {
      await adminService.confirmarLevantamento(codigoAtual);
      $('resultado-codigo').innerHTML = '<div><b>Entrega registada.</b><p>O objeto passou para Devolvidos.</p></div>';
      $('codigo').value = '';
    } catch (erro) {
      $('erro-codigo').textContent = erro.message;
      $('erro-codigo').hidden = false;
      $('botao-entregar').disabled = false;
    }
  }
}
