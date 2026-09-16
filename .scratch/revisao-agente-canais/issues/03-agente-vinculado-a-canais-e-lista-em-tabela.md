# 03: Agente vinculado a Canais e lista em tabela

**What to build:** O mesmo Agente passa a poder responder em vários Canais, e a lista de Agentes passa a mostrar isso. Os cards viram uma tabela com nome, objetivo, Coleções, Canais vinculados e status, com as ações num menu por linha em vez de botões soltos. Excluir um Agente não derruba o Canal: o vínculo é anulado e o Canal continua conectado, esperando outro Agente. Os gatilhos de transferência somem por completo — eles nunca entraram no prompt e só faziam o cliente perder tempo preenchendo. Referência: spec, user stories 1–7, 24–26 e 29.

**Blocked by:** 01 (Canal, Conta e Número do WhatsApp)

**Status:** ready-for-agent

- [ ] Agente tem muitos Canais; a relação um-para-um com o número do WhatsApp deixa de existir
- [ ] Excluir um Agente anula o vínculo nos Canais dele sem apagá-los
- [ ] Gatilhos de transferência removidos: coluna, validação, cast, campo do formulário e a menção no comentário do template de instruções
- [ ] Lista de Agentes em tabela com nome, objetivo, contagem de Coleções, Canais vinculados e status
- [ ] Ativar, desativar e excluir movidos para um menu de ações na linha, com confirmação antes de excluir
- [ ] Member vê a tabela sem as ações de gerenciamento
- [ ] Componente `table` do shadcn trazido para o projeto se ainda não existir
- [ ] Feature tests: Canais vinculados aparecem na listagem, gatilhos não são mais aceitos nem devolvidos, exclusão de Agente preserva o Canal, permissões por Papel, isolamento entre Empresas
