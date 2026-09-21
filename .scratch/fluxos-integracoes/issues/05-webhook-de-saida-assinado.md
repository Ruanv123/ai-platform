# 05: Webhook de saída assinado

**What to build:** Quando um Evento acontece, o sistema da Empresa fica sabendo. A plataforma chama um endereço que ela cadastrou, assinando a chamada para que o outro lado confirme que veio daqui, e insiste quando a chamada falha por instabilidade.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Cadastrar endereço de destino por Evento
- [ ] Entrega at-least-once, com assinatura HMAC em cabeçalho
- [ ] Segredo de assinatura gerado pela plataforma e mostrado uma vez
- [ ] Retry com backoff exponencial para falha temporária
- [ ] O corpo segue o formato declarado do Evento
- [ ] Entrega não bloqueia a Execução
