# 18: Motor único — Fluxo trivial e migração dos Canais existentes

**What to build:** O passo de contração. Tudo que hoje vive no Job de resposta — esperar cinco segundos, agrupar as mensagens não respondidas, marcar como lida, mostrar "digitando", respeitar a janela de 24 horas, tentar de novo e registrar a falha no Canal — passa a ser comportamento do motor. Todo Canal que já tem Agente vinculado ganha um Fluxo trivial de um Nó, e o caminho antigo é apagado. Para a Empresa que já usa a plataforma, nada pode mudar.

**Blocked by:** 07, 08, 09

**Status:** completed

- [x] Migração cria um Fluxo trivial publicado para cada Canal com Agente vinculado
- [x] O Fluxo trivial entrega o turno ao Agente e não retoma
- [x] Buffer de cinco segundos com agrupamento de Mensagens não respondidas é do motor
- [x] Marcar como lida e indicador de digitando continuam acontecendo
- [x] Resposta fora da janela de 24 horas não é enviada e fica registrada como falha, em vez de sumir
- [x] Retry com backoff e erro registrado no Canal continuam valendo
- [x] O Job de resposta antigo é removido, e nenhum código chama o caminho anterior
- [x] A suíte de atendimento existente passa sem alteração de asserção — é a prova de que a migração não mudou comportamento
