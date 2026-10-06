# Foco em Agentes e atendimento no WhatsApp: sem Fluxos, Loja nem CRM

Status: accepted

O produto é uma plataforma para a Empresa criar Agentes que atendem seus Contatos pelo WhatsApp, a partir da Base de conhecimento, com a Equipe podendo assumir pelo atendimento. Fluxos (editor visual, motor de execução, Eventos e observabilidade), a Loja (Shopify, com catálogo, Pedidos, Link de compra e Atribuição) e o CRM (RD Station e o catálogo de provedores) chegaram a ser construídos ou especificados e foram removidos, porque espalhavam o esforço por três produtos laterais antes de o core estar maduro. A Agenda continua, porque agendar é atendimento para os segmentos-alvo, e os Templates continuam, porque são a única forma de falar com o Contato fora da janela de 24 horas.

## Consequences

- Os ADRs 0005 a 0009 (Fluxos e Campos do Contato), 0013 (Loja) e 0014 e 0015 (CRM) ficam superseded por este.
- O que o Agente faz além de responder vive em Ações do próprio Agente, nunca num grafo de Nós. Reabrir Fluxos, Loja ou CRM exige uma decisão nova, não a restauração do código antigo.
