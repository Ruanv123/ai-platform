# 15: Sub-fluxo

**What to build:** Um Fluxo chama outro como se chamasse uma função: passa parâmetros, recebe valores de volta, e o chamado não enxerga nada além disso. É o que permite "pedir CPF" existir uma vez e ser usado em cinco lugares, em vez de copiado. Inclui o limite de profundidade, para que uma cadeia de chamadas não vire recursão infinita.

**Blocked by:** 06

**Status:** completed

- [x] Nó de Sub-fluxo declara parâmetros de entrada e valores de retorno
- [x] O Sub-fluxo não enxerga as Variáveis do chamador — escopo isolado, não plano
- [x] O retorno do Sub-fluxo alimenta Variáveis do chamador
- [x] Limite de profundidade de chamadas, encerrando com motivo explícito ao estourar
- [x] Ciclo de Sub-fluxos é detectado na publicação
- [x] Nomes repetidos entre chamador e chamado não colidem, e existe teste que prova isso
