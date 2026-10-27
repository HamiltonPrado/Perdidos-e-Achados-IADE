import math

from database import obter_ligacao
from persistencia import (
    bloquear_artigos,
    validar_reserva,
    guardar_correspondencia,
    guardar_notificacao,
)


# Publica os resultados apenas se a reserva e as versões continuarem válidas.
def publicar_resultados(
    trabalho,
    artigo,
    resultados,
    modelo_ia,
    limiar_texto,
):
    if not 0 <= limiar_texto <= 1:
        raise ValueError("O limiar deve estar entre 0 e 1.")

    if not modelo_ia or len(modelo_ia) > 100:
        raise ValueError("O identificador do modelo é inválido.")

    ligacao = obter_ligacao()

    try:
        ligacao.begin()

        artigos = bloquear_artigos(ligacao, artigo, resultados)

        if not validar_reserva(ligacao, trabalho, artigo, artigos):
            ligacao.rollback()
            return None

        atual = artigos[artigo["id"]]
        tipo_oposto = "found" if atual["tipo"] == "lost" else "lost"

        # Cancela a publicação se algum candidato mudou durante a análise.
        for resultado in resultados:
            candidato = artigos.get(resultado["id"])

            if (
                candidato is None
                or candidato["estado"] != "ativo"
                or candidato["tipo"] != tipo_oposto
                or candidato["versao"] != resultado["versao"]
            ):
                ligacao.rollback()
                return None

        ids_guardados = set()

        for resultado in resultados:
            score = float(resultado["score_texto"])

            if not math.isfinite(score) or not 0 <= score <= 1:
                raise ValueError("A IA devolveu uma pontuação inválida.")

            if score < limiar_texto:
                continue

            candidato = artigos[resultado["id"]]

            if atual["tipo"] == "lost":
                perdido, achado = atual, candidato
            else:
                perdido, achado = candidato, atual

            if achado["ponto_recolha_id"] is None:
                raise ValueError("O achado não tem ponto de recolha.")

            correspondencia = guardar_correspondencia(
                ligacao,
                perdido,
                achado,
                score,
                modelo_ia,
            )

            # Uma correspondência já concluída não volta a ser sugerida.
            if correspondencia is None:
                continue

            guardar_notificacao(
                ligacao,
                correspondencia,
                perdido,
                achado,
            )

            ids_guardados.add(correspondencia["id"])

        with ligacao.cursor() as cursor:
            # Obtém as sugestões deste artigo, incluindo as anteriores.
            cursor.execute(
                """
                SELECT id
                FROM matches
                WHERE estado = 'sugerido'
                  AND (item_id = %s OR matched_item_id = %s)
                ORDER BY id
                FOR UPDATE
                """,
                (atual["id"], atual["id"]),
            )

            sugestoes = cursor.fetchall()

            # Descarta sugestões que já não fazem parte dos resultados.
            for sugestao in sugestoes:
                if sugestao["id"] in ids_guardados:
                    continue

                cursor.execute(
                    """
                    UPDATE matches
                    SET estado = 'descartado'
                    WHERE id = %s
                      AND estado = 'sugerido'
                    """,
                    (sugestao["id"],),
                )

                cursor.execute(
                    """
                    UPDATE notifications
                    SET invalidada_em = CURRENT_TIMESTAMP
                    WHERE match_id = %s
                      AND invalidada_em IS NULL
                    """,
                    (sugestao["id"],),
                )

            # Confirma novamente a reserva antes de concluir o trabalho.
            cursor.execute(
                """
                UPDATE ia_jobs
                SET estado = 'concluido',
                    reservado_ate = NULL,
                    token_reserva = NULL,
                    ultimo_erro = NULL
                WHERE id = %s
                  AND item_id = %s
                  AND versao_item = %s
                  AND token_reserva = %s
                  AND estado = 'em_processamento'
                  AND reservado_ate > CURRENT_TIMESTAMP
                """,
                (
                    trabalho["id"],
                    atual["id"],
                    atual["versao"],
                    trabalho["token_reserva"],
                ),
            )

            if cursor.rowcount != 1:
                ligacao.rollback()
                return None

            cursor.execute(
                """
                UPDATE items
                SET ia_estado = 'analisado',
                    ia_erro = NULL
                WHERE id = %s
                  AND versao = %s
                  AND estado = 'ativo'
                """,
                (atual["id"], atual["versao"]),
            )

            if cursor.rowcount != 1:
                ligacao.rollback()
                return None

        ligacao.commit()

        return len(ids_guardados)

    except Exception:
        ligacao.rollback()
        raise

    finally:
        ligacao.close()