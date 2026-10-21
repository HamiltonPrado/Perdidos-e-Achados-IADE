/*
 * pages/detalhe.js — LÓGICA DA PÁGINA DE DETALHE (detalhe.html)
 * ------------------------------------------------------------
 * Como funciona:
 *   1. Lê o id do endereço (detalhe.html?id=3).
 *   2. Pede o objeto ao itemsService e preenche a página.
 *   3. Decide o que mostrar no lado direito:
 *        - anúncio meu         → aviso "Este anúncio é teu"
 *        - já não está livre   → aviso "já foi reclamado"
 *        - já reclamei         → mensagem "Pedido enviado"
 *        - senão               → formulário "É teu?"
 *   4. Ao enviar o formulário, cria a reclamação (com fotos de prova).
 */

import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { getUser, isLoggedIn } from '../utilities/auth.js';
import { esc, formatarDataLonga, fotoVazia, lerParametro, seletorDeFotos } from '../utilities/tools.js';

montarNavBar('inicio');
montarFooter();

const $ = (id) => document.getElementById(id); // atalho

// ---------- 1. Qual objeto? ----------
const id = lerParametro('id');
const item = id ? await itemsService.getById(id) : null;

if (!item) {
  $('nao-encontrado').hidden = false;
} else {
  mostrarItem(item);
  await prepararReclamar(item);
  $('conteudo').hidden = false;
}

// ---------- 2. Preencher a página ----------
function mostrarItem(item) {
  document.title = `${item.titulo} · Achados e Perdidos IADE`;

  $('migalha-categoria').textContent = item.categoria;
  $('migalha-titulo').textContent = item.titulo;
  $('etiqueta-categoria').textContent = item.categoria;
  $('etiqueta-estado').textContent = { por_reclamar: 'Por reclamar', reclamado: 'Reclamado', devolvido: 'Devolvido' }[item.estado];
  $('titulo').textContent = item.titulo;
  $('info-local').textContent = item.local;
  $('info-data').textContent = formatarDataLonga(item.dataEncontrado);
  $('info-onde').textContent = item.ondeEsta === 'secretaria' ? 'Entregue na secretaria' : 'Com quem encontrou (combinar pela app)';
  $('info-autor').textContent = item.publicadoPor?.nome ?? '—';
  $('descricao').textContent = item.descricao || 'Sem descrição.';

  montarGaleria(item.fotos);
}

// Galeria: foto grande + miniaturas. Sem fotos → 3 espaços vazios com X
function montarGaleria(fotos) {
  const total = fotos.length || 3;

  function mostrarFoto(n) {
    $('foto-principal').innerHTML = fotos[n]
      ? `<img src="${esc(fotos[n])}" alt="Foto ${n + 1} do objeto">`
      : `${fotoVazia()}<span class="galeria__legenda">Foto ${n + 1} de ${total}</span>`;

    $('miniaturas').querySelectorAll('button').forEach((b, i) => b.setAttribute('aria-pressed', String(i === n)));
  }

  $('miniaturas').innerHTML = Array.from({ length: total }, (_, n) => `
    <button type="button" class="galeria__miniatura" data-foto="${n}" aria-label="Ver foto ${n + 1}">
      ${fotos[n] ? `<img src="${esc(fotos[n])}" alt="">` : ''}
    </button>`).join('');

  $('miniaturas').addEventListener('click', (e) => {
    const botao = e.target.closest('[data-foto]');
    if (botao) mostrarFoto(Number(botao.dataset.foto));
  });

  mostrarFoto(0);
}

// ---------- 3. Lado direito: o que mostrar? ----------
async function prepararReclamar(item) {
  const eu = getUser();

  if (eu && item.publicadoPor?.id === eu.id) {
    $('aviso-meu').hidden = false;
    return;
  }
  if (item.estado !== 'por_reclamar') {
    $('aviso-fechado').hidden = false;
    return;
  }

  $('bloco-reclamar').hidden = false;
  $('pergunta').textContent = item.pergunta || 'Descreve um detalhe do objeto';

  // Já reclamei este objeto antes?
  if (isLoggedIn()) {
    const minhas = await reclamacoesService.getMinhas();
    if (minhas.some((r) => r.itemId === item.id)) {
      mostrarEnviado('Já fizeste um pedido para este objeto. Vais receber uma notificação quando quem encontrou responder.');
      return;
    }
  }

  // Fotos de prova (até 3)
  const fotosProva = seletorDeFotos($('fotos-prova'), $('miniaturas-prova'), 3, () => {
    $('erro-fotos').hidden = false;
  });

  // ---------- 4. Enviar o pedido ----------
  $('form-reclamar').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!isLoggedIn()) {
      alert('Entra na tua conta para reclamar um objeto.');
      return;
    }

    const resposta = $('resposta').value.trim();
    $('erro-resposta').hidden = !!resposta;
    if (!resposta) {
      $('resposta').focus();
      return;
    }

    $('botao-reclamar').disabled = true;
    $('botao-reclamar').textContent = 'A enviar…';

    try {
      const n = fotosProva.ficheiros.length;
      await reclamacoesService.create(item.id, { resposta, fotos: fotosProva.ficheiros });
      mostrarEnviado(
        n
          ? `Pedido enviado com ${n} ${n === 1 ? 'foto' : 'fotos'} de prova. Vais receber uma notificação quando quem encontrou confirmar.`
          : 'Pedido enviado. Vais receber uma notificação quando quem encontrou confirmar.'
      );
    } catch (erro) {
      $('botao-reclamar').disabled = false;
      $('botao-reclamar').textContent = 'Reclamar objeto';
      $('erro-resposta').textContent = erro.message;
      $('erro-resposta').hidden = false;
    }
  });
}

// Esconde o formulário e mostra a mensagem verde
function mostrarEnviado(texto) {
  $('form-reclamar').hidden = true;
  $('pedido-enviado').textContent = texto;
  $('pedido-enviado').hidden = false;
}
