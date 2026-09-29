# 08: Triagem de produtos por subagente

**What to build:** O Contato recebe de 3 a 5 sugestões com mais chance de compra para ele, e não os primeiros resultados da busca. Quando a busca híbrida traz mais candidatos (cerca de 30) do que o limite de oferta, um subagente com modelo barato os reordena usando a conversa recente, os Pedidos do Cliente da loja ligado ao Contato e a Memória do agente sobre o Contato, e devolve as escolhas com um motivo curto cada. Abaixo do limite não há triagem. O custo aparece no Traço. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 04, 06

**Status:** ready-for-agent

- [ ] Acima do limite, a Tool devolve só as Variantes escolhidas pela triagem, na ordem dela, com motivo
- [ ] Abaixo do limite, o subagente não é chamado
- [ ] O subagente recebe a conversa, os Pedidos do Cliente da loja e a Memória do agente do Contato
- [ ] Contato sem Cliente da loja é triado só com conversa e Memória
- [ ] Falha do subagente cai no resultado da busca sem triagem, sem quebrar a resposta
- [ ] O custo da triagem aparece no Traço
- [ ] Testes com o subagente faked
