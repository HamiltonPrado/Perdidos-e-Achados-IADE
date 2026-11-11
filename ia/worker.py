# Trabalhador da IA: trata a fila ia_jobs, compara cada objeto com os do tipo contrário e grava as sugestões.
# No Docker é o serviço «ia» (docker compose logs -f ia). À mão: .venv-ai/bin/python worker.py

import logging
import time

from base_dados import (apanhar_cadeado, concluir_sem_analise, devolver_pedidos_a_meio,
                        gravar_resultado, ler_candidatos, ler_objeto, ligar, proximo_pedido,
                        registar_falha)
from modelo import ID_MODELO, Modelo, texto_do_objeto

# Nota mínima (0 a 1) para dois objetos serem «parecidos». Escolhida com testes.
LIMIAR = 0.65

log = logging.getLogger("ia")


# Passos 2 a 5 para um pedido. Devolve quantas sugestões foram gravadas.
def tratar_pedido(bd, modelo, pedido):
    objeto = ler_objeto(bd, pedido["item_id"])
    if objeto is None:                       # foi devolvido, resolvido, … nada a fazer
        concluir_sem_analise(bd, pedido)
        return 0

    candidatos = ler_candidatos(bd, objeto)
    notas = modelo.notas(texto_do_objeto(objeto), [texto_do_objeto(c) for c in candidatos])
    parecidos = [(c, nota) for c, nota in zip(candidatos, notas) if nota >= LIMIAR]

    return gravar_resultado(bd, pedido, objeto, parecidos, ID_MODELO)


# Liga à base de dados e apanha o cadeado (só um worker); se falhar, tenta de 5 em 5 s.
def ligar_ao_trabalho():
    while True:
        bd = None
        try:
            bd = ligar()
            tem_cadeado = apanhar_cadeado(bd)
            if tem_cadeado:
                devolver_pedidos_a_meio(bd)
                return bd
        except Exception as erro:
            # Inclui um nó que ainda está a sincronizar («WSREP has not yet prepared node»).
            log.warning("Base de dados indisponível (%s). Nova tentativa dentro de 5 s.", erro)
            if bd:
                bd.close()
            time.sleep(5)
            continue

        bd.close()
        raise SystemExit("Já está outro worker da IA ligado. Só pode haver um de cada vez.")


def main():
    log.info("A carregar o modelo…")
    modelo = Modelo()
    bd = ligar_ao_trabalho()
    log.info("Pronto. À espera de pedidos…")

    while True:
        try:
            pedido = proximo_pedido(bd)                                   # passo 1
        except Exception as erro:
            log.warning("Perdi a ligação à base de dados (%s). A voltar a ligar…", erro)
            bd.close()
            bd = ligar_ao_trabalho()
            continue

        if pedido is None:                   # fila vazia: espera um pouco e volta a ver
            time.sleep(3)
            continue

        log.info("Pedido %s (objeto %s), tentativa %s", pedido["id"], pedido["item_id"], pedido["tentativas"])
        try:
            total = tratar_pedido(bd, modelo, pedido)                     # passos 2 a 5
            log.info("Pedido %s concluído: %s sugestão(ões)", pedido["id"], total)
        except Exception as erro:
            log.exception("Pedido %s falhou.", pedido["id"])
            try:
                registar_falha(bd, pedido, f"{type(erro).__name__}: {erro}")
            except Exception:                # a ligação caiu: ao voltar, o pedido regressa à fila
                bd.close()
                bd = ligar_ao_trabalho()


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s | %(message)s")
    try:
        main()
    except KeyboardInterrupt:
        log.info("Worker terminado.")
