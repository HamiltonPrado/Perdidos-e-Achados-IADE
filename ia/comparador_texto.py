from sentence_transformers import SentenceTransformer


NOME_MODELO = (
    "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
)

REVISAO_MODELO = "e8f8c211226b894fcb81acc59f3b34ba3efd5f42"


class ComparadorTexto:
    def __init__(self):
        # Carrega o modelo uma vez por instância do comparador.
        self.modelo = SentenceTransformer(
            NOME_MODELO,
            revision=REVISAO_MODELO,
            device="cpu",
            trust_remote_code=False,
            model_kwargs={"use_safetensors": True},
        )

    # Converte os textos em vetores normalizados.
    def vetorizar(self, textos):
        return self.modelo.encode(
            textos,
            normalize_embeddings=True,
            convert_to_numpy=True,
            show_progress_bar=False,
        )

    # Calcula uma pontuação de semelhança entre 0 e 1.
    def comparar(self, texto_a, texto_b):
        vetores = self.vetorizar([texto_a, texto_b])

        pontuacao = float(vetores[0] @ vetores[1])

        return max(0.0, min(1.0, pontuacao))


if __name__ == "__main__":
    print("A carregar o modelo…")

    comparador = ComparadorTexto()

    perdido = (
        "Perdi uma carteira preta com fecho vermelho na biblioteca."
    )

    achado_parecido = (
        "Foi encontrada na biblioteca uma carteira preta "
        "com um pequeno fecho vermelho."
    )

    achado_diferente = (
        "Foi encontrado um guarda-chuva azul na cantina."
    )

    print(
        "Carteira parecida:",
        round(comparador.comparar(perdido, achado_parecido), 3),
    )

    print(
        "Objeto diferente:",
        round(comparador.comparar(perdido, achado_diferente), 3),
    )