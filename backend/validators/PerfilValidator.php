<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/resposta.php';

class PerfilValidator
{
    public const NOME_MIN = 2;
    public const NOME_MAX = 100;

    // Limpa espaços a mais e confirma que o nome é texto normal com 2 a 100 caracteres.
    public static function validarNome(mixed $nome): string
    {
        if (!is_string($nome)) {
            responderJson(['erro' => 'Envia o nome como texto.'], 422);
        }

        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        $tamanho = mb_strlen($nome);

        if ($tamanho < self::NOME_MIN || $tamanho > self::NOME_MAX) {
            responderJson([
                'erro' => 'O nome deve ter entre ' . self::NOME_MIN . ' e ' . self::NOME_MAX . ' caracteres.'
            ], 422);
        }

        // Sem caracteres de controlo nem símbolos (<, >, @…): letras, números, espaços, hífen, apóstrofo e ponto.
        if (preg_match("/\A[\p{L}\p{M}\p{N} .'-]+\z/u", $nome) !== 1) {
            responderJson([
                'erro' => 'O nome só pode ter letras, números, espaços, hífen, apóstrofo e ponto.'
            ], 422);
        }

        return $nome;
    }
}
