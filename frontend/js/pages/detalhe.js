import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { getUser, isLoggedIn } from '../utilities/auth.js';
import { esc, formatarData, fotoVazia, lerParametro } from '../utilities/tools.js';

montarNavBar('inicio');
montarFooter();

const $ = (id) => document.getElementById(id);

const id = lerParametro('id');
const item = id ? await itemsService.getById(id) : null;

if (!item) {
  $('nao-encontrado').hidden = false;
} else {
  mostrarItem(item);
  await mostrarAcoes(item);
  $('conteudo').hidden = false;
}

function mostrarItem(item) {
  document.title = `${item.titulo} · Perdidos & Achados`;

  const estados = {
    por_receber: 'A aguardar entrega na receção',
    por_reclamar: item.ondeEsta === 'secretaria' ? 'Disponível na receção' : 'Com quem encontrou',
    reclamado: 'Pedido em validação',
    devolvido: 'Já devolvido',
  };
  $('estado').textContent = estados[item.estado] ?? '';

  $('titulo').textContent = item.titulo;
  $('local').textContent = item.local;
  $('data').textContent = formatarData(item.dataEncontrado);
  $('descricao').textContent = item.descricao || 'Sem descrição.';

  montarFotos(item);
}

function montarFotos(item) {
  const fotos = item.fotos;
  const etiqueta = `<span class="detalhe__categoria">${esc(item.categoria)}</span>`;

  function mostrar(n) {
    $('foto-principal').innerHTML = (fotos[n] ? `<img src="${esc(fotos[n])}" alt="Foto do objeto: ${esc(item.titulo)}">` : fotoVazia()) + etiqueta;
    $('miniaturas').querySelectorAll('button').forEach((b, i) => b.setAttribute('aria-pressed', String(i === n)));
  }

  if (fotos.length > 1) {
    $('miniaturas').innerHTML = fotos.map((src, n) => `
      <button type="button" class="detalhe__miniatura" data-foto="${n}" aria-label="Ver foto ${n + 1}">
        <img src="${esc(src)}" alt="">
      </button>`).join('');
    $('miniaturas').addEventListener('click', (e) => {
      const botao = e.target.closest('[data-foto]');
      if (botao) mostrar(Number(botao.dataset.foto));
    });
  }

  mostrar(0);
}

async function mostrarAcoes(item) {
  const eu = getUser();

  if (eu && item.publicadoPor?.id === eu.id) {
    $('aviso-meu').hidden = false;
    return;
  }
  if (item.estado !== 'por_reclamar') {
    $('aviso-fechado').hidden = false;
    return;
  }
  if (isLoggedIn()) {
    const minhas = await reclamacoesService.getMinhas();
    if (minhas.some((r) => r.itemId === item.id)) {
      $('ja-reclamei').hidden = false;
      return;
    }
  }

  $('botao-reclamar').href = `reclamar.html?id=${item.id}`;
  $('bloco-reclamar').hidden = false;
}
