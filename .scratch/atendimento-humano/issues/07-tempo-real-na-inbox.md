# 07: Tempo real na inbox

**What to build:** Ninguém precisa recarregar a página para ver que o cliente respondeu. A conversa aberta e a lista de pendentes se atualizam sozinhas, o que importa aqui porque há uma pessoa conversando ao vivo com um cliente do outro lado.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Mensagem nova aparece na conversa aberta sem recarregar
- [ ] Lista de pendentes e contador do menu atualizam sozinhos
- [ ] Conversa assumida por outra pessoa reflete na tela de quem está olhando
- [ ] Instala `laravel/reverb` e `laravel-echo` e documenta o processo novo em produção — aprovação já dada, escopo restrito à inbox
- [ ] O dashboard de execuções continua em polling, conforme a spec de observabilidade
- [ ] Canais de broadcast são escopados pela Empresa, com teste de autorização
