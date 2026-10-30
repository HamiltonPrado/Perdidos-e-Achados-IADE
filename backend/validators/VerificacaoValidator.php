<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class VerificacaoValidator
{
    public static function validar(array $dados): array
    {
        if (
            !isset(
                $dados['pergunta_secreta'],
                $dados['resposta_esperada']
            )
            || !is_string($dados['pergunta_secreta'])
            || !is_string($dados['resposta_esperada'])
        ) {
            responderJson([
                'erro' => 'Indica a pergunta secreta '
                    . 'e a resposta esperada.'
            ], 422);
        }

        $pergunta = trim($dados['pergunta_secreta']);
        $resposta = trim($dados['resposta_esperada']);

        if (
            preg_match(
                '/\A[^\r\n]{1,255}\z/u',
                $pergunta
            ) !== 1
        ) {
            responderJson([
                'erro' => 'A pergunta deve ter entre 1 e 255 '
                    . 'caracteres, numa única linha.'
            ], 422);
        }

        if (
            preg_match(
                '/\A.{1,500}\z/us',
                $resposta
            ) !== 1
        ) {
            responderJson([
                'erro' => 'A resposta esperada deve ter '
                    . 'entre 1 e 500 caracteres.'
            ], 422);
        }

        return [
            'pergunta_secreta' => $pergunta,
            'resposta_esperada' => $resposta,
        ];
    }
}