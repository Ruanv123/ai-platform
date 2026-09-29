# 05: Tela de Operações do CRM

**What to build:** Na página do CRM, um Owner ou Admin vê cada escrita que a plataforma pediu ao CRM — origem (automática, Ação do Agente ou reenvio), tipo, Contato, situação pendente, feita ou com falha, tentativas e o motivo legível da falha. Pode filtrar só as falhas e tentar de novo uma Operação com falha, o que cria uma nova tentativa com origem reenvio. O resumo do que foi enviado aparece com os Campos do Contato sensíveis mascarados. Uma limpeza diária apaga Operações com mais de 30 dias. É o antídoto para o "anotei" do Agente virar promessa silenciosa. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] A lista mostra as Operações do CRM da Empresa, mais recentes primeiro, com origem, tipo, Contato, situação, tentativas e motivo
- [ ] O filtro mostra só as Operações com falha
- [ ] "Tentar de novo" numa Operação com falha a reenvia com origem reenvio e atualiza a situação
- [ ] Valores de Campos do Contato sensíveis aparecem mascarados
- [ ] Operações com mais de 30 dias são apagadas pela limpeza diária agendada
- [ ] Só Owner e Admin veem a lista e reenviam; Member recebe 403
- [ ] Operações de uma Empresa nunca aparecem em outra
- [ ] Testes pelos controllers da página do CRM e pelo comando agendado
