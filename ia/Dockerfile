# Imagem da IA (serviço «ia»), com o modelo lá dentro. Depois de mudar ia/: docker compose up -d --build ia

FROM python:3.13-slim

ENV PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    HF_HOME=/opt/modelo

WORKDIR /app

# PyTorch só para CPU (a versão normal traz vários GB de bibliotecas NVIDIA).
RUN pip install --index-url https://download.pytorch.org/whl/cpu torch

COPY requirements.txt .
RUN pip install -r requirements.txt

# Descarrega o modelo (cerca de 470 MB) para dentro da imagem.
COPY modelo.py .
RUN python -c "from modelo import Modelo; Modelo()" \
    && chmod -R a+rX /opt/modelo

COPY *.py ./

# A partir daqui o modelo vem da imagem, nunca da internet.
ENV HF_HUB_OFFLINE=1

# Não corre como administrador do contentor.
RUN useradd --create-home ia
USER ia

CMD ["python", "worker.py"]
