# 03: Primeiro turno — gatilho de mensagem e nó de Mensagem

**What to build:** O tracer bullet. Um Canal com Fluxo publicado recebe uma mensagem do Contato, nasce uma Execução, o nó de Mensagem manda um texto de volta pelo mesmo número e a Execução termina. É o caminho mais fino que atravessa gatilho, motor, Nó, saída e persistência.

**Blocked by:** 02

**Status:** completed

- [x] O Gatilho de mensagem carrega a escolha do Canal — o vínculo Fluxo↔Canal vive nele, não numa ligação separada
- [x] Chegar mensagem num Canal com Fluxo publicado cria uma Execução presa àquela Versão
- [x] O nó de Mensagem envia texto pelo adapter de saída existente e registra a Mensagem de saída
- [x] A Execução termina com estado final e motivo registrados
- [x] Execução é escopada pela Empresa, com teste de isolamento
- [x] Reentrega do mesmo evento pelo provedor não gera resposta duplicada
- [x] Teste dirige pelo webhook do Canal e afirma em `Http::assertSent`, no padrão de `WhatsAppResponseFlowTest`
