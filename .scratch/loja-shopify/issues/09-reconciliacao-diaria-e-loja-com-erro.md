# 09: Reconciliação diária e Loja com erro

**What to build:** A Empresa confia que o espelho não fica errado para sempre e descobre quando algo quebra. Um comando agendado diário faz, para cada Loja, busca incremental por `updated_at` desde o marcador de reconciliação para Produtos, Categorias, Clientes da loja e Pedidos, recria inscrições de webhook ausentes e atualiza a última sincronização. Refresh de token que falha ou acesso revogado deixa a Loja com erro, motivo e ação sugerida (reconectar), e avisa Owners e Admins. Throttle da Shopify reenfileira com backoff sem virar erro. Com a Loja em erro, as Tools continuam respondendo a partir do espelho. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] Alteração que não chegou por webhook é recuperada pela reconciliação
- [ ] Recurso apagado na Shopify sem webhook sai do espelho
- [ ] Inscrição de webhook ausente é recriada
- [ ] A última sincronização é atualizada e o marcador avança
- [ ] Refresh de token que falha deixa a Loja com erro e notifica Owners e Admins uma vez
- [ ] Reconectar limpa o erro
- [ ] Throttle da Shopify reenfileira sem marcar erro
- [ ] Loja com erro continua servindo Buscar produtos e Link de compra
