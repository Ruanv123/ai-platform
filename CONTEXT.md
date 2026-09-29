# Plataforma de Agentes de IA para Atendimento

Plataforma SaaS multi-tenant onde uma empresa brasileira configura agentes de IA que atendem seus clientes nos canais de mensageria, respondendo a partir do conhecimento real do negócio. Cada conta é isolada e pertence a exatamente uma Empresa.

## Language

### Conta e pessoas

**Empresa**:
O tenant da plataforma: o negócio que contrata o produto e dono de tudo que é criado dentro dele. Implementada pelo model `Team`.
_Avoid_: Time, Organização, Conta, Workspace, Tenant

**Equipe**:
As pessoas que têm acesso a uma Empresa, cada uma com um Papel.
_Avoid_: Membros, Usuários, Time

**Papel**:
O nível de acesso de uma pessoa dentro da Empresa: Owner, Admin ou Member.
_Avoid_: Permissão, Role, Cargo

**Convite**:
O pedido pendente para uma pessoa entrar na Equipe com um Papel definido. Expira em três dias e some da lista ao expirar; reconvidar é a forma de renovar.
_Avoid_: Invite, Convocação

**Horário de atendimento**:
Os dias e faixas de horário em que a Empresa atende, com o fuso horário em que são lidos.
_Avoid_: Expediente, Horário comercial, Business hours

### Agente

**Agente**:
Uma configuração de atendimento pertencente à Empresa — persona, objetivo, Estilo, Limites, Cenários, Ações, Coleções consultáveis e Calendários agendáveis em que pode marcar. Uma Empresa pode ter vários, e o mesmo Agente pode atender em vários Canais.
_Avoid_: Bot, Assistente, Chatbot, Persona

**Objetivo**:
Para que o Agente existe — Atendimento, Vendas, Qualificação ou Agendamento. Define os pontos de partida sugeridos (boas-vindas, Limites, Cenários e Situações de teste), não o que ele pode fazer.
_Avoid_: Papel, Função, Tipo de agente

**Estilo**:
Como o Agente fala: tom, uso de emojis, tamanho das respostas e diretrizes livres sobre a forma da resposta. Nunca diz o que ele faz nem o que ele evita.
_Avoid_: Diretriz de resposta, Instrução, Personalidade

**Limite**:
Algo que o Agente nunca faz, em qualquer conversa — um assunto que evita, uma informação que não passa, um compromisso que não assume.
_Avoid_: Regra, Proteção, Guardrail, Restrição

**Cenário**:
Uma situação específica descrita em linguagem natural — quando ela acontece e o que o Agente deve fazer — podendo citar Ações. O Agente a interpreta dentro da conversa livre, sem garantia de ordem; quando a ordem precisa ser garantida, o lugar é um Fluxo.
_Avoid_: Instrução, Playbook, Roteiro, Fluxo

**Ação**:
Algo que o Agente pode fazer além de responder — consultar a Base de conhecimento, buscar produtos da Loja, mandar Link de compra, marcar na agenda, fazer a Transferência para humano, aplicar Tag, salvar Campo do Contato. A Empresa liga as que o Agente pode usar; um Cenário só pode pedir uma Ação ligada.
_Avoid_: Ferramenta, Tool, Habilidade, Integração

**Rascunho do agente**:
A configuração do Agente que está sendo editada e ainda não vale nos Canais. É sobre ela que o Playground e as Situações de teste rodam.
_Avoid_: Edição, Pendente

**Versão do agente**:
O retrato da configuração do Agente no momento em que foi publicado. É o que atende nos Canais; editar cria Rascunho, publicar cria Versão nova.
_Avoid_: Revisão, Snapshot, Release

**Memória do agente**:
Os fatos curtos e importantes que um Agente decidiu guardar sobre um Contato — uma preferência, um vínculo, algo já resolvido. Pertence ao par Agente e Contato: outro Agente não a enxerga. Não desliza como o Histórico e vale em todos os Canais do Contato. A Empresa pode vê-la e apagá-la.
_Avoid_: Contexto, Histórico, Campo do Contato, Perfil

**Situação de teste**:
Uma conversa de exemplo salva junto do Agente, com o comportamento esperado — o que a resposta deve ou não conter e quais Ações deve ou não usar. Roda de novo sobre o Rascunho a cada mudança para mostrar o que quebrou.
_Avoid_: Cenário, Eval, Caso de teste, Teste

**Playground**:
O chat de teste do atendimento. Roda sobre o rascunho — o Rascunho do agente, no caso do Agente, e a Versão não publicada, no caso do Fluxo — e nada é enviado ao Contato. Consultar Horários disponíveis e buscar produtos da Loja é real; marcar e cancelar Compromisso são simulados, e o Link de compra sai sem Atribuição. No Fluxo, mostra o Traço do turno ao lado da conversa.
_Avoid_: Simulador, Preview, Sandbox

**Agente inativo**:
Um Agente que não responde nos Canais vinculados, mas continua utilizável no Playground. Todo Agente nasce inativo e sem Versão do agente; ativar pela primeira vez publica a primeira Versão. Ativo e inativo são o único estado do Agente — publicar é uma ação, não um estado.
_Avoid_: Agente desligado, Agente pausado

### Base de conhecimento

**Coleção**:
Um agrupamento de Documentos dentro da Empresa. É a unidade que um Agente escolhe consultar.
_Avoid_: Base de conhecimento, Pasta, Categoria, Knowledge base

**Base de conhecimento**:
O conjunto de todo o conhecimento da Empresa. É o nome da seção, nunca de uma Coleção individual.

**Documento**:
Uma fonte de conhecimento dentro de uma Coleção: arquivo, texto livre, FAQ ou URL. É processado em segundo plano e vira Trechos pesquisáveis.
_Avoid_: Arquivo, Fonte, Conteúdo

**Trecho**:
O pedaço de um Documento, com embedding, que a busca semântica retorna. Implementado pelo model `Chunk`.
_Avoid_: Chunk, Fragmento, Pedaço

### Canais

**Canal**:
A conexão configurada de uma Empresa com um meio de atendimento — uma linha em `channels`. Aponta para no máximo um Agente, e é o Canal que sabe se o atendimento está funcionando.
_Avoid_: Conexão, Integração, Canal de atendimento

**Tipo de canal**:
O meio de atendimento em si (WhatsApp, Chat no site, Instagram Direct), independente de qualquer configuração. O catálogo da tela de Canais é gerado a partir dos Tipos de canal.
_Avoid_: Canal, Provedor, Plataforma

**Conta do WhatsApp**:
A conta de WhatsApp Business da Empresa na Meta (a WABA). É dona dos Números do WhatsApp e dos Templates, e é por ela que a Meta cobra a Empresa. Sabe por qual Forma de conexão foi ligada à plataforma.
_Avoid_: WABA, Conta Meta, Business Account, Credenciais

**App da Meta**:
O aplicativo registrado na Meta que chama a API do WhatsApp em nome da Conta do WhatsApp e assina os webhooks que ela recebe. Na conexão pelo Tech Provider é um só, o da plataforma, compartilhado por todas as Empresas; na conexão por App próprio é o da Empresa.
_Avoid_: Credenciais, Integração, Aplicativo

**Forma de conexão**:
Como a Conta do WhatsApp foi ligada à plataforma: pelo **Tech Provider** (a Empresa autoriza a plataforma pelo cadastro incorporado da Meta e usa o App da Meta da plataforma) ou por **App próprio** (a Empresa informa as credenciais do seu próprio App da Meta).
_Avoid_: Método, Modo, Manual, Embedded Signup

**Número do WhatsApp**:
O número que recebe e responde mensagens, com sua identidade e qualidade. É a ele que o Canal se prende.
_Avoid_: Telefone, WhatsApp, Linha

**Perfil do WhatsApp**:
A identidade pública de um Número do WhatsApp, vista pelos Contatos ao abrir a conversa: nome de exibição, foto, recado e dados do negócio. Quem guarda o Perfil é o provedor, não a plataforma: a Empresa vê e edita o Perfil sempre direto no provedor.
_Avoid_: Contato, Perfil comercial, Business profile

**WhatsApp de testes**:
O Tipo de canal que conecta um número de WhatsApp por um serviço não oficial, usado apenas em desenvolvimento. Não é oferecido aos clientes.
_Avoid_: Evolution, WhatsApp não oficial, WhatsApp alternativo

**Integração**:
Uma conexão configurada com um sistema externo — CRM, agenda, e-commerce — que os Nós de um Fluxo ou o Agente usam para consultar ou registrar coisas lá fora. É outra coisa que um Canal: Canal traz a conversa, Integração leva e busca o resultado. Guarda a credencial; o Fluxo guarda o que fazer com ela.
_Avoid_: Conector, Canal, App

**Agenda**:
A Integração com a conta Google da Empresa, autorizada uma vez por alguém da Equipe e pertencente à Empresa, não a quem autorizou. Uma Empresa tem no máximo uma. Quando o Google revoga o acesso, fica com erro até alguém reconectar.
_Avoid_: Google Agenda, Calendar, Conexão de agenda

**Calendário**:
Um dos calendários dentro da conta Google conectada pela Agenda — o de um profissional, de uma sala. Uma Agenda pode ter vários.
_Avoid_: Agenda, Calendar

**Calendário agendável**:
Um Calendário que a Empresa liberou para agendamento, com as regras de quem atende nele: duração do compromisso, janela de atendimento e antecedência mínima, além do nome e da descrição com que o Agente o apresenta ao Contato. A janela, quando não é personalizada, é o Horário de atendimento da Empresa. As regras são do Calendário, não do Agente: todo Agente que marca nele obedece às mesmas.
_Avoid_: Profissional, Recurso, Agenda

**Horário disponível**:
Um intervalo com a duração do Calendário agendável, dentro da janela, depois da antecedência mínima, e livre no Calendário. É o que o Agente oferece ao Contato.
_Avoid_: Slot, Vaga, Horário livre

**Compromisso**:
Um evento que o Agente marcou num Calendário agendável para um Contato, registrado na plataforma. Só o que a plataforma marcou é Compromisso: eventos criados direto no Google não são. É por ele que o Agente sabe o que o Contato tem marcado e o que pode cancelar. O Google continua sendo a fonte da verdade: evento apagado lá é Compromisso cancelado, evento movido lá é Compromisso remarcado. Um Contato tem no máximo um Compromisso futuro por Calendário agendável; remarcar é cancelar e marcar de novo.
_Avoid_: Agendamento, Evento, Consulta, Reserva

### Loja

**Loja**:
A Integração de e-commerce da Empresa com a sua loja num Provedor de e-commerce — Shopify, Nuvemshop, VTEX, Loja Integrada. Uma Empresa tem no máximo uma. A plataforma mantém uma cópia do que o Agente precisa da Loja, no mesmo formato para qualquer provedor, sincronizada em segundo plano. É a Loja que sabe se essa cópia está em dia — importando, sincronizada ou com erro —, e com erro o Agente continua usando a última cópia.
_Avoid_: E-commerce, Store, Shop, Integração de loja

**Provedor de e-commerce**:
A plataforma em que a Loja existe. Cada provedor tem a sua forma de conectar e de avisar mudanças, mas o que chega ao Agente é sempre igual.
_Avoid_: Plataforma, Canal, Marketplace

**Produto**:
O que a Loja vende, com nome, descrição e Categorias. Não é comprado diretamente: quem se compra é uma Variante.
_Avoid_: Item, Mercadoria, Anúncio

**Variante**:
A versão comprável de um Produto — tamanho M na cor azul —, com SKU, preço, preço comparativo e estoque somado de todos os locais.
_Avoid_: SKU, Opção, Grade, Modelo

**Categoria**:
Um agrupamento de Produtos na Loja. Nunca é chamada de Coleção, que é da Base de conhecimento.
_Avoid_: Coleção, Collection, Departamento

**Cliente da loja**:
Quem tem cadastro na Loja, como o Provedor de e-commerce o conhece — nome, e-mail, telefone e Pedidos. Liga-se a no máximo um Contato quando o telefone bate, e aparece na ficha dele sem ser copiado para o Contato. Pode existir sem Contato, quando comprou sem telefone.
_Avoid_: Cliente, Comprador, Customer

**Link de compra**:
O endereço que o Agente manda ao Contato e que abre o checkout da Loja com as Variantes e quantidades escolhidas. Não cria nada na Loja até o Contato comprar, e leva a marca que permite a Atribuição.
_Avoid_: Carrinho, Checkout, Link de pagamento

**Atribuição**:
A ligação entre um Pedido e o atendimento que gerou o Link de compra usado nele — qual Contato e qual Agente. É o que permite dizer quanto o Agente vendeu.
_Avoid_: Conversão, Origem, Tracking

**Pedido**:
Uma compra feita na Loja, com suas Variantes, quantidades e valores. Na v1 serve de histórico do que o comprador já levou, para recomendar; o Agente não responde sobre o andamento dele.
_Avoid_: Compra, Venda, Order

### Atendimento

**Contato**:
A pessoa que fala com a Empresa pelos Canais, identificada pelo telefone dentro da Empresa. A mesma pessoa falando em dois Canais continua sendo um único Contato.
_Avoid_: Cliente, Lead, Usuário, Destinatário

**Mensagem**:
O registro de conteúdo recebido de um Contato ou preparado para envio pela Empresa em um Canal. Um envio do Agente ou de alguém da Equipe pode falhar e continuar visível no atendimento.
_Avoid_: Interação, Evento, Ticket

**Conversa**:
A sequência de Mensagens de um Contato com a Empresa em um Canal. É a unidade em que a IA pode ser pausada para atendimento humano, sem afetar o mesmo Contato em outros Canais.
_Avoid_: Thread, Sessão, Ticket

**Pausa da IA**:
A suspensão temporária das respostas automáticas do Agente em uma Conversa enquanto alguém da Equipe a conduz. Pode terminar por devolução manual ou pelo prazo da Empresa.
_Avoid_: Agente inativo, Transferência para humano

**Reação**:
Um emoji que o Contato coloca sobre uma Mensagem já trocada. Pertence à Mensagem reagida e não é uma Mensagem nova: não abre turno e não aciona o Agente. Reagir de novo troca o emoji; reagir vazio remove.
_Avoid_: Curtida, Mensagem de reação

**Histórico**:
A janela recente de Mensagens de um Contato num Canal: o que o Agente lê para responder. É uma leitura das Mensagens, não uma entidade própria.
_Avoid_: Conversa, Thread, Sessão, Atendimento

### Fluxos

**Fluxo**:
O atendimento desenhado como um grafo de Nós, pertencente à Empresa. Descreve o que acontece a partir de um Gatilho: o que é perguntado, o que é decidido, o que é chamado fora da plataforma e quando o Agente assume.
_Avoid_: Workflow, Automação, Jornada, Funil, Bot

**Fluxo de conversa**:
Um Fluxo que contém algum Nó que espera o Contato responder, e por isso toma o turno da conversa. Um Canal tem no máximo um deles no Gatilho de mensagem, porque o Contato que responde não diz a quem está respondendo.
_Avoid_: Fluxo principal, Fluxo síncrono

**Fluxo de reação**:
Um Fluxo que roda do começo ao fim sem esperar resposta. Não toma o turno, e por isso vários podem rodar em paralelo sobre a mesma mensagem. A diferença para o Fluxo de conversa é lida do grafo, não declarada.
_Avoid_: Regra, Automação, Trigger, Fluxo secundário

**Versão do fluxo**:
O retrato imutável de um Fluxo no momento em que foi publicado. É o que uma Execução percorre: editar o Fluxo cria rascunho, publicar cria Versão nova, e quem está no meio termina na Versão em que começou.
_Avoid_: Revisão, Snapshot, Release

**Nó**:
Cada passo de um Fluxo — mandar mensagem, capturar resposta, decidir, esperar, chamar uma API, entregar ao Agente, transferir para humano, emitir Evento.
_Avoid_: Bloco, Card, Etapa, Passo

**Gatilho**:
O Nó que faz um Fluxo começar, e onde vive a escolha do Canal. Dois tipos: mensagem recebida e webhook.
_Avoid_: Trigger, Evento de entrada, Início

**Execução**:
Um Contato percorrendo uma Versão do fluxo. Tem começo, fim e Traço, e guarda em que Nó está e as Variáveis que coletou. Não é uma Conversa: não guarda Mensagens, e um Contato pode ter tido muitas ao longo do tempo.
_Avoid_: Conversa, Sessão, Instância, Run, Percurso

**Variável**:
Um valor que vive durante uma Execução e morre com ela. Diferente do Campo do Contato, que sobrevive.
_Avoid_: Contexto, Parâmetro, Estado

**Campo do Contato**:
Um dado do Contato que a Empresa declarou e que persiste entre Execuções — convênio, cidade, data da última compra. É o que transforma o Contato de identificador em ficha.
_Avoid_: Atributo, Propriedade, Custom field, Metadado

**Tag**:
Uma marca livre aplicada a um Contato, sem valor associado. Serve para separar quem é o quê.
_Avoid_: Etiqueta, Rótulo, Label, Categoria

**Sub-fluxo**:
Um Fluxo chamado por outro, com parâmetros de entrada e valores de retorno declarados. Não enxerga as Variáveis de quem o chamou.
_Avoid_: Fluxo filho, Componente, Bloco reutilizável

**Evento**:
Um acontecimento nomeado que um Nó emite durante uma Execução. Alimenta as métricas do Fluxo e pode sair da plataforma como webhook. O nome é declarado pela Empresa, não escrito à mão a cada uso.
_Avoid_: Acontecimento, Sinal, Marco, Webhook

**Traço**:
O registro do que aconteceu numa Execução, nó a nó: por onde passou, quanto demorou, o que enviou e recebeu, quanto custou em IA e onde quebrou.
_Avoid_: Log, Histórico, Auditoria, Trace

**Modelo de fluxo**:
Um Fluxo pronto de catálogo que a Empresa clona e adapta. Nunca é chamado de Template, que é outra coisa.
_Avoid_: Template, Preset, Exemplo

**Template**:
A mensagem pré-aprovada pela Meta, única forma de escrever para um Contato fora da janela de 24 horas. Pertence à Conta do WhatsApp, porque é lá que a Meta a aprova. Nunca é o nome de um Fluxo pronto.
_Avoid_: Modelo, HSM, Mensagem pronta

**Transferência para humano**:
O momento em que um Fluxo ou um Agente para de responder, marca o Contato como aguardando atendimento e avisa a Equipe. Só volta a responder quando alguém devolve ou por inatividade.
_Avoid_: Handoff, Escalação, Takeover
