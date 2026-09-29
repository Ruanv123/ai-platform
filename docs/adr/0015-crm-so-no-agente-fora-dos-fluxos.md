# CRM só no Agente, fora dos Fluxos, na v1

O CRM entra na plataforma apenas pelo Agente — Ações de consultar a ficha, garantir Negócio, mover de Etapa do funil e registrar nota — e pelo que a plataforma faz por conta própria (Vínculo e Resumo do atendimento). Não há Nó de CRM nem Gatilho de CRM nos Fluxos. É a mesma direção que a Agenda tomou, quando o conector de agenda do Fluxo virou `wontfix` e o agendamento passou a viver só no Agente.

## Considered Options

- **Conector curado que compila para o Nó de Requisição HTTP**, como previa `.scratch/fluxos-integracoes/spec.md`: rejeitada. O token do RD Station CRM é rotativo e exige renovação sob lock, "garantir Negócio" é busca, decisão e escrita, não uma requisição, e o Nó teria que conhecer o formato de cada Provedor de CRM, quebrando o modelo único.
- **Nó de CRM nativo e Gatilho de CRM na v1**: adiada. Dobraria a superfície (validação de publicação, colisão com Execuções, janela de 24h em Fluxo iniciado de fora) antes de o modelo canônico ter sido provado com um provedor.

## Consequences

- Quem precisa de CRM num Fluxo continua usando o Nó de Requisição HTTP com uma Integração genérica, fora do modelo canônico.
- Quando o CRM entrar nos Fluxos, deve chamar o mesmo motor que as Ações do Agente usam, nunca uma segunda implementação.
