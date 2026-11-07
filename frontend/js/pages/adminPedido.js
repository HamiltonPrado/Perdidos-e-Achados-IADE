import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService, respostaCoincide } from '../services/adminService.js';
import { esc, fotoVazia, formatarDataLonga, iniciais, lerParametro } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

if (requerAdmin()) {
  montarAdminHeader('pedidos');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const pedido = await adminService.getPedido(lerParametro('id'));
  if (!pedido) {
    $('nao-encontrado').hidden = false;
    return;
  }
  mostrar(pedido);
  $('conteudo').hidden = false;

  $('botao-aprovar').addEventListener('click', () => decidir(pedido.id, 'aprovada'));
  $('botao-recusar').addEventListener('click', () => {
    $('decisao-acoes').hidden = true;
    $('form-recusa').hidden = false;
    $('motivo').focus();
  });
  $('cancelar-recusa').addEventListener('click', () => {
    $('form-recusa').hidden = true;
    $('decisao-acoes').hidden = false;
  });
  $('form-recusa').addEventListener('submit', (e) => {
    e.preventDefault();
    const motivo = $('motivo').value.trim();
    $('erro-motivo').hidden = !!motivo;
    if (motivo) decidir(pedido.id, 'recusada', motivo);
  });
}

function mostrar(p) {
  const nome = p.aluno?.nome ?? 'Aluno';
  $('avatar').textContent = iniciais(nome);
  $('avatar-mensagem').textContent = iniciais(nome);
  $('aluno-nome').textContent = nome;
  $('aluno-nome-mensagem').textContent = nome;
  $('aluno-email').textContent = p.aluno?.email ?? '';
  $('referencia').textContent = p.referencia ?? '';
  $('data-pedido').textContent = `Pedido recebido · ${formatarDataLonga(p.criadoEm)}`;

  $('resumo').innerHTML = `
    <div class="objeto-resumo__foto">${p.foto ? `<img src="${esc(p.foto)}" alt="">` : fotoVazia()}</div>
    <div>
      <span class="rotulo-pequeno">Objeto na receção</span>
      <h2>${esc(p.objeto)}</h2>
      <span>${esc(p.local ?? p.pontoRecolha ?? '')}</span>
    </div>`;

  $('mensagem-aluno').hidden = !p.informacao;
  $('informacao').textContent = p.informacao;

  const coincide = respostaCoincide(p.resposta, p.respostaEsperada);
  $('validacao').classList.add(coincide ? 'validacao--coincide' : 'validacao--difere');
  $('pergunta').textContent = p.pergunta || 'Pergunta secreta';
  $('resposta').textContent = `Resposta: ${p.resposta}`;
  $('esperada').textContent = `Resposta esperada: ${p.respostaEsperada || '—'} · ${coincide ? 'Coincide' : 'Não coincide'}`;

  $('bloco-fotos').hidden = p.fotos.length === 0;
  $('fotos').innerHTML = p.fotos.map((f, n) => `<img src="${esc(f)}" alt="Foto de prova ${n + 1}">`).join('');

  mostrarEstado(p);
}

function mostrarEstado(p) {
  if (p.estado === 'pendente') return;
  $('decisao').hidden = true;
  const textos = {
    aprovada: ['Pedido aprovado.', p.codigo
      ? `O aluno recebeu o código de levantamento <b>${esc(p.codigo)}</b>. Quando vier à receção, regista o levantamento no painel.`
      : 'O aluno vai receber um código para levantar o objeto na receção.'],
    recusada: ['Pedido recusado.', `Motivo: ${esc(p.motivo || '—')}`],
    concluida: ['Objeto levantado.', 'Este pedido já foi concluído com a entrega do objeto.'],
  };
  const [titulo, texto] = textos[p.estado] ?? [`Estado: ${esc(p.estado)}`, ''];
  $('resultado').innerHTML = `<div><b>${titulo}</b><p>${texto}</p></div>
    <a href="objetos.html?separador=pedidos" class="botao botao--contorno">Voltar aos pedidos</a>`;
  $('resultado').hidden = false;
}

async function decidir(id, estado, motivo = '') {
  $('erro-geral').hidden = true;
  document.querySelectorAll('#decisao button').forEach((b) => { b.disabled = true; });
  try {
    const atualizado = await adminService.decidirPedido(id, estado, motivo);
    mostrarEstado(atualizado);
  } catch (erro) {
    $('erro-geral').textContent = erro.message;
    $('erro-geral').hidden = false;
    document.querySelectorAll('#decisao button').forEach((b) => { b.disabled = false; });
  }
}
