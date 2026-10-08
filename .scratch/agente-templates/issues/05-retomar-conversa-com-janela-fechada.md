# 05: Retomar conversa com a janela fechada

**What to build:** Quando o Agente gera uma resposta e a janela de 24 horas fechou, ele envia o Template de retomar conversa configurado em vez de descartar a resposta.

**Blocked by:** 04

**Status:** done

- [x] Janela fechada + Template configurado: envia o Template e marca as Mensagens como respondidas
- [x] Sem Template configurado: comportamento atual, com falha `customer_care_window_closed`
- [x] Falha no envio do Template fica visível no Atendimento
