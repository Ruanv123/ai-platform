# 09: Documentos de arquivo (PDF/DOCX/TXT)

**What to build:** Owner e Admin enviam arquivos PDF, DOCX ou TXT (até 20 MB) para uma Coleção. O arquivo original é guardado num disco configurável por env e pode ser baixado e substituído. A extração usa `pdftotext` (via `spatie/pdf-to-text`) para PDF, leitura do XML do DOCX (parágrafos, títulos, tabelas, sem dependência) e TXT com normalização de encoding para UTF-8. PDF sem texto falha com "PDF sem texto extraível". O tamanho do arquivo conta na cota. Referência: spec, user stories 33–34, 41, 46, 49 e seção Base de conhecimento.

**Blocked by:** 07 (Documentos de texto livre + ingestão + busca)

**Status:** ready-for-agent

- [ ] `spatie/pdf-to-text` instalado (compatibilidade com PHP 8.5 confirmada)
- [ ] Upload valida formato (PDF, DOCX, TXT), limite de 20 MB e cota da Empresa, com mensagens claras
- [ ] Arquivo original armazenado em disco configurável; excluir o documento remove o arquivo
- [ ] Extração de PDF, DOCX (títulos, parágrafos, tabelas) e TXT (Latin-1 → UTF-8) alimenta o pipeline existente
- [ ] PDF sem texto extraível marca `failed` com mensagem em pt-BR
- [ ] Substituir arquivo reprocessa e recalcula a cota; baixar arquivo original respeita isolamento por Empresa
- [ ] Feature tests com `Storage::fake()` e fixtures reais pequenas (PDF com texto, PDF só imagem, DOCX com título e tabela, TXT Latin-1)
- [ ] Dependência de `poppler-utils` documentada para o CI
