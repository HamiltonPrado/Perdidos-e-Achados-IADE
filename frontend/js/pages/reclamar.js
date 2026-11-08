import { requerLogin, getUser } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { itemsService } from '../services/itemsService.js';
import { reclamacoesService } from '../services/reclamacoesService.js';
import { esc, fotoVazia, lerParametro } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

if (requerLogin()) {
  montarNavBar('inicio');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const id = lerParametro('id');
  const item = id ? await itemsService.getById(id) : null;

  if (!item || item.estado !== 'por_reclamar') {
    $('indisponivel').hidden = false;
    return;
  }
  if (item.publicadoPor?.id === getUser()?.id) {
    $('indisponivel-texto').textContent = 'Não podes reclamar um objeto que foste tu a registar.';
    $('indisponivel').hidden = false;
    return;
  }

  // O backend recusa reclamações enquanto a equipa não definir a pergunta secreta.
  if (!item.pergunta) {
    $('indisponivel-texto').textContent = 'A receção ainda está a preparar este objeto. Tenta reclamá-lo mais tarde.';
    $('indisponivel').hidden = false;
    return;
  }

  const minhas = await reclamacoesService.getMinhas();
  if (minhas.some((r) => r.itemId === item.id)) {
    mostrarEnviado();
    return;
  }

  $('voltar-detalhe').href = `detalhe.html?id=${item.id}`;
  $('cancelar').href = `detalhe.html?id=${item.id}`;

  const foto = item.fotos[0];
  $('resumo-foto').innerHTML = foto ? `<img src="${esc(foto)}" alt="">` : fotoVazia();
  $('resumo-titulo').textContent = item.titulo;
  $('resumo-local').textContent = `Encontrado em ${item.local}`;
  $('pergunta').textContent = item.pergunta;

  $('ecra-formulario').hidden = false;

  $('form-reclamar').addEventListener('submit', async (e) => {
    e.preventDefault();

    const resposta = $('resposta').value.trim();
    $('erro-resposta').hidden = !!resposta;
    if (!resposta) {
      $('resposta').focus();
      return;
    }

    $('botao-continuar').disabled = true;
    $('botao-continuar').textContent = 'A enviar…';
    $('erro-geral').hidden = true;

    try {
      await reclamacoesService.create(item.id, {
        resposta,
        informacao: $('informacao').value.trim(),
      });
      mostrarEnviado();
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-continuar').disabled = false;
      $('botao-continuar').textContent = 'Continuar →';
    }
  });
}

function mostrarEnviado() {
  $('ecra-formulario').hidden = true;
  $('ecra-enviado').hidden = false;
  window.scrollTo(0, 0);
}
