# 11: Registrar Contatos atendidos

**What to build:** Na tela do CRM, a opção "Registrar Contatos atendidos", desligada por padrão, com o mínimo de mensagens recebidas do Contato configurável (padrão 2). Ligada, depois de uma resposta do Agente num Canal, se o Contato ainda não tem Vínculo e já mandou o mínimo de mensagens, é enfileirada uma Operação do CRM que garante a Pessoa do CRM — achando pelo telefone ou criando — e o Vínculo. Não vale para o Playground nem para o WhatsApp de testes. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] A opção e o mínimo de mensagens são configuráveis na tela do CRM; só Owner e Admin
- [ ] Desligada, nenhuma Pessoa do CRM é criada sem Ação do Agente
- [ ] Ligada, depois da resposta do Agente a um Contato sem Vínculo que atingiu o mínimo, uma Operação garante a Pessoa do CRM e o Vínculo
- [ ] Contato abaixo do mínimo, ou que já tem Vínculo, não gera Operação
- [ ] Playground e WhatsApp de testes nunca geram Operação por essa opção
- [ ] Testes pelo fluxo de resposta do Agente no WhatsApp, com a API do RD fingida
