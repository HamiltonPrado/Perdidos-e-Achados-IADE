# Deve ser chamada dentro de uma transação,
# depois de bloquear e validar os dois artigos.
def guardar_correspondencia(
    ligacao,
    perdido,
    achado,
    score_texto,
    modelo_ia,
):
    dados = {
        "perdido_id": perdido["id"],
        "achado_id": achado["id"],
        "versao_perdido": perdido["versao"],
        "versao_achado": achado["versao"],
        "score_texto": score_texto,
        "modelo_ia": modelo_ia,
    }

    with ligacao.cursor() as cursor:
        cursor.execute(
            """
            SELECT
                id,
                estado,
                versao_perdido,
                versao_achado,
                modelo_ia
            FROM matches
            WHERE item_id = %(perdido_id)s
              AND matched_item_id = %(achado_id)s
            FOR UPDATE
            """,
            dados,
        )

        existente = cursor.fetchone()

        # Preserva o registo associado a uma entrega concluída.
        if existente is not None:
            if existente["estado"] == "concluido":
                return None

        if existente is None:
            cursor.execute(
                """
                INSERT INTO matches (
                    item_id,
                    matched_item_id,
                    score,
                    score_texto,
                    score_imagem,
                    modelo_ia,
                    versao_perdido,
                    versao_achado,
                    estado
                ) VALUES (
                    %(perdido_id)s,
                    %(achado_id)s,
                    %(score_texto)s,
                    %(score_texto)s,
                    NULL,
                    %(modelo_ia)s,
                    %(versao_perdido)s,
                    %(versao_achado)s,
                    'sugerido'
                )
                """,
                dados,
            )

            return {
                "id": cursor.lastrowid,
                "renotificar": True,
            }

        # Uma nova versão ou modelo pode justificar um novo alerta.
        renotificar = (
            existente["estado"] != "sugerido"
            or existente["versao_perdido"] != perdido["versao"]
            or existente["versao_achado"] != achado["versao"]
            or existente["modelo_ia"] != modelo_ia
        )

        dados["id"] = existente["id"]

        cursor.execute(
            """
            UPDATE matches
            SET score = %(score_texto)s,
                score_texto = %(score_texto)s,
                score_imagem = NULL,
                modelo_ia = %(modelo_ia)s,
                versao_perdido = %(versao_perdido)s,
                versao_achado = %(versao_achado)s,
                estado = 'sugerido'
            WHERE id = %(id)s
              AND estado <> 'concluido'
            """,
            dados,
        )

        return {
            "id": existente["id"],
            "renotificar": renotificar,
        }

    # Cria ou atualiza a notificação da correspondência.
# Deve usar a mesma transação que guarda a correspondência.
def guardar_notificacao(
    ligacao,
    correspondencia,
    perdido,
    achado,
):
    dados = {
        "match_id": correspondencia["id"],
        "user_id": perdido["user_id"],
        "item_id": perdido["id"],
        "ponto_recolha_id": achado["ponto_recolha_id"],
        "renotificar": int(correspondencia["renotificar"]),
    }

    with ligacao.cursor() as cursor:
        cursor.execute(
            """
            INSERT INTO notifications (
                match_id,
                user_id,
                item_id,
                ponto_recolha_id
            ) VALUES (
                %(match_id)s,
                %(user_id)s,
                %(item_id)s,
                %(ponto_recolha_id)s
            )
            ON DUPLICATE KEY UPDATE
                lida = IF(
                    %(renotificar)s OR invalidada_em IS NOT NULL,
                    0,
                    lida
                ),
                lida_em = IF(
                    %(renotificar)s OR invalidada_em IS NOT NULL,
                    NULL,
                    lida_em
                ),
                ponto_recolha_id = %(ponto_recolha_id)s,
                invalidada_em = NULL
            """,
            dados,
        )

# Valida o artigo e bloqueia a reserva para a gravação dos resultados.
# Deve ser chamada na mesma transação, depois de bloquear_artigos().
def validar_reserva(
    ligacao,
    trabalho,
    artigo,
    artigos_bloqueados,
):
    atual = artigos_bloqueados.get(artigo["id"])

    if (
        atual is None
        or atual["estado"] != "ativo"
        or atual["versao"] != artigo["versao"]
        or artigo["id"] != trabalho["item_id"]
        or artigo["versao"] != trabalho["versao_item"]
    ):
        return False

    with ligacao.cursor() as cursor:
        cursor.execute(
            """
            SELECT id
            FROM ia_jobs
            WHERE id = %s
              AND item_id = %s
              AND versao_item = %s
              AND token_reserva = %s
              AND estado = 'em_processamento'
              AND reservado_ate > CURRENT_TIMESTAMP
            FOR UPDATE
            """,
            (
                trabalho["id"],
                trabalho["item_id"],
                trabalho["versao_item"],
                trabalho["token_reserva"],
            ),
        )

        return cursor.fetchone() is not None
    
    # Bloqueia os artigos por ordem de ID durante a gravação dos resultados.
# Deve ser chamada dentro de uma transação.
def bloquear_artigos(ligacao, artigo, resultados):
    ids = {int(artigo["id"])}

    for resultado in resultados:
        ids.add(int(resultado["id"]))

    artigos_bloqueados = {}

    with ligacao.cursor() as cursor:
        for item_id in sorted(ids):
            cursor.execute(
                """
                SELECT
                    id,
                    user_id,
                    tipo,
                    estado,
                    ponto_recolha_id,
                    versao
                FROM items
                WHERE id = %s
                FOR UPDATE
                """,
                (item_id,),
            )

            atual = cursor.fetchone()

            if atual is not None:
                artigos_bloqueados[atual["id"]] = atual

    return artigos_bloqueados