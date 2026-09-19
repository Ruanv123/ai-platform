# Spec: Atendimento no WhatsApp

Status: ready-for-agent

## Problem Statement

A Empresa hoje consegue conectar seu número do WhatsApp, vincular um Agente e ver o webhook verificado — mas nada acontece quando um cliente manda mensagem. O webhook valida a assinatura e descarta o evento. O Agente só existe no Playground, respondendo a quem já está logado na plataforma.

Isso deixa a plataforma em um estado em que tudo parece configurado e nada atende. A Empresa montou a Base de conhecimento, escreveu as Instruções, testou no Playground e conectou o número, e o cliente que escreve para esse número não recebe resposta.

Há ainda um problema de desenvolvimento: só é possível exercitar o atendimento com um número aprovado pela Meta, com Conta do WhatsApp real e webhook público verificado. Não existe forma de rodar o ciclo completo localmente.

## Solution

O WhatsApp passa a atender de verdade: a mensagem do cliente entra pelo webhook, vira Mensagem de um Contato, e o Agente vinculado ao Canal responde pelo mesmo número.

- Cada mensagem recebida é interpretada por uma **strategy** específica do tipo de evento (texto, imagem, áudio, vídeo, documento, status de entrega). O que não tem strategy é registrado como não suportado em vez de descartado, para que a superfície real que chega de cada provedor seja observável.
- A mídia é resolvida **na entrada**: áudio vira transcrição, imagem vira descrição, documento vira texto extraído. Vídeo é reconhecido e recusado com clareza. A Mensagem guarda o texto resolvido, de forma que repetir o Histórico não reprocessa a mesma mídia.
- A resposta não sai a cada mensagem solta. Cada mensagem abre um fluxo que espera cinco segundos; ao fim da espera, o Agente lê o Histórico e responde de uma vez as Mensagens ainda não respondidas. Quem escreve em pedaços recebe uma resposta, não três.
- Enquanto isso, o Contato vê a mensagem marcada como lida e o indicador de "digitando", para que os cinco segundos não pareçam travamento.
- Um **Tipo de canal novo, WhatsApp de testes**, conecta um número por um serviço não oficial (Evolution Go) e permite rodar o ciclo inteiro na máquina do desenvolvedor. Ele não é oferecido aos clientes: só aparece em ambiente local ou para administradores da plataforma.
- Como os dois Tipos de canal têm famílias de strategies próprias, uma **suíte de conformidade** roda as mesmas asserções contra fixtures reais dos dois, para que o que se depura localmente e o que atende em produção não divirjam.

## User Stories

### Recebimento de mensagens

1. As a Contato, I want to receber resposta do Agente quando escrevo para o número da Empresa, so that eu seja atendido sem esperar por uma pessoa
2. As a Contato, I want to que minha mensagem de texto seja entendida pelo Agente, so that ele responda ao que eu realmente perguntei
3. As a Contato novo, I want to ser reconhecido como Contato da Empresa na primeira mensagem, so that o atendimento comece sem cadastro prévio
4. As a Contato que já falou antes, I want to que o Agente tenha o Histórico recente da nossa conversa, so that eu não precise repetir o contexto
5. As a Contato que sumiu por mais de um dia, I want to que o Agente comece do zero, so that ele não responda preso a um assunto que já morreu
6. As a Contato, I want to que meu nome do WhatsApp seja reconhecido, so that o atendimento não me trate como um número
7. As a Empresa, I want to que o Contato seja único pelo telefone dentro da minha conta, so that a mesma pessoa não vire dois cadastros
8. As a Empresa, I want to que mensagens recebidas no Canal de testes não apareçam no Histórico do Canal oficial, so that o desenvolvimento não contamine o atendimento real

### Tipos de mensagem e mídia

9. As a Contato, I want to mandar um áudio e ser entendido, so that eu não precise digitar
10. As a Contato, I want to mandar uma foto e o Agente entender o que está nela, so that eu mostre o problema em vez de descrever
11. As a Contato, I want to mandar um PDF ou documento e o Agente ler o conteúdo, so that eu envie o comprovante ou a proposta que tenho em mãos
12. As a Contato, I want to receber um aviso claro quando mando um vídeo, so that eu saiba que preciso explicar por texto em vez de ficar esperando
13. As a Contato, I want to que a legenda que escrevi junto com a imagem seja considerada, so that a foto e o que eu disse sobre ela sejam lidos juntos
14. As a Empresa, I want to que uma mídia seja processada uma única vez, so that a mesma transcrição não seja paga de novo a cada resposta
15. As a Empresa, I want to que mensagens de tipo não suportado (figurinha, localização, contato, reação) fiquem registradas, so that eu saiba o que meus clientes realmente mandam antes de decidir tratar
16. As a Contato, I want to que uma mídia que a plataforma não conseguiu processar não trave o atendimento, so that o Agente ainda responda ao resto

### Status de entrega

17. As a Empresa, I want to saber quando a resposta do Agente foi entregue ao Contato, so that eu confie que o atendimento chegou
18. As a Empresa, I want to saber quando a resposta foi lida pelo Contato, so that eu diferencie quem ignorou de quem não recebeu
19. As a Empresa, I want to saber quando o envio falhou, so that eu perceba um problema no número antes do cliente reclamar
20. As a Empresa, I want to que o status de entrega atualize a Mensagem já registrada, so that o histórico não encha de linhas duplicadas

### Agrupamento e resposta

21. As a Contato que escreve em pedaços, I want to receber uma resposta só que considere tudo que mandei, so that a conversa não fique fragmentada
22. As a Contato, I want to que minha mensagem seja marcada como lida assim que chega, so that eu saiba que estou sendo atendido
23. As a Contato, I want to ver o indicador de "digitando" enquanto o Agente pensa, so that a espera não pareça abandono
24. As a Contato, I want to que mensagens já respondidas não sejam respondidas de novo, so that eu não receba a mesma coisa duas vezes
25. As a Contato, I want to receber a resposta em uma mensagem só, so that a conversa fique legível
26. As a Empresa, I want to que o Agente responda usando a minha Base de conhecimento, so that as respostas venham do meu negócio e não de conhecimento genérico
27. As a Empresa, I want to que o Agente use a persona, o tom e as Instruções que configurei, so that o atendimento no WhatsApp tenha a mesma voz que testei no Playground
28. As a Empresa com Agente inativo, I want to que ele não responda no Canal, so that eu consiga desligar o atendimento sem desconectar o número
29. As a Empresa sem Agente vinculado ao Canal, I want to que a mensagem seja registrada mas não respondida, so that eu não perca o que chegou enquanto configuro

### Confiabilidade

30. As a Empresa, I want to que uma reentrega do mesmo evento pelo provedor não gere resposta duplicada, so that o cliente não receba a mesma mensagem duas vezes
31. As a Empresa, I want to que mensagens fora de ordem sejam lidas na ordem em que o cliente falou, so that o Agente não responda a um pedaço solto
32. As a Empresa, I want to que uma falha temporária da IA seja tentada de novo antes de desistir, so that uma instabilidade momentânea não vire atendimento perdido
33. As a Contato, I want to receber um aviso quando o atendimento falhou de vez, so that eu não fique esperando indefinidamente
34. As a Empresa, I want to ver o erro registrado no Canal quando o atendimento falha, so that eu saiba que algo está errado sem precisar do cliente me avisar
35. As a Empresa, I want to que o webhook responda rápido ao provedor, so that a Meta não pare de entregar meus eventos
36. As a Empresa, I want to que uma resposta que ultrapassou a janela de 24 horas da Meta não seja enviada, so that meu número não seja penalizado

### Canal de testes

37. As a desenvolvedor da plataforma, I want to conectar um número por um serviço não oficial, so that eu rode o atendimento inteiro na minha máquina sem número aprovado
38. As a desenvolvedor da plataforma, I want to que o Canal de testes use as mesmas strategies de saída e o mesmo fluxo de resposta, so that o que eu depuro seja o que roda em produção
39. As a cliente da plataforma, I want to não ver o Canal de testes no catálogo de Canais, so that eu não configure por engano algo que não é suportado
40. As a administrador da plataforma, I want to ver e configurar o Canal de testes, so that eu consiga reproduzir o problema de um cliente
41. As a desenvolvedor da plataforma, I want to que os eventos do serviço não oficial sejam traduzidos com nomes e formato próprios dele, so that a diferença de payload não vaze para o resto do sistema
42. As a desenvolvedor da plataforma, I want to que a suíte de conformidade cubra os dois Tipos de canal com as mesmas asserções, so that uma strategy nova não fique pronta só de um lado

### Isolamento e permissões

43. As a Empresa, I want to que Contatos e Mensagens pertençam somente à minha conta, so that nenhuma outra Empresa veja quem fala comigo
44. As a Empresa, I want to que um webhook com número desconhecido seja recusado, so that ninguém injete atendimento na minha conta
45. As a Empresa, I want to que um payload com assinatura inválida seja recusado, so that o endereço público do webhook não seja um portal aberto

## Implementation Decisions

### Domínio

- **Contato** é um model novo, escopado pela Empresa (`BelongsToTeam`, como os demais), identificado pelo par Empresa + telefone. Guarda o nome informado pelo WhatsApp. A mesma pessoa falando em dois Canais é um único Contato.
- **Mensagem** é um model novo, pertencente ao Contato e **sempre preso ao Canal** por onde passou. Guarda: direção (entrada/saída), tipo original do evento, o id da mensagem no provedor, o texto resolvido, o status de entrega e a marca de já respondida.
- **Não existe model de Conversa.** O **Histórico** que o Agente lê é uma consulta às Mensagens do Contato naquele Canal, cortada no primeiro intervalo maior que 24 horas, com teto de mensagens. Decisão registrada no ADR 0003.
- O corte de 24 horas e o teto são regra de leitura, não colunas.

### Tipo de canal

- `ChannelType` ganha o caso **WhatsApp de testes**. É um Tipo de canal próprio, não um provedor dentro do Tipo WhatsApp. Decisão e alternativas rejeitadas no ADR 0002.
- O catálogo é gerado do enum, então o Tipo novo só aparece quando `app()->isLocal()` ou o usuário é administrador da plataforma — o mesmo par já usado pelo `HorizonServiceProvider`.
- `unique(team_id, type)` continua valendo por tipo, então a Empresa pode ter o oficial e o de testes ao mesmo tempo. O isolamento entre eles vem do filtro por Canal no Histórico.
- O Canal de testes guarda os dados da sua própria conexão (endereço do serviço, nome da instância, credencial de acesso) em tabela própria, seguindo o padrão de `whatsapp_accounts` / `whatsapp_phone_numbers` estabelecido pelo ADR 0001. Segredos com cast `encrypted`.

### Entrada

- Cada Tipo de canal tem **sua própria família de strategies** de evento, unidas por uma interface por tipo. **Não existe evento canônico intermediário.** Decisão e o risco que ela cria no ADR 0004.
- Núcleo de eventos da v1: texto, imagem, áudio, vídeo, documento e status de entrega. Tudo o mais cai numa strategy de não suportado, que **registra a Mensagem com o tipo bruto** em vez de descartar.
- Os formatos são diferentes e não devem ser reconciliados: o WhatsApp oficial entrega `entry.changes.value` com `messages` e `statuses`; o WhatsApp de testes entrega o envelope `{event, instance, data}` com `MESSAGE`, `SEND_MESSAGE`, `READ_RECEIPT`, `CONNECTION` e `QRCODE`.
- **Rotas separadas por Tipo de canal.** A rota do WhatsApp de testes é registrada em todos os ambientes e **não tem autenticação**, porque o serviço não assina o payload. Risco aceito e registrado no ADR 0002; a garantia é operacional, não técnica.
- A rota do WhatsApp oficial mantém o comportamento atual: handshake `hub.*` no GET e validação de `X-Hub-Signature-256` no POST. O que muda é que o evento deixa de ser descartado.
- **Idempotência** por `ShouldBeUnique` no fluxo de ingestão, com o id da mensagem no provedor como chave e janela de 24 a 48 horas. Não há índice único no banco — o que significa que a proteção cobre o processamento, não o armazenamento.
- **Ordenação** pelo timestamp do provedor, não pela ordem de chegada, já que fila e retry embaralham a chegada.
- O webhook responde ao provedor imediatamente; todo o trabalho acontece em fila.

### Mídia

- Resolvida **na entrada**, com o resultado guardado na Mensagem. Áudio transcrito, imagem descrita, documento extraído em texto, vídeo reconhecido e recusado com mensagem explícita ao Contato.
- Documento reusa a extração já existente na Base de conhecimento, inclusive `spatie/pdf-to-text`.
- Tudo usa OpenAI: `config/ai.php` passa `default_for_images` de `gemini` para `openai`.
- Falha na resolução de uma mídia não derruba o fluxo: a Mensagem fica registrada com a falha e o Agente responde ao resto.

### Fluxo de resposta

- **Um fluxo por mensagem recebida**, despachado com `delay` de 5 segundos. O atraso é no despacho, nunca `sleep()` dentro do job, para não segurar o worker.
- Ao acordar, o fluxo lê as Mensagens ainda **não respondidas** do Contato naquele Canal, monta o Histórico, roda o Agente e envia a resposta. Ao enviar, marca essas Mensagens como respondidas.
- Mensagem nova durante um fluxo em andamento **abre um fluxo novo**, do início. Como as respondidas já saíram do conjunto, o segundo fluxo responde apenas o que sobrou.
- Um Agente de atendimento novo, irmão do `PlaygroundAgent`: mesma montagem de Instruções e mesma ferramenta de busca na Base de conhecimento, mas com o Histórico vindo das Mensagens em vez do rascunho do formulário.
- Canal sem Agente vinculado, ou com Agente inativo: a Mensagem é registrada e nenhum fluxo de resposta é aberto.

### Saída

- Família de saída por Tipo de canal, com três ações na v1: **enviar texto**, **marcar como lida** e **indicador de "digitando"**. Sem envio de mídia.
- Marcar como lida e "digitando" disparam na chegada da mensagem, antes da espera de 5 segundos.
- A resposta do Agente sai como **uma única mensagem**.
- "Marcar como lida" (ação de saída) e o status `read` recebido do provedor são coisas opostas apesar do nome parecido em português, e não devem compartilhar nome no código.

### Falhas

- Falha do provedor de IA ou do envio: retry com backoff pelo próprio Horizon, **com limite de tempo total**. Só depois de esgotar o Contato recebe um aviso genérico e o Canal recebe `last_error`.
- O limite de tempo total existe para que o retry não esgote contra a parede das 24 horas da Meta.
- Resposta cuja janela de 24 horas da Meta já passou não é enviada: fica registrada como não entregue. Templates aprovados não entram nesta fase.

## Testing Decisions

Um bom teste aqui exercita **o que o provedor manda e o que o Contato recebe**, nada entre os dois. Nenhum teste deve conhecer strategy, job, buffer ou adapter pelo nome.

**Seam único: a rota de webhook.** Cada teste faz POST de um payload de provedor e assere três coisas: o status HTTP devolvido ao provedor, as linhas de Contato e Mensagem, e o envio de saída capturado por `Http::fake()` / `Http::assertSent`. Tudo entre esses dois pontos é interno e livre para mudar.

Isso é viável porque `phpunit.xml` já roda com `QUEUE_CONNECTION=sync`: o fluxo atrasado executa inline e uma requisição dirige o caminho inteiro.

**Fakes, todos de borda:**

- O Agente de atendimento com `::fake()`, exatamente como `PlaygroundTest` já faz com `PlaygroundAgent::fake()`.
- `Transcription::fake()` e `Image::fake()` do `laravel/ai` para a mídia.
- `Http::fake()` para o envio ao provedor.

**Suíte de conformidade (a defesa do ADR 0004):** não é uma estrutura própria, é o mesmo seam parametrizado. Um dataset Pest com os dois Tipos de canal roda as mesmas asserções contra as duas rotas e as duas fixtures. Uma strategy nova não está pronta enquanto o dataset não passar nos dois.

**Fixtures:** payloads reais em `tests/Fixtures/whatsapp/oficial/` e `tests/Fixtures/whatsapp/testes/`, um arquivo por tipo de evento, seguindo o padrão já usado por `tests/Fixtures/knowledge/`.

**A única exceção ao seam:** o agrupamento do buffer é invisível sob `sync`, porque o fluxo roda antes de a segunda mensagem chegar. Os testes desse grupo trocam o driver de fila e executam os jobs pendentes manualmente depois dos dois POSTs. Continua sendo a rota como seam, apenas com a fila sob controle do teste. Assertir que um job foi despachado com 5 segundos de atraso testaria a implementação e está descartado.

**Prior art:** `tests/Feature/Channels/WhatsAppWebhookTest.php` para requisição crua com corpo assinado, `tests/Feature/Knowledge/PlaygroundTest.php` para o fake do Agente, `tests/Feature/Knowledge/DocumentTest.php` para processamento em fila.

## Out of Scope

- **Interface de atendimento.** Nada de caixa de entrada, lista de Contatos ou leitura de Mensagens na plataforma. Esta fase faz o atendimento funcionar, não faz a Empresa assistir a ele.
- **Humano assumindo o atendimento.** Sem transferência, sem pausa, sem intervenção manual.
- **Templates aprovados da Meta** e qualquer mensagem iniciada pela Empresa. Todo envio aqui é reativo a uma mensagem do Contato.
- **Envio de mídia pelo Agente.** Só texto sai.
- **Resposta quebrada em vários balões.** Uma mensagem por resposta.
- **Tipos de evento fora do núcleo** (figurinha, localização, contato, reação, botões, listas, grupos, chamadas): registrados como não suportados, não tratados.
- **O Canal de testes como produto.** Sem tela de QR code, sem pareamento, sem ciclo de vida de instância, sem estados novos em `ChannelStatus`.
- **Instagram Direct e Chat no site**, que continuam indisponíveis no catálogo.
- **Métricas de atendimento** no Dashboard.

## Further Notes

Três consequências que valem estar visíveis para quem implementar:

O ADR 0001 tratava `unique(team_id, type)` como a trava que impedia dois WhatsApps na mesma Empresa. Com o Tipo novo, essa trava deixou de existir e o isolamento entre o Canal oficial e o de testes passou a depender inteiramente do **filtro por Canal no Histórico**. Remover esse filtro mistura os dois sem quebrar nada visivelmente.

A idempotência escolhida protege o processamento, não o armazenamento. Se o lock expirar ou o cache for limpo entre a entrega e a reentrega, duas Mensagens com o mesmo id do provedor ainda são graváveis. É o limite conhecido da decisão, não um descuido.

As tabelas `agent_conversations` e `agent_conversation_messages` do `laravel/ai` existem na migration e continuam sem uso. O Agente de atendimento monta as próprias mensagens, como o `PlaygroundAgent` já faz. Elas não são o lugar do Histórico: não têm `team_id`, nem id do provedor, nem status de entrega.
