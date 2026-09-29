# 07: Agente sabe em que pé está o Negócio

**What to build:** A cada turno, o Agente recebe no prompt, para cada Negócio vinculado aberto do Contato, o Funil de vendas, a Etapa do funil, a Situação e o nome do Responsável, lidos do que a plataforma guarda, sem chamar o CRM. O valor não entra. Assim o Agente responde "o Carlos já está preparando sua proposta" em vez de recomeçar. Vale nos Canais e no Playground quando há Contato. Ver `.scratch/crm-rd-station/spec.md`.

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Contato com Negócios vinculados abertos: o prompt traz Funil de vendas, Etapa do funil, Situação e Responsável de cada um
- [ ] O valor do Negócio nunca entra no prompt automático
- [ ] Negócios ganhos, perdidos ou apagados não entram
- [ ] Nenhuma requisição ao CRM é feita para montar o contexto
- [ ] Sem CRM conectado ou sem Negócio vinculado, o prompt não muda
- [ ] Testes com o fake do SDK de IA verificando o conteúdo do prompt, com requisições inesperadas proibidas
