import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { cartaoItem } from '../components/itemCard.js';
import { procurasService } from '../services/procurasService.js';
import { itemsService } from '../services/itemsService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc, seletorDeFotos, dataParaApi } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

if (requerLogin()) {
  montarNavBar('procurar');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const categorias = await categoriasService.getAll();
  const locais = await locaisService.getAll();
  const opcoes = (lista) => lista.map((x) => `<option value="${esc(x.id)}">${esc(x.nome)}</option>`).join('');
  $('categoria').insertAdjacentHTML('beforeend', opcoes(categorias));
  $('local').insertAdjacentHTML('beforeend', opcoes(locais));

  const fotos = seletorDeFotos($('fotos'), $('miniaturas-fotos'), 3, () => {
    $('erro-fotos').hidden = false;
  });

  $('form-procurar').addEventListener('submit', async (e) => {
    e.preventDefault();

    const erros = {
      titulo: !$('titulo').value.trim(),
      categoria: !$('categoria').value,
      descricao: !$('descricao').value.trim(),
      local: !$('local').value,
    };
    Object.entries(erros).forEach(([campo, temErro]) => {
      $(`erro-${campo}`).hidden = !temErro;
    });
    const primeiroErro = Object.keys(erros).find((campo) => erros[campo]);
    if (primeiroErro) {
      $(primeiroErro).focus();
      return;
    }

    $('botao-enviar').disabled = true;
    $('botao-enviar').textContent = 'A publicar…';
    $('erro-geral').hidden = true;

    try {
      const procura = await procurasService.create(
        {
          titulo: $('titulo').value.trim(),
          descricao: $('descricao').value.trim(),
          categoriaId: $('categoria').value,
          localId: $('local').value,
          dataPerdido: dataParaApi($('data').value),
          fotos: fotos.ficheiros,
        });
      await mostrarParecidos(procura);
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-enviar').disabled = false;
      $('botao-enviar').textContent = 'Publicar pedido de procura →';
    }
  });
}

// Mostra logo os objetos da mesma categoria que já estão na receção.
// (A comparação por IA com os achados entra na fase 2.)
async function mostrarParecidos(procura) {
  const parecidos = await itemsService.getAll({ categoria: procura.categoriaId });

  $('parecidos-titulo').textContent = 'Objetos da mesma categoria já na receção';
  $('parecidos').innerHTML = parecidos.slice(0, 6).map(cartaoItem).join('');
  $('parecidos').hidden = parecidos.length === 0;
  $('parecidos-titulo').hidden = parecidos.length === 0;
  $('sem-parecidos').textContent = 'Ainda não há objetos desta categoria na receção.';
  $('sem-parecidos').hidden = parecidos.length > 0;

  $('referencia-procura').textContent = procura.referencia ? `Referência do teu registo: ${procura.referencia}` : '';

  $('ecra-formulario').hidden = true;
  $('ecra-enviado').hidden = false;
  window.scrollTo(0, 0);
}
