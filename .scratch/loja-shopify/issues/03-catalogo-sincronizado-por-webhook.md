# 03: Catálogo sincronizado por webhook

**What to build:** Um produto alterado na Shopify reflete na plataforma em poucos minutos. Ao conectar, a plataforma se inscreve nos tópicos de produto (criado, atualizado, apagado), estoque (`inventory_levels/update`) e collections (criada, atualizada, apagada). O webhook é só um aviso: o job leva (Loja, tipo, id externo, apagado ou não), busca o estado atual na API e faz upsert no canônico, ou apaga do espelho quando o recurso sumiu. Avisos repetidos do mesmo recurso numa janela curta viram uma única busca. O embedding só é refeito quando o texto de origem muda. Ver `.scratch/loja-shopify/spec.md` e ADR 0013.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Conectar registra as inscrições de webhook dos tópicos de catálogo
- [ ] Produto criado ou atualizado é gravado a partir da busca na API, não do payload do webhook
- [ ] Produto apagado sai do espelho; despublicado continua espelhado mas marcado como não publicado
- [ ] Mudança de estoque num local atualiza o estoque somado da Variante do item de estoque
- [ ] Collection alterada atualiza Categoria e seus Produtos
- [ ] Webhooks duplicados e fora de ordem terminam no mesmo estado
- [ ] Vários avisos do mesmo recurso em sequência geram uma só busca na API
- [ ] Alteração só de preço ou estoque não gera embedding novo
