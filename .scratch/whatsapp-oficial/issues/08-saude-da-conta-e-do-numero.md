# 08: Saúde da Conta e do Número

**What to build:** Quando a Meta bloqueia ou restringe a Conta do WhatsApp, quando a Empresa remove a plataforma como parceira ou quando o token deixa de valer, o Canal aparece com erro e mensagem em português. Mudanças de qualidade, limite de mensagens e nome exibido aparecem na tela do Canal. Notificações ficam fora (só o estado é gravado).

**Blocked by:** 02

**Status:** resolved

- [x] `account_update` com ban, restrição ou `PARTNER_REMOVED` grava `ban_state`/`review_status` e leva o Canal a `Error` com `last_error` traduzido
- [x] `account_review_update` grava `review_status`
- [x] `phone_number_quality_update` atualiza `quality_rating` e `messaging_limit_tier`
- [x] `phone_number_name_update` atualiza `verified_name` e `name_status`
- [x] Resposta 190 da Graph API em qualquer chamada (envio, teste, Templates) leva o Canal a `Error`
- [x] A tela do WhatsApp mostra qualidade, limite de mensagens e estado do nome
- [x] Testes pelo webhook com fixtures reais de cada evento; só na família oficial
