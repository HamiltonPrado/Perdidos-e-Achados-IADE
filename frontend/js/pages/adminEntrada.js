import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService } from '../services/adminService.js';
import { esc, fotoVazia, formatarData, lerParametro } from '../utilities/tools.js';
import { PRATELEIRAS } from '../utilities/stock.js';

const $ = (id) => document.getElementById(id);

if (requerAdmin()) {
  montarAdminHeader('objetos');
  montarFooter();
  await iniciar();
}

function resumoHtml(item, linha) {
  return `
    <div class="objeto-resumo__foto">${item.fotos[0] ? `<img src="${esc(item.fotos[0])}" alt="">` : fotoVazia()}</div>
    <div>
      <span class="rotulo-pequeno">${linha}</span>
      <h2>${esc(item.titulo)}</h2>
      <span>${esc(item.categoria)} · ${esc(item.referencia)}</span>
    </div>`;
}

async function iniciar() {
  const id = lerParametro('id');
  if (!id) {
    mostrarPesquisa();
    return;
  }

  const item = await adminService.getObjeto(id);
  if (!item) {
    $('nao-encontrado').hidden = false;
    return;
  }
  if (item.estado !== 'por_receber') {
    $('nao-encontrado-texto').textContent = 'Este objeto já deu entrada na receção.';
    $('nao-encontrado').hidden = false;
    return;
  }

  $('resumo').innerHTML = resumoHtml(item, 'Objeto registado pelo aluno');
  $('entregue-por').textContent = item.publicadoPor?.nome ?? '—';
  $('encontrado-em').innerHTML = `${esc(item.local)}<span>${esc(formatarData(item.dataEncontrado))}</span>`;
  $('descricao').textContent = item.descricao || 'Sem descrição.';
  // O aluno traz a referência provisória ENT-…; ao confirmar passa a ACH-… com o mesmo número.
  $('referencia').textContent = `${item.referencia} → ${item.referencia.replace(/^ENT-/, 'ACH-')} ao confirmar`;

  const pontos = await adminService.listarPontosRecolha();
  $('ponto').innerHTML = pontos.map((p) => `<option value="${p.id}">${esc(p.nome)}</option>`).join('');
  await preencherPrateleiras();
  $('ecra-formulario').hidden = false;

  $('form-entrada').addEventListener('submit', async (e) => {
    e.preventDefault();
    const valores = {
      pergunta: $('pergunta').value.trim(),
      resposta: $('resposta').value.trim(),
      stock: $('stock').value,
    };
    Object.entries(valores).forEach(([campo, valor]) => { $(`erro-${campo}`).hidden = !!valor; });
    const emFalta = Object.keys(valores).find((campo) => !valores[campo]);
    if (emFalta) {
      $(emFalta).focus();
      return;
    }

    $('botao-confirmar').disabled = true;
    $('erro-geral').hidden = true;
    try {
      const recebido = await adminService.confirmarEntrada(item.id, {
        pontoRecolhaId: $('ponto').value,
        localizacaoStock: valores.stock,
        observacoes: $('observacoes').value.trim(),
        pergunta: valores.pergunta,
        respostaEsperada: valores.resposta,
      });
      $('resumo-final').innerHTML = resumoHtml(recebido, `Na receção · ${esc(recebido.localizacaoStock)}`);
      $('ecra-formulario').hidden = true;
      $('ecra-sucesso').hidden = false;
      window.scrollTo(0, 0);
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-confirmar').disabled = false;
    }
  });
}

function mostrarPesquisa() {
  $('ecra-referencia').hidden = false;
  $('referencia-pesquisa').focus();

  $('form-referencia').addEventListener('submit', async (e) => {
    e.preventDefault();
    const referencia = $('referencia-pesquisa').value.trim();
    $('erro-referencia').hidden = true;
    if (!referencia) {
      $('erro-referencia').textContent = 'Escreve a referência do objeto.';
      $('erro-referencia').hidden = false;
      return;
    }
    const item = await adminService.procurarPorReferencia(referencia);
    if (!item) {
      $('erro-referencia').textContent = `Não há nenhum objeto com a referência ${referencia}.`;
    } else if (item.estado !== 'por_receber') {
      $('erro-referencia').textContent = `O objeto ${item.referencia} (${item.titulo}) já deu entrada na receção.`;
    } else {
      window.location.href = `entrada.html?id=${item.id}`;
      return;
    }
    $('erro-referencia').hidden = false;
  });
}

async function preencherPrateleiras() {
  const livres = await adminService.prateleirasLivres();
  $('stock').innerHTML = livres.length
    ? `<option value="">Escolher prateleira…</option>${livres.map((p) => `<option>${p}</option>`).join('')}`
    : '<option value="">Não há prateleiras livres</option>';
  $('stock').disabled = livres.length === 0;
  $('ajuda-stock').textContent = `${livres.length} de ${PRATELEIRAS.length} prateleiras livres.`;
}
