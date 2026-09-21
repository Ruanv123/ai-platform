# 17: Nós de saída — Emitir Evento e Enviar Template

**What to build:** Forma mínima de dois Nós. Emitir Evento registra que a Execução passou por um ponto nomeado, o que mais tarde vai alimentar métricas e webhooks de saída. Enviar Template manda uma mensagem pré-aprovada pela Meta, preenchendo as variáveis posicionais — é o único jeito de falar com a janela de 24 horas fechada.

**Blocked by:** 06

**Status:** completed

- [x] Nó de Emitir Evento com nome declarado, não string escrita à mão em cada uso
- [x] O Evento emitido fica registrado na Execução, pronto para o barramento completo de outra spec
- [x] Nó de Enviar Template é distinto do nó de Mensagem, com variáveis posicionais e sem texto livre
- [x] As variáveis do Template são preenchidas com Variável e Campo do Contato
- [x] Enviar Template funciona com a janela de 24 horas fechada; o nó de Mensagem não
- [x] Sincronização e submissão de Template à Meta ficam para a spec de WhatsApp ativo
