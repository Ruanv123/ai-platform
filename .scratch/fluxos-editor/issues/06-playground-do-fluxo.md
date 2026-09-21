# 06: Playground do Fluxo

**What to build:** A Empresa conversa com o próprio fluxo antes de publicar. A gaveta abre por baixo do canvas, sem sombra, com a conversa de um lado e o traço do turno do outro, e os nós acendem no desenho conforme a Execução passa por eles. Nada é enviado ao Contato.

**Blocked by:** 04, `fluxos-motor` 04, `fluxos-observabilidade` 02

**Status:** ready-for-agent

- [ ] Gaveta inferior rente, sem sombra, que abre e fecha mantendo o canvas visível
- [ ] Roda a Versão rascunho, nunca a publicada, e nada sai para o Contato
- [ ] Conversa de um lado, traço do turno do outro
- [ ] Os Nós acendem no canvas conforme a Execução passa
- [ ] Custo em reais e contagem de tokens do turno
- [ ] Fluxo com gatilho de webhook é testável injetando payload falso, pelo gatilho manual do motor
- [ ] Nenhuma Execução do Playground aparece nas métricas do Fluxo
