# 01: Listagem de Fluxos

**What to build:** "Fluxos" aparece na navegação e a Empresa vê todos os seus numa tabela: nome, tipo, gatilho, Canal, quanto rodaram e como estão. O interruptor liga e desliga sem sair da lista. A tabela continua utilizável com dezenas de Fluxos.

**Blocked by:** `fluxos-motor` 02

**Status:** ready-for-agent

- [ ] Item "Fluxos" na navegação, de primeiro nível
- [ ] Tabela plana, sem agrupamento, com paginação server-side: intervalo, total, itens por página e navegação
- [ ] Coluna de status com interruptor e rótulo: Publicado, Pausado, Rascunho
- [ ] Interruptor desabilitado enquanto o Fluxo nunca foi publicado
- [ ] Selo de "alterações não publicadas" derivado da comparação com a Versão publicada
- [ ] Colunas de tipo, gatilho, Canal, execuções em 7 dias, conclusão e última execução
- [ ] Filtros de Canal, Gatilho e Status, combináveis
- [ ] Member vê a lista e não consegue publicar nem pausar
