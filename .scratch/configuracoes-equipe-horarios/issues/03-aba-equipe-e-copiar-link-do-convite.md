# 03: Aba Equipe e copiar link do Convite

**What to build:** A Equipe sai da barra lateral principal e vira aba de Configurações, com URL própria, e cada Convite pendente passa a poder ser entregue por fora do e-mail.

A barra lateral principal perde "Equipe" e fica só com o que a Empresa opera no dia a dia: Dashboard, Agentes, Base de conhecimento, Canais, Integrações e Configurações. Gerenciar quem tem acesso não é operação, é configuração — o usuário entra ali quando alguém entra ou sai do time. A URL antiga de Equipe, que era o destino daquele item da barra lateral e renderizava a mesma coisa que a página de Empresa, **deixa de existir e responde 404**. Não há redirect: ela é resquício de migração, e mantê-la viva reintroduz a duplicação que este trabalho existe para eliminar.

Na aba nova, Owner e Admin encontram o quadro completo de quem tem ou terá acesso: os membros no topo, com nome, e-mail e Papel, podendo trocar o Papel de alguém ou removê-lo com confirmação; os Convites pendentes logo abaixo, podendo convidar por e-mail com Papel definido ou cancelar um Convite com confirmação; e, no fim, sair da Empresa — que continua indisponível para o Owner, para a conta nunca ficar sem dono.

**O Convite ganha "copiar link".** Hoje, quando o e-mail não chega — cai em spam, o endereço tem um typo, a pessoa usa outro e-mail no trabalho — o Convite morre ali, porque o link único que já existe está preso dentro do e-mail. Owner e Admin passam a copiar esse mesmo endereço para entregar por WhatsApp, Slack ou pessoalmente, com confirmação de que a cópia aconteceu. Quando a cópia automática não estiver disponível — ela exige contexto seguro, e em HTTP puro o botão falharia mudo — o link aparece em texto selecionável para cópia manual. O link copiado é o mesmo que vai no e-mail e leva ao mesmo lugar.

Member abre a aba e vê a lista de membros sem nenhuma ação, e **nenhuma seção de Convites**: quem não pode convidar nem cancelar não tem o que fazer ali. Os Convites nem chegam ao navegador do Member.

Membros e Convites desaparecem da página de Empresa, que a partir daqui fica só com os dados do negócio.

As rotas de membro, Convite e sair da Empresa acompanham a mudança de endereço, para não sobrar um prefixo órfão apontando para a URL que virou 404. As rotas públicas de Convite — o link que abre o cadastro, aceitar e recusar — **não mudam**, porque são o alvo de convites já enviados que ainda podem ser aceitos.

**Blocked by:** 01 (Preparação: enum Weekday e ordem do submenu)

**Status:** done

- [x] "Equipe" não aparece mais na barra lateral principal
- [x] "Equipe" aparece no submenu de Configurações, entre Empresa e Horário de atendimento, e tem URL própria
- [x] A aba tem título de página e breadcrumb próprios, e fica destacada no submenu quando ativa
- [x] A URL antiga de Equipe responde 404, sem redirect
- [x] Owner vê os membros com nome, e-mail e Papel, troca o Papel de um membro e o remove com confirmação
- [x] Member vê a lista de membros sem nenhuma ação
- [x] Member sai da Empresa a partir desta aba; Owner não vê a opção de sair
- [x] Owner e Admin veem os Convites pendentes logo abaixo dos membros, convidam por e-mail com Papel definido e cancelam com confirmação
- [x] Owner e Admin copiam o link de um Convite pendente e recebem confirmação de que foi copiado
- [x] Quando a cópia automática não está disponível, o link aparece em texto selecionável, sem polyfill
- [x] O link copiado é o mesmo endereço enviado por e-mail
- [x] O botão de copiar link só aparece para quem pode criar Convite
- [x] Member não vê a seção de Convites, e a chave de Convites está **ausente** das props do Member, não vazia
- [x] Membros e Convites saem da página de Empresa
- [x] O controller da aba carrega apenas as props de Equipe: não carrega dados do negócio, fusos nem dias da semana
- [x] As rotas de membro, Convite e sair da Empresa passam a viver sob o novo endereço, com os nomes correspondentes
- [x] Os redirects após convidar, cancelar Convite, trocar Papel e remover membro apontam para a aba de Equipe
- [x] As rotas públicas de Convite — abrir, aceitar e recusar — continuam com os mesmos endereços e nomes
- [x] Existe cobertura na borda HTTP/Inertia para renderização, props carregadas, isolamento por Papel, ausência de Convites para o Member, o código do Convite chegando às props, e a rota antiga não registrada
- [x] Os casos de Equipe que hoje vivem no teste de Empresa migram, e os testes de membro e de Convite acompanham os novos nomes de rota, sem perder nenhum caso

## Comments

O comportamento do botão de copiar link não tem cobertura automatizada: o projeto não tem plugin de teste de browser, e instalar um seria dependência nova e infraestrutura inteira por causa de um manipulador de clique. A cobertura para em garantir que o código do Convite chega às props; a cópia, a confirmação e o fallback são verificados à mão. Decisão registrada na spec.
