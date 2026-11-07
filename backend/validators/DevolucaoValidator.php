<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class DevolucaoValidator
{
    // Lê e valida os dados enviados para registar uma devolução.
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

        // recebido_por deve indicar o ID da pessoa a quem a equipa entrega o objeto.
        if (
            !is_array($dados)
            || !isset($dados['recebido_por'])
            || !is_int($dados['recebido_por'])
            || $dados['recebido_por'] <= 0
        ) {
            responderJson([
                'erro' => 'Indica recebido_por como um inteiro positivo.'
            ], 422);
        }

        // item_perdido_id e match_id são opcionais: podes não os enviar
        // ou enviar null para indicar que não existe essa associação.
        foreach (['item_perdido_id', 'match_id'] as $campo) {
            $valor = $dados[$campo] ?? null;

            if (
                $valor !== null
                && (!is_int($valor) || $valor <= 0)
            ) {
                responderJson([
                    'erro' => 'O campo ' . $campo
                        . ' deve ser um inteiro positivo ou null.'
                ], 422);
            }
        }

        $perdidoId = $dados['item_perdido_id'] ?? null;
        $matchId = $dados['match_id'] ?? null;

        // Se enviares uma correspondência da IA (match_id), indica também
        // o objeto perdido a que ela se refere (item_perdido_id).
        if ($matchId !== null && $perdidoId === null) {
            responderJson([
                'erro' => 'Indica item_perdido_id quando enviares match_id.'
            ], 422);
        }

        return [
            'recebido_por' => $dados['recebido_por'],
            'item_perdido_id' => $perdidoId,
            'match_id' => $matchId,
        ];
    }
}
