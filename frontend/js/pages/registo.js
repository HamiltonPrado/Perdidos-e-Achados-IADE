import { requerLogin, getUser } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { esc, fotoVazia, lerParametro, formatarData } from '../utilities/tools.js';
import { RECECAO } from '../utilities/rececao.js';

const $ = (id) => document.getElementById(id);

if (requerLogin()) {
  montarNavBar('meus-anuncios');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const id = lerParametro('id');
  const item = id ? await itemsService.getById(id) : null;

  if (!item || item.publicadoPor?.id !== getUser()?.id) {
    $('nao-encontrado').hidden = false;
    return;
  }

  // Por entregar: referência provisória ENT-…; depois da entrada na receção passa a ACH-….
  const referencia = item.referencia;

  const textos = {
    por_receber: {
      sobretitulo: lerParametro('novo') ? 'Objeto registado' : 'Por entregar',
      titulo: 'Agora entrega-o na receção.',
      
    },
    por_reclamar: {
      sobretitulo: 'Na receção',
      titulo: 'Já está na receção.',
      texto: 'Obrigado! O objeto já foi entregue e está visível para quem o perdeu.',
    },
    reclamado: {
      sobretitulo: 'Na receção',
      titulo: 'Já está na receção.',
      texto: 'Alguém pediu este objeto. A receção está a validar o pedido.',
    },
    devolvido: {
      sobretitulo: 'Devolvido',
      titulo: 'Já foi devolvido ao dono.',
      texto: 'Obrigado por ajudares a comunidade da Universidade Europeia.',
    },
  };
  const t = textos[item.estado] ?? textos.por_receber;

  $('sobretitulo').textContent = t.sobretitulo;
  $('titulo').textContent = t.titulo;
  $('texto').textContent = t.texto ?? '';
  $('texto').hidden = !t.texto;
  $('referencia').textContent = referencia;

  $('resumo').innerHTML = `
    <div class="resumo-objeto__foto">${item.fotos[0] ? `<img src="${esc(item.fotos[0])}" alt="">` : fotoVazia()}</div>
    <div class="resumo-objeto__texto">
      <small>OBJETO</small>
      <b>${esc(item.titulo)}</b>
      <span>${esc(item.local)} · ${esc(formatarData(item.dataEncontrado))}</span>
    </div>`;

  $('info-responsavel').textContent = RECECAO.responsavel;
  $('info-local').textContent = RECECAO.local;
  $('info-horario').textContent = RECECAO.horario;

  // As instruções de entrega só interessam enquanto o objeto ainda não chegou à receção.
  if (item.estado !== 'por_receber') {
    document.querySelector('.registo__info').hidden = true;
    $('referencia-ajuda').hidden = true;
  }

  $('conteudo').hidden = false;
}
