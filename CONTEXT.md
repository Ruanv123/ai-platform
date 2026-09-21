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
Uma configuração de atendimento pertencente à Empresa — persona, objetivo, estilo, instruções e Coleções consultáveis. Uma Empresa pode ter vários, e o mesmo Agente pode atender em vários Canais.
_Avoid_: Bot, Assistente, Chatbot, Persona

**Instrução**:
Uma orientação escrita pelo cliente que entra no prompt do Agente. Formam uma lista ordenada.
_Avoid_: Regra, Prompt, Diretriz

**Playground**:
O chat de teste do atendimento. Roda sobre o rascunho — do formulário, no caso do Agente, e da Versão não publicada, no caso do Fluxo — e nada é enviado ao Contato. No Fluxo, mostra o Traço do turno ao lado da conversa.
_Avoid_: Simulador, Preview, Sandbox

**Agente inativo**:
Um Agente que não responde nos Canais vinculados, mas continua utilizável no Playground.
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
As credenciais de app da Meta usadas pelo Canal: WABA ID, tokens, verify token e o estado de verificação do webhook.
_Avoid_: WABA, Conta Meta, Business Account

**Número do WhatsApp**:
O número que recebe e responde mensagens, com sua identidade e qualidade. É a ele que o Canal se prende.
_Avoid_: Telefone, WhatsApp, Linha

**WhatsApp de testes**:
O Tipo de canal que conecta um número de WhatsApp por um serviço não oficial, usado apenas em desenvolvimento. Não é oferecido aos clientes.
_Avoid_: Evolution, WhatsApp não oficial, WhatsApp alternativo

**Integração**:
Uma conexão configurada com um sistema externo — CRM, agenda, e-commerce — que os Nós de um Fluxo usam para consultar ou registrar coisas lá fora. É outra coisa que um Canal: Canal traz a conversa, Integração leva e busca o resultado. Guarda a credencial; o Fluxo guarda o que fazer com ela.
_Avoid_: Conector, Canal, App

### Atendimento

**Contato**:
A pessoa que fala com a Empresa pelos Canais, identificada pelo telefone dentro da Empresa. A mesma pessoa falando em dois Canais continua sendo um único Contato.
_Avoid_: Cliente, Lead, Usuário, Destinatário

**Mensagem**:
Cada mensagem trocada entre um Contato e o Agente, em qualquer direção, sempre presa ao Canal por onde passou.
_Avoid_: Interação, Evento, Ticket

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
O momento em que um Fluxo para de responder, marca o Contato como aguardando atendimento e avisa a Equipe. O Fluxo só volta a responder quando alguém devolve ou por inatividade.
_Avoid_: Handoff, Escalação, Takeover
