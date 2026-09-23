# 05: Agente marca Compromisso

**What to build:** Depois que o Contato confirma dia, hora e profissional, o Agente usa a Tool `marcar_compromisso` e o compromisso aparece no Calendário do Google. Nasce o Compromisso, registrado na plataforma e ligado ao Calendário agendável e ao Contato. Antes de inserir, sob um lock por Calendário, a ocupação é consultada de novo; se o horário foi ocupado, a Tool devolve falha com alternativas. Um Contato tem no máximo um Compromisso futuro por Calendário agendável. O evento sai com título `{nome do Contato} (via WhatsApp)`, descrição com telefone e motivo, sem convidados, e o id do Contato em `extendedProperties.private`. O Contato vem da conversa, nunca de argumento do modelo. A descrição da Tool instrui a só marcar após confirmação explícita. No Playground, marcar é simulado: sucesso com id fictício, sem chamar o Google e sem gravar Compromisso. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Marcar cria o evento no Google e grava o Compromisso
- [ ] Evento com título, descrição, sem convidados e id do Contato nas propriedades privadas
- [ ] Horário ocupado entre a consulta e a marcação devolve falha com alternativas e não cria nada
- [ ] Segundo Compromisso futuro no mesmo Calendário agendável é recusado
- [ ] Horário fora da janela ou antes da antecedência mínima é recusado
- [ ] No Playground, nenhuma requisição de escrita ao Google e nenhum Compromisso gravado
- [ ] Agenda com erro devolve falha legível
- [ ] Testes pela conversa do Agente com `ToolCall`, `Http::fake` para o Google
