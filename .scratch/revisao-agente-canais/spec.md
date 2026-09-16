# Spec: Revisão do Agente, dos Canais e da Base de conhecimento

Status: ready-for-agent

Revisa as issues 11 (Agentes CRUD), 12 (Playground), 13 (Canais WhatsApp) e 15 (Catálogo de Integrações) da fase 1, já entregues, a partir do uso real das telas.

## Problem Statement

A fase 1 entregou as telas funcionando, mas o uso revelou que a forma delas não corresponde ao que o produto é.

O **Agente** é o centro do produto e ficou espremido num modal de 900 linhas, separado do **Playground** por uma navegação. Quem está escrevendo uma Instrução não consegue ver o efeito dela sem salvar, sair da tela de edição e entrar no chat — e o aviso de "alterações não salvas" existe justamente porque esse caminho é errado. O resultado é que ninguém itera: o cliente preenche o formulário uma vez, testa uma vez, e não volta.

O Agente também carrega campos que não fazem nada: os gatilhos de transferência são preenchidos pelo usuário e nunca entram no prompt.

A tela de **Canais** não é uma tela de canais: é a configuração do WhatsApp com um card de "Chat no site" enfiado dentro dela. Não existe lugar onde a Empresa veja quais meios de atendimento existem, quais estão conectados e qual Agente responde em cada um. E o vínculo Agente ↔ meio de atendimento mora dentro da tabela do WhatsApp, o que significa que o segundo Tipo de canal vai reinventar tudo de novo.

Dentro de uma **Coleção**, os Documentos são cards soltos sem paginação, filtro ou ordenação — a listagem carrega o conteúdo extraído de todos eles de uma vez, o que numa Empresa com Documentos de arquivo significa megabytes por navegação. E o link de voltar para a Base de conhecimento aponta para a própria tela de Documentos, então não funciona.

## Solution

**O Agente vira uma tela só.** A lista de Agentes é uma tabela; abrir ou criar um Agente leva a uma página com o formulário à esquerda e o Playground à direita. O chat roda sobre o **rascunho** do formulário, não sobre a configuração salva: ajusta o tom, manda uma mensagem, ajusta de novo, salva quando gostou. Funciona inclusive para um Agente que ainda não existe, então "criar" e "testar" são o mesmo gesto. Os gatilhos de transferência são removidos.

**O Canal vira uma entidade de verdade.** A tela de Canais passa a listar os Tipos de canal (WhatsApp, Chat no site, Instagram Direct) em linhas largas que mostram estado real: status da conexão, número conectado e qual Agente responde. Só o WhatsApp é configurável hoje; os outros dois aparecem como "Em breve". A configuração do WhatsApp ganha página própria, sem o card de Chat no site dentro dela, e passa a separar **Conta do WhatsApp** (credenciais de app da Meta) de **Número do WhatsApp** (identidade e qualidade), com o Canal se prendendo ao número — que é quem recebe o webhook e atende. O mesmo Agente pode responder em vários Canais.

**Os Documentos viram tabela.** Paginada no servidor, com busca por título, filtro por tipo e por status, e ordenação. O conteúdo sai da listagem e é buscado sob demanda quando o Documento é aberto para edição. O link de voltar passa a levar para a Base de conhecimento.

## User Stories

### Lista de Agentes

1. As a pessoa da Empresa, I want to ver meus Agentes numa tabela com nome, objetivo, número de Coleções, Canais vinculados e status, so that eu compare os Agentes sem abrir cada um
2. As a Owner ou Admin, I want to criar um Agente a partir de um botão na lista que me leva para uma página inteira, so that eu não preencha uma configuração longa dentro de um modal
3. As a Owner ou Admin, I want to editar um Agente clicando na linha dele, so that eu chegue rápido na configuração
4. As a Owner ou Admin, I want to ativar, desativar e excluir um Agente por um menu na própria linha, so that a tabela não fique cheia de botões
5. As a Owner ou Admin, I want to confirmar antes de excluir um Agente, so that eu não perca uma configuração por engano
6. As a pessoa da Empresa, I want to ver na lista quais Agentes estão vinculados a algum Canal, so that eu saiba quais estão realmente atendendo
7. As a Member, I want to ver a lista sem as ações de gerenciamento, so that eu acompanhe a configuração sem risco de alterá-la

### Editor do Agente com Playground

8. As a Owner ou Admin, I want to ver o formulário do Agente e o chat de teste lado a lado na mesma tela, so that eu veja o efeito de cada mudança sem trocar de contexto
9. As a Owner ou Admin, I want to que o chat use exatamente o que está no formulário agora, mesmo sem salvar, so that eu teste uma ideia antes de me comprometer com ela
10. As a Owner ou Admin, I want to conversar com um Agente que ainda não foi salvo, so that eu descubra se a configuração presta antes de criá-la
11. As a Owner ou Admin, I want to que a mensagem de boas-vindas no chat se atualize enquanto eu a escrevo, so that eu veja como ela chega para o cliente
12. As a Owner ou Admin, I want to que o histórico da conversa não seja apagado quando eu mudo a configuração, so that eu compare a resposta antiga com a nova
13. As a Owner ou Admin, I want to reiniciar a conversa quando eu quiser, so that eu teste um cenário do zero
14. As a pessoa da Empresa, I want to ver quais Trechos da Base de conhecimento o Agente consultou, logo abaixo da resposta que eles justificaram, so that eu entenda de onde veio cada afirmação
15. As a pessoa da Empresa, I want to que o painel de fontes venha recolhido e eu o abra quando quiser, so that o chat continue legível
16. As a pessoa da Empresa, I want to ser avisado quando o Agente respondeu sem consultar a Base de conhecimento, so that eu saiba que a resposta veio só dos dados da Empresa
17. As a Owner ou Admin, I want to continuar na mesma tela depois de salvar uma edição, so that eu siga testando
18. As a Owner ou Admin, I want to ser levado ao editor do Agente que acabei de criar, so that eu continue ajustando sem procurá-lo na lista
19. As a Owner ou Admin, I want to ver erros de validação em português junto do campo errado, so that eu corrija sem adivinhar
20. As a pessoa da Empresa, I want to usar a tela num monitor pequeno com formulário e chat em abas, so that ela continue utilizável fora do desktop
21. As a Member, I want to conversar com o Agente no Playground sem poder editar a configuração, so that eu avalie o atendimento sem alterá-lo
22. As a pessoa da Empresa, I want to receber uma mensagem amigável quando o provedor de IA falhar ou demorar demais, so that eu não veja um erro técnico
23. As a pessoa da Empresa, I want to ser avisada em português quando eu passar do limite de mensagens por hora, so that eu entenda por que parou
24. As a Owner ou Admin, I want to não ver mais o campo de gatilhos de transferência, so that eu não perca tempo preenchendo algo que não tem efeito

### Agente e Canais

25. As a Owner ou Admin, I want to vincular um Agente a um Canal, so that ele atenda as conversas que chegam ali
26. As a Owner ou Admin, I want to usar o mesmo Agente em mais de um Canal, so that eu não precise manter configurações duplicadas
27. As a Owner ou Admin, I want to desativar um Agente e com isso parar o atendimento nos Canais dele, so that eu interrompa o atendimento sem apagar a configuração do Canal
28. As a Owner ou Admin, I want to continuar testando um Agente inativo no Playground, so that eu o conserte antes de ligá-lo de novo
29. As a Owner ou Admin, I want to que excluir um Agente não quebre o Canal, so that a conexão continue de pé enquanto eu escolho outro Agente
30. As a Owner ou Admin, I want to ver em destaque quando um Canal está conectado mas sem Agente vinculado, so that eu perceba que ninguém está atendendo

### Catálogo de Canais

31. As a pessoa da Empresa, I want to ver uma lista dos meios de atendimento disponíveis, so that eu saiba onde posso colocar meus Agentes
32. As a pessoa da Empresa, I want to ver em cada linha o status da conexão, so that eu saiba o que está funcionando sem abrir nada
33. As a pessoa da Empresa, I want to ver o número conectado e o Agente que responde na linha do WhatsApp, so that eu confirme a configuração de relance
34. As a pessoa da Empresa, I want to ver o motivo do erro na própria linha quando um Canal está com problema, so that eu descubra que o atendimento parou sem entrar na configuração
35. As a Owner ou Admin, I want to ir da linha do WhatsApp direto para a configuração dele, so that eu conecte ou ajuste o canal
36. As a pessoa da Empresa, I want to ver Chat no site e Instagram Direct marcados como "Em breve" e desabilitados, so that eu conheça a direção do produto sem tentar configurar algo que não existe
37. As a pessoa da Empresa, I want to que a tela de Canais tenha uma densidade diferente da tela de Integrações, so that eu perceba que uma é operação e a outra é catálogo

### Configuração do WhatsApp

38. As a Owner ou Admin, I want to informar as credenciais da Conta do WhatsApp e o Número num formulário único, so that eu conecte o canal numa tacada
39. As a Owner ou Admin, I want to ver as credenciais agrupadas em Conta e Número, so that eu entenda o que é de app e o que é do número
40. As a Owner ou Admin, I want to nunca receber de volta o access token nem o app secret que salvei, so that meus segredos não trafeguem para o navegador
41. As a Owner ou Admin, I want to salvar uma edição sem redigitar os segredos, so that eu ajuste um campo sem ter que buscar as credenciais de novo
42. As a Owner ou Admin, I want to copiar a URL do webhook e o verify token, so that eu configure o webhook no painel da Meta
43. As a Owner ou Admin, I want to testar a conexão e ver o número, o nome verificado e a qualidade que a Meta devolveu, so that eu confirme que as credenciais funcionam
44. As a Owner ou Admin, I want to ver a mensagem de erro traduzida quando o teste falha, so that eu saiba o que corrigir
45. As a Owner ou Admin, I want to ver quando o webhook foi verificado pela Meta, so that eu saiba se falta esse passo
46. As a Owner ou Admin, I want to escolher qual Agente responde neste Canal, so that o atendimento use a configuração certa
47. As a Owner ou Admin, I want to remover a configuração do WhatsApp inteira, so that eu recomece ou desconecte o número
48. As a Owner ou Admin, I want to ser impedido de configurar um segundo WhatsApp na mesma Empresa, so that não fique ambíguo qual número atende
49. As a Member, I want to ver a configuração sem poder alterá-la, so that eu conheça o estado do canal
50. As a pessoa da Empresa, I want to não ver mais o card de Chat no site dentro da configuração do WhatsApp, so that cada canal viva no seu lugar

### Webhook do WhatsApp

51. As a Meta, I want to completar o handshake de verificação contra o verify token da Conta do WhatsApp, so that o webhook fique registrado
52. As a Meta, I want to entregar eventos assinados que sejam validados contra o app secret da Conta, so that eventos forjados sejam recusados
53. As a plataforma, I want to resolver o Número do WhatsApp que recebeu a mensagem e chegar ao Canal e ao Agente dele, so that a fase seguinte tenha por onde responder
54. As a plataforma, I want to recusar um evento cujo número não pertence a nenhuma Empresa, so that nada vaze entre contas

### Documentos numa Coleção

55. As a pessoa da Empresa, I want to ver os Documentos da Coleção numa tabela com título, tipo, status, tamanho e data de atualização, so that eu compare muitos Documentos de uma vez
56. As a pessoa da Empresa, I want to navegar entre páginas de Documentos, so that a tela não trave numa Coleção grande
57. As a pessoa da Empresa, I want to buscar um Documento pelo título, so that eu encontre um específico sem rolar a lista
58. As a pessoa da Empresa, I want to filtrar por tipo de Documento, so that eu veja só arquivos, só FAQs, só textos ou só URLs
59. As a pessoa da Empresa, I want to filtrar por status, so that eu encontre rápido o que falhou no processamento
60. As a pessoa da Empresa, I want to ordenar por título, tamanho ou data de atualização, so that eu organize a listagem do meu jeito
61. As a pessoa da Empresa, I want to que meus filtros fiquem na URL, so that eu possa recarregar a página ou compartilhar o link sem perder o estado
62. As a pessoa da Empresa, I want to ver o status de um Documento em processamento se atualizar sozinho, so that eu saiba quando ele ficou pronto
63. As a pessoa da Empresa, I want to que a atualização automática não me tire da página nem perca meus filtros, so that eu continue trabalhando enquanto ela acontece
64. As a pessoa da Empresa, I want to que a atualização automática pare quando não houver nada processando na página, so that a tela não fique fazendo requisições à toa
65. As a Owner ou Admin, I want to editar, baixar, mover, reprocessar e excluir um Documento por um menu na linha, so that as ações não poluam a tabela
66. As a Owner ou Admin, I want to abrir um Documento para edição e ver o conteúdo dele carregado, so that eu corrija o texto ou as perguntas da FAQ
67. As a pessoa da Empresa, I want to voltar para a Base de conhecimento pelo link acima do título da Coleção, so that eu troque de Coleção sem usar o botão do navegador
68. As a pessoa da Empresa, I want to continuar vendo o uso de armazenamento da Empresa acima da tabela, so that eu saiba quanto espaço me resta
69. As a pessoa da Empresa, I want to ver a mensagem de erro do Documento que falhou, so that eu entenda por que ele não ficou pronto
70. As a pessoa da Empresa, I want to ver um estado vazio claro quando o filtro não devolve nada, so that eu não ache que meus Documentos sumiram

### Dashboard e navegação

71. As a pessoa da Empresa, I want to que o item do checklist sobre o WhatsApp me leve direto para a configuração dele, so that eu não pare no catálogo
72. As a pessoa da Empresa, I want to que o item do checklist sobre vincular Agente me leve para onde o vínculo é feito, so that eu complete a ativação
73. As a pessoa da Empresa, I want to que o resumo do WhatsApp no dashboard reflita o status do Canal, so that o número na tela seja verdade

## Implementation Decisions

### Canal como entidade (ver ADR 0001)

- Nova tabela `channels`, fina: Empresa, Tipo de canal, Agente (nulável), status e último erro. É onde vive o vínculo Agente ↔ meio de atendimento e o estado do atendimento.
- `ChannelType` é um enum com WhatsApp, Chat no site e Instagram Direct, cada um com label, descrição, ícone, disponibilidade e URL de configuração, expondo um `catalog()` estático — mesmo padrão já usado por `CrmProvider` no catálogo de Integrações. O catálogo da tela é gerado do enum, não do banco.
- `ChannelStatus` é um enum genérico com "Não conectado", "Conectado" e "Erro", gravado explicitamente nas transições: salvar credenciais deixa o Canal em "Não conectado"; só um teste de conexão bem-sucedido o move para "Conectado"; falha de teste o move para "Erro" e preenche o último erro. `WhatsAppConnectionStatus` é removido e absorvido por `ChannelStatus`.
- `unique(team_id, type)`: uma Empresa tem no máximo um Canal de cada Tipo de canal. Suportar dois números é remover esse índice depois.
- Os dados específicos do WhatsApp são divididos em duas tabelas. `whatsapp_accounts` guarda o que é de app: método de conexão (`WhatsAppConnectionMethod`, hoje sempre manual), WABA ID, access token e app secret (ambos com cast criptografado), verify token (único), quando o webhook foi verificado e quando foi o último teste. `whatsapp_phone_numbers` guarda o que é do número: phone number ID (único globalmente), número exibido, nome verificado e qualidade.
- O Canal se prende ao **número**, não à conta: é o número que recebe o webhook e atende. A conta pertence ao número via relação, e a resolução no webhook é número → Canal → Agente.
- `WhatsAppNumber` deixa de existir; a tabela antiga é dropada sem backfill. Nenhum ambiente tem dado real.
- Excluir a configuração do WhatsApp apaga o Canal em cascata. Como o catálogo vem do enum, não existem linhas órfãs.
- Excluir um Agente anula o vínculo no Canal e deixa o Canal "Conectado" sem Agente; a linha do catálogo destaca esse estado. Não vira "Erro": as credenciais estão boas, falta configuração.
- Nova `ChannelPolicy` no lugar de `WhatsAppNumberPolicy`, mantendo a permissão `channel:manage` de Owner e Admin.
- A rota de catálogo é servida por um `ChannelController` próprio; a configuração do WhatsApp ganha rota e nome próprios, deixando de ocupar a rota raiz de canais. O binding da configuração é feito pelo Canal, recusando Canal de outro Tipo.
- O Form Request do WhatsApp valida conta e número juntos e mantém as duas regras que já existiam: segredos opcionais na edição (branco preserva o valor salvo) e recusa de um segundo WhatsApp na mesma Empresa.

### Agente

- `transfer_triggers` é removido por completo: coluna, validação, cast, campo do formulário e a menção no comentário do template de instruções.
- O Agente ganha relação com Canais (um Agente, muitos Canais), substituindo a relação um-para-um com o número do WhatsApp.
- `is_active` passa a ter efeito de domínio: Agente inativo não responde nos Canais vinculados, mas continua utilizável no Playground.
- As rotas de Agente saem do prefixo de conhecimento e passam a viver sob o próprio recurso, com os controllers movidos para um namespace de Agentes. Os nomes de rota não mudam, então a sidebar e o Wayfinder acompanham.
- O modal de formulário é removido. `create` e `edit` renderizam a mesma página de editor, com formulário e Playground na mesma tela, colapsando em abas em telas estreitas.
- `store` redireciona para o editor do Agente recém-criado; `update` permanece na página e apenas emite o toast.
- A lista de Agentes vira tabela com menu de ações por linha.

### Playground sobre rascunho

- O endpoint de streaming deixa de ser por Agente e passa a ser único, sem binding de modelo: recebe a configuração completa do formulário mais o histórico da conversa.
- A configuração recebida é validada com as mesmas regras do Form Request de Agente, incluindo a checagem de que as Coleções escolhidas pertencem à Empresa do usuário. A autorização deixa de ser "ver este Agente" e passa a ser "pertencer a uma Empresa".
- O agente de IA é construído a partir de uma instância **não persistida** do model Agente, com a Empresa e as Coleções setadas como relações. O template de instruções e a ferramenta de busca na Base de conhecimento não mudam.
- O limite de uso sobe de 30 para 60 mensagens por hora por usuário, porque o fluxo de rascunho convida a iterar.
- A conversa continua descartável: nada é persistido, o estado vive no cliente e o aviso de "alterações não salvas" deixa de existir.

### Documentos

- A listagem de Documentos passa a ser paginada no servidor, 20 por página, com busca por título, filtro por tipo, filtro por status e ordenação por título, tamanho ou data de atualização, todos carregados da query string e preservados na navegação.
- O conteúdo extraído e os itens de FAQ saem do payload da listagem. Um novo endpoint devolve um Documento completo, consumido quando o modal de edição abre.
- O polling passa a ser condicional à página renderizada: o servidor só sinaliza polling quando existe Documento pendente ou em processamento naquela página, e o recarregamento é parcial, preservando estado e rolagem.
- A tabela usa o data table do shadcn em modo manual — paginação, filtro e ordenação são do servidor, a biblioteca cuida de colunas, cabeçalho e visibilidade. Isso adiciona `@tanstack/react-table` às dependências, aprovado.
- O formulário de Documento continua num modal. Sheets não são usados para este tipo de formulário neste projeto.
- O link de voltar da tela de Documentos passa a apontar para a listagem de Coleções. Hoje ele aponta para a própria tela de Documentos, e é por isso que não funciona.

### Vocabulário

- `CONTEXT.md` na raiz é a fonte do vocabulário: Empresa, Equipe, Papel, Agente, Instrução, Playground, Agente inativo, Coleção, Base de conhecimento, Documento, Trecho, Canal, Tipo de canal, Conta do WhatsApp, Número do WhatsApp e Integração. Uma Coleção nunca é chamada de "base de conhecimento" na interface; "Base de conhecimento" é o nome da seção.
- A decisão de modelagem dos Canais está registrada no ADR 0001 e não deve ser revertida sem atualizá-lo.

## Testing Decisions

Um bom teste aqui exercita o comportamento externo pela rota HTTP e afirma o que o usuário obtém: a página renderizada e suas props, o estado do banco depois da ação, o redirecionamento, o toast, o código de status. Não afirma como o controller chegou lá, quais métodos foram chamados nem a forma interna das classes. Um teste que quebra ao renomear um método privado está errado.

**Seam único: teste de feature HTTP com asserções Inertia.** É o que a suíte inteira já usa e nenhum seam novo é introduzido.

Prior art, na ordem de utilidade:

- Configuração de canal, segredos mascarados, teste de conexão contra a Graph API com `Http::fake()` e permissões por Papel: os testes de WhatsApp existentes.
- Handshake e assinatura do webhook: os testes de webhook existentes.
- Streaming, fake do agente do `laravel/ai`, limite de uso e isolamento entre Empresas: os testes de Playground existentes.
- Listagem, validação, upload, quota e reprocessamento de Documentos: os testes de Documento existentes.
- Checklist e resumo: os testes de Dashboard existentes.

Cobertura esperada:

- **Canais**: catálogo renderiza os três Tipos de canal com disponibilidade correta; linha do WhatsApp mostra status, número e Agente; linha em erro mostra o último erro; Canal de outra Empresa não aparece e devolve não encontrado por URL; Member vê sem ações.
- **WhatsApp**: criação grava Canal, Conta e Número numa transação; edição sem segredos preserva os salvos; segredos nunca voltam nas props; teste bem-sucedido move o Canal para "Conectado" e grava número, nome verificado e qualidade; teste falho move para "Erro" e grava a mensagem traduzida; segundo WhatsApp na mesma Empresa é recusado; exclusão apaga Canal, Conta e Número; Member é bloqueado nas ações de escrita.
- **Webhook**: handshake casa pelo verify token da Conta e marca a verificação; evento assinado corretamente é aceito; assinatura inválida, número desconhecido e payload sem número são recusados.
- **Agente**: lista traz Canais vinculados; criação redireciona para o editor do novo Agente; atualização permanece na página; gatilhos de transferência não são mais aceitos nem devolvidos; excluir um Agente anula o vínculo do Canal sem apagá-lo; permissões por Papel; isolamento entre Empresas.
- **Playground**: streaming funciona com configuração de rascunho de um Agente que não existe no banco; o prompt renderizado reflete o rascunho e não a configuração salva — o fake do `laravel/ai` expõe o agente no prompt registrado, o que permite afirmar isso pelo seam HTTP; Coleção de outra Empresa é recusada na validação; Member consegue usar; erro do provedor vira mensagem amigável; limite de uso dispara na mensagem 61.
- **Documentos**: paginação devolve 20 por página com metadados; busca, cada filtro e cada ordenação; filtros preservados na navegação; conteúdo ausente da listagem e presente no endpoint de Documento; sinalização de polling ligada só quando a página tem Documento em processamento; Documento de outra Empresa devolve não encontrado.
- **Dashboard**: itens do checklist apontam para a configuração do WhatsApp; resumo lê o status pelo Canal.

Factories novas para Canal, Conta do WhatsApp e Número do WhatsApp, com estados para Canal conectado, Canal em erro e Canal sem Agente. A factory de número do WhatsApp antiga é removida junto do model.

## Out of Scope

- Fazer o Agente efetivamente responder mensagens recebidas no WhatsApp. O webhook continua validando e recusando sem processar, como na fase 1.
- Implementar Chat no site e Instagram Direct. Eles existem apenas como Tipos de canal marcados "Em breve" no catálogo.
- Suportar mais de um Número do WhatsApp por Empresa, ou Agentes diferentes por número. O índice único por Empresa e Tipo de canal permanece.
- Conectar o WhatsApp por embedded signup. O método de conexão continua manual; o enum já prevê o outro caso.
- Qualquer alteração nas Integrações de CRM. Elas seguem como catálogo "em breve" e são citadas só como contraste de densidade visual.
- Persistir conversas do Playground ou criar histórico de atendimento.
- Renomear Coleção para "Base de conhecimento" no código, nas rotas ou no banco.
- Paginar ou filtrar a listagem de Coleções e a de Agentes. Só a listagem de Documentos é paginada nesta rodada.
- Migrar dados existentes de `whats_app_numbers`. Não há backfill.

## Further Notes

- A ordem de execução é **Canais → Agente → Documentos → Dashboard e navegação**. Canais primeiro porque tem a migration destrutiva e porque a relação Agente ↔ Canais depende dela. Documentos é independente e fica por último porque colide com trabalho não commitado no working tree.
- O working tree tem alterações não commitadas dos Documentos de URL, incluindo a tela de Documentos, o controller e os testes. A refatoração da tabela é construída em cima delas, não ao lado.
- As telas de Canais e de Integrações compartilham a linguagem visual mas não a densidade: Canais é lista de linhas largas porque são coisas que você opera e que têm estado; Integrações é grid de cards com painel lateral porque são coisas que você ainda vai escolher.
- Todo texto de interface, mensagem de validação e label de enum é em português do Brasil, seguindo o que já existe.
- `wayfinder:generate` precisa rodar depois das mudanças de rota, e as importações de rota no frontend acompanham os novos nomes.
