# 10: "Continuar sem esperar" e aviso de proposta no dashboard

**What to build:** Na tela de progresso do onboarding, "continuar sem esperar" conclui o onboarding e leva ao dashboard, enquanto a Leitura do site segue em segundo plano. Com uma proposta pendente, o dashboard mostra um aviso que leva à conferência:
- se a proposta veio do onboarding, é o formulário pré-preenchido com a origem por campo (o mesmo do passo 3);
- se veio de uma nova Leitura, é a conferência campo a campo de Configurações.

O aviso some quando a proposta é confirmada, descartada ou substituída. Se a Leitura falhar depois de o cliente sair, o dashboard mostra o motivo e o caminho para preencher os Dados da Empresa à mão. Ver `.scratch/leitura-do-site/spec.md`.

**Blocked by:** 09

**Status:** ready-for-agent

- [ ] "Continuar sem esperar" conclui o onboarding e leva ao dashboard com a Leitura ainda em andamento
- [ ] Proposta pendente mostra o aviso no dashboard, e ele leva à conferência certa pela origem da proposta
- [ ] O aviso some quando a proposta é confirmada, descartada ou substituída
- [ ] Leitura com falha depois de sair mostra o motivo e o caminho para o preenchimento manual
