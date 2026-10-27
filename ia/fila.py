import secrets

from database import obter_ligacao
from artigos import carregar_artigo
from artigos import carregar_artigo, listar_candidatos

DURACAO_RESERVA = 60


# Reserva um trabalho pendente ou recupera uma reserva expirada.
def reservar_trabalho():
    ligacao = obter_ligacao()

    try:
        ligacao.begin()

        with ligacao.cursor() as cursor:
            cursor.execute(
                """
                SELECT id, item_id, versao_item, tentativas
                FROM ia_jobs
                WHERE disponivel_em <= CURRENT_TIMESTAMP
                  AND (
                      estado = 'pendente'
                      OR (
                          estado = 'em_processamento'
                          AND reservado_ate <= CURRENT_TIMESTAMP
                      )
                  )
                ORDER BY disponivel_em ASC, id ASC
                LIMIT 1
                FOR UPDATE
                """
            )

            trabalho = cursor.fetchone()

            if trabalho is None:
                ligacao.rollback()
                return None

            # Identifica esta reserva específica.
            token = secrets.token_hex(32)

            cursor.execute(
                """
                UPDATE ia_jobs
                SET estado = 'em_processamento',
                    tentativas = tentativas + 1,
                    token_reserva = %s,
                    reservado_ate = TIMESTAMPADD(
                        SECOND,
                        %s,
                        CURRENT_TIMESTAMP
                    )
                WHERE id = %s
                """,
                (
                    token,
                    DURACAO_RESERVA,
                    trabalho["id"],
                ),
            )

        ligacao.commit()

        trabalho["token_reserva"] = token
        trabalho["tentativas"] += 1

        return trabalho

    except Exception:
        ligacao.rollback()
        raise

    finally:
        ligacao.close()


# Renova apenas uma reserva que ainda esteja válida.
def renovar_reserva(trabalho):
    ligacao = obter_ligacao()

    try:
        with ligacao.cursor() as cursor:
            cursor.execute(
                """
                UPDATE ia_jobs
                SET reservado_ate = TIMESTAMPADD(
                    SECOND,
                    %s,
                    CURRENT_TIMESTAMP
                )
                WHERE id = %s
                  AND item_id = %s
                  AND versao_item = %s
                  AND token_reserva = %s
                  AND estado = 'em_processamento'
                  AND reservado_ate > CURRENT_TIMESTAMP
                """,
                (
                    DURACAO_RESERVA,
                    trabalho["id"],
                    trabalho["item_id"],
                    trabalho["versao_item"],
                    trabalho["token_reserva"],
                ),
            )

            return cursor.rowcount == 1

    finally:
        ligacao.close()


# Agenda uma nova tentativa ou termina o trabalho com erro.
def registar_falha(trabalho, mensagem):
    ligacao = obter_ligacao()

    try:
        ligacao.begin()

        with ligacao.cursor() as cursor:
            # Mantém a ordem dos bloqueios: artigo primeiro, trabalho depois.
            cursor.execute(
                """
                SELECT id, versao, estado
                FROM items
                WHERE id = %s
                FOR UPDATE
                """,
                (trabalho["item_id"],),
            )

            artigo = cursor.fetchone()

            cursor.execute(
                """
                SELECT tentativas
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

            atual = cursor.fetchone()

            # Um trabalhador que perdeu a reserva não pode alterar o trabalho.
            if atual is None:
                ligacao.rollback()
                return False

            tentativas = atual["tentativas"]
            estado = "erro" if tentativas >= 3 else "pendente"
            atraso = min(60, 5 * (2 ** min(tentativas - 1, 4)))
            mensagem = str(mensagem)[:500]

            cursor.execute(
                """
                UPDATE ia_jobs
                SET estado = %s,
                    disponivel_em = TIMESTAMPADD(
                        SECOND, %s, CURRENT_TIMESTAMP
                    ),
                    reservado_ate = NULL,
                    token_reserva = NULL,
                    ultimo_erro = %s
                WHERE id = %s
                  AND token_reserva = %s
                  AND estado = 'em_processamento'
                  AND reservado_ate > CURRENT_TIMESTAMP
                """,
                (
                    estado,
                    atraso,
                    mensagem,
                    trabalho["id"],
                    trabalho["token_reserva"],
                ),
            )

            if cursor.rowcount != 1:
                ligacao.rollback()
                return False

            # Atualiza apenas a versão do artigo que este trabalho analisava.
            if (
                artigo is not None
                and artigo["estado"] == "ativo"
                and artigo["versao"] == trabalho["versao_item"]
            ):
                cursor.execute(
                    """
                    UPDATE items
                    SET ia_estado = %s,
                        ia_erro = %s
                    WHERE id = %s
                      AND versao = %s
                      AND estado = 'ativo'
                    """,
                    (
                        estado,
                        mensagem,
                        artigo["id"],
                        artigo["versao"],
                    ),
                )

        ligacao.commit()
        return True

    except Exception:
        ligacao.rollback()
        raise

    finally:
        ligacao.close()


        
# Executa uma reserva para testar a fila.
if __name__ == "__main__":
    from artigos import carregar_artigo, listar_candidatos
    from comparador_texto import ComparadorTexto, REVISAO_MODELO
    from recomendacoes import ordenar_candidatos
    from publicacao import publicar_resultados

    # Valor experimental: será ajustado com os nossos testes.
    LIMIAR_TEXTO = 0.75

    MODELO_IA = (
        f"texto-v1:minilm-multilingual-l12-v2@{REVISAO_MODELO}"
    )

    # Carrega o modelo antes de reservar um trabalho.
    print("A carregar o modelo…")
    comparador = ComparadorTexto()

    trabalho = reservar_trabalho()

    if trabalho is None:
        print("Não há trabalhos disponíveis neste momento.")

    else:
        artigo = carregar_artigo(trabalho)

        if artigo is None:
            print("O artigo já não está disponível para esta reserva.")

        else:
            candidatos = listar_candidatos(artigo)

            resultados = ordenar_candidatos(
                artigo,
                candidatos,
                comparador,
            )

            print("Artigo analisado:", artigo["id"], artigo["titulo"])

            for resultado in resultados:
                print(
                    f"ID {resultado['id']} | "
                    f"{resultado['titulo']} | "
                    f"Texto: {resultado['score_texto']:.3f}"
                )

            # Só publica se ainda conseguir renovar a reserva.
            if not renovar_reserva(trabalho):
                print("A reserva expirou. Os resultados não foram guardados.")

            else:
                total = publicar_resultados(
                    trabalho,
                    artigo,
                    resultados,
                    MODELO_IA,
                    LIMIAR_TEXTO,
                )

                if total is None:
                    print(
                        "A reserva ou os artigos mudaram. "
                        "Os resultados não foram guardados."
                    )

                else:
                    print("Correspondências publicadas:", total)
                    print("Trabalho concluído.")