# 05: Ramificação — Condição, avaliador de expressões e Teste A/B

**What to build:** O Fluxo passa a seguir caminhos diferentes conforme o que já coletou: o nó de Condição avalia uma expressão sobre as Variáveis e escolhe a saída, e o nó de Teste A/B sorteia entre arestas para comparar duas redações. Entrega também o avaliador de expressões único, que o ticket 07 vai reusar na condição de retorno do Agente.

**Blocked by:** 04

**Status:** completed

- [x] Um avaliador de expressões booleanas, com um consumidor agora e outro no 07
- [x] Nó de Condição com várias saídas; a primeira verdadeira, de cima para baixo, é seguida
- [x] Saída "senão" sempre disponível, para que nenhuma Execução fique sem caminho
- [x] Expressão que falha ao avaliar segue a saída de erro em vez de derrubar a Execução
- [x] Nó de Teste A/B sorteia entre arestas dentro da mesma Versão, nunca entre Versões
- [x] A distribuição do sorteio é reproduzível em teste
