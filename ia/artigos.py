from database import obter_ligacao


# Obtém o artigo apenas se o trabalho ainda puder ser processado.
def carregar_artigo(trabalho):
    ligacao = obter_ligacao()

    try:
        with ligacao.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    i.id,
                    i.user_id,
                    i.tipo,
                    i.titulo,
                    i.descricao,
                    i.category_id,
                    i.location_id,
                    i.ponto_recolha_id,
                    i.data_ocorrencia,
                    i.versao
                FROM items AS i
                INNER JOIN ia_jobs AS j ON j.item_id = i.id
                WHERE j.id = %s
                  AND j.item_id = %s
                  AND j.token_reserva = %s
                  AND j.estado = 'em_processamento'
                  AND j.reservado_ate > CURRENT_TIMESTAMP
                  AND j.versao_item = %s
                                   AND i.versao = j.versao_item
                  AND i.estado = 'ativo'
                  AND (
                      i.tipo = 'lost'
                      OR i.estado_recepcao = 'na_recepcao'
                  )
                               """,
                (
                    trabalho["id"],
                    trabalho["item_id"],
                    trabalho["token_reserva"],
                    trabalho["versao_item"],
                ),
            )

            return cursor.fetchone()

    finally:
        ligacao.close()

        # Lista os artigos ativos que podem corresponder ao artigo analisado.
def listar_candidatos(artigo):
    tipo_oposto = "found" if artigo["tipo"] == "lost" else "lost"

    ligacao = obter_ligacao()

    try:
        with ligacao.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    id,
                    user_id,
                    tipo,
                    titulo,
                    descricao,
                    category_id,
                    location_id,
                    ponto_recolha_id,
                    data_ocorrencia,
                    versao
                FROM items
                               WHERE tipo = %s
                  AND estado = 'ativo'
                  AND (
                      tipo = 'lost'
                      OR estado_recepcao = 'na_recepcao'
                  )
                ORDER BY id ASC
                """,
                (tipo_oposto,),
            )

            return cursor.fetchall()

    finally:
        ligacao.close()