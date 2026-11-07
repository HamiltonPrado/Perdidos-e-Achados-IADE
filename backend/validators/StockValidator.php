<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class StockValidator
{
    // Verifica os campos localizacao_stock e observacoes_internas enviados pela equipa.
    public static function validar(array $dados): array
    {
        $resultado = [];

        $campos = [
            'localizacao_stock' => 100,
            'observacoes_internas' => 1000,
        ];

        foreach ($campos as $campo => $limite) {
            // Se a equipa não enviar este campo, mantém o que já está guardado.
            if (!array_key_exists($campo, $dados)) {
                continue;
            }

            $valor = $dados[$campo];

            if ($valor === null) {
                $resultado[$campo] = null;
                continue;
            }

            if (!is_string($valor)) {
                responderJson([
                    'erro' => 'O campo ' . $campo
                        . ' deve ser texto ou null.',
                ], 422);
            }

            $valor = trim($valor);

            // Se a equipa enviar texto vazio, apaga o valor que estava neste campo.
            if ($valor === '') {
                $resultado[$campo] = null;
                continue;
            }

            $padrao = $campo === 'localizacao_stock'
                ? '/\A[^\r\n]{1,100}\z/u'
                : '/\A.{1,1000}\z/us';

            if (preg_match($padrao, $valor) !== 1) {
                responderJson([
                    'erro' => 'O campo ' . $campo
                        . ' deve ter até ' . $limite
                        . ' caracteres.'
                        . ($campo === 'localizacao_stock'
                            ? ' Usa uma única linha.'
                            : ''),
                ], 422);
            }

            $resultado[$campo] = $valor;
        }

        return $resultado;
    }
}
