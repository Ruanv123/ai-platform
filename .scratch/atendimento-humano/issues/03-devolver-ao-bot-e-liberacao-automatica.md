# 03: Devolver ao bot e liberação automática

**What to build:** Terminado o atendimento humano, a pessoa devolve a conversa e a Execução pausada volta a andar de onde parou. E nada fica preso por esquecimento: quem assumiu e sumiu libera a conversa sozinho depois de um tempo, e uma conversa esquecida volta ao motor em vez de morrer na lista.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Devolver ao bot retoma a Execução pausada no Nó em que ela estava
- [ ] A atribuição é liberada automaticamente por inatividade de quem assumiu
- [ ] A conversa volta ao motor automaticamente por inatividade, com prazo configurável
- [ ] Devolver uma conversa cuja Execução já expirou tem comportamento definido e testado
- [ ] A conversa devolvida sai da lista de pendentes
