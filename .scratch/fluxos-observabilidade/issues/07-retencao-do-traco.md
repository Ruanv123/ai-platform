# 07: Retenção do traço

**What to build:** O traço detalhado some depois de 30 dias e os números ficam para sempre. Sem isso a tabela de traços vira a maior do banco em dois meses, e o produto passa a custar mais para guardar do que para rodar.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Traço detalhado podado após 30 dias, por tarefa agendada
- [ ] Agregados por Fluxo, Versão e Nó mantidos indefinidamente
- [ ] Podar não afeta as métricas já calculadas
- [ ] O prazo é configurável, para virar diferença por plano depois
- [ ] A poda roda em lotes, sem travar o banco
