# 04: Aba Empresa e remoção da página antiga

**What to build:** A página que servia a tudo já está vazia — horários saíram no 02, membros e Convites no 03. Este ticket a substitui por uma aba de Empresa de verdade e apaga o que sobrou.

Owner e Admin abrem Empresa e encontram só os dados do negócio: nome fantasia, segmento, site, descrição, produtos e serviços, público-alvo, telefone público, e-mail público e endereço. Sem rolar por horários nem por lista de gente. Salvar confirma na tela, e os erros aparecem em português por campo. Member vê os mesmos dados em leitura, porque são contexto útil do negócio, e recebe negação se tentar salvar por requisição direta.

Por baixo, a aba deixa de ser servida pelo controller que decidia qual página renderizar olhando para o nome da rota, e passa a ter um controller que carrega **apenas** os dados do negócio e a lista de segmentos — nem membros, nem Convites, nem fusos, nem dias da semana, que é o que ele montava em toda visita mesmo quando ninguém ia olhar. A validação dos dados do negócio também passa a viver separada da do onboarding, sem duplicar as mensagens em português.

O resíduo some: a página antiga, o arquivo que apenas a reexportava, o caso de resolução de layout que existia só para ela, e a duplicação em que duas rotas diferentes levavam ao mesmo conteúdo. No fim, Configurações tem seis abas — Perfil, Segurança, Aparência, Empresa, Equipe, Horário de atendimento — cada uma com sua URL, e nenhuma delas com dois endereços.

O onboarding continua coletando tudo numa tela só, com todos os campos obrigatórios, sem mudança.

**Blocked by:** 02 (Aba Horário de atendimento), 03 (Aba Equipe e copiar link do Convite)

**Status:** done

- [x] A aba Empresa mostra apenas os dados do negócio, sem horários, membros ou Convites
- [x] Owner e Admin editam e salvam os dados do negócio, com confirmação na tela
- [x] Erros de validação aparecem em português, por campo
- [x] Member visualiza os dados do negócio em leitura e recebe negação ao tentar salvar por requisição direta
- [x] A aba tem título de página e breadcrumb próprios, e fica destacada no submenu quando ativa
- [x] O controller da aba carrega apenas dados do negócio e segmentos
- [x] Nenhum controller decide qual página renderizar olhando para o nome da rota
- [x] A validação dos dados do negócio está separada da do onboarding, com as mensagens em português num único lugar
- [x] A página antiga, o arquivo que a reexportava e o caso de layout criado para ela deixam de existir
- [x] Existe um único endereço para a Empresa; a duplicação de rotas acabou
- [x] Configurações tem exatamente seis abas, na ordem Perfil, Segurança, Aparência, Empresa, Equipe, Horário de atendimento
- [x] O onboarding continua funcionando sem mudança de comportamento
- [x] Existe cobertura na borda HTTP/Inertia para renderização, props carregadas, atualização, validação e leitura pelo Member
- [x] Os casos de dados do negócio que hoje vivem no teste de Empresa migram para o novo arquivo, sem perder nenhum
- [x] Visitante não autenticado é mandado para o login em qualquer aba de Configurações, e usuário sem Empresa vai para o onboarding
