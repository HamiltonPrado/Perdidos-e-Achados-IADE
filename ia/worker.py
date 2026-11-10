# =============================================================================
#  O «TRABALHADOR» DA IA  —  é este o ficheiro que se põe a correr
# =============================================================================
#
#  No Docker arranca sozinho com o resto (serviço «ia»):  docker compose logs -f ia
#  À mão, sem Docker (na pasta ia/):  .venv-ai/bin/python worker.py    Parar: Ctrl + C
#  (Com o serviço «ia» ligado, não lancem outro à mão: ver «cadeado» em base_dados.py.)
#
#  Quando alguém regista um objeto perdido, ou a receção confirma que recebeu um achado,
#  o site deixa um pedido de análise numa fila (a tabela ia_jobs). Este programa fica
#  sempre ligado e trata esses pedidos, um de cada vez, em 5 passos:
#
#    1. pega no pedido mais antigo da fila;
#    2. lê o objeto e os candidatos do tipo contrário (perdido ↔ achados na receção);
#    3. o modelo dá uma nota de 0 a 1 a cada candidato (semelhança do título + descrição);
#    4. grava como sugestão os que têm nota ≥ LIMIAR e avisa o dono do objeto perdido;
#    5. marca o pedido como concluído. Se algo falhar, tenta outra vez (até 3 vezes).
#
#  Os outros dois ficheiros:
#    modelo.py       → o modelo de IA (transforma texto em notas de semelhança)
#    base_dados.py   → tudo o que se lê e grava na base de dados
# =============================================================================

import logging
import time

from base_dados import (apanhar_cadeado, concluir_sem_analise, devolver_pedidos_a_meio,
                        gravar_resultado, ler_candidatos, ler_objeto, ligar, proximo_pedido,
                        registar_falha)
from modelo import ID_MODELO, Modelo, texto_do_objeto

# Nota mínima para dois objetos contarem como «parecidos» (0 = nada a ver, 1 = iguais).
# Afinada com testes: com 0,75 ficavam de fora pares óbvios (~0,67) e com 0,6 apareciam
# objetos diferentes só por terem a mesma cor (~0,64).
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


# Liga à base de dados e fica com o «cadeado» (só pode haver um worker ligado).
# Se a base de dados não responder, tenta de 5 em 5 segundos até conseguir.
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
