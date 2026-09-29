# 01: Conta do WhatsApp dona dos Números (prefactor)

**What to build:** Reorganizar a relação entre Conta do WhatsApp e Número do WhatsApp para refletir a Meta: a Conta (WABA) pertence à Empresa e é dona dos Números, e não o contrário. Nada muda para quem usa: a tela do WhatsApp, o webhook atual, o envio e os Templates continuam funcionando como hoje. Ver spec `../spec.md`, seção "Esquema", e o glossário (Conta do WhatsApp, App da Meta, Forma de conexão).

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] `whatsapp_phone_numbers` aponta para `whatsapp_accounts`; a coluna `whatsapp_accounts.whatsapp_phone_number_id` deixa de existir
- [x] `whatsapp_accounts` ganha `team_id`, `business_id`, `name`, `app_id`, `subscribed_at`, `ban_state` e `review_status`; `waba_id` passa a ser único
- [x] `method` é substituída por `connection_method` com os casos `tech_provider` e `own_app`; as contas `manual` viram `own_app` (só há dados de desenvolvimento, a migração pode descartar o que não couber)
- [x] `verify_token` passa a ser único e nulo fora do App próprio
- [x] `whatsapp_phone_numbers` ganha `name_status`, `messaging_limit_tier`, `registration_pin` (criptografado) e `registered_at`
- [x] Models, relações, factories e todos os pontos que resolviam a Conta a partir do Número (controller do Canal, webhook, adapter de saída, serviço e sincronizador de Templates, conformidade) usam a nova relação
- [x] `unique(team_id, type)` em `channels` continua valendo
- [x] A suíte atual de WhatsApp, Templates e conversa continua verde sem mudança de comportamento
