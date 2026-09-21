# 07: Chat de IA — gerar fluxo a partir de descrição

**What to build:** A Empresa descreve o atendimento em português e recebe um fluxo montado no canvas. O chat abre como sheet rente à direita, sem sombra. A IA não escreve no desenho direto: ela propõe uma lista de alterações que a pessoa aplica ou descarta.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Sheet de chat rente à direita, sem sombra, que abre e fecha
- [ ] Descrição em texto gera DSL
- [ ] O DSL gerado passa por validação de schema e de integridade antes de virar desenho
- [ ] As alterações aparecem como lista para aplicar ou descartar, nunca aplicadas direto
- [ ] Aplicar escreve no rascunho e o canvas reflete
- [ ] A IA não inventa tipo de Nó fora do catálogo
- [ ] Falha do provedor é comunicada sem perder o que já estava desenhado
