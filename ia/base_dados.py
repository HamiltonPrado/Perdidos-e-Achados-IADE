# Leituras e gravações da IA na base de dados (ia_jobs, items, matches, notifications).
# Usa o .env da raiz. Testar a ligação: .venv-ai/bin/python base_dados.py

import os
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

FICHEIRO_ENV = Path(__file__).resolve().parent.parent / ".env"
NOS = [("127.0.0.1", 3307), ("127.0.0.1", 3308), ("127.0.0.1", 3309)]


# Lê o .env (linhas NOME=valor). As variáveis de ambiente têm prioridade.
def configuracao():
    valores = {}
    if FICHEIRO_ENV.exists():
        for linha in FICHEIRO_ENV.read_text(encoding="utf-8").splitlines():
            linha = linha.strip()
            if linha and not linha.startswith("#") and "=" in linha:
                nome, valor = linha.split("=", 1)
                valores[nome.strip()] = valor.strip().strip("'\"")
    valores.update({k: v for k, v in os.environ.items() if k.startswith("DB_")})
    return valores


# Liga ao primeiro nó que responder (DB_HOST pode ser "galera1,galera2,galera3").
def ligar():
    cfg = configuracao()
    if not cfg.get("DB_PASSWORD"):
        raise RuntimeError(f"Falta DB_PASSWORD: cria o {FICHEIRO_ENV} (cp .env.example .env).")

    porta = int(cfg.get("DB_PORT", 3306))
    nos = [(h.strip(), porta) for h in cfg.get("DB_HOST", "").split(",") if h.strip()] or NOS
    erro = None
    for host, porta in nos:
        try:
            return pymysql.connect(
                host=host, port=porta,
                user=cfg.get("DB_USER", "perdidos"),
                password=cfg["DB_PASSWORD"],
                database=cfg.get("DB_NAME", "perdidos_achados"),
                charset="utf8mb4", cursorclass=DictCursor, autocommit=True,
                connect_timeout=3, read_timeout=30, write_timeout=30,
            )
        except pymysql.err.OperationalError as e:
            erro = e
    raise erro


def consultar(bd, sql, valores=()):
    with bd.cursor() as cursor:
        cursor.execute(sql, valores)
        return cursor.fetchall()


def executar(bd, sql, valores=()):
    with bd.cursor() as cursor:
        return cursor.execute(sql, valores)   # devolve quantas linhas mudaram


# --- A fila ------------------------------------------------------------------

# Cadeado para haver só um worker. Só vale no nó onde foi pedido; dois workers em nós
# diferentes apenas repetem trabalho (sugestões e avisos são únicos por par).
def apanhar_cadeado(bd):
    return consultar(bd, "SELECT GET_LOCK('perdidos_achados_ia', 0) AS ok")[0]["ok"] == 1


# Pedidos que ficaram a meio voltam à fila (seguro: só há um worker).
def devolver_pedidos_a_meio(bd):
    executar(bd, "UPDATE ia_jobs SET estado = 'pendente' WHERE estado = 'em_processamento'")


# O pedido mais antigo que está à espera (ou None), já marcado como «em_processamento».
def proximo_pedido(bd):
    pedidos = consultar(bd, """
        SELECT id, item_id, versao_item, tentativas FROM ia_jobs
        WHERE estado = 'pendente' AND disponivel_em <= NOW()
        ORDER BY disponivel_em, id LIMIT 1""")
    if not pedidos:
        return None

    pedido = pedidos[0]
    pedido["tentativas"] += 1
    executar(bd, "UPDATE ia_jobs SET estado = 'em_processamento', tentativas = %s WHERE id = %s",
             (pedido["tentativas"], pedido["id"]))
    return pedido


# O objeto já não precisa de análise (foi devolvido, resolvido…): o pedido fica concluído.
def concluir_sem_analise(bd, pedido):
    executar(bd, """
        UPDATE ia_jobs SET estado = 'concluido', ultimo_erro = NULL
        WHERE id = %s AND versao_item = %s AND estado = 'em_processamento'""",
             (pedido["id"], pedido["versao_item"]))


# Falhou: nova tentativa daqui a 10 s × tentativa; à 3.ª fica «erro».
def registar_falha(bd, pedido, mensagem):
    estado = "erro" if pedido["tentativas"] >= 3 else "pendente"
    mudou = executar(bd, """
        UPDATE ia_jobs SET estado = %s, ultimo_erro = %s,
               disponivel_em = NOW() + INTERVAL %s SECOND
        WHERE id = %s AND versao_item = %s AND estado = 'em_processamento'""",
             (estado, mensagem[:500], 10 * pedido["tentativas"], pedido["id"], pedido["versao_item"]))
    # Só marca o objeto se o pedido ainda estava em tratamento.
    if mudou:
        executar(bd, "UPDATE items SET ia_estado = %s, ia_erro = %s WHERE id = %s AND versao = %s AND estado = 'ativo'",
                 (estado, mensagem[:500], pedido["item_id"], pedido["versao_item"]))


# --- Os objetos --------------------------------------------------------------

# Pode ser comparado: ativo e, se for achado, já na receção.
PODE_SER_COMPARADO = """
    estado = 'ativo'
    AND (tipo = 'lost' OR (estado_recepcao = 'na_recepcao' AND ponto_recolha_id IS NOT NULL))"""

CAMPOS = "id, user_id, tipo, titulo, descricao, ponto_recolha_id, versao"


# O objeto do pedido, ou None se já não fizer sentido analisá-lo (foi devolvido, etc.).
def ler_objeto(bd, item_id):
    objetos = consultar(bd, f"SELECT {CAMPOS} FROM items WHERE id = %s AND {PODE_SER_COMPARADO}", (item_id,))
    return objetos[0] if objetos else None


# Os candidatos: objetos do tipo contrário. Perdido → achados; achado → perdidos.
def ler_candidatos(bd, objeto):
    tipo_contrario = "found" if objeto["tipo"] == "lost" else "lost"
    return consultar(bd, f"SELECT {CAMPOS} FROM items WHERE tipo = %s AND {PODE_SER_COMPARADO} ORDER BY id",
                     (tipo_contrario,))


# --- Gravar o resultado ------------------------------------------------------

# Numa transação: grava sugestões e avisos, descarta as antigas e conclui o pedido.
def gravar_resultado(bd, pedido, objeto, parecidos, id_modelo):
    bd.begin()
    try:
        guardadas = [guardar_sugestao(bd, objeto, candidato, nota, id_modelo) for candidato, nota in parecidos]

        for antiga in consultar(bd, """
                SELECT id FROM matches
                WHERE estado = 'sugerido' AND (item_id = %s OR matched_item_id = %s)""",
                (objeto["id"], objeto["id"])):
            if antiga["id"] not in guardadas:
                executar(bd, "UPDATE matches SET estado = 'descartado' WHERE id = %s", (antiga["id"],))
                executar(bd, "UPDATE notifications SET invalidada_em = NOW() WHERE match_id = %s AND invalidada_em IS NULL",
                         (antiga["id"],))

        # Se o objeto mudou durante a análise, o pedido já voltou a «pendente» e não é concluído aqui.
        executar(bd, """
            UPDATE ia_jobs SET estado = 'concluido', ultimo_erro = NULL
            WHERE id = %s AND versao_item = %s AND estado = 'em_processamento'""",
                 (pedido["id"], pedido["versao_item"]))
        executar(bd, "UPDATE items SET ia_estado = 'analisado', ia_erro = NULL WHERE id = %s AND versao = %s",
                 (objeto["id"], objeto["versao"]))
        bd.commit()
    except Exception:
        bd.rollback()
        raise
    return len([g for g in guardadas if g])


# Grava ou atualiza a sugestão e o aviso; None se o par já levou a uma devolução.
def guardar_sugestao(bd, objeto, candidato, nota, id_modelo):
    perdido, achado = (objeto, candidato) if objeto["tipo"] == "lost" else (candidato, objeto)

    existente = consultar(bd, """
        SELECT id, estado, versao_perdido, versao_achado FROM matches
        WHERE item_id = %s AND matched_item_id = %s""", (perdido["id"], achado["id"]))
    existente = existente[0] if existente else None

    if existente and existente["estado"] == "concluido":
        return None

    valores = (nota, nota, id_modelo, perdido["versao"], achado["versao"])
    if existente:
        executar(bd, """
            UPDATE matches SET score = %s, score_texto = %s, modelo_ia = %s,
                   versao_perdido = %s, versao_achado = %s, estado = 'sugerido'
            WHERE id = %s""", valores + (existente["id"],))
        sugestao = existente["id"]
    else:
        with bd.cursor() as cursor:
            cursor.execute("""
                INSERT INTO matches (score, score_texto, modelo_ia, versao_perdido, versao_achado,
                                     item_id, matched_item_id, estado)
                VALUES (%s, %s, %s, %s, %s, %s, %s, 'sugerido')""", valores + (perdido["id"], achado["id"]))
            sugestao = cursor.lastrowid

    # O aviso volta a «não lido» só se a sugestão é nova, estava descartada ou algo mudou.
    avisar_de_novo = (
        existente is None
        or existente["estado"] != "sugerido"
        or existente["versao_perdido"] != perdido["versao"]
        or existente["versao_achado"] != achado["versao"]
    )
    executar(bd, """
        INSERT INTO notifications (match_id, user_id, item_id, ponto_recolha_id)
        VALUES (%(sugestao)s, %(dono)s, %(perdido)s, %(ponto)s)
        ON DUPLICATE KEY UPDATE
            lida = IF(%(de_novo)s OR invalidada_em IS NOT NULL, 0, lida),
            lida_em = IF(%(de_novo)s OR invalidada_em IS NOT NULL, NULL, lida_em),
            ponto_recolha_id = %(ponto)s,
            invalidada_em = NULL""",
             {"sugestao": sugestao, "dono": perdido["user_id"], "perdido": perdido["id"],
              "ponto": achado["ponto_recolha_id"], "de_novo": int(avisar_de_novo)})
    return sugestao


# Teste rápido da ligação.
if __name__ == "__main__":
    with ligar() as bd:
        pendentes = consultar(bd, "SELECT COUNT(*) AS n FROM ia_jobs WHERE estado = 'pendente'")[0]["n"]
        no = consultar(bd, "SELECT @@hostname AS no")[0]["no"]
        print(f"Ligação à base de dados estabelecida ({no}). Pedidos à espera: {pendentes}")
