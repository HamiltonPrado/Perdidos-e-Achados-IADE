# Perdidos & Achados IADE

## 1. Título do projeto
Perdidos & Achados IADE: plataforma web distribuída e tolerante a falhas para a gestão de objetos perdidos e achados no campus

## 2. Ano letivo
2026/2027

## 3. Semestre
1.º semestre (5.º semestre do curso, 3.º ano)

## 4. Curso(s) envolvidos
Licenciatura em Engenharia Informática — IADE, Universidade Europeia

## 5. Unidade(s) curricular(es)
- Projeto de Desenvolvimento de Software (LEIF005D01)
- Sistemas Distribuídos (LEIF005D01)
- Inteligência Artificial (LEIF005D01)
- Segurança Informática (LEIF005D01)
- Engenharia de Software (LEIF005D01)

## 6. Docente(s)
- **Projeto de Desenvolvimento de Software:** Rui Miguel Simão Pascoal
- **Sistemas Distribuídos:** Pedro Miguel Gomes Silva Rosa
- **Inteligência Artificial:** Samuel Simão Canada Gomes
- **Segurança Informática:** Pedro Ramos Santos Brandão
- **Engenharia de Software:** Rui Alexandre Moreira da Silva Roberto Ramos
- **Em todas as unidades curriculares:** Nathan Paulino Campos, André Miguel Guedelha Sabino

## 7. Estudantes participantes
- Francisco Hamilton do Prado Baptista — n.º 20240788
- Isimar Claúdio Agostinho Quixito — n.º 20242038
- Vivandro Jorge Agostinho Kambanza — n.º 20241805

## 8. Palavras-chave
Perdidos e achados; Aplicação web; Sistemas distribuídos; Tolerância a falhas; Alta disponibilidade; Replicação síncrona; Quórum; Failover automático; Cluster MariaDB Galera; Contentores Docker; Proxy reverso; API REST; Segurança web; CSRF; HMAC-SHA256; Inteligência artificial; Processamento de linguagem natural; Semelhança semântica; Sentence-BERT

## 9. Tecnologias utilizadas
- **Frontend:** HTML5, CSS3 e JavaScript (módulos ES, Fetch API); Canvas API para reduzir e converter as fotografias para JPEG antes do envio
- **Backend:** PHP 8.4 (API REST em camadas: rotas, middleware, controllers, validators e models), servidor Apache, PDO
- **Base de dados:** MariaDB 11.4 em cluster Galera de 3 nós (replicação síncrona, multi-primário), sessões e fotografias guardadas na base de dados
- **Infraestrutura e distribuição:** Docker e Docker Compose (8 contentores); nginx 1.28 como proxy reverso e porta de entrada única; healthchecks e autoheal (reinício automático); árbitro do cluster e painel de controlo em Python 3.12; cópias de segurança automáticas com `mariadb-dump`
- **Segurança:** palavras-passe com bcrypt (`password_hash`); sessões com cookie `HttpOnly` e `SameSite`; tokens CSRF; código de levantamento guardado como HMAC-SHA256; limite de tentativas de login; validação do conteúdo das fotografias
- **Inteligência artificial:** Python 3 com sentence-transformers 6.2 (modelo `paraphrase-multilingual-MiniLM-L12-v2`, vetores de 384 dimensões e semelhança do cosseno); PyMySQL para o trabalhador de análise
- **Testes:** painel de testes da API em HTML/JavaScript (bateria automática do percurso completo, mapa do cluster ao vivo, simulação de falhas e logs dos contentores); curl
- **Ferramentas:** Git e GitHub; Mermaid (diagramas); DBeaver (gestão da base de dados); Visual Studio Code

## 10. Resumo curto
O Perdidos & Achados IADE é uma plataforma web que liga quem perde um objeto no campus a quem o encontra. Os alunos registam objetos perdidos ou achados, a receção confirma a entrada dos objetos e o dono reclama-os respondendo a uma pergunta secreta. Depois de aprovada a reclamação, recebe um código de levantamento, guardado apenas como HMAC-SHA256. O sistema foi desenhado para continuar a funcionar perante falhas: a API em PHP usa um cluster MariaDB Galera de três nós com replicação síncrona, mudança automática de nó, quórum, recuperação automática após paragem total, reinício de serviços com problemas e cópias de segurança de hora a hora, tudo orquestrado com Docker. Inclui ainda um modelo de inteligência artificial que compara descrições pelo significado para sugerir objetos parecidos. Nos testes, a falha de um nó da base de dados não causou perda de dados.
