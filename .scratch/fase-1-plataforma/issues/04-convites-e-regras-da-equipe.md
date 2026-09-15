# 04: Convites e regras da Equipe

**What to build:** A Equipe funciona com a regra de uma Empresa por usuário. Owner e Admin convidam por e-mail; o link do convite leva a um cadastro já vinculado à Empresa com o papel do convite, sem passar pelo onboarding (usuário logado sem Empresa também pode aceitar). Convidar e-mail que já pertence a outra Empresa é bloqueado. Owner não pode sair; Member pode. A exclusão da Empresa fica desabilitada. Os convites pendentes deixam de aparecer no Dashboard. Referência: spec, user stories 12–20.

**Blocked by:** 03 (Onboarding da Empresa)

**Status:** ready-for-agent

- [ ] Owner e Admin convidam definindo papel Admin ou Member; e-mail de convite enviado (visível no Mailpit)
- [ ] Link do convite abre cadastro vinculado; ao concluir, o usuário é membro da Empresa com o papel do convite e cai no Dashboard sem onboarding
- [ ] Usuário logado sem Empresa consegue aceitar um convite
- [ ] Convite para e-mail que já pertence a outra Empresa é recusado na validação
- [ ] Owner e Admin listam e cancelam convites pendentes; Owner altera papéis e remove membros
- [ ] Owner não consegue sair da Empresa; Member consegue
- [ ] Exclusão da Empresa indisponível (rota e UI)
- [ ] Convites pendentes removidos do Dashboard e das props
- [ ] Feature tests para cada regra acima
