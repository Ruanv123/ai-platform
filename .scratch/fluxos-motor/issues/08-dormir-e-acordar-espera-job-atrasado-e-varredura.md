# 08: Dormir e acordar — nó de Espera, job atrasado e varredura

**What to build:** Uma Execução pode parar e voltar sozinha depois de um tempo: o nó de Espera segura o Fluxo por minutos ou dias e ele retoma no instante certo. Uma varredura periódica encontra Execuções que deveriam ter acordado e não acordaram, de forma que nenhuma suma em silêncio — é o que torna possível saber quais estão presas.

**Blocked by:** 04

**Status:** completed

- [x] Nó de Espera com duração configurável; a Execução guarda o instante de acordar
- [x] Job atrasado acorda a Execução na hora certa
- [x] Varredura agendada encontra Execução com instante de acordar vencido e a retoma
- [x] Execução parada além do esperado é identificável como presa por consulta
- [x] Acordar duas vezes a mesma Execução não avança o Fluxo duas vezes
- [x] Testes usam viagem no tempo para provar a retomada sem esperar de verdade
