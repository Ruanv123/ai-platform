# 03: Agente consulta a ficha no CRM

**What to build:** O Agente ganha a Ação "consultar ficha no CRM". Quando o Contato ainda não tem Vínculo, a plataforma busca a Pessoa do CRM pelo telefone em E.164, reconhecendo os formatos variados em que o telefone está digitado no CRM; havendo mais de uma, usa a atualizada mais recentemente; encontrando, cria o Vínculo; não encontrando, não cria nada. A Tool é síncrona, com timeout de cerca de 5 segundos, e devolve só os Campos do CRM marcados como visíveis ao Agente, os Negócios abertos (Funil de vendas, Etapa do funil, valor) e o nome do Responsável — nunca Atividades. Estourando o tempo, devolve "ficha indisponível" e o Agente segue. Na tela do CRM, a Empresa escolhe quais Campos do CRM o Agente pode ver. A Ação é ligada por Agente e só aparece com CRM conectado; enquanto o Rascunho do agente não existir, a configuração segue o mesmo mecanismo em que hoje o Agente é ligado aos Calendários agendáveis. No Playground a consulta é real. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] Com CRM conectado e a Ação ligada, o Agente recebe a Tool; sem CRM ou com a Ação desligada, não recebe
- [ ] Sem Vínculo, a busca por telefone encontra a Pessoa do CRM em formatos variados e cria o Vínculo; nenhuma encontrada não cria Pessoa do CRM nem Vínculo
- [ ] Duas Pessoas do CRM com o mesmo telefone: vale a atualizada mais recentemente
- [ ] A ficha devolve só Campos do CRM visíveis, Negócios abertos e o nome do Responsável; nunca notas nem Atividades
- [ ] Timeout ou erro do CRM devolve "ficha indisponível" sem quebrar o turno
- [ ] A tela do CRM permite marcar e desmarcar Campos do CRM visíveis ao Agente; só Owner e Admin alteram
- [ ] A Ação aparece na configuração do Agente só com CRM conectado
- [ ] No Playground a consulta chama o CRM de verdade
- [ ] Testes pela Tool com a API do RD fingida e pelo adapter falso para a regra do Vínculo; fixtures do RD de busca por telefone e leitura de Pessoa do CRM e Negócios na suíte de conformidade
