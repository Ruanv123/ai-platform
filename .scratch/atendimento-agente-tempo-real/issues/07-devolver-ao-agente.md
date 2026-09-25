# 07: Devolver manualmente a Conversa ao Agente

**What to build:** A pessoa responsável devolve a Conversa ao Agente antes do prazo. O Agente responde uma vez quando a última Mensagem do Contato ficou sem resposta da Empresa; se a Empresa respondeu por último, aguarda nova entrada.

**Blocked by:** 06 — Responder e trocar o responsável pela Conversa.

**Status:** done

- [x] A ação de devolver encerra a pausa e libera o responsável quando não há resposta pendente ou quando a resposta do Agente é enviada com sucesso.
- [x] Se a última Mensagem relevante veio do Contato após a última saída enviada com sucesso pela Empresa, o Agente considera o Histórico e responde exatamente uma vez.
- [x] Uma Mensagem humana enviada por último impede resposta duplicada, e repetir a ação de retorno não cria outro envio.
- [x] Fora da janela do WhatsApp oficial, a resposta automática aparece como bloqueada no chat sem chamada de texto à Meta; falha da IA ou do provedor mantém pausa e responsável.
- [x] Não há nova tentativa automática da mesma falha sem ação humana ou nova Mensagem do Contato; testes cobrem retorno, idempotência e falhas na fronteira HTTP e do processamento.
