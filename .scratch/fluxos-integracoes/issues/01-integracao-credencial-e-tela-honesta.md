# 01: Integração — credencial e tela honesta

**What to build:** A tela de Integrações deixa de ser um catálogo de logos que não conectam. A Empresa cadastra uma Integração de verdade, guarda a credencial dela em segurança, testa a conexão e vê o estado. O que ainda não conecta passa a dizer que ainda não conecta, em vez de prometer capacidade inexistente.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Cadastrar uma Integração com nome, tipo e credencial
- [ ] Credencial guardada cifrada e nunca devolvida para a tela
- [ ] Testar conexão a partir da tela, com resultado legível
- [ ] Estado da Integração visível: conectada, com erro, nunca testada
- [ ] O catálogo para de prometer capacidade que não existe; o que é planejado é rotulado como planejado
- [ ] Integração é escopada pela Empresa, com teste de isolamento
- [ ] Apagar uma Integração em uso por um Fluxo tem comportamento definido e avisado
