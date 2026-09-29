# 06: Clientes da loja e Pedidos espelhados

**What to build:** A Equipe abre um Contato no atendimento e vê o Cliente da loja ligado a ele: nome, e-mail, total gasto e últimos Pedidos. A importação inicial passa a trazer Clientes da loja e Pedidos dos últimos 60 dias, com Itens do pedido; a plataforma se inscreve nos webhooks de clientes e pedidos (criado, atualizado, apagado), tratados como aviso e busca, igual ao catálogo. O telefone é normalizado para E.164 usando o país da Loja como padrão; como prefactor, a normalização de telefone hoje no namespace do WhatsApp é extraída para uso comum. A ligação Cliente da loja ↔ Contato (no máximo um de cada lado) acontece ao espelhar o Cliente da loja e ao criar um Contato, e é refeita quando o telefone muda. Cliente da loja sem telefone é espelhado sem ligação. Nada é copiado para o Contato. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Normalização E.164 extraída e reaproveitada pelo WhatsApp e pela Loja, sem mudar o comportamento atual
- [ ] Importação inicial grava Clientes da loja, Pedidos dos últimos 60 dias e Itens do pedido
- [ ] Webhooks de clientes e pedidos fazem upsert a partir da API; apagados saem do espelho
- [ ] Telefone em qualquer formato é ligado ao Contato com o mesmo E.164
- [ ] Contato criado depois é ligado ao Cliente da loja existente
- [ ] Mudança de telefone na Shopify refaz a ligação
- [ ] Cliente da loja sem telefone é espelhado sem ligação
- [ ] O nome do Contato nunca é alterado pela Loja
- [ ] A ficha do Contato no atendimento mostra o Cliente da loja e seus últimos Pedidos
- [ ] Fixtures reais de cliente e pedido entram na suíte de conformidade
