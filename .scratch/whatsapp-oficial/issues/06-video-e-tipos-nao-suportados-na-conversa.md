# 06: Vídeo e tipos não suportados na conversa

**What to build:** O vídeo que o Contato envia aparece na conversa para a Equipe assistir, e o Agente responde que não entende vídeos. Sticker, localização, cartão de contato e qualquer outro tipo aparecem como "Tipo de mensagem não suportado", sem acionar o Agente e sem processamento específico. Ver ADR 0004.

**Blocked by:** 02

**Status:** resolved

- [x] O vídeo recebido é guardado e exibido na bolha com player
- [x] O Agente recebe só que o Contato enviou um vídeo e responde que não entende vídeos
- [x] Tipos sem strategy própria são registrados com o tipo bruto e exibidos como "Tipo de mensagem não suportado"
- [x] Mensagens não suportadas não acionam o Agente
- [x] Conformidade nas duas famílias para vídeo e para tipo não suportado
