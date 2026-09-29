# 01: Montagem das Ações do Agente num lugar só

**What to build:** Prefactor antes do CRM. Hoje o Agente que atende nos Canais e o Agente do Playground montam as suas Tools cada um do seu jeito, repetindo as regras de quando a busca na Base de conhecimento e as Ações da Agenda aparecem e passando à mão a indicação de que no Playground marcar e cancelar Compromisso são simulados. O CRM vai acrescentar quatro Ações com a mesma regra de "ler é real, escrever é simulado no Playground", e cada nova Ação dobraria a duplicação. Aqui a montagem passa a acontecer num único lugar, que recebe o Agente configurado, o Contato (ou nenhum, no Playground) e o modo real ou simulado, e devolve as Tools. Nenhum comportamento visível muda. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] O Agente dos Canais e o do Playground obtêm as Tools do mesmo lugar, informando apenas o modo real ou simulado
- [ ] A busca na Base de conhecimento continua aparecendo só quando o Agente tem Coleções
- [ ] As Ações da Agenda continuam aparecendo só quando o Agente tem Calendários agendáveis, reais nos Canais e com marcar e cancelar simulados no Playground
- [ ] A Tool de voltar ao Fluxo continua sendo acrescentada só no atendimento dos Canais, quando existe
- [ ] Os testes existentes de agendamento pelo Agente, de Playground e de resposta no WhatsApp passam sem alteração de comportamento
