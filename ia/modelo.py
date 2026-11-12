# Modelo de IA (paraphrase-multilingual-MiniLM-L12-v2): transforma texto em vetores e compara-os (nota de 0 a 1).
# Testar sem base de dados: .venv-ai/bin/python modelo.py

from sentence_transformers import SentenceTransformer

NOME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"

# Versão fixa do modelo, para as notas não mudarem com atualizações.
REVISAO = "e8f8c211226b894fcb81acc59f3b34ba3efd5f42"

# Nome gravado em cada sugestão, para se saber que modelo a encontrou.
ID_MODELO = f"texto-v1:minilm-multilingual-l12-v2@{REVISAO}"

# Nota mínima (0 a 1) para dois objetos serem «parecidos». Escolhida com testes.
LIMIAR = 0.65


# Texto comparado: título + descrição.
def texto_do_objeto(objeto):
    return f"{objeto['titulo'].strip()}\n{(objeto['descricao'] or '').strip()}"


class Modelo:
    def __init__(self):
        # Carrega o modelo uma vez; sem código remoto, só pesos safetensors.
        self.modelo = SentenceTransformer(
            NOME,
            revision=REVISAO,
            device="cpu",
            trust_remote_code=False,
            model_kwargs={"use_safetensors": True},
        )

    # Nota (0 a 1) do texto contra cada um dos outros, numa só chamada ao modelo.
    def notas(self, texto, outros):
        if not outros:
            return []

        impressoes = self.modelo.encode(
            [texto] + outros,
            normalize_embeddings=True,   # deixa todas do mesmo tamanho → nota entre 0 e 1
            convert_to_numpy=True,
            show_progress_bar=False,
        )

        # Vetores normalizados: o produto interno é a semelhança do cosseno (negativos contam como 0).
        return [max(0.0, min(1.0, float(impressoes[0] @ outra))) for outra in impressoes[1:]]


# Exemplo: a carteira parecida deve dar ~0,79 (parecido) e o guarda-chuva ~0,26 (diferente).
if __name__ == "__main__":
    print("A carregar o modelo…")
    modelo = Modelo()

    perdido = "Perdi uma carteira preta com fecho vermelho na biblioteca."
    achados = [
        "Foi encontrada na biblioteca uma carteira preta com um pequeno fecho vermelho.",
        "Foi encontrado um guarda-chuva azul na cantina.",
    ]

    for achado, nota in zip(achados, modelo.notas(perdido, achados)):
        decisao = "parecido" if nota >= LIMIAR else "diferente"
        print(f"{nota:.3f}  {decisao:<9}  {achado}")
