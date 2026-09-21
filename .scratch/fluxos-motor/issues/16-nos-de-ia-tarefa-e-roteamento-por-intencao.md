# 16: Nós de IA — Tarefa de IA e Roteamento por intenção

**What to build:** Dois Nós que usam o modelo sem entregar a conversa a ele. A Tarefa de IA extrai, classifica ou reescreve algo e devolve o resultado numa Variável. O Roteamento por intenção lê a mensagem do Contato e escolhe entre as opções declaradas no próprio Nó, com uma saída para "nenhuma das anteriores".

**Blocked by:** 07

**Status:** completed

- [x] Nó de Tarefa de IA com instrução própria, devolvendo saída estruturada numa Variável
- [x] Nó de Roteamento por intenção com as opções declaradas inline, cada uma com descrição
- [x] Saída obrigatória para nenhuma intenção reconhecida
- [x] Nenhum dos dois toma o turno nem espera o Contato
- [x] Falha do provedor segue o caminho de erro do Nó em vez de derrubar a Execução
- [x] Não existe catálogo de Intenções da Empresa nesta v1
- [x] Testes usam o `::fake()` do agente
