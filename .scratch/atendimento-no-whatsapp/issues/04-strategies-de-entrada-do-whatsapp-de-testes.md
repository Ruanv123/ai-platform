# 04: Strategies de entrada do WhatsApp de testes

**What to build:** A rota e a família de strategies do Evolution Go, que entrega o envelope `{event, instance, data}` com `MESSAGE`, `SEND_MESSAGE`, `READ_RECEIPT`, `CONNECTION` e `QRCODE` — nomes e formato **diferentes** dos da Meta, e que não devem ser reconciliados num evento canônico (ADR 0004). Rota própria, registrada em todos os ambientes e **sem autenticação**, porque o serviço não assina o payload: risco aceito e registrado no ADR 0002. O Canal é identificado pelo nome da instância. Mesmo núcleo de eventos e mesma strategy de não suportado do issue 03. Referência: spec, user stories 37, 38, 41.

**Blocked by:** 01, 02

**Status:** done

- [x] Rota própria de webhook para o Canal de testes, fora de autenticação e CSRF
- [x] Canal resolvido pelo nome da instância do payload; instância desconhecida é recusada
- [x] Strategies do núcleo lendo o envelope `{event, instance, data}`
- [x] `READ_RECEIPT` atualiza o status da Mensagem de saída correspondente
- [x] Evento fora do núcleo registra Mensagem com o tipo bruto
- [x] Idempotência e ordenação com as mesmas regras do issue 03
- [x] Nenhuma tradução para um formato comum com o WhatsApp oficial
- [x] Testes pelo seam da rota, com fixtures em `tests/Fixtures/whatsapp/testes/`

## Comments

- Implementado no commit `c8542f2` (Implement WhatsApp test webhook strategies).
