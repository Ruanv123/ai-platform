# Spec: Fase 1: Plataforma de Agentes de IA para Atendimento

Status: ready-for-agent

## Problem Statement

Empresas brasileiras querem colocar agentes de IA para atender clientes no WhatsApp (e, depois, num popup de chat no site, estilo CloudHumans), com respostas baseadas no conhecimento real da empresa e com as oportunidades caindo automaticamente no pipeline do CRM que já usam.

Hoje não existe um lugar onde uma empresa consiga, sozinha e sem saber escrever prompts: cadastrar os dados do negócio, montar uma base de conhecimento, configurar um agente com instruções e regras de forma guiada, testar como ele responde e deixar o número oficial do WhatsApp conectado. Ferramentas de prompt livre dão liberdade demais (o cliente quebra o agente) e ferramentas de agência exigem que alguém de fora configure tudo.

A fase 1 precisa entregar a fundação da plataforma: a Empresa, a Base de conhecimento, os Agentes com Playground, a configuração do Canal WhatsApp, o catálogo de Integrações e o Dashboard. O agente respondendo em canais reais e os CRMs funcionando ficam para fases seguintes.

## Solution

Uma plataforma SaaS multi-tenant em que cada **Empresa** (o `Team` do sistema) tem sua própria conta isolada:

- Qualquer pessoa se cadastra e, antes de acessar o resto, passa por um **onboarding da Empresa**, que coleta os dados do negócio e cria a Empresa com ela como Owner. Depois ela convida a Equipe por e-mail.
- A Empresa monta uma **Base de conhecimento** organizada em **Coleções**, com documentos vindos de arquivos (PDF, DOCX, TXT), textos livres, FAQs e URLs. Os documentos são processados em segundo plano e ficam pesquisáveis por similaridade semântica.
- A Empresa cria **Agentes** através de um formulário estruturado (persona, objetivo, tom, instruções, regras, gatilhos de transferência, coleções usadas). O prompt final é montado pela plataforma a partir de um template controlado. O cliente só preenche os campos.
- Cada Agente tem um **Playground**: um chat de teste com streaming, que mostra os trechos da base de conhecimento que o agente consultou para responder.
- Em **Canais**, a Empresa conecta seu número do **WhatsApp Cloud API** (oficial da Meta) colando as credenciais, testa a conexão e vincula um Agente ao número. Nesta fase o número fica configurado, mas o agente ainda não responde mensagens.
- Em **Integrações**, a Empresa vê o catálogo de CRMs que estarão disponíveis (todos "em breve").
- O **Dashboard** mostra um checklist de ativação e um resumo do estado da conta, reservando espaço para as métricas de atendimento futuras.

## User Stories

### Cadastro, onboarding e Empresa

1. As a visitante, I want to criar uma conta com nome, e-mail e senha, so that eu possa começar a usar a plataforma sem depender de ninguém
2. As a novo usuário, I want to entrar direto na plataforma sem precisar confirmar o e-mail, so that o cadastro não trave o meu primeiro acesso
3. As a usuário recém-cadastrado sem Empresa, I want to ser levado automaticamente para o onboarding da Empresa, so that eu configure o negócio antes de qualquer outra coisa
4. As a usuário sem Empresa, I want to ser redirecionado para o onboarding quando tento acessar qualquer página da plataforma, so that eu não caia em telas vazias ou quebradas
5. As a usuário no onboarding, I want to informar nome fantasia, segmento, site, descrição do negócio, produtos/serviços, público-alvo, horário de atendimento, fuso horário e contatos públicos, so that os agentes conheçam a minha empresa
6. As a usuário no onboarding, I want to ver erros de validação claros em português, so that eu saiba exatamente o que corrigir
7. As a usuário que concluiu o onboarding, I want to me tornar Owner da Empresa criada, so that eu tenha controle total da conta
8. As a usuário que concluiu o onboarding, I want to ser levado ao Dashboard, so that eu veja os próximos passos
9. As a usuário que já tem Empresa, I want to não conseguir acessar o onboarding de novo nem criar uma segunda Empresa, so that cada conta tenha exatamente uma Empresa
10. As a Owner ou Admin, I want to editar os dados da Empresa em Configurações, so that eu mantenha as informações atualizadas quando o negócio mudar
11. As a Member, I want to visualizar os dados da Empresa sem poder editá-los, so that eu conheça o contexto sem arriscar alterações

### Equipe e convites

12. As a Owner ou Admin, I want to convidar pessoas por e-mail definindo o papel (Admin ou Member), so that minha equipe trabalhe na plataforma
13. As a pessoa convidada, I want to receber um e-mail com link de convite, so that eu consiga entrar na Empresa certa
14. As a pessoa convidada sem conta, I want to criar minha conta pelo link do convite e já cair dentro da Empresa, so that eu não passe pelo onboarding de uma Empresa nova
15. As a Owner ou Admin, I want to ser impedido de convidar um e-mail que já pertence a outra Empresa, so that ninguém fique vinculado a duas Empresas
16. As a Owner ou Admin, I want to ver e cancelar convites pendentes, so that eu controle quem ainda pode entrar
17. As a Owner, I want to alterar o papel dos membros e removê-los, so that eu gerencie o acesso da equipe
18. As a Owner, I want to não poder sair da Empresa, so that a conta nunca fique sem dono
19. As a Member, I want to sair da Empresa, so that eu deixe de ter acesso quando não fizer mais parte do time
20. As a Owner, I want to que a exclusão da Empresa não esteja disponível nesta fase, so that ninguém apague a conta inteira por engano

### Isolamento e permissões

21. As a usuário de uma Empresa, I want to nunca ver nem acessar agentes, coleções, documentos ou canais de outra Empresa, so that os dados do meu negócio fiquem protegidos
22. As a usuário, I want to receber "não encontrado" ao tentar abrir por URL um recurso de outra Empresa, so that nem a existência dele seja revelada
23. As a Owner ou Admin, I want to criar, editar e excluir Agentes, Coleções, Documentos e a configuração do WhatsApp, so that eu gerencie a operação
24. As a Member, I want to visualizar Agentes, Base de conhecimento, Canais, Integrações e Dashboard, so that eu acompanhe a configuração
25. As a Member, I want to não ver botões de ação que não posso executar e ser bloqueado se tentar via requisição direta, so that as permissões sejam respeitadas de ponta a ponta

### Base de conhecimento: Coleções

26. As a Owner ou Admin, I want to que toda Empresa comece com uma Coleção "Geral", so that eu possa adicionar documentos imediatamente
27. As a Owner ou Admin, I want to criar Coleções com nome e descrição, so that eu organize o conhecimento por assunto (vendas, suporte, políticas)
28. As a Owner ou Admin, I want to renomear e editar a descrição de uma Coleção, so that a organização acompanhe a evolução do conteúdo
29. As a Owner ou Admin, I want to excluir uma Coleção após confirmar vendo quantos documentos serão apagados, so that eu não apague conteúdo sem perceber
30. As a Owner ou Admin, I want to que a exclusão de uma Coleção apague em cascata seus documentos, trechos e arquivos, so that não sobre lixo nem espaço ocupado
31. As a Owner ou Admin, I want to ser impedido de excluir a Coleção "Geral", so that a Empresa sempre tenha ao menos uma Coleção
32. As a usuário, I want to ver a lista de Coleções com a quantidade de documentos de cada uma, so that eu entenda como a base está distribuída

### Base de conhecimento: Documentos

33. As a Owner ou Admin, I want to enviar arquivos PDF, DOCX ou TXT para uma Coleção, so that o agente aprenda com os materiais que já tenho
34. As a Owner ou Admin, I want to ser impedido de enviar arquivos acima de 20 MB ou de formato não suportado, com mensagem clara, so that eu saiba o motivo da recusa
35. As a Owner ou Admin, I want to criar um documento de texto livre com título e conteúdo, so that eu registre conhecimento que não está em nenhum arquivo
36. As a Owner ou Admin, I want to criar um documento de FAQ com vários pares de pergunta e resposta, so that o agente responda dúvidas frequentes com precisão
37. As a Owner ou Admin, I want to adicionar, editar, reordenar e remover pares de pergunta e resposta de um FAQ, so that eu mantenha o FAQ atualizado
38. As a Owner ou Admin, I want to editar documentos de texto livre e FAQ e ter o conteúdo reprocessado automaticamente, so that o agente use sempre a versão atual
39. As a Owner ou Admin, I want to adicionar uma URL de página para uma Coleção, so that o agente aprenda com o conteúdo do meu site
40. As a Owner ou Admin, I want to re-sincronizar manualmente um documento de URL, so that eu atualize o conteúdo quando a página mudar
41. As a Owner ou Admin, I want to substituir o arquivo de um documento, so that eu atualize um material sem perder a organização
42. As a Owner ou Admin, I want to mover um documento para outra Coleção, so that eu corrija a organização sem reenviar
43. As a Owner ou Admin, I want to excluir um documento, liberando o espaço que ele ocupava, so that eu remova conteúdo desatualizado
44. As a usuário, I want to ver o status de cada documento (pendente, processando, pronto, erro), so that eu saiba se ele já está disponível para os agentes
45. As a usuário, I want to ver a lista de documentos atualizando o status sem recarregar a página enquanto há processamento em andamento, so that eu acompanhe a ingestão
46. As a Owner ou Admin, I want to ver a mensagem de erro de um documento que falhou (ex.: "PDF sem texto extraível", "Página inacessível"), so that eu entenda e corrija o problema
47. As a Owner ou Admin, I want to reprocessar um documento com erro, so that eu tente de novo após uma falha temporária
48. As a usuário, I want to ver o tamanho e a data de atualização de cada documento, so that eu entenda o que ocupa espaço
49. As a usuário, I want to baixar o arquivo original de um documento enviado, so that eu confira o que foi carregado
50. As a usuário, I want to ver o uso de armazenamento da Empresa em relação à cota de 500 MB, so that eu saiba quanto espaço ainda tenho
51. As a Owner ou Admin, I want to ser impedido de adicionar conteúdo que ultrapasse a cota, com mensagem clara, so that eu saiba que preciso liberar espaço
52. As a Owner ou Admin, I want to que páginas de URL tenham menu, rodapé e avisos de cookies descartados na extração, so that o agente não aprenda lixo de navegação

### Agentes

53. As a Owner ou Admin, I want to criar um Agente informando o nome da persona, so that os clientes conversem com uma identidade consistente
54. As a Owner ou Admin, I want to escolher o objetivo do Agente (Atendimento/Suporte, Vendas, Qualificação/SDR, Agendamento), so that ele se comporte de acordo com a função
55. As a Owner ou Admin, I want to escolher o tom de voz (formal, amigável, descontraído), so that o Agente fale do jeito da minha marca
56. As a Owner ou Admin, I want to definir se o Agente usa emojis e o tamanho das respostas (curtas ou médias), so that o estilo combine com o WhatsApp e com meu público
57. As a Owner ou Admin, I want to definir a mensagem de boas-vindas, so that o primeiro contato seja padronizado
58. As a Owner ou Admin, I want to adicionar, editar, reordenar e remover instruções em lista, so that eu oriente o Agente sem escrever um prompt
59. As a Owner ou Admin, I want to adicionar, editar, reordenar e remover regras do que o Agente nunca deve fazer, so that eu evite respostas indesejadas
60. As a Owner ou Admin, I want to cadastrar gatilhos de quando transferir para um humano, so that a configuração já esteja pronta quando a transferência existir
61. As a Owner ou Admin, I want to selecionar quais Coleções o Agente pode consultar, so that cada Agente use só o conhecimento pertinente à sua função
62. As a Owner ou Admin, I want to ativar ou desativar um Agente, so that eu controle quais Agentes estão disponíveis
63. As a Owner ou Admin, I want to editar qualquer campo de um Agente, so that eu ajuste o comportamento com base nos testes
64. As a Owner ou Admin, I want to excluir um Agente e, se ele estiver vinculado ao WhatsApp, ter o número desvinculado automaticamente, so that a exclusão nunca fique travada
65. As a Owner ou Admin, I want to ser avisado antes de excluir um Agente que está vinculado ao WhatsApp, so that eu saiba que o número ficará sem Agente
66. As a usuário, I want to ver a lista de Agentes com objetivo, status e canal vinculado, so that eu tenha visão geral dos Agentes da Empresa
67. As a Owner ou Admin, I want to que os dados da Empresa sejam usados automaticamente por todos os Agentes, so that eu não precise repetir as informações do negócio em cada Agente
68. As a Owner ou Admin, I want to que o Agente sempre responda em português do Brasil, so that o atendimento seja consistente com meu público

### Playground

69. As a usuário (qualquer papel), I want to conversar com um Agente num chat de teste dentro da plataforma, so that eu valide o comportamento antes de colocá-lo num canal
70. As a usuário, I want to ver a resposta do Agente aparecendo em streaming, so that a experiência seja parecida com a de um cliente real
71. As a usuário, I want to ver a mensagem de boas-vindas ao abrir o Playground, so that eu teste o primeiro contato
72. As a usuário, I want to ver ao lado da conversa os trechos da base de conhecimento consultados em cada resposta (documento, coleção, trecho e similaridade), so that eu entenda de onde veio a informação e ajuste a base
73. As a usuário, I want to ver claramente quando o Agente respondeu sem consultar a base, so that eu identifique respostas não fundamentadas
74. As a usuário, I want to reiniciar a conversa com um botão, so that eu teste cenários diferentes do zero
75. As a usuário, I want to que o Playground use sempre a configuração salva do Agente, so that o teste reflita o que realmente estará em produção
76. As a Owner ou Admin, I want to ser avisado no Playground quando há alterações não salvas no formulário do Agente, so that eu não teste achando que a mudança já vale
77. As a usuário, I want to ser limitado a um número de mensagens por hora no Playground, com mensagem clara ao atingir o limite, so that o custo com o modelo fique controlado
78. As a usuário, I want to ver uma mensagem amigável se o modelo falhar ou demorar demais, so that eu saiba que posso tentar de novo
79. As a usuário, I want to que o Agente só encontre documentos com status pronto e das Coleções selecionadas nele, so that o teste reflita exatamente a configuração
80. As a usuário, I want to que um Agente sem Coleções selecionadas ainda converse usando os dados da Empresa, so that eu consiga testar mesmo antes de montar a base

### Canais: WhatsApp

81. As a usuário, I want to ver a página Canais com o card do WhatsApp e o card "Chat no site" marcado como em breve, so that eu entenda por onde os Agentes vão atender
82. As a Owner ou Admin, I want to cadastrar o número do WhatsApp Cloud API informando WABA ID, Phone Number ID, access token permanente e App Secret, so that a plataforma consiga falar com a Meta em meu nome
83. As a Owner ou Admin, I want to que a plataforma gere o verify token e me mostre a URL do webhook com botão de copiar, so that eu configure o webhook no painel da Meta sem erro
84. As a Owner ou Admin, I want to testar a conexão e ver o número de telefone, o nome verificado e o quality rating retornados pela Meta, so that eu tenha certeza de que as credenciais estão corretas
85. As a Owner ou Admin, I want to ver uma mensagem de erro clara quando o teste de conexão falhar (token inválido, ID incorreto, permissão ausente), so that eu saiba o que corrigir
86. As a usuário, I want to ver o status do número (não verificado, conectado, erro) e a data do último teste, so that eu saiba se o canal está saudável
87. As a Owner ou Admin, I want to ver se o webhook já foi verificado pela Meta, so that eu saiba que a configuração no painel da Meta deu certo
88. As a Owner ou Admin, I want to vincular um Agente ao número do WhatsApp, so that esteja definido quem atenderá quando o atendimento for ativado
89. As a Owner ou Admin, I want to trocar ou remover o Agente vinculado, so that eu mude a estratégia de atendimento
90. As a Owner ou Admin, I want to editar as credenciais sem ter que redigitar os segredos que não mudaram, so that eu atualize só o necessário
91. As a Owner ou Admin, I want to que os segredos nunca sejam exibidos de volta na tela (apenas mascarados), so that eles não vazem por captura de tela ou por um Member
92. As a Owner ou Admin, I want to remover o número conectado, so that eu desfaça a configuração
93. As a Owner ou Admin, I want to ser impedido de cadastrar um segundo número, so that a regra de um número por Empresa seja respeitada nesta fase
94. As a Member, I want to visualizar o status do WhatsApp e o Agente vinculado sem ver credenciais nem poder editar, so that eu acompanhe o canal com segurança
95. As a plataforma, I want to responder ao desafio de verificação do webhook da Meta apenas quando o verify token confere com o de algum número cadastrado, so that só webhooks legítimos sejam verificados
96. As a plataforma, I want to rejeitar eventos de webhook com assinatura inválida e aceitar (e descartar) os com assinatura válida, so that a Meta não desative o webhook e nenhum evento forjado seja aceito

### Integrações

97. As a usuário, I want to ver o catálogo de Integrações com RD Station CRM, HubSpot, Pipedrive, Kommo, Salesforce e Ploomes, so that eu saiba quais CRMs estarão disponíveis
98. As a usuário, I want to abrir um drawer com a descrição de cada CRM e o que a integração fará, so that eu entenda o valor antes de ela existir
99. As a usuário, I want to ver o botão "Conectar" desabilitado e o selo "Em breve", so that fique claro que ainda não é possível conectar

### Dashboard

100. As a usuário, I want to ver um checklist de ativação (dados da Empresa, primeiro Agente criado, primeiro documento pronto, WhatsApp conectado, Agente vinculado ao WhatsApp), so that eu saiba o que falta para colocar o atendimento no ar
101. As a usuário, I want to clicar em cada item pendente do checklist e ir direto para a tela correspondente, so that eu avance sem procurar no menu
102. As a usuário, I want to ver o resumo com a quantidade de Agentes (ativos e total), documentos prontos e com erro, uso de armazenamento e status do WhatsApp, so that eu tenha a saúde da conta num só lugar
103. As a usuário, I want to ver os cards de métricas de atendimento (conversas, atendimentos resolvidos, transferências, oportunidades criadas) como "em breve", so that eu saiba o que o Dashboard trará
104. As a usuário, I want to navegar pela sidebar entre Dashboard, Agentes, Base de conhecimento, Canais, Integrações, Equipe e Configurações, so that eu encontre qualquer área rapidamente

### Operação da plataforma

105. As a desenvolvedor, I want to subir Postgres com pgvector, Redis e Mailpit via Docker com um comando, so that o ambiente de desenvolvimento seja reproduzível
106. As a desenvolvedor, I want to acompanhar filas, jobs falhados e retries no Horizon, so that eu diagnostique falhas de ingestão
107. As a desenvolvedor, I want to ver convites e e-mails de redefinição de senha no Mailpit, so that eu teste os fluxos de e-mail localmente
108. As a administrador da plataforma, I want to que exista uma marcação de administrador da plataforma no usuário, so that o painel interno possa ser construído depois sem migração de dados

## Implementation Decisions

### Infraestrutura

- O banco passa de SQLite para **PostgreSQL com a extensão pgvector** em todos os ambientes, inclusive testes. As migrations existentes continuam valendo, e uma migration habilita a extensão `vector`.
- Em desenvolvimento o Docker roda **apenas os serviços** (Postgres com a imagem oficial do pgvector, Redis e Mailpit). A aplicação roda no host, cujo PHP 8.5 já tem `pdo_pgsql`, `redis`, `zip`, `dom` e o binário `pdftotext`.
- Filas no **Redis**, monitoradas pelo **Laravel Horizon**, com duas filas: `ingestion` (extração, chunking e embeddings) e `default`. O painel do Horizon fica restrito ao ambiente local por enquanto, e em produção só administradores da plataforma acessam.
- E-mail em desenvolvimento via Mailpit (SMTP). O ambiente de produção ainda não foi decidido e o spec não depende dele.
- Arquivos originais dos documentos são gravados num disco configurável por variável de ambiente (local em dev, compatível com S3 em produção).
- Dependências novas aprovadas: `laravel/horizon`, `spatie/pdf-to-text`, `fivefilters/readability.php` e `league/html-to-markdown`. DOCX é lido sem dependência, via `ZipArchive` + DOM sobre o XML do documento. A compatibilidade com PHP 8.5 é confirmada na instalação.
- Toda a interface fica em **pt-BR**, com strings direto no código (sem sistema de i18n). As mensagens de validação do Laravel ganham tradução pt-BR. O `APP_NAME` atual é mantido e o visual continua o do starter kit (shadcn).

### Tenancy e autenticação

- **Empresa = `Team`**. O modelo `Team` existente é mantido e ganha os campos do onboarding: nome fantasia, segmento, site, descrição do negócio, produtos/serviços, público-alvo, horário de atendimento (estruturado por dia da semana), fuso horário e contatos públicos (telefone, e-mail, endereço).
- **Um usuário pertence a exatamente uma Empresa.**
  - O cadastro deixa de criar o team pessoal com o nome do usuário.
  - A criação de team fica restrita ao onboarding.
  - Somem a tela de criar team, o seletor de team e a troca de team.
  - O conceito de `is_personal` deixa de ser usado.
- **Verificação de e-mail desligada**: sai a feature do Fortify e o middleware `verified` das rotas. Reset de senha, 2FA e passkeys continuam como estão.
- **Onboarding bloqueante**: um middleware manda todo usuário autenticado sem Empresa para o onboarding. O onboarding cria a Empresa, vincula o usuário como `Owner` e cria a Coleção "Geral", tudo numa transação.
- **Convites**:
  - O fluxo de convite existente é adaptado: o link do e-mail leva a um cadastro vinculado ao convite, que cria o usuário já como membro da Empresa, com o papel do convite e sem onboarding.
  - Um usuário logado sem Empresa também pode aceitar.
  - Convidar um e-mail que já pertence a outra Empresa é bloqueado na validação.
- **Rotas sem slug do team**: o prefixo `{current_team}` e o middleware de URL defaults saem. A Empresa corrente é sempre a do usuário logado.
- **Isolamento**:
  - Todo model de domínio tem `team_id` e usa um comportamento compartilhado de pertencer à Empresa: um global scope filtra pela Empresa do usuário autenticado e o `team_id` é preenchido na criação.
  - Policies garantem as permissões.
  - Recurso de outra Empresa responde **404**.
  - O scope só se aplica em contexto HTTP autenticado; jobs de fila recebem o model explicitamente.
- **Permissões**: o enum `TeamPermission` existente ganha `agent:manage`, `knowledge:manage` e `channel:manage`, concedidas a `Owner` e `Admin`. `Member` só tem leitura, e a leitura vem implícita de ser membro. O frontend recebe as permissões do usuário para esconder as ações.
- Owner não pode sair da Empresa. A exclusão da Empresa fica desabilitada.
- O usuário ganha a flag booleana `is_platform_admin` (padrão falso), sem uso na fase 1 além do acesso ao Horizon fora do ambiente local.

### Base de conhecimento

- **Módulos de domínio**:
  - **Coleção**: pertence à Empresa; tem nome, descrição e marcação de coleção padrão.
  - **Documento**: pertence à Empresa e a uma Coleção.
  - **Chunk**: trecho indexado, pertence ao Documento e carrega `team_id` e `collection_id` desnormalizados para filtrar a busca.
- **Coleção "Geral"**: é criada no onboarding, marcada como padrão e não pode ser excluída. Excluir qualquer outra Coleção apaga em cascata documentos, chunks e arquivos armazenados.
- **Tipos de Documento** (enum): `file` (PDF/DOCX/TXT), `text` (texto livre), `faq` (lista de pares pergunta/resposta guardada estruturada no próprio documento) e `url`.
- **Status do Documento** (enum): `pending → processing → ready | failed`, com mensagem de erro legível em pt-BR quando `failed`. Toda criação, edição de conteúdo, substituição de arquivo, re-sync de URL ou reprocessamento volta o status para `pending` e despacha a ingestão.
- **Pipeline de ingestão** (job na fila `ingestion`, idempotente):
  1. Extrai o texto conforme o tipo:
     - PDF via `pdftotext`; PDF sem texto vira falha "PDF sem texto extraível" (sem OCR).
     - DOCX via XML (parágrafos, títulos e tabelas).
     - TXT direto, com detecção e normalização de encoding para UTF-8.
     - URL: baixa a página com timeout, isola o conteúdo principal com Readability e converte para Markdown.
     - Texto livre usado como está.
  2. Faz o chunking:
     - Documentos não-FAQ são quebrados de forma orientada a Markdown (títulos e parágrafos), com alvo de ~800 tokens e overlap de ~100, estimando tokens por caracteres.
     - No FAQ, cada par pergunta/resposta vira exatamente um chunk.
  3. Gera os embeddings em lote com `text-embedding-3-small` (1536 dimensões) através do `laravel/ai`.
  4. Substitui atomicamente os chunks antigos pelos novos e marca `ready`.

  Falha após as tentativas marca `failed` com a mensagem e preserva os chunks anteriores. Enquanto não está `ready`, o documento não aparece na busca.
- **Índice vetorial**: coluna `vector(1536)` com índice HNSW por distância de cosseno.
- **Cota**:
  - Limite de 500 MB por Empresa e 20 MB por arquivo.
  - O uso soma o tamanho dos arquivos originais mais o tamanho em bytes do conteúdo textual das fontes sem arquivo (texto livre, FAQ e o texto extraído de URL).
  - O tamanho fica gravado no Documento.
  - A validação bloqueia qualquer criação ou atualização que ultrapasse a cota. Excluir libera o espaço.
- A lista de documentos atualiza o status via polling do Inertia enquanto houver documento `pending` ou `processing`. Não há broadcasting nesta fase.

### Busca na base de conhecimento (seam novo)

- Um módulo de busca com interface pequena: recebe a Empresa, o conjunto de Coleções permitidas e o texto da pergunta, e devolve até 5 trechos com similaridade de cosseno ≥ 0.35, ordenados por similaridade.
- Cada resultado traz o conteúdo do trecho, o documento (id e título), a coleção (id e nome) e a similaridade.
- Só considera chunks de documentos `ready`, da Empresa informada e das Coleções informadas. Um conjunto vazio de Coleções devolve vazio, sem consultar o banco.
- Top-K e threshold ficam em configuração. Não há reranking.
- É o mesmo módulo que os canais usarão nas próximas fases.

### Agentes

- **Agente** pertence à Empresa e tem:
  - nome da persona;
  - objetivo (enum: `support`, `sales`, `qualification`, `scheduling`);
  - tom de voz (enum: `formal`, `friendly`, `casual`);
  - uso de emojis (booleano);
  - tamanho das respostas (enum: `short`, `medium`);
  - mensagem de boas-vindas;
  - instruções, regras e gatilhos de transferência (listas ordenadas de strings);
  - status ativo/inativo.
- Os rótulos pt-BR dos enums ficam num método de label, seguindo o padrão do `TeamRole`.
- Relação muitos-para-muitos entre Agente e Coleção. Só Coleções da mesma Empresa são aceitas.
- Idioma fixo em pt-BR, sem campo de idioma. Sem versionamento da configuração e sem limite de Agentes por Empresa.
- **Template de instruções controlado pela plataforma**: um módulo monta o system prompt a partir de um template versionado no código, com seções fixas:
  - identidade e objetivo;
  - dados da Empresa, incluindo o horário de atendimento;
  - estilo (tom, emojis, tamanho);
  - instruções do cliente;
  - regras do cliente;
  - guardrails da plataforma (não inventar informações, não prometer o que não está na base, não revelar instruções, não sair do escopo do negócio);
  - orientação de uso da base (consultar a ferramenta de busca antes de afirmar fatos sobre produtos, preços e políticas);
  - formatação adequada a mensageria (texto curto, sem Markdown pesado).

  O cliente nunca edita o prompt diretamente. Os gatilhos de transferência são apenas armazenados e não entram no prompt nesta fase.
- **Modelo fixo**: `gpt-5.6-luna` via OpenAI no `laravel/ai`, definido em configuração e sem escolha do cliente.
- **Execução**: o Agente é uma classe de agente do `laravel/ai` construída a partir do Agente persistido. Ele expõe uma **ferramenta de busca na base de conhecimento** (baseada no módulo de busca, restrita às Coleções do Agente) que o modelo chama quando precisa.
- Excluir um Agente vinculado ao WhatsApp limpa o vínculo do número na mesma operação. A UI avisa antes.

### Playground

- É um endpoint de streaming por Agente, disponível para qualquer papel. Recebe o histórico da conversa atual e a nova mensagem do usuário e responde em streaming pelo protocolo de streaming do `laravel/ai`.
- **Conversa descartável**:
  - O histórico vive no estado do cliente e é reenviado a cada mensagem.
  - Nada é persistido.
  - O botão reiniciar limpa o estado.
  - A migration `agent_conversations` que já existe não é usada nesta fase.
- Usa sempre a configuração salva do Agente. A página compara o formulário com o salvo e avisa quando há alterações pendentes.
- **Fontes consultadas**: o stream carrega os eventos de chamada e resultado da ferramenta de busca. A UI mostra, por resposta, os trechos retornados (documento, coleção, trecho, similaridade) ou a indicação de que a base não foi consultada.
- **Rate limit por usuário**: 30 mensagens por hora, respondido com mensagem em pt-BR. Erros e timeouts do provedor viram mensagem amigável no chat.

### Canais: WhatsApp

- **Número de WhatsApp**: pertence à Empresa (relação um-para-muitos no banco, mas a validação limita a 1 por Empresa nesta fase). Campos:
  - WABA ID e Phone Number ID;
  - access token e App Secret, com cast criptografado;
  - verify token gerado pela plataforma, único globalmente;
  - Agente vinculado (opcional, anulado ao excluir o Agente);
  - status de conexão (enum: `unverified`, `connected`, `error`) e mensagem do último erro;
  - dados retornados pela Meta (número exibido, nome verificado, quality rating);
  - data do último teste e data de verificação do webhook.
- A modelagem já considera um campo de método de conexão (`manual` agora, `embedded_signup` no futuro).
- **Testar conexão**: consulta a Graph API com o token para o Phone Number ID e grava status e dados retornados. Os erros da Meta são traduzidos para mensagens pt-BR. A versão da Graph API fica em configuração.
- **Segredos**: nunca voltam para o frontend, só a indicação de que estão preenchidos e uma versão mascarada. Na edição, campo de segredo vazio mantém o valor atual.
- **Webhook**: rota pública única, fora da autenticação e sem CSRF.
  - **GET** (handshake): responde com o `hub.challenge` quando `hub.mode` é `subscribe` e o `hub.verify_token` confere com algum número cadastrado. Nesse caso grava a data de verificação. Qualquer outro caso recebe 403.
  - **POST**: valida a assinatura `X-Hub-Signature-256` (HMAC-SHA256 do corpo cru com o App Secret do número identificado pelo Phone Number ID do payload). Assinatura inválida ou número desconhecido recebe 403. Assinatura válida recebe 200 e o evento é descartado sem processamento nesta fase.
- A página **Canais** mostra o card WhatsApp (configurável) e o card "Chat no site" (em breve).

### Integrações

- O catálogo estático sai do enum `CrmProvider` com os casos RD Station CRM, HubSpot, Pipedrive, Kommo, Salesforce e Ploomes. Cada caso tem nome, descrição, logo e lista do que a integração fará. Não há tabela nem persistência.
- A página lista os cards. Cada card abre um drawer com os detalhes, o botão "Conectar" desabilitado e o selo "Em breve".

### Dashboard e navegação

- **Sidebar**: Dashboard, Agentes, Base de conhecimento, Canais, Integrações, Equipe e Configurações (Empresa, Perfil, Segurança, Aparência).
- **Dashboard**:
  - O **checklist de ativação** é calculado do estado real: dados da Empresa completos, ao menos um Agente, ao menos um documento `ready`, WhatsApp `connected` e Agente vinculado ao WhatsApp. Cada item leva à tela correspondente.
  - O **resumo** mostra Agentes ativos/total, documentos `ready` e `failed`, uso da cota e status do WhatsApp.
  - Os cards de métricas de atendimento aparecem como "em breve", sem dados.
- Os convites pendentes mostrados hoje no dashboard deixam de fazer sentido, porque o usuário tem uma única Empresa e o convite passa a ser aceito no cadastro. Essa exibição é removida.

## Testing Decisions

- **O que é um bom teste aqui**: exercita comportamento externo pelo seam mais alto possível (requisição HTTP → resposta, props Inertia, estado do banco, e-mails, jobs). Não testa detalhes internos como nomes de classes, métodos privados, a ordem das seções do prompt ou o formato interno dos chunks. Um teste deve continuar passando depois de uma refatoração que não muda o comportamento.
- **Seam principal: HTTP (feature tests Pest)**. Tudo que o usuário faz é testado pelas rotas, com `actingAs`, `assertInertia`, redirects, códigos de status e asserções no banco:
  - cadastro sem verificação de e-mail, redirecionamento ao onboarding e onboarding criando Empresa, Owner e Coleção "Geral";
  - bloqueio de segunda Empresa;
  - convite gerando cadastro vinculado; bloqueio de e-mail de outra Empresa; Owner impedido de sair;
  - isolamento entre Empresas, com 404 em recursos alheios, para cada tipo de recurso;
  - permissões por papel, com `Member` recebendo 403 em escrita e conseguindo ler e usar o Playground;
  - CRUD de Coleções: "Geral" indelével e cascata na exclusão;
  - criação e edição de Documentos de cada tipo; validação de formato, 20 MB e cota de 500 MB; exclusão liberando cota; re-sync e reprocessamento;
  - Agentes: validação dos campos, Coleções só da mesma Empresa, exclusão desvinculando o WhatsApp;
  - Playground: streaming responde, rate limit a partir da 31ª mensagem, erro do provedor tratado;
  - WhatsApp: cadastro, limite de 1 número, segredos nunca presentes nas props, edição mantendo segredos, teste de conexão com sucesso e erro, vínculo de Agente;
  - webhook: handshake válido e inválido, assinatura válida e inválida;
  - catálogo de Integrações nas props;
  - checklist e resumo do Dashboard refletindo o estado.
- **Fronteiras externas** com os fakes do framework, sem seams próprios:
  - `Embeddings::fake()` e o fake de agente do `laravel/ai` para a OpenAI;
  - `Http::fake()` para a Graph API e para o download de URLs;
  - `Storage::fake()` para arquivos;
  - `Mail::fake()` / `Notification::fake()` para convites;
  - fila `sync` nos testes de ingestão, para que a requisição de criação rode o pipeline inteiro e o teste verifique status `ready`, chunks gerados e tamanho contabilizado;
  - `Queue::fake()` apenas onde o teste é sobre o despacho.
- **Extração de texto sem seam**: fixtures reais e pequenas (PDF com texto, PDF só com imagem, DOCX com título e tabela, TXT em Latin-1, HTML com menu e rodapé) passam pelas bibliotecas e pelo `pdftotext` de verdade. O CI precisa de `poppler-utils`.
- **Seam novo: busca na base de conhecimento**. Testado diretamente contra o Postgres com pgvector usando vetores controlados (embeddings fakeados com vetores conhecidos), cobrindo:
  - limite de 5 resultados;
  - corte abaixo de 0.35;
  - ordenação por similaridade;
  - exclusão de documentos que não estão `ready`;
  - isolamento por Empresa;
  - restrição às Coleções informadas;
  - conjunto vazio de Coleções.
- **Banco de testes**: os testes passam a rodar em Postgres com pgvector (um database de teste dedicado no container), não mais em SQLite em memória. A configuração de testes é ajustada de acordo.
- **Prior art**: os testes existentes do starter kit (Dashboard, Teams, Auth e Settings) são feature tests Pest via HTTP com factories e `assertInertia`. Os novos seguem o mesmo estilo. Os testes do starter kit afetados por decisões deste spec (team pessoal no cadastro, verificação de e-mail, troca de team, slug na URL, convites no dashboard) são atualizados para refletir o novo comportamento, e não simplesmente apagados.

## Out of Scope

- O Agente responder mensagens reais no WhatsApp: processar eventos do webhook, fila de mensagens, envio pela Graph API, janela de 24h, templates de mensagem.
- Popup de chat no site ("Chat no site").
- Conexão real com qualquer CRM e criação ou configuração de oportunidades em pipelines.
- Transferência real para humano (os gatilhos são apenas armazenados).
- Embedded Signup da Meta (WhatsApp).
- Métricas reais de atendimento no Dashboard e histórico de conversas.
- Persistência das conversas do Playground.
- Painel de administrador da plataforma, impersonate e suspensão de contas.
- Exclusão da Empresa e múltiplas Empresas por usuário.
- Mais de um número de WhatsApp por Empresa.
- Escolha de modelo ou provedor de LLM pelo cliente, e chave própria (BYOK).
- Prompt livre, idioma diferente de pt-BR e versionamento da configuração do Agente.
- OCR de PDF escaneado, crawl de site inteiro, páginas que dependem de JavaScript, re-sync automático de URL e conectores (Google Drive, Notion etc.).
- Reranking de resultados da busca.
- Billing, planos e cotas diferenciadas por plano.
- Internacionalização da interface e identidade visual própria.
- Definição do ambiente de produção.

## Further Notes

- **Glossário usado neste spec**:
  - **Empresa**: o `Team`, o tenant.
  - **Equipe**: membros da Empresa.
  - **Agente**.
  - **Coleção**.
  - **Documento**.
  - **Chunk**: trecho indexado.
  - **Base de conhecimento**: conjunto das Coleções da Empresa.
  - **Playground**.
  - **Canal**: por onde o cliente final conversa (WhatsApp, Chat no site).
  - **Integração**: sistema externo para onde os dados vão (CRMs).

  Canal e Integração são deliberadamente conceitos separados. Ainda não há `CONTEXT.md`; este glossário é bom candidato para o `/domain-modeling`.
- A migração de SQLite para Postgres deve acontecer antes de qualquer model de domínio, porque não há dados a preservar.
- A ordem natural de construção é:
  1. infraestrutura (Docker, Postgres, Redis, Horizon, banco de testes);
  2. tenancy e onboarding;
  3. Base de conhecimento com ingestão e busca;
  4. Agentes e Playground;
  5. Canais/WhatsApp;
  6. Integrações;
  7. Dashboard.
- Pontos a verificar na implementação:
  - a API exata do `laravel/ai` 0.11 para agentes com ferramentas, streaming com eventos de ferramenta e embeddings em lote (consultar a documentação instalada);
  - a disponibilidade do modelo `gpt-5.6-luna` na conta OpenAI usada;
  - a compatibilidade dos pacotes novos com PHP 8.5.
- Webhook compartilhado: como a Meta chama uma única URL para todos os números, o número é identificado pelo verify token (GET) e pelo Phone Number ID do payload (POST). Isso já prepara o terreno para vários números e para o Embedded Signup.
