"""Contentor «controlo»: painel de contentores do testar-api.html e árbitro do cluster Galera.

Só desenvolvimento: só vê este projeto, só age em CONTROLO_SERVICOS e só faz 4 ações
(parar, arrancar, crash, reiniciar), sempre com o cabeçalho X-Controlo: 1.
Árbitro: se 2 nós Galera caírem e o Docker confirmar que estão parados, reativa o cluster no que sobrou.
Rotas (em /controlo/): GET /estado · POST /acao {"servico", "acao"}
"""
import http.client
import json
import os
import socket
import struct
import threading
import time
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

SOCKET_DOCKER = "/var/run/docker.sock"
PERMITIDOS = [s.strip() for s in os.environ.get("CONTROLO_SERVICOS", "galera1,galera2,galera3,web,backup").split(",") if s.strip()]
NOS_GALERA = [s.strip() for s in os.environ.get("ARBITRO_NOS", "galera1,galera2,galera3").split(",") if s.strip()]
ARBITRO_ESPERA = int(os.environ.get("ARBITRO_ESPERA", "15"))
ARBITRO = {"ativo": bool(NOS_GALERA), "ultima_decisao": None}
ACOES = {
    "parar": "/stop?t=10",
    "arrancar": "/start",
    "crash": "/kill?signal=SIGKILL",
    "reiniciar": "/restart?t=10",
}
# Nestes serviços o «crash» termina o processo principal por dentro, como uma avaria real:
# assim o restart: unless-stopped volta a arrancá-lo (um docker kill conta como paragem manual).
CRASH_INTERNO = {"web"}


class LigacaoUnix(http.client.HTTPConnection):
    """Ligação HTTP à API do Docker através do socket Unix."""

    def __init__(self):
        super().__init__("localhost", timeout=30)

    def connect(self):
        self.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        self.sock.settimeout(30)
        self.sock.connect(SOCKET_DOCKER)


def docker(metodo, caminho, corpo=None, bruto=False):
    ligacao = LigacaoUnix()
    try:
        cabecalhos = {"Content-Type": "application/json"} if corpo is not None else {}
        ligacao.request(metodo, caminho, body=json.dumps(corpo) if corpo is not None else None, headers=cabecalhos)
        resposta = ligacao.getresponse()
        conteudo = resposta.read()
        if bruto:
            return resposta.status, conteudo
        dados = json.loads(conteudo) if conteudo[:1] in (b"[", b"{") else conteudo.decode(errors="replace")
        return resposta.status, dados
    finally:
        ligacao.close()


def projeto():
    _, eu = docker("GET", f"/containers/{socket.gethostname()}/json")
    return eu["Config"]["Labels"]["com.docker.compose.project"]


PROJETO = projeto()


def contentores():
    """Contentores do projeto (sem os de `docker compose run`), por serviço."""
    filtros = urllib.parse.quote(json.dumps({"label": [f"com.docker.compose.project={PROJETO}"]}))
    _, lista = docker("GET", f"/containers/json?all=1&filters={filtros}")
    resultado = {}
    for c in lista:
        etiquetas = c.get("Labels", {})
        if etiquetas.get("com.docker.compose.oneoff") == "True":
            continue
        servico = etiquetas.get("com.docker.compose.service")
        texto = c.get("Status", "")
        saude = next((s for s in ("unhealthy", "healthy", "health: starting") if s in texto), None)
        resultado[servico] = {
            "servico": servico,
            "id": c["Id"],
            "estado": c.get("State"),  # running, exited, restarting…
            "saude": {"health: starting": "starting"}.get(saude, saude),
            "descricao": texto,
            "controlavel": servico in PERMITIDOS,
        }
    return resultado


def executar_em(id_contentor, comando):
    """Corre um comando num contentor (docker exec) e devolve o que escreveu no stdout."""
    _, criado = docker("POST", f"/containers/{id_contentor}/exec",
                       {"AttachStdout": True, "AttachStderr": False, "Cmd": ["sh", "-c", comando]})
    _, fluxo = docker("POST", f"/exec/{criado['Id']}/start", {"Detach": False, "Tty": False}, bruto=True)
    saida, i = b"", 0
    while i + 8 <= len(fluxo):  # o Docker separa o fluxo em blocos com um cabeçalho de 8 bytes
        tamanho = struct.unpack(">I", fluxo[i + 4:i + 8])[0]
        saida += fluxo[i + 8:i + 8 + tamanho]
        i += 8 + tamanho
    return saida.decode(errors="replace")


SQL_ROOT = 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -u root -N -e '


def estado_galera(id_contentor):
    saida = executar_em(id_contentor, SQL_ROOT + "\"SHOW STATUS WHERE Variable_name IN ('wsrep_cluster_status', 'wsrep_last_committed')\"")
    valores = dict(linha.split("\t", 1) for linha in saida.strip().splitlines() if "\t" in linha)
    if "wsrep_cluster_status" not in valores:
        return None  # o MariaDB ainda não aceita ligações
    return {"primary": valores["wsrep_cluster_status"] == "Primary",
            "ultima": int(valores.get("wsrep_last_committed", "-1") or -1)}


def arbitro():
    sem_maioria_desde = None
    while True:
        time.sleep(5)
        try:
            todos = contentores()
            nos = {n: todos.get(n) for n in NOS_GALERA}
            ligados = {n: c for n, c in nos.items() if c and c["estado"] == "running"}
            parados = [n for n, c in nos.items() if c and c["estado"] in ("exited", "dead", "created")]
            estados = {n: estado_galera(c["id"]) for n, c in ligados.items()}

            # Só há razão para agir se NENHUM nó ligado está num cluster com maioria, todos os
            # ligados respondem, e todos os outros estão confirmadamente parados pelo Docker.
            if not ligados or any(e and e["primary"] for e in estados.values()) \
               or any(e is None for e in estados.values()) \
               or len(ligados) + len(parados) != len(NOS_GALERA):
                sem_maioria_desde = None
                continue

            sem_maioria_desde = sem_maioria_desde or time.time()
            if time.time() - sem_maioria_desde < ARBITRO_ESPERA:
                continue

            escolhido = max(estados, key=lambda n: estados[n]["ultima"])
            # No MariaDB 11 cada opção do Galera é uma variável própria (wsrep_provider_pc_bootstrap);
            # nas versões antigas muda-se por wsrep_provider_options. Tenta a nova e, se falhar, a antiga.
            executar_em(ligados[escolhido]["id"],
                        SQL_ROOT + "\"SET GLOBAL wsrep_provider_pc_bootstrap = ON\" 2>/dev/null || "
                        + SQL_ROOT + "\"SET GLOBAL wsrep_provider_options = 'pc.bootstrap=YES'\"")
            time.sleep(3)
            if not (estado_galera(ligados[escolhido]["id"]) or {}).get("primary"):
                print(f"[arbitro] tentei reativar o cluster no {escolhido}, mas continua sem maioria.", flush=True)
                continue
            mensagem = (f"{', '.join(parados)} parado(s) (confirmado pelo Docker) e sem maioria há "
                        f"{ARBITRO_ESPERA} s: cluster reativado no {escolhido} (transação {estados[escolhido]['ultima']}).")
            ARBITRO["ultima_decisao"] = {"hora": time.strftime("%H:%M:%S"), "mensagem": mensagem}
            print(f"[arbitro] {mensagem}", flush=True)
            sem_maioria_desde = None
        except Exception as erro:  # o árbitro nunca pode parar o servidor
            print(f"[arbitro] erro: {erro}", flush=True)


class Pedido(BaseHTTPRequestHandler):
    def responder(self, codigo, dados):
        corpo = json.dumps(dados, ensure_ascii=False).encode()
        self.send_response(codigo)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(corpo)))
        self.end_headers()
        self.wfile.write(corpo)

    def do_GET(self):
        if self.path.rstrip("/") != "/estado":
            return self.responder(404, {"erro": "Rota não encontrada."})
        servicos = sorted(contentores().values(), key=lambda s: s["servico"])
        for s in servicos:
            s.pop("id")
        self.responder(200, {"estado": "ok", "projeto": PROJETO, "servicos": servicos, "permitidos": PERMITIDOS, "arbitro": ARBITRO})

    def do_POST(self):
        if self.path.rstrip("/") != "/acao":
            return self.responder(404, {"erro": "Rota não encontrada."})
        if self.headers.get("X-Controlo") != "1":
            return self.responder(403, {"erro": "Falta o cabeçalho X-Controlo."})
        try:
            tamanho = min(int(self.headers.get("Content-Length", 0)), 1000)
            pedido = json.loads(self.rfile.read(tamanho) or b"{}")
        except (ValueError, json.JSONDecodeError):
            return self.responder(400, {"erro": "JSON inválido."})

        servico, acao = pedido.get("servico"), pedido.get("acao")
        if servico not in PERMITIDOS:
            return self.responder(403, {"erro": f"O serviço «{servico}» não pode ser controlado pelo painel."})
        if acao not in ACOES:
            return self.responder(400, {"erro": "Ação inválida. Usa parar, arrancar, crash ou reiniciar."})

        alvo = contentores().get(servico)
        if not alvo:
            return self.responder(404, {"erro": f"O contentor do serviço «{servico}» não existe."})

        if acao == "crash" and servico in CRASH_INTERNO:
            if alvo["estado"] != "running":
                return self.responder(409, {"erro": f"O «{servico}» não está a correr."})
            executar_em(alvo["id"], "kill -TERM 1")
            print(f"[controlo] crash interno {servico}", flush=True)
            return self.responder(200, {"estado": "ok", "servico": servico, "acao": acao,
                                        "mensagem": "O processo principal terminou; o Docker volta a arrancá-lo."})

        codigo, dados = docker("POST", f"/containers/{alvo['id']}{ACOES[acao]}")
        print(f"[controlo] {acao} {servico} → HTTP {codigo}", flush=True)
        if codigo in (204, 304):
            mensagem = "Já estava nesse estado." if codigo == 304 else "Feito."
            return self.responder(200, {"estado": "ok", "servico": servico, "acao": acao, "mensagem": mensagem})
        erro = dados.get("message") if isinstance(dados, dict) else str(dados)
        return self.responder(409, {"erro": f"O Docker recusou: {erro}"})

    def log_message(self, formato, *args):
        pass  # os GET /estado de 2 em 2 segundos não enchem o log


if __name__ == "__main__":
    print(f"[controlo] projeto {PROJETO}; serviços controláveis: {', '.join(PERMITIDOS)}", flush=True)
    if NOS_GALERA:
        print(f"[arbitro] a vigiar {', '.join(NOS_GALERA)} (age após {ARBITRO_ESPERA} s sem maioria)", flush=True)
        threading.Thread(target=arbitro, daemon=True).start()
    ThreadingHTTPServer(("0.0.0.0", 8000), Pedido).serve_forever()
