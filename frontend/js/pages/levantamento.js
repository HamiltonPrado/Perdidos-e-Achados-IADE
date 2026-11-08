import { requerLogin, getUser } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { itemsService } from '../services/itemsService.js';
import { paraData } from '../services/notificacoesService.js';
import { esc, fotoVazia, lerParametro, formatarDataLonga } from '../utilities/tools.js';
import { RECECAO } from '../utilities/rececao.js';

const $ = (id) => document.getElementById(id);

if (requerLogin()) {
  montarNavBar('');
  montarFooter();
  await iniciar();
}

function indisponivel(titulo, texto = '') {
  $('a-carregar').hidden = true;
  $('indisponivel-titulo').innerHTML = `<b>${esc(titulo)}</b>`;
  $('indisponivel-texto').textContent = texto;
  $('indisponivel').hidden = false;
}

async function iniciar() {
  const id = Number(lerParametro('reclamacao'));
  let reclamacao = null;
  try {
    reclamacao = (await reclamacoesService.getMinhas()).find((r) => r.id === id) ?? null;
  } catch {
    indisponivel('Não foi possível carregar o pedido.', 'Tenta outra vez daqui a pouco.');
    return;
  }

  if (!reclamacao) {
    indisponivel('Não encontrámos este pedido.', 'Confirma se entraste com a conta que fez a reclamação.');
    return;
  }

  if (reclamacao.estado === 'aprovada' && reclamacao.estadoArtigo === 'devolvido') {
    indisponivel('Já levantaste este objeto.', `«${reclamacao.titulo}» foi entregue na receção.`);
    return;
  }

  if (reclamacao.estado !== 'aprovada') {
    const textos = {
      pendente: ['O teu pedido ainda está em análise.', 'Avisamos-te nas notificações quando a receção o validar.'],
      reaberta: ['A receção reabriu o teu pedido.', 'Envia uma nova resposta para a receção voltar a analisar.'],
      recusada: ['A receção não validou o teu pedido.', reclamacao.motivo],
      cancelada: ['Este pedido foi cancelado.', ''],
    };
    const [titulo, texto] = textos[reclamacao.estado] ?? textos.pendente;
    indisponivel(titulo, texto);
    return;
  }

  const user = getUser();
  const local = reclamacao.pontoRecolha || RECECAO.local;

  $('texto').innerHTML = `A receção confirmou a tua resposta.<br>Já podes levantar «${esc(reclamacao.titulo)}» em ${esc(local)}.`;
  $('local').textContent = local;
  $('horario').textContent = RECECAO.horario;
  $('pessoa-nome').textContent = user?.nome ?? '';
  $('pessoa-email').textContent = user?.email ?? '';

  if (reclamacao.motivo) {
    $('nota-texto').textContent = reclamacao.motivo;
    $('nota').hidden = false;
  }

  $('resumo').innerHTML = resumoHtml({ titulo: reclamacao.titulo });
  $('a-carregar').hidden = true;
  $('conteudo').hidden = false;

  // Foto e categoria do objeto (não vêm na reclamação).
  itemsService.getById(reclamacao.itemId).then((item) => {
    if (item) $('resumo').innerHTML = resumoHtml(item);
  }).catch(() => {});

  await mostrarCodigo(reclamacao.id);
}

function resumoHtml(item) {
  const detalhe = [item.categoria, item.local && `Encontrado em ${item.local}`].filter(Boolean).join(' · ');
  return `
    <div class="resumo-objeto__foto">${item.fotos?.[0] ? `<img src="${esc(item.fotos[0])}" alt="">` : fotoVazia()}</div>
    <div class="resumo-objeto__texto">
      <small>OBJETO</small>
      <b>${esc(item.titulo)}</b>
      ${detalhe ? `<span>${esc(detalhe)}</span>` : ''}
      ${item.referencia ? `<span>Ref. ${esc(item.referencia)}</span>` : ''}
    </div>`;
}

// POST /api/claims/:id/collection-request devolve o código (o mesmo enquanto for válido).
async function mostrarCodigo(reclamacaoId) {
  try {
    const pedido = await reclamacoesService.pedirCodigo(reclamacaoId);
    $('codigo').textContent = pedido.codigo.replace(/^(.{4})(.{4})$/, '$1 $2');
    $('codigo').setAttribute('aria-label', pedido.codigo.split('').join(' '));

    const expira = paraData(pedido.expiraEm);
    if (expira && !Number.isNaN(expira.getTime())) {
      $('validade').textContent = formatarDataLonga(expira);
      const horas = Math.max(0, Math.round((expira.getTime() - Date.now()) / 3600000));
      $('validade-restante').textContent = horas >= 1
        ? `Faltam cerca de ${horas} ${horas === 1 ? 'hora' : 'horas'}`
        : 'Expira em menos de uma hora';
    }
    $('codigo-ajuda').textContent = 'Mostra este código na receção. Se expirar, volta a abrir esta página para receberes outro.';
  } catch (erro) {
    $('codigo-ajuda').textContent = 'Código indisponível';
    $('erro-codigo').textContent = erro.message;
    $('erro-codigo').hidden = false;
  }
}
