# 07: Clique em botão de Template

**What to build:** O Contato toca num botão de resposta rápida de um Template ("Confirmar consulta") e o atendimento continua como se ele tivesse escrito o texto do botão. O código definido no Template fica guardado para que um Fluxo possa decidir por ele, mesmo que o texto do botão mude.

**Blocked by:** 02

**Status:** resolved

- [x] `messages` ganha `button_payload`
- [x] Mensagens `button` e `interactive` do tipo `button_reply` gravam o texto do botão em `content` e o código em `button_payload`
- [x] A Mensagem aciona o Agente normalmente e aparece na conversa como texto
- [x] Só na família oficial (exceção registrada no ADR 0004)
- [x] Teste pelo webhook com fixture real dos dois formatos
