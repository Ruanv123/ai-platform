# 10: Templates atualizados por webhook

**What to build:** A aprovação, a rejeição, a pausa e a desativação de um Template, e as mudanças de categoria e de qualidade feitas pela Meta, aparecem sozinhas na plataforma, com histórico. O botão de sincronização manual continua existindo.

**Blocked by:** 02, 09

**Status:** resolved

- [x] `message_template_status_update` atualiza status e motivo de rejeição (com a explicação em português já existente) e registra o histórico
- [x] `template_category_update` atualiza a categoria atribuída e registra o histórico
- [x] `message_template_quality_update` atualiza `quality_rating`
- [x] Eventos de um Template desconhecido disparam a sincronização da Conta
- [x] Testes pelo webhook com fixtures reais de cada evento; só na família oficial
