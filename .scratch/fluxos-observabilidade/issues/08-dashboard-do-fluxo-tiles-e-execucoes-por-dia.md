# 08: Dashboard do Fluxo — tiles e execuções por dia

**What to build:** A Empresa abre o Fluxo e vê, de relance, se o atendimento está saudável: quantas Execuções começaram, quantas terminaram, quantas foram abandonadas, quantas falharam, quantas estão presas e quanto custou de IA. Mais um gráfico de execuções por dia, com o dia detalhado ao passar o mouse.

**Blocked by:** 03, 01

**Status:** ready-for-agent

- [ ] Seis tiles: iniciadas, concluídas, abandonadas, com falha, presas e custo de IA
- [ ] Gráfico de execuções por dia, com tooltip por dia
- [ ] Seletor de período de 24 horas, 7 dias e 30 dias
- [ ] Série única não leva legenda; o título nomeia
- [ ] **Sem eixo duplo**: execuções e falhas diferem em uma ordem de grandeza e não vão no mesmo eixo
- [ ] Alternativa em tabela disponível para o gráfico
- [ ] Atualização por polling, não WebSocket
