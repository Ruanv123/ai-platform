# 09: Escada de exceção e Transferir para humano

**What to build:** O Contato responde outra coisa, ou não responde nada, e o Fluxo sabe o que fazer. Os cinco degraus funcionam na ordem: repetir o Nó com reforço, cair no Nó de exceção, entregar o turno ao Agente para responder a pergunta paralela e voltar, transferir para humano, encerrar. Transferir pausa a Execução, marca o Contato como aguardando e avisa a Equipe.

**Blocked by:** 07, 08

**Status:** completed

- [x] Escada configurável por Nó, com o terceiro degrau como padrão
- [x] Degrau 1: repetir o Nó N vezes com mensagem de reforço
- [x] Degrau 2: Nó de exceção do Fluxo
- [x] Degrau 3: entregar o turno ao Agente e retomar o Nó depois
- [x] Degrau 4: Nó de Transferir para humano pausa a Execução, marca o Contato e notifica a Equipe
- [x] Degrau 5: encerrar a Execução com motivo registrado
- [x] Timeout por Nó dispara a escada quando o Contato não responde — é coisa distinta do gatilho de inatividade, que está fora de escopo
- [x] Enquanto o Contato está aguardando humano, o motor não responde por ele
- [x] Retorno automático ao motor por inatividade, já que a tela de atendimento é outra spec
