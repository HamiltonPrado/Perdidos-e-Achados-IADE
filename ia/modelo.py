# =============================================================================
#  O MODELO DE IA  —  dá uma nota de 0 a 1 à semelhança entre textos
# =============================================================================
#
#  Como funciona, sem matemática:
#    O modelo lê um texto (por ex. «Carteira preta com fecho vermelho») e transforma-o
#    numa lista de 384 números: uma espécie de «impressão digital» do SIGNIFICADO.
#    Textos que querem dizer o mesmo ficam com impressões parecidas, mesmo com palavras
#    diferentes («fecho» / «zíper») ou noutra língua. Comparando duas impressões obtém-se
#    uma nota de 0 (nada a ver) a 1 (iguais).
#
#  O modelo: paraphrase-multilingual-MiniLM-L12-v2 (biblioteca sentence-transformers).
#    Gratuito, percebe português e corre no próprio computador, sem placa gráfica.
#    Da primeira vez é descarregado da internet (cerca de 470 MB) e fica guardado.
#
#  Para experimentar sozinho, sem base de dados (na pasta ia/):
#    .venv-ai/bin/python modelo.py
# =============================================================================

from sentence_transformers import SentenceTransformer

NOME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"

# Versão exata do modelo: todos os computadores usam o mesmo e as notas não mudam
# de um dia para o outro por causa de uma atualização.
REVISAO = "e8f8c211226b894fcb81acc59f3b34ba3efd5f42"

# Nome gravado em cada sugestão, para se saber que modelo a encontrou.
ID_MODELO = f"texto-v1:minilm-multilingual-l12-v2@{REVISAO}"


# O texto que o modelo compara: título e descrição juntos.
# (A categoria, o local e a data não entram na comparação.)
def texto_do_objeto(objeto):
    return f"{objeto['titulo'].strip()}\n{(objeto['descricao'] or '').strip()}"


class Modelo:
    def __init__(self):
        # Carregar o modelo demora alguns segundos: faz-se uma vez, quando o worker arranca.
        # As duas opções de segurança impedem-no de correr código descarregado da internet.
        self.modelo = SentenceTransformer(
            NOME,
            revision=REVISAO,
            device="cpu",
            trust_remote_code=False,
            model_kwargs={"use_safetensors": True},
        )

    # Compara um texto com uma lista de outros e devolve uma nota (0 a 1) para cada um,
    # pela mesma ordem. Todos os textos vão ao modelo de uma vez, que é muito mais rápido.
    def notas(self, texto, outros):
        if not outros:
            return []

        impressoes = self.modelo.encode(
            [texto] + outros,
            normalize_embeddings=True,   # deixa todas do mesmo tamanho → nota entre 0 e 1
            convert_to_numpy=True,
            show_progress_bar=False,
        )

        # «@» multiplica as duas impressões número a número e soma tudo: quanto mais
        # parecidas, maior o resultado. Valores negativos (textos opostos) contam como 0.
        return [max(0.0, min(1.0, float(impressoes[0] @ outra))) for outra in impressoes[1:]]


# Exemplo para mostrar a IA a funcionar (por ex. numa apresentação).
# Esperado: a carteira parecida tem nota alta (~0,79) e o guarda-chuva baixa (~0,26).
if __name__ == "__main__":
    print("A carregar o modelo…")
    modelo = Modelo()

    perdido = "Perdi uma carteira preta com fecho vermelho na biblioteca."
    achados = [
        "Foi encontrada na biblioteca uma carteira preta com um pequeno fecho vermelho.",
        "Foi encontrado um guarda-chuva azul na cantina.",
    ]

    for achado, nota in zip(achados, modelo.notas(perdido, achados)):
        print(f"{nota:.3f}  {achado}")
