<?php

declare(strict_types=1);

// Limite de tentativas de login por email: 5 falhas em 15 minutos bloqueiam esse email.
// Fica na base de dados, por isso vale para todos os nós e réplicas do web.
class TentativaLogin
{
    public const MAXIMO = 5;
    public const JANELA_MINUTOS = 15;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Segundos que faltam para o email poder tentar outra vez (0 = não está bloqueado).
    public function segundosBloqueado(string $email): int
    {
        // A 5.ª falha mais recente: quando sair da janela, o email volta a poder tentar.
        $consulta = $this->pdo->prepare('
            SELECT TIMESTAMPDIFF(SECOND, NOW(), falhou_em + INTERVAL ' . self::JANELA_MINUTOS . ' MINUTE)
            FROM tentativas_login
            WHERE email = :email
              AND falhou_em > NOW() - INTERVAL ' . self::JANELA_MINUTOS . ' MINUTE
            ORDER BY falhou_em DESC
            LIMIT 1 OFFSET ' . (self::MAXIMO - 1)
        );
        $consulta->execute(['email' => $email]);

        return max(0, (int) $consulta->fetchColumn());
    }

    public function registarFalha(string $email): void
    {
        $this->pdo->prepare('INSERT INTO tentativas_login (email) VALUES (:email)')
            ->execute(['email' => $email]);

        // Limpeza: falhas com mais de um dia já não contam para nada.
        $this->pdo->exec('DELETE FROM tentativas_login WHERE falhou_em < NOW() - INTERVAL 1 DAY');
    }

    // Um login certo apaga as falhas anteriores desse email.
    public function limpar(string $email): void
    {
        $this->pdo->prepare('DELETE FROM tentativas_login WHERE email = :email')
            ->execute(['email' => $email]);
    }
}
