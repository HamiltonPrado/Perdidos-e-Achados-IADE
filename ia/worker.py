# Trabalhador da IA (fase 1): trata os pedidos da fila ia_jobs e grava as sugestões em matches.
# No Docker é o serviço «ia» (docker compose logs -f ia). À mão: .venv-ai/bin/python worker.py

import logging
import os
import time
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from modelo import ID_MODELO, Modelo, texto_do_objeto

LIMIAR = 0.65  # nota mínima (0 a 1) para dois objetos serem «parecidos»

log = logging.getLogger("ia")

# Um objeto entra na comparação se estiver ativo e, sendo achado, já estiver na receção.
PODE_SER_COMPARADO = "estado = 'ativo' AND (tipo = 'lost' OR estado_recepcao = 'na_recepcao')"


# --- Base de dados ---------------------------------------------------------------------

# Configuração: variáveis de ambiente (Docker) ou o .env da raiz do projeto.
def ler_config():
    cfg = {}
    env = Path(__file__).resolve().parent.parent / ".env"
    if env.exists():
        for linha in env.read_text(encoding="utf-8").splitlines():
            if "=" in linha and not linha.startswith("#"):
                nome, valor = linha.split("=", 1)
                cfg[nome.strip()] = valor.strip()
    cfg.update(os.environ)
    return cfg


# Liga ao primeiro nó da lista que responder (no Docker: galera1, galera2, galera3).
def ligar():
    cfg = ler_config()
    for host in cfg.get("DB_HOST", "127.0.0.1").split(","):
        try:
            return pymysql.connect(
                host=host.strip(), port=int(cfg.get("DB_PORT", 3307)),
                user=cfg.get("DB_USER", "perdidos"), password=cfg["DB_PASSWORD"],
                database=cfg.get("DB_NAME", "perdidos_achados"),
                charset="utf8mb4", cursorclass=DictCursor, autocommit=True,
            )
        except pymysql.err.OperationalError as e:
            erro = e
    raise erro


def sql(bd, comando, valores=()):
    with bd.cursor() as cursor:
        cursor.execute(comando, valores)
        return cursor.fetchall()


# Liga (tentando de 5 em 5 s até conseguir) e devolve à fila os pedidos que ficaram a meio.
def ligar_quando_possivel():
    while True:
        try:
            bd = ligar()
            sql(bd, "UPDATE ia_jobs SET estado = 'pendente' WHERE estado = 'em_processamento'")
            return bd
        except Exception as erro:
            log.warning("Base de dados indisponível (%s). Nova tentativa em 5 s.", erro)
            time.sleep(5)


# --- Os 5 passos ---------------------------------------------------------------------

# 1. O pedido mais antigo da fila, marcado como «em_processamento».
def proximo_pedido(bd):
    pedidos = sql(bd, "SELECT id, item_id FROM ia_jobs WHERE estado = 'pendente' ORDER BY id LIMIT 1")
    if not pedidos:
        return None
    sql(bd, "UPDATE ia_jobs SET estado = 'em_processamento', tentativas = tentativas + 1 WHERE id = %s",
        (pedidos[0]["id"],))
    return pedidos[0]


# 2. O objeto do pedido e os candidatos do tipo contrário (perdido ↔ achados).
def ler_objeto(bd, item_id):
    objetos = sql(bd, f"SELECT id, tipo, titulo, descricao, versao FROM items WHERE id = %s AND {PODE_SER_COMPARADO}",
                  (item_id,))
    return objetos[0] if objetos else None


def ler_candidatos(bd, objeto):
    contrario = "found" if objeto["tipo"] == "lost" else "lost"
    return sql(bd, f"SELECT id, tipo, titulo, descricao, versao FROM items WHERE tipo = %s AND {PODE_SER_COMPARADO}",
               (contrario,))


# 3. O modelo dá uma nota a cada candidato; ficam os que chegam ao limiar.
def parecidos(modelo, objeto, candidatos):
    notas = modelo.notas(texto_do_objeto(objeto), [texto_do_objeto(c) for c in candidatos])
    return [(c, nota) for c, nota in zip(candidatos, notas) if nota >= LIMIAR]


# 4 e 5. Numa transação (ou fica tudo gravado, ou nada): troca as sugestões antigas deste
# objeto pelas novas e marca o pedido como concluído.
def gravar(bd, pedido, objeto, sugestoes):
    bd.begin()
    try:
        if objeto:
            sql(bd, "UPDATE matches SET estado = 'descartado' WHERE estado = 'sugerido' AND (item_id = %s OR matched_item_id = %s)",
                (objeto["id"], objeto["id"]))
            for candidato, nota in sugestoes:
                perdido, achado = (objeto, candidato) if objeto["tipo"] == "lost" else (candidato, objeto)
                # Cada par perdido ↔ achado só existe uma vez: se já existe, é atualizado.
                sql(bd, """
                    INSERT INTO matches (item_id, matched_item_id, score, score_texto, modelo_ia, versao_perdido, versao_achado)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE score = VALUES(score), score_texto = VALUES(score_texto),
                        modelo_ia = VALUES(modelo_ia), versao_perdido = VALUES(versao_perdido),
                        versao_achado = VALUES(versao_achado), estado = IF(estado = 'concluido', 'concluido', 'sugerido')""",
                    (perdido["id"], achado["id"], nota, nota, ID_MODELO, perdido["versao"], achado["versao"]))
            # Só fica «analisado» se o objeto não mudou entretanto (senão volta a ser analisado).
            sql(bd, "UPDATE items SET ia_estado = 'analisado', ia_erro = NULL WHERE id = %s AND versao = %s",
                (objeto["id"], objeto["versao"]))
        # Se o objeto mudou durante a análise, o site já pôs o pedido em «pendente»: não se toca.
        sql(bd, "UPDATE ia_jobs SET estado = 'concluido', ultimo_erro = NULL WHERE id = %s AND estado = 'em_processamento'",
            (pedido["id"],))
        bd.commit()
    except Exception:
        bd.rollback()
        raise


def marcar_erro(bd, pedido, erro):
    mensagem = f"{type(erro).__name__}: {erro}"[:500]
    sql(bd, "UPDATE ia_jobs SET estado = 'erro', ultimo_erro = %s WHERE id = %s", (mensagem, pedido["id"]))
    sql(bd, "UPDATE items SET ia_estado = 'erro', ia_erro = %s WHERE id = %s", (mensagem, pedido["item_id"]))


# --- O ciclo -------------------------------------------------------------------------

def main():
    log.info("A carregar o modelo…")
    modelo = Modelo()
    bd = ligar_quando_possivel()
    log.info("Pronto. À espera de pedidos…")

    while True:
        pedido = None
        try:
            pedido = proximo_pedido(bd)
            if pedido is None:
                time.sleep(3)
                continue
            log.info("Pedido %s (objeto %s)", pedido["id"], pedido["item_id"])
            objeto = ler_objeto(bd, pedido["item_id"])
            sugestoes = parecidos(modelo, objeto, ler_candidatos(bd, objeto)) if objeto else []
            gravar(bd, pedido, objeto, sugestoes)
            log.info("Pedido %s concluído: %s sugestão(ões)", pedido["id"], len(sugestoes))
        except pymysql.err.OperationalError as erro:  # a base de dados foi abaixo
            log.warning("Perdi a ligação à base de dados (%s).", erro)
            bd.close()
            bd = ligar_quando_possivel()
        except Exception as erro:  # erro na análise: o pedido fica em «erro» com o motivo
            log.exception("Erro ao tratar o pedido.")
            if pedido:
                marcar_erro(bd, pedido, erro)


if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s | %(message)s")
    try:
        main()
    except KeyboardInterrupt:
        log.info("Worker terminado.")
