# 15: Desconexão completa e troca de Provedor de CRM

**What to build:** Desconectar o CRM, pela plataforma ou ao trocar de Provedor de CRM, remove as inscrições de webhook no RD Station CRM, apaga token, Vínculos, metadados, Negócios vinculados, mapeamentos de Campos e configuração do CRM, desliga as Ações do CRM em todos os Agentes e encerra as Pausas da IA de origem CRM. Nada é apagado no CRM da Empresa, nunca. Operações do CRM pendentes deixam de ser enviadas. Conectar outro Provedor de CRM com um já conectado mostra o aviso do que será apagado e só segue com confirmação. Apagar um Contato na plataforma apaga o Vínculo e os Negócios vinculados dele, nunca a Pessoa do CRM. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 08, 09, 10, 11, 12

**Status:** ready-for-agent

- [ ] Desconectar remove as inscrições de webhook no RD
- [ ] Desconectar apaga token, Vínculos, metadados, Negócios vinculados, mapeamentos e configuração do CRM da Empresa
- [ ] As Ações do CRM somem de todos os Agentes da Empresa
- [ ] Pausas da IA de origem CRM são encerradas; pausas manuais permanecem
- [ ] Operações pendentes não são enviadas depois da desconexão
- [ ] Nenhuma requisição de remoção de registro é feita ao CRM
- [ ] Troca de Provedor de CRM exige confirmação do aviso
- [ ] Apagar um Contato apaga o Vínculo e os Negócios vinculados dele sem chamar o CRM
- [ ] Só Owner e Admin desconectam
- [ ] Testes com a API do RD fingida verificando exatamente quais requisições saem
