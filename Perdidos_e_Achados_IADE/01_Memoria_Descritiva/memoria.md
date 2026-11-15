# Memória Descritiva — Perdidos & Achados IADE

## 1. Enquadramento e problema
No campus perdem-se todos os dias chaves, carteiras, cartões, eletrónicos e material escolar. Hoje o processo depende de perguntar presencialmente na receção, sem registo do que foi perdido ou entregue. Quem perde um objeto não sabe se alguém o encontrou, e a receção não consegue confirmar com segurança quem é o verdadeiro dono.

## 2. Objetivos
- Centralizar numa plataforma web o registo de objetos **perdidos** e **achados**.
- Garantir que os objetos são devolvidos à pessoa certa, com uma **verificação de propriedade** e um **código de levantamento**.
- Construir um sistema **distribuído e tolerante a falhas**, que continue a funcionar quando um componente falha.
- Ajudar a encontrar correspondências entre perdidos e achados com **inteligência artificial**.

## 3. Solução proposta
Uma aplicação web com dois perfis:
- **Aluno:** procura entre os objetos achados, regista o que perdeu, regista o que encontrou e entrega-o na receção, reclama um objeto e acompanha os seus registos.
- **Equipa da receção:** confirma a entrada dos objetos (referência de inventário ACH-), define a pergunta secreta de cada achado, aprova ou recusa reclamações e regista a entrega com o código de levantamento.

### Percurso de um objeto
1. O aluno regista um objeto **perdido** (referência PER-) ou **achado** (referência provisória ENT-).
2. A receção recebe o achado, confirma-o no inventário (referência ACH-) e define uma **pergunta secreta**.
3. O dono reclama o objeto respondendo à pergunta secreta.
4. A receção aprova ou recusa a reclamação.
5. Com a reclamação aprovada, o aluno pede um **código de levantamento de 8 caracteres**, válido durante 24 horas.
6. No balcão, a receção valida o código e regista a devolução.

## 4. Arquitetura
O sistema corre em **Docker Compose**, com os seguintes serviços:

| Serviço | Função |
|---|---|
| **nginx** | Porta de entrada única (proxy reverso). Encaminha `/` e `/api` para o web e contorna o web quando este falha. |
| **web** | Apache + PHP 8.4: o frontend (HTML, CSS, JavaScript) e a API REST. |
| **galera1, galera2, galera3** | Cluster **MariaDB Galera** de 3 nós com replicação síncrona: todos os nós têm os mesmos dados e todos aceitam escritas. |
| **backup** | Cópia da base de dados (`mariadb-dump`) de hora a hora, guardando as últimas 24. |
| **controlo** | Árbitro do cluster e painel de testes (mapa ao vivo, simulação de falhas e logs). |
| **autoheal** | Reinicia os contentores que deixam de responder. |

A API segue uma arquitetura em camadas: **rotas → middleware (autenticação, CSRF, administrador) → controllers → validators → models**.

## 5. Distribuição e tolerância a falhas
**Modelo de faltas:** consideramos faltas por paragem com recuperação (*crash-recovery*): um componente pode parar e voltar mais tarde com os dados que tinha guardados. Não consideramos faltas bizantinas.

| Falta | Mecanismo |
|---|---|
| Cai 1 nó da base de dados | Os outros 2 mantêm o **quórum** e continuam. O PHP passa automaticamente ao nó seguinte e lembra-se do último que funcionou. Quando o nó volta, recebe sozinho o que perdeu. |
| Caem 2 nós | O nó que sobra fica sem maioria e deixa de aceitar escritas. O **árbitro** confirma que os outros estão parados e reativa-o ao fim de 15 s. |
| Param os 3 nós | Os nós fazem uma **votação** num volume partilhado e o nó com a última transação refunda o cluster. |
| Partição de rede | Só o lado com maioria aceita escritas, o que evita o *split-brain*. |
| O web ou o nginx deixam de responder | O healthcheck falha e o **autoheal** reinicia o contentor. |
| Um contentor termina | `restart: unless-stopped` volta a arrancá-lo. |
| Dados apagados ou corrompidos | **Backups** de hora a hora, com reposição pelo script `repor.sh`. |

**Resultado medido:** com o crash do nó em uso, a aplicação continuou a funcionar. Houve uma pausa de cerca de 5 s nas escritas, que é o tempo de deteção da falha pelo Galera, e o nó voltou sincronizado em cerca de 6 s, **sem perda de dados**.

## 6. Segurança
- Palavras-passe guardadas com `password_hash` (bcrypt).
- Sessões guardadas na base de dados, com cookie `HttpOnly` e `SameSite=Lax`, e identificador renovado no login.
- Proteção **CSRF** em todas as escritas.
- **Limite de tentativas de login:** 5 falhas em 15 minutos bloqueiam o email.
- O código de levantamento é guardado apenas como **HMAC-SHA256** e é renovado a cada pedido.
- As fotografias são validadas pelo conteúdo (JPEG ou PNG, até 2 MB).
- A conta da aplicação só acede à sua própria base de dados (menor privilégio), e o serviço só está acessível no próprio computador (127.0.0.1).

## 7. Inteligência artificial
**Fase 1 (concluída):** um modelo de semelhança semântica (Sentence-BERT multilingue `paraphrase-multilingual-MiniLM-L12-v2`) transforma o título e a descrição de cada objeto num vetor de 384 números e compara os vetores com a **semelhança do cosseno**, dando uma nota de 0 a 1. Com um limiar de 0,65, foi avaliado com 12 objetos perdidos e 17 achados: o achado certo ficou em 1.º lugar em 11 dos 12 casos (MRR 0,94), com precisão de 0,77 e revocação de 0,83.

**Fase 2 (em curso, desligada por omissão):** um trabalhador (`worker.py`) lê a fila de análises (`ia_jobs`), compara cada objeto com os candidatos e grava as sugestões, que aparecem ao aluno no site.

## 8. Testes
- Uma bateria automática de testes da API cobre o percurso completo, recusas, fotografias e permissões (**35 verificações aprovadas**).
- Ensaios de tolerância a falhas com crash de nós da base de dados e do servidor web, medindo os pedidos falhados e o tempo de recuperação.

## 9. Limitações
- Uma só instância do web e do nginx. O web já está preparado para ter réplicas, porque as sessões e as fotografias estão na base de dados.
- Todos os serviços correm num único computador.
- Sem HTTPS (o acesso é só local).

## 10. Trabalho futuro
- Ligar a IA (fase 2) ao site e ativar as notificações.
- Várias réplicas do web e distribuição por várias máquinas.
- HTTPS (TLS).
- Testes automáticos a cada alteração, com integração contínua.
