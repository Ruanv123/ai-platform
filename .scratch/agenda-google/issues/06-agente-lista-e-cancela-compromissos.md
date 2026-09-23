# 06: Agente lista e cancela Compromissos

**What to build:** O Contato pergunta o que tem marcado e o Agente responde com a Tool `listar_compromissos`; o Contato pede para cancelar e o Agente usa `cancelar_compromisso`. Remarcar é cancelar e marcar de novo, sem Tool própria. O Google continua sendo a fonte da verdade: ao listar ou cancelar, cada Compromisso futuro é relido no Google, e evento apagado lá vira Compromisso cancelado, evento movido lá tem o horário atualizado. Só Compromissos futuros do próprio Contato podem ser cancelados, sem antecedência mínima. Cancelar apaga o evento no Google e marca o Compromisso como cancelado; se o evento já não existe, só marca. No Playground, cancelar é simulado. Desconectar a Agenda mantém os Compromissos como histórico. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** 05

**Status:** ready-for-agent

- [ ] Listar devolve os Compromissos futuros do Contato com Calendário agendável, dia e hora
- [ ] Evento apagado no Google aparece como cancelado e deixa de ser listado como futuro
- [ ] Evento movido no Google tem o horário do Compromisso atualizado
- [ ] Cancelar apaga o evento no Google e marca o Compromisso como cancelado
- [ ] Cancelar Compromisso cujo evento já sumiu só marca como cancelado
- [ ] Cancelar Compromisso de outro Contato ou passado é recusado
- [ ] No Playground, cancelar não chama o Google nem altera o Compromisso
- [ ] Desconectar a Agenda mantém os Compromissos
- [ ] Testes pela conversa do Agente com `ToolCall`, `Http::fake` para o Google
