# 02: Nó HTTP usa Integração salva

**What to build:** O Nó de Requisição HTTP para de carregar segredo dentro do desenho e passa a apontar para uma Integração cadastrada. Trocar uma chave passa a ser uma edição num lugar só, em vez de caçar o valor em cada Nó de cada Fluxo.

**Blocked by:** 01, `fluxos-motor` 14

**Status:** ready-for-agent

- [ ] O Nó escolhe uma Integração da Empresa em vez de receber credencial digitada
- [ ] A autenticação é aplicada na chamada a partir da Integração, sem passar pelo DSL
- [ ] O DSL do Fluxo nunca contém segredo
- [ ] Trocar a credencial na Integração vale para todos os Nós que a usam
- [ ] Integração com erro faz o Nó seguir a saída de erro, com motivo claro no traço
- [ ] Fluxos existentes que usavam credencial no Nó são migrados ou avisados
