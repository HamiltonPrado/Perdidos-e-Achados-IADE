<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class RecepcaoValidator
{
    public static function lerDados(): array
    {
        $tipoConteudo = strtolower(
            trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])
        );

        if ($tipoConteudo !== 'application/json') {
            responderJson([
                'erro' => 'O pedido deve usar Content-Type: application/json.'
            ], 415);
        }

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

        if (
            !is_array($dados)
            || !isset(
                $dados['ponto_recolha_id'],
                $dados['localizacao_stock']
            )
            || !is_int($dados['ponto_recolha_id'])
            || $dados['ponto_recolha_id'] <= 0
            || !is_string($dados['localizacao_stock'])
        ) {
            responderJson([
                'erro' => 'Indica um ponto de recolha válido '
                    . 'e a localização física do objeto.'
            ], 422);
        }

        $localizacao = trim($dados['localizacao_stock']);

        if (
            preg_match(
                '/\A[^\r\n]{1,100}\z/u',
                $localizacao
            ) !== 1
        ) {
            responderJson([
                'erro' => 'A localização deve ter entre 1 e 100 '
                    . 'caracteres, numa única linha.'
            ], 422);
        }

        $observacoes = $dados['observacoes_internas'] ?? null;

        if ($observacoes !== null) {
            if (!is_string($observacoes)) {
                responderJson([
                    'erro' => 'As observações internas devem ser texto.'
                ], 422);
            }

            $observacoes = trim($observacoes);

            if (
                preg_match(
                    '/\A.{0,1000}\z/us',
                    $observacoes
                ) !== 1
            ) {
                responderJson([
                    'erro' => 'As observações internas podem ter '
                        . 'no máximo 1000 caracteres.'
                ], 422);
            }

            if ($observacoes === '') {
                $observacoes = null;
            }
        }

        return [
            'ponto_recolha_id' => $dados['ponto_recolha_id'],
            'localizacao_stock' => $localizacao,
            'observacoes_internas' => $observacoes,
        ];
    }
}