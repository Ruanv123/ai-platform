# 05: Reação

**What to build:** Quando o Contato reage a uma mensagem, a Equipe vê o emoji sobre a bolha reagida. A Reação não vira Mensagem nova e não aciona o Agente. Ver glossário (Reação).

**Blocked by:** 02

**Status:** resolved

- [x] `messages` ganha `reaction_emoji` e `reacted_at`
- [x] A Reação é gravada na Mensagem reagida, encontrada pelo `provider_message_id` no mesmo Canal
- [x] Reagir de novo troca o emoji; emoji vazio remove a Reação
- [x] Reação a uma Mensagem desconhecida é descartada
- [x] Nenhuma Mensagem é criada e o Agente não é acionado
- [x] A conversa mostra o emoji sobre a bolha e atualiza em tempo real como as demais mudanças da conversa
- [x] Conformidade nas duas famílias (criar, trocar, remover)
