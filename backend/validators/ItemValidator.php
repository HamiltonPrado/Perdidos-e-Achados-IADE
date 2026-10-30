<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/VerificacaoValidator.php';

class ItemValidator
{
    // Lê o JSON e valida os dados necessários para registar um artigo.
        public static function lerDadosArtigo(
        bool $exigirPontoRecolha = false,
        bool $exigirVerificacao = false
    ): array {
        $tipoConteudo = strtolower(
            trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
        );

        if ($tipoConteudo !== 'application/json') {
            responderJson([
                'erro' => 'O pedido deve usar Content-Type: application/json.'
            ], 415);
        }

        // Converte o JSON recebido num array.
        try {
            $dados = json_decode(
                file_get_contents('php://input'),
                true,
                32,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $erro) {
            responderJson([
                'erro' => 'O JSON enviado é inválido.'
            ], 400);
        }

        // Confirma a presença e os tipos dos campos obrigatórios.
        if (
            !is_array($dados)
            || !isset(
                $dados['titulo'],
                $dados['descricao'],
                $dados['category_id'],
                $dados['location_id'],
                $dados['data_ocorrencia']
            )
            || !is_string($dados['titulo'])
            || !is_string($dados['descricao'])
            || !is_int($dados['category_id'])
            || !is_int($dados['location_id'])
            || !is_string($dados['data_ocorrencia'])
        ) {
            responderJson([
                'erro' => 'Envia título, descrição, categoria, local e data. '
                    . 'Os identificadores devem ser números inteiros.'
            ], 422);
        }

        $titulo = trim($dados['titulo']);
        $descricao = trim($dados['descricao']);

        // Valida o comprimento dos textos.
        if (
            preg_match('/\A[^\r\n]{1,150}\z/u', $titulo) !== 1
            || preg_match('/\A.{1,5000}\z/us', $descricao) !== 1
        ) {
            responderJson([
                'erro' => 'O título deve ter entre 1 e 150 caracteres '
                    . 'e a descrição entre 1 e 5000.'
            ], 422);
        }

        // Os identificadores devem ser inteiros positivos.
        if ($dados['category_id'] <= 0 || $dados['location_id'] <= 0) {
            responderJson([
                'erro' => 'Seleciona uma categoria e um local válidos.'
            ], 422);
        }

        // Nos achados, exige também um ponto de recolha.
        if (
            $exigirPontoRecolha
            && (
                !isset($dados['ponto_recolha_id'])
                || !is_int($dados['ponto_recolha_id'])
                || $dados['ponto_recolha_id'] <= 0
            )
        ) {
            responderJson([
                'erro' => 'Seleciona um ponto de recolha válido.'
            ], 422);
        }

        // Verifica o formato da data.
        $dataTexto = $dados['data_ocorrencia'];

        if (
            preg_match(
                '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/',
                $dataTexto
            ) !== 1
        ) {
            responderJson([
                'erro' => 'A data deve usar o formato AAAA-MM-DD.'
            ], 422);
        }

        $fusoHorario = new DateTimeZone('Europe/Lisbon');

        $data = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dataTexto,
            $fusoHorario
        );

        $hoje = new DateTimeImmutable('today', $fusoHorario);

        // Rejeita datas inexistentes, anteriores ao limite ou futuras.
        if (
            $data === false
            || $data->format('Y-m-d') !== $dataTexto
            || $dataTexto < '1000-01-01'
            || $data > $hoje
        ) {
            responderJson([
                'erro' => 'Indica uma data válida que não seja futura.'
            ], 422);
        }

        // Devolve apenas os campos permitidos e já validados.
                    $verificacao = $exigirVerificacao
            ? VerificacaoValidator::validar($dados)
            : [];

        return array_merge([
            'titulo' => $titulo,
            'descricao' => $descricao,
            'category_id' => $dados['category_id'],
            'location_id' => $dados['location_id'],
            'data_ocorrencia' => $dataTexto,
            'ponto_recolha_id' => $exigirPontoRecolha
                ? $dados['ponto_recolha_id']
                : null,
        ], $verificacao);
    }
}