<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class ReclamacaoValidator
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
            || !isset($dados['resposta'])
            || !is_string($dados['resposta'])
        ) {
            responderJson([
                'erro' => 'Indica a tua resposta à pergunta secreta.'
            ], 422);
        }

        $resposta = trim($dados['resposta']);

        if (preg_match('/\A.{1,500}\z/us', $resposta) !== 1) {
            responderJson([
                'erro' => 'A resposta deve ter entre 1 e 500 caracteres.'
            ], 422);
        }

        $informacao = $dados['informacao_adicional'] ?? null;

        if ($informacao !== null) {
            if (!is_string($informacao)) {
                responderJson([
                    'erro' => 'A informação adicional deve ser texto.'
                ], 422);
            }

            $informacao = trim($informacao);

            if (
                preg_match('/\A.{0,1000}\z/us', $informacao) !== 1
            ) {
                responderJson([
                    'erro' => 'A informação adicional pode ter '
                        . 'no máximo 1000 caracteres.'
                ], 422);
            }

            if ($informacao === '') {
                $informacao = null;
            }
        }

        $perdidoId = $dados['item_perdido_id'] ?? null;

        if (
            $perdidoId !== null
            && (!is_int($perdidoId) || $perdidoId <= 0)
        ) {
            responderJson([
                'erro' => 'O identificador do perdido deve ser '
                    . 'um número inteiro positivo.'
            ], 422);
        }

        return [
            'resposta' => $resposta,
            'informacao_adicional' => $informacao,
            'item_perdido_id' => $perdidoId,
        ];
    }
}