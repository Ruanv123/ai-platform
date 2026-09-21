# 04: Traço nível 3 — payload HTTP com mascaramento

**What to build:** Quando um Nó de Requisição HTTP falha, dá para ver exatamente o que foi enviado e o que voltou, lado a lado — que é o que transforma "a integração quebrou" em "mandei `manha` e a API espera `AM`". Os campos marcados como sensíveis aparecem mascarados.

**Blocked by:** 02, `fluxos-motor` 14

**Status:** ready-for-agent

- [ ] Request e response completos gravados no passo do Nó
- [ ] Exibição lado a lado no traço, com o status HTTP
- [ ] Campos declarados como sensíveis no Nó aparecem mascarados no traço
- [ ] Credencial nunca é gravada, em nenhum nível, sem exceção configurável
- [ ] Tentativas de retry aparecem contadas
