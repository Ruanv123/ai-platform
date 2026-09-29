# 10: Desinstalação, desconexão completa e LGPD

**What to build:** Desconectar pela plataforma ou desinstalar o app na Shopify tem o mesmo efeito, e os pedidos de privacidade da Shopify são cumpridos. Desconectar pela plataforma desinstala o app na loja via API; o webhook `app/uninstalled` dispara o mesmo desconectar. Os dois fazem hard delete de Produtos, Variantes, Categorias, Clientes da loja, Pedidos, Itens do pedido e tokens de Atribuição, soft delete da Integração e da Loja, e desligam os interruptores da Loja nos Agentes; Contatos, Mensagens e Memórias do agente ficam. Webhooks de compliance: `customers/redact` apaga o Cliente da loja, os Pedidos dele e a ligação com o Contato; `customers/data_request` gera um arquivo com o que a plataforma tem daquele Cliente da loja (cadastro, Pedidos, Atribuições) e o envia ao Owner da Empresa por e-mail; `shop/redact` garante que nada da Loja sobrou, de forma idempotente. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] Desconectar pela plataforma chama a desinstalação na Shopify e apaga o espelho inteiro
- [ ] `app/uninstalled` produz exatamente o mesmo resultado
- [ ] Contatos, Mensagens e Memórias do agente continuam depois de desconectar
- [ ] Interruptores da Loja ficam desligados nos Agentes
- [ ] `customers/redact` apaga só o Cliente da loja, os Pedidos dele e a ligação; o Contato fica
- [ ] `customers/data_request` envia ao Owner um arquivo com os dados daquele Cliente da loja
- [ ] `shop/redact` não falha quando o espelho já foi apagado
- [ ] Os três webhooks de compliance respondem 401 com HMAC inválido
