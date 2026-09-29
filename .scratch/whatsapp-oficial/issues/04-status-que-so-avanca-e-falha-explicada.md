# 04: Status que só avança e falha explicada na bolha

**What to build:** A Equipe vê na conversa se cada mensagem enviada foi enviada, entregue ou lida, sem o status voltar atrás quando a Meta entrega os eventos fora de ordem. Quando um envio falha, a bolha explica em português o motivo. Ver spec `../spec.md`, seção "Strategies da família Meta".

**Blocked by:** 02

**Status:** resolved

- [x] O status só avança (sent < delivered < read); `failed` sobrescreve qualquer status, menos `read`
- [x] `messages` ganha `failure_code`; em `failed` são gravados o código da Meta e `failure_reason` traduzido (131047 janela de 24h fechada, 131026 número não pode receber, 131053 mídia recusada, 131050 Contato bloqueou marketing, texto genérico para os demais)
- [x] 131050 revoga o consentimento de marketing do Contato
- [x] A bolha da Mensagem com falha mostra o motivo traduzido
- [x] `pricing` é ignorado
- [x] A família do WhatsApp de testes segue a mesma regra de avanço onde houver status equivalente
- [x] Conformidade: status fora de ordem nas duas famílias; `failed` com código e 131050 só na oficial
