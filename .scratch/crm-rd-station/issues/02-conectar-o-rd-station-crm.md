# 02: Conectar o RD Station CRM

**What to build:** Na tela de Integrações, um Owner ou Admin abre o CRM, clica em conectar no card do RD Station CRM, autoriza o app da plataforma no RD Station (OAuth2 por código de autorização, API v2) e volta com o CRM da Empresa conectado. A página do CRM mostra o estado (conectado), a conta conectada e a ação de desconectar; os demais Provedores de CRM aparecem como "em breve", sem prometer capacidade. A credencial fica criptografada na Integração do tipo `crm`, no máximo uma por Empresa. O token de acesso vale 2 horas e cada renovação devolve um refresh token novo que invalida o anterior: a renovação acontece sob lock por CRM, relendo a credencial depois do lock, e de forma proativa quando faltam 5 minutos para expirar. Ao conectar, os metadados do CRM — Funis de vendas com as suas Etapas do funil, Campos do CRM com tipo e opções, Responsáveis e Motivos de perda — são carregados e guardados. Aqui nasce o contrato do adapter de Provedor de CRM (ADR 0014), com o RD Station CRM como único adapter real, um adapter falso para os testes do Motor de CRM e a base da suíte de conformidade com fixtures do RD. A desconexão completa fica no ticket 15; aqui desconectar apenas apaga a credencial e o estado local existente. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Iniciar a conexão redireciona ao RD Station com um `state` que amarra a Empresa; o callback valida o `state`, troca o código por access e refresh token e grava o CRM
- [ ] Autorização recusada ou `state` inválido não grava nada e volta com mensagem legível
- [ ] Reconectar reaproveita o mesmo CRM; nunca existem dois CRMs na Empresa
- [ ] Conectar outro Provedor de CRM com um já conectado exige confirmação do aviso de que o atual será desconectado
- [ ] A renovação do token acontece sob lock por CRM e de forma proativa; duas renovações concorrentes nunca reutilizam um refresh token já trocado
- [ ] Os metadados são carregados na conexão e guardados com a data da carga
- [ ] O contrato do adapter declara as operações, as capacidades e o limite de requisições descritos na spec; o Motor de CRM só conversa com o contrato
- [ ] O CRM continua funcionando quando a pessoa que conectou sai da Equipe
- [ ] Só Owner e Admin conectam e desconectam; Member recebe 403
- [ ] Testes com a API do RD fingida e requisições inesperadas proibidas, cobrindo OAuth, renovação concorrente e carga de metadados; fixtures do RD na suíte de conformidade para token e metadados
