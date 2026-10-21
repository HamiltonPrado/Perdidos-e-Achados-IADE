/*
 * pages/perfil.js — LÓGICA DA PÁGINA PERFIL (perfil.html)
 * ------------------------------------------------------------
 * Como funciona:
 *   1. Só abre com sessão iniciada.
 *   2. Mostra o nome e a foto atuais.
 *   3. Ao escolher/remover foto ou escrever o nome, mostra logo
 *      como vai ficar (mas só guarda ao carregar em "Guardar").
 *   4. Ao guardar: envia ao userService, atualiza a sessão e a navbar.
 */

import { requerLogin, getUser, atualizarUser } from '../utilities/auth.js';
import { montarNavBar } from '../components/navbar.js';
import { montarFooter } from '../components/footer.js';
import { userService } from '../services/userService.js';
import { esc, iniciais, nomeCurto, lerImagem } from '../utilities/tools.js';

const $ = (id) => document.getElementById(id);

// Foto escolhida mas ainda não guardada:
//   undefined = não mexer · File = nova foto · null = remover
let fotoNova;
let previewFoto = null; // imagem para mostrar no círculo

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

  // Escolher nova foto
  $('ficheiro-foto').addEventListener('change', async () => {
    const ficheiro = $('ficheiro-foto').files[0];
    if (!ficheiro) return;
    fotoNova = ficheiro;
    previewFoto = await lerImagem(ficheiro, 300);
    $('ficheiro-foto').value = '';
    $('guardado').hidden = true;
    desenhar();
  });

  // Remover foto
  $('remover-foto').addEventListener('click', () => {
    fotoNova = null;
    previewFoto = null;
    $('guardado').hidden = true;
    desenhar();
  });

  // Ao escrever o nome, atualiza as iniciais e o exemplo
  $('nome').addEventListener('input', () => {
    $('guardado').hidden = true;
    desenhar();
  });

  // Guardar
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
      atualizarUser(atualizado);   // guarda na sessão
      montarNavBar('perfil');      // o avatar do topo passa a mostrar a foto/nome novos
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

// Atualiza o círculo, o botão "Remover foto" e o exemplo do nome
function desenhar() {
  const nome = $('nome').value || getUser()?.nome || '';
  $('avatar').innerHTML = previewFoto ? `<img src="${previewFoto}" alt="">` : esc(iniciais(nome));
  $('remover-foto').hidden = !previewFoto;
  $('exemplo-nome').textContent = nomeCurto(nome);
}
