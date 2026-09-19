# 01: Contato e Mensagem

**What to build:** Os dois models do atendimento. **Contato** escopado pela Empresa (`BelongsToTeam`, como os demais models da plataforma), único pelo par Empresa + telefone, guardando o nome informado pelo WhatsApp. **Mensagem** pertencente ao Contato e sempre presa ao Canal por onde passou, guardando direção, tipo original do evento, id da mensagem no provedor, texto resolvido, status de entrega e a marca de já respondida. Factories para ambos. **Não crie model de Conversa** — ver ADR 0003. Referência: spec, seção Implementation Decisions / Domínio, user stories 3, 7, 8, 43.

**Blocked by:** nada

**Status:** ready-for-agent

- [ ] Migration e model de Contato, escopado pela Empresa, único por Empresa + telefone
- [ ] Migration e model de Mensagem, pertencente ao Contato e ao Canal
- [ ] Mensagem registra direção, tipo original, id no provedor, texto resolvido, status de entrega e marca de respondida
- [ ] Factories com estados úteis (entrada/saída, respondida/não respondida)
- [ ] Leitura do Histórico: Mensagens de um Contato **num Canal**, cortadas no primeiro intervalo maior que 24h, com teto de mensagens
- [ ] Sem índice único de id do provedor no banco — a idempotência é resolvida no issue 03
- [ ] Testes do corte de 24h, do teto e do filtro por Canal
