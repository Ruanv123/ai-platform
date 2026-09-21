# 02: Assumir e responder

**What to build:** A pessoa assume a conversa e responde pelo mesmo número, sem o cliente perceber a troca. Quem assumiu fica visível para o resto da Equipe, e quem não assumiu é avisado antes de escrever, para que duas pessoas não respondam o mesmo cliente com coisas diferentes.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Botão de assumir marca quem ficou responsável, e a lista passa a mostrar isso
- [ ] Quem não assumiu vê aviso antes de conseguir escrever
- [ ] Responder envia pelo Canal da conversa e registra a Mensagem de saída, com a pessoa que enviou
- [ ] O motor continua sem responder enquanto a conversa está com humano
- [ ] Duas pessoas tentando assumir ao mesmo tempo: uma vence, de forma testada
- [ ] Testes dirigem pelas rotas e afirmam em `Http::assertSent`
