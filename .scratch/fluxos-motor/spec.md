# Spec: Motor de fluxos

Status: ready-for-agent

## Problem Statement

A Empresa configurou o Agente, montou a Base de conhecimento, conectou o número e o atendimento funciona — mas só sabe responder perguntas. Cada mensagem é um turno isolado: chega, o Agente lê o Histórico, responde texto, acabou. Não existe passo, não existe memória entre turnos, não existe caminho.

Isso trava exatamente o que o cliente brasileiro compra automação de WhatsApp para fazer. Marcar um horário exige perguntar a data, guardar a resposta, consultar a agenda e confirmar — quatro passos com estado entre eles. Qualificar um lead exige coletar dois ou três dados obrigatórios e decidir com base neles. Confirmar uma consulta exige o sistema do cliente iniciar a conversa. Nada disso é expressável hoje, e nenhuma quantidade de Instrução no prompt resolve: o Agente pode ser instruído a perguntar o CPF, mas não há onde guardar o CPF, nem como garantir que ele não siga em frente sem o dado.

Há também um problema que só aparece depois: quando o atendimento faz mais do que responder, ele quebra de formas novas — o Contato some no meio, a API externa recusa, o fluxo entra em laço. Hoje não existe nem o conceito de "atendimento em andamento", então não existe nada para inspecionar quando algo dá errado.

## Solution

Um motor de execução de Fluxos, com o atendimento atual passando a ser um caso particular dele.

Um **Fluxo** é o atendimento desenhado como grafo de **Nós**, pertencente à Empresa. Publicar cria uma **Versão** imutável, e uma **Execução** é um Contato percorrendo uma Versão — ela guarda em que Nó está e quais **Variáveis** coletou, e fica presa à Versão em que começou.

O Fluxo é **de conversa** ou **de reação**, e a categoria é lida do grafo, não declarada: se ele contém algum Nó que espera o Contato responder, ele toma o turno e é único por Canal no Gatilho de mensagem; se não contém, roda até o fim de uma vez e pode rodar em paralelo com quantos outros houver. Isso elimina a necessidade de qualquer regra de desempate.

O Fluxo empresta o turno ao Agente por **condição de retorno declarada**, avaliada em código a cada mensagem, com uma ferramenta de devolução como válvula para o que a condição não previu. Quando o Contato responde fora do esperado, uma **escada de exceção** configurável por Nó decide o que fazer.

O que o Contato guardou ao longo do tempo vive em **Campos do Contato** e **Tags**, declarados pela Empresa, que sobrevivem à Execução que os criou.

E o motor é único: um Canal sem Fluxo desenhado roda um Fluxo trivial de um Nó — entregue o turno ao Agente e não retome — de forma que buffer, janela de 24 horas, retry e tratamento de falha existam em um lugar só.

## User Stories

### Desenhar e publicar

1. As a Empresa, I want to descrever meu atendimento como uma sequência de passos, so that o bot faça mais do que responder perguntas soltas
2. As a Empresa, I want to editar um Fluxo sem que a edição afete quem está sendo atendido agora, so that eu trabalhe durante o horário comercial sem medo
3. As a Empresa, I want to publicar quando eu estiver pronta, so that a mudança entre no ar num momento que eu escolhi
4. As a Empresa, I want to que quem está no meio de um atendimento termine na Versão em que começou, so that publicar não quebre conversa em andamento
5. As a Empresa, I want to voltar para uma Versão anterior, so that eu desfaça uma publicação ruim sem refazer o desenho
6. As a Empresa, I want to pausar um Fluxo publicado sem despublicá-lo, so that eu desligue o atendimento por um tempo sem perder o que estava no ar
7. As a Empresa, I want to distinguir um Fluxo que nunca publiquei de um que desliguei, so that eu saiba o que está pela metade e o que está apenas parado
8. As a Empresa, I want to ver que meu rascunho difere da Versão publicada, so that eu lembre de publicar o que ajustei
9. As a Empresa, I want to ser impedida de publicar um segundo Fluxo de conversa no mesmo Canal, so that nunca haja dúvida sobre quem está conversando com o Contato
10. As a Empresa, I want to ser avisada quando um Fluxo de reação ganhou um Nó de espera, so that eu saiba que ele mudou de natureza antes de publicar
11. As a Empresa, I want to que a publicação recuse um desenho quebrado, so that eu não descubra pelo cliente que existe um Nó sem saída
12. As a Owner, I want to que só eu e os Admin possam publicar, so that ninguém altere o atendimento ao vivo sem responsabilidade
13. As a Member, I want to ver e testar os Fluxos, so that eu entenda o atendimento sem poder quebrá-lo

### Conduzir a conversa

14. As a Contato, I want to que o bot lembre o que eu já respondi dentro do atendimento, so that eu não repita a mesma informação
15. As a Contato, I want to que o bot siga caminhos diferentes conforme o que eu digo, so that o atendimento seja sobre o meu caso
16. As a Contato, I want to que o bot insista quando eu não respondo o que foi perguntado, so that eu consiga completar o que vim fazer
17. As a Contato que pergunta outra coisa no meio, I want to ser respondido e depois retomar de onde paramos, so that minha dúvida não derrube o atendimento
18. As a Contato que desiste no meio, I want to que o bot entenda que eu desisti, so that ele não fique repetindo a mesma pergunta
19. As a Contato, I want to que o bot me passe para uma pessoa quando eu pedir, so that eu não fique preso num robô
20. As a Contato, I want to que o bot pare de responder quando um humano assumir, so that eu não receba duas respostas diferentes
21. As a Contato que some no meio, I want to que o atendimento se encerre em vez de ficar pendurado, so that eu comece do zero quando voltar
22. As a Empresa, I want to que o Agente continue usando minha persona, tom, Instruções e Base de conhecimento dentro do Fluxo, so that a voz do atendimento seja a mesma que testei
23. As a Empresa, I want to declarar quando o Agente devolve o turno ao Fluxo, so that a conversa livre não engula o processo que eu desenhei
24. As a Empresa, I want to que o Agente possa devolver o turno por conta própria, so that a situação que eu não previ não trave o atendimento

### Guardar e usar dados

25. As a Empresa, I want to declarar quais dados eu guardo sobre meus Contatos, so that a plataforma sirva ao meu negócio e não a um modelo genérico
26. As a Empresa, I want to que um dado coletado hoje esteja lá amanhã, so that o Contato não seja perguntado a mesma coisa toda vez
27. As a Empresa, I want to marcar Contatos com Tags, so that eu separe quem é o quê sem inventar um campo para cada coisa
28. As a Empresa, I want to usar o que o Contato me disse dentro da mensagem seguinte, so that o atendimento pareça pessoal
29. As a Empresa, I want to guardar valores que valem para toda a conta, so that eu não repita o mesmo endereço de API em dez Nós
30. As a Empresa, I want to decidir o caminho do Fluxo com base num Campo do Contato, so that quem já é cliente receba um atendimento diferente de quem nunca comprou

### Reação em paralelo

31. As a Empresa, I want to que vários Fluxos de reação rodem sobre a mesma mensagem, so that eu etiquete, registre e alerte sem atrapalhar a conversa
32. As a Empresa, I want to que um Fluxo de reação não tome o turno, so that ele nunca dispute a conversa com o Fluxo principal
33. As a Empresa, I want to que um Fluxo de reação possa mandar uma mensagem curta, so that eu confirme o recebimento de um documento sem montar um fluxo inteiro
34. As a Empresa, I want to ser avisada quando um Fluxo de reação envia mensagem num Canal que já tem Fluxo de conversa, so that eu decida se aquilo pode atravessar a conversa

### Reaproveitar

35. As a Empresa, I want to chamar um Fluxo de dentro de outro, so that eu reaproveite "pedir CPF" em vez de copiá-lo em cinco lugares
36. As a Empresa, I want to que o Sub-fluxo receba e devolva valores declarados, so that ele funcione em qualquer contexto sem conhecer quem o chamou
37. As a Empresa, I want to que o Sub-fluxo não enxergue as Variáveis de quem o chamou, so that dois Sub-fluxos não briguem por um nome repetido

### Confiabilidade

38. As a Empresa, I want to que um Fluxo mal desenhado não rode em laço infinito, so that ele não queime meu orçamento de IA
39. As a Empresa, I want to que toda Execução tenha prazo de validade, so that não acumulem execuções zumbis no meu banco
40. As a Empresa, I want to que uma Execução parada apareça como presa, so that eu descubra o problema sem o cliente reclamar
41. As a Empresa, I want to que um erro de limite seja registrado com motivo explícito, so that eu nunca veja um atendimento parar em silêncio
42. As a Empresa, I want to que uma falha temporária da IA seja tentada de novo antes de desistir, so that instabilidade momentânea não vire atendimento perdido
43. As a Empresa, I want to que um gatilho que chega durante uma Execução ativa tenha comportamento declarado, so that dois atendimentos não se atropelem
44. As a Empresa, I want to que uma reentrega do mesmo evento pelo provedor não gere resposta duplicada, so that o Contato não receba a mesma coisa duas vezes
45. As a Empresa, I want to que o atendimento continue respeitando a janela de 24 horas da Meta, so that meu número não seja penalizado

### Continuidade com o que já existe

46. As a Empresa que já tem Canal configurado, I want to que meu atendimento continue funcionando igual depois da mudança, so that eu não perca nada por causa de uma funcionalidade que não pedi
47. As a Empresa, I want to que o comportamento de agrupar mensagens em pedaços continue valendo, so that quem escreve em três linhas receba uma resposta só
48. As a Empresa, I want to que o Contato continue sendo único pelo telefone dentro da minha conta, so that a mesma pessoa não vire dois cadastros
49. As a Empresa, I want to que Fluxos, Versões e Execuções pertençam somente à minha conta, so that nenhuma outra Empresa veja meu atendimento

## Implementation Decisions

### Domínio e schema

- Models novos, todos escopados pela Empresa com o mesmo padrão dos existentes (`BelongsToTeam`, `HasUuids`, factory): **Fluxo**, **Versão do fluxo**, **Execução**, **Campo do Contato**, **Tag**, **Variável global**.
- **Fluxo** guarda nome, Canal e o estado único de três valores: rascunho, publicado, pausado. Ativar exige ter uma Versão publicada; o estado não é dois booleanos. "Alterações não publicadas" é derivado da comparação entre o rascunho e a Versão publicada, não um quarto estado.
- **Versão do fluxo** guarda o DSL completo e imutável, mais a categoria (conversa ou reação) calculada na publicação, para o motor não recalcular a cada mensagem.
- **Execução** guarda a Versão, o Contato, o Canal, o Nó corrente, as Variáveis, o instante de acordar, as tentativas e o estado final com motivo. **Não guarda Mensagens** — ADR 0005.
- **Campo do Contato** é schema declarado pela Empresa (nome, tipo: texto, número, data, escolha) mais valor por Contato — ADR 0009.
- `Message.event_type` passa a se chamar `payload_type`, liberando "Evento" para o barramento. Migração de uma coluna, barata agora.

### DSL

- Formato próprio, versionado, **independente do editor** — ADR 0008. Nós, arestas e configuração. A posição no canvas é metadado que o motor nunca lê.
- Validação por schema mais integridade do grafo: sem Nó órfão, sem aresta para destino inexistente, sem ciclo sem saída. A validação roda na publicação e é o que recusa desenho quebrado.
- A categoria conversa/reação é derivada: o Fluxo é **de conversa** se, e somente se, o grafo contém algum Nó que espera o Contato (Capturar resposta, Entregar ao Agente). Caso contrário é **de reação** — ADR 0006.

### Catálogo de Nós da v1

Mensagem · Capturar resposta · Condição · Espera · Definir variável ou tag · Entregar ao Agente · Transferir para humano · Emitir Evento · Nó de exceção · Requisição HTTP · Enviar Template · Sub-fluxo · Tarefa de IA · Roteamento por intenção · Teste A/B.

- **Enviar Template** é Nó separado de Mensagem: um Template tem variáveis posicionais, botões e categoria, e nenhum texto livre. Unificar os dois empurra o erro para o tempo de execução.
- **Roteamento por intenção** declara as opções inline no próprio Nó, com descrição cada. Não existe catálogo de Intenções da Empresa na v1; ele vira refatoração natural se a mesma intenção começar a se repetir.
- **Teste A/B** sorteia entre arestas dentro da mesma Versão. Nunca entre duas Versões publicadas, o que quebraria a premissa de uma Versão publicada por Fluxo.
- **Sub-fluxo** declara parâmetros de entrada e valores de retorno, como chamada de função. Escopo plano foi rejeitado: gera colisão de nome intermitente e impossível de depurar.
- **Transferir para humano** pausa a Execução, marca o Contato como aguardando e avisa a Equipe. O bot volta manualmente ou por inatividade. A tela de atendimento é spec própria.

### Posse do turno

- **Entregar ao Agente** empresta o turno com uma **condição de retorno declarada**, avaliada em código a cada mensagem — não pelo modelo.
- O Agente recebe também uma ferramenta de devolução ao Fluxo, para o caso que a condição não previu.
- As duas juntas por desenho: a condição é a garantia determinística, a ferramenta é a válvula. Só a condição trava quando o Contato desiste; só a ferramenta deixa o modelo esquecer de voltar.

### Avaliador de expressões

- **Um avaliador, dois consumidores**: o Nó de Condição e a condição de retorno do Nó de Agente. Expressão booleana sobre Variáveis, Campos do Contato, Tags e payload do gatilho.
- A UI pode oferecer o caso simples ("campo preenchido") como formulário, compilando para expressão. O motor conhece só a expressão.

### Escada de exceção

Configurável por Nó, cinco degraus na ordem: repetir o Nó N vezes com reforço → Nó de exceção do Fluxo → entregar o turno ao Agente → transferir para humano → encerrar. O padrão é o terceiro degrau, porque a pergunta paralela é o desvio mais comum e é o caso de uso canônico do empréstimo de turno.

### Gatilhos

- Dois visíveis: **mensagem recebida** (carrega a escolha do Canal — o vínculo Fluxo↔Canal vive aqui, não numa ligação separada) e **webhook**.
- Um terceiro, **manual**, existe mas não aparece no seletor: é o que permite o Playground rodar um Fluxo de webhook injetando payload falso.
- **O timeout por Nó não é o gatilho de inatividade.** O gatilho inicia Fluxo e está fora de escopo; o timeout faz uma Execução parada avançar e é parte da escada. Os dois têm nomes parecidos e naturezas diferentes.

### Dormir e acordar

Job atrasado por Execução, mais uma **varredura de reconciliação** agendada que encontra Execução com instante de acordar vencido. O job dá precisão; a varredura é a rede que impede Execução perdida em silêncio, e é a fonte da métrica de execuções presas.

### Colisão de gatilho

- Uma Execução de conversa ativa por Contato e Canal. Fluxos de reação não tomam turno nem ocupam Execução ativa, e ficam fora dessa regra.
- Políticas declaradas por gatilho: ignorar, enfileirar, substituir. Padrão: mensagem recebida → ignorar (a mensagem alimenta a Execução em curso); webhook → enfileirar.
- "Interromper e retomar" fica fora da v1: é a mais cara de implementar e a menos pedida.

### Limites

Teto de passos por turno, profundidade máxima de Sub-fluxo, tempo de vida máximo da Execução (30 dias) e Execuções simultâneas por Empresa. Todos com padrão ajustável. Ao bater o teto, a Execução **termina com erro explícito registrado**, nunca parada silenciosa.

### Motor único e migração

- O motor é o mesmo para todos os Canais — ADR 0007. Um Canal sem Fluxo desenhado roda um Fluxo trivial de um Nó.
- Migração cria esse Fluxo trivial para cada Canal que já tem Agente vinculado.
- O comportamento hoje dentro do Job de resposta — buffer de cinco segundos, agrupar Mensagens não respondidas, marcar como lida, indicador de digitando, janela de 24 horas, retry com backoff, falha registrada no Canal — passa a ser comportamento do motor e vale para todo Fluxo.

### Permissões

Owner e Admin editam e publicam. Member vê, testa no Playground e lê métricas. Editar rascunho é inofensivo; publicar altera o atendimento ao vivo e merece a separação.

## Testing Decisions

### O que é um bom teste aqui

Um bom teste dirige o sistema pela **mesma porta que a realidade usa** e afirma somente o que um observador de fora veria: a Mensagem que saiu, a linha que ficou no banco, a resposta HTTP, o que o Inertia entregou à tela. Nenhum teste deve conhecer o nome de uma classe do motor, a ordem interna em que os Nós são resolvidos, nem o formato interno do DSL além do que a validação recusa. O dia em que o motor for reescrito por dentro, a suíte inteira deve continuar passando.

### O seam

**Um seam: a rota HTTP.** Quatro portas, todas no formato já estabelecido pelo repositório:

- **Webhook do Canal** — dirige Fluxo com gatilho de mensagem, ponta a ponta. É a porta que `WhatsAppResponseFlowTest` e `WhatsAppWebhookConformanceTest` já usam.
- **Webhook de gatilho** — rota nova, mesma forma, dirige Fluxo com gatilho de webhook.
- **Rotas do Fluxo** — criar, salvar rascunho, publicar, pausar. A validação de publicação é observável aqui: Fluxo de conversa duplicado no Canal, Nó órfão, aresta sem destino, ciclo sem saída.
- **Rota do Playground** — roda a Versão rascunho e devolve o Traço do turno.

Tudo que a feature faz é observável por essas quatro: Mensagens persistidas, envio de saída, linhas de Execução, estado final com motivo, e props do Inertia.

**Nenhum seam novo além do webhook de gatilho, que é uma rota de produto e não um ponto de teste.** O que se usa em cima do seam são fakes já em uso no repositório: `Http::fake` e `Http::assertSent` para o provedor e para o Nó de Requisição HTTP, o `::fake()` do próprio agente para a IA, `Embeddings::fake` para a busca semântica, viagem no tempo para esperas e timeouts, e a fila.

**Deliberadamente rejeitado:** chamar o motor direto com DSL de fixture. É tentador com quinze tipos de Nó, mas é testar implementação — e amarra a suíte à forma interna do motor, que é justamente o que mais vai mudar.

### Suíte de conformidade de Nós

Espelhando o que o ADR 0004 comprou por provedor: **as mesmas asserções rodadas contra cada tipo de Nó**, pelo seam do webhook. Cada Nó do catálogo precisa provar, no mínimo, que avança quando dá certo, que segue a saída de erro quando dá errado, que registra passagem no Traço e que respeita o teto de passos. É o que impede um Nó novo de entrar pela metade.

### Prior art

- `WhatsAppWebhookConformanceTest` — o padrão de conformidade com fixtures e asserções compartilhadas.
- `WhatsAppResponseFlowTest` — ciclo completo pela rota, com `Http::assertSent` na saída e o `::fake()` do agente na IA.
- `PlaygroundTest` — como a IA e os embeddings são falsificados, incluindo a falha do provedor.
- `ChannelTest`, `AgentTest` — CRUD por rota com asserção de Inertia e isolamento por Empresa.
- `ContactMessageTest` — persistência e escopo de Contato e Mensagem.

### Cobertura obrigatória

Isolamento por Empresa em Fluxo, Versão e Execução; recusa de publicação para cada regra de validação; permissão de publicar por Papel; Execução presa à Versão em que começou; a escada de exceção nos cinco degraus; a condição de retorno satisfeita e a devolução pela ferramenta; colisão de gatilho nas três políticas; cada um dos quatro limites terminando com motivo registrado; e o Fluxo trivial reproduzindo o atendimento atual sem diferença observável.

## Out of Scope

- **Editor visual de fluxos.** O canvas, os painéis, o `+`, a geração por IA e a tela de Playground são spec própria. Esta spec entrega o motor, o DSL e as rotas; a suíte dirige por HTTP sem tela.
- **Traço detalhado e dashboard.** O motor registra passagem, resultado e motivo; a gravação em quatro níveis, o mascaramento de campo sensível, a retenção e as telas de métrica e funil são spec própria.
- **Nó de Requisição HTTP e barramento de Eventos na forma completa.** O Nó existe no catálogo do motor; os conectores curados, o webhook de saída assinado e o catálogo de Eventos declarados são spec própria.
- **Templates e webhook de entrada na forma completa.** O Nó de Template existe; sincronização, submissão à Meta, acompanhamento de aprovação, consentimento e rate limit são spec própria.
- **Caixa de entrada de atendimento humano.** O Nó de transferência pausa e avisa; a tela onde alguém responde é spec própria.
- **Campanha e disparo em massa.** Outro produto: exige aprovação de template em lote, controle de qualidade do número e opt-out em escala, com risco de o cliente perder a WABA dele.
- **Gatilho de inatividade e gatilho de evento interno.** Adiados. O timeout por Nó, que é outra coisa, permanece.
- **Replay de Execução** e **migração de Execuções em andamento** para uma Versão nova.
- **Catálogo de Intenções da Empresa**; na v1 as opções são inline no Nó.
- **Teste A/B entre Versões**; só dentro de uma Versão.
- **Segmento** como entidade: perdeu seus consumidores quando campanha e gatilho de evento interno saíram.

## Further Notes

- **Ordem de ataque.** Esta spec destrava as outras cinco. Editor e observabilidade dependem só dela e podem correr em paralelo depois; integrações, WhatsApp ativo e atendimento humano vêm em seguida, em qualquer ordem.
- **ADRs que governam esta área:** 0003 (Histórico é consulta, não entidade — continua valendo), 0005 (Execução estreita), 0006 (conversa e reação), 0007 (motor único), 0008 (Versão imutável e DSL próprio), 0009 (Campos do Contato). O 0004 vale como precedente da suíte de conformidade.
- **Nenhuma dependência nova** é necessária aqui. React Flow pertence à spec do editor e Reverb à do atendimento humano; as duas precisam de aprovação explícita antes de instalar.
- **Risco conhecido:** o volume de Execuções e Traços cresce rápido. Esta spec só cria a Execução; a política de retenção vive na spec de observabilidade, e as duas precisam entrar antes de o produto ver carga real.
- **Ponto de atenção na migração:** o Fluxo trivial precisa reproduzir o atendimento atual sem diferença observável, incluindo o buffer de cinco segundos e o descarte fora da janela de 24 horas. Vale um teste que rode as asserções de hoje contra o motor novo, como prova de que a migração não mudou comportamento.
