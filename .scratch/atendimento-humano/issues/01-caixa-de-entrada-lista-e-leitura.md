# 01: Caixa de entrada — lista de pendentes e leitura da conversa

**What to build:** A Equipe finalmente tem para onde olhar quando um Fluxo desiste. "Atendimento" aparece no menu com o contador de pendentes, a lista mostra as conversas que um Fluxo transferiu — com o motivo pelo qual vieram parar ali — e clicar abre a conversa inteira para ler. Ainda não dá para responder; dá para saber que alguém está esperando.

**Blocked by:** `fluxos-motor` 09

**Status:** ready-for-agent

- [ ] Item "Atendimento" na navegação, com contador de conversas aguardando
- [ ] Lista mostra apenas conversas que um Fluxo transferiu, com Contato, trecho da última mensagem e tempo de espera
- [ ] Cada item mostra por que foi transferido: pedido do Contato, escada esgotada, ou mídia não suportada
- [ ] Abrir a conversa mostra as Mensagens em ordem, com quem falou cada uma
- [ ] Mensagem enviada pelo Agente é distinguível de mensagem enviada por pessoa
- [ ] Lista e conversa são escopadas pela Empresa, com teste de isolamento
- [ ] A conversa é somente leitura neste ticket
