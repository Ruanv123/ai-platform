# 06: Responder e trocar o responsável pela Conversa

**What to build:** A Equipe responde pelo mesmo Canal com texto ou Template. Enviar numa Conversa ainda conduzida pelo Agente pausa e assume em uma operação; se outra pessoa já assumiu, é necessária uma tomada explícita e registrada.

**Blocked by:** 04 — Atualizar todas as Conversas em tempo real; 05 — Pausar e assumir o Agente.

**Status:** done

- [x] O primeiro envio humano por HTTP em Conversa com Agente ativo pausa e atribui antes de enviar; a pessoa atribuída pode enviar texto ou Template pelo Canal correto.
- [x] Outra pessoa da Equipe não envia sem tomada explícita; uma tomada concorrente tem resultado único e registra responsável anterior e novo.
- [x] Se o envio falhar, pausa e atribuição permanecem, a falha aparece na Conversa e a pessoa pode tentar novamente sem duplicar Mensagem enviada.
- [x] Texto humano respeita a janela do WhatsApp oficial; Template aprovado continua disponível fora dela. Canal indisponível impede envio.
- [x] Mensagem, falha e mudança de responsável atualizam outras telas; testes de funcionalidade usam banco real e provedor HTTP simulado.
