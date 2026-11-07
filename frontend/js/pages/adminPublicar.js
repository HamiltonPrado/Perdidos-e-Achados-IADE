import { requerAdmin } from '../utilities/auth.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { adminService } from '../services/adminService.js';
import { categoriasService } from '../services/categoriasService.js';
import { locaisService } from '../services/locaisService.js';
import { esc, seletorDeFotos } from '../utilities/tools.js';
import { PRATELEIRAS } from '../utilities/stock.js';

const $ = (id) => document.getElementById(id);

if (requerAdmin()) {
  montarAdminHeader('publicar');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const [categorias, locais, pontos] = await Promise.all([
    categoriasService.getAll(),
    locaisService.getAll(),
    adminService.listarPontosRecolha(),
  ]);
  const opcoes = (lista) => lista.map((x) => `<option value="${esc(x.id)}">${esc(x.nome)}</option>`).join('');
  $('categoria').insertAdjacentHTML('beforeend', opcoes(categorias));
  $('local').insertAdjacentHTML('beforeend', opcoes(locais));
  $('ponto').innerHTML = opcoes(pontos);
  await preencherPrateleiras();

  const fotos = seletorDeFotos($('fotos'), $('miniaturas-fotos'), 5);
  $('fotos').addEventListener('change', () => { $('erro-fotos').hidden = true; });

  $('form-publicar').addEventListener('submit', async (e) => {
    e.preventDefault();
    const erros = {
      titulo: !$('titulo').value.trim(),
      categoria: !$('categoria').value,
      descricao: !$('descricao').value.trim(),
      local: !$('local').value,
      pergunta: !$('pergunta').value.trim(),
      resposta: !$('resposta').value.trim(),
      stock: !$('stock').value,
    };
    Object.entries(erros).forEach(([campo, temErro]) => { $(`erro-${campo}`).hidden = !temErro; });
    const semFoto = fotos.ficheiros.length === 0;
    $('erro-fotos').hidden = !semFoto;

    const primeiro = semFoto ? 'fotos' : Object.keys(erros).find((c) => erros[c]);
    if (primeiro) {
      $(primeiro === 'fotos' ? 'miniaturas-fotos' : primeiro).scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (primeiro !== 'fotos') $(primeiro).focus();
      return;
    }

    $('botao-publicar').disabled = true;
    $('erro-geral').hidden = true;
    try {
      const novo = await adminService.publicarObjeto({
        titulo: $('titulo').value.trim(),
        descricao: $('descricao').value.trim(),
        categoriaId: $('categoria').value,
        localId: $('local').value,
        dataEncontrado: $('data').value ? new Date($('data').value).toISOString() : new Date().toISOString(),
        pontoRecolhaId: $('ponto').value,
        localizacaoStock: $('stock').value,
        entreguePor: $('entregue').value.trim(),
        pergunta: $('pergunta').value.trim(),
        respostaEsperada: $('resposta').value.trim(),
        fotos: fotos.ficheiros,
      }, categorias, locais);
      window.location.href = `objetos.html?separador=rececao&publicado=${encodeURIComponent(novo.referencia)}`;
    } catch (erro) {
      $('erro-geral').textContent = erro.message;
      $('erro-geral').hidden = false;
      $('botao-publicar').disabled = false;
    }
  });

  $('botao-publicar').disabled = false;
}

async function preencherPrateleiras() {
  const livres = await adminService.prateleirasLivres();
  $('stock').innerHTML = livres.length
    ? `<option value="">Escolher prateleira…</option>${livres.map((p) => `<option>${p}</option>`).join('')}`
    : '<option value="">Não há prateleiras livres</option>';
  $('stock').disabled = livres.length === 0;
  $('ajuda-stock').textContent = `${livres.length} de ${PRATELEIRAS.length} prateleiras livres.`;
}
