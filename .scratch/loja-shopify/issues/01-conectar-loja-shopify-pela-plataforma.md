# 01: Conectar a Loja Shopify pela plataforma

**What to build:** Na tela de Integrações, um Owner ou Admin clica em "Conectar" no card da Shopify, informa o domínio da loja, autoriza o app da plataforma na Shopify e volta com a Loja da Empresa conectada. O OAuth pede só escopos de leitura (`read_products`, `read_inventory`, `read_customers`, `read_orders`) e usa o token de acesso que expira, com refresh token, guardados criptografados na Integração de e-commerce com provedor `shopify`. A Loja nasce 1:1 com a Integração e guarda domínio, nome, moeda, país, fuso e estado. A página da Loja mostra esse estado e as ações de reconectar e desconectar. Aqui nasce o contrato do adapter por Provedor de e-commerce (ADR 0013), com a Shopify como único adapter. Ver `.scratch/loja-shopify/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Iniciar a conexão redireciona à Shopify com os escopos de leitura e um `state` que amarra a Empresa
- [ ] O callback valida `state` e HMAC da Shopify, troca o código pelo token que expira e grava Integração e Loja com os dados da loja lidos da GraphQL Admin API
- [ ] Callback com `state` inválido, HMAC inválido ou autorização recusada não grava nada e volta com mensagem legível
- [ ] O cliente da Shopify renova o token pelo refresh token quando ele expira, antes de qualquer chamada
- [ ] Reconectar reaproveita a mesma Integração e Loja; nunca existem duas Lojas na Empresa
- [ ] Desconectar faz soft delete da Integração e da Loja e apaga as credenciais
- [ ] Todas as rotas exigem a permissão de gerenciar Integrações
- [ ] O card da Shopify conecta de verdade; os demais Provedores de e-commerce continuam no catálogo sem prometer capacidade
- [ ] Testes com `Http::fake` para OAuth, token e GraphQL
