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

**Dados da Empresa**:
O que a Empresa é e como encontrá-la: segmento, descrição do negócio, produtos e serviços, público-alvo, contatos públicos, endereço e Horário de atendimento. Todo Agente da Empresa os conhece sem consultar a Base de conhecimento.
_Avoid_: Perfil da Empresa, Cadastro, Sobre

**Voz da marca**:
O jeito de falar da Empresa, válido para todos os seus Agentes: uma descrição de como a marca soa, com frases de exemplo tiradas da própria marca. Trata de como se diz, nunca do que não se diz ou não se faz, que é Limite. Pertence à Empresa, não ao Agente; cada Agente pode ajustá-la com o seu Estilo.
_Avoid_: Tom de voz, Personalidade, Branding, Estilo

**Leitura do site**:
Uma passada da plataforma pelas páginas-chave do site da Empresa, com a sua situação: em andamento, concluída ou com falha. Transforma o que leu em Documentos da Coleção do site e em valores propostos para os Dados da Empresa e a Voz da marca, que só valem depois que alguém da Equipe confirma; nunca sobrescreve em silêncio o que já foi confirmado. Só roda quando alguém pede. A Empresa guarda o histórico; vale a última.
_Avoid_: Scraping, Crawl, Importação, Varredura, Scan

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
Como o Agente fala: tom, uso de emojis, tamanho das respostas e diretrizes livres sobre a forma da resposta. É o ajuste do Agente sobre a Voz da marca, e vale por cima dela quando as duas divergem. Nunca diz o que ele faz nem o que ele evita.
_Avoid_: Diretriz de resposta, Instrução, Personalidade

**Limite**:
Algo que o Agente nunca faz, em qualquer conversa — um assunto que evita, uma informação que não passa, um compromisso que não assume.
_Avoid_: Regra, Proteção, Guardrail, Restrição

**Cenário**:
Uma situação específica descrita em linguagem natural — quando ela acontece e o que o Agente deve fazer — podendo citar Ações. O Agente a interpreta dentro da conversa livre, sem garantia de ordem; não é um roteiro com ordem garantida.
_Avoid_: Instrução, Playbook, Roteiro, Fluxo

**Ação**:
Algo que o Agente pode fazer além de responder — consultar a Base de conhecimento, marcar na agenda, fazer a Transferência para humano. A Empresa liga as que o Agente pode usar; um Cenário só pode pedir uma Ação ligada.
_Avoid_: Ferramenta, Tool, Habilidade, Integração

**Rascunho do agente**:
A configuração do Agente que está sendo editada e ainda não vale nos Canais. É sobre ela que o Playground e as Situações de teste rodam.
_Avoid_: Edição, Pendente

**Versão do agente**:
O retrato da configuração do Agente no momento em que foi publicado. É o que atende nos Canais; editar cria Rascunho, publicar cria Versão nova.
_Avoid_: Revisão, Snapshot, Release

**Memória do agente**:
Os fatos curtos e importantes que um Agente decidiu guardar sobre um Contato — uma preferência, um vínculo, algo já resolvido. Pertence ao par Agente e Contato: outro Agente não a enxerga. Não desliza como o Histórico e vale em todos os Canais do Contato. A Empresa pode vê-la e apagá-la.
_Avoid_: Contexto, Histórico, Perfil

**Situação de teste**:
Uma conversa de exemplo salva junto do Agente, com o comportamento esperado — o que a resposta deve ou não conter e quais Ações deve ou não usar. Roda de novo sobre o Rascunho a cada mudança para mostrar o que quebrou.
_Avoid_: Cenário, Eval, Caso de teste, Teste

**Playground**:
O chat de teste do atendimento. Roda sobre o Rascunho do agente e nada é enviado ao Contato. Consultar Horários disponíveis é real; marcar e cancelar Compromisso são simulados.
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

**Coleção do site**:
A Coleção que a Leitura do site cria e mantém, com um Documento por página lida e o FAQ do site como Documento FAQ. A Leitura só mexe nos Documentos que ela mesma criou: atualiza os que continuam no site, remove os que sumiram e não traz de volta os que alguém da Equipe apagou. Fora isso, é uma Coleção como outra qualquer, e todo Agente novo nasce consultando-a.
_Avoid_: Coleção do site importado, Base do site

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
Uma conexão configurada com um sistema externo que o Agente usa para consultar ou registrar coisas lá fora; hoje, só a Agenda. É outra coisa que um Canal: Canal traz a conversa, Integração leva e busca o resultado.
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

**Template**:
A mensagem pré-aprovada pela Meta, única forma de escrever para um Contato fora da janela de 24 horas. Pertence à Conta do WhatsApp, porque é lá que a Meta a aprova.
_Avoid_: Modelo, HSM, Mensagem pronta

**Transferência para humano**:
O momento em que o Agente para de responder, marca o Contato como aguardando atendimento e avisa a Equipe. Só volta a responder quando alguém devolve ou por inatividade.
_Avoid_: Handoff, Escalação, Takeover
