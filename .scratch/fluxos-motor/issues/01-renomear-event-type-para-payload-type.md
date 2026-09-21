# 01: Renomear `event_type` para `payload_type`

**What to build:** Nada muda para quem usa o produto. A coluna da Mensagem que hoje guarda o tipo do payload do provedor passa a se chamar `payload_type`, liberando a palavra "Evento" para o barramento que virá depois. É prefactor: faz-se agora porque são 24 ocorrências em 13 arquivos, e cada ticket seguinte acrescenta mais.

**Blocked by:** None (can start immediately)

**Status:** completed

- [x] Migração renomeia a coluna sem perda de dado
- [x] Model, factory, strategies de entrada, adapters de saída e o Agente passam a usar o nome novo
- [x] Nenhuma ocorrência de `event_type` sobra no código da aplicação nem nos testes
- [x] A suíte existente passa sem alteração de asserção, só de nome
