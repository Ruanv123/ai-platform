# Spec: Agenda do Google e agendamento pelo Agente

Status: ready-for-agent

Depends on: `.scratch/fluxos-integracoes/spec.md`

Supersedes: `.scratch/fluxos-integracoes/issues/03-conector-de-agenda.md`

## Problem Statement

Agendar é o caso de uso número um do atendimento por WhatsApp no Brasil: consultório, salão, oficina, clínica. Hoje a Empresa não consegue fazer isso de verdade.

A Agenda da tela de Integrações pede uma URL base, um tipo de autenticação e dois caminhos (`/availability` e `/events`). Quase nenhum consultório tem uma API que responda nesse contrato. Quem agenda usa o Google Agenda, e não há como conectá-lo.

Os dois Nós de agenda do Fluxo dependem desse mesmo contrato HTTP. Além disso, obrigam a Empresa a desenhar o agendamento passo a passo: perguntar o dia, consultar, oferecer, confirmar, marcar. Um Agente de IA faz isso melhor numa conversa livre, mas não tem nenhuma ferramenta de agenda: só sabe consultar a Base de conhecimento.

Resultado: o Contato pede um horário, o Agente não consegue ver a agenda, e alguém da Equipe precisa marcar à mão.

## Solution

A Empresa conecta a **Agenda** com um clique em "Conectar com Google", no fluxo OAuth da própria Google. Depois disso:

- Na tela da Agenda, a Empresa vê os **Calendários** da conta e escolhe quais são **Calendários agendáveis**. Cada um tem nome e descrição de exibição, duração do compromisso, janela de atendimento (o Horário de atendimento da Empresa ou uma janela própria) e antecedência mínima.
- No formulário do Agente, uma seção "Agenda" permite escolher em quais Calendários agendáveis ele pode marcar.
- Com pelo menos um Calendário selecionado, o Agente ganha uma ferramenta de agenda. Com ela, na conversa, ele consulta Horários disponíveis, marca **Compromisso** depois da confirmação do Contato, lista o que o Contato tem marcado e cancela.
- No Playground, a consulta é real e marcar ou cancelar são simulados: a Empresa testa sem sujar a agenda real.
- Se o Google revogar o acesso, a Agenda fica com erro, a tela pede para reconectar e o Agente avisa o Contato de que não consegue ver a agenda agora, em vez de inventar horário.

O conector de agenda por HTTP genérico e os Nós de agenda do Fluxo deixam de existir. Quem tem sistema próprio de agenda usa o Nó de Requisição HTTP.

## User Stories

1. As an Owner ou Admin, I want to conectar a conta Google da Empresa com um clique, so that eu não precise configurar URL, token ou API
2. As an Owner ou Admin, I want to ver com qual conta Google a Agenda está conectada, so that eu saiba de onde vêm os horários
3. As a Member sem permissão de gerenciar Integrações, I want to não conseguir conectar nem desconectar a Agenda, so that só quem administra mexa na conexão da Empresa
4. As an Owner, I want to que a Agenda continue funcionando quando a pessoa que a conectou sai da Equipe, so that o atendimento não pare por causa de uma saída
5. As an Owner ou Admin, I want to ver o motivo quando eu recuso a autorização no Google, so that eu entenda por que a Agenda não foi conectada
6. As an Owner ou Admin, I want to ver todos os Calendários da conta Google conectada, so that eu escolha quais aceitam agendamento
7. As an Owner ou Admin, I want to liberar um Calendário para agendamento, so that os Agentes possam marcar nele
8. As an Owner ou Admin, I want to dar ao Calendário agendável um nome e uma descrição de exibição, so that o Agente apresente "Dra. Ana, dermatologia" em vez de um e-mail
9. As an Owner ou Admin, I want to definir a duração do compromisso de cada Calendário agendável, so that uma consulta de 40 minutos não seja oferecida em fatias de 30
10. As an Owner ou Admin, I want to que a janela do Calendário agendável seja por padrão o Horário de atendimento da Empresa, so that eu não precise configurar duas vezes
11. As an Owner ou Admin, I want to definir uma janela própria para um Calendário agendável, so that a Dra. Ana que só atende terça e quinta não receba compromissos na segunda
12. As an Owner ou Admin, I want to definir a antecedência mínima de cada Calendário agendável, so that ninguém marque um horário daqui a dez minutos
13. As an Owner ou Admin, I want to deixar de liberar um Calendário para agendamento, so that os Agentes parem de oferecê-lo
14. As an Owner ou Admin, I want to desconectar a Agenda, so that a plataforma deixe de acessar a conta Google da Empresa
15. As an Owner ou Admin, I want to reconectar a Agenda quando ela estiver com erro, so that o agendamento volte a funcionar
16. As an Owner ou Admin, I want to ver na tela de Integrações que a Agenda está com erro e por quê, so that eu não descubra só quando um Contato reclamar
17. As an Owner ou Admin, I want to escolher em quais Calendários agendáveis um Agente pode marcar, so that o Agente da dermatologia não marque na agenda da ortopedia
18. As an Owner ou Admin, I want to ver a seção "Agenda" do Agente desabilitada, com caminho para Integrações, quando a Agenda não está conectada, so that eu saiba o que falta
19. As an Owner ou Admin, I want to que o Agente de qualquer objetivo possa agendar, so that um Agente de Suporte também marque um retorno
20. As a Contato, I want to perguntar quais horários existem, so that eu escolha um que funcione para mim
21. As a Contato, I want to pedir horários de um profissional específico, so that eu seja atendido por quem já me conhece
22. As a Contato, I want to pedir "qualquer profissional", so that eu pegue o primeiro horário livre
23. As a Contato, I want to receber horários no fuso da Empresa, so that eu não chegue uma hora adiantado
24. As a Contato, I want to só receber horários de verdade livres, so that eu não marque algo que já está ocupado
25. As a Contato, I want to que o Agente confirme dia, hora e profissional antes de marcar, so that nada seja marcado por engano
26. As a Contato, I want to saber na hora se o horário que escolhi foi ocupado por outra pessoa, e receber alternativas, so that eu não saia achando que marquei
27. As a Contato, I want to receber a confirmação do compromisso na própria conversa, so that eu tenha o registro onde já estou
28. As a Contato, I want to perguntar o que tenho marcado, so that eu lembre dia e hora
29. As a Contato, I want to cancelar meu compromisso pela conversa, so that eu não precise ligar
30. As a Contato, I want to remarcar pela conversa, so that eu troque o horário sem perder o atendimento
31. As a Contato, I want to não conseguir cancelar o compromisso de outra pessoa, so that ninguém mexa no meu
32. As a Contato, I want to receber uma resposta honesta quando a agenda está fora do ar, so that eu saiba que preciso tentar de novo ou falar com alguém
33. As a Empresa, I want to que um Contato tenha no máximo um compromisso futuro por Calendário agendável, so that ninguém ocupe a semana inteira de um profissional
34. As a Empresa, I want to que o evento no Google traga o nome e o telefone do Contato e o motivo, so that a recepção saiba quem vem e para quê
35. As a Empresa, I want to que o evento no Google seja rastreável até o Contato, so that eu encontre a conversa a partir da agenda
36. As a Empresa, I want to que um compromisso apagado ou movido no Google seja respeitado pelo Agente, so that a agenda do Google continue sendo a verdade
37. As a Empresa, I want to que dois Contatos não consigam marcar o mesmo horário ao mesmo tempo, so that eu não tenha encaixe duplo
38. As an Owner ou Admin testando no Playground, I want to ver os Horários disponíveis reais, so that eu valide a configuração dos Calendários agendáveis
39. As an Owner ou Admin testando no Playground, I want to que marcar e cancelar sejam simulados, so that meus testes não criem compromissos na agenda da clínica
40. As an Owner ou Admin, I want to que os Nós de agenda sumam do editor de Fluxo, so that eu não monte um Fluxo com algo que não funciona mais
41. As an Owner ou Admin, I want to que um Fluxo que precise agendar entregue a conversa ao Agente, so that o agendamento tenha um único caminho

## Implementation Decisions

### Conexão OAuth

- Um único OAuth Client no Google Cloud, da plataforma. Escopos: `calendar.events`, `calendar.freebusy` e `calendar.calendarlist.readonly`, mais o e-mail da conta para exibição.
- `laravel/socialite` entra como dependência, só para o fluxo de autorização: redirect com `access_type=offline` e `prompt=consent` (garante refresh token a cada conexão), troca do código e leitura do e-mail. A Calendar API **não** usa Socialite nem `google/apiclient`: são chamadas com o cliente HTTP do Laravel.
- Duas rotas novas sob a área de Integrações: iniciar a conexão (redirect para o Google) e callback. Ambas exigem a permissão `ManageIntegrations`. O callback valida o `state` e grava a Agenda da Empresa do usuário autenticado.
- Credenciais do Google (client id, secret e redirect) vêm de configuração de serviços, lidas do ambiente.
- A Agenda é a Integração de tipo `agenda` e provedor `google`, continuando singleton por Empresa. As credenciais criptografadas guardam access token, refresh token, expiração e o e-mail da conta. Não se guarda quem autorizou.
- Reconectar com a Agenda já existente atualiza as credenciais da mesma Integração e limpa o erro. Não cria uma segunda.
- Desconectar apaga a Integração, os Calendários agendáveis e os vínculos Agente–Calendário. Os Compromissos permanecem como histórico. A revogação do token no Google é best-effort.

### Cliente do Google Calendar

- Um cliente fino, construído a partir da Agenda, com quatro operações: listar Calendários, consultar ocupação (`freeBusy`), criar evento e ler ou apagar evento.
- Renova o access token pelo refresh token quando expirado e persiste o novo token na Agenda.
- Um `invalid_grant` no refresh marca a Agenda com status de erro e a mensagem "Reconecte a conta Google", e lança uma exceção de domínio que as camadas de cima traduzem.
- O Google é chamado só por esse cliente. É a única fronteira externa da feature.

### Calendários agendáveis

- Nova tabela de Calendários agendáveis, pertencente à Empresa (escopo de tenant como os demais models) e à Agenda. Guarda:
  - id do Calendário no Google;
  - nome e descrição de exibição;
  - duração em minutos (padrão 30);
  - antecedência mínima em minutos (padrão 120);
  - janela própria (opcional): mesmo formato do Horário de atendimento da Empresa, com dias da semana e uma faixa de abertura e fechamento. Nula significa usar o Horário de atendimento da Empresa.
- A tela da Agenda (a página de Integração singleton `agenda`, que hoje mostra o formulário HTTP) passa a mostrar:
  - o estado da conexão;
  - a conta conectada;
  - a lista de Calendários vindos do Google, com opção de liberar e editar as regras de cada um;
  - os botões conectar, reconectar e desconectar.
- Um Calendário que sumiu do Google, ou que a Empresa deixou de liberar, sai dos Agentes sem aviso.

### Agente

- Relação muitos-para-muitos entre Agente e Calendário agendável, no mesmo padrão da relação com Coleções.
- O formulário do Agente ganha a seção "Agenda", com a seleção de Calendários agendáveis. Ela fica desabilitada, com link para Integrações, quando não há Agenda conectada.
- `WhatsAppAgent` e `PlaygroundAgent` registram as Tools de agenda quando o Agente tem pelo menos um Calendário agendável. O objetivo do Agente não interfere.

### Módulo de agendamento

Módulo profundo por trás das Tools, com uma interface pequena:

- **consultar Horários disponíveis**
  - Entrada: Calendários agendáveis permitidos, um ou "qualquer um", intervalo de datas e um limite.
  - Cálculo: janela ∩ (agora + antecedência mínima, fim do intervalo) − ocupação do `freeBusy`, fatiado pela duração, no fuso do Horário de atendimento da Empresa.
  - Saída: no máximo cerca de 10 Horários disponíveis, cada um rotulado com o Calendário agendável.
- **marcar Compromisso**
  - Entrada: Contato, Calendário agendável, início e motivo.
  - Recusa se o Contato já tem Compromisso futuro nesse Calendário agendável.
  - Sob um lock por Calendário, consulta o `freeBusy` de novo. Se o horário foi ocupado, devolve falha com alternativas.
  - Cria o evento: título `{nome do Contato} (via WhatsApp)`, descrição com telefone e motivo, sem convidados, e o id do Contato em `extendedProperties.private`.
  - Grava o Compromisso.
- **listar Compromissos do Contato**: relê cada Compromisso futuro no Google. Evento apagado ou cancelado vira Compromisso cancelado; evento movido tem o horário atualizado.
- **cancelar Compromisso**: só Compromissos futuros do próprio Contato. Apaga o evento no Google e marca o Compromisso como cancelado. Se o evento já não existe no Google, só marca como cancelado.

Regras adicionais:
- Modo simulado (Playground): consultar é real; marcar e cancelar devolvem sucesso com id fictício, sem chamar o Google e sem gravar Compromisso.
- Agenda com erro ou falha do Google: o módulo lança uma exceção de domínio, que as Tools transformam em texto de falha para o Agente.

### Compromisso

Nova tabela de Compromissos, pertencente à Empresa e ligada ao Calendário agendável e ao Contato. Guarda id do evento no Google, início, fim, motivo e status (marcado ou cancelado).

### Tools do Agente

- Quatro Tools finas sobre o módulo, no padrão de `SearchKnowledgeBase`, com nomes estáveis: `consultar_horarios`, `marcar_compromisso`, `listar_compromissos` e `cancelar_compromisso`.
- A descrição lista os Calendários agendáveis permitidos (nome e descrição de exibição) e instrui o Agente a só marcar depois de confirmação explícita do Contato, repetindo dia, hora e profissional.
- As Tools recebem o Contato da conversa pelo construtor. Não aceitam Contato como argumento do modelo, o que impede agir sobre outro Contato.
- As respostas são JSON, como em `SearchKnowledgeBase`. Falhas voltam como `{ "ok": false, "motivo": ... }` legível, nunca como exceção para o provedor.

### Remoção

- Sai do catálogo do editor, do validador de DSL, do publicador, do motor e do rótulo do Traço: os Nós `agenda_availability` e `agenda_create_event` e seus aliases (`calendar_availability`, `check_availability`, `calendar_create_event`, `create_calendar_event`).
- Sai o formulário HTTP da Agenda: URL base, tipo de autenticação e caminhos. O tipo `agenda` deixa de ser aceito na criação genérica de Integração, porque só nasce pelo OAuth.

## Testing Decisions

- **Um bom teste** exercita a feature por fora (uma rota HTTP, ou uma conversa do Agente em que o modelo chama a Tool) e verifica efeitos observáveis: resposta, requisições que chegaram ao Google, linhas gravadas e status da Agenda. Não verifica classes internas nem o formato das chamadas entre módulos.
- **Tudo externo é fake:**
  - Google via `Http::fake` (endpoints da Calendar API e de token);
  - OAuth via `Socialite::fake`;
  - modelo via `WhatsAppAgent::fake` / `PlaygroundAgent::fake` com `ToolCall`.
- **Seam 1: Tools pela conversa do Agente.** Cobre:
  - Horários disponíveis respeitando janela padrão e própria, duração, antecedência mínima, ocupação e fuso;
  - "qualquer um" rotulando por Calendário agendável;
  - marcar com nova consulta que encontra o horário ocupado;
  - limite de um Compromisso futuro;
  - conteúdo do evento criado;
  - listar com evento apagado e movido no Google;
  - cancelar Compromisso de outro Contato, que deve ser recusado;
  - `invalid_grant`, que deixa a Agenda com erro e devolve falha legível;
  - Playground sem requisições de escrita ao Google e sem Compromisso gravado;
  - Agente sem Calendário agendável sem as Tools.
  - Prior art: `tests/Feature/Knowledge/PlaygroundTest.php` (ToolCall de `search_knowledge_base`) e `tests/Feature/Channels/WhatsAppResponseFlowTest.php` (`WhatsAppAgent::fake` + `Http::fake`).
- **Seam 2: rotas HTTP.** Cobre:
  - redirect com escopos, `access_type` e `prompt`;
  - callback gravando a Agenda da Empresa;
  - callback com `state` inválido ou autorização recusada;
  - reconectar reaproveitando a mesma Integração;
  - permissão `ManageIntegrations` em todas as rotas;
  - liberar e editar Calendário agendável;
  - desconectar apagando Calendários agendáveis e vínculos e mantendo Compromissos;
  - seleção de Calendários agendáveis no Agente, recusando Calendário de outra Empresa.
  - Prior art: `tests/Feature/IntegrationManagementTest.php`, `tests/Feature/Integrations/IntegrationTest.php` e `tests/Feature/Agents/AgentTest.php`.
- **Testes do comportamento antigo são removidos, não adaptados:** criação e teste de conexão da Agenda por URL base (`IntegrationManagementTest`) e a página da Agenda com formulário HTTP (`IntegrationTest`). O validador de Fluxo passa a rejeitar `agenda_*` como Nó desconhecido.
- Não há teste unitário isolado do cálculo de Horário disponível: ele é coberto pela Tool.

## Out of Scope

- Nós de agenda no Fluxo. Um Fluxo que precisa agendar entrega ao Agente.
- Outros provedores de agenda (Outlook, Calendly, agendas próprias por HTTP).
- Webhook ou sincronização ativa com o Google: a reconciliação é preguiçosa, na leitura.
- Convite por e-mail ao Contato, lembrete automático antes do compromisso e confirmação por Template.
- Intervalo entre compromissos (buffer), limite de compromissos por dia e duração por tipo de serviço.
- Política de cancelamento com antecedência mínima.
- Tool própria de remarcar: remarcar é cancelar e marcar.
- Notificação à Equipe quando a Agenda cai (hoje só existe o toast).
- Várias contas Google por Empresa.

## Further Notes

- **Verificação do app pelo Google:** os escopos são sensíveis, não restritos. Isso exige verificação da tela de consentimento (domínio, política de privacidade, vídeo de demonstração), mas não auditoria CASA. Até a verificação sair, o app fica limitado a 100 usuários de teste e o refresh token expira em 7 dias. Vale começar a verificação em paralelo à implementação.
- O glossário já foi atualizado com Agenda, Calendário, Calendário agendável, Horário disponível e Compromisso. Também registra o comportamento do Playground.
- Esta spec contradiz a decisão de `fluxos-integracoes` de que "o conector curado compila para o nó genérico" e de que o primeiro conector é um Nó de Agenda. Vale registrar como ADR.
