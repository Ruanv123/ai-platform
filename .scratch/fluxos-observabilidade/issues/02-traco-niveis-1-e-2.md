# 02: Traço níveis 1 e 2 — passagem por nó e variáveis

**What to build:** Depois que uma Execução roda, dá para ver o que ela fez. Cada Nó registra que foi alcançado, qual aresta seguiu, quanto demorou e se deu erro, e cada passo guarda o retrato das Variáveis daquele momento. Uma tela de detalhe mostra isso em ordem, com o Nó que quebrou destacado.

**Blocked by:** `fluxos-motor` 03

**Status:** ready-for-agent

- [ ] Cada passo registra Nó, aresta seguida, duração, resultado e erro
- [ ] Cada passo guarda o retrato das Variáveis
- [ ] Tela de detalhe da Execução mostra os passos em ordem
- [ ] O Nó onde a Execução falhou ou parou aparece destacado
- [ ] Cabeçalho com Contato, Canal, Versão, duração e estado final com motivo
- [ ] Nó não alcançado aparece como tal, em vez de sumir
- [ ] Traço é escopado pela Empresa, com teste de isolamento
