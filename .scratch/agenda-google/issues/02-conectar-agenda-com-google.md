# 02: Conectar a Agenda com Google

**What to build:** Na tela da Agenda, um Owner ou Admin clica em "Conectar com Google", autoriza na tela de consentimento do Google e volta com a Agenda da Empresa conectada, vendo "Conectada como x@gmail.com". A conexão pertence à Empresa, não a quem autorizou. Dá para reconectar (atualiza a mesma Agenda e limpa o erro) e desconectar. Usa `laravel/socialite` só para o fluxo de autorização, com um OAuth Client único da plataforma, escopos `calendar.events`, `calendar.freebusy` e `calendar.calendarlist.readonly`, `access_type=offline` e `prompt=consent`. Tokens e e-mail ficam nas credenciais criptografadas da Integração `agenda` com provedor `google`. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Iniciar a conexão redireciona ao Google com os escopos, `access_type=offline` e `prompt=consent`
- [ ] O callback grava a Agenda da Empresa com access token, refresh token, expiração e e-mail da conta
- [ ] Callback com `state` inválido não grava nada
- [ ] Autorização recusada no Google volta à tela com mensagem legível
- [ ] Reconectar reaproveita a mesma Agenda e limpa o status de erro; nunca existem duas Agendas na Empresa
- [ ] Desconectar apaga a Agenda (revogação no Google é best-effort)
- [ ] Todas as rotas exigem a permissão de gerenciar Integrações
- [ ] A tela da Agenda mostra estado, conta conectada e ações de conectar, reconectar e desconectar
- [ ] Testes com `Socialite::fake` e `Http::fake`
