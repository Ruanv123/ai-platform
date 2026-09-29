# 05: Agente manda Link de compra

**What to build:** Depois de recomendar, o Agente manda ao Contato um Link de compra que abre o checkout da Shopify com as Variantes e quantidades escolhidas. O Rascunho do agente ganha o interruptor "Mandar Link de compra", com as mesmas regras de visibilidade e padrão do "Buscar produtos". A Tool recebe pares (Variante, quantidade), recusa Variante indisponível ou de Produto não visível com falha legível, e monta o permalink de carrinho com UTMs fixas (`utm_source` da plataforma, `utm_medium=whatsapp`, `utm_campaign=agente-ia`) e um token de Atribuição opaco nos atributos do carrinho. O token é um registro próprio que liga token a Contato, Agente, Canal e momento. No Playground o link sai sem token. Nada é criado na Shopify. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Interruptor no Rascunho do agente, visível só com Loja, ligado por padrão para Objetivo Vendas
- [ ] O link abre o checkout com várias Variantes e quantidades
- [ ] UTMs fixas presentes; nenhum id interno legível no link
- [ ] Um token de Atribuição é gravado por link gerado nos Canais
- [ ] Variante indisponível ou de Produto não visível é recusada com mensagem legível para o Agente
- [ ] No Playground o link é gerado sem token e nenhum token é gravado
- [ ] Nenhuma requisição de escrita chega à Shopify
