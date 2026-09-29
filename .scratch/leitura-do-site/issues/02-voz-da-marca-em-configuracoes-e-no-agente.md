# 02: Voz da marca em Configurações e nas instruções do Agente

**What to build:** A Empresa ganha a Voz da marca: um texto descritivo em pt-BR (da ordem de 1.000 caracteres no máximo) e de 2 a 4 frases de exemplo. Em Configurações > Empresa, quem tem `UpdateTeam` edita os dois. As instruções de todos os Agentes da Empresa ganham a seção "Voz da marca" entre "Dados da Empresa" e "Estilo", com o texto e as frases, dizendo explicitamente que o Estilo do Agente prevalece quando divergir. Sem Voz da marca, a seção some e o Agente se comporta como hoje. A Voz da marca é lida na hora, como os Dados da Empresa: editar muda a fala de todos os Agentes, inclusive os ativos, sem republicar, e a Versão do agente não a guarda. A versão do template de instruções é incrementada. Ver `.scratch/leitura-do-site/spec.md`, ADR 0017 e os termos Voz da marca e Estilo no `CONTEXT.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Owner e Admin editam o texto e as frases de exemplo da Voz da marca em Configurações > Empresa; Member não
- [ ] Validação de tamanho do texto e de 2 a 4 frases quando houver frases
- [ ] As instruções do Agente (Playground e Canais) trazem a seção "Voz da marca" com o texto, as frases e a precedência do Estilo
- [ ] Sem Voz da marca, a seção é omitida
- [ ] Editar a Voz da marca muda as instruções de um Agente ativo sem nova Versão do agente
- [ ] Uma Empresa não vê nem edita a Voz da marca de outra
