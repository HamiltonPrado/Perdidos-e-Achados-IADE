import json
import os
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor
from pymysql.constants import CLIENT


# Abre uma ligação usando a configuração do Docker ou a configuração local.
def obter_ligacao():
    if "DB_HOST" in os.environ:
        config = {
            "host": os.environ["DB_HOST"],
            "port": os.environ.get("DB_PORT", "3306"),
            "username": os.environ["DB_USER"],
            "password": os.environ["DB_PASSWORD"],
            "database": os.environ["DB_NAME"],
        }

    else:
        caminho = Path(__file__).resolve().parent / "config.local.json"

        with caminho.open(encoding="utf-8") as ficheiro:
            config = json.load(ficheiro)


    return pymysql.connect(
        host=config["host"],
        port=int(config["port"]),
        user=config["username"],
        password=config["password"],
        database=config["database"],
        charset="utf8mb4",
        cursorclass=DictCursor,
        client_flag=CLIENT.FOUND_ROWS,
        autocommit=True,
        connect_timeout=5,
        read_timeout=10,
        write_timeout=10,
    )


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