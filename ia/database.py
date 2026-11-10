import os
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor
from pymysql.constants import CLIENT


# O .env da raiz do projeto é o mesmo que o docker-compose.yml usa: utilizador, palavra-passe e
# nome da base de dados ficam num só sítio.
FICHEIRO_ENV = Path(__file__).resolve().parent.parent / ".env"

# Os 3 nós Galera, pelas portas publicadas no docker-compose.yml. Se um estiver em baixo,
# a ligação segue para o próximo.
NOS_POR_OMISSAO = "127.0.0.1:3307,127.0.0.1:3308,127.0.0.1:3309"


def ler_env(caminho):
    valores = {}

    try:
        linhas = caminho.read_text(encoding="utf-8").splitlines()
    except FileNotFoundError:
        return valores

    for linha in linhas:
        linha = linha.strip()

        if not linha or linha.startswith("#") or "=" not in linha:
            continue

        nome, valor = linha.split("=", 1)
        valores[nome.strip()] = valor.strip().strip("'\"")

    return valores


# As variáveis de ambiente têm prioridade sobre o .env.
#   DB_HOST + DB_PORT  → um servidor só (ex.: o MAMP, DB_HOST=127.0.0.1 DB_PORT=8889)
#   IA_DB_NOS          → lista host:porta alternativa aos 3 nós do Docker
def configuracao():
    env = ler_env(FICHEIRO_ENV)

    def valor(nome, omissao=None):
        return os.environ.get(nome) or env.get(nome) or omissao

    if valor("DB_HOST"):
        nos = [(valor("DB_HOST"), int(valor("DB_PORT", "3306")))]
    else:
        nos = [
            (no.rsplit(":", 1)[0], int(no.rsplit(":", 1)[1]))
            for no in valor("IA_DB_NOS", NOS_POR_OMISSAO).split(",")
            if no.strip()
        ]

    palavra_passe = valor("DB_PASSWORD")

    if not palavra_passe:
        raise RuntimeError(
            f"Falta DB_PASSWORD: cria o {FICHEIRO_ENV} (cp .env.example .env) "
            "ou define-a no ambiente."
        )

    return {
        "nos": nos,
        "utilizador": valor("DB_USER", "perdidos"),
        "palavra_passe": palavra_passe,
        "base_dados": valor("DB_NAME", "perdidos_achados"),
    }


# Abre uma ligação ao primeiro nó que responder.
def obter_ligacao():
    config = configuracao()
    ultimo_erro = None

    for host, porta in config["nos"]:
        try:
            return pymysql.connect(
                host=host,
                port=porta,
                user=config["utilizador"],
                password=config["palavra_passe"],
                database=config["base_dados"],
                charset="utf8mb4",
                cursorclass=DictCursor,
                client_flag=CLIENT.FOUND_ROWS,
                autocommit=True,
                connect_timeout=3,
                read_timeout=10,
                write_timeout=10,
            )
        except pymysql.err.OperationalError as erro:
            ultimo_erro = erro

    raise ultimo_erro


# Executado apenas quando iniciamos este ficheiro diretamente.
if __name__ == "__main__":
    ligacao = obter_ligacao()

    try:
        with ligacao.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(*) AS total
                FROM ia_jobs
                WHERE estado = 'pendente'
                """
            )

            resultado = cursor.fetchone()

            print("Ligação à base de dados estabelecida.")
            print("Trabalhos pendentes:", resultado["total"])

    finally:
        ligacao.close()