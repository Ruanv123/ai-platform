# 03: Webhook do Tech Provider

**What to build:** O App da Meta da plataforma recebe em `/webhooks/whatsapp` os eventos de todas as Contas conectadas pelo Tech Provider. O handshake e a assinatura usam o verify token e o secret da plataforma, configurados por ambiente, e o processamento é o mesmo do ticket 02. Ver ADR 0012.

**Blocked by:** 02

**Status:** resolved

- [x] Configuração do App da Meta da plataforma por ambiente: `app_id`, `app_secret`, `config_id` do cadastro incorporado e verify token do webhook, ao lado da versão da Graph API já existente
- [x] GET conclui o handshake só com o verify token da plataforma
- [x] POST valida a assinatura só com o secret da plataforma; assinatura inválida recebe 403
- [x] Eventos de Contas `tech_provider` são processados; eventos de WABA desconhecida ou de Conta `own_app` recebem 200 e são descartados
- [x] Um POST assinado com o secret de uma Conta de App próprio é recusado nesta rota, e um assinado com o secret da plataforma é recusado na rota de App próprio
- [x] Testes pelas rotas HTTP cobrindo as duas rotas lado a lado
