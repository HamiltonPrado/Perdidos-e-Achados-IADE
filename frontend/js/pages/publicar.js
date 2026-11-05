import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc, seletorDeFotos } from '../utilities/tools.js';
import { RECECAO } from '../utilities/rececao.js';

const $ = (id) => document.getElementById(id);

if (requerLogin()) {
  montarNavBar('publicar');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  $('nota-proximo').textContent = `Depois de registares, entrega o objeto à ${RECECAO.responsavel} na receção.`;

  const categorias = await categoriasService.getAll();
  const locais = await locaisService.getAll();
  const opcoes = (lista) => lista.map((x) => `<option value="${esc(x.id)}">${esc(x.nome)}</option>`).join('');
  $('categoria').insertAdjacentHTML('beforeend', opcoes(categorias));
  $('local').insertAdjacentHTML('beforeend', opcoes(locais));

  const fotos = seletorDeFotos($('fotos'), $('miniaturas-fotos'), 5, () => {
    $('erro-fotos').hidden = false;
  });

  $('descricao').addEventListener('input', () => {
    $('contador').textContent = `${$('descricao').value.length} / 500 caracteres`;
  });

  $('form-publicar').addEventListener('submit', async (e) => {
    e.preventDefault();

    const erros = {
      titulo: !$('titulo').value.trim(),
      categoria: !$('categoria').value,
      local: !$('local').value,
      pergunta: !$('pergunta').value.trim(),
      resposta: !$('resposta').value.trim(),
    };
    Object.entries(erros).forEach(([campo, temErro]) => {
      $(`erro-${campo}`).hidden = !temErro;
    });

    const primeiroErro = Object.keys(erros).find((campo) => erros[campo]);
    if (primeiroErro) {
      $(primeiroErro).focus();
      return;
    }

    $('botao-publicar').disabled = true;
    $('botao-publicar').textContent = 'A registar…';
    $('erro-geral').hidden = true;

    try {
      const novo = await itemsService.create(
        {
          titulo: $('titulo').value.trim(),
          descricao: $('descricao').value.trim(),
          categoriaId: $('categoria').value,
          localId: $('local').value,
          dataEncontrado: $('data').value ? new Date($('data').value).toISOString() : new Date().toISOString(),
          pergunta: $('pergunta').value.trim(),
          respostaEsperada: $('resposta').value.trim(),
          fotos: fotos.ficheiros,
        },
        categorias,
        locais
      );

      window.location.href = `registo.html?id=${novo.id}&novo=1`;
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-publicar').disabled = false;
      $('botao-publicar').textContent = 'Registar objeto →';
    }
  });
}
