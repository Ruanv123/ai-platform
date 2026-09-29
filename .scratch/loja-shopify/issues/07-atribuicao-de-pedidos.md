# 07: Atribuição de Pedidos

**What to build:** Quando o Contato compra pelo Link de compra, o Pedido espelhado chega com o token de Atribuição nos atributos e ganha a Atribuição ao Contato, ao Agente e ao Canal que geraram o link. Só conta a venda direta: Pedido sem token não é atribuído, e token desconhecido é ignorado. A ficha do Contato marca os Pedidos atribuídos. O painel de vendas do Agente fica fora. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 05, 06

**Status:** ready-for-agent

- [ ] Pedido com token válido grava a Atribuição com Contato, Agente e Canal
- [ ] Pedido sem token ou com token desconhecido fica sem Atribuição
- [ ] Reprocessar o mesmo Pedido não duplica nem altera a Atribuição
- [ ] Pedido feito com link do Playground não tem Atribuição
- [ ] A ficha do Contato distingue os Pedidos atribuídos
