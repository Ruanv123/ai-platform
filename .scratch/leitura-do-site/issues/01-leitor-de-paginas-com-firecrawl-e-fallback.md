# 01: Leitor de páginas com Firecrawl e fallback no Readability

**What to build:** Prefactor. O Documento URL passa a ler a página pela API do Firecrawl (markdown), inclusive páginas que só mostram conteúdo depois de rodar JavaScript. Se o Firecrawl falhar (erro, timeout, resposta vazia) ou não houver chave configurada, a leitura cai no Readability atual, sem o cliente perceber. Nasce um módulo leitor de páginas com duas capacidades: devolver o markdown de uma URL e devolver o mapa de URLs de um site. O mapa ainda não tem uso aqui e fica pronto para a Leitura do site. A integração usa o cliente HTTP do Laravel, sem SDK novo, com chave e URL base na configuração de serviços. As mensagens de falha amigáveis do Documento URL continuam as mesmas. Ver `.scratch/leitura-do-site/spec.md` e ADR 0016.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Documento URL é lido pelo Firecrawl quando há chave configurada, e o markdown vira Trechos pela ingestão atual
- [ ] Falha, timeout ou resposta vazia do Firecrawl levam ao Readability sem marcar o Documento como falho
- [ ] Sem chave configurada, o leitor usa só o Readability
- [ ] Falha nas duas leituras mantém as mensagens amigáveis atuais do Documento URL
- [ ] O leitor devolve o mapa de URLs de um site pelo Firecrawl; sem Firecrawl, devolve os links da página lida
- [ ] Testes existentes do Documento URL continuam passando, e os novos cobrem o Firecrawl e o fallback com `Http::fake` e `preventStrayRequests`
