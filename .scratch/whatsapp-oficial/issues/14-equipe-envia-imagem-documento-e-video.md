# 14: Equipe envia imagem, documento e vídeo

**What to build:** Na conversa, a pessoa da Equipe anexa uma imagem, um documento ou um vídeo e o Contato recebe. Arquivos fora de formato ou tamanho são recusados antes do envio com mensagem em português. A mídia enviada aparece na bolha com o status de entrega. Ver spec `../spec.md`, seção "Envio de mídia".

**Blocked by:** 01

**Status:** resolved

- [x] Os adapters de saída das duas famílias ganham envio de imagem, documento e vídeo (o de áudio fica preparado para o ticket 15)
- [x] Oficial: o arquivo é salvo no disco privado `whatsapp_media`, enviado a `POST /{phone}/media` e depois a `/messages` com o id da mídia
- [x] A Mensagem de saída guarda os `media_*` locais e aparece na bolha com a mídia e o status
- [x] Validação no front e no Form Request: imagem jpeg/png até 5 MB, documento pdf/doc(x)/xls(x)/ppt(x)/txt até 100 MB, vídeo mp4/3gp até 16 MB, com mensagens em português bem escritas
- [x] A janela de 24h e a Pausa da IA continuam valendo para o envio de mídia
- [x] Mídia recusada pela Meta depois do envio aparece como falha na bolha (via ticket 04 quando disponível)
- [x] Testes pela rota da conversa com `Http::fake` para cada tipo e para cada recusa de validação; conformidade do envio nas duas famílias
