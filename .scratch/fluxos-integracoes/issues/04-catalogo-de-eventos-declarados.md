# 04: Catálogo de Eventos declarados

**What to build:** A Empresa declara os Eventos que usa — nome, descrição e formato do dado — e o Nó de Emitir Evento passa a escolher da lista em vez de aceitar texto livre. Sem isso, `lead_qualificado` e `lead-qualificado` viram dois funis e ninguém descobre.

**Blocked by:** `fluxos-motor` 17

**Status:** ready-for-agent

- [ ] Declarar Evento com nome, descrição e formato do dado
- [ ] O Nó de Emitir Evento escolhe da lista, sem texto livre
- [ ] Renomear um Evento não perde o histórico já emitido
- [ ] Apagar um Evento em uso avisa quais Fluxos o emitem
- [ ] Eventos são escopados pela Empresa
