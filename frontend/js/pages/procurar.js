import { requerLogin } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { cartaoItem } from '../components/itemCard.js';
import { procurasService, IA_ATIVA } from '../services/procurasService.js';
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

// A IA corre à parte (ia/worker.py) e demora alguns segundos a comparar a procura com os
// achados da receção. O ecrã aparece logo e vai perguntando ao backend até a análise acabar.
const ESPERA_ENTRE_PEDIDOS = 2000;
const ESPERA_MAXIMA = 30000;

async function mostrarParecidos(procura) {
  $('referencia-procura').textContent = procura.referencia ? `Referência do teu registo: ${procura.referencia}` : '';
  $('parecidos-titulo').textContent = 'A comparar com os objetos na receção…';
  $('parecidos').innerHTML = '';
  $('sem-parecidos').hidden = true;

  $('ecra-formulario').hidden = true;
  $('ecra-enviado').hidden = false;
  window.scrollTo(0, 0);

  if (!IA_ATIVA) {
    await mostrarMesmaCategoria(procura);
    return;
  }

  const limite = Date.now() + ESPERA_MAXIMA;
  let resultado = { estadoIa: 'pendente', itens: [] };
  try {
    do {
      await new Promise((r) => setTimeout(r, ESPERA_ENTRE_PEDIDOS));
      resultado = await procurasService.sugestoes(procura.id);
    } while (resultado.estadoIa === 'pendente' && Date.now() < limite);
  } catch {
    resultado = { estadoIa: 'erro', itens: [] };
  }

  if (resultado.itens.length > 0) {
    $('parecidos-titulo').textContent = resultado.itens.length === 1
      ? 'Encontrámos um objeto parecido na receção'
      : `Encontrámos ${resultado.itens.length} objetos parecidos na receção`;
    $('parecidos').innerHTML = resultado.itens.map(cartaoItem).join('');
    return;
  }

  $('parecidos-titulo').textContent = 'Objetos parecidos já na receção';
  $('sem-parecidos').textContent = resultado.estadoIa === 'analisado'
    ? 'Ainda não há objetos parecidos na receção. Vamos avisar-te quando chegar um.'
    : 'A comparação está a demorar mais do que o normal. Vamos avisar-te quando houver um objeto parecido.';
  $('sem-parecidos').hidden = false;
}

// Sem IA: mostra os objetos da mesma categoria que já estão na receção.
async function mostrarMesmaCategoria(procura) {
  const itens = await itemsService.getAll({ categoria: procura.categoriaId }).catch(() => []);

  $('parecidos-titulo').textContent = 'Objetos da mesma categoria já na receção';
  $('parecidos').innerHTML = itens.slice(0, 6).map(cartaoItem).join('');
  $('sem-parecidos').textContent = 'Ainda não há objetos desta categoria na receção.';
  $('sem-parecidos').hidden = itens.length > 0;
}
