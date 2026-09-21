# 12: Colisão de gatilho — as três políticas

**What to build:** Um gatilho dispara enquanto o Contato já está no meio de um atendimento, e o que acontece é uma regra visível que a Empresa escolheu, não um comportamento imprevisível. Existe no máximo uma Execução de conversa ativa por Contato e Canal, e cada gatilho declara o que fazer quando encontra uma em curso.

**Blocked by:** 11

**Status:** completed

- [x] Uma Execução de conversa ativa por Contato e Canal, garantida sob concorrência
- [x] Política declarada por gatilho: ignorar, enfileirar ou substituir
- [x] Padrão do Gatilho de mensagem é ignorar, e a mensagem alimenta a Execução em curso
- [x] Substituir encerra a Execução anterior com motivo registrado
- [x] Enfileirar dispara quando a Execução em curso termina
- [x] Fluxos de reação ficam fora dessa regra, conforme o 11
- [x] "Interromper e retomar" não entra nesta v1
