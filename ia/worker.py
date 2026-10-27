import logging
import threading
import time
from contextlib import contextmanager

from artigos import carregar_artigo, listar_candidatos
from comparador_texto import ComparadorTexto, REVISAO_MODELO
from fila import (
    reservar_trabalho,
    renovar_reserva,
    registar_falha,
)
from publicacao import publicar_resultados
from recomendacoes import ordenar_candidatos


# Valor experimental para os nossos testes.
LIMIAR_TEXTO = 0.75

MODELO_IA = (
    f"texto-v1:minilm-multilingual-l12-v2@{REVISAO_MODELO}"
)


# Renova a reserva em segundo plano enquanto a análise decorre.
@contextmanager
def manter_reserva(trabalho):
    parar = threading.Event()
    perdida = threading.Event()

    def renovar():
        while not parar.wait(15):
            try:
                if not renovar_reserva(trabalho):
                    perdida.set()
                    return

            except Exception:
                logging.exception("Não foi possível renovar a reserva.")
                perdida.set()
                return

    tarefa = threading.Thread(target=renovar, daemon=True)
    tarefa.start()

    try:
        yield perdida

    finally:
        parar.set()
        tarefa.join()


# Analisa e publica um trabalho reservado.
def processar_trabalho(comparador, trabalho):
    with manter_reserva(trabalho) as reserva_perdida:
        try:
            artigo = carregar_artigo(trabalho)

            if artigo is None:
                raise RuntimeError(
                    "O artigo ou a reserva já não estão disponíveis."
                )

            candidatos = listar_candidatos(artigo)

            resultados = ordenar_candidatos(
                artigo,
                candidatos,
                comparador,
            )

            if reserva_perdida.is_set():
                logging.warning(
                    "Trabalho %s: reserva perdida; resultados descartados.",
                    trabalho["id"],
                )
                return

            # Renova novamente antes de iniciar a publicação.
            if not renovar_reserva(trabalho):
                logging.warning(
                    "Trabalho %s: a reserva deixou de estar válida.",
                    trabalho["id"],
                )
                return

            total = publicar_resultados(
                trabalho,
                artigo,
                resultados,
                MODELO_IA,
                LIMIAR_TEXTO,
            )

            if total is None:
                registar_falha(
                    trabalho,
                    "A reserva ou os artigos mudaram durante a análise.",
                )

                logging.warning(
                    "Trabalho %s: os resultados não foram publicados.",
                    trabalho["id"],
                )
                return

            logging.info(
                "Trabalho %s concluído | artigo %s | correspondências: %s",
                trabalho["id"],
                artigo["id"],
                total,
            )

        except Exception as erro:
            logging.exception(
                "Erro ao processar o trabalho %s.",
                trabalho["id"],
            )

            if not reserva_perdida.is_set():
                try:
                    registar_falha(
                        trabalho,
                        f"{type(erro).__name__}: {erro}",
                    )

                except Exception:
                    logging.exception(
                        "Não foi possível registar a falha. "
                        "A reserva ficará disponível após expirar."
                    )


# Procura trabalhos continuamente, reutilizando o modelo carregado.
def executar():
    logging.info("A carregar o modelo…")
    comparador = ComparadorTexto()

    logging.info("Trabalhador iniciado. À espera de trabalhos…")

    while True:
        try:
            trabalho = reservar_trabalho()

        except Exception:
            logging.exception(
                "Não foi possível consultar a fila. "
                "Nova tentativa dentro de 5 segundos."
            )

            time.sleep(5)
            continue

        if trabalho is None:
            time.sleep(3)
            continue

        logging.info(
            "Trabalho %s reservado | tentativa %s",
            trabalho["id"],
            trabalho["tentativas"],
        )

        processar_trabalho(comparador, trabalho)


if __name__ == "__main__":
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s | %(levelname)s | %(message)s",
    )

    try:
        executar()

    except KeyboardInterrupt:
        logging.info("Trabalhador terminado.")