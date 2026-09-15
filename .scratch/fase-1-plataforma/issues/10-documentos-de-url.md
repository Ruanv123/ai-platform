# 10: Documentos de URL

**What to build:** Owner e Admin adicionam uma URL de página a uma Coleção. A plataforma baixa a página (com timeout), isola o conteúdo principal com `fivefilters/readability.php` (descartando menu, rodapé e avisos de cookies), converte para Markdown com `league/html-to-markdown` e envia ao pipeline. Página inacessível ou sem conteúdo falha com mensagem clara. O documento pode ser re-sincronizado manualmente. O tamanho do texto extraído conta na cota. Referência: spec, user stories 39–40, 46, 52.

**Blocked by:** 07 (Documentos de texto livre + ingestão + busca)

**Status:** done

- [x] `fivefilters/readability.php` e `league/html-to-markdown` instalados (compatibilidade com PHP 8.5 confirmada)
- [x] Criação de documento `url` com validação de URL http/https
- [x] Download com timeout; erros de rede, status HTTP de erro e conteúdo vazio marcam `failed` com mensagem em pt-BR
- [x] Conteúdo principal extraído e convertido para Markdown preservando títulos e listas
- [x] Botão de re-sincronizar volta para `pending` e reprocessa; tamanho do texto extraído atualiza a cota
- [x] Feature tests com `Http::fake()` e fixture HTML com menu e rodapé (verificando que o lixo de navegação não entra nos chunks), página inacessível e re-sync
