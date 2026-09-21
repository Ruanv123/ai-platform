# 04: Capturar resposta — a Execução através de turnos

**What to build:** O Fluxo pergunta alguma coisa e para. O Contato responde horas depois e a Execução retoma exatamente no Nó onde estava, com o que ele disse guardado numa Variável, e segue em frente. É aqui que o atendimento deixa de ser turno isolado e passa a ter memória.

**Blocked by:** 03

**Status:** completed

- [x] O nó de Capturar resposta guarda a mensagem do Contato numa Variável nomeada
- [x] A Execução persiste o Nó corrente e as Variáveis entre turnos
- [x] A mensagem seguinte do mesmo Contato no mesmo Canal retoma a Execução em vez de criar outra
- [x] Validação de formato no Nó, com caminho de saída para valor inválido
- [x] As Variáveis morrem com a Execução — não vazam para a Execução seguinte
- [x] Teste dirige dois turnos pelo webhook e afirma a retomada no Nó certo
