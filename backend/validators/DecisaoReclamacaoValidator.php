<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class DecisaoReclamacaoValidator
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
            || !isset($dados['estado'])
            || !is_string($dados['estado'])
            || !in_array(
                $dados['estado'],
                ['aprovada', 'recusada'],
                true
            )
        ) {
            responderJson([
                'erro' => 'O estado deve ser aprovada ou recusada.'
            ], 422);
        }

        $motivo = $dados['motivo_decisao'] ?? null;

        if ($motivo !== null) {
            if (!is_string($motivo)) {
                responderJson([
                    'erro' => 'O motivo da decisão deve ser texto.'
                ], 422);
            }

            $motivo = trim($motivo);

            if (preg_match('/\A.{0,1000}\z/us', $motivo) !== 1) {
                responderJson([
                    'erro' => 'O motivo pode ter no máximo '
                        . '1000 caracteres.'
                ], 422);
            }

            if ($motivo === '') {
                $motivo = null;
            }
        }

        if ($dados['estado'] === 'recusada' && $motivo === null) {
            responderJson([
                'erro' => 'Indica o motivo da recusa.'
            ], 422);
        }

        return [
            'estado' => $dados['estado'],
            'motivo_decisao' => $motivo,
        ];
    }
}