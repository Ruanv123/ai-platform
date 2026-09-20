# 03: Strategies de entrada do WhatsApp oficial

**What to build:** O webhook oficial para de descartar o evento e passa a interpretá-lo. Uma família de strategies por tipo de evento — texto, imagem, áudio, vídeo, documento e status de entrega — unidas por uma interface por tipo, lendo `entry.changes.value` com `messages` e `statuses`. Todo evento fora do núcleo cai numa strategy de não suportado que **registra** a Mensagem com o tipo bruto. Idempotência por `ShouldBeUnique` no fluxo de ingestão, chaveado pelo id da mensagem no provedor, janela de 24 a 48h. Ordenação pelo timestamp do provedor. O webhook responde imediatamente e o trabalho vai para a fila. A mídia ainda não é resolvida aqui — issue 06. Referência: ADR 0004; spec, user stories 1–8, 15, 17–20, 30, 31, 35.

**Blocked by:** 01

**Status:** done

- [x] Handshake e validação de assinatura seguem funcionando como hoje
- [x] Evento válido responde ao provedor imediatamente e enfileira o processamento
- [x] Strategies de texto, imagem, áudio, vídeo, documento e status, com interface por tipo de evento
- [x] Evento de tipo desconhecido registra Mensagem com o tipo bruto em vez de descartar
- [x] Status de entrega atualiza a Mensagem de saída existente, sem criar linha nova
- [x] Reentrega do mesmo id do provedor não processa duas vezes (`ShouldBeUnique`)
- [x] Mensagens são ordenadas pelo timestamp do provedor
- [x] Contato criado na primeira mensagem, reusado nas seguintes
- [x] Payload com número desconhecido ou assinatura inválida continua recusado
- [x] Testes pelo seam da rota, com fixtures em `tests/Fixtures/whatsapp/oficial/`

## Comments

- Implementado no commit `07f9764` (Add WhatsApp webhook conformance suite), junto com o issue 05.
