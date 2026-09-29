# 03: Leitura do site em Configurações, das páginas até a Coleção do site

**What to build:** Tracer bullet da feature. Em Configurações > Empresa, o bloco "Leitura do site" mostra a última Leitura (situação, data, páginas encontradas, lidas e com falha, motivo da falha) e oferece "Ler o site" para o site salvo.

Ler cria uma Leitura do site em andamento, que roda em segundo plano na fila de ingestão:
1. mapeia as URLs pelo leitor de páginas;
2. um agente do `laravel/ai` com structured output escolhe até N URLs (configurável, padrão 25), sempre com a home. Prioriza institucional, serviços, políticas e contato, e exclui produtos individuais, posts, paginação, login e carrinho. O conteúdo do site é tratado como dado não confiável;
3. cada página escolhida vira um Documento URL da Coleção do site, criada nesse momento e marcada como mantida pela Leitura, com os Documentos marcados como vindos dela. Os Documentos seguem a ingestão atual e contam na cota de armazenamento.

Regras:
- **Validação na requisição, sem requisição externa:** endereço inválido, Instagram e endereços de WhatsApp (`wa.me`, `api.whatsapp.com`, `whatsapp.com`) são recusados com mensagem que orienta.
- **Limite:** no máximo uma Leitura iniciada a cada 24 horas por Empresa, e nenhuma enquanto outra estiver em andamento. A recusa diz quando será possível de novo.
- **Falha total** (nenhuma página lida): Leitura com falha e motivo amigável.
- **Falha parcial:** Leitura concluída, e as páginas com erro ficam como Documentos com falha.
- **Permissão:** rodar exige `UpdateTeam`. Os Documentos criados não exigem `ManageKnowledge` à parte.

Ver `.scratch/leitura-do-site/spec.md`, ADR 0016 e os termos Leitura do site e Coleção do site no `CONTEXT.md`.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] "Ler o site" cria uma Leitura em andamento, e o bloco acompanha até concluída ou com falha
- [ ] Páginas escolhidas pelo agente, com a home sempre incluída e no máximo N, viram Documentos URL da Coleção do site
- [ ] A Empresa tem no máximo uma Coleção do site, e ela aparece na Base de conhecimento como Coleção comum
- [ ] Instagram, WhatsApp e endereços inválidos são recusados sem nenhuma requisição ao Firecrawl
- [ ] Segunda Leitura em menos de 24 horas ou com outra em andamento é recusada com o horário liberado
- [ ] Falha total deixa a Leitura com falha e motivo; falha parcial conclui com as páginas com erro visíveis como Documentos com falha
- [ ] Estouro de cota faz as páginas que não couberem falharem como qualquer Documento URL
- [ ] Member não roda Leitura; uma Empresa não vê a Leitura de outra
- [ ] Testes pela borda HTTP com Firecrawl falsificado, agente de escolha falsificado com `preventStrayPrompts` e `Embeddings::fake()`
