# 06: Campos do Contato, Tags e Variáveis globais

**What to build:** A Empresa declara o que guarda sobre seus Contatos — convênio, cidade, data da última compra — e o Fluxo grava e lê esses Campos. Tags marcam Contatos sem valor associado. Variáveis globais guardam o que vale para a conta inteira, para não repetir o mesmo endereço de API em dez Nós. O dado coletado hoje continua lá amanhã.

**Blocked by:** 05

**Status:** completed

- [x] A Empresa declara Campos do Contato com nome e tipo: texto, número, data, escolha
- [x] O nó de Definir variável ou tag grava Variável, Campo do Contato e Tag
- [x] Campos e Tags sobrevivem ao fim da Execução; Variáveis não
- [x] O avaliador do 05 lê Campos do Contato e Tags além de Variáveis
- [x] Interpolação de Variável, Campo e Variável global no texto do nó de Mensagem
- [x] Campos, Tags e Variáveis globais são escopados pela Empresa, com teste de isolamento
- [x] Apagar a declaração de um Campo tem comportamento definido e testado
