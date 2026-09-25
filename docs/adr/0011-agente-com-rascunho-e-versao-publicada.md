# Agente com Rascunho e Versão publicada, e um único estado ativo/inativo

O Agente deixa de valer nos Canais no momento em que é salvo: toda edição vai para o **Rascunho do agente**, que o Playground e as Situações de teste exercitam, e só **Publicar** cria uma **Versão do agente** que passa a atender. Um Agente que já está atendendo clientes reais não pode mudar de comportamento no meio de uma conversa porque alguém trocou um Limite para experimentar. Publicar é uma ação, não um estado: o único estado do Agente continua sendo ativo ou inativo, todo Agente nasce inativo e sem Versão, e ativar pela primeira vez publica.

## Considered Options

- **Salvar vale na hora, item a item** (como Proteções e Cenários no Chatwoot): rejeitada porque cada ajuste vira experimento em produção, e as Situações de teste perderiam o momento natural de rodar — antes de valer.
- **Salvar na hora, com o Agente inativo como proteção**: rejeitada porque obriga a tirar o atendimento do ar para editar.
- **Dois estados, publicado e ativo**: rejeitada porque "publicado mas inativo" e "ativo mas nunca publicado" são combinações que ninguém sabe ler na tela.

## Consequences

- O Nó do Fluxo que entrega ao Agente usa a Versão publicada, nunca o Rascunho.
- Publicar com Situações de teste falhando avisa, mas não bloqueia.
- Voltar para uma Versão anterior passa a ser possível pelo mesmo mecanismo do Fluxo (ADR 0008).
