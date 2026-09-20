# 06: Resolução de mídia na entrada

**What to build:** Áudio, imagem e documento passam a virar texto **no momento da entrada**, com o resultado guardado na Mensagem — de forma que repetir o Histórico não reprocesse a mesma mídia. Áudio transcrito, imagem descrita, documento extraído (reusando a extração da Base de conhecimento, inclusive `spatie/pdf-to-text`). Vídeo é reconhecido e recusado com aviso explícito ao Contato. Legenda enviada junto com a mídia entra no texto resolvido. Falha na resolução não derruba o fluxo: a Mensagem fica registrada com a falha. Tudo OpenAI — `config/ai.php` passa `default_for_images` de `gemini` para `openai`. Referência: spec, user stories 9–16.

**Blocked by:** 03

**Status:** done

- [x] Áudio baixado do provedor e transcrito; a Mensagem guarda a transcrição
- [x] Imagem descrita; a Mensagem guarda a descrição e a legenda, se houver
- [x] Documento extraído em texto, reusando a extração já existente
- [x] Vídeo reconhecido, não processado, e o Contato avisado de forma clara
- [x] `default_for_images` passa para `openai` em `config/ai.php`
- [x] Falha de resolução registra a Mensagem com a falha sem interromper o processamento
- [x] Uma mídia é resolvida uma única vez, mesmo aparecendo em várias respostas
- [x] Testes com `Transcription::fake()` e `Image::fake()`, pelo seam da rota

## Comments

- Implementado no commit `e3e9754` (Implement incoming WhatsApp media resolution).
