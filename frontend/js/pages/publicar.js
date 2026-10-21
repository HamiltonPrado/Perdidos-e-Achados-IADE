/*
 * pages/publicar.js — LÓGICA DA PÁGINA PUBLICAR (publicar.html)
 * ------------------------------------------------------------
 * Como funciona:
 *   1. Só abre com sessão iniciada (requerLogin).
 *   2. Preenche as listas de categorias e locais.
 *   3. Vai atualizando os passos e o contador de caracteres.
 *   4. Ao publicar: valida os campos, envia ao itemsService e
 *      abre a página de detalhe do anúncio novo.
 */

import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc, seletorDeFotos } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

// ---------- 1. Precisa de sessão ----------
if (requerLogin()) {
  montarNavBar('publicar');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  // ---------- 2. Opções das listas ----------
  const categorias = await categoriasService.getAll();
  const locais = await locaisService.getAll();
  const opcoes = (lista) => lista.map((x) => `<option value="${esc(x.id)}">${esc(x.nome)}</option>`).join('');
  $('categoria').insertAdjacentHTML('beforeend', opcoes(categorias));
  $('local').insertAdjacentHTML('beforeend', opcoes(locais));

  // Fotos (até 5)
  const fotos = seletorDeFotos($('fotos'), $('miniaturas-fotos'), 5, () => {
    $('erro-fotos').hidden = false;
  });

  // ---------- 3. Contador e passos ----------
  $('descricao').addEventListener('input', () => {
    $('contador').textContent = `${$('descricao').value.length} / 500 caracteres`;
  });

  function atualizarPassos() {
    let passo = 1;
    if ($('titulo').value.trim() && $('categoria').value) passo = 2;
    if (passo === 2 && $('local').value) passo = 3;

    document.querySelectorAll('.passos li').forEach((li, n) => {
      if (n + 1 === passo) li.setAttribute('aria-current', 'step');
      else li.removeAttribute('aria-current');
    });
  }
  ['titulo', 'categoria', 'local'].forEach((id) => {
    $(id).addEventListener('input', atualizarPassos);
    $(id).addEventListener('change', atualizarPassos);
  });

  // ---------- 4. Publicar ----------
  $('form-publicar').addEventListener('submit', async (e) => {
    e.preventDefault();

    // Validação: mostra o erro de cada campo em falta
    const erros = {
      titulo: !$('titulo').value.trim(),
      categoria: !$('categoria').value,
      local: !$('local').value,
      pergunta: !$('pergunta').value.trim(),
      confirmo: !$('confirmo').checked,
    };
    Object.entries(erros).forEach(([campo, temErro]) => {
      $(`erro-${campo}`).hidden = !temErro;
    });

    // Põe o cursor no primeiro campo com erro
    const primeiroErro = Object.keys(erros).find((campo) => erros[campo]);
    if (primeiroErro) {
      $(primeiroErro).focus();
      return;
    }

    $('botao-publicar').disabled = true;
    $('botao-publicar').textContent = 'A publicar…';
    $('erro-geral').hidden = true;

    try {
      const novo = await itemsService.create(
        {
          titulo: $('titulo').value.trim(),
          descricao: $('descricao').value.trim(),
          cor: $('cor').value.trim(),
          categoriaId: $('categoria').value,
          localId: $('local').value,
          dataEncontrado: $('data').value ? new Date($('data').value).toISOString() : new Date().toISOString(),
          ondeEsta: document.querySelector('input[name="onde"]:checked').value,
          pergunta: $('pergunta').value.trim(),
          fotos: fotos.ficheiros,
        },
        categorias,
        locais
      );

      // Vai para a página do anúncio acabado de criar
      window.location.href = `detalhe.html?id=${novo.id}`;
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-publicar').disabled = false;
      $('botao-publicar').textContent = 'Publicar anúncio';
    }
  });
}
