# 06: Fila de falhas de webhook visível

**What to build:** A entrega que falhou de vez aparece numa tela, com o motivo e a opção de tentar de novo. Este ticket existe por um motivo específico: automação que falha em silêncio é a queixa mais citada contra os concorrentes, e a diferença entre sofrer dela e não sofrer é exatamente esta tela.

**Blocked by:** 05

**Status:** ready-for-agent

- [ ] Entregas esgotadas ficam numa fila de falhas visível na UI
- [ ] Cada item mostra Evento, destino, código de resposta, motivo e quando ocorreu
- [ ] Reenviar manualmente, item a item
- [ ] Contador de falhas aparece onde a Empresa já olha, não só numa tela escondida
- [ ] A fila respeita a retenção e não cresce indefinidamente
