# 04: Agente consulta Horários disponíveis

**What to build:** O formulário do Agente ganha a seção "Agenda", onde se escolhe em quais Calendários agendáveis ele pode marcar; sem Agenda conectada, a seção fica desabilitada com caminho para Integrações. Com pelo menos um Calendário agendável selecionado, independente do objetivo, o Agente ganha a Tool `consultar_horarios` no WhatsApp e no Playground (real nos dois). O Contato pede horários de um profissional ou de "qualquer um" e recebe Horários disponíveis: janela ∩ (agora + antecedência mínima, fim do intervalo pedido) − ocupação do `freeBusy`, fatiado pela duração, no fuso do Horário de atendimento da Empresa, no máximo cerca de 10, cada um rotulado com o Calendário agendável. A descrição da Tool apresenta os Calendários agendáveis pelo nome e descrição de exibição. Agenda com erro ou falha do Google volta ao Agente como falha legível, nunca como horário inventado. Ver `.scratch/agenda-google/spec.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Seção "Agenda" no formulário do Agente salva a seleção de Calendários agendáveis
- [ ] Seção desabilitada, com link para Integrações, quando não há Agenda conectada
- [ ] Selecionar Calendário agendável de outra Empresa é recusado
- [ ] Agente sem Calendário agendável não recebe a Tool
- [ ] Horários respeitam janela padrão e janela própria, duração, antecedência mínima e ocupação
- [ ] Horários no fuso da Empresa
- [ ] "Qualquer um" devolve horários rotulados por Calendário agendável
- [ ] Agenda com erro ou falha do Google devolve `{ "ok": false, "motivo": ... }`
- [ ] Funciona no WhatsApp e no Playground
- [ ] Testes pela conversa do Agente com `ToolCall`, `Http::fake` para o Google
