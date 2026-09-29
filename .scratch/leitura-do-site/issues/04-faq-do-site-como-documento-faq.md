# 04: FAQ do site como Documento FAQ

**What to build:** Quando a Leitura do site encontra uma página de perguntas frequentes (indicada pelo agente de escolha de páginas), um agente do `laravel/ai` com structured output extrai dela os pares pergunta/resposta. Os pares viram um único Documento FAQ na Coleção do site, marcado como vindo da Leitura, com um Trecho por par, como o Documento FAQ já faz. A página de FAQ não vira também Documento URL, para não duplicar Trechos. Pares e textos ficam no idioma original do site, e o conteúdo é tratado como dado não confiável. Se nenhum par for extraído, a página segue como Documento URL comum. Ver `.scratch/leitura-do-site/spec.md`.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Site com página de FAQ gera um Documento FAQ na Coleção do site com os pares extraídos
- [ ] A página de FAQ não gera Documento URL quando o Documento FAQ é criado
- [ ] Página de FAQ sem pares extraíveis vira Documento URL comum
- [ ] Site sem página de FAQ não gera Documento FAQ
- [ ] Testes com o agente de extração de FAQ falsificado
