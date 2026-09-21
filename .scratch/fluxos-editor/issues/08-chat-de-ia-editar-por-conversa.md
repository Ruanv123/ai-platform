# 08: Chat de IA — editar por conversa

**What to build:** Com o fluxo já desenhado, a Empresa pede ajustes conversando: "adicione uma pergunta de CPF antes do agendamento". A IA lê o desenho atual e propõe a alteração em cima dele, em vez de recomeçar do zero.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] A IA recebe o DSL atual como contexto
- [ ] O pedido vira alteração incremental, preservando o que não foi mencionado
- [ ] As alterações continuam passando por validação e pela lista de aplicar ou descartar
- [ ] Apagar e mover Nós existentes é possível e fica explícito na lista
- [ ] Conversa mantém o histórico dentro da sessão de edição
