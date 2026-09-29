# 04: Agente busca produtos

**What to build:** O Contato descreve o que procura ("tênis de corrida leve até 400 reais") e o Agente responde com poucos produtos, preço e disponibilidade. O Rascunho do agente ganha o interruptor "Buscar produtos", visível só quando a Empresa tem Loja e ligado por padrão em Agentes com Objetivo Vendas; ele vale nos Canais ao publicar a Versão do agente. A Tool recebe a descrição e filtros opcionais (preço mínimo e máximo, Categoria, opção, só disponíveis), faz busca híbrida (similaridade de embedding mais filtros SQL) só sobre Produtos ativos e publicados na loja online, no nível da Variante, e devolve título, opções, preço, preço comparativo, disponibilidade e URL do Produto. Nesta fatia não há triagem: o resultado é cortado nos primeiros colocados. No Playground a busca é real. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] O interruptor aparece no Agente só com Loja conectada e faz parte do Rascunho e da Versão do agente
- [ ] Agente com Objetivo Vendas nasce com o interruptor ligado quando há Loja
- [ ] A Tool só é entregue ao modelo com interruptor ligado e Loja conectada (sincronizada ou com erro)
- [ ] Produtos em rascunho, arquivados ou não publicados nunca voltam
- [ ] Variante esgotada só volta se vende sem estoque, e vem marcada como tal
- [ ] Filtros de preço, Categoria e opção são respeitados
- [ ] Busca funciona no Playground com o catálogo real
- [ ] Testes pela conversa do Agente com `ToolCall`, `Embeddings::fake` e `PlaygroundAgent::fake`/`WhatsAppAgent::fake`
