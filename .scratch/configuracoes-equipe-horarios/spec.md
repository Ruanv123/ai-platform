# Spec: Configurações — abas de Equipe e Horário de atendimento

Status: ready-for-agent

## Problem Statement

A tela de Configurações não reflete como o usuário pensa a própria conta.

**A Equipe está no lugar errado.** Ela ocupa um item na barra lateral principal, ao lado de Agentes, Base de conhecimento, Canais e Integrações — que são as coisas que a Empresa *opera* no dia a dia. Gerenciar quem tem acesso não é operação, é configuração: o usuário entra ali uma vez por mês, quando alguém entra ou sai do time.

**Uma única página faz três trabalhos.** Hoje "Empresa" é uma página só, longa, que empilha os dados do negócio, o Horário de atendimento, a lista de membros e os Convites pendentes. Quem quer só corrigir o horário de sábado rola por nove campos de texto livre antes de chegar na grade. Quem quer só remover um membro rola a página inteira. Não há como mandar a alguém o link "vá ajustar o horário de atendimento".

**A mesma página tem duas URLs.** `/settings/teams` e `/settings/company` renderizam exatamente o mesmo conteúdo, resquício de uma migração anterior. O usuário chega no mesmo lugar por dois caminhos diferentes, e o item "Equipe" da barra lateral leva para uma página cujo título é "Dados da Empresa".

**Os Convites só nascem e morrem.** Depois de criado, um Convite só pode ser cancelado. Quando o e-mail não chega — cai em spam, o endereço tem um typo, a pessoa usa outro e-mail no trabalho — não existe nenhuma forma de entregar o link por outro meio. O convite já tem um link único e funcional, mas ele está preso dentro do e-mail.

## Solution

Configurações passa a ter seis abas, em lista plana, e a Equipe sai da barra lateral principal.

- **A barra lateral principal perde "Equipe".** Ficam Dashboard, Agentes, Base de conhecimento, Canais, Integrações e Configurações. A barra lateral passa a conter só o que a Empresa opera.

- **O submenu de Configurações vai de quatro para seis abas**, nesta ordem: Perfil, Segurança, Aparência, Empresa, Equipe, Horário de atendimento. As três primeiras são sobre a pessoa, as três últimas sobre a Empresa, mas sem separador nem título de grupo — a ordem já comunica.

- **"Empresa" fica só com os dados do negócio**: nome fantasia, segmento, site, descrição, produtos e serviços, público-alvo, telefone público, e-mail público e endereço.

- **"Horário de atendimento" ganha aba própria**, com a grade de dias e faixas de horário **e o fuso horário**. O fuso sai dos dados da Empresa porque ele existe para dizer em que referência as faixas devem ser lidas — separar os dois obriga o usuário a pular de aba para configurar uma coisa só.

- **"Equipe" ganha aba própria**, com os membros no topo, os Convites logo abaixo e o botão de sair da Empresa no fim. Convite não vira aba: ele é o meio de alguém virar membro, não um objeto de primeira classe na navegação.

- **Cada Convite pendente ganha "copiar link"**, que copia o mesmo endereço que vai no e-mail, para o Owner ou Admin entregar por WhatsApp, Slack ou pessoalmente quando o e-mail falhar.

- **A URL duplicada morre.** `/settings/teams` deixa de existir e passa a responder 404.

O Member continua vendo as seis abas. Empresa e Horário de atendimento aparecem em modo leitura, porque são contexto útil do negócio. Equipe mostra os membros sem ações e **sem a seção de Convites** — quem não pode convidar nem cancelar não tem o que fazer ali.

## User Stories

### Navegação

1. As a Owner, I want to não ver "Equipe" na barra lateral principal, so that a barra lateral contenha só o que eu opero no dia a dia
2. As a usuário, I want to encontrar Equipe dentro de Configurações, so that eu procure gestão de acesso onde ela conceitualmente mora
3. As a usuário, I want to ver seis abas em Configurações numa lista plana, so that eu alcance qualquer configuração em um clique
4. As a usuário, I want to ver as abas na ordem Perfil, Segurança, Aparência, Empresa, Equipe, Horário de atendimento, so that as configurações da minha conta venham antes das do negócio
5. As a usuário, I want to ver a aba atual destacada no submenu, so that eu saiba onde estou
6. As a usuário, I want to ler no cabeçalho de Configurações uma descrição que cubra conta e Empresa, so that o texto não contradiga as abas listadas abaixo dele
7. As a usuário, I want to que cada aba tenha sua própria URL, so that eu possa salvar ou compartilhar o link de uma configuração específica
8. As a usuário, I want to que cada aba tenha seu próprio título de página e breadcrumb, so that eu me situe pela aba do navegador e pelo topo da tela

### Empresa

9. As a Owner ou Admin, I want to editar os dados do negócio numa aba que contém só eles, so that eu não role por horários e membros para corrigir o site da empresa
10. As a Owner ou Admin, I want to salvar os dados da Empresa e receber confirmação, so that eu saiba que a alteração foi aplicada
11. As a Owner ou Admin, I want to ver erros de validação em português por campo, so that eu saiba exatamente o que corrigir
12. As a Owner ou Admin, I want to que salvar os dados da Empresa não altere meu Horário de atendimento, so that formulários separados não interfiram um no outro
13. As a Member, I want to visualizar os dados da Empresa sem poder editá-los, so that eu conheça o contexto do negócio sem arriscar alterações

### Horário de atendimento

14. As a Owner ou Admin, I want to abrir uma aba dedicada ao Horário de atendimento, so that eu ajuste um horário sem passar pelos dados do negócio
15. As a Owner ou Admin, I want to marcar quais dias da semana a Empresa atende, so that os dias fechados fiquem explícitos
16. As a Owner ou Admin, I want to definir abertura e fechamento por dia, so that cada dia possa ter sua própria faixa
17. As a Owner ou Admin, I want to escolher o fuso horário na mesma aba do horário, so that eu configure horário e sua referência de uma vez
18. As a Owner ou Admin, I want to ver os dias sempre na ordem de segunda a domingo, so that a grade seja previsível
19. As a Owner ou Admin, I want to ver erros de validação de horário em português, so that eu corrija um formato inválido sem adivinhar
20. As a Owner ou Admin, I want to que salvar o Horário de atendimento não altere os dados do negócio, so that eu não sobrescreva campos que nem vi
21. As a Member, I want to ler o Horário de atendimento como texto, so that eu saiba quando a Empresa atende
22. As a Member, I want to ver "Fechado" nos dias sem atendimento, so that a ausência de horário seja informação e não um espaço vazio
23. As a Member, I want to não ver campos desabilitados na visão de leitura, so that a tela não pareça quebrada

### Equipe

24. As a Owner ou Admin, I want to abrir uma aba dedicada à Equipe, so that eu gerencie acesso sem passar pelos dados da Empresa
25. As a Owner, I want to ver todos os membros com nome, e-mail e Papel, so that eu saiba quem tem acesso à Empresa
26. As a Owner, I want to alterar o Papel de um membro, so that eu ajuste o acesso conforme a função muda
27. As a Owner, I want to remover um membro com confirmação, so that eu não tire o acesso de alguém por clique acidental
28. As a Member, I want to visualizar a lista de membros sem ações, so that eu saiba com quem trabalho sem poder alterar nada
29. As a Member, I want to sair da Empresa a partir da aba Equipe, so that eu deixe de ter acesso quando não fizer mais parte do time
30. As a Owner, I want to não ver a opção de sair da Empresa, so that a conta nunca fique sem dono

### Convites

31. As a Owner ou Admin, I want to ver os Convites pendentes logo abaixo dos membros, so that eu veja o quadro completo de quem tem ou terá acesso
32. As a Owner ou Admin, I want to convidar alguém por e-mail definindo o Papel, so that minha equipe entre na plataforma
33. As a Owner ou Admin, I want to cancelar um Convite pendente com confirmação, so that eu revogue o acesso antes que a pessoa entre
34. As a Owner ou Admin, I want to copiar o link de um Convite pendente, so that eu entregue o convite por outro meio quando o e-mail não chegar
35. As a Owner ou Admin, I want to receber confirmação de que o link foi copiado, so that eu saiba que posso colar
36. As a Owner ou Admin, I want to ver o link em texto selecionável quando a cópia automática falhar, so that eu ainda consiga entregá-lo manualmente
37. As a Owner ou Admin, I want to que o link copiado seja o mesmo que vai no e-mail, so that as duas entregas levem ao mesmo lugar
38. As a Member, I want to não ver a seção de Convites, so that a tela não mostre nada que eu não posso usar

### Isolamento e rotas

39. As a usuário, I want to receber 404 ao abrir a URL antiga de Equipe, so that não existam dois endereços para a mesma configuração
40. As a Member, I want to ser bloqueado ao tentar salvar Empresa ou Horário de atendimento por requisição direta, so that as permissões valham além da interface
41. As a visitante não autenticado, I want to ser mandado para o login ao abrir qualquer aba de Configurações, so that nada da Empresa vaze
42. As a usuário sem Empresa, I want to ser levado ao onboarding ao tentar abrir Configurações, so that eu não caia em telas vazias

## Implementation Decisions

### Rotas

Três páginas substituem a única página atual:

| Aba | URL | Nome da rota |
| --- | --- | --- |
| Empresa | `/settings/company` | `company.edit` / `company.update` |
| Equipe | `/settings/team` | `team.edit` |
| Horário de atendimento | `/settings/business-hours` | `business-hours.edit` / `business-hours.update` |

`teams.edit` e `teams.update` são **removidas sem redirect**: `/settings/teams` passa a responder 404. A decisão é deliberada — a URL antiga é resquício de migração e mantê-la viva reintroduz a duplicação que esta spec existe para eliminar.

As sub-rotas de Equipe acompanham a renomeação, de `settings/teams/...` para `settings/team/...`, com os nomes `team.members.update`, `team.members.destroy`, `team.leave`, `team.invitations.store` e `team.invitations.destroy`. Deixar `settings/teams/*` convivendo com `settings/team` produziria exatamente o resíduo que a spec remove.

As rotas públicas de Convite (`invitations.show`, `invitations.accept`, `invitations.decline`) **não mudam** — elas são o alvo dos links já enviados por e-mail, e renomeá-las quebraria convites em trânsito.

### Controllers

O controller único que hoje monta todas as props em toda visita se divide em três, todos em `Settings/`, cada um carregando **apenas as props da sua aba**:

- **`CompanyController`** — dados do negócio e a lista de segmentos. Não carrega membros, Convites, fuso nem dias da semana.
- **`TeamController`** — membros, Convites, Papéis atribuíveis, permissões e se o usuário pode sair. Não carrega dados do negócio.
- **`BusinessHoursController`** — grade de horários, fuso atual, lista de fusos e dias da semana. Não carrega membros nem Convites.

Os Convites só entram nas props quando o usuário pode criar ou cancelar Convite. Para o Member a chave é **ausente**, não uma lista vazia: ausente diz "isto não é seu", vazia diz "você não tem convites".

O `TeamController` atual, que decide o componente a renderizar com `$request->routeIs(...)`, deixa de existir nessa forma.

### Validação

`SaveCompanyRequest` hoje serve onboarding e edição, alternando entre `required` e `sometimes` via `routeIs()`. Com mais duas telas isso viraria uma árvore de condicionais. Passa a haver três FormRequests:

- **`SaveCompanyRequest`** — onboarding, todos os campos obrigatórios, incluindo horário e fuso (o onboarding continua coletando tudo numa tela só).
- **`UpdateCompanyRequest`** — só os campos de dados do negócio.
- **`UpdateBusinessHoursRequest`** — só a grade e o fuso.

As regras e as mensagens em português ficam num ponto compartilhado, para que a mesma mensagem não seja escrita três vezes e saia de sincronia. A autorização de cada request checa a policy de atualizar a Empresa, exatamente como hoje.

O contrato do payload de horários **não muda**: continua um mapa com as sete chaves `monday` a `sunday`, cada uma com `enabled`, `open` e `close`.

### Dias da semana e segmentos

A lista de dias da semana e a de segmentos hoje são métodos estáticos no controller de onboarding, chamados de fora. Com três controllers novos consumindo, isso seria controller importando controller.

Passa a existir um **enum `Weekday`**, com chaves em TitleCase e métodos `label()` e `options()`, espelhando o que `TeamSegment` já faz. Os valores do enum são exatamente as chaves já gravadas no JSON de horários, então **não há migração** nem alteração de dados. Os métodos estáticos saem do controller de onboarding, e os consumidores passam a usar `Weekday::options()` e `TeamSegment::options()` diretamente.

### Frontend

Três páginas Inertia substituem a página única, sob o mesmo layout de Configurações. A página antiga em `pages/teams/` é removida, junto com o arquivo que apenas a reexportava e com o caso `teams/` na resolução de layouts.

O submenu de Configurações ganha os itens Equipe e Horário de atendimento e é reordenado. Sem separador, sem título de grupo. A descrição do cabeçalho passa a cobrir conta e Empresa, em vez de falar só do perfil.

Os modais de convidar membro, remover membro e cancelar convite são reaproveitados como estão.

**Visão de leitura do Horário de atendimento** (Member): uma linha por dia, com a faixa ou "Fechado", e o fuso horário abaixo. Texto, não campos desabilitados — campo desabilitado parece defeito, texto parece informação.

**Copiar link**: monta a URL do Convite a partir do código que já vai nas props e escreve na área de transferência. Sucesso mostra confirmação. Quando a API de área de transferência não existe — ela exige contexto seguro, e em HTTP puro o botão falharia mudo — mostra o link em texto selecionável para cópia manual. Sem polyfill. O botão só aparece para quem pode criar Convite.

### O que não muda

Nenhuma migração. Nenhuma alteração no schema, no formato do JSON de horários, nas policies, no fluxo de aceitar convite ou no onboarding da Empresa.

## Testing Decisions

### O que faz um bom teste aqui

Testar o comportamento observável pela borda: dado um usuário com determinado Papel, uma requisição a uma rota produz determinada resposta, determinadas props e determinado efeito no banco. Nada de asserção sobre nomes de método, estrutura interna de controller ou como as regras de validação foram organizadas — a divisão em três FormRequests é reorganização interna, e um teste que a enxergue trava a refatoração seguinte.

### Seam

**Um só, e já existe**: requisição HTTP → resposta Inertia, via `actingAs(...)->get(route(...))` com `assertInertia`. É a arte prévia de todo o projeto (`tests/Feature/Teams/TeamTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Feature/CompanyOnboardingTest.php`). **Nenhum seam novo é criado.**

Cabem nele: qual componente cada aba renderiza, quais props ela carrega e quais **não** carrega, o modo leitura do Member, a ausência de Convites para o Member, a validação de cada um dos três formulários, o isolamento por Papel em requisição direta, e as rotas que deixaram de existir.

O enum `Weekday` **não ganha teste unitário**. `options()` é uma lista constante, já coberta pela asserção da prop de dias na aba de Horário de atendimento; testá-la isoladamente seria testar uma constante e criar um segundo seam para nada.

Para as rotas removidas, a arte prévia é o teste existente que afirma que a rota de exclusão de Empresa não está registrada.

### Redistribuição dos testes

`tests/Feature/Teams/TeamTest.php` cobre hoje dados da Empresa e Equipe na mesma rota. Ele se divide, **sem perder nenhum caso**:

- **`tests/Feature/Settings/CompanyTest.php`** — renderização, atualização, validação e leitura para Member dos dados do negócio.
- **`tests/Feature/Settings/BusinessHoursTest.php`** — grade e fuso: renderização, atualização, validação e a visão de leitura do Member.
- **`tests/Feature/Teams/TeamTest.php`** — membros, permissões, sair da Empresa, e as rotas que não existem.

`TeamMemberTest.php` e `TeamInvitationTest.php` continuam onde estão, atualizando os nomes de rota. `TeamInvitationTest` ganha a asserção de que o código do Convite chega nas props — insumo do copiar link — e a de que a chave de Convites está ausente para o Member.

`PruneExpiredTeamInvitationsTest.php`, `CompanyOnboardingTest.php` e `DashboardTest.php` não referenciam nenhuma rota removida e ficam intocados.

### Cobertura que esta spec não alcança

**O comportamento do botão "copiar link" não é testado.** O projeto usa Pest 5 sem plugin de browser, então a chamada à área de transferência, a confirmação e o caminho de fallback não têm seam automatizável. O teste para nas props: o código do Convite chega ao frontend.

A alternativa seria instalar um plugin de teste de browser, descartada de propósito: é dependência nova e infraestrutura inteira por causa de um manipulador de clique, e o seam resultante viraria convite a testar o resto da interface por ali. **O copiar link é verificado à mão.**

## Out of Scope

- **Reenviar convite** por e-mail e renovar o prazo. Convite expirado não bloqueia novo convite para o mesmo endereço, então reconvidar já resolve.
- **Lista de convites expirados.** Convites continuam sumindo da tela ao expirar, sem aviso.
- **Histórico de convites aceitos.** Quem aceitou já aparece na lista de membros.
- **Alterar o Papel de um convite pendente.** Cancelar e reconvidar resolve, a um custo de interface que não se paga.
- **Redirect da URL antiga** `/settings/teams`. É 404 por decisão.
- **Excluir a Empresa**, que segue indisponível.
- **Separar Convites em aba própria.**
- **Qualquer mudança no onboarding**, que continua coletando dados do negócio, horário e fuso numa tela só.
- **Qualquer mudança de schema ou migração.**

## Further Notes

O glossário em `CONTEXT.md` já foi atualizado com os dois termos que esta spec introduz na navegação: **Convite** (o pedido pendente para alguém entrar na Equipe com um Papel, que expira em três dias e some da lista ao expirar) e **Horário de atendimento** (os dias e faixas em que a Empresa atende, com o fuso em que são lidos).

Não há ADR nesta spec. A divisão de telas é barata de reverter, não é surpreendente para quem chega depois, e não houve trade-off real entre alternativas — é arrumação de resíduo, não decisão arquitetural. O ADR existente sobre Canal não toca nesta área.

Os redirects após convidar, cancelar convite, alterar Papel e remover membro apontam hoje para a rota que deixa de existir, nos controllers de Convite e de membro; passam a apontar para a aba de Equipe. O `DashboardController` já referencia `company.edit`, que sobrevive, e não precisa de alteração.
