# 14: Webhook do WhatsApp

**What to build:** Rota pública única de webhook para a Meta, fora de autenticação e CSRF. GET faz o handshake: responde o `hub.challenge` quando `hub.mode` é `subscribe` e o `hub.verify_token` confere com algum número cadastrado, gravando a data de verificação (visível na tela do WhatsApp); caso contrário 403. POST valida a assinatura `X-Hub-Signature-256` (HMAC-SHA256 do corpo cru com o App Secret do número identificado pelo Phone Number ID do payload): válida responde 200 e descarta o evento; inválida ou número desconhecido responde 403. Referência: spec, user stories 87, 95–96 e seção Canais: WhatsApp.

**Blocked by:** 13 (Canais: configuração do WhatsApp)

**Status:** ready-for-agent

- [ ] Rota GET e POST pública, sem CSRF e sem autenticação
- [ ] Handshake válido devolve o challenge em texto puro e grava a data de verificação no número
- [ ] Verify token desconhecido ou modo diferente de `subscribe` responde 403
- [ ] POST com assinatura válida responde 200 sem processar o evento
- [ ] POST com assinatura inválida, ausente ou Phone Number ID desconhecido responde 403
- [ ] Tela do WhatsApp mostra se o webhook já foi verificado
- [ ] Feature tests para handshake válido/inválido e assinatura válida/inválida/ausente
