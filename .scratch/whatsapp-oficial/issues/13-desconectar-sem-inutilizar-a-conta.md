# 13: Desconectar sem inutilizar a conta

**What to build:** A Empresa desconecta o WhatsApp da plataforma e o número continua funcionando na Meta, pronto para ser usado em outro lugar. Contatos e Mensagens continuam na plataforma. Ver ADR 0012.

**Blocked by:** 11

**Status:** resolved

- [x] Tech Provider: chama `DELETE /{waba}/subscribed_apps` e apaga token e PIN
- [x] O número **nunca** é desregistrado e nenhuma outra configuração da conta da Meta é alterada
- [x] App próprio: apaga as credenciais sem chamar a Meta
- [x] O Canal, a Conta e o Número são removidos; Contatos e Mensagens permanecem
- [x] Falha na chamada à Meta não impede a desconexão local e é informada em português
- [x] Testes pela rota com `Http::fake`, conferindo que só `subscribed_apps` é chamado
