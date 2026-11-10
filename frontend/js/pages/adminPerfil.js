import { requerAdmin, getUser, atualizarUser } from '../utilities/auth.js';
import { url } from '../utilities/caminhos.js';
import { montarAdminHeader } from '../components/adminHeader.js';
import { montarFooter } from '../components/footer.js';
import { userService } from '../services/userService.js';
import { authService } from '../services/authService.js';
import { esc, iniciais, lerImagem } from '../utilities/tools.js';

// Perfil da receção: igual ao do aluno (perfil.js), mas com o cabeçalho da receção.
// O nome e a foto ainda não são gravados no backend (não há rota para editar a conta).

const $ = (id) => document.getElementById(id);

let fotoNova;
let previewFoto = null;

if (requerAdmin()) {
  montarAdminHeader('perfil');
  montarFooter();
  await iniciar();
}

async function iniciar() {
  const user = await userService.getMe();
  $('nome').value = user.nome;
  $('email').textContent = getUser()?.email ?? user.email ?? '';
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

  $('terminar-sessao').addEventListener('click', async () => {
    await authService.sair();
    window.location.href = url('aluno/entrar.html?perfil=admin');
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
      montarAdminHeader('perfil');
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
  $('avatar').innerHTML = previewFoto ? `<img src="${esc(previewFoto)}" alt="">` : esc(iniciais(nome));
  $('remover-foto').hidden = !previewFoto;
}
