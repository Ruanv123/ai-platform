# 09: Falhas e janela de 24 horas

**What to build:** O que acontece quando o atendimento quebra. Falha do provedor de IA ou do envio é tentada de novo com backoff pelo Horizon, **com limite de tempo total** — o limite existe para o retry não esgotar contra a parede das 24 horas da Meta. Só depois de esgotar o Contato recebe um aviso genérico e o Canal recebe `last_error`. Resposta cuja janela de 24 horas da Meta já passou não é enviada: fica registrada como não entregue. Templates aprovados não entram nesta fase. Referência: spec, user stories 32–34, 36.

**Blocked by:** 08

**Status:** done

- [x] Falha transitória de IA ou de envio é tentada de novo com backoff
- [x] Limite de tempo total do retry, menor que a janela de 24 horas
- [x] Esgotado o retry, o Contato recebe aviso genérico e o Canal recebe `last_error`
- [x] Resposta fora da janela de 24 horas não é enviada e fica registrada como não entregue
- [x] Nenhum template da Meta é implementado
- [x] Testes: falha transitória que se recupera, falha que esgota, e envio barrado pela janela
