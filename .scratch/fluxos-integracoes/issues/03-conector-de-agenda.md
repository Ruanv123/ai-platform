# 03: Conector de Agenda

**What to build:** O primeiro conector curado, e o que fecha o ciclo sozinho: o Contato marca o horário na conversa e o compromisso aparece na agenda de verdade. Dois Nós tipados — consultar disponibilidade e criar evento — em vez de a Empresa montar JSON na mão.

**Blocked by:** 02

**Status:** wontfix

- [ ] Conectar uma agenda pela tela de Integrações
- [ ] Nó de consultar disponibilidade devolve horários em Variáveis
- [ ] Nó de criar evento marca o compromisso e devolve o identificador
- [ ] Os dois compilam para o mesmo motor de execução, retry e tratamento de erro do Nó genérico
- [ ] Fuso horário respeita o configurado no Horário de atendimento da Empresa
- [ ] Erro da agenda segue a saída de erro do Nó, com motivo legível
- [ ] CRM fica para depois: este é o conector que fecha o ciclo sem depender de alguém olhar um funil

## Comments

- 2026-09-22: Substituída por `.scratch/agenda-google/spec.md`. Agendamento passa a viver só no Agente, via Tool, e a Agenda passa a ser só Google; os Nós de agenda do Fluxo serão removidos.
