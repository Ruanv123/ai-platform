# 02: Mostrar todas as Conversas na caixa de entrada

**What to build:** A Equipe acompanha na mesma tela as Conversas conduzidas diretamente pelo Agente e as transferências de Fluxos. Uma Conversa do Agente já pode ser aberta e lida antes de ser assumida por alguém.

**Blocked by:** 01 — Identificar Conversas por Contato e Canal.

**Status:** done

- [x] A lista reúne todo par Contato + Canal com Mensagens dos Tipos de canal WhatsApp oficial e WhatsApp de testes, sem duplicar Conversas que também têm Execução de Fluxo.
- [x] A ordenação usa a Mensagem mais recente e a paginação mantém ordem estável quando duas Mensagens têm o mesmo horário.
- [x] Uma Conversa conduzida pelo Agente abre com Histórico em leitura e estado identificável; transferências de Fluxos conservam seus controles atuais.
- [x] A listagem e o detalhe respeitam o isolamento por Empresa; testes de funcionalidade cobrem Agente, Fluxo e ausência de Mensagens.
