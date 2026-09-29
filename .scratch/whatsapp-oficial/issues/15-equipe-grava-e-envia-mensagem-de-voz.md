# 15: Equipe grava e envia mensagem de voz

**What to build:** Na conversa, a pessoa da Equipe grava um áudio no navegador e o Contato recebe como mensagem de voz, em qualquer navegador moderno. Também é possível anexar um arquivo de áudio pronto.

**Blocked by:** 14

**Status:** resolved

- [x] Gravação no navegador com `opus-recorder` (dependência npm aprovada), gerando `ogg/opus`
- [x] Interface de gravar, parar, ouvir e descartar antes de enviar
- [x] Anexo de áudio pronto aceita ogg/opus, mp3, aac e mp4 até 16 MB, com validação em português
- [x] O envio usa o fluxo de mídia do ticket 14 e chega como mensagem de voz quando é ogg/opus
- [x] Sem conversão no servidor e sem `ffmpeg`
- [x] Testes pela rota da conversa para o upload de áudio; a gravação é validada manualmente
