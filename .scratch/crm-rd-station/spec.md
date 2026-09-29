# Spec: CRM — motor de integração e RD Station CRM pelo Agente

Status: ready-for-agent

Depends on: `.scratch/agenda-google/spec.md` (padrão de Integração por OAuth, Tools do Agente e estado de Integração)

ADRs: `docs/adr/0014-crm-sem-espelho-com-vinculos-metadados-e-negocios-vinculados.md`, `docs/adr/0015-crm-so-no-agente-fora-dos-fluxos.md`, `docs/adr/0010-pausa-do-agente-por-contato-e-canal.md`, `docs/adr/0012-duas-formas-de-conexao-com-endereco-de-webhook-proprio.md`

## Problem Statement

A tela de Integrações lista RD Station CRM, HubSpot, Pipedrive e outros como Provedores de CRM, com capacidades como "Criar e atualizar contatos" e "Registrar oportunidades no funil", mas nenhum conecta. Uma Empresa que tem time comercial e usa um Agente para atender e qualificar pelo WhatsApp vive com dois mundos separados:

- o que o Agente descobre na conversa — interesse, orçamento, convênio, prazo — fica preso no Histórico e alguém precisa redigitar no CRM;
- o Contato que já está sendo trabalhado por um vendedor continua recebendo respostas da IA, porque a plataforma não sabe em que pé o Negócio está;
- o Agente não sabe que aquele Contato já tem uma proposta enviada, e responde como se fosse um desconhecido;
- quando uma escrita no CRM falha, ninguém fica sabendo.

As próximas integrações serão Pipedrive e HubSpot. Se o RD Station CRM for construído como um caso particular, cada novo Provedor de CRM vira uma nova implementação das Ações do Agente, da pausa e da ficha do Contato.

## Solution

A Empresa conecta o RD Station CRM com um clique pela tela de Integrações. A partir daí:

- **Modelo único:** tudo o que o Agente, o Inbox e a tela do CRM enxergam está num modelo canônico — Pessoa do CRM, Organização do CRM, Negócio, Funil de vendas, Etapa do funil, Atividade, Responsável e Campo do CRM —, igual para qualquer Provedor de CRM. O RD Station CRM é o primeiro adapter; Pipedrive e HubSpot entram depois implementando o mesmo contrato.
- **Sem espelho:** o CRM é a fonte da verdade. A plataforma guarda só o Vínculo entre Contato e Pessoa do CRM, os metadados de configuração do CRM e o estado mínimo dos Negócios vinculados (ADR 0014).
- **Agente:** ganha quatro Ações — consultar a ficha no CRM, garantir Negócio, mover o Negócio de Etapa do funil e registrar nota — e passa a saber, a cada turno, a Etapa, a Situação e o Responsável dos Negócios vinculados do Contato.
- **Etapa comercial:** quando um Negócio vinculado entra numa Etapa do funil que a Empresa marcou como conduzida pelo time comercial, a IA é pausada para aquele Contato em todos os Canais e a Equipe é avisada. Quando nenhum Negócio vinculado está mais numa Etapa comercial, a IA volta.
- **Registro automático:** a plataforma pode criar a Pessoa do CRM de todo Contato atendido e registra um Resumo do atendimento no CRM depois de um período de silêncio.
- **Operações do CRM:** cada escrita no CRM fica registrada com a sua situação, e a Empresa vê e reenvia o que falhou.
- **Ficha do Contato:** no atendimento, a Equipe vê o Vínculo, os Negócios abertos e as últimas Operações do CRM daquele Contato.

O CRM não entra nos Fluxos nesta versão (ADR 0015).

## User Stories

### Conectar e desconectar

1. As an Owner ou Admin, I want to conectar o RD Station CRM com um clique pela tela de Integrações, so that eu não precise copiar token nem configurar webhook à mão.
2. As an Owner ou Admin, I want to ver com qual conta do RD Station CRM o CRM está conectado, so that eu saiba para onde os dados estão indo.
3. As an Owner ou Admin, I want to ver o motivo quando eu recuso a autorização no RD Station, so that eu entenda por que o CRM não foi conectado.
4. As a Member da Empresa, I want to ser impedido de conectar ou desconectar o CRM, so that só quem administra a Empresa decida sobre integrações.
5. As an Owner de uma Empresa que já tem um CRM, I want to ser avisado de que conectar outro Provedor de CRM desconecta o atual e apaga Vínculos e configurações, so that eu não troque de CRM por engano.
6. As an Owner, I want to que o CRM continue funcionando quando a pessoa que o conectou sai da Equipe, so that o atendimento não pare por causa de uma saída.
7. As an Owner ou Admin, I want to que os webhooks do RD Station CRM sejam registrados sozinhos ao conectar, so that a plataforma saiba das mudanças sem eu configurar nada no RD.
8. As an Owner ou Admin, I want to desconectar o CRM pela plataforma, so that eu encerre a integração quando quiser.
9. As an Owner ou Admin, I want to que desconectar remova os webhooks no RD Station CRM, so that o RD não fique mandando avisos para um lugar que não os usa.
10. As an Owner ou Admin, I want to que desconectar apague Vínculos, metadados, estado dos Negócios vinculados e mapeamentos, e desligue as Ações do CRM nos Agentes, so that nada fique pela metade.
11. As an Owner ou Admin, I want to que desconectar nunca apague nada no meu CRM, so that o trabalho do time comercial esteja sempre seguro.
12. As an Owner ou Admin, I want to reconectar o CRM quando o acesso é revogado ou expira, so that a integração volte sem refazer toda a configuração.
13. As a Empresa sem CRM conectado, I want to que a tela de Integrações pare de prometer capacidades de Provedores de CRM que não conectam, so that eu não perca tempo tentando.

### Estado do CRM

14. As an Owner ou Admin, I want to ver se o CRM está conectado, com atenção ou com erro, so that eu saiba se a integração está funcionando.
15. As an Owner ou Admin, I want to ver o CRM com erro, o motivo e o que fazer quando o RD Station recusa a renovação do acesso, so that eu reconecte antes de perder Operações.
16. As an Owner ou Admin, I want to receber um aviso quando o CRM entrar em erro, so that eu não dependa de abrir a tela para descobrir.
17. As a Empresa, I want to que as escritas pedidas enquanto o CRM está com erro fiquem guardadas por até 72 horas e sejam enviadas quando eu reconectar, so that uma queda de acesso não apague o trabalho do Agente.
18. As a Empresa, I want to que escritas retidas por mais de 72 horas falhem com um motivo claro, so that eu saiba o que não chegou ao CRM.
19. As an Owner ou Admin, I want to ver o CRM com atenção quando um Funil de vendas, Etapa do funil ou Campo do CRM configurado deixou de existir no RD, com a lista do que quebrou e onde, so that eu corrija a configuração do Agente ou do mapeamento.
20. As a Empresa, I want to que os Funis de vendas, Etapas do funil, Campos do CRM, Responsáveis e Motivos de perda sejam recarregados ao conectar, ao abrir a tela do CRM, diariamente e quando uma escrita falha por referência inexistente, so that a configuração reflita o RD sem eu pedir.
21. As a Empresa, I want to que um limite de requisições do RD Station atrase as escritas em vez de derrubar o CRM, so that um pico de atendimento não vire erro.

### Vínculo entre Contato e Pessoa do CRM

22. As a Empresa, I want to que um Contato seja ligado à Pessoa do CRM que tem o mesmo telefone, so that o que acontece no WhatsApp caia no registro certo do CRM.
23. As a Empresa, I want to que telefones digitados de qualquer jeito no CRM, como `11 99999-8888` ou `+55 (11) 99999-8888`, sejam reconhecidos, so that o Vínculo funcione com a base que o time comercial já tem.
24. As a Empresa, I want to que, havendo mais de uma Pessoa do CRM com o mesmo telefone, a plataforma use a atualizada mais recentemente, so that ela não trave nem crie uma terceira.
25. As a Empresa, I want to que a Pessoa do CRM só seja criada quando algo realmente precisa escrever no CRM, so that meu CRM não se encha de números errados e spam.
26. As a Empresa, I want to que duas mensagens simultâneas do mesmo Contato nunca criem duas Pessoas do CRM, so that o CRM não ganhe duplicatas por causa da plataforma.
27. As a Empresa, I want to que o Vínculo seja desfeito quando a Pessoa do CRM é apagada no CRM, so that a próxima necessidade encontre o registro que sobrou.
28. As a Empresa, I want to que o Vínculo continue valendo quando o telefone da Pessoa do CRM muda no CRM, so that uma correção de cadastro não separe o Contato do seu histórico comercial.
29. As a Empresa, I want to que um Negócio criado pelo vendedor para uma Pessoa do CRM ainda sem Vínculo ligue essa Pessoa ao Contato que tem o mesmo telefone, so that a plataforma reconheça o Contato sem depender da ordem em que as coisas aconteceram.
30. As a Empresa, I want to que a plataforma nunca crie um Contato a partir do CRM, so that só quem falou pelos Canais seja Contato.
31. As a Empresa, I want to ligar a opção "Registrar Contatos atendidos", so that todo Contato que o Agente atendeu vire Pessoa do CRM sem depender de o Agente decidir escrever.
32. As a Empresa, I want to definir quantas mensagens o Contato precisa ter mandado, com o Agente tendo respondido, para ser registrado, so that "oi" de número errado não entre no CRM.
33. As an Owner ou Admin, I want to escolher um Responsável padrão, so that toda Pessoa do CRM e todo Negócio criados pela plataforma tenham alguém do time comercial encarregado.
34. As a Empresa, I want to que um Negócio criado pela plataforma herde o Responsável da Pessoa do CRM quando ela já tem um, so that o cliente continue com o vendedor que já o conhece.
35. As a Empresa, I want to que a plataforma nunca sobrescreva o nome ou o telefone de uma Pessoa do CRM que já existe, só preencha quando estão vazios, so that o cadastro que o time comercial cuidou não mude sozinho.

### Mapeamento de Campos

36. As an Owner ou Admin, I want to mapear um Campo do Contato a um Campo do CRM da Pessoa do CRM, so that o que o Agente salva na plataforma apareça no CRM.
37. As an Owner ou Admin, I want to escolher a direção de cada mapeamento — da plataforma para o CRM ou do CRM para a plataforma —, so that eu decida quem manda em cada dado.
38. As an Owner ou Admin, I want to ser impedido de mapear campos de tipos incompatíveis, so that um texto livre não quebre um campo de escolha no CRM.
39. As an Owner ou Admin, I want to mapear cada opção de um Campo do Contato de escolha para uma opção do Campo do CRM, so that "Unimed" aqui seja "UNIMED" lá.
40. As a Empresa, I want to que a mudança de um Campo do Contato mapeado para o CRM seja enviada quando o Contato já tem Vínculo, so that o CRM fique em dia.
41. As a Empresa, I want to que, quando o Vínculo nasce, todos os Campos do Contato mapeados para o CRM sejam enviados de uma vez, so that nada fique para trás por ter sido salvo antes.
42. As a Empresa, I want to que uma alteração na Pessoa do CRM atualize os Campos do Contato mapeados do CRM para a plataforma, so that o Agente use o dado que o vendedor corrigiu.
43. As an Owner ou Admin, I want to marcar quais Campos do CRM o Agente pode ver ao consultar a ficha, so that dado interno do time comercial não chegue ao Contato.

### Agente: Ações do CRM

44. As an Owner ou Admin, I want to ligar ou desligar cada Ação do CRM por Agente, so that só o Agente de vendas ou de qualificação mexa no CRM.
45. As a Empresa, I want to que as Ações do CRM venham ligadas por padrão nos Agentes com Objetivo Vendas ou Qualificação quando há CRM conectado, so that eu não precise descobrir onde ligar.
46. As a Empresa, I want to que ligar, desligar e configurar as Ações do CRM passe pelo Rascunho do agente, so that a mudança só valha nos Canais depois de publicar.
47. As a Empresa sem CRM conectado, I want to que as Ações do CRM não apareçam, so that eu não ligue algo que não funciona.
48. As an Owner ou Admin, I want to escolher em qual Funil de vendas e em qual Etapa do funil o Agente cria Negócios, so that o que ele gera caia no lugar certo do processo comercial.
49. As an Owner ou Admin, I want to liberar Etapas do funil para onde o Agente pode mover o Negócio, cada uma com uma descrição de quando mover, so that ele avance o Negócio pelo critério da Empresa.
50. As a Empresa, I want to que ganho e perdido nunca possam ser liberados para o Agente, so that a IA não feche nem descarte Negócio sozinha.
51. As a Contato, I want to que o Agente saiba o que o CRM já registra sobre mim quando é relevante, so that eu não precise repetir o que já contei ao vendedor.
52. As a Empresa, I want to que a consulta da ficha mostre ao Agente só os Campos do CRM visíveis, os Negócios abertos e o nome do Responsável, e nunca as notas, so that anotações internas não vazem para o Contato.
53. As a Contato, I want to que a conversa continue quando o CRM demora para responder à consulta da ficha, so that eu não fique esperando por causa de um sistema lento.
54. As a Empresa, I want to que o Agente crie um Negócio quando o Contato demonstra interesse, so that a oportunidade não dependa de alguém redigitar a conversa.
55. As a Empresa, I want to que o Agente atualize o Negócio aberto da Pessoa do CRM naquele Funil de vendas em vez de criar outro, inclusive quando foi o vendedor que o criou, so that não surjam Negócios duplicados.
56. As a Empresa, I want to que o Agente mova o Negócio para uma Etapa liberada quando o critério dela é atendido, so that o funil mostre a qualificação que já aconteceu.
57. As a Empresa, I want to que o Agente registre uma nota no CRM com algo importante que o Contato disse, so that o vendedor saiba antes de ligar.
58. As a Contato, I want to que a resposta do Agente não espere a escrita no CRM terminar, so that o atendimento continue rápido.
59. As a Empresa, I want to que uma escrita do Agente que falha fique visível nas Operações do CRM, so that o "anotei" do Agente não vire uma promessa silenciosa.

### Agente: contexto e Etapa comercial

60. As a Contato que já tem um Negócio em andamento, I want to que o Agente saiba em que Etapa do funil ele está e quem é o Responsável, so that ele responda "o Carlos já está preparando sua proposta" em vez de recomeçar.
61. As a Empresa, I want to que o valor do Negócio não entre no que o Agente sabe automaticamente, so that ele não cite valores sem ninguém ter perguntado.
62. As an Owner ou Admin, I want to marcar Etapas do funil como Etapas comerciais, so that a IA saia da frente quando o time comercial assume.
63. As a vendedor, I want to que, ao mover o Negócio para uma Etapa comercial no CRM, a IA pare de responder aquele Contato em todos os Canais, so that o Contato não receba respostas da IA enquanto eu negocio.
64. As a Equipe, I want to que o Contato com a IA pausada pelo CRM fique aguardando atendimento no Inbox e eu seja avisado, so that a mensagem dele não fique sem resposta.
65. As a Empresa, I want to que a IA volte a responder quando nenhum Negócio vinculado do Contato está mais numa Etapa comercial, ou quando o Negócio é ganho ou perdido, so that ninguém precise lembrar de devolver.
66. As a Equipe, I want to devolver o Contato à IA manualmente mesmo com o Negócio numa Etapa comercial, so that eu decida quando a IA pode voltar, até o Negócio entrar de novo numa Etapa comercial.
67. As a Empresa, I want to que a pausa pelo CRM não tenha prazo, so that a IA não volte no meio de uma negociação.
68. As a Empresa, I want to que a IA fique pausada se qualquer Negócio vinculado do Contato estiver numa Etapa comercial, so that um Negócio de outro Funil de vendas não traga a IA de volta.
69. As a Empresa, I want to que o Agente possa mover o Negócio para uma Etapa liberada que também é Etapa comercial, pausando a si mesmo, so that "qualifiquei, agora é com o vendedor" aconteça sem Transferência manual.
70. As an Owner ou Admin, I want to que a tela sugira liberar ao Agente uma Etapa comercial como passagem para o time comercial, so that eu descubra esse caminho.

### Registro automático e Resumo do atendimento

71. As a vendedor, I want to encontrar no CRM uma nota com o resumo do que o Contato conversou, so that eu entre na conversa sabendo o contexto.
72. As a Empresa, I want to que o Resumo do atendimento seja registrado depois de 30 minutos de silêncio do Contato, so that ele resuma um bloco de conversa e não cada mensagem.
73. As a Empresa, I want to que o Resumo só seja gerado quando o Contato mandou pelo menos duas mensagens na janela, so that eu não pague por resumir um "ok 👍".
74. As a Empresa, I want to que o Resumo inclua o que a Equipe disse quando houve atendimento humano, so that o vendedor veja a conversa inteira.
75. As a Empresa, I want to que o Resumo só exista para Contatos que já têm Vínculo, so that uma nota nunca crie uma Pessoa do CRM.
76. As a Empresa, I want to desligar o Resumo do atendimento, so that eu não pague por algo que meu time não lê.
77. As a Empresa, I want to ver quanto cada Resumo custou em IA, so that eu saiba o custo da integração.
78. As a Empresa, I want to que cada janela gere um Resumo novo, sem reescrever o anterior, so that o histórico no CRM seja fiel ao que aconteceu.

### Operações do CRM

79. As an Owner ou Admin, I want to ver cada escrita que a plataforma pediu ao CRM, com a situação pendente, feita ou com falha, so that eu confie no que chegou lá.
80. As an Owner ou Admin, I want to filtrar as Operações do CRM com falha e ver o motivo legível, so that eu resolva o que não chegou.
81. As an Owner ou Admin, I want to tentar de novo uma Operação do CRM com falha, so that eu não precise refazer à mão no CRM.
82. As a Empresa, I want to que falhas temporárias do RD Station sejam tentadas de novo sozinhas, so that instabilidade não vire Operação perdida.
83. As a Empresa, I want to que falhas definitivas, como um campo recusado pelo RD, parem de ser tentadas, so that a fila não fique presa num erro que não se resolve sozinho.
84. As a Empresa, I want to que dados sensíveis apareçam mascarados nas Operações do CRM, so that o registro não exponha o que foi declarado como sensível.
85. As a Empresa, I want to que as Operações do CRM sejam guardadas por 30 dias, so that o registro não cresça para sempre.

### Ficha do Contato no Inbox

86. As a Equipe, I want to ver na ficha do Contato se ele tem Vínculo com uma Pessoa do CRM, com um atalho para abri-la no RD, so that eu vá direto ao registro certo.
87. As a Equipe, I want to ver os Negócios abertos do Contato, com Funil de vendas, Etapa do funil, valor e Responsável, so that eu atenda sabendo em que pé está a venda.
88. As a Equipe, I want to ver as últimas Operações do CRM daquele Contato, so that eu saiba o que o Agente registrou e o que falhou.
89. As a Equipe, I want to desfazer o Vínculo quando a plataforma ligou o Contato à Pessoa do CRM errada, so that a próxima escrita procure de novo.
90. As a Equipe, I want to ver na ficha quando a IA está pausada pelo CRM e por qual Negócio, so that eu entenda por que o Agente não está respondendo.

### Avisos à Equipe

91. As a Equipe, I want to ser avisada em tempo real quando um Negócio vinculado é ganho, so that eu comemore e trate o Contato como cliente.
92. As a Equipe, I want to ser avisada em tempo real quando um Negócio vinculado é perdido, com o Motivo de perda, so that eu saiba como retomar o contato.
93. As a Equipe, I want to ser avisada em tempo real quando a IA é pausada pelo CRM, so that eu assuma a conversa.
94. As a Equipe, I want to que uma mudança de Etapa do funil comum só atualize a ficha, sem aviso, so that eu não seja inundada de notificações.

### Playground

95. As an Owner ou Admin, I want to testar no Playground a consulta da ficha com o CRM real, so that eu veja exatamente o que o Agente veria.
96. As an Owner ou Admin, I want to que garantir Negócio, mover de Etapa e registrar nota no Playground sejam simulados, so that meus testes não sujem o CRM.
97. As an Owner ou Admin, I want to ver no Playground o que o Agente teria escrito no CRM, so that eu confira a decisão dele sem efeito real.

### Extensibilidade

98. As a Empresa que usa Pipedrive ou HubSpot, I want to que a integração do meu CRM chegue com as mesmas Ações, a mesma pausa por Etapa comercial e a mesma ficha, so that eu tenha o mesmo produto independentemente do Provedor de CRM.
99. As a Empresa, I want to que capacidades que meu Provedor de CRM não tem fiquem escondidas em vez de falharem, so that eu nunca configure algo impossível.

## Implementation Decisions

### Modelo canônico e o que é guardado (ADR 0014)

- **CRM**: continua sendo a Integração do tipo `crm`, no máximo uma por Empresa, com o Provedor de CRM identificado pelo catálogo já existente. A credencial fica criptografada na Integração, como hoje. Estados: conectado, atenção (referência quebrada; a integração funciona) e erro (sem acesso; escritas retidas). A configuração do CRM guarda: Responsável padrão, opção "Registrar Contatos atendidos" e o mínimo de mensagens, opção e janela do Resumo do atendimento, Etapas comerciais e Campos do CRM visíveis ao Agente.
- **Entidades canônicas** que atravessam o contrato do adapter: Pessoa do CRM, Organização do CRM, Negócio (Funil de vendas, Etapa do funil, Situação aberto/ganho/perdido, Motivo de perda, valor, Responsável), Funil de vendas, Etapa do funil, Atividade (nota; tarefa fica declarada no contrato mas sem uso nesta versão), Responsável e Campo do CRM (definição com tipo e opções; valor). Produto, proposta, reunião, campanha, Lead do Pipedrive e ticket do HubSpot não fazem parte do canônico.
- **Guardado localmente, e nada mais:**
  - **Vínculo**: Contato ↔ id externo da Pessoa do CRM, único por Contato. Nasce por busca de telefone ou por criação na primeira escrita; some quando a Pessoa do CRM é apagada no CRM ou quando a Equipe o desfaz.
  - **Metadados**: Funis de vendas, Etapas do funil, Campos do CRM (com tipo e opções), Responsáveis e Motivos de perda, com a data da última carga.
  - **Negócio vinculado**: id externo, Vínculo, Funil de vendas, Etapa do funil, Situação, valor, Responsável e data da última atualização no provedor. Usado para o contexto do Agente e para decidir entrada e saída de Etapas comerciais.
  - **Mapeamento de Campos**: par Campo do Contato ↔ Campo do CRM, direção única e mapa de opções para campos de escolha.
  - **Operação do CRM**: origem (automática, Ação do Agente, reenvio), tipo, Contato, situação, tentativas, motivo da falha, resumo mascarado do que foi enviado, custo em IA quando houver, e instante de expiração da retenção.
- Nenhum payload de webhook nem cópia de Pessoa do CRM, Organização do CRM ou Atividade é guardado.
- Tudo tem `team_id`; unicidade por (CRM, id externo) onde houver id externo.

### Contrato do adapter de Provedor de CRM

- Um contrato fixo que todo Provedor de CRM implementa, com as operações:
  - conectar (URL de autorização, troca do código, renovação do acesso);
  - registrar, conferir e remover inscrições de webhook;
  - validar a autenticidade de um webhook recebido e identificar a qual CRM ele pertence;
  - traduzir um webhook num aviso canônico (tipo de recurso, id externo, apagado ou não);
  - carregar metadados (Funis de vendas com Etapas, Campos do CRM, Responsáveis, Motivos de perda);
  - buscar Pessoas do CRM por telefone;
  - buscar uma Pessoa do CRM, um Negócio, e os Negócios abertos de uma Pessoa do CRM;
  - criar e atualizar Pessoa do CRM (campos padrão e Campos do CRM);
  - criar e atualizar Negócio, mover de Etapa do funil;
  - registrar Atividade de nota;
  - montar o endereço de uma Pessoa do CRM no próprio provedor;
  - declarar capacidades (por exemplo: Motivo de perda obrigatório, ganho e perdido como Etapas do funil) e o limite de requisições.
- **Só o adapter conhece o provedor.** O Motor de CRM — Vínculo, garantir Negócio, Operações, retenção, Etapa comercial, Resumo, mapeamento — é único e não sabe qual Provedor de CRM está do outro lado.
- Erros do adapter chegam ao Motor em categorias canônicas: temporário (tentar de novo), limite de requisições (esperar o tempo indicado), acesso recusado (renovar uma vez; persistindo, CRM com erro), referência inexistente (recarregar metadados; CRM com atenção) e recusa definitiva (falha sem novas tentativas).
- A tela, as Ações do Agente e a configuração leem as capacidades declaradas e escondem o que o provedor não suporta, em vez de nivelar pelo mínimo comum.
- **Situação do Negócio**: canônica em Etapa do funil + Situação + Motivo de perda. Um provedor em que ganho e perdido são Etapas do funil (HubSpot) é traduzido pelo adapter; o Motor nunca vê essa diferença.

### Adapter do RD Station CRM

- **API v2** do RD Station CRM, com OAuth2 por código de autorização de um app da plataforma registrado na RD App Store.
- **Token rotativo:** o acesso vale 2 horas e cada renovação devolve um refresh token novo, invalidando o anterior. A renovação acontece sob lock por CRM, relendo a credencial depois de adquirir o lock, e de forma proativa quando faltam 5 minutos para expirar. Renovação recusada deixa o CRM com erro e avisa Owner e Admin, no mesmo padrão da Agenda.
- **Webhooks** registrados pela API ao conectar, para os eventos de contato (atualizado, apagado) e de negócio (criado, atualizado, apagado). Cada CRM recebe uma chave aleatória própria, enviada pelo RD no header de autenticação configurado no webhook; a plataforma guarda só o hash dela e identifica o CRM por ele.
- Busca por telefone considera as variações de formato que o RD aceita e normaliza o resultado para E.164, com o Brasil como país padrão, usando a mesma normalização de telefone do Contato.

### Recebimento de webhooks

- **Um endpoint por Provedor de CRM**, fora do grupo autenticado e com throttle próprio, sem nenhum identificador do CRM na URL (ADR 0012). Nesta versão, só o do RD Station CRM.
- O controller pede ao adapter que valide e identifique; chave inválida recebe 401; CRM desconhecido ou desconectado recebe 200 e é descartado; válido recebe 200 e o aviso canônico é enfileirado. Nada é armazenado.
- **O webhook é um aviso** (ADR 0013 e 0014): o job busca o estado atual na API e age sobre ele. Ordem de chegada, duplicata e formato do payload não importam.
- **Debounce por recurso:** avisos repetidos do mesmo (CRM, tipo, id externo) numa janela curta viram uma única busca, com job único por essa chave.
- **Pessoa do CRM atualizada**: atualiza os Campos do Contato mapeados do CRM para a plataforma, se houver Vínculo. **Pessoa do CRM apagada** (inclusive o lado perdedor de um merge): desfaz o Vínculo e apaga os Negócios vinculados dela.
- **Negócio criado ou atualizado**: se a Pessoa do CRM tem Vínculo, atualiza o Negócio vinculado; se não tem, busca a Pessoa do CRM, normaliza o telefone e procura um Contato existente com ele — encontrando, cria o Vínculo; não encontrando, descarta. **Negócio apagado**: apaga o Negócio vinculado.
- Depois de atualizar Negócios vinculados, o Motor reavalia a Etapa comercial do Contato (ver abaixo) e emite os avisos à Equipe de ganho, perda e pausa.
- Webhooks causados por Operações do próprio CRM da plataforma são tratados igual: como a reação é calculada sobre o estado, e não sobre o evento, eles só confirmam o que já se sabia.

### Vínculo

- Buscado só quando algo precisa do CRM para aquele Contato: uma Ação do Agente, "Registrar Contatos atendidos", uma mudança de Campo do Contato mapeado (que só gera Operação se já houver Vínculo) ou um webhook de Negócio.
- Busca pelo telefone E.164 do Contato; várias Pessoas do CRM com o mesmo telefone → a atualizada mais recentemente, com a duplicata anotada na Operação; nenhuma → criada na primeira escrita, nunca por uma leitura.
- **Lock por Contato** em toda operação que pode criar Vínculo, para que escritas concorrentes do mesmo Contato não criem duas Pessoas do CRM.
- Ao nascer o Vínculo, a primeira escrita leva todos os Campos do Contato mapeados para o CRM. Nome e telefone da Pessoa do CRM só são preenchidos quando vazios.
- A troca de telefone da Pessoa do CRM no CRM não desfaz o Vínculo.
- **Responsável**: uma Pessoa do CRM criada pela plataforma recebe o Responsável padrão da configuração do CRM. Um Negócio criado pela plataforma herda o Responsável da Pessoa do CRM e, se ela não tem, recebe o Responsável padrão.
- Apagar um Contato na plataforma apaga o Vínculo e os Negócios vinculados dele, nunca a Pessoa do CRM.

### Registrar Contatos atendidos

- Opção na configuração do CRM, desligada por padrão, com um mínimo de mensagens recebidas do Contato configurável (padrão 2).
- Quando ligada, depois de uma resposta do Agente num Canal, se o Contato ainda não tem Vínculo e já mandou o mínimo de mensagens, é enfileirada uma Operação que garante a Pessoa do CRM.
- Não vale para o Playground nem para o WhatsApp de testes.

### Ações do Agente

- Quatro Tools, montadas pelo Agente só quando o CRM está conectado e a Ação está ligada na Versão do agente em uso, no mesmo lugar em que as Tools da Agenda são montadas hoje:
  - **Consultar ficha no CRM**: leitura síncrona com timeout de cerca de 5 segundos. Devolve os Campos do CRM marcados como visíveis ao Agente, os Negócios abertos (Funil de vendas, Etapa do funil, valor) e o nome do Responsável. Nunca devolve Atividades. Sem Vínculo, faz a busca por telefone sem criar nada; estourando o tempo, devolve "ficha indisponível" e o Agente segue.
  - **Garantir Negócio**: recebe título e, opcionalmente, valor estimado; usa o Funil de vendas e a Etapa inicial configurados no Agente. Busca ao vivo um Negócio aberto da Pessoa do CRM naquele Funil de vendas; havendo, atualiza; não havendo, cria. Garante o Vínculo antes.
  - **Mover Negócio de Etapa**: recebe uma das Etapas liberadas do Agente; qualquer outra é recusada à Tool com uma mensagem que o Agente entende. Age sobre o Negócio aberto do Funil de vendas do Agente; sem Negócio aberto, responde isso ao Agente.
  - **Registrar nota**: recebe um texto e registra uma Atividade de nota na Pessoa do CRM e, havendo, no Negócio aberto do Funil de vendas do Agente.
- **Escritas são enfileiradas**: a Tool cria a Operação do CRM, enfileira e devolve ao Agente que o pedido foi registrado. A falha posterior aparece nas Operações do CRM.
- **Configuração no Rascunho do agente**: Ações ligadas, Funil de vendas, Etapa inicial e Etapas liberadas com a descrição de quando mover. Ganho e perdido não podem ser Etapas liberadas; Etapas comerciais podem, e a tela sugere uma delas como passagem para o time comercial.
- **Padrão**: ao conectar o CRM, e ao criar um Agente com CRM conectado, as quatro Ações vêm ligadas no Rascunho dos Agentes com Objetivo Vendas ou Qualificação, com o primeiro Funil de vendas e a sua primeira Etapa do funil. Desconectar desliga as Ações nas Versões e Rascunhos, como a Loja faz.
- **Contexto automático**: a cada turno, o Agente recebe no prompt, para cada Negócio vinculado aberto do Contato, o Funil de vendas, a Etapa do funil, a Situação e o nome do Responsável. O valor não entra. Essa leitura é local, sem chamar a API.
- **Playground**: consultar ficha é real; garantir Negócio, mover de Etapa e registrar nota são simulados e devolvem o que teria sido enviado, sem criar Operação do CRM.

### Etapa comercial e Pausa da IA

- É a Pausa da IA já existente (ADR 0010), com **origem CRM**. Diferenças em relação à pausa manual:
  - vale em todas as Conversas do Contato, em todos os Canais;
  - não tem prazo;
  - marca o Contato como aguardando atendimento e avisa a Equipe, como na Transferência para humano.
- **Regra de estado, reavaliada a cada mudança de Negócio vinculado**: o Contato deve estar pausado pelo CRM se, e só se, algum Negócio vinculado aberto dele está numa Etapa comercial.
  - Passou de nenhum para algum: pausa em todas as Conversas do Contato e avisa.
  - Passou de algum para nenhum (saiu da Etapa comercial, foi ganho, perdido ou apagado): encerra a pausa com origem CRM e o Agente volta.
  - Continua em algum: nada muda.
- **Devolução manual** com o Negócio ainda numa Etapa comercial encerra a pausa e vale até a próxima passagem de nenhum para algum.
- Uma pausa manual já existente numa Conversa não é substituída nem encerrada pela regra do CRM.
- Uma mudança nas Etapas comerciais da configuração reavalia todos os Contatos com Negócios vinculados.

### Resumo do atendimento

- Desligável na configuração do CRM; ligado por padrão ao conectar.
- Depois de 30 minutos sem mensagem do Contato num Canal, se ele tem Vínculo e mandou pelo menos 2 mensagens desde o último Resumo daquele Canal, gera um Resumo com o modelo `gpt-5.6-luna` e registra como Atividade de nota na Pessoa do CRM e no Negócio aberto mais recente, se houver.
- O Resumo inclui mensagens do Contato, do Agente e da Equipe. Cada janela gera um Resumo novo; nunca reescreve o anterior.
- O custo em IA fica na Operação do CRM do Resumo.

### Mapeamento de Campos

- Um par por Campo do Contato, com direção única: da plataforma para o CRM (padrão) ou do CRM para a plataforma. Bidirecional não existe nesta versão.
- Só pares de tipo compatível são aceitos. Campo de escolha para campo de escolha exige que cada opção do Campo do Contato tenha uma opção correspondente.
- Plataforma → CRM: a mudança do valor de um Campo do Contato mapeado gera uma Operação de atualização da Pessoa do CRM se já houver Vínculo; sem Vínculo, nada acontece até o Vínculo nascer.
- CRM → plataforma: vem pelo webhook de Pessoa do CRM atualizada.
- Campo do Contato sensível tem o valor mascarado na Operação do CRM.

### Operações do CRM, filas e retenção

- **Supervisor novo no Horizon**, separado do que responde o WhatsApp, com três filas em ordem de prioridade:
  - `crm-webhooks`: avisos recebidos; jobs curtos, únicos por (CRM, recurso), com debounce;
  - `crm-operations`: escritas; 5 tentativas com backoff exponencial;
  - `crm-maintenance`: carga de metadados, Resumos do atendimento, reavaliação de Etapas comerciais e expiração de retenção.
- **Limite de requisições por CRM**, com o valor declarado pelo adapter. Chegar ao limite, ou receber pedido de espera do provedor, reenfileira respeitando o tempo indicado sem gastar tentativa.
- Acesso recusado renova o token uma vez e tenta de novo; persistindo, o CRM entra em erro.
- Recusa definitiva falha a Operação na hora, com motivo legível.
- **Retenção com o CRM em erro**: Operações novas ou em andamento ficam pendentes e retidas; ao reconectar, são enviadas na ordem em que foram criadas; passadas 72 horas, falham com motivo.
- **Reenvio**: "tentar de novo" numa Operação com falha cria uma nova tentativa com a origem reenvio.
- Operações ficam guardadas por 30 dias; uma limpeza diária apaga as mais antigas.
- Leituras síncronas do Agente e da ficha do Inbox não passam por fila nem viram Operação.

### Metadados

- Carregados ao conectar, ao abrir a tela do CRM (se a última carga tem mais de alguns minutos), por um comando diário e quando uma Operação falha por referência inexistente.
- Depois de cada carga, a configuração é conferida: Funil de vendas, Etapa do funil ou Campo do CRM referenciado por um Agente, por um mapeamento ou pelas Etapas comerciais e que não existe mais deixa o CRM em atenção, com a lista do que quebrou e onde. Corrigir a configuração tira o CRM de atenção.

### Ficha do Contato no Inbox

- Bloco do CRM na ficha: situação do Vínculo com atalho "abrir no CRM", Negócios abertos lidos ao vivo ao abrir a ficha (com cache curto e, se o CRM não responde, os Negócios vinculados guardados), as últimas Operações do CRM do Contato, a indicação de Pausa da IA pelo CRM com o Negócio que a causou, e o botão "desfazer Vínculo".
- Desfazer Vínculo apaga o Vínculo e os Negócios vinculados daquele Contato e encerra uma pausa com origem CRM; a próxima necessidade refaz a busca.

### Avisos à Equipe

- Avisos in-app, em tempo real pelo broadcasting já usado no Inbox, para quem tem acesso ao Inbox da Empresa: Negócio vinculado ganho, Negócio vinculado perdido (com Motivo de perda) e IA pausada pelo CRM. Mudança de Etapa do funil comum não avisa. Sem e-mail nesta versão.

### Tela do CRM

- Página do tipo `crm` na tela de Integrações, com: catálogo dos Provedores de CRM em que só o RD Station CRM conecta e os demais aparecem como "em breve"; conexão e reconexão por OAuth; estado do CRM com motivo; conta conectada; Etapas comerciais; Campos do CRM visíveis ao Agente; "Registrar Contatos atendidos" e o mínimo de mensagens; Resumo do atendimento; mapeamento de Campos; Operações do CRM com filtro por falha e reenvio; desconectar com aviso do que será apagado.
- Só Owner e Admin configuram; Member não vê as ações de configuração.

## Testing Decisions

- **Um bom teste observa comportamento externo**: o que sai para o RD Station CRM (requisições fingidas), o que muda no banco e o que o Agente, o Inbox ou a tela devolvem. Nada de testar jobs, locks ou classes internas isoladamente; filas rodam de forma síncrona nos testes.
- **Seam principal — fronteira HTTP com o RD Station CRM:** a API do RD é fingida com respostas de fixture e requisições inesperadas são proibidas. Os testes entram pelos pontos públicos: endpoint de webhook do CRM, Tools do Agente, controllers da tela do CRM e da ficha do Inbox, e os comandos agendados. Prior art: os testes de agendamento do Agente (Tools chamadas diretamente com o Google fingido) e os testes de conexão da Agenda para OAuth e renovação de token; os testes de webhook do WhatsApp para assinatura, descarte e enfileiramento.
- **Seam secundária — contrato do adapter de Provedor de CRM:** um adapter falso, registrado no lugar do real, cobre o Motor de CRM sem RD: Vínculo e duplicatas, lock contra criação concorrente, garantir Negócio, Etapa comercial e Pausa da IA, retenção de 72 horas, categorias de erro, capacidades escondidas e desconexão. É a prova de que o Motor não conhece o RD. É a única seam nova.
- **Seam de IA:** os fakes do SDK de IA cobrem o Resumo do atendimento (quando gera, quando não gera, o que entra) e o contexto automático do Agente (Negócios vinculados no prompt, sem valor).
- **Suíte de conformidade do adapter:** fixtures reais de respostas e webhooks do RD Station CRM, parametrizadas, com asserções escritas sobre o canônico e não sobre o RD. É a suíte que Pipedrive e HubSpot vão ter que passar. Prior art: a suíte de conformidade de webhooks do WhatsApp.
- **Comportamentos que precisam de teste:**
  - conectar, recusar autorização, renovar token sob concorrência (o refresh token antigo nunca é reutilizado), renovação recusada leva a erro e aviso;
  - webhook com chave inválida, CRM desconhecido, aviso válido, debounce de avisos repetidos;
  - Vínculo por telefone em formatos variados, duplicata escolhe a mais recente, nenhuma encontrada não cria na leitura e cria na escrita, duas escritas concorrentes criam uma Pessoa do CRM, Pessoa apagada desfaz, Negócio de Pessoa sem Vínculo liga a Contato existente e nunca cria Contato;
  - cada Tool: habilitada só com CRM conectado e Ação ligada, garantir Negócio atualiza o aberto em vez de duplicar, mover recusa Etapa não liberada, consultar ficha esconde Campos não visíveis e Atividades, timeout devolve ficha indisponível;
  - Etapa comercial: entrada pausa todas as Conversas e avisa, saída, ganho e perda retomam, devolução manual vale até a próxima entrada, vários Negócios, pausa manual preservada, Agente movendo para Etapa comercial pausa a si mesmo;
  - mapeamento: tipos incompatíveis recusados, envio só com Vínculo, envio de todos os campos no nascimento do Vínculo, direção CRM → plataforma pelo webhook, nome e telefone não sobrescritos;
  - Operações: retry temporário, espera por limite sem gastar tentativa, falha definitiva, retenção e expiração com CRM em erro, reenvio, mascaramento, limpeza de 30 dias;
  - metadados: referência quebrada leva a atenção com a lista, correção tira de atenção;
  - Playground: leitura real, escrita simulada sem Operação;
  - desconectar remove webhooks no RD, apaga estado local, desliga Ações e não chama nenhuma remoção de registro no CRM;
  - autorização: Member não conecta, não configura, não reenvia.

## Out of Scope

- CRM nos Fluxos: Nó de CRM, Gatilho de CRM e qualquer Fluxo iniciado por evento do CRM (ADR 0015).
- Mensagem iniciada pelo Agente a partir de evento do CRM, como boas-vindas após Negócio ganho.
- Criar Contato a partir de Pessoa do CRM.
- Espelho de Pessoas do CRM, Organizações do CRM ou Atividades (ADR 0014).
- RD Station Marketing.
- Pipedrive, HubSpot, Kommo, Salesforce e Ploomes como adapters funcionais — só o contrato validado para os dois primeiros.
- Produto, proposta, reunião, campanha, Lead do Pipedrive e ticket do HubSpot.
- Mapeamento bidirecional de Campos.
- Marcar Negócio como ganho ou perdido pelo Agente.
- Rodízio de Responsáveis.
- Tarefas no CRM na Transferência para humano.
- Vincular manualmente um Contato a outra Pessoa do CRM (só desfazer).
- Avisos por e-mail.
- Mais de um CRM por Empresa.

## Further Notes

- **Correspondência entre Provedores de CRM**, para validar o contrato antes de construir os próximos adapters:

  | Canônico | RD Station CRM | Pipedrive | HubSpot |
  | --- | --- | --- | --- |
  | Pessoa do CRM | contact | person | contact |
  | Organização do CRM | organization | organization | company |
  | Negócio | deal | deal (Lead fica fora) | deal |
  | Funil de vendas / Etapa do funil | pipeline / stage | pipeline / stage | pipeline / dealstage |
  | Situação | campo de ganho/perda + motivo | status open/won/lost | Etapas `closedwon`/`closedlost` traduzidas pelo adapter |
  | Atividade de nota | anotação | note | note (engagement) |
  | Responsável | user | user | owner |
  | Campo do CRM | custom field | custom field (chave em hash) | property |
  | Autenticação do webhook | header e chave definidos pela plataforma | basic auth por webhook | assinatura v3 do app + `portalId` no corpo |

- A spec de `.scratch/fluxos-integracoes/spec.md` previa conectores curados compilando para o Nó de Requisição HTTP e citava CRM como candidato; o ADR 0015 registra por que o CRM não segue esse caminho.
- O limite de requisições da API v2 do RD Station CRM não está na documentação de autenticação; o adapter declara um valor conservador até ser confirmado.
- O glossário do CRM está em `CONTEXT.md`, seção CRM; a Pausa da IA ganhou a origem CRM.

## Comments

- 2026-09-29: O Rascunho do agente (ADR 0011) ainda não existe no código. Enquanto não existir, a configuração das Ações do CRM segue o mesmo mecanismo em que o Agente é ligado aos Calendários agendáveis, e entra no Rascunho quando ele for implementado. Tickets em `issues/01` a `issues/15`.
