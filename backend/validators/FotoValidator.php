<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class FotoValidator
{
    // Valida a fotografia enviada no campo "foto".
    public static function lerFotografia(): array
    {
        $ficheiro = $_FILES['foto'] ?? null;

        // Confirma que foi enviado um único ficheiro.
        if (
            !is_array($ficheiro)
            || !isset($ficheiro['error'])
            || !is_int($ficheiro['error'])
        ) {
            responderJson([
                'erro' => 'Envia uma fotografia no campo foto.'
            ], 422);
        }

        $erroUpload = $ficheiro['error'];

        if ($erroUpload === UPLOAD_ERR_NO_FILE) {
            responderJson([
                'erro' => 'Seleciona uma fotografia.'
            ], 422);
        }

        // Verifica se o PHP recusou a fotografia por exceder o tamanho permitido.
        if (
            $erroUpload === UPLOAD_ERR_INI_SIZE
            || $erroUpload === UPLOAD_ERR_FORM_SIZE
        ) {
            responderJson([
                'erro' => 'A fotografia ultrapassa o tamanho permitido.'
            ], 413);
        }

        if ($erroUpload === UPLOAD_ERR_PARTIAL) {
            responderJson([
                'erro' => 'O envio da fotografia ficou incompleto.'
            ], 400);
        }

        if ($erroUpload !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Falha ao receber a fotografia. Código: ' . $erroUpload
            );
        }

        $caminho = $ficheiro['tmp_name'] ?? null;

        // Confirma que a fotografia foi enviada neste pedido à API.
        if (
            !is_string($caminho)
            || !is_uploaded_file($caminho)
        ) {
            responderJson([
                'erro' => 'O ficheiro enviado não é válido.'
            ], 422);
        }

        // Recusa fotografias com mais de 2 MiB (2 × 1024 × 1024 bytes).
        $tamanho = filesize($caminho);

        if ($tamanho === false) {
            throw new RuntimeException(
                'Não foi possível verificar o tamanho da fotografia.'
            );
        }

        if ($tamanho === 0) {
            responderJson([
                'erro' => 'A fotografia está vazia.'
            ], 422);
        }

        if ($tamanho > 2 * 1024 * 1024) {
            responderJson([
                'erro' => 'A fotografia deve ter, no máximo, 2 MB.'
            ], 413);
        }

        // Verifica se o conteúdo é JPEG ou PNG; a extensão do nome não é suficiente.
        $detetor = new finfo(FILEINFO_MIME_TYPE);
        $mime = $detetor->file($caminho);

        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            responderJson([
                'erro' => 'Apenas são permitidas imagens JPEG ou PNG.'
            ], 422);
        }

        // Lê o conteúdo da fotografia para o guardar na base de dados.
        $dados = file_get_contents($caminho);

        if ($dados === false) {
            throw new RuntimeException(
                'Não foi possível ler a fotografia.'
            );
        }

        return [
            'mime' => $mime,
            'dados' => $dados,
        ];
    }
}
