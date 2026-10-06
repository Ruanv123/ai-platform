# Fluxo de conversa e Fluxo de reação: unicidade derivada do grafo, sem regra de desempate

Status: superseded by ADR-0018

Vários Fluxos podem disparar na mesma mensagem recebida. O que não pode é dois Fluxos conversarem ao mesmo tempo: se o Fluxo A perguntou a data e o Fluxo B perguntou o CPF, a resposta "amanhã de manhã" não pertence a nenhum dos dois de forma decidível — a ambiguidade está na cabeça do Contato, não no código, e nenhuma regra de desempate a resolve.

A separação é lida do grafo, não declarada pelo usuário:

- **Fluxo de conversa** contém algum Nó que espera o Contato. Toma o turno. **No máximo um publicado por Canal no Gatilho de mensagem.**
- **Fluxo de reação** não contém nenhum Nó de espera e roda até o fim de uma vez. Quantos a Empresa quiser, todos em paralelo.

Fluxos de conversa com Gatilho de webhook podem ser vários por Canal, porque quem chama nomeia o evento: não há ambiguidade na entrada.

## Considered Options

- **Lista ordenada por prioridade, primeiro que casa vence**: rejeitada pelo usuário por acrescentar um conceito que precisa ser aprendido, explicado e mantido em ordem.
- **Condições mutuamente exclusivas validadas na publicação**: rejeitada porque provar exclusividade de expressões arbitrárias é indecidível no caso geral — o validador acabaria mentindo em parte dos casos.
- **Todos os Fluxos que casam disparam, inclusive os de conversa**: rejeitada porque manda respostas duplicadas ao Contato e deixa a mensagem seguinte sem dono.

## Consequences

- A ramificação que seria "qual Fluxo atende" passa a acontecer **dentro** do Fluxo de conversa, no primeiro Nó depois do Gatilho — é para isso que existem a Condição e o Roteamento por intenção.
- A política de colisão de Execução (uma ativa por Contato e Canal) vale só para Fluxos de conversa. Fluxos de reação não tomam turno nem ocupam Execução ativa.
- Acrescentar um Nó de espera a um Fluxo de reação muda a categoria dele. O editor avisa disso na publicação, e a publicação é recusada se já houver um Fluxo de conversa naquele Canal.
- Um Fluxo de reação **pode** enviar mensagem ao Contato, mesmo que um Fluxo de conversa esteja no meio de uma pergunta. Risco aceito: mensagens de reação são avisos curtos. O editor alerta na publicação, e a decisão é da Empresa.
