<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../helpers/resposta.php';
require_once __DIR__ . '/../middleware/autenticacao.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/administrador.php';

class ItemController
{
    private PDO $pdo;
    private Item $artigos;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->artigos = new Item($pdo);
    }


    // Devolve os artigos pertencentes ao utilizador autenticado.
    public function listarMeus(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $artigos = $this->artigos->listarPorUtilizador(
            $utilizador['id']
        );

        responderJson([
            'estado' => 'ok',
            'artigos' => $artigos
        ]);
    }

    // Permite à equipa administradora consultar todos os artigos.
    public function listarTodos(): void
    {
        exigirAdministrador($this->pdo);

        $artigos = $this->artigos->listarTodos();

        responderJson([
            'estado' => 'ok',
            'artigos' => $artigos
        ]);
    }


        // Lê e valida os dados usados no registo de um artigo.
    private function lerDadosArtigo(bool $exigirPontoRecolha = false): array
    {
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

        // Valida os textos e os identificadores.
        if (
            preg_match('/\A[^\r\n]{1,150}\z/u', $titulo) !== 1
            || preg_match('/\A.{1,5000}\z/us', $descricao) !== 1
        ) {
            responderJson([
                'erro' => 'O título deve ter entre 1 e 150 caracteres '
                    . 'e a descrição entre 1 e 5000.'
            ], 422);
        }

        if ($dados['category_id'] <= 0 || $dados['location_id'] <= 0) {
            responderJson([
                'erro' => 'Seleciona uma categoria e um local válidos.'
            ], 422);
        }

        // Nos achados, exige um ponto de recolha.
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

        // Verifica o formato e a validade da data.
        $dataTexto = $dados['data_ocorrencia'];

        if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $dataTexto) !== 1) {
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

        // Devolve apenas os campos permitidos, já validados.
        return [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'category_id' => $dados['category_id'],
            'location_id' => $dados['location_id'],
            'data_ocorrencia' => $dataTexto,
            'ponto_recolha_id' => $exigirPontoRecolha
                ? $dados['ponto_recolha_id']
                : null,
        ];
    }


        // Regista um objeto perdido associado ao utilizador autenticado.
    public function criarPerdido(): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $dados = $this->lerDadosArtigo();

        try {
            $id = $this->artigos->criarPerdido(
                $utilizador['id'],
                $dados
            );
        } catch (PDOException $erro) {
            if ((int) ($erro->errorInfo[1] ?? 0) === 1452) {
                responderJson([
                    'erro' => 'Não foi possível associar o registo. '
                        . 'Confirma a categoria e o local selecionados.'
                ], 422);
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Objeto perdido registado.',
            'item' => [
                'id' => $id,
                'tipo' => 'lost',
                'estado' => 'ativo',
                'ia_estado' => 'pendente',
            ]
        ], 201);
    }

        // Regista um objeto achado, com acesso reservado à equipa.
    public function criarAchado(): void
    {
        $utilizador = exigirAdministrador($this->pdo);
        exigirCsrf();

        // Exige também um ponto de recolha.
        $dados = $this->lerDadosArtigo(true);

        try {
            $id = $this->artigos->criarAchado(
                $utilizador['id'],
                $dados
            );
        } catch (PDOException $erro) {
            if ((int) ($erro->errorInfo[1] ?? 0) === 1452) {
                responderJson([
                    'erro' => 'Não foi possível associar o registo. '
                        . 'Confirma a categoria, o local e o ponto de recolha.'
                ], 422);
            }

            throw $erro;
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Objeto achado registado.',
            'item' => [
                'id' => $id,
                'tipo' => 'found',
                'estado' => 'ativo',
                'ia_estado' => 'pendente',
            ]
        ], 201);
    }

        // Permite ao autor marcar o seu objeto perdido como resolvido.
    public function resolver(int $id): void
    {
        $utilizador = exigirAutenticacao($this->pdo);
        exigirCsrf();

        $artigo = $this->artigos->procurarPorId($id);

        // Apenas o autor pode executar esta operação.
        if (
            $artigo === null
            || (int) $artigo['user_id'] !== $utilizador['id']
        ) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        // A opção "Já encontrei" aplica-se apenas aos perdidos.
        if ($artigo['tipo'] !== 'lost') {
            responderJson([
                'erro' => 'Esta operação aplica-se apenas a objetos perdidos.'
            ], 409);
        }

        // Uma repetição do pedido mantém o resultado já alcançado.
        if ($artigo['estado'] !== 'resolvido') {
            if ($artigo['estado'] !== 'ativo') {
                responderJson([
                    'erro' => 'Só podes resolver um objeto perdido ativo.'
                ], 409);
            }

            $alterado = $this->artigos->marcarResolvido(
                $id,
                $utilizador['id']
            );

            // O estado pode ter mudado entre a consulta e a atualização.
            if (!$alterado) {
                responderJson([
                    'erro' => 'O artigo foi alterado entretanto. '
                        . 'Atualiza os dados antes de tentar novamente.'
                ], 409);
            }
        }

        responderJson([
            'estado' => 'ok',
            'mensagem' => 'Objeto perdido marcado como resolvido.',
            'item' => [
                'id' => $id,
                'estado' => 'resolvido',
            ]
        ]);
    }

        // Devolve um artigo ao seu autor ou à equipa administradora.
    public function mostrar(int $id): void
    {
        $utilizador = exigirAutenticacao($this->pdo);

        $artigo = $this->artigos->procurarPorId($id);

        if ($artigo === null) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        $pertenceAoUtilizador =
            (int) $artigo['user_id'] === $utilizador['id'];

        $eAdministrador = $utilizador['role'] === 'admin';

        // Usa também 404 para não revelar artigos de outras pessoas.
        if (!$pertenceAoUtilizador && !$eAdministrador) {
            responderJson([
                'erro' => 'Artigo não encontrado.'
            ], 404);
        }

        responderJson([
            'estado' => 'ok',
            'artigo' => $artigo
        ]);
    }
}