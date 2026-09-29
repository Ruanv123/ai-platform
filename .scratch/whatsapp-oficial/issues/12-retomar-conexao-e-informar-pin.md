# 12: Retomar a conexão e informar o PIN

**What to build:** Quando a conexão pelo Tech Provider falha no meio, a Empresa clica em "tentar de novo" e a plataforma continua do passo que falhou, sem repetir o que já foi feito na conta da Meta. Se o número já tiver PIN de confirmação em duas etapas, a tela pede o PIN da Empresa.

**Blocked by:** 11

**Status:** resolved

- [x] "Tentar de novo" retoma a partir do primeiro passo não concluído (`subscribed_at`, `registered_at`, dados do número, Templates)
- [x] Passos já concluídos não são chamados de novo na Graph API
- [x] Quando o registro falha porque o número já tem PIN, a tela pede o PIN, que é guardado criptografado e usado na nova tentativa
- [x] PIN errado mostra mensagem em português e permite tentar de novo
- [x] Testes pelas rotas com `Http::fake` para falha em cada passo e retomada
