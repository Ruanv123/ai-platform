# 10: Mapeamento de Campos do Contato e Campos do CRM

**What to build:** Na tela do CRM, a Empresa mapeia um Campo do Contato a um Campo do CRM da Pessoa do CRM, escolhendo a direção: da plataforma para o CRM (padrão) ou do CRM para a plataforma. Bidirecional não existe. Só pares de tipo compatível são aceitos, e campo de escolha para campo de escolha exige mapear cada opção do Campo do Contato a uma opção do Campo do CRM. Plataforma → CRM: quando o valor de um Campo do Contato mapeado muda e o Contato já tem Vínculo, uma Operação do CRM atualiza a Pessoa do CRM; sem Vínculo, nada acontece, e quando o Vínculo nasce a primeira escrita leva todos os Campos do Contato mapeados de uma vez. CRM → plataforma: o webhook de Pessoa do CRM atualizada atualiza os Campos do Contato mapeados nessa direção. Valores de Campos do Contato sensíveis aparecem mascarados na Operação. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04, 08

**Status:** ready-for-agent

- [ ] A tela cria, altera e remove pares com direção única; só Owner e Admin
- [ ] Pares de tipo incompatível são recusados; escolha para escolha exige todas as opções mapeadas
- [ ] Mudança de Campo do Contato mapeado para o CRM gera Operação só quando há Vínculo
- [ ] A primeira escrita de um Vínculo recém-criado leva todos os Campos do Contato mapeados para o CRM
- [ ] Webhook de Pessoa do CRM atualizada atualiza os Campos do Contato mapeados do CRM para a plataforma e não gera Operação de volta
- [ ] Um par numa direção nunca provoca escrita na outra
- [ ] Valores sensíveis mascarados na Operação
- [ ] Testes pela tela, pelo salvamento de Campo do Contato e pelo endpoint de webhook, com a API do RD fingida; fixtures do RD de atualizar Campos do CRM na suíte de conformidade
