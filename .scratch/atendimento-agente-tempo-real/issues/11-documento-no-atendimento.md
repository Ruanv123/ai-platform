# 11: Abrir documentos no atendimento

**What to build:** A Equipe consulta o documento recebido pelo Contato e o texto extraído na mesma Mensagem, com visualização para formatos compatíveis e download protegido para os demais.

**Blocked by:** 09 — Exibir imagens originais no atendimento.

**Status:** done

- [x] Documentos novos recebidos nos dois Tipos de canal WhatsApp preservam arquivo, nome e tipo sem perder o texto extraído para o Agente.
- [x] Documentos visualizáveis abrem na tela; os demais oferecem download autenticado, sempre limitado à Empresa da Conversa.
- [x] Falha no download do provedor ou arquivo removido mostra estado claro sem ocultar o texto derivado; documentos antigos permanecem somente com esse texto.
- [x] A mídia acompanha o ciclo de vida da Mensagem e reentrega não duplica arquivo; testes de funcionalidade cobrem ambos os provedores e isolamento.
