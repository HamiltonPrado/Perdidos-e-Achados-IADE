import { requerLogin, getUser, atualizarUser } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { userService } from '../services/userService.js';
import { esc, iniciais, nomeCurto, lerImagem } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

let fotoNova;
let previewFoto = null;

if (requerLogin()) {
  montarNavBar('perfil');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const user = await userService.getMe();
  $('nome').value = user.nome;
  previewFoto = user.foto;
  desenhar();

  $('ficheiro-foto').addEventListener('change', async () => {
    const ficheiro = $('ficheiro-foto').files[0];
    if (!ficheiro) return;
    fotoNova = ficheiro;
    previewFoto = await lerImagem(ficheiro, 300);
    $('ficheiro-foto').value = '';
    $('guardado').hidden = true;
    desenhar();
  });

  $('remover-foto').addEventListener('click', () => {
    fotoNova = null;
    previewFoto = null;
    $('guardado').hidden = true;
    desenhar();
  });

  $('nome').addEventListener('input', () => {
    $('guardado').hidden = true;
    desenhar();
  });

  $('form-perfil').addEventListener('submit', async (e) => {
    e.preventDefault();

    const nome = $('nome').value.trim();
    $('erro-nome').hidden = !!nome;
    if (!nome) {
      $('nome').focus();
      return;
    }

    $('botao-guardar').disabled = true;
    try {
      const atualizado = await userService.update({ nome, foto: fotoNova });
      atualizarUser(atualizado);
      montarNavBar('perfil');
      fotoNova = undefined;
      $('guardado').textContent = 'Perfil guardado.';
      $('guardado').hidden = false;
    } catch (erro) {
      $('erro-nome').textContent = erro.message;
      $('erro-nome').hidden = false;
    } finally {
      $('botao-guardar').disabled = false;
    }
  });
}

function desenhar() {
  const nome = $('nome').value || getUser()?.nome || '';
  $('avatar').innerHTML = previewFoto ? `<img src="${previewFoto}" alt="">` : esc(iniciais(nome));
  $('remover-foto').hidden = !previewFoto;
  $('exemplo-nome').textContent = nomeCurto(nome);
}
