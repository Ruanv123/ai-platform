# 01: Corrigir a paleta de gráficos do projeto

**What to build:** Nada muda na tela ainda, mas os tokens de gráfico do projeto passam a ser legíveis. Hoje `--chart-4` e `--chart-5` são praticamente a mesma cor — indistinguíveis mesmo com visão de cores completa — e as duas ficam abaixo de 3:1 de contraste contra branco; `--chart-3` lê como cinza. É prefactor: corrigir antes de existir qualquer gráfico, senão todos nascem tortos.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Os cinco tokens passam na validação de faixa de luminosidade, piso de croma e separação para daltonismo
- [ ] Separação entre pares adjacentes acima do piso a olho normal
- [ ] Todos alcançam pelo menos 3:1 contra a superfície do gráfico
- [ ] Os valores do modo escuro são escolhidos e validados contra a superfície escura, não derivados por inversão
- [ ] A validação é reproduzível, não avaliada a olho
