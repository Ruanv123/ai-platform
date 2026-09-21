# 10: Limites de segurança da plataforma

**What to build:** Um Fluxo mal desenhado não derruba a plataforma nem queima orçamento de IA. Existem tetos, todos com valor padrão e ajustáveis, e bater num teto encerra a Execução com um motivo explícito que aparece para quem for investigar — nunca uma parada em silêncio.

**Blocked by:** 08

**Status:** completed

- [x] Teto de passos por turno, que impede laço infinito entre Nós de decisão
- [x] Tempo de vida máximo da Execução, com padrão de 30 dias
- [x] Teto de Execuções simultâneas por Empresa
- [x] Bater qualquer teto encerra a Execução com motivo explícito registrado
- [x] Nenhum dos tetos encerra em silêncio, e existe teste para cada um
- [x] Os valores são configuráveis sem alteração de código
