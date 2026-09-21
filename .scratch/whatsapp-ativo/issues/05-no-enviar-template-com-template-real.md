# 05: Nó Enviar Template com Template real

**What to build:** O Nó de Enviar Template deixa de ser mínimo: ele escolhe um Template aprovado de verdade e preenche as variáveis posicionais com o que a plataforma sabe do Contato. É assim que o Fluxo disparado por webhook consegue iniciar conversa com a janela fechada.

**Blocked by:** 02, `fluxos-motor` 17

**Status:** ready-for-agent

- [ ] O Nó escolhe um Template aprovado do Canal em questão
- [ ] As variáveis posicionais são preenchidas com Variável, Campo do Contato e Variável global
- [ ] Pré-visualização do que vai chegar, no painel do Nó
- [ ] Template não aprovado não pode ser selecionado
- [ ] Template removido ou recusado depois de publicado faz o Nó seguir a saída de erro, com motivo claro
- [ ] Envio registra a Mensagem de saída como as demais
