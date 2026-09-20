# 08: Fluxo de resposta com buffer

**What to build:** O atendimento fecha o ciclo. Cada mensagem recebida abre um fluxo despachado com `delay` de 5 segundos — o atraso é no despacho, **nunca** `sleep()` dentro do job. Marcar como lida e "digitando" disparam na chegada, antes da espera. Ao acordar, o fluxo lê as Mensagens ainda **não respondidas** do Contato naquele Canal, monta o Histórico, roda o Agente e envia uma única mensagem de resposta, marcando as Mensagens como respondidas. Mensagem nova durante um fluxo em andamento abre um fluxo novo do início; como as respondidas já saíram do conjunto, o segundo fluxo responde só o que sobrou. Um Agente de atendimento novo, irmão do `PlaygroundAgent`: mesma montagem de Instruções e mesma ferramenta de busca na Base de conhecimento, com o Histórico vindo das Mensagens. Referência: spec, user stories 21, 24, 26–29.

**Blocked by:** 03, 06, 07

**Status:** done

- [x] Fluxo por mensagem recebida, despachado com `delay` de 5 segundos
- [x] Marcar como lida e "digitando" disparam na chegada, antes da espera
- [x] O fluxo responde as Mensagens não respondidas do Contato naquele Canal e as marca ao enviar
- [x] Duas mensagens dentro da janela recebem uma resposta só
- [x] Mensagem já respondida nunca é respondida de novo
- [x] Agente de atendimento usa persona, tom, Instruções e Coleções do Agente vinculado ao Canal
- [x] Canal sem Agente, ou com Agente inativo, registra a Mensagem e não abre fluxo de resposta
- [x] Resposta sai em uma única mensagem
- [x] Testes com o fake do Agente, no seam da rota; o grupo do agrupamento troca o driver de fila e executa os jobs manualmente
