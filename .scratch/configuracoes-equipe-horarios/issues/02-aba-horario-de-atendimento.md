# 02: Aba Horário de atendimento

**What to build:** O Horário de atendimento ganha aba própria em Configurações, com URL própria, e sai da página de Empresa.

Owner e Admin abrem a aba e encontram só o que precisam para responder "quando a gente atende": os sete dias da semana, sempre de segunda a domingo, cada um podendo ser marcado como dia de atendimento e receber horário de abertura e fechamento, mais o fuso horário em que essas faixas devem ser lidas. O fuso vem junto porque ele não faz sentido sozinho — deixá-lo nos dados do negócio obrigaria a pular de aba para configurar uma coisa só. Salvar confirma na tela, e erros de formato aparecem em português no campo errado.

Member abre a mesma aba e **lê** o horário: uma linha por dia, com a faixa ou "Fechado", e o fuso embaixo. Texto, não campos desabilitados — campo desabilitado parece defeito, texto parece informação. Hoje essa visão não existe: quem não pode editar vê apenas o fuso solto e a grade some inteira.

O bloco de horários desaparece da página de Empresa. A partir daqui, salvar os dados do negócio não toca mais no horário de atendimento, e salvar o horário não toca nos dados do negócio — são dois formulários com campos disjuntos, e nenhum sobrescreve o que o outro guarda.

A validação do horário passa a viver separada da validação dos dados do negócio, sem que as mensagens em português precisem ser escritas duas vezes. O onboarding continua coletando tudo numa tela só, com todos os campos obrigatórios, sem mudança.

**Blocked by:** 01 (Preparação: enum Weekday e ordem do submenu)

**Status:** done

- [x] "Horário de atendimento" aparece no submenu de Configurações, depois de Empresa, e tem URL própria
- [x] A aba tem título de página e breadcrumb próprios, e fica destacada no submenu quando ativa
- [x] Owner e Admin marcam quais dias a Empresa atende e definem abertura e fechamento por dia
- [x] Os dias aparecem sempre na ordem de segunda a domingo
- [x] O fuso horário é escolhido nesta aba e some dos dados da Empresa
- [x] Salvar confirma na tela e persiste; erros de formato de horário e de fuso aparecem em português
- [x] Member vê o horário como texto, uma linha por dia, com "Fechado" nos dias sem atendimento e o fuso abaixo
- [x] Member não vê campos, desabilitados ou não, na visão de leitura
- [x] Member recebe negação ao tentar salvar o horário por requisição direta
- [x] Salvar os dados da Empresa não altera o horário de atendimento, e salvar o horário não altera os dados do negócio
- [x] O controller da aba carrega apenas as props do horário: não carrega membros nem Convites
- [x] A validação do horário está separada da validação dos dados do negócio, com as mensagens em português num único lugar
- [x] O onboarding continua exigindo horário e fuso, sem mudança de comportamento
- [x] O formato do horário gravado não muda: as mesmas sete chaves, com dia ativo, abertura e fechamento
- [x] Existe cobertura na borda HTTP/Inertia para renderização, props carregadas, atualização, validação e visão de leitura do Member
- [x] Os casos de horário que hoje vivem no teste de Empresa migram para o novo arquivo, sem perder nenhum
