# Versão do fluxo imutável, e um DSL independente do editor

Publicar um Fluxo cria uma **Versão imutável**. Toda Execução fica presa à Versão em que começou: quem está no meio termina na antiga, e a edição do rascunho não afeta ninguém em andamento. O Fluxo é persistido como um **DSL próprio** — nós, arestas e configuração — em que a posição no canvas é metadado descartável. O editor visual é uma view sobre o DSL, nunca o contrário.

## Considered Options

- **Fluxo mutável, sem versão**: rejeitada porque a Execução guarda "estou no Nó X" (ADR 0005) e apagar o Nó X deixa Execuções órfãs, de forma silenciosa e difícil de reproduzir.
- **Migrar Execuções em andamento para a Versão nova**: adiada. É funcionalidade real, mas quase ninguém usa, e ela obriga a responder o que fazer quando o Nó atual não existe mais na Versão nova.
- **Persistir o formato do React Flow direto**: rejeitada porque amarra o motor de execução à biblioteca do editor para sempre. Trocar de biblioteca, gerar Fluxo por IA ou importar de outra ferramenta passariam todos pelo formato de uma dependência de frontend.

## Consequences

- Rollback sai de graça: republicar uma Versão anterior é uma operação, não uma reconstrução.
- Comparar desempenho entre Versões passa a ser possível, porque cada Execução sabe em qual rodou.
- A geração de Fluxo por IA produz DSL, que passa por validação de schema e de integridade do grafo antes de virar Fluxo. Nunca escrita direta no banco.
- Teste A/B acontece **dentro** de uma Versão, em um Nó que sorteia entre arestas — nunca entre duas Versões publicadas ao mesmo tempo, o que quebraria a premissa de uma Versão publicada por Fluxo.
