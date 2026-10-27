# Junta o título e a descrição para a comparação.
def texto_do_artigo(artigo):
    titulo = artigo["titulo"].strip()
    descricao = (artigo["descricao"] or "").strip()

    return f"{titulo}\n{descricao}"


# Ordena os candidatos pela semelhança com o artigo analisado.
def ordenar_candidatos(artigo, candidatos, comparador):
    if not candidatos:
        return []

    textos = [texto_do_artigo(artigo)]

    for candidato in candidatos:
        textos.append(texto_do_artigo(candidato))

    # Calcula todos os vetores numa chamada ao modelo.
    vetores = comparador.vetorizar(textos)

    resultados = []

    for indice, candidato in enumerate(candidatos, start=1):
        pontuacao = float(vetores[0] @ vetores[indice])

        resultado = dict(candidato)
        resultado["score_texto"] = max(
            0.0,
            min(1.0, pontuacao),
        )

        resultados.append(resultado)

    return sorted(
        resultados,
        key=lambda resultado: resultado["score_texto"],
        reverse=True,
    )